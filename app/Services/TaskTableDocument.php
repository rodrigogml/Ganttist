<?php

namespace App\Services;

use InvalidArgumentException;

final class TaskTableDocument
{
    public const MAX_ROWS = 200;

    public const MAX_COLUMNS = 100;

    public const MAX_BYTES = 512000;

    /** @param array<string, mixed> $document @return array<string, mixed> */
    public function normalize(array $document): array
    {
        $sheets = $document['sheets'] ?? null;
        if (! is_array($sheets) || count($sheets) !== 1) {
            throw new InvalidArgumentException('A tabela deve conter exatamente uma aba.');
        }

        if (array_is_list($sheets)) {
            $normalized = $this->normalizeLegacyDocument($sheets);
        } else {
            $normalized = $this->normalizeUniverDocument($document, $sheets);
        }
        if (! $this->hasContent($normalized)) {
            throw new InvalidArgumentException('A tabela deve conter ao menos um valor, fórmula ou formatação.');
        }
        $encoded = json_encode($normalized, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_BYTES) {
            throw new InvalidArgumentException('A tabela excede o limite de 500 KB.');
        }

        return $normalized;
    }

    /** @param array<int, mixed> $sheets @return array<string, mixed> */
    private function normalizeLegacyDocument(array $sheets): array
    {
        $sheet = $sheets[0] ?? null;
        $rows = is_array($sheet) ? ($sheet['rows'] ?? []) : null;
        $columns = is_array($sheet) ? ($sheet['columns'] ?? []) : null;
        if (! is_array($rows) || ! is_array($columns) || count($rows) > self::MAX_ROWS || count($columns) > self::MAX_COLUMNS) {
            throw new InvalidArgumentException('A tabela excede o limite de 200 linhas ou 100 colunas.');
        }

        return ['version' => 1, 'sheets' => [[...$sheet, 'rows' => array_values($rows), 'columns' => array_values($columns)]]];
    }

    /** @param array<string, mixed> $document @param array<string, mixed> $sheets @return array<string, mixed> */
    private function normalizeUniverDocument(array $document, array $sheets): array
    {
        $sheetOrder = $document['sheetOrder'] ?? null;
        if (! is_array($sheetOrder) || count($sheetOrder) !== 1 || ! is_string($sheetOrder[0] ?? null)) {
            throw new InvalidArgumentException('A tabela do Univer deve conter exatamente uma aba ordenada.');
        }
        $sheet = $sheets[$sheetOrder[0]] ?? null;
        $rowCount = is_array($sheet) ? ($sheet['rowCount'] ?? null) : null;
        $columnCount = is_array($sheet) ? ($sheet['columnCount'] ?? null) : null;
        if (! is_int($rowCount) || ! is_int($columnCount) || $rowCount < 1 || $columnCount < 1 || $rowCount > self::MAX_ROWS || $columnCount > self::MAX_COLUMNS) {
            throw new InvalidArgumentException('A tabela excede o limite de 200 linhas ou 100 colunas.');
        }

        return $document;
    }

    /** @param array<string, mixed> $document */
    private function hasContent(array $document): bool
    {
        foreach ($document['sheets'] ?? [] as $sheet) {
            if (! is_array($sheet)) {
                continue;
            }
            if (($sheet['mergeData'] ?? []) !== [] || ($sheet['rowData'] ?? []) !== [] || ($sheet['columnData'] ?? []) !== []) {
                return true;
            }
            foreach ($sheet['cellData'] ?? $sheet['rows'] ?? [] as $row) {
                foreach (is_array($row) ? ($row['cells'] ?? $row) : [] as $cell) {
                    if (! is_array($cell)) {
                        if ($cell !== null && $cell !== '') {
                            return true;
                        }

                        continue;
                    }
                    if (($cell['v'] ?? $cell['value'] ?? null) !== null && ($cell['v'] ?? $cell['value'] ?? '') !== '') {
                        return true;
                    }
                    if (! empty($cell['f']) || ! empty($cell['s']) || ! empty($cell['style'])) {
                        return true;
                    }
                }
            }
        }

        return ! empty($document['styles']);
    }
}
