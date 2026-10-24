<?php

namespace App\Modules\IntegrationEngine\Support;

use App\Modules\IntegrationEngine\Exceptions\InvalidIngestionFile;
use Generator;
use SplFileObject;

class CsvStreamReader
{
    /**
     * @param  array<string, string>  $headers
     * @return Generator<int, array{row_number: int, data: array<string, string|null>}>
     */
    public function rows(
        string $path,
        array $headers,
        string $delimiter,
        string $encoding,
        int $maxRows,
    ): Generator {
        if (mb_strtoupper($encoding) !== 'UTF-8') {
            throw new InvalidIngestionFile("Unsupported configured encoding [{$encoding}].");
        }

        $file = new SplFileObject($path, 'r');
        $file->setCsvControl($delimiter);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);

        $actualHeaders = $file->fgetcsv();

        if (! is_array($actualHeaders)) {
            throw new InvalidIngestionFile('The CSV header row is missing.');
        }

        $actualHeaders = array_map(
            static fn (mixed $header): string => trim((string) $header),
            $actualHeaders,
        );
        $actualHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $actualHeaders[0]) ?? $actualHeaders[0];

        if ($actualHeaders !== array_keys($headers)) {
            throw new InvalidIngestionFile('The CSV headers do not exactly match the configured dataset contract.');
        }

        $rowNumber = 1;
        $dataRows = 0;

        while (! $file->eof()) {
            $values = $file->fgetcsv();
            $rowNumber++;

            if ($values === false || $values === [null] || $values === []) {
                continue;
            }

            $dataRows++;

            if ($dataRows > $maxRows) {
                throw new InvalidIngestionFile("The CSV exceeds the configured limit of {$maxRows} rows.");
            }

            if (count($values) !== count($actualHeaders)) {
                throw new InvalidIngestionFile("CSV row {$rowNumber} has an unexpected number of fields.");
            }

            $mapped = [];

            foreach ($actualHeaders as $index => $sourceHeader) {
                $value = $values[$index];

                if ($value !== null && ! mb_check_encoding($value, 'UTF-8')) {
                    throw new InvalidIngestionFile("CSV row {$rowNumber} is not valid UTF-8.");
                }

                $canonicalField = $headers[$sourceHeader];
                $mapped[$canonicalField] = $value === null || trim($value) === '' ? null : trim($value);
            }

            yield ['row_number' => $rowNumber, 'data' => $mapped];
        }
    }
}
