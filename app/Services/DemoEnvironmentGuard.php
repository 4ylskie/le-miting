<?php

namespace App\Services;

use Dotenv\Dotenv;
use FilesystemIterator;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Env;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Console\Input\ArgvInput;
use Throwable;

class DemoEnvironmentGuard
{
    public const ROOT = 'C:/Users/USER/Herd/le-miting-demo';

    public const ORIGIN = 'http://127.0.0.1:8011';

    /** Select the explicit file BEFORE Laravel's normal fallback or cache loading. */
    public static function prepare(Application $app): void
    {
        $app->beforeBootstrapping(LoadEnvironmentVariables::class, function (Application $app): void {
            $input = $app->runningInConsole() ? new ArgvInput : null;
            $requested = $input?->getParameterOption('--env', null);
            if (self::path($app->basePath()) === self::path(self::ROOT)
                || $requested === 'demo' || Env::get('APP_ENV') === 'demo') {
                $app->instance('demo.requested', true);
                try {
                    self::assertBootFiles($app);
                    if ($requested !== null && $requested !== 'demo') {
                        throw new RuntimeException('Demo safety: conflicting environment selection.');
                    }
                    $app->loadEnvironmentFrom('.env.demo');
                } catch (Throwable) {
                    self::stopBootstrap();
                }
            }
        });
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            if ($app->bound('demo.requested') || $app->environment('demo')) {
                $app->instance('demo.requested', true);
                $guard = new self;
                try {
                    $guard->verify($app);
                } catch (Throwable) {
                    self::stopBootstrap();
                }
            }
        });
        $app->afterBootstrapping(RegisterProviders::class, function (Application $app): void {
            if (! $app->bound('demo.requested')) {
                return;
            }
            try {
                if ($app->resolved('db')) {
                    throw new RuntimeException('Demo safety: database resolved before guard installation.');
                }
                $guard = new self;
                $guard->verify($app);
                $migration = null;
                if (PHP_SAPI === 'cli' && ($_SERVER['argv'][1] ?? null) === 'migrate') {
                    DemoMigrationGuard::assertInvocation(PHP_SAPI, $app->environment(), $app->basePath(), array_slice($_SERVER['argv'], 1));
                    $migration = new DemoMigrationGuard;
                    $migration->assertMigrationFiles($app->databasePath('migrations'));
                    $app->instance(DemoMigrationGuard::class, $migration);
                }
                $app->singleton('db.factory', fn () => new DemoGuardedConnectionFactory($app, $guard, new DemoDatabaseGuard($migration !== null), $migration));
                if ($migration !== null && ($app->make('migrator')->paths() !== []
                    || $app['config']->get('database.migrations.table') !== 'migrations')) {
                    throw new RuntimeException('Demo migration safety: additional migration paths or alternate repository are forbidden.');
                }
                $app['config']->set('mail.mailers', ['array' => ['transport' => 'array']]);
                $app['config']->set('filesystems.disks', array_intersect_key($app['config']->get('filesystems.disks'), array_flip(['local', 'public'])));
                $app['config']->set('cache.stores', ['database' => $app['config']->get('cache.stores.database')]);
                $app['config']->set('queue.connections', ['sync' => ['driver' => 'sync']]);
                $app['config']->set('logging.channels', [
                    'single' => ['driver' => 'single', 'path' => $app->storagePath('logs/laravel.log')],
                ]);
                $app['config']->set('dompdf.options.temp_dir', $app->storagePath('framework/cache'));
                $app['config']->set('dompdf.options.isRemoteEnabled', false);
                $app->make('db')->connection();
            } catch (Throwable) {
                self::stopBootstrap();
            }
        });
    }

    /** Laravel's exception logger is not safe to resolve before configuration is verified. */
    private static function stopBootstrap(): never
    {
        $message = 'Demo safety: separate runtime configuration or database verification failed. No fallback or application operations permitted.';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message.PHP_EOL);
        } else {
            http_response_code(503);
            echo $message;
        }
        exit(1);
    }

    public static function assertBootFiles(Application $app): void
    {
        self::assertBootState($app->basePath(), realpath($app->basePath()) ?: '',
            is_file($app->basePath('.env.demo')), is_file($app->basePath('.env')),
            $app->configurationIsCached() || is_file($app->basePath('bootstrap/cache/config.php')));
        if (self::path(realpath($app->basePath('.env.demo')) ?: '') !== self::path(self::ROOT.'/.env.demo')) {
            throw new RuntimeException('Demo safety: environment files must not be shared.');
        }
    }

    public static function assertBootState(string $base, string $resolved, bool $demoFile, bool $fallbackFile, bool $cached): void
    {
        if (self::path($base) !== self::path(self::ROOT) || self::path($resolved) !== self::path(self::ROOT)
            || ! $demoFile || $fallbackFile || $cached) {
            throw new RuntimeException('Demo safety: separate runtime, explicit .env.demo and uncached configuration are required.');
        }
    }

    public function verify(Application $app): void
    {
        self::assertBootFiles($app);
        if (! $app->environment('demo') || $app['config']->get('app.env') !== 'demo'
            || $app->environmentFile() !== '.env.demo'
            || $app->bound('config_loaded_from_cache') && $app->make('config_loaded_from_cache')) {
            throw new RuntimeException('Demo safety: explicit demo environment is required.');
        }
        try {
            $this->assertResolvedPath($app->basePath('.demo-key-fingerprints.json'), self::ROOT.'/.demo-key-fingerprints.json');
            $explicit = Dotenv::parse(file_get_contents($app->basePath('.env.demo')));
            $fingerprints = json_decode(file_get_contents($app->basePath('.demo-key-fingerprints.json')), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Demo safety: private runtime configuration is missing or invalid.');
        }
        $this->assertSettings($app['config']->all(), $explicit, $fingerprints);
        $this->assertPaths($app);
        if (! $app->runningInConsole()) {
            $this->assertWebRequest($_SERVER);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $explicit
     * @param  array<string, mixed>  $fingerprints
     */
    public function assertSettings(#[SensitiveParameter] array $config, #[SensitiveParameter] array $explicit, array $fingerprints): void
    {
        $settings = [
            'APP_ENV' => ['app.env', 'demo'], 'APP_URL' => ['app.url', self::ORIGIN],
            'APP_DEBUG' => ['app.debug', false], 'SESSION_DRIVER' => ['session.driver', 'database'],
            'SESSION_CONNECTION' => ['session.connection', 'mysql'],
            'SESSION_COOKIE' => ['session.cookie', 'le-miting-demo-session'],
            'SESSION_DOMAIN' => ['session.domain', null],
            'CACHE_STORE' => ['cache.default', 'database'], 'CACHE_PREFIX' => ['cache.prefix', 'le-miting-demo-cache-'],
            'DB_CACHE_CONNECTION' => ['cache.stores.database.connection', 'mysql'],
            'DB_CACHE_LOCK_CONNECTION' => ['cache.stores.database.lock_connection', 'mysql'],
            'MAIL_MAILER' => ['mail.default', 'array'], 'QUEUE_CONNECTION' => ['queue.default', 'sync'],
            'PAYMENT_DRIVER' => ['payment.driver', 'mock'], 'AI_DRIVER' => ['ai.driver', 'mock'],
            'LOG_CHANNEL' => ['logging.default', 'single'], 'FILESYSTEM_DISK' => ['filesystems.default', 'local'],
        ];
        foreach ($settings as $variable => [$key, $expected]) {
            $literal = $expected === false ? 'false' : ($expected === null ? 'null' : $expected);
            if (! array_key_exists($variable, $explicit) || $explicit[$variable] !== $literal
                || data_get($config, $key) !== $expected) {
                throw new RuntimeException('Demo safety: missing or conflicting isolation settings.');
            }
        }
        $key = data_get($config, 'app.key', '');
        $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        if ($decoded === false || strlen($decoded) !== 32 || ($explicit['APP_KEY'] ?? null) !== $key
            || ! preg_match('/^[a-f0-9]{64}$/', $fingerprints['source_key_sha256'] ?? '')
            || ($fingerprints['demo_key_sha256'] ?? null) !== hash('sha256', $decoded)
            || $fingerprints['source_key_sha256'] === $fingerprints['demo_key_sha256']
            || ! empty(data_get($config, 'app.previous_keys'))) {
            throw new RuntimeException('Demo safety: separately generated and recorded application key is required.');
        }
        if (data_get($config, 'database.default') !== 'mysql'
            || array_keys(data_get($config, 'database.connections', [])) !== ['mysql']) {
            throw new RuntimeException('Demo safety: exactly one approved MySQL connection is required.');
        }
        foreach (['app.timezone' => 'UTC', 'session.table' => 'sessions', 'session.http_only' => true,
            'cache.stores.database.table' => 'cache', 'cache.stores.database.lock_table' => 'cache_locks',
            'mail.mailers.array.transport' => 'array', 'filesystems.disks.local.driver' => 'local',
            'filesystems.disks.public.driver' => 'local', 'logging.channels.single.driver' => 'single'] as $field => $expected) {
            if (data_get($config, $field) !== $expected) {
                throw new RuntimeException('Demo safety: isolated storage and transport configuration is required.');
            }
        }
        $database = data_get($config, 'database.connections.mysql', []);
        (new DemoDatabaseGuard)->assertConfiguration($database);
        foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
            'DB_DATABASE' => 'le_miting_demo', 'DB_USERNAME' => 'le_miting_demo'] as $variable => $value) {
            if (($explicit[$variable] ?? null) !== $value) {
                throw new RuntimeException('Demo safety: explicit database settings are required.');
            }
        }
        if (empty($explicit['DB_PASSWORD']) || $explicit['DB_PASSWORD'] !== ($database['password'] ?? null)) {
            throw new RuntimeException('Demo safety: explicit demo credentials are required.');
        }
        foreach (['payment.fiuu.merchant_id', 'payment.fiuu.secret_key', 'openai.api_key',
            'mail.mailers.smtp.username', 'mail.mailers.smtp.password', 'mail.mailers.smtp.url',
            'services.postmark.key', 'services.resend.key', 'services.ses.key', 'services.ses.secret'] as $field) {
            if (! empty(data_get($config, $field))) {
                throw new RuntimeException('Demo safety: external service credentials are forbidden.');
            }
        }
        foreach (['FIUU_MERCHANT_ID', 'FIUU_SECRET_KEY', 'OPENAI_API_KEY', 'MAIL_USERNAME', 'MAIL_PASSWORD',
            'MAIL_URL', 'POSTMARK_API_KEY', 'RESEND_API_KEY', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY'] as $variable) {
            if (! empty($explicit[$variable]) || ! empty(Env::get($variable))) {
                throw new RuntimeException('Demo safety: external service credentials are forbidden.');
            }
        }
    }

    public function assertPaths(Application $app): void
    {
        $expected = [
            $app->storagePath() => self::ROOT.'/storage',
            $app->bootstrapPath('cache') => self::ROOT.'/bootstrap/cache',
            $app->getCachedConfigPath() => self::ROOT.'/bootstrap/cache/config.php',
            $app->getCachedServicesPath() => self::ROOT.'/bootstrap/cache/services.php',
            $app->getCachedPackagesPath() => self::ROOT.'/bootstrap/cache/packages.php',
            $app->getCachedRoutesPath() => self::ROOT.'/bootstrap/cache/routes-v7.php',
            $app->getCachedEventsPath() => self::ROOT.'/bootstrap/cache/events.php',
            $app->publicPath() => self::ROOT.'/public',
            $app['config']->get('view.compiled', '') => self::ROOT.'/storage/framework/views',
            $app['config']->get('filesystems.disks.local.root', '') => self::ROOT.'/storage/app/private',
            $app['config']->get('filesystems.disks.public.root', '') => self::ROOT.'/storage/app/public',
            $app->publicPath('storage') => self::ROOT.'/storage/app/public',
            $app['config']->get('logging.channels.single.path', '') => self::ROOT.'/storage/logs/laravel.log',
        ];
        foreach ($expected as $actual => $target) {
            $this->assertResolvedPath($actual, $target);
        }
        foreach (['framework/cache', 'framework/sessions', 'fonts', 'logs'] as $path) {
            $this->assertResolvedPath($app->storagePath($path), self::ROOT.'/storage/'.$path);
        }
        foreach ([$app->storagePath(), $app->bootstrapPath('cache')] as $root) {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($entries as $entry) {
                $this->assertResolvedPath($entry->getPathname(), $entry->getPathname());
            }
        }
    }

    public function assertResolvedPath(string $actual, string $expected): void
    {
        $resolved = realpath($actual);
        if ($resolved === false && ! file_exists($actual) && is_dir(dirname($actual))) {
            $resolved = realpath(dirname($actual)).'/'.basename($actual);
        }
        if (self::path($resolved ?: '') !== self::path($expected)) {
            throw new RuntimeException('Demo safety: writable paths must resolve inside the separate demo runtime.');
        }
    }

    /** @param array<string, mixed> $server */
    public function assertWebRequest(array $server): void
    {
        if (($server['REMOTE_ADDR'] ?? null) !== '127.0.0.1'
            || ($server['SERVER_NAME'] ?? null) !== '127.0.0.1'
            || (string) ($server['SERVER_PORT'] ?? '') !== '8011'
            || ($server['HTTP_HOST'] ?? null) !== '127.0.0.1:8011'
            || ! empty($server['HTTP_FORWARDED']) || ! empty($server['HTTP_X_FORWARDED_FOR'])) {
            throw new RuntimeException('Demo safety: direct loopback-only access is required.');
        }
    }

    public function protectCommand(CommandStarting $event): void
    {
        if (! in_array($event->command, ['demo:preflight', 'demo:catalog-import', 'admin:provision-demo', 'demo:transactions'], true) || $event->input->hasParameterOption('--force')
            || $event->command === 'admin:provision-demo' && ! $event->input->isInteractive()) {
            throw new RuntimeException('Demo safety: only approved preflight, catalog import, transaction generation and interactive demo provisioning commands are permitted; seeders and workers remain blocked.');
        }
    }

    private static function path(string $path): string
    {
        return strtolower(rtrim(str_replace('\\', '/', $path), '/'));
    }
}
