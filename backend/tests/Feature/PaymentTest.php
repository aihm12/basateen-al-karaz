<?php

namespace Tests\Feature;

use App\Models\BranchProductVariant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogEnrichmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_cloud_test_requires_explicit_opt_in_and_rejects_live_keys(): void
    {
        $this->app->instance('env', 'production');
        config(['payments.enabled' => true, 'payments.secret_key' => 'sk_test_fake', 'payments.allow_cloud_test' => false]);
        $this->getJson('/api/payment-config')->assertJsonPath('online_enabled', false);
        config(['payments.allow_cloud_test' => true]);
        $this->getJson('/api/payment-config')->assertJsonPath('online_enabled', true)->assertJsonPath('mode', 'test');
        config(['payments.secret_key' => 'sk_live_forbidden']);
        $this->getJson('/api/payment-config')->assertJsonPath('online_enabled', false);
    }

    public function test_return_page_does_not_claim_payment_success(): void
    {
        $this->get('/payment/return?status=paid')->assertOk()->assertSee('التحقق من نتيجة الدفع')->assertSee('لا يعني تأكيد الدفع');
    }

    public function test_native_payment_is_bound_to_order_and_verified_on_server(): void
    {
        $order = $this->onlineOrder();
        config(['payments.publishable_key' => 'pk_test_fake']);
        Http::preventStrayRequests();
        $url = '/api/orders/'.$order->id;
        $session = $this->postJson($url.'/native-payment-session', ['amount' => 1])->assertOk()->assertJsonPath('amount', 1500)->assertJsonMissingPath('secret_key');
        $id = $session->json('payment_id');
        $this->postJson($url.'/native-payment-session')->assertJsonPath('payment_id', $id);
        $this->postJson($url.'/payment-session')->assertStatus(409);
        $data = ['id' => $id, 'amount' => 1500, 'currency' => 'SAR', 'description' => $order->order_number, 'status' => 'paid'];
        Http::fake(['https://api.moyasar.com/v1/payments/'.$id => Http::sequence()->push(array_merge($data, ['amount' => 1]))->push(array_merge($data, ['live' => true]))->push($data)->push($data)]);
        $this->postJson($url.'/native-verify-payment', ['status' => 'paid'])->assertStatus(409);
        $this->postJson($url.'/native-verify-payment')->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->postJson($url.'/native-verify-payment')->assertJsonPath('data.payment_status', 'paid');
        $this->postJson($url.'/native-verify-payment')->assertOk();
        Sanctum::actingAs(User::create(['name' => 'Other native', 'phone' => '+966500000088']));
        $this->postJson($url.'/native-payment-session')->assertNotFound();
        $this->postJson($url.'/native-verify-payment')->assertNotFound();
    }

    private function onlineOrder(): Order
    {
        config(['payments.enabled' => true, 'payments.secret_key' => 'sk_test_fake']);
        $user = User::create(['name' => 'Payment test', 'phone' => '+966500000022']);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['branch_id' => 1, 'variant_id' => 2, 'quantity' => 2])->assertCreated();
        $response = $this->postJson('/api/orders', ['delivery_type' => 'pickup', 'payment_method' => 'moyasar'], ['Idempotency-Key' => 'payment-test-order'])->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    public function test_disabled_or_live_key_never_enables_online_payments(): void
    {
        Http::preventStrayRequests();
        config(['payments.enabled' => true, 'payments.secret_key' => 'sk_live_forbidden']);
        $this->getJson('/api/payment-config')->assertOk()->assertJsonPath('online_enabled', false)->assertJsonMissingPath('secret_key');
        Http::assertNothingSent();
    }

    public function test_creates_single_invoice_and_verifies_amount_from_provider(): void
    {
        $order = $this->onlineOrder();
        Http::preventStrayRequests();
        $invoice = ['id' => 'invoice-test-123', 'url' => 'https://moyasar.com/invoices/test', 'amount' => 1500, 'currency' => 'SAR', 'description' => $order->order_number, 'status' => 'initiated'];
        Http::fake(['https://api.moyasar.com/v1/invoices' => Http::response($invoice, 201)]);
        $this->postJson('/api/orders/'.$order->id.'/payment-session', ['amount' => 1])->assertOk()->assertJsonPath('checkout_url', $invoice['url']);
        $this->postJson('/api/orders/'.$order->id.'/payment-session')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['amount'] === 1500 && $request['currency'] === 'SAR');
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'invoice_id' => $invoice['id'], 'status' => 'pending']);
        Http::fake(['https://api.moyasar.com/v1/invoices/invoice-test-123' => Http::sequence()
            ->push(array_merge($invoice, ['status' => 'paid', 'amount' => 100]))
            ->push(array_merge($invoice, ['status' => 'paid']))
            ->push(array_merge($invoice, ['status' => 'paid']))]);
        $this->postJson('/api/orders/'.$order->id.'/verify-payment', ['status' => 'paid'])->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->postJson('/api/orders/'.$order->id.'/verify-payment')->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->postJson('/api/orders/'.$order->id.'/verify-payment')->assertOk();
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_foreign_customer_cannot_create_or_verify_payment(): void
    {
        $order = $this->onlineOrder();
        Http::preventStrayRequests();
        Sanctum::actingAs(User::create(['name' => 'Other', 'phone' => '+966500000023']));
        $this->postJson('/api/orders/'.$order->id.'/payment-session')->assertNotFound();
        $this->postJson('/api/orders/'.$order->id.'/verify-payment')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_uncertain_create_requires_review_instead_of_duplicate_charge(): void
    {
        $order = $this->onlineOrder();
        Http::preventStrayRequests();
        Http::fake(['https://api.moyasar.com/v1/invoices' => Http::response([], 503)]);
        $this->postJson('/api/orders/'.$order->id.'/payment-session')->assertStatus(502);
        $this->postJson('/api/orders/'.$order->id.'/payment-session')->assertStatus(409);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'session_state' => 'review_required', 'status' => 'pending']);
    }

    public function test_redirect_payload_alone_cannot_mark_payment_paid(): void
    {
        $order = $this->onlineOrder();
        Http::preventStrayRequests();
        $this->postJson('/api/orders/'.$order->id.'/verify-payment', ['status' => 'paid', 'amount' => 1500])->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_catalog_enrichment_adds_products_without_overwriting_changes(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);
        $product = Product::where('sku', 'BK-CHERRY')->firstOrFail();
        $product->update(['description_ar' => 'وصف يخص المستخدم']);
        $product->variants()->first()->update(['price' => '33.00']);
        BranchProductVariant::where('product_variant_id', $product->variants()->first()->id)->update(['stock_quantity' => 4, 'is_available' => false]);
        $this->seed(CatalogEnrichmentSeeder::class);
        $this->assertSame(12, Product::count());
        $this->assertSame('وصف يخص المستخدم', $product->fresh()->description_ar);
        $this->assertSame('33.00', $product->variants()->first()->price);
        $this->assertDatabaseHas('branch_product_variants', ['product_variant_id' => $product->variants()->first()->id, 'stock_quantity' => 4, 'is_available' => false]);
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('data.main_image', '/images/catalog/cherries.png');
    }
}
