<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('feedback');
    }

    public function down(): void
    {
        throw new LogicException('Dropping obsolete feedback data is irreversible. Restore a database backup instead of rolling back this migration.');
    }
};
