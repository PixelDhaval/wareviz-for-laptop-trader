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
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('fiscal_year_start_month')->nullable();

            $table->string('laptop_code_prefix')->nullable()->default('WV');
            $table->string('laptop_code_suffix')->nullable();
            $table->string('laptop_code_separator')->nullable();
            $table->string('laptop_code_date_source')->nullable();
            $table->string('laptop_code_date_format')->nullable();
            $table->unsignedTinyInteger('laptop_code_sequence_pad')->default(6);

            $table->string('sale_code_prefix')->nullable();
            $table->string('sale_code_suffix')->nullable();
            $table->string('sale_code_separator')->nullable()->default('-');
            $table->string('sale_code_date_format')->nullable();
            $table->string('sale_code_date_position')->default('before');
            $table->unsignedTinyInteger('sale_code_sequence_pad')->default(4);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_year_start_month',
                'laptop_code_prefix',
                'laptop_code_suffix',
                'laptop_code_separator',
                'laptop_code_date_source',
                'laptop_code_date_format',
                'laptop_code_sequence_pad',
                'sale_code_prefix',
                'sale_code_suffix',
                'sale_code_separator',
                'sale_code_date_format',
                'sale_code_date_position',
                'sale_code_sequence_pad',
            ]);
        });
    }
};
