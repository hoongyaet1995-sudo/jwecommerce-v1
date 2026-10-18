<?php
namespace Tests\Feature\Admin;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AdminSessionExpiryTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real CSRF rejection, normally bypassed by Laravel's test environment.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests() { return false; }
        });
    }

    public function test_expired_login_form_redirects_to_fresh_login_without_replaying_credentials(): void
    {
        $this->withSession(['_token' => 'fresh-token'])
            ->post('/admin/login', ['_token' => 'stale-token', 'email' => 'test@example.com', 'password' => 'do-not-flash'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password');
        $this->get('/admin/login')->assertOk()->assertSee('name="_token"', false);
    }

    public function test_expired_admin_operation_is_not_executed_but_valid_csrf_still_works(): void
    {
        $executed = false;
        \Illuminate\Support\Facades\Route::middleware('web')->post('/admin/expiry-probe', function () use (&$executed) {
            $executed = true;
            return response('saved');
        });
        $this->withSession(['_token' => 'fresh-token'])->post('/admin/expiry-probe', ['_token' => 'stale-token'])
            ->assertStatus(303)->assertRedirect(route('admin.login'));
        $this->assertFalse($executed);
        $this->post('/admin/expiry-probe', ['_token' => 'fresh-token'])->assertOk();
        $this->assertTrue($executed);
    }
    public function test_admin_json_csrf_failure_remains_419_for_existing_client_redirect(): void
    {
        $this->withSession(['_token' => 'fresh-token'])->postJson('/admin/login', ['_token' => 'stale-token'])
            ->assertStatus(419);
    }

    public function test_customer_csrf_failures_are_not_redirected_to_admin(): void
    {
        $this->withSession(['_token' => 'fresh-token'])->post('/account/login', ['_token' => 'stale-token'])
            ->assertStatus(419);
    }
}
