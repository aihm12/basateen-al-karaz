<?php

namespace Tests\Feature;

use App\Models\BranchProductVariant;
use App\Models\DeliverySlot;
use App\Models\OtpChallenge;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FoodicsIntegration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function customer(string $phone = '+966500000001'): User
    {
        $user = User::create(['name' => 'Test Customer', 'phone' => $phone, 'phone_verified_at' => now()]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function addTomatoes(int $quantity = 2)
    {
        return $this->postJson('/api/cart/items', ['branch_id' => 1, 'variant_id' => ProductVariant::where('sku', 'BK-DEMO-2-01')->value('id'), 'quantity' => $quantity]);
    }

    private function checkout(string $key = 'test-order-0001', array $extra = [])
    {
        return $this->postJson('/api/orders', array_merge(['delivery_type' => 'pickup', 'payment_method' => 'cash'], $extra), ['Idempotency-Key' => $key]);
    }

    public function test_catalog_and_seed_are_complete_and_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/products')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/products/1')->assertOk()->assertJsonCount(2, 'data.variants')->assertJsonCount(1, 'data.option_groups');
        $this->getJson('/api/products?search=Mango')->assertJsonCount(1, 'data');
        $this->getJson('/api/products?per_page=500')->assertUnprocessable();
        Product::find(1)->update(['is_active' => false]);
        $this->getJson('/api/products/1')->assertNotFound();
    }

    public function test_private_routes_require_authentication(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->checkout()->assertUnauthorized();
    }

    public function test_otp_issues_real_token_and_prevents_replay_and_revokes_logout(): void
    {
        $phone = '+966500000010';
        $code = $this->postJson('/api/auth/send-otp', compact('phone'))->assertStatus(202)->json('development_code');
        $this->assertNotSame($code, OtpChallenge::first()->code_hash);
        $token = $this->postJson('/api/auth/verify-otp', compact('phone', 'code'))->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('phone', $phone);
        $this->postJson('/api/auth/verify-otp', compact('phone', 'code'))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_otp_wrong_attempt_is_persisted_and_expired_code_fails(): void
    {
        OtpChallenge::create(['phone' => '+966500000011', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(5)]);
        $this->postJson('/api/auth/verify-otp', ['phone' => '+966500000011', 'code' => '654321'])->assertUnprocessable();
        $this->assertSame(1, OtpChallenge::first()->attempts);
        OtpChallenge::first()->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/auth/verify-otp', ['phone' => '+966500000011', 'code' => '123456'])->assertUnprocessable();
    }

    public function test_otp_rate_limit_and_local_provider_fails_closed_in_production(): void
    {
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500000012'])->assertStatus(202);
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500000012'])->assertStatus(429);
        $this->app->instance('env', 'production');
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500000013'])->assertStatus(503)->assertJsonMissingPath('development_code');
    }

    public function test_required_duplicate_and_foreign_options_are_rejected(): void
    {
        $this->customer();
        $base = ['branch_id' => 1, 'variant_id' => 1, 'quantity' => 1];
        $this->postJson('/api/cart/items', $base)->assertUnprocessable();
        $this->postJson('/api/cart/items', $base + ['option_value_ids' => [1, 1]])->assertUnprocessable();
        $this->postJson('/api/cart/items', $base + ['option_value_ids' => [4]])->assertUnprocessable();
        $this->postJson('/api/cart/items', $base + ['option_value_ids' => [1], 'unit_price' => '0.01'])->assertCreated()->assertJsonPath('subtotal', '24.95');
    }

    public function test_cart_update_delete_and_ownership(): void
    {
        $first = $this->customer();
        $id = $this->addTomatoes()->assertCreated()->json('data.items.0.id');
        $this->patchJson('/api/cart/items/'.$id, ['quantity' => 3])->assertOk()->assertJsonPath('subtotal', '22.50');
        $this->customer('+966500000002');
        $this->deleteJson('/api/cart/items/'.$id)->assertNotFound();
        Sanctum::actingAs($first);
        $this->deleteJson('/api/cart/items/'.$id)->assertNoContent();
        $this->getJson('/api/cart')->assertJsonPath('subtotal', '0.00');
    }

    public function test_checkout_reprices_and_snapshots_and_is_idempotent(): void
    {
        $this->customer();
        $this->addTomatoes()->assertCreated();
        ProductVariant::where('sku', 'BK-DEMO-2-01')->update(['price' => '9.00']);
        $first = $this->checkout()->assertCreated()->assertJsonPath('data.total', '18.00')->assertJsonPath('data.payment_status', 'pending')->json('data');
        $this->checkout()->assertCreated()->assertJsonPath('data.id', $first['id']);
        $this->checkout('test-order-0001', ['customer_notes' => 'changed'])->assertStatus(409);
        ProductVariant::where('sku', 'BK-DEMO-2-01')->update(['price' => '20.00']);
        $this->getJson('/api/orders/'.$first['id'])->assertOk()->assertJsonPath('data.items.0.unit_price', '9.00');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('integration_logs', ['provider' => 'foodics', 'status' => 'deferred']);
    }

    public function test_order_privacy_and_empty_cart(): void
    {
        $this->customer();
        $this->checkout()->assertUnprocessable();
        $this->addTomatoes();
        $id = $this->checkout()->assertCreated()->json('data.id');
        $this->customer('+966500000002');
        $this->getJson('/api/orders/'.$id)->assertNotFound();
        $this->getJson('/api/orders')->assertJsonCount(0, 'data');
    }

    public function test_coupon_discount_and_per_customer_limit(): void
    {
        $this->customer();
        $this->addTomatoes(10);
        $this->checkout('coupon-order-1', ['coupon_code' => 'WELCOME20'])->assertCreated()->assertJsonPath('data.discount', '15.00')->assertJsonPath('data.total', '60.00')->assertJsonPath('data.tax', '7.83');
        $this->addTomatoes(10);
        $this->checkout('coupon-order-2', ['coupon_code' => 'WELCOME20'])->assertUnprocessable();
    }

    public function test_finite_stock_checks_aggregate_cart_and_checkout(): void
    {
        $this->customer();
        BranchProductVariant::where('product_variant_id', 2)->update(['stock_quantity' => 3]);
        $this->addTomatoes(2)->assertCreated();
        $this->addTomatoes(2)->assertUnprocessable();
        $this->checkout()->assertCreated();
        $this->assertDatabaseHas('branch_product_variants', ['product_variant_id' => 2, 'stock_quantity' => 1]);
    }

    public function test_delivery_address_slot_capacity_and_snapshot(): void
    {
        $this->customer();
        $address = $this->postJson('/api/addresses', ['label' => 'Home', 'district' => 'حي تجريبي', 'street' => 'Street', 'delivery_zone_id' => 1, 'maps_url' => 'https://maps.app.goo.gl/example'])->assertCreated()->json('data.id');
        $date = now()->addDay()->toDateString();
        $slot = DeliverySlot::where('day_of_week', now()->addDay()->dayOfWeek)->first();
        $slot->update(['max_orders' => 1]);
        $this->addTomatoes(8);
        $details = ['delivery_type' => 'delivery', 'address_id' => $address, 'delivery_date' => $date, 'delivery_slot_id' => $slot->id];
        $this->checkout('delivery-order-1', $details)->assertCreated()->assertJsonPath('data.delivery_fee', '10.00')->assertJsonPath('data.total', '70.00')->assertJsonPath('data.address_snapshot.street', 'Street')->assertJsonPath('data.address_snapshot.maps_url', 'https://maps.app.goo.gl/example');
        $this->addTomatoes(8);
        $this->checkout('delivery-order-2', $details)->assertUnprocessable();
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_delivery_cannot_use_other_customers_address(): void
    {
        $first = $this->customer();
        $address = $first->addresses()->create(['label' => 'Home', 'district' => 'حي تجريبي', 'street' => 'Street', 'delivery_zone_id' => 1]);
        $this->customer('+966500000002');
        $this->addTomatoes(8);
        $this->checkout('foreign-address', ['delivery_type' => 'delivery', 'address_id' => $address->id, 'delivery_date' => now()->addDay()->toDateString(), 'delivery_slot_id' => 1])->assertNotFound();
    }

    public function test_integration_failure_rolls_back_order_payment_stock_and_cart(): void
    {
        $this->customer();
        BranchProductVariant::where('product_variant_id', 2)->update(['stock_quantity' => 10]);
        $this->addTomatoes(2);
        $this->mock(FoodicsIntegration::class)->shouldReceive('queueOrder')->once()->andThrow(new \RuntimeException('Simulated outbox failure'));
        $this->checkout()->assertStatus(500);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('branch_product_variants', ['product_variant_id' => 2, 'stock_quantity' => 10]);
    }

    public function test_disabled_customer_cannot_use_existing_token(): void
    {
        $user = $this->customer();
        $user->forceFill(['is_active' => false])->save();
        $this->getJson('/api/cart')->assertForbidden();
    }
}
