<?php

namespace App\Modules\IntegrationEngine\Actions;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Models\Account;
use App\Models\Product;
use App\Modules\DataHub\Actions\UpsertAccount;
use App\Modules\DataHub\Actions\UpsertProduct;
use App\Modules\DataHub\Actions\UpsertSalesOrder;
use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Exceptions\InvalidIngestionFile;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Models\StagedSalesOrderRow;
use App\Modules\IntegrationEngine\Support\CsvStreamReader;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProcessIngestion
{
    public function __construct(
        private CsvStreamReader $reader,
        private IntegrationPaths $paths,
        private UpsertAccount $upsertAccount,
        private UpsertProduct $upsertProduct,
        private UpsertSalesOrder $upsertSalesOrder,
    ) {}

    public function handle(Ingestion $ingestion): void
    {
        if ($ingestion->status->isTerminal()) {
            if ($this->paths->processingFileExists($ingestion)) {
                $this->paths->finish($ingestion, $ingestion->status === IngestionStatus::COMPLETED);
            }

            return;
        }

        $this->prepare($ingestion);

        try {
            if ($ingestion->file_size > (int) config('integration.max_file_bytes')) {
                throw new InvalidIngestionFile('The CSV exceeds the configured file size limit.');
            }

            match ($ingestion->dataset) {
                IntegrationDataset::ACCOUNTS => $this->processAccounts($ingestion),
                IntegrationDataset::PRODUCTS => $this->processProducts($ingestion),
                IntegrationDataset::SALES_ORDERS => $this->processSalesOrders($ingestion),
            };
        } catch (InvalidIngestionFile $exception) {
            $this->addError($ingestion, null, null, 'invalid_file', $exception->getMessage());
            $ingestion->update(['last_error' => $exception->getMessage()]);
        }

        $this->finalize($ingestion->fresh());
    }

    private function prepare(Ingestion $ingestion): void
    {
        $ingestion->errors()->delete();
        $ingestion->stagedSalesOrderRows()->delete();
        $ingestion->update([
            'status' => IngestionStatus::PROCESSING,
            'attempts' => $ingestion->attempts + 1,
            'total_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'created_records' => 0,
            'updated_records' => 0,
            'last_error' => null,
            'processing_started_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
        ]);

        Log::info('Integration ingestion processing started.', ['ingestion_id' => $ingestion->id]);
    }

    private function processAccounts(Ingestion $ingestion): void
    {
        $counts = $this->emptyCounts();

        try {
            foreach ($this->rows($ingestion) as $row) {
                $counts['total_rows']++;
                $data = $this->validated($ingestion, $row['row_number'], $row['data'], [
                    'external_id' => ['required', 'string', 'max:255'],
                    'account_number' => ['required', 'string', 'max:255'],
                    'account_type' => ['required', Rule::enum(AccountType::class)],
                    'name' => ['required', 'string', 'max:255'],
                    'status' => ['required', Rule::enum(AccountStatus::class)],
                ]);

                if ($data === null) {
                    $counts['failed_rows']++;

                    continue;
                }

                $created = $this->upsertAccount->handle($data);
                $counts['valid_rows']++;
                $counts[$created ? 'created_records' : 'updated_records']++;
            }
        } finally {
            $ingestion->update($counts);
        }
    }

    private function processProducts(Ingestion $ingestion): void
    {
        $counts = $this->emptyCounts();

        try {
            foreach ($this->rows($ingestion) as $row) {
                $counts['total_rows']++;
                $data = $this->validated($ingestion, $row['row_number'], $row['data'], [
                    'external_id' => ['required', 'string', 'max:255'],
                    'sku' => ['required', 'string', 'max:255'],
                    'name' => ['required', 'string', 'max:255'],
                    'description' => ['nullable', 'string'],
                    'price' => ['required', 'decimal:0,4', 'gte:0', 'max:99999999999999.9999'],
                    'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
                ]);

                if ($data === null) {
                    $counts['failed_rows']++;

                    continue;
                }

                try {
                    $created = $this->upsertProduct->handle($data);
                } catch (DomainException $exception) {
                    $this->addError($ingestion, $row['row_number'], 'sku', 'duplicate_sku', $exception->getMessage());
                    $counts['failed_rows']++;

                    continue;
                }

                $counts['valid_rows']++;
                $counts[$created ? 'created_records' : 'updated_records']++;
            }
        } finally {
            $ingestion->update($counts);
        }
    }

    private function processSalesOrders(Ingestion $ingestion): void
    {
        $counts = $this->emptyCounts();

        try {
            foreach ($this->rows($ingestion) as $row) {
                $counts['total_rows']++;
                $data = $this->validated($ingestion, $row['row_number'], $row['data'], $this->salesOrderRules($ingestion));

                if ($data === null) {
                    $counts['failed_rows']++;

                    continue;
                }

                $ingestion->stagedSalesOrderRows()->create(['row_number' => $row['row_number'], ...$data]);
            }
        } catch (InvalidIngestionFile $exception) {
            $ingestion->update($counts);

            throw $exception;
        }

        $externalOrderIds = $ingestion->stagedSalesOrderRows()
            ->select('order_external_id')
            ->distinct()
            ->orderBy('order_external_id')
            ->pluck('order_external_id');

        foreach ($externalOrderIds as $externalOrderId) {
            $rows = $ingestion->stagedSalesOrderRows()
                ->where('order_external_id', $externalOrderId)
                ->orderBy('row_number')
                ->get();

            if (! $this->validateOrderGroup($ingestion, $rows)) {
                $counts['failed_rows'] += $rows->count();

                continue;
            }

            try {
                $result = $this->upsertSalesOrder->handle($this->toSalesOrderData($rows));
            } catch (DomainException $exception) {
                $this->addError(
                    $ingestion,
                    $rows->first()->row_number,
                    'order_external_id',
                    'invalid_relation',
                    $exception->getMessage(),
                );
                $counts['failed_rows'] += $rows->count();

                continue;
            }

            $counts['valid_rows'] += $rows->count();
            $counts['created_records'] += $result['created'];
            $counts['updated_records'] += $result['updated'];
        }

        $ingestion->update($counts);
    }

    /**
     * @return iterable<int, array{row_number: int, data: array<string, string|null>}>
     */
    private function rows(Ingestion $ingestion): iterable
    {
        $headers = config("integration.datasets.{$ingestion->dataset->value}.headers");

        if (! is_array($headers)) {
            throw new InvalidIngestionFile('The ingestion dataset is no longer configured.');
        }

        return $this->reader->rows(
            $this->paths->processingPath($ingestion),
            $headers,
            (string) config('integration.delimiter'),
            (string) config('integration.encoding'),
            (int) config('integration.max_rows'),
        );
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, string|null>|null
     */
    private function validated(Ingestion $ingestion, int $rowNumber, array $data, array $rules): ?array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->passes()) {
            return $validator->validated();
        }

        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($ingestion, $rowNumber, $field, 'validation', $message);
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function salesOrderRules(Ingestion $ingestion): array
    {
        $dateFormat = (string) config('integration.date_format', 'Y-m-d');

        return [
            'order_external_id' => ['required', 'string', 'max:255'],
            'order_number' => ['required', 'string', 'max:255'],
            'order_date' => ['required', "date_format:{$dateFormat}"],
            'account_external_id' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'total_amount' => ['required', 'decimal:0,2', 'max:9999999999999999.99'],
            'line_external_id' => ['required', 'string', 'max:255'],
            'line_number' => ['nullable', 'string', 'max:255'],
            'product_external_id' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999999.9999'],
            'unit_price' => ['required', 'decimal:0,2', 'max:9999999999999999.99'],
            'line_total' => ['required', 'decimal:0,2', 'max:9999999999999999.99'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:255'],
            'unit_of_measure' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @param  Collection<int, StagedSalesOrderRow>  $rows
     */
    private function validateOrderGroup(Ingestion $ingestion, Collection $rows): bool
    {
        $valid = true;
        $first = $rows->first();
        $headerFields = ['order_number', 'order_date', 'account_external_id', 'status', 'currency', 'total_amount'];

        foreach ($rows as $row) {
            foreach ($headerFields as $field) {
                if ((string) $row->{$field} !== (string) $first->{$field}) {
                    $this->addError(
                        $ingestion,
                        $row->row_number,
                        $field,
                        'inconsistent_order_header',
                        "Order [{$first->order_external_id}] has inconsistent {$field} values.",
                    );
                    $valid = false;
                }
            }
        }

        $duplicateLineIds = $rows->groupBy('line_external_id')->filter(fn (Collection $lines): bool => $lines->count() > 1);

        foreach ($duplicateLineIds as $lineExternalId => $duplicateRows) {
            foreach ($duplicateRows as $row) {
                $this->addError(
                    $ingestion,
                    $row->row_number,
                    'line_external_id',
                    'duplicate_order_line',
                    "Line [{$lineExternalId}] appears more than once in the order.",
                );
            }
            $valid = false;
        }

        $accountExists = Account::query()
            ->where('external_id', $first->account_external_id)
            ->exists();

        if (! $accountExists) {
            $this->addError(
                $ingestion,
                $first->row_number,
                'account_external_id',
                'unknown_account',
                "Account [{$first->account_external_id}] does not exist.",
            );
            $valid = false;
        }

        $products = Product::query()
            ->whereIn('external_id', $rows->pluck('product_external_id')->unique())
            ->get()
            ->keyBy('external_id');

        foreach ($rows as $row) {
            $product = $products->get($row->product_external_id);

            if ($product === null) {
                $this->addError(
                    $ingestion,
                    $row->row_number,
                    'product_external_id',
                    'unknown_product',
                    "Product [{$row->product_external_id}] does not exist.",
                );
                $valid = false;

                continue;
            }

            if ($row->sku !== null && $row->sku !== $product->sku) {
                $this->addError(
                    $ingestion,
                    $row->row_number,
                    'sku',
                    'product_sku_mismatch',
                    "SKU [{$row->sku}] does not match product [{$row->product_external_id}].",
                );
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * @param  Collection<int, StagedSalesOrderRow>  $rows
     * @return array<string, mixed>
     */
    private function toSalesOrderData(Collection $rows): array
    {
        $first = $rows->first();

        return [
            'external_id' => $first->order_external_id,
            'order_number' => $first->order_number,
            'order_date' => $first->order_date->format('Y-m-d'),
            'account_external_id' => $first->account_external_id,
            'status' => $first->status,
            'currency' => $first->currency,
            'total_amount' => $first->total_amount,
            'lines' => $rows->map(fn (StagedSalesOrderRow $row): array => [
                'external_id' => $row->line_external_id,
                'line_number' => $row->line_number,
                'product_external_id' => $row->product_external_id,
                'quantity' => $row->quantity,
                'unit_price' => $row->unit_price,
                'total_price' => $row->line_total,
                'description' => $row->description,
                'sku' => $row->sku,
                'unit_of_measure' => $row->unit_of_measure,
            ])->all(),
        ];
    }

    /**
     * @return array{total_rows: int, valid_rows: int, failed_rows: int, created_records: int, updated_records: int}
     */
    private function emptyCounts(): array
    {
        return [
            'total_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'created_records' => 0,
            'updated_records' => 0,
        ];
    }

    private function addError(
        Ingestion $ingestion,
        ?int $rowNumber,
        ?string $field,
        string $code,
        string $message,
    ): void {
        $ingestion->errors()->create([
            'row_number' => $rowNumber,
            'field' => $field,
            'code' => $code,
            'message' => $message,
        ]);
    }

    private function finalize(Ingestion $ingestion): void
    {
        if ($ingestion->total_rows === 0 && ! $ingestion->errors()->exists()) {
            $this->addError($ingestion, null, null, 'empty_file', 'The CSV contains no data rows.');
        }

        $hasErrors = $ingestion->errors()->exists();
        $status = match (true) {
            ! $hasErrors && $ingestion->valid_rows > 0 => IngestionStatus::COMPLETED,
            $ingestion->valid_rows > 0 => IngestionStatus::PARTIALLY_FAILED,
            default => IngestionStatus::FAILED,
        };

        $ingestion->update([
            'status' => $status,
            'completed_at' => now(),
            'failed_at' => $status === IngestionStatus::COMPLETED ? null : now(),
        ]);

        $this->paths->finish($ingestion, $status === IngestionStatus::COMPLETED);

        Log::info('Integration ingestion processing finished.', [
            'ingestion_id' => $ingestion->id,
            'status' => $status->value,
            'total_rows' => $ingestion->total_rows,
            'valid_rows' => $ingestion->valid_rows,
            'failed_rows' => $ingestion->failed_rows,
        ]);
    }
}
