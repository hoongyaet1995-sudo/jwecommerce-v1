<?php
namespace Tests\Unit;
use App\Services\Marketplace\LazadaPackResult;
use PHPUnit\Framework\TestCase;
class LazadaPackResultTest extends TestCase
{
    public function test_partial_pack_keeps_individual_outcomes():void
    {
        $body=['code'=>'0','result'=>['success'=>true,'data'=>['pack_order_list'=>[['order_id'=>'100','order_item_list'=>[
            ['order_item_id'=>'101','item_err_code'=>'0','package_id'=>'PK1','tracking_number'=>'TRACK1'],
            ['order_item_id'=>'102','item_err_code'=>'E101','msg'=>'private message'],
        ]]]]]];
        $result=LazadaPackResult::parse($body,'100',['101','102']);
        $this->assertFalse($result['complete']);
        $this->assertSame('packed',$result['items']['101']['status']);
        $this->assertSame('PK1',$result['items']['101']['package_id']);
        $this->assertSame('unconfirmed',$result['items']['102']['status']);
        $this->assertStringNotContainsString('private message',json_encode($result));
        unset($body['result']['data']['pack_order_list'][0]['order_item_list'][1]);
        $this->expectException(\RuntimeException::class);
        LazadaPackResult::parse($body,'100',['101','102']);
    }
    public function test_success_requires_a_package_identity():void
    {
        $this->expectException(\RuntimeException::class);
        LazadaPackResult::parse(['code'=>'0','result'=>['success'=>true,'data'=>['pack_order_list'=>[
            ['order_id'=>'100','order_item_list'=>[['order_item_id'=>'101','item_err_code'=>'0']]]
        ]]]],'100',['101']);
    }
}
