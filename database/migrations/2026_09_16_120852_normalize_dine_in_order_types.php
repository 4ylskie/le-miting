<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('order_type', 'dine_in')
            ->update(['order_type' => 'dine-in']);
    }

    public function down(): void {}
};
