<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\CachedState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Install the guard before database providers boot and before Laravel sets up test traits. */
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);

        if (isset($this->traitsUsedByTest[WithCachedConfig::class])) {
            throw new RuntimeException('Test database guard: cached configuration is not permitted.');
        }

        if (isset(CachedState::$cachedRoutes, $this->traitsUsedByTest[WithCachedRoutes::class])) {
            $app->booting(fn () => $this->markRoutesCached($app));
        }

        MySqlTestDatabaseGuard::prepare($app);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
