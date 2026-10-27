<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            if (! DB::scalar('PRAGMA foreign_keys')) {
                throw new RuntimeException('Payment integrity requires SQLite foreign-key enforcement.');
            }
            DB::transaction(fn () => $this->repairSqlite());

            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Payment integrity migration supports MySQL 8.0.16+ and SQLite only.');
        }
        $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
        if (str_contains(strtolower($version), 'mariadb') || ! preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)
            || version_compare($matches[1], '8.0.16', '<')) {
            throw new RuntimeException('Payment integrity requires MySQL 8.0.16 or newer; MariaDB compatibility has not been established.');
        }
        if ((int) DB::scalar('SELECT @@SESSION.foreign_key_checks') !== 1) {
            throw new RuntimeException('Payment integrity requires MySQL foreign-key checking to remain enabled.');
        }

        $engines = DB::table('information_schema.tables')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', ['payments', 'orders', 'reservations'])->pluck('ENGINE');
        if ($engines->count() !== 3 || $engines->contains(fn ($engine) => strtolower($engine) !== 'innodb')) {
            throw new RuntimeException('Payments and both parent tables must exist and use InnoDB before repair.');
        }
        $this->assertValidAssociations();
        $foreignKeys = $this->parentForeignKeys();
        $grammar = DB::connection()->getQueryGrammar();
        $clauses = [];
        $additions = [];
        foreach (['order_id' => 'orders', 'reservation_id' => 'reservations'] as $column => $parent) {
            $matching = array_filter($foreignKeys, fn ($foreignKey) => $foreignKey['columns'] === [$column]);
            foreach ($matching as $foreignKey) {
                if (! $this->isRestrictive($foreignKey)) {
                    $clauses[] = 'DROP FOREIGN KEY '.$grammar->wrap($foreignKey['name']);
                }
            }
            if (array_any($matching, fn ($foreignKey) => $this->isRestrictive($foreignKey))) {
                continue;
            }
            $name = 'payments_'.$column.'_restrict';
            while (in_array($name, array_column($foreignKeys, 'name'), true)) {
                $name .= '_v2';
            }
            $additions[] = 'ADD CONSTRAINT '.$grammar->wrap($name).' FOREIGN KEY ('.$column.') REFERENCES '.$parent.' (id) ON DELETE RESTRICT ON UPDATE RESTRICT';
        }
        $check = DB::table('information_schema.check_constraints')->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('CONSTRAINT_NAME', 'payments_exactly_one_parent')->value('CHECK_CLAUSE');
        if ($check !== null) {
            $normalized = preg_replace('/[\s`()]+/', '', strtolower($check));
            if ($normalized !== 'order_idisnotnullandreservation_idisnullororder_idisnullandreservation_idisnotnull') {
                throw new RuntimeException('Payment parent repair stopped: unexpected XOR constraint requires review.');
            }
            $clauses[] = 'ALTER CHECK payments_exactly_one_parent ENFORCED';
        } else {
            $clauses[] = 'ADD CONSTRAINT payments_exactly_one_parent CHECK ((order_id IS NOT NULL AND reservation_id IS NULL) OR (order_id IS NULL AND reservation_id IS NOT NULL)) ENFORCED';
        }

        // MySQL COPY validates new FKs but cannot combine FK addition/removal.
        // Install restrictions first; a retry can complete either deployment stage.
        if ($additions !== []) {
            DB::statement('ALTER TABLE payments '.implode(', ', $additions).', ALGORITHM=COPY');
        }
        DB::statement('ALTER TABLE payments '.implode(', ', $clauses));
    }

    private function assertValidAssociations(): void
    {
        $invalid = DB::table('payments as p')
            ->leftJoin('orders as o', 'o.id', '=', 'p.order_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'p.reservation_id')
            ->whereRaw('(p.order_id IS NULL AND p.reservation_id IS NULL)
                OR (p.order_id IS NOT NULL AND p.reservation_id IS NOT NULL)
                OR (p.order_id IS NOT NULL AND o.id IS NULL)
                OR (p.reservation_id IS NOT NULL AND r.id IS NULL)')->exists();
        if ($invalid) {
            throw new RuntimeException('Payment parent repair stopped: existing payments have missing, dual or nonexistent parents. Resolve associations explicitly; no financial rows were changed.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function parentForeignKeys(): array
    {
        $foreignKeys = Schema::getForeignKeys('payments');
        foreach ($foreignKeys as $foreignKey) {
            $expected = match ($foreignKey['columns']) {
                ['order_id'] => 'orders',
                ['reservation_id'] => 'reservations',
                default => null,
            };
            if ($expected === null || $foreignKey['foreign_table'] !== $expected || $foreignKey['foreign_columns'] !== ['id']) {
                throw new RuntimeException('Payment parent repair stopped: unexpected foreign-key definition requires review.');
            }
        }

        return $foreignKeys;
    }

    private function repairSqlite(): void
    {
        $this->assertValidAssociations();
        $foreignKeys = $this->parentForeignKeys();
        $restrictive = count($foreignKeys) === 2
            && in_array(['order_id'], array_column($foreignKeys, 'columns'), true)
            && in_array(['reservation_id'], array_column($foreignKeys, 'columns'), true)
            && collect($foreignKeys)->every(fn ($foreignKey) => $this->isRestrictive($foreignKey));

        if (! $restrictive) {
            Schema::table('payments', function (Blueprint $table) use ($foreignKeys): void {
                foreach ($foreignKeys as $foreignKey) {
                    $table->dropForeign($foreignKey['columns']);
                }
                $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('reservation_id')->references('id')->on('reservations')->restrictOnDelete()->restrictOnUpdate();
            });
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared('DROP TRIGGER IF EXISTS payments_exactly_one_parent_'.$operation);
            DB::unprepared('CREATE TRIGGER payments_exactly_one_parent_'.$operation.' BEFORE '.strtoupper($operation).' ON payments
                WHEN (NEW.order_id IS NULL AND NEW.reservation_id IS NULL)
                    OR (NEW.order_id IS NOT NULL AND NEW.reservation_id IS NOT NULL)
                BEGIN SELECT RAISE(ABORT, \'A payment must belong to exactly one order or reservation.\'); END');
        }
    }

    /** @param array<string, mixed> $foreignKey */
    private function isRestrictive(array $foreignKey): bool
    {
        return in_array($foreignKey['on_delete'], ['restrict', 'no action'], true)
            && in_array($foreignKey['on_update'], ['restrict', 'no action'], true);
    }

    public function down(): void
    {
        throw new LogicException('Payment parent integrity is forward-only. Use a forward repair; do not restore orphan-producing foreign keys.');
    }
};
