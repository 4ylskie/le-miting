<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\MySqlTestDatabaseGuard;

require_once dirname(__DIR__).'/vendor/autoload.php';

try {
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    MySqlTestDatabaseGuard::prepare($app);
    $app->make(Kernel::class)->bootstrap();
    restore_exception_handler();
} catch (Throwable $exception) {
    $message = str_starts_with($exception->getMessage(), 'Test database guard:')
        ? $exception->getMessage()
        : 'Test database guard: preflight failed; inspect the isolated test configuration.';

    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}
