<?php

namespace App\Support;

class CsvExportSanitizer
{
    /**
     * Prefix spreadsheet formula prefixes so exported text opens as plain text.
     *
     * @param  array<int, string|int|float|null>  $row
     * @return array<int, string|int|float|null>
     */
    public function sanitizeRow(array $row): array
    {
        return array_map(static function (string|int|float|null $value): string|int|float|null {
            if (! is_string($value)) {
                return $value;
            }

            return preg_match('/(*UCP)^\s*[=+\-@]/u', $value) === 1
                ? "'{$value}"
                : $value;
        }, $row);
    }
}
