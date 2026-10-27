<?php

namespace App\Services;

use App\Models\Allergen;
use Illuminate\Database\Connection;
use PDO;
use RuntimeException;
use Tests\MySqlTestDatabaseGuard;

class DemoCatalogTransfer
{
    public const COLUMNS = [
        'menu_categories' => ['id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at'],
        'menu_items' => ['id', 'menu_category_id', 'name', 'slug', 'description', 'price', 'image_path', 'spice_level', 'protein_type', 'dietary_type', 'flavor_profile', 'is_available', 'is_featured', 'sort_order', 'deleted_at', 'created_at', 'updated_at'],
        'tables' => ['id', 'table_number', 'capacity', 'status', 'created_at', 'updated_at'],
    ];

    public const TABLES = ['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'menu_categories', 'menu_items', 'migrations', 'order_items', 'orders', 'password_reset_tokens', 'payments', 'reservation_foods', 'reservations', 'sessions', 'tables', 'users'];

    private const CURRENT_TEST_TABLES = ['allergens', 'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'menu_categories', 'menu_item_allergen_assessments', 'menu_items', 'migrations', 'order_items', 'orders', 'password_reset_tokens', 'payments', 'reservation_foods', 'reservations', 'sessions', 'tables', 'users'];

    /** Pins the reviewed export without embedding business records. */
    public const APPROVED_SHA256 = '123011805b4fd2ce3193fcd71d007a415cdbda4d020d4064c4613d525309ff46';

    /** @return array<string, mixed> */
    public function export(PDO $source, string $publicStorage, string $layout): array
    {
        $this->assertSource($source);
        if ($source->inTransaction()) {
            throw new RuntimeException('Catalog safety: source already has a transaction.');
        }
        $source->exec('SET SESSION TRANSACTION READ ONLY');
        $source->beginTransaction();
        try {
            if ((int) $source->query('SELECT @@session.transaction_read_only')->fetchColumn() !== 1) {
                throw new RuntimeException('Catalog safety: source is not read-only.');
            }
            $rows = $this->readRows($source);
            $assets = [];
            foreach ($rows['menu_items'] as $item) {
                $path = $item['image_path'];
                if ($path === null) {
                    continue;
                }
                $this->assertAssetPath($path);
                $file = $publicStorage.'/'.$path;
                $resolved = realpath($file);
                if ($resolved === false || str_replace('\\', '/', $resolved) !== str_replace('\\', '/', realpath($publicStorage).'/'.$path)) {
                    throw new RuntimeException('Catalog safety: missing or redirected source image.');
                }
                $bytes = file_get_contents($file);
                if ($bytes === false) {
                    throw new RuntimeException('Catalog safety: unreadable image.');
                }
                $assets[$path] = ['sha256' => hash('sha256', $bytes), 'bytes' => base64_encode($bytes)];
            }
            ksort($assets);
            $bundle = ['format' => 1, 'source' => 'le_miting_dev', 'rows' => $rows, 'assets' => $assets, 'layout_sha256' => hash_file('sha256', $layout)];
            $this->validate($bundle);

            return $bundle;
        } finally {
            $source->rollBack();
        }
    }

    /** DevelopmentDatabaseGuard also checks environment, configuration and privileges at the CLI boundary. */
    public function assertSource(PDO $source): void
    {
        $identity = $source->query('SELECT DATABASE() AS db, CURRENT_USER() AS account, VERSION() AS version, @@version_comment AS product, @@port AS port, @@session.time_zone AS timezone, @@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
        if ($source->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $source->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP'
            || ($identity['db'] ?? null) !== 'le_miting_dev' || ($identity['account'] ?? null) !== 'le_miting_dev@127.0.0.1'
            || ($identity['version'] ?? null) !== '26.7.0' || ($identity['product'] ?? null) !== 'MySQL Community Server - GPL'
            || (int) ($identity['port'] ?? 0) !== 3307 || ($identity['timezone'] ?? null) !== '+00:00'
            || strtolower(rtrim(str_replace('\\', '/', $identity['datadir'] ?? ''), '/')) !== 'c:/programdata/mysql/mysql server 26.7/data') {
            throw new RuntimeException('Catalog safety: wrong source identity.');
        }
    }

    /** @return array<string, list<array<string, string|null>>> */
    public function readRows(PDO $connection, bool $allowCurrentTestRevision = false): array
    {
        $result = [];
        foreach (self::COLUMNS as $table => $columns) {
            $rows = $connection->query('SELECT * FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $result[$table] = array_map(function (array $row) use ($table, $columns, $allowCurrentTestRevision): array {
                if ($allowCurrentTestRevision && $table === 'menu_items') {
                    if (array_keys($row) !== [...$columns, 'allergen_assessment_revision']
                        || (string) $row['allergen_assessment_revision'] !== '0') {
                        throw new RuntimeException('Catalog safety: unreviewed catalog columns.');
                    }

                    unset($row['allergen_assessment_revision']);
                }

                if (array_keys($row) !== $columns) {
                    throw new RuntimeException('Catalog safety: unreviewed catalog columns.');
                }

                return array_map(fn ($value) => $value === null ? null : (string) $value, $row);
            }, $rows);
        }

        return $result;
    }

    /** @param array<string, mixed> $bundle */
    public function validate(array $bundle): void
    {
        if (array_keys($bundle) !== ['format', 'source', 'rows', 'assets', 'layout_sha256'] || $bundle['format'] !== 1
            || $bundle['source'] !== 'le_miting_dev' || array_keys($bundle['rows']) !== array_keys(self::COLUMNS)
            || ! preg_match('/^[a-f0-9]{64}$/', $bundle['layout_sha256'])) {
            throw new RuntimeException('Catalog safety: unsupported export shape or provenance.');
        }
        foreach (['menu_categories' => 6, 'menu_items' => 35, 'tables' => 11] as $table => $count) {
            $rows = $bundle['rows'][$table];
            if (count($rows) !== $count || count(array_unique(array_column($rows, 'id'))) !== $count) {
                throw new RuntimeException('Catalog safety: count or identifier mismatch.');
            }
            foreach ($rows as $row) {
                if (array_keys($row) !== self::COLUMNS[$table] || ! preg_match('/^[1-9][0-9]*$/', $row['id'])) {
                    throw new RuntimeException('Catalog safety: unreviewed columns or invalid identifier.');
                }
            }
        }
        $categories = array_column($bundle['rows']['menu_categories'], null, 'id');
        $visible = $unavailable = 0;
        $featured = $paths = [];
        foreach ($bundle['rows']['menu_items'] as $item) {
            if (! isset($categories[$item['menu_category_id']])) {
                throw new RuntimeException('Catalog safety: missing category relationship.');
            }
            $visible += $item['is_available'] === '1' && $item['deleted_at'] === null && $categories[$item['menu_category_id']]['is_active'] === '1' ? 1 : 0;
            $unavailable += $item['is_available'] === '0' ? 1 : 0;
            if ($item['is_featured'] === '1') {
                $featured[] = [(int) $item['sort_order'], $item['name']];
            }
            if ($item['image_path'] !== null) {
                $this->assertAssetPath($item['image_path']);
                $paths[] = $item['image_path'];
            }
        }
        sort($featured);
        if ($visible !== 27 || $unavailable !== 8 || $featured !== [[1, 'Nasi Lemak'], [2, 'Rendang'], [3, 'Roti Canai'], [4, 'Roti Telur'], [5, 'Cendol']]) {
            throw new RuntimeException('Catalog safety: visibility or featured ordering mismatch.');
        }
        $capacities = array_column($bundle['rows']['tables'], 'capacity', 'table_number');
        foreach ([4, 2, 4, 4, 4, 6, 6, 8, 8, 10, 3] as $index => $capacity) {
            if (($capacities['T'.($index + 1)] ?? null) !== (string) $capacity) {
                throw new RuntimeException('Catalog safety: approved table capacities differ.');
            }
        }
        $paths = array_values(array_unique($paths));
        sort($paths);
        if (array_keys($bundle['assets']) !== $paths) {
            throw new RuntimeException('Catalog safety: missing or extra assets.');
        }
        foreach ($bundle['assets'] as $asset) {
            $bytes = base64_decode($asset['bytes'], true);
            if (array_keys($asset) !== ['sha256', 'bytes'] || $bytes === false || hash('sha256', $bytes) !== $asset['sha256']
                || ! in_array(@getimagesizefromstring($bytes)[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                throw new RuntimeException('Catalog safety: invalid image contents or checksum.');
            }
        }
    }

    private function assertAssetPath(string $path): void
    {
        if (! preg_match('#^menu/[a-z0-9]+\.jpg$#', $path)) {
            throw new RuntimeException('Catalog safety: asset path outside approved menu directory.');
        }
    }

    /** @param array<string, mixed> $bundle */
    public function fingerprint(array $bundle): string
    {
        return hash('sha256', json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $bundle */
    public function assertApproved(array $bundle, string $layout): void
    {
        $this->validate($bundle);
        if ($this->fingerprint($bundle) !== self::APPROVED_SHA256 || hash_file('sha256', $layout) !== $bundle['layout_sha256']) {
            throw new RuntimeException('Catalog safety: reviewed export or floor-plan fingerprint differs.');
        }
    }

    /** @return list<string> */
    public function assertTargetSchema(Connection $target): array
    {
        $currentTestTarget = $this->isCurrentTestTarget($target);
        $expectedTables = $currentTestTarget ? self::CURRENT_TEST_TABLES : self::TABLES;
        $tables = $target->getPdo()->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables !== $expectedTables
            || $target->table('migrations')->count() !== ($currentTestTarget ? 20 : 18)) {
            throw new RuntimeException('Catalog safety: target schema differs from Stage 3.');
        }

        if ($currentTestTarget && $target->table('allergens')->orderBy('sort_order')->pluck('code', 'sort_order')->all()
            !== array_combine(range(0, count(Allergen::CODES) - 1), Allergen::CODES)) {
            throw new RuntimeException('Catalog safety: target contains unapproved allergen records.');
        }

        return $expectedTables;
    }

    public function assertEmptyTarget(Connection $target): void
    {
        $tables = $this->assertTargetSchema($target);
        $currentTestTarget = $this->isCurrentTestTarget($target);

        foreach ($tables as $table) {
            if ($table !== 'migrations' && ($table !== 'allergens' || ! $currentTestTarget) && $target->table($table)->exists()) {
                throw new RuntimeException('Catalog safety: target contains existing records.');
            }
        }
    }

    private function isCurrentTestTarget(Connection $target): bool
    {
        if (! app()->environment('testing') || $target->getDatabaseName() !== MySqlTestDatabaseGuard::DATABASE) {
            return false;
        }

        MySqlTestDatabaseGuard::assertConnection($target);

        return true;
    }

    /**
     * Called after CLI verification of the demo guard and approved fingerprint.
     * Tests exercise the same operation exclusively on the guarded test connection.
     * Files and SQL cannot commit atomically: retain the marker/assets on interruption for review.
     *
     * @param  array<string, mixed>  $bundle
     */
    public function restore(Connection $target, array $bundle, string $publicStorage, string $privateStorage): void
    {
        $this->validate($bundle);
        $this->assertEmptyTarget($target);
        if (file_exists($publicStorage.'/menu') || file_exists($privateStorage.'/catalog-stage')) {
            throw new RuntimeException('Catalog safety: destination assets or staging already exist.');
        }
        $marker = @fopen($privateStorage.'/catalog-restoration.started', 'x');
        if ($marker === false) {
            throw new RuntimeException('Catalog safety: previous or concurrent restoration attempt; review required.');
        }
        fclose($marker);
        $target->transaction(function () use ($target, $bundle, $publicStorage, $privateStorage): void {
            $this->assertEmptyTarget($target);
            if (! mkdir($privateStorage.'/catalog-stage', 0700)) {
                throw new RuntimeException('Catalog safety: unable to stage assets.');
            }
            foreach ($bundle['assets'] as $path => $asset) {
                $file = $privateStorage.'/catalog-stage/'.basename($path);
                $bytes = base64_decode($asset['bytes'], true);
                if (file_put_contents($file, $bytes, LOCK_EX) !== strlen($bytes) || hash_file('sha256', $file) !== $asset['sha256']) {
                    throw new RuntimeException('Catalog safety: staged asset verification failed.');
                }
            }
            foreach ($bundle['rows'] as $table => $rows) {
                foreach ($rows as $row) {
                    $target->table($table)->insert($row);
                }
            }
            if ($this->readRows($target->getPdo(), $this->isCurrentTestTarget($target)) !== $bundle['rows']) {
                throw new RuntimeException('Catalog safety: restored records differ from source.');
            }
            if (file_exists($publicStorage.'/menu') || ! rename($privateStorage.'/catalog-stage', $publicStorage.'/menu')) {
                throw new RuntimeException('Catalog safety: asset publication failed; database transaction will roll back.');
            }
        }, 1);
    }
}
