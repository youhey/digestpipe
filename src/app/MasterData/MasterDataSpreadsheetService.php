<?php

namespace App\MasterData;

use App\Models\FeedSource;
use App\Models\SelectionKeyword;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Feed Source と Selection Keyword master data の XLSX 入出力を扱います。
 */
class MasterDataSpreadsheetService
{
    /** @var list<string> */
    private const FEED_SOURCE_COLUMNS = [
        'key',
        'name',
        'url',
        'language',
        'enabled',
        'analysis_enabled',
        'tier',
        'category',
        'sort_order',
    ];

    /** @var list<string> */
    private const SELECTION_KEYWORD_COLUMNS = [
        'keyword',
        'score',
        'enabled',
        'locale',
        'category',
        'notes',
        'sort_order',
        'match_mode',
    ];

    /**
     * Feed Source master data を XLSX として出力します。
     */
    public function exportFeedSources(): string
    {
        $rows = [];

        foreach (FeedSource::all()->sortBy([['sort_order', 'asc'], ['key', 'asc']]) as $source) {
            $rows[] = [
                $source->key,
                $source->name,
                $source->url,
                $source->language,
                $source->enabled,
                $source->analysis_enabled,
                $source->tier,
                $source->category,
                $source->sort_order,
            ];
        }

        return $this->writeWorkbook(self::FEED_SOURCE_COLUMNS, $rows);
    }

    /**
     * Selection Keyword master data を XLSX として出力します。
     */
    public function exportSelectionKeywords(string $type): string
    {
        $this->assertKeywordType($type);
        $rows = [];

        foreach (SelectionKeyword::query()->where('type', $type)->get()->sortBy([['sort_order', 'asc'], ['keyword', 'asc']]) as $keyword) {
            $rows[] = [
                $keyword->keyword,
                $keyword->score,
                $keyword->enabled,
                $keyword->locale,
                $keyword->category,
                $keyword->notes,
                $keyword->sort_order,
                $keyword->match_mode,
            ];
        }

        return $this->writeWorkbook(self::SELECTION_KEYWORD_COLUMNS, $rows);
    }

    /**
     * Feed Source master data を XLSX から取り込みます。
     */
    public function importFeedSources(string $path): MasterDataImportResult
    {
        $rows = $this->readWorkbook($path, self::FEED_SOURCE_COLUMNS);

        return DB::transaction(function () use ($rows): MasterDataImportResult {
            $result = new MasterDataImportResult();

            foreach ($rows as $rowNumber => $row) {
                if ($this->isBlankRow($row)) {
                    ++$result->skipped;

                    continue;
                }

                $key = $this->requiredString($row, 'key', $rowNumber);
                $source = FeedSource::query()->where('key', $key)->first();

                if (! $source instanceof FeedSource) {
                    $source = new FeedSource(['key' => $key]);
                    ++$result->created;
                } else {
                    ++$result->updated;
                }

                $source->fill([
                    'name' => $this->requiredString($row, 'name', $rowNumber),
                    'url' => $this->requiredString($row, 'url', $rowNumber),
                    'language' => $this->requiredString($row, 'language', $rowNumber),
                    'enabled' => $this->boolean($row, 'enabled', $rowNumber),
                    'analysis_enabled' => $this->boolean($row, 'analysis_enabled', $rowNumber),
                    'tier' => $this->requiredString($row, 'tier', $rowNumber),
                    'category' => $this->requiredString($row, 'category', $rowNumber),
                    'sort_order' => $this->integer($row, 'sort_order', $rowNumber, 100),
                ]);
                $source->save();
            }

            return $result;
        });
    }

