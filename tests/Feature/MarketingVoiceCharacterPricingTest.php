<?php
namespace Tests\Feature;
use App\Services\Marketing\MarketingVoiceCharacterPricing;
use Tests\TestCase;
class MarketingVoiceCharacterPricingTest extends TestCase {
 private function price():array{return ['model'=>'eleven_multilingual_v2','billing_unit'=>'character','per_character_microusd'=>80,'cap_microusd'=>10000,'pricing_verified_at'=>'2026-10-25','pricing_valid_until'=>now()->addDay()->toIso8601String()];}
 public function test_request_upper_bound_counts_unicode_characters_not_utf8_bytes():void{$p=app(MarketingVoiceCharacterPricing::class);$this->assertSame(240,$p->bound('你好。',$this->price()));$this->assertSame(240,$p->actual('3','你好。',$this->price()));}
 public function test_missing_negative_or_unexpected_billing_units_are_unknown():void{$p=app(MarketingVoiceCharacterPricing::class);foreach([null,'x','-1','2','4','1.5'] as $v)$this->assertNull($p->actual($v,'你好。',$this->price()));}
 public function test_unknown_rate_or_expired_price_stops_before_request():void{$cfg=$this->price();$cfg['pricing_valid_until']='2000-01-01';$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingVoiceCharacterPricing::class)->bound('你好。',$cfg);}
 public function test_request_above_cap_stops_before_request():void{$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingVoiceCharacterPricing::class)->bound(str_repeat('茶',126),$this->price());}
}
