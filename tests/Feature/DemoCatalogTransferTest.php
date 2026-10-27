<?php

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Table;
use App\Services\DemoCatalogTransfer;
use App\Services\DemoDatabaseGuard;
use App\Services\DemoEnvironmentGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

uses(RefreshDatabase::class);

function catalogFixture(): array
{
    $rows = [];
    $time = '2026-01-02 03:04:05';
    foreach (range(1, 6) as $id) {
        $rows['menu_categories'][] = MenuCategory::factory()->make(['id' => $id, 'name' => 'Category '.$id, 'slug' => 'category-'.$id,
            'sort_order' => $id, 'is_active' => 1, 'created_at' => $time, 'updated_at' => $time])->getAttributes();
    }
    $featured = ['Nasi Lemak', 'Rendang', 'Roti Canai', 'Roti Telur', 'Cendol'];
    foreach (range(1, 35) as $id) {
        $rows['menu_items'][] = MenuItem::factory()->make(['id' => $id, 'menu_category_id' => ($id % 6) + 1,
            'name' => $featured[$id - 1] ?? 'Fixture dish '.$id, 'slug' => 'fixture-dish-'.$id,
            'description' => 'Preserved description '.$id, 'price' => '12.30', 'image_path' => $id === 1 ? 'menu/fixture.jpg' : null,
            'spice_level' => 'mild', 'protein_type' => null, 'dietary_type' => 'vegetarian', 'flavor_profile' => 'savoury',
            'is_available' => (int) ($id <= 27), 'is_featured' => (int) ($id <= 5), 'sort_order' => $id <= 5 ? $id : 0,
            'deleted_at' => $id === 35 ? $time : null, 'created_at' => $time, 'updated_at' => $time])->getAttributes();
    }
    foreach ([4, 2, 4, 4, 4, 6, 6, 8, 8, 10, 3] as $index => $capacity) {
        $rows['tables'][] = Table::factory()->make(['id' => $index + 1, 'table_number' => 'T'.($index + 1),
            'capacity' => $capacity, 'status' => 'available', 'created_at' => $time, 'updated_at' => $time])->getAttributes();
    }
    foreach (DemoCatalogTransfer::COLUMNS as $table => $columns) {
        $rows[$table] = array_map(function ($row) use ($columns) {
            $ordered = [];
            foreach ($columns as $column) {
                $ordered[$column] = $row[$column] === null ? null : (string) $row[$column];
            }

            return $ordered;
        }, $rows[$table]);
    }
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZl8AAAAASUVORK5CYII=');

    return ['format' => 1, 'source' => 'le_miting_dev', 'rows' => $rows,
        'assets' => ['menu/fixture.jpg' => ['sha256' => hash('sha256', $image), 'bytes' => base64_encode($image)]],
        'layout_sha256' => hash_file('sha256', config_path('reservation.php'))];
}

function catalogDirectories(): array
{
    Storage::fake('catalog-public');
    Storage::fake('catalog-private');

    return [Storage::disk('catalog-public')->path(''), Storage::disk('catalog-private')->path('')];
}