    /**
     * Selection Keyword master data を XLSX から取り込みます。
     */
    public function importSelectionKeywords(string $path, string $type): MasterDataImportResult
    {
        $this->assertKeywordType($type);
        $rows = $this->readWorkbook($path, self::SELECTION_KEYWORD_COLUMNS);

        return DB::transaction(function () use ($rows, $type): MasterDataImportResult {
            $result = new MasterDataImportResult();

            foreach ($rows as $rowNumber => $row) {
                if ($this->isBlankRow($row)) {
                    ++$result->skipped;

                    continue;
                }

                $value = $this->requiredString($row, 'keyword', $rowNumber);
                $keyword = SelectionKeyword::query()->where('type', $type)->where('keyword', $value)->first();

                if (! $keyword instanceof SelectionKeyword) {
                    $keyword = new SelectionKeyword(['type' => $type, 'keyword' => $value]);
                    ++$result->created;
                } else {
                    ++$result->updated;
                }

                $keyword->fill([
                    'score' => $this->integer($row, 'score', $rowNumber, 0),
                    'enabled' => $this->boolean($row, 'enabled', $rowNumber),
                    'locale' => $this->requiredString($row, 'locale', $rowNumber),
                    'category' => $this->nullableString($row, 'category'),
                    'notes' => $this->nullableString($row, 'notes'),
                    'sort_order' => $this->integer($row, 'sort_order', $rowNumber, 100),
                    'match_mode' => $this->requiredString($row, 'match_mode', $rowNumber),
                ]);
                $keyword->save();
            }

            return $result;
        });
    }

    /**
     * @param list<string> $columns
     * @param list<list<bool|float|int|string|null>> $rows
     */
    private function writeWorkbook(array $columns, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'digestpipe-master-data-');

        if ($path === false) {
            throw new InvalidArgumentException('Temporary XLSX file could not be created.');
        }

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($columns, (new Style())->setFontBold()));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
        $contents = file_get_contents($path);
        unlink($path);

        if ($contents === false) {
            throw new InvalidArgumentException('Temporary XLSX file could not be read.');
        }

        return $contents;
    }

    /**
     * @param list<string> $requiredColumns
     *
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    private function readWorkbook(string $path, array $requiredColumns): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('Import file was not found.');
        }

        $reader = new Reader();
        $reader->open($path);
        $headers = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rowNumber = 0;

            foreach ($sheet->getRowIterator() as $row) {
                ++$rowNumber;
                $values = $this->rowValues($row);

                if ($rowNumber === 1) {
                    $headers = $this->headers($values, $requiredColumns);

                    continue;
                }

                if ($headers === null) {
                    throw new InvalidArgumentException('Import file header row is missing.');
                }

                $rows[$rowNumber] = $this->combineRow($headers, $values);
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @return list<bool|float|int|string|null>
     */
    private function rowValues(Row $row): array
    {
        $values = [];

        foreach ($row->getCells() as $cell) {
            $value = $cell->getValue();

            if ($value instanceof DateInterval || $value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $values[] = is_string($value) ? trim($value) : $value;
        }

        return $values;
    }

    /**
     * @param list<bool|float|int|string|null> $values
     * @param list<string> $requiredColumns
     *
     * @return list<string>
     */
    private function headers(array $values, array $requiredColumns): array
    {
        $headers = [];

        foreach ($values as $value) {
            $headers[] = is_string($value) ? trim($value) : '';
        }

        foreach ($requiredColumns as $column) {
            if (! in_array($column, $headers, true)) {
                throw new InvalidArgumentException('Import file is missing required column: ' . $column);
            }
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     * @param list<bool|float|int|string|null> $values
     *
     * @return array<string, bool|float|int|string|null>
     */
    private function combineRow(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = $values[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param array<string, bool|float|int|string|null> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, bool|float|int|string|null> $row
     */
    private function requiredString(array $row, string $column, int $rowNumber): string
    {
        $value = $row[$column] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf('Row %d column %s is required.', $rowNumber, $column));
    }

    /**
     * @param array<string, bool|float|int|string|null> $row
     */
    private function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? trim($value) : (string) $value;
    }

    /**
     * @param array<string, bool|float|int|string|null> $row
     */
    private function integer(array $row, string $column, int $rowNumber, int $default): int
    {
        $value = $row[$column] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException(sprintf('Row %d column %s must be an integer.', $rowNumber, $column));
    }

    /**
     * @param array<string, bool|float|int|string|null> $row
     */
    private function boolean(array $row, string $column, int $rowNumber): bool
    {
        $value = $row[$column] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'y', 'on', 'enabled' => true,
                '0', 'false', 'no', 'n', 'off', 'disabled' => false,
                default => throw new InvalidArgumentException(sprintf('Row %d column %s must be a boolean.', $rowNumber, $column)),
            };
        }

        throw new InvalidArgumentException(sprintf('Row %d column %s must be a boolean.', $rowNumber, $column));
    }

    private function assertKeywordType(string $type): void
    {
        if (! in_array($type, ['positive', 'negative'], true)) {
            throw new InvalidArgumentException('Selection keyword type is invalid.');
        }
    }
}
