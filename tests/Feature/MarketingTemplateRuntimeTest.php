<?php
namespace Tests\Feature;
use Tests\TestCase;
class MarketingTemplateRuntimeTest extends TestCase{
 public function test_missing_browser_is_detected_without_starting_generation():void{
  config(['marketing.production.browser_binary'=>base_path('does-not-exist/chrome.exe')]);$this->assertFalse(app(\App\Services\Marketing\MarketingTemplateRuntime::class)->ready());
 }
}
