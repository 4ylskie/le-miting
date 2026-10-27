<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

/** Real commits and MySQL DDL cannot run inside RefreshDatabase's outer transaction. */
trait NonTransactionalDatabase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['allergens'];

    public function tearDownNonTransactionalDatabase(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        MySqlTestDatabaseGuard::assertEnvironment($this->app);
        MySqlTestDatabaseGuard::assertConnection(DB::connection());
        $this->truncateTablesForAllConnections();
    }
}
