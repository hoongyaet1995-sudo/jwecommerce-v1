<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_revisions', fn (Blueprint $t) => $t->unsignedBigInteger('number')->default(0));
        $numbers = [];
        DB::table('page_revisions')->orderBy('id')->chunkById(100, function ($rows) use (&$numbers) {
            foreach ($rows as $r) {
                $numbers[$r->page_id] = ($numbers[$r->page_id] ?? 0) + 1;
                DB::table('page_revisions')->where('id', $r->id)->update(['number' => $numbers[$r->page_id], 'created_at' => $r->created_at]);
            }
        });
        Schema::table('page_revisions', fn (Blueprint $t) => $t->unique(['page_id', 'number']));
    }

    public function down(): void
    {
        Schema::table('page_revisions', function (Blueprint $t) {
            $t->dropUnique(['page_id', 'number']);
            $t->dropColumn('number');
        });
    }
};
