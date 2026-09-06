<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Check before any DDL: never resolve historical conflicts by deleting data.
        $conflict = DB::table('marketplace_variant_mappings as m')
            ->join('marketplace_listing_variants as l', 'l.id', '=', 'm.listing_variant_id')
            ->select('l.store_id', 'm.product_variant_id')
            ->groupBy('l.store_id', 'm.product_variant_id')->havingRaw('COUNT(*) > 1')->first();
        if ($conflict) {
            throw new RuntimeException('Marketplace mapping conflict: store '.$conflict->store_id.', JW variant '.$conflict->product_variant_id.'. Resolve mappings explicitly before retrying migration.');
        }

        Schema::table('marketplace_listing_variants', function (Blueprint $table) {
            $table->unique(['id', 'store_id'], 'mp_listing_store_identity');
        });
        Schema::table('marketplace_variant_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable();
        });
        DB::table('marketplace_variant_mappings')->orderBy('id')->chunkById(500, function ($rows) {
            $stores = DB::table('marketplace_listing_variants')->whereIn('id', $rows->pluck('listing_variant_id'))->pluck('store_id', 'id');
            foreach ($rows as $row) {
                DB::table('marketplace_variant_mappings')->where('id', $row->id)->update(['store_id'=>$stores[$row->listing_variant_id]]);
            }
        });
        Schema::table('marketplace_variant_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable(false)->change();
            $table->unique(['store_id', 'product_variant_id'], 'mp_store_jw_variant_unique');
            $table->foreign(['listing_variant_id', 'store_id'], 'mp_mapping_listing_store_fk')
                ->references(['id', 'store_id'])->on('marketplace_listing_variants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_variant_mappings', function (Blueprint $table) {
            $table->dropForeign('mp_mapping_listing_store_fk')->columns(['listing_variant_id', 'store_id']);
            $table->dropUnique('mp_store_jw_variant_unique');
            $table->dropColumn('store_id');
        });
        Schema::table('marketplace_listing_variants', function (Blueprint $table) {
            $table->dropUnique('mp_listing_store_identity');
        });
    }
};
