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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_local_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('sale_export_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('invoice_value_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('freight_cost_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('local_expense_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('duty_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('other_expense_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