test('catalog restoration preserves IDs relationships metadata archives prices featured order and capacities', function () {
    $bundle = catalogFixture();
    $service = new DemoCatalogTransfer;
    [$public, $private] = catalogDirectories();
    $service->restore(DB::connection(), $bundle, $public, $private);
    expect($service->readRows(DB::connection()->getPdo(), true))->toBe($bundle['rows']);
    expect(MenuItem::withTrashed()->where('allergen_assessment_revision', 0)->count())->toBe(35);
    expect(DB::table('allergens')->orderBy('sort_order')->pluck('code')->all())->toBe(Allergen::CODES);
    expect(MenuItem::count())->toBe(34);
    expect(MenuItem::withTrashed()->count())->toBe(35);
    expect(MenuItem::withTrashed()->where('is_available', false)->count())->toBe(8);
    expect(MenuItem::where('is_available', true)->count())->toBe(27);
    expect(MenuItem::where('is_featured', true)->orderBy('sort_order')->pluck('name')->all())->toBe(['Nasi Lemak', 'Rendang', 'Roti Canai', 'Roti Telur', 'Cendol']);
    expect(Table::where('table_number', 'T1')->value('capacity'))->toBe(4);
    expect(Table::where('table_number', 'T11')->value('capacity'))->toBe(3);
    expect(hash_file('sha256', $public.'/menu/fixture.jpg'))->toBe($bundle['assets']['menu/fixture.jpg']['sha256']);
    foreach (['users', 'orders', 'reservations', 'payments', 'sessions', 'cache'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    expect(fn () => $service->restore(DB::connection(), $bundle, $public, $private))->toThrow(RuntimeException::class, 'existing records');
});

test('catalog restoration refuses even one existing destination record', function () {
    MenuCategory::factory()->create();
    [$public, $private] = catalogDirectories();
    expect(fn () => (new DemoCatalogTransfer)->restore(DB::connection(), catalogFixture(), $public, $private))->toThrow(RuntimeException::class, 'existing records');
    expect(MenuCategory::count())->toBe(1);
    expect(is_file($private.'/catalog-restoration.started'))->toBeFalse();
});

test('catalog restoration refuses an altered canonical allergen vocabulary', function () {
    DB::table('allergens')->where('code', 'peanut')->update(['code' => 'unreviewed']);
    [$public, $private] = catalogDirectories();

    expect(fn () => (new DemoCatalogTransfer)->restore(DB::connection(), catalogFixture(), $public, $private))
        ->toThrow(RuntimeException::class, 'unapproved allergen records');
    expect(is_file($private.'/catalog-restoration.started'))->toBeFalse();
});

test('restoration preserves noncontiguous source identifiers instead of allocating replacements', function () {
    $bundle = catalogFixture();
    foreach ($bundle['rows']['menu_categories'] as &$category) {
        $category['id'] = (string) ((int) $category['id'] + 100);
    }
    unset($category);
    foreach ($bundle['rows']['menu_items'] as &$item) {
        $item['id'] = (string) ((int) $item['id'] + 200);
        $item['menu_category_id'] = (string) ((int) $item['menu_category_id'] + 100);
    }
    unset($item);
    foreach ($bundle['rows']['tables'] as &$table) {
        $table['id'] = (string) ((int) $table['id'] + 500);
    }
    unset($table);
    [$public, $private] = catalogDirectories();
    (new DemoCatalogTransfer)->restore(DB::connection(), $bundle, $public, $private);
    expect(MenuItem::findOrFail(201)->menu_category_id)->toBe(102);
    expect(Table::findOrFail(511)->table_number)->toBe('T11');
    expect(MenuCategory::find(1))->toBeNull();
});

test('export enforces a read only source transaction and transfers no excluded tables', function () {
    $bundle = catalogFixture();
    [$public] = catalogDirectories();
    mkdir($public.'/menu');
    file_put_contents($public.'/menu/fixture.jpg', base64_decode($bundle['assets']['menu/fixture.jpg']['bytes']));
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_CONNECTION_STATUS)->andReturn('127.0.0.1 via TCP/IP');
    $identity = Mockery::mock(PDOStatement::class);
    $identity->shouldReceive('fetch')->once()->andReturn(['db' => 'le_miting_dev', 'account' => 'le_miting_dev@127.0.0.1',
        'version' => '26.7.0', 'product' => 'MySQL Community Server - GPL', 'port' => 3307, 'timezone' => '+00:00',
        'datadir' => 'C:/ProgramData/MySQL/MySQL Server 26.7/Data/']);
    $pdo->shouldReceive('query')->once()->with(Mockery::pattern('/^SELECT DATABASE\(\) AS db/'))->andReturn($identity);
    $pdo->shouldReceive('inTransaction')->once()->andReturnFalse();
    $pdo->shouldReceive('exec')->once()->with('SET SESSION TRANSACTION READ ONLY')->andReturn(0);
    $pdo->shouldReceive('beginTransaction')->once()->andReturnTrue();
    $readonly = Mockery::mock(PDOStatement::class);
    $readonly->shouldReceive('fetchColumn')->once()->andReturn(1);
    $pdo->shouldReceive('query')->once()->with('SELECT @@session.transaction_read_only')->andReturn($readonly);
    foreach ($bundle['rows'] as $table => $rows) {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('fetchAll')->once()->andReturn($rows);
        $pdo->shouldReceive('query')->once()->with('SELECT * FROM `'.$table.'` ORDER BY id')->andReturn($statement);
    }
    $pdo->shouldReceive('rollBack')->once()->andReturnTrue();
    $actual = (new DemoCatalogTransfer)->export($pdo, rtrim($public, '/\\'), config_path('reservation.php'));
    expect($actual)->toBe($bundle);
});

test('catalog validation refuses incomplete or altered populations and unsafe asset data', function (string $case) {
    $bundle = catalogFixture();
    match ($case) {
        'count' => array_pop($bundle['rows']['menu_items']),
        'category' => $bundle['rows']['menu_items'][0]['menu_category_id'] = '999',
        'duplicate id' => $bundle['rows']['menu_items'][1]['id'] = '1',
        'featured' => $bundle['rows']['menu_items'][0]['sort_order'] = '9',
        'T1' => $bundle['rows']['tables'][0]['capacity'] = '2',
        'T11' => $bundle['rows']['tables'][10]['capacity'] = '4',
        'archived visible' => $bundle['rows']['menu_items'][0]['deleted_at'] = '2026-01-02 03:04:05',
        'availability' => $bundle['rows']['menu_items'][0]['is_available'] = '0',
        'path' => $bundle['rows']['menu_items'][0]['image_path'] = '../.env',
        'missing image' => $bundle['assets'] = [],
        'corrupt image' => $bundle['assets']['menu/fixture.jpg']['bytes'] = base64_encode('not an image'),
        'extra dataset' => $bundle['rows']['users'] = [],
        'extra column' => $bundle['rows']['menu_items'][0]['unknown'] = 'value',
        'wrong provenance' => $bundle['source'] = 'le_miting_test',
    };
    expect(fn () => (new DemoCatalogTransfer)->validate($bundle))->toThrow(RuntimeException::class);
})->with(['count', 'category', 'duplicate id', 'featured', 'T1', 'T11', 'archived visible', 'availability', 'path', 'missing image', 'corrupt image', 'extra dataset', 'extra column', 'wrong provenance']);

test('unapproved catalog fingerprint cannot pass the CLI approval boundary', function () {
    expect(fn () => (new DemoCatalogTransfer)->assertApproved(catalogFixture(), config_path('reservation.php')))->toThrow(RuntimeException::class, 'fingerprint');
});

test('interruption rolls back all catalog rows and leaves an explicit retry blocker', function () {
    [$public, $private] = catalogDirectories();
    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_starts_with($query, 'insert into `menu_items`')) {
            throw new RuntimeException('Simulated interruption after category inserts');
        }
    });
    $service = new DemoCatalogTransfer;
    expect(fn () => $service->restore(DB::connection(), catalogFixture(), $public, $private))->toThrow(RuntimeException::class, 'Simulated interruption');
    foreach (array_keys(DemoCatalogTransfer::COLUMNS) as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    expect(is_file($private.'/catalog-restoration.started'))->toBeTrue();
    expect(is_dir($public.'/menu'))->toBeFalse();
    expect(fn () => $service->restore(DB::connection(), catalogFixture(), $public, $private))->toThrow(RuntimeException::class);
});

