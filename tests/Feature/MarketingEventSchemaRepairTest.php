<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingEventSchemaRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_interrupted_schema_repair_preserves_duplicate_evidence_and_restores_uniqueness(): void
    {
        $index=collect(Schema::getIndexes('marketing_analytics_events'))->first(fn($i)=>$i['unique']&&$i['columns']===['dedupe_key']);
        Schema::table('marketing_analytics_events',fn(Blueprint $t)=>$t->dropUnique($index['name']));
        $row=['merchant_key'=>'local-test','campaign_id'=>(string)Str::uuid(),'link_id'=>(string)Str::uuid(),'kind'=>'arrival','dedupe_key'=>'same-arrival','session_key'=>'same-session','occurred_at'=>now()];
        DB::table('marketing_analytics_events')->insert([$row,$row]);
        $migration=require database_path('migrations/2026_10_29_050000_repair_marketing_event_deduplication.php');
        $migration->up(); $migration->up();
        $this->assertDatabaseCount('marketing_analytics_events',2);
        $this->assertDatabaseHas('marketing_analytics_events',['kind'=>'arrival','dedupe_key'=>'same-arrival']);
        $archived=DB::table('marketing_analytics_events')->where('kind','duplicate_legacy')->first();
        $this->assertSame('same-arrival',json_decode($archived->details,true)['dedupe_repair']['original_key']);
        DB::table('marketing_analytics_events')->insertOrIgnore($row);
        $this->assertDatabaseCount('marketing_analytics_events',2);
    }
}
