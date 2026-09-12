<?php
namespace Tests\Unit;
use App\Services\Marketplace\LazadaRtsResult;
use PHPUnit\Framework\TestCase;
class LazadaRtsResultTest extends TestCase
{
 public function test_partial_success_is_not_hidden_by_outer_success():void
 {
  $result=LazadaRtsResult::parse(['code'=>'0','result'=>['success'=>true,'data'=>['packages'=>[
   ['package_id'=>'A','item_err_code'=>'0'],['package_id'=>'B','item_err_code'=>'600002','msg'=>'private-provider-text','retry'=>true],
  ]]]],['A','B']);
  $this->assertFalse($result['complete']);
  $this->assertSame('ready_to_ship',$result['packages']['A']['status']);
  $this->assertSame('unconfirmed',$result['packages']['B']['status']);
  $this->assertStringNotContainsString('private-provider-text',json_encode($result));
 }
 public function test_missing_package_cannot_be_confirmed():void
 {
  $this->expectException(\RuntimeException::class);
  LazadaRtsResult::parse(['code'=>'0','result'=>['success'=>true,'data'=>['packages'=>[['package_id'=>'A','item_err_code'=>'0']]]]],['A','B']);
 }
}