test('an existing attempt marker or asset directory prevents replacement', function (string $existing) {
    [$public, $private] = catalogDirectories();
    if ($existing === 'marker') {
        file_put_contents($private.'/catalog-restoration.started', 'previous attempt');
    } else {
        mkdir($public.'/menu');
        file_put_contents($public.'/menu/existing.txt', 'must survive');
    }
    expect(fn () => (new DemoCatalogTransfer)->restore(DB::connection(), catalogFixture(), $public, $private))->toThrow(RuntimeException::class);
    expect(MenuItem::count())->toBe(0);
    expect($existing === 'marker' ? file_get_contents($private.'/catalog-restoration.started') : file_get_contents($public.'/menu/existing.txt'))
        ->toBe($existing === 'marker' ? 'previous attempt' : 'must survive');
})->with(['marker', 'assets']);

test('source identity rejects wrong schemas accounts endpoints and server identities', function (string $field, mixed $value) {
    $identity = ['db' => 'le_miting_dev', 'account' => 'le_miting_dev@127.0.0.1', 'version' => '26.7.0', 'product' => 'MySQL Community Server - GPL',
        'port' => 3307, 'timezone' => '+00:00', 'datadir' => 'C:/ProgramData/MySQL/MySQL Server 26.7/Data/'];
    $identity[$field] = $value;
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('fetch')->once()->andReturn($identity);
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('query')->once()->andReturn($statement);
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_CONNECTION_STATUS)->andReturn('127.0.0.1 via TCP/IP');
    expect(fn () => (new DemoCatalogTransfer)->assertSource($pdo))->toThrow(RuntimeException::class, 'source identity');
})->with([['db', 'le_miting_test'], ['db', 'le_miting_demo'], ['account', 'root@localhost'], ['port', 3306], ['product', 'MariaDB'], ['timezone', 'SYSTEM']]);

test('the actual test connection cannot masquerade as either source or demo destination', function () {
    expect(fn () => (new DemoCatalogTransfer)->assertSource(DB::connection()->getPdo()))->toThrow(RuntimeException::class);
    expect(fn () => (new DemoDatabaseGuard)->assertPdo(DB::connection()->getPdo()))->toThrow(RuntimeException::class);
    $this->artisan('demo:catalog-export')->assertFailed();
    $this->artisan('demo:catalog-import')->assertFailed();
});

test('demo command gate allows catalog import but still refuses seeding export and force', function () {
    $guard = new DemoEnvironmentGuard;
    $guard->protectCommand(new CommandStarting('demo:catalog-import', new ArgvInput(['artisan', 'demo:catalog-import']), new NullOutput));
    foreach (['db:seed', 'demo:catalog-export', 'migrate:fresh'] as $command) {
        expect(fn () => $guard->protectCommand(new CommandStarting($command, new ArgvInput(['artisan', $command]), new NullOutput)))->toThrow(RuntimeException::class);
    }
    expect(fn () => $guard->protectCommand(new CommandStarting('demo:catalog-import', new ArgvInput(['artisan', 'demo:catalog-import', '--force']), new NullOutput)))->toThrow(RuntimeException::class);
});
