<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repair_jobs', function (Blueprint $table) {
            $table->foreignId('cost_currency_id')->nullable()->after('cost')->constrained('currencies')->restrictOnDelete();
            $table->decimal('cost_exchange_rate', 16, 6)->default(1)->after('cost_currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repair_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_currency_id');
            $table->dropColumn('cost_exchange_rate');
        });
    }
};
