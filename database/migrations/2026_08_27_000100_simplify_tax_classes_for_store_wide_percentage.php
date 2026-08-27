<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_classes', function (Blueprint $table): void {
            $table->decimal('rate_percent', 8, 4)->default(0)->after('description');
            $table->unsignedTinyInteger('active_slot')->nullable()->after('is_active');
            $table->unique('name');
            $table->unique('active_slot');
        });

        DB::table('products')->update(['tax_class_id' => null]);
        DB::table('tax_classes')->update([
            'is_active' => false,
            'active_slot' => null,
            'rate_percent' => 0,
        ]);

        $proofClasses = ['Browser Acceptance Tax Class', 'Closure Proof Goods 7%'];
        $proofZones = ['Browser Acceptance Malaysia Zone', 'Closure Proof Malaysia Zone'];

        DB::table('tax_rules')
            ->whereIn('tax_class_id', DB::table('tax_classes')->whereIn('name', $proofClasses)->select('id'))
            ->delete();

        DB::table('tax_classes')->whereIn('name', $proofClasses)->delete();

        DB::table('tax_zones')
            ->whereIn('name', $proofZones)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('tax_rules')
                    ->whereColumn('tax_rules.tax_zone_id', 'tax_zones.id');
            })
            ->delete();
    }

    public function down(): void
    {
        Schema::table('tax_classes', function (Blueprint $table): void {
            $table->dropUnique('tax_classes_name_unique');
            $table->dropUnique('tax_classes_active_slot_unique');
            $table->dropColumn(['rate_percent', 'active_slot']);
        });
    }
};
