<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cost lines, in the order they appear on the shipment. Each one gets an
     * amount, a currency foreign key, and the exchange rate (to the base
     * currency) that applied to this shipment.
     *
     * @var array<int, string>
     */
    private const COST_COLUMNS = [
        'invoice_value',
        'freight_cost',
        'local_expense',
        'duty',
        'other_expense',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (self::COST_COLUMNS as $column) {
                $table->decimal($column, 14, 2)->default(0);
                $table->foreignId("{$column}_currency_id")->nullable()->constrained('currencies')->restrictOnDelete();
                $table->decimal("{$column}_exchange_rate", 16, 6)->default(1);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops every stored shipment cost, so this is destructive once costs exist.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (self::COST_COLUMNS as $column) {
                $table->dropConstrainedForeignId("{$column}_currency_id");
                $table->dropColumn([$column, "{$column}_exchange_rate"]);
            }
        });
    }
};
