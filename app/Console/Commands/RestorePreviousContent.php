<?php

namespace App\Console\Commands;

use App\Services\DevelopmentDatabaseGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use RuntimeException;
use SQLite3;

#[Signature('content:restore-previous {--backup= : Path to the verified SQLite backup}')]
#[Description('Restore the approved menu and table records from the preserved SQLite database')]
class RestorePreviousContent extends Command
{
    private const string SOURCE_HASH = 'A08F52B25B415E0C0591A3C26F915BA31C17CFACE4B44F2DE0D62D77F6EA157C';

    private const string BACKUP_HASH = '508188600DEC2035E884603CFC4430EB71F069F235E9EB9B8B5456FBA9B12922';

    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'menu_categories' => ['id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at'],
        'menu_items' => ['id', 'menu_category_id', 'name', 'slug', 'description', 'price', 'image_path', 'spice_level', 'protein_type', 'dietary_type', 'flavor_profile', 'is_available', 'is_featured', 'sort_order', 'deleted_at', 'created_at', 'updated_at'],
        'tables' => ['id', 'table_number', 'capacity', 'status', 'created_at', 'updated_at'],
    ];

    public function handle(DevelopmentDatabaseGuard $guard): int
    {
        $backup = $this->option('backup');
        $source = database_path('database.sqlite');
        if (! is_string($backup) || ! is_file($source) || ! is_file($backup)
            || strtoupper((string) hash_file('sha256', $source)) !== self::SOURCE_HASH
            || strtoupper((string) hash_file('sha256', $backup)) !== self::BACKUP_HASH) {
            throw new RuntimeException('The preserved SQLite source or backup does not match the approved baseline.');
        }

        $connection = $guard->verify();
        $this->assertEmptyTarget($connection);

        $sqlite = new SQLite3($source, SQLITE3_OPEN_READONLY);
        $sqlite->enableExceptions(true);
        $sqlite->exec('PRAGMA query_only=ON');
        if ($sqlite->querySingle('PRAGMA quick_check') !== 'ok') {
            throw new RuntimeException('SQLite source integrity check failed.');
        }

        $categories = $this->readRows($sqlite, 'menu_categories');
        $items = $this->readRows($sqlite, 'menu_items');
        $tables = $this->readRows($sqlite, 'tables');
        $this->assertSource($categories, $items, $tables);

        $guard->verify();
        $connection->transaction(function () use ($connection, $categories, $items, $tables): void {
            $this->assertEmptyTarget($connection);
            foreach (['menu_categories' => $categories, 'menu_items' => $items, 'tables' => $tables] as $table => $rows) {
                foreach ($rows as $row) {
                    $connection->table($table)->insert($row);
                }
                $this->assertImportedRows($connection, $table, $rows);
            }
        }, 1);

        foreach (['menu_categories' => $categories, 'menu_items' => $items, 'tables' => $tables] as $table => $rows) {
            $this->info($table.': '.count($rows).' inserted; IDs '.implode(',', array_column($rows, 'id')));
        }

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function readRows(SQLite3 $sqlite, string $table): array
    {
        $columns = implode(', ', self::COLUMNS[$table]);
        $result = $sqlite->query("SELECT $columns FROM $table ORDER BY id");
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $tables
     */
    private function assertSource(array $categories, array $items, array $tables): void
    {
        if (count($categories) !== 6 || count($items) !== 35 || count($tables) !== 11) {
            throw new RuntimeException('SQLite source record counts differ from the approved inventory.');
        }

        $categoryIds = array_column($categories, 'id');
        $featured = [];
        $available = 0;
        foreach ($items as $item) {
            if (! in_array($item['menu_category_id'], $categoryIds, true)) {
                throw new RuntimeException('A menu item has no source category.');
            }
            if ($item['is_available']) {
                $available++;
            }
            if ($item['is_featured'] && $item['is_available']) {
                $featured[(int) $item['sort_order']] = $item['name'];
            }
            if ($item['image_path'] !== null
                && (! preg_match('#^menu/[a-z0-9]+\.jpg$#', $item['image_path'])
                    || ! is_file(storage_path('app/public/'.$item['image_path'])))) {
                throw new RuntimeException('A referenced menu image is missing or outside the approved asset directory.');
            }
        }
        ksort($featured);
        if ($available !== 27 || array_values($featured) !== ['Nasi Lemak', 'Rendang', 'Roti Canai', 'Roti Telur', 'Cendol']
            || array_keys($featured) !== [1, 2, 3, 4, 5]) {
            throw new RuntimeException('Menu availability or featured ordering differs from the approved inventory.');
        }

        $capacities = [4, 2, 4, 4, 4, 6, 6, 8, 8, 10, 3];
        foreach ($tables as $index => $table) {
            if ($table['table_number'] !== 'T'.($index + 1)
                || (int) $table['capacity'] !== $capacities[$index]
                || $table['status'] !== 'available') {
                throw new RuntimeException('Table numbers, capacities or statuses differ from the approved inventory.');
            }
        }
    }

    private function assertEmptyTarget(Connection $connection): void
    {
        foreach (['menu_categories', 'menu_items', 'tables', 'order_items', 'reservation_foods', 'reservations', 'orders', 'payments'] as $table) {
            if ($connection->table($table)->exists()) {
                throw new RuntimeException('The development database contains records that require manual review before restoration.');
            }
        }
    }

    /** @param list<array<string, mixed>> $expected */
    private function assertImportedRows(Connection $connection, string $table, array $expected): void
    {
        $actual = $connection->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        if (count($actual) !== count($expected)) {
            throw new RuntimeException('Imported '.$table.' record count differs from the source.');
        }
        foreach ($expected as $index => $row) {
            foreach ($row as $column => $value) {
                $stored = $actual[$index][$column];
                if ($column === 'price') {
                    $value = number_format((float) $value, 2, '.', '');
                }
                if ($value !== null && (string) $value !== (string) $stored || $value === null && $stored !== null) {
                    throw new RuntimeException('Imported '.$table.' data differs from the source.');
                }
            }
        }
    }
}
