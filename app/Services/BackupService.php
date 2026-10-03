<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Database backup and restore without needing mysqldump on the server.
 * A backup is one JSON file holding every row of the business tables.
 * Uploaded photos are NOT inside the file (they are files, not database rows).
 */
class BackupService
{
    public const TABLES = ['users', 'products', 'product_variants', 'sales', 'sale_items', 'stock_movements'];

    private const DIR = 'backups';

    /** Writes a backup file on the server and returns its file name. */
    public function create(string $suffix = ''): string
    {
        $payload = [
            'format'     => 'treklite-backup',
            'version'    => 1,
            'created_at' => now()->toIso8601String(),
            'tables'     => [],
        ];

        foreach (self::TABLES as $table) {
            $payload['tables'][$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        $name = 'treklite-backup-' . now()->format('Ymd-His') . $suffix . '.json';
        Storage::disk('local')->put(self::DIR . '/' . $name, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $name;
    }

    /** Backups stored on the server, newest first. */
    public function list(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => $this->isValidName(basename($f)))
            ->map(fn ($f) => [
                'name'     => basename($f),
                'size'     => $disk->size($f),
                'modified' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($f)),
            ])
            ->sortByDesc('modified')
            ->values()
            ->all();
    }

    /** Full path of a stored backup, or null if the name is not a real backup. */
    public function path(string $name): ?string
    {
        if (! $this->isValidName($name) || ! Storage::disk('local')->exists(self::DIR . '/' . $name)) {
            return null;
        }

        return Storage::disk('local')->path(self::DIR . '/' . $name);
    }

    /**
     * Replaces ALL business data with the contents of a backup file.
     * Everything happens in one transaction: if anything is wrong, nothing changes.
     *
     * @return array<string,int> number of rows restored per table
     */
    public function restore(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ($data['format'] ?? null) !== 'treklite-backup' || ! is_array($data['tables'] ?? null)) {
            throw new InvalidArgumentException('This is not a valid Treklite backup file.');
        }

        // Validate everything BEFORE touching the database.
        foreach (self::TABLES as $table) {
            if (! isset($data['tables'][$table]) || ! is_array($data['tables'][$table])) {
                throw new InvalidArgumentException("The backup file is missing the \"{$table}\" table.");
            }
            $allowed = Schema::getColumnListing($table);
            foreach ($data['tables'][$table] as $row) {
                if (! is_array($row) || array_diff(array_keys($row), $allowed)) {
                    throw new InvalidArgumentException("The backup file does not match the current database structure (table \"{$table}\").");
                }
            }
        }

        $counts = [];

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach (self::TABLES as $table) {
                DB::table($table)->delete();
            }
            foreach (self::TABLES as $table) {
                foreach (array_chunk($data['tables'][$table], 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $counts[$table] = count($data['tables'][$table]);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            throw $e;
        }

        return $counts;
    }

    private function isValidName(string $name): bool
    {
        return (bool) preg_match('/^treklite-backup-\d{8}-\d{6}(-before-restore)?\.json$/', $name);
    }
}
