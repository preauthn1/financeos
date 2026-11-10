<?php

declare(strict_types=1);

namespace app\support;

final class CsvHelper
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, scalar|null>> $rows
     */
    public static function build(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function parse(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[(string) $header] = (string) ($data[$index] ?? '');
            }
            if (array_filter($row) !== []) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }
}
