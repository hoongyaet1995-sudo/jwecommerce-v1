<?php
namespace Tests\Feature;
use App\Support\ShopBrand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
class ErrorViewDatabaseIndependenceTest extends TestCase {
 public function test_framework_error_views_never_resolve_database_backed_brand():void {
  config(['app.debug'=>false]);$calls=0;$brand=\Mockery::mock(ShopBrand::class);$brand->shouldReceive('name')->andReturnUsing(function()use(&$calls){$calls++;return 'Database-backed brand';});$this->app->instance(ShopBrand::class,$brand);
  foreach([404,419,500,503]as $status){$response=app(ExceptionHandler::class)->render(Request::create('/missing'),new HttpException($status));$this->assertSame($status,$response->getStatusCode());$this->assertStringNotContainsString('Database-backed brand',$response->getContent());}
  $this->assertSame(0,$calls,'Error page and its layout must not resolve the database-backed brand.');
 }
 public function test_original_database_exception_renders_without_a_second_brand_lookup():void {
  config(['app.debug'=>false]);$brand=\Mockery::mock(ShopBrand::class);$brand->shouldNotReceive('name');$this->app->instance(ShopBrand::class,$brand);
  $response=app(ExceptionHandler::class)->render(Request::create('/admin/pages'),new \PDOException('Original database outage'));
  $this->assertSame(500,$response->getStatusCode());$this->assertStringContainsString('Server Error',$response->getContent());$this->assertStringNotContainsString('Original database outage',$response->getContent());
 }
 public function test_normal_views_still_receive_the_saved_brand():void {
  $brand=\Mockery::mock(ShopBrand::class);$brand->shouldReceive('name')->once()->andReturn('Saved shop name');$this->app->instance(ShopBrand::class,$brand);
  view()->addNamespace('brand-test',base_path('tests/Fixtures/Views'));
  $this->assertSame('Saved shop name',trim(view('brand-test::normal')->render()));
 }
 public function test_http_database_failure_still_returns_the_original_static_error_page():void {
  config(['app.debug'=>false]);$brand=\Mockery::mock(ShopBrand::class);$brand->shouldNotReceive('name');$this->app->instance(ShopBrand::class,$brand);
  \Illuminate\Support\Facades\Route::get('/_test/error-database',fn()=>throw new \PDOException('Original database outage'));
  $this->get('/_test/error-database')->assertStatus(500)->assertSee('Server Error')->assertDontSee('Original database outage');
 }
}
