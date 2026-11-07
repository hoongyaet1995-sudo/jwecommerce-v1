<?php
namespace Tests\Feature;

use App\Models\{MarketingPostAutomation,User};
use App\Services\Marketing\MarketingAutomationSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingAutomationScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function automation(): MarketingPostAutomation
    {
        return MarketingPostAutomation::create(['id'=>(string)Str::uuid(),'user_id'=>User::factory()->create()->id,'merchant_key'=>'test','name'=>'Monday','preset'=>'spotlight','version'=>1,'schedule_enabled'=>true,'schedule_epoch'=>'2026-11-01 00:00:00','schedule_checked_at'=>'2026-11-08 00:00:00','settings'=>['days'=>[1,3],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur']]);
    }

    public function test_boundary_and_duplicate_ticks_reserve_once(): void
    {
        $a=$this->automation();$clock=CarbonImmutable::parse('2026-11-09 10:30','Asia/Kuala_Lumpur');
        $s=app(MarketingAutomationSchedule::class);$ids=$s->reserveDue($clock);
        $this->assertCount(1,$ids);$this->assertSame([],$s->reserveDue($clock));
        $row=DB::table('marketing_automation_occurrences')->first();
        $this->assertSame('queued',$row->status);$this->assertSame('2026-11-09 02:00:00',$row->scheduled_at);
        $this->assertSame($a->id,$row->automation_id);
    }

    public function test_expired_schedule_is_recorded_without_queueing_and_paused_is_ignored(): void
    {
        $a=$this->automation();$s=app(MarketingAutomationSchedule::class);
        $this->assertSame([],$s->reserveDue(CarbonImmutable::parse('2026-11-09 10:31','Asia/Kuala_Lumpur')));
        $this->assertDatabaseHas('marketing_automation_occurrences',['status'=>'skipped','reason'=>'catchup_expired']);
        $a->update(['schedule_enabled'=>false]);$s->reserveDue(CarbonImmutable::parse('2026-11-11 10:00','Asia/Kuala_Lumpur'));
        $this->assertSame(1,DB::table('marketing_automation_occurrences')->count());
    }

    public function test_activation_is_prospective_and_timezone_midnight_is_correct(): void
    {
        $a=$this->automation();$a->update(['schedule_epoch'=>'2026-11-09 02:01:00','settings'=>['days'=>[2],'time'=>'00:00','timezone'=>'Asia/Kuala_Lumpur']]);
        $s=app(MarketingAutomationSchedule::class);$this->assertCount(1,$s->reserveDue(CarbonImmutable::parse('2026-11-10 00:00','Asia/Kuala_Lumpur')));
        $this->assertDatabaseHas('marketing_automation_occurrences',['scheduled_at'=>'2026-11-09 16:00:00']);
    }
    public function test_occurrence_unique_key_rejects_duplicate_slot_even_with_another_generation_id():void
    {
        $this->automation();app(MarketingAutomationSchedule::class)->reserveDue(CarbonImmutable::parse('2026-11-09 10:00','Asia/Kuala_Lumpur'));
        $row=(array)DB::table('marketing_automation_occurrences')->first();$row['id']=(string)Str::uuid();$row['generation_id']=(string)Str::uuid();
        $this->expectException(\Illuminate\Database\QueryException::class);DB::table('marketing_automation_occurrences')->insert($row);
    }
}
