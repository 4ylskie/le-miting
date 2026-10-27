<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergens', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
        });

        $vocabulary = [
            'peanut' => 'Peanut', 'tree_nuts' => 'Tree nuts', 'milk' => 'Milk',
            'egg' => 'Egg', 'soy' => 'Soy', 'wheat_gluten' => 'Wheat/gluten',
            'fish' => 'Fish', 'crustacean_shellfish' => 'Crustacean shellfish',
            'molluscs' => 'Molluscs', 'sesame' => 'Sesame',
        ];
        foreach (array_keys($vocabulary) as $index => $code) {
            DB::table('allergens')->insert([
                'code' => $code, 'name' => $vocabulary[$code], 'sort_order' => $index,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('menu_item_allergen_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('allergen_id')->constrained()->restrictOnDelete();
            $table->enum('recipe_status', ['unknown', 'contains', 'reviewed_absent'])->default('unknown');
            $table->enum('cross_contact_status', ['unknown', 'possible', 'reviewed_no_known_risk'])->default('unknown');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at', 6);
            $table->text('evidence');
            $table->char('menu_fingerprint', 64);
            $table->timestamp('invalidated_at', 6)->nullable();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('invalidation_reason')->nullable();
            $table->timestamps(6);
            $table->unique(['menu_item_id', 'allergen_id'], 'menu_allergen_pair_unique');
        });
        DB::statement('ALTER TABLE menu_item_allergen_assessments ADD CONSTRAINT assessment_evidence_required CHECK (CHAR_LENGTH(TRIM(evidence)) > 0)');
        DB::statement('ALTER TABLE menu_item_allergen_assessments ADD CONSTRAINT assessment_invalidation_complete CHECK ((invalidated_at IS NULL AND invalidated_by IS NULL AND invalidation_reason IS NULL) OR (invalidated_at IS NOT NULL AND invalidated_by IS NOT NULL AND invalidation_reason IS NOT NULL AND CHAR_LENGTH(TRIM(invalidation_reason)) > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_allergen_assessments');
        Schema::dropIfExists('allergens');
    }
};
