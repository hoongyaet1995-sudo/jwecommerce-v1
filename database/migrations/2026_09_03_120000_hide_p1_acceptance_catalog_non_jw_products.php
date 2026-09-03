<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const HANDLES = ['t', 'ps5-pro', 'razer-bluetooth-mouse-rz-250'];

    public function up(): void
    {
        DB::table('products')->whereIn('handle', self::HANDLES)->update(['status' => 'draft']);
    }

    public function down(): void
    {
        DB::table('products')->whereIn('handle', self::HANDLES)->update(['status' => 'active']);
    }
};
