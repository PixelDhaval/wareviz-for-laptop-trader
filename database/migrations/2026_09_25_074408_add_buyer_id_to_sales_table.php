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
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('buyer_id')->nullable()->after('type')->constrained()->restrictOnDelete();
        });

        // Backfill: one Buyer per distinct (buyer_name, buyer_contact) pair,
        // same approach as add_generation_id_to_laptops_table. Contact info
        // now lives on the buyer record, not duplicated per sale, so
        // buyer_contact has nowhere to go but into the new buyers row.
        DB::table('sales')
            ->whereNotNull('buyer_name')
            ->where('buyer_name', '!=', '')
            ->select('buyer_name', 'buyer_contact')
            ->distinct()
            ->get()
            ->each(function (object $row): void {
                $buyerId = DB::table('buyers')->insertGetId([
                    'name' => $row->buyer_name,
                    'phone' => $row->buyer_contact,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('sales')
                    ->where('buyer_name', $row->buyer_name)
                    ->where(function ($query) use ($row) {
                        $row->buyer_contact === null
                            ? $query->whereNull('buyer_contact')
                            : $query->where('buyer_contact', $row->buyer_contact);
                    })
                    ->update(['buyer_id' => $buyerId]);
            });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['buyer_name', 'buyer_contact']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->after('type');
            $table->string('buyer_contact')->nullable()->after('buyer_name');
        });

        DB::table('buyers')->orderBy('id')->get(['id', 'name', 'phone'])->each(function (object $buyer): void {
            DB::table('sales')
                ->where('buyer_id', $buyer->id)
                ->update(['buyer_name' => $buyer->name, 'buyer_contact' => $buyer->phone]);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_id');
        });
    }
};
