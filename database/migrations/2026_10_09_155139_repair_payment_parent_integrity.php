<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $repair = require __DIR__.'/2026_09_16_123958_enforce_payments_have_exactly_one_parent.php';
        $repair->up();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Payment parent integrity is forward-only. Use a forward repair instead of rolling back.');
    }
};
