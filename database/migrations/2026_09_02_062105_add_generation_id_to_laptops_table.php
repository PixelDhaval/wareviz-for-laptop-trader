<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('laptops', function (Blueprint $table) {
            $table->foreignId('generation_id')->nullable()->after('processor_id')->constrained()->nullOnDelete();
        });

        DB::table('laptops')
            ->whereNotNull('generation')
            ->where('generation', '!=', '')
            ->distinct()
            ->pluck('generation')
            ->each(function (string $name): void {
                $generationId = DB::table('generations')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('laptops')->where('generation', $name)->update(['generation_id' => $generationId]);
            });

        Schema::table('laptops', function (Blueprint $table) {
            $table->dropColumn('generation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laptops', function (Blueprint $table) {
            $table->string('generation')->nullable()->after('processor_id');
        });

        DB::table('generations')->orderBy('id')->get(['id', 'name'])->each(function (object $generation): void {
            DB::table('laptops')
                ->where('generation_id', $generation->id)
                ->update(['generation' => $generation->name]);
        });

        Schema::table('laptops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generation_id');
        });
    }
};
