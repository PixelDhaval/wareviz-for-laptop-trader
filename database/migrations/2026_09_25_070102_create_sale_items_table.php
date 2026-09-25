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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete, not cascade: Sale::deleting() removes each item
            // individually first, so SaleItem::deleted() fires and reverts the
            // laptop's status. A DB-level cascade would skip that model event.
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('laptop_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->foreignId('price_currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->decimal('price_exchange_rate', 16, 6)->default(1);
            $table->timestamps();

            $table->unique(['sale_id', 'laptop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
