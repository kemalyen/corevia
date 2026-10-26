<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Queries\VendorOrderQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderReportController extends Controller
{
    public function __invoke(Request $request, VendorOrderQuery $orders): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);

        $query = $orders->forUser($user)
            ->with('lines:id,sales_order_id,sku,description,quantity,unit_of_measure')
            ->when($validated['from'] ?? null, fn ($query, string $from) => $query->whereDate('order_date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, string $to) => $query->whereDate('order_date', '<=', $to))
            ->orderBy('order_date');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            abort_if($output === false, Response::HTTP_INTERNAL_SERVER_ERROR);
            fputcsv($output, ['Order reference', 'Date', 'Status', 'ERP state', 'Currency', 'Total', 'SKU', 'Description', 'Quantity', 'Unit']);

            $query->lazyById()->each(function (SalesOrder $order) use ($output): void {
                if ($order->lines->isEmpty()) {
                    fputcsv($output, array_map($this->escapeSpreadsheetValue(...), [
                        $order->order_number,
                        $order->order_date->toDateString(),
                        $order->status->value,
                        $order->erp_sync_state->value,
                        $order->currency,
                        $order->total_amount,
                        null,
                        null,
                        null,
                        null,
                    ]));

                    return;
                }

                foreach ($order->lines as $line) {
                    fputcsv($output, array_map($this->escapeSpreadsheetValue(...), [
                        $order->order_number,
                        $order->order_date->toDateString(),
                        $order->status->value,
                        $order->erp_sync_state->value,
                        $order->currency,
                        $order->total_amount,
                        $line->sku,
                        $line->description,
                        $line->quantity,
                        $line->unit_of_measure,
                    ]));
                }
            });

            fclose($output);
        }, 'vendor-orders-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function escapeSpreadsheetValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'{$value}" : $value;
    }
}
