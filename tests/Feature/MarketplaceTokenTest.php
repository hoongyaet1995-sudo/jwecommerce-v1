<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_tokens_are_encrypted_independent_and_hidden(): void
    {
        $ids = [];
        foreach (['one', 'two'] as $name) {
            $ids[] = DB::table('marketplace_stores')->insertGetId([
                'platform' => 'shopee', 'shop_name' => $name, 'region' => 'MY',
                'environment' => 'sandbox', 'status' => 'pending_auth',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($ids as $id) {
            MarketplaceStoreToken::create([
                'store_id' => $id, 'access_token' => 'access-'.$id,
                'refresh_token' => 'refresh-'.$id, 'access_expires_at' => now()->addHours(4),
            ]);
        }
        foreach ($ids as $id) {
            $raw = DB::table('marketplace_store_tokens')->where('store_id', $id)->first();
            $this->assertNotSame('access-'.$id, $raw->access_token);
            $this->assertNotSame('refresh-'.$id, $raw->refresh_token);
            $token = MarketplaceStoreToken::where('store_id', $id)->firstOrFail();
            $this->assertSame('access-'.$id, $token->access_token);
            $this->assertSame('refresh-'.$id, $token->refresh_token);
            $this->assertArrayNotHasKey('access_token', $token->toArray());
            $this->assertArrayNotHasKey('refresh_token', $token->toArray());
            $this->assertNull($token->refresh_expires_at);
        }
    }
}
