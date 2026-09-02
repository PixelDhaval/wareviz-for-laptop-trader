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
        Schema::create('laptops', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('laptop_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('processor_id')->constrained()->restrictOnDelete();
            $table->string('serial_no')->nullable();
            $table->string('generation')->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->unsignedSmallInteger('storage_gb')->nullable();
            $table->boolean('has_builtin_ram')->default(false);
            $table->unsignedSmallInteger('builtin_ram_gb')->nullable();
            $table->unsignedSmallInteger('builtin_storage_gb')->nullable();
            $table->boolean('is_battery_ok')->default(true);
            $table->boolean('is_lcd_ok')->default(true);
            $table->boolean('is_bezel_ok')->default(true);
            $table->boolean('is_top_cover_ok')->default(true);
            $table->boolean('is_body_ok')->default(true);
            $table->boolean('is_back_cover_ok')->default(true);
            $table->boolean('is_keyboard_ok')->default(true);
            $table->boolean('is_touchpad_ok')->default(true);
            $table->text('issues')->nullable();
            $table->boolean('has_issues')->default(false);
            $table->string('status')->default('in_stock');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('serial_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laptops');
    }
};
