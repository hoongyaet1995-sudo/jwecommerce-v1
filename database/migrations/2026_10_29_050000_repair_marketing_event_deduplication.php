<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        $indexes = Schema::getIndexes('marketing_analytics_events');
        if (collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === ['dedupe_key'])) return;

        // Recover interrupted MySQL DDL without discarding the original evidence.
        $groups = DB::table('marketing_analytics_events')->select('dedupe_key')
            ->groupBy('dedupe_key')->havingRaw('COUNT(*) > 1')->get();
        foreach ($groups as $group) {
            $rows = DB::table('marketing_analytics_events')->where('dedupe_key', $group->dedupe_key)->orderBy('id')->get();
            foreach ($rows->skip(1) as $row) {
                $details = json_decode($row->details ?? '{}', true) ?: [];
                $details['dedupe_repair'] = ['original_kind'=>$row->kind,'original_key'=>$row->dedupe_key,'canonical_event_id'=>$rows->first()->id];
                DB::table('marketing_analytics_events')->where('id', $row->id)->update([
                    'kind'=>'duplicate_legacy', 'dedupe_key'=>'repair:'.$row->id.':'.hash('sha256', $row->dedupe_key),
                    'details'=>json_encode($details),
                ]);
            }
        }
        Schema::table('marketing_analytics_events', fn (Blueprint $table) => $table->unique('dedupe_key', 'mkt_events_dedupe_unique'));
    }

    public function down(): void
    {
        // Evidence repair is intentionally irreversible; never restore duplicate identities.
    }
};
