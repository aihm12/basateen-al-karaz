<?php

namespace Tests\Feature;

use App\Models\BranchProductVariant;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function manager(): Staff
    {
        $staff = Staff::factory()->create();
        $this->actingAs($staff, 'staff');
        $this->withSession(['staff_password_stamp' => hash('sha256', $staff->password)]);

        return $staff;
    }

    private function order(string $method = 'cash'): Order
    {
        Http::preventStrayRequests();
        config(['payments.enabled' => true, 'payments.secret_key' => 'sk_test_fake']);
        $user = User::create(['name' => 'Test customer', 'phone' => '+966500001234']);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['branch_id' => 1, 'variant_id' => 2, 'quantity' => 2])->assertCreated();
        $response = $this->postJson('/api/orders', ['delivery_type' => 'pickup', 'payment_method' => $method], ['Idempotency-Key' => 'admin-order'])->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    public function test_customer_and_guest_cannot_enter_admin_and_staff_cannot_change_catalog(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::create(['name' => 'Customer', 'phone' => '+966500001111']), 'web')->get('/admin/products')->assertRedirect('/admin/login');
        $this->actingAs(Staff::factory()->create(['role' => 'staff']), 'staff');
        $this->get('/admin/kitchen')->assertOk();
        foreach (['/admin/products', '/admin/categories', '/admin/team', '/admin/coupons', '/admin/delivery'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/products/save/1', ['name_ar' => 'hacked'])->assertForbidden();
        $this->assertDatabaseMissing('products', ['name_ar' => 'hacked']);
    }

    public function test_manager_pages_render_and_escape_catalog_names(): void
    {
        $this->manager();
        Product::findOrFail(1)->update(['name_ar' => '<script>alert(1)</script>']);
        foreach (['/admin', '/admin/orders', '/admin/kitchen', '/admin/products', '/admin/products/create', '/admin/products/1', '/admin/categories', '/admin/coupons', '/admin/delivery', '/admin/team', '/admin/account'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->get('/admin/products/1')->assertSee('&lt;script&gt;', false);
    }

    public function test_login_throttle_and_inactive_staff_are_enforced(): void
    {
        $staff = Staff::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $staff->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/admin/login', ['email' => $staff->email, 'password' => 'TestPassword1234'])->assertSessionHasErrors(['email' => 'محاولات كثيرة. حاول بعد دقيقة.']);
        $this->assertGuest('staff');
        $inactive = Staff::factory()->create(['is_active' => false]);
        $this->post('/admin/login', ['email' => $inactive->email, 'password' => 'TestPassword1234'])->assertSessionHasErrors('email');
        $this->actingAs($inactive, 'staff')->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_logout_and_password_change(): void
    {
        $staff = Staff::factory()->create();
        $this->post('/admin/login', ['email' => $staff->email, 'password' => 'TestPassword1234'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($staff, 'staff');
        $this->post('/admin/account/password', ['current_password' => 'bad', 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertSessionHasErrors('current_password');
        $this->post('/admin/account/password', ['current_password' => 'TestPassword1234', 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassword12345', $staff->fresh()->password));
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('staff');
    }

    public function test_password_reset_revokes_existing_session(): void
    {
        $staff = $this->manager();
        $this->get('/admin')->assertOk();
        $staff->update(['password' => 'ChangedPassword123']);
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest('staff');
    }

    public function test_product_variant_and_inventory_updates_reach_customer_api(): void
    {
        $this->manager();
        $data = ['name_ar' => 'منتج جديد', 'name_en' => 'New product', 'sku' => 'ADMIN-NEW', 'category_id' => 1, 'tax_rate' => 15, 'is_active' => 0, 'is_featured' => 1];
        $this->post('/admin/products/save', $data)->assertSessionHasNoErrors();
        $product = Product::where('sku', 'ADMIN-NEW')->firstOrFail();
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->post('/admin/products/'.$product->id.'/variants', ['name_ar' => '500 جم', 'name_en' => '500 g', 'sku' => 'ADMIN-NEW-500', 'price' => 18.50, 'is_active' => 1])->assertSessionHasNoErrors();
        $variant = $product->variants()->firstOrFail();
        $this->post('/admin/products/'.$product->id.'/stock', ['variant_id' => $variant->id, 'branch_id' => 1, 'stock_quantity' => 20, 'is_available' => 1, 'previous' => ''])->assertSessionHasNoErrors();
        $this->post('/admin/products/save/'.$product->id, [...$data, 'is_active' => 1, 'foodics_product_id' => 'forged'])->assertSessionHasNoErrors();
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('data.variants.0.price', '18.50')->assertJsonPath('data.foodics_product_id', null);
        $this->post('/admin/products/'.$product->id.'/stock', ['variant_id' => $variant->id, 'branch_id' => 1, 'stock_quantity' => 90, 'is_available' => 1, 'previous' => '19'])->assertSessionHasErrors('stock_quantity');
        $this->assertDatabaseHas('branch_product_variants', ['product_variant_id' => $variant->id, 'stock_quantity' => 20]);
        $this->post('/admin/products/'.$product->id.'/variants', ['id' => 2, 'name_ar' => 'x', 'name_en' => 'x', 'sku' => 'wrong', 'price' => 1, 'is_active' => 1])->assertSessionHasErrors('id');
    }

    public function test_failed_catalog_upload_preserves_existing_product(): void
    {
        $this->manager();
        config(['filesystems.catalog_disk' => 'product_images']);
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('product_images')->andReturn($disk);
        $product = Product::findOrFail(1);
        $data = $product->only('name_ar', 'name_en', 'sku', 'category_id', 'tax_rate', 'is_active', 'is_featured');
        $image = UploadedFile::fake()->createWithContent('good.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII='));
        $this->post('/admin/products/save/1', [...$data, 'name_ar' => 'Changed name', 'image' => $image])->assertSessionHasErrors('image');
        $this->assertSame($product->main_image, $product->fresh()->main_image);
        $this->assertSame($product->name_ar, $product->fresh()->name_ar);
    }

    public function test_catalog_upload_uses_configured_storage(): void
    {
        $this->manager();
        config(['filesystems.catalog_disk' => 'product_images']);
        Storage::fake('product_images');
        $product = Product::findOrFail(1);
        $data = $product->only('name_ar', 'name_en', 'sku', 'category_id', 'tax_rate', 'is_active', 'is_featured');
        $image = UploadedFile::fake()->createWithContent('good.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII='));
        $this->post('/admin/products/save/1', [...$data, 'image' => $image])->assertSessionHasNoErrors();
        $files = Storage::disk('product_images')->files('catalog');
        $this->assertCount(1, $files);
        $this->assertSame(Storage::disk('product_images')->url($files[0]), $product->fresh()->main_image);
    }

    public function test_upload_rejects_executable_and_publishes_valid_image(): void
    {
        $this->manager();
        $p = Product::findOrFail(1);
        $data = $p->only('name_ar', 'name_en', 'sku', 'category_id', 'tax_rate', 'is_active', 'is_featured');
        $this->post('/admin/products/save/1', [...$data, 'image' => UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;')])->assertSessionHasErrors('image');
        $image = UploadedFile::fake()->createWithContent('good.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII='));
        $this->post('/admin/products/save/1', [...$data, 'image' => $image])->assertSessionHasNoErrors();
        $path = $p->fresh()->main_image;
        $this->assertStringStartsWith('/images/uploads/', $path);
        $this->assertFileExists(public_path($path));
        unlink(public_path($path));
    }

    public function test_preparation_requires_legal_transitions_and_cash_receipt_before_handover(): void
    {
        $order = $this->order();
        $this->manager();
        $this->get('/admin/orders/'.$order->id)->assertOk();
        $this->post('/admin/orders/'.$order->id.'/status', ['expected_status' => 'pending', 'status' => 'delivered'])->assertSessionHasErrors('status');
        foreach (['pending' => 'confirmed', 'confirmed' => 'preparing', 'preparing' => 'ready'] as $from => $to) {
            $this->post('/admin/orders/'.$order->id.'/status', ['expected_status' => $from, 'status' => $to])->assertSessionHasNoErrors();
        }
        $this->post('/admin/orders/'.$order->id.'/status', ['expected_status' => 'ready', 'status' => 'delivered'])->assertSessionHasErrors('status');
        $this->post('/admin/orders/'.$order->id.'/collect', ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/orders/'.$order->id.'/collect', ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('admin_events')->where('action', 'cash_collected')->count());
        $this->post('/admin/orders/'.$order->id.'/status', ['expected_status' => 'ready', 'status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
    }

    public function test_cancellation_restores_reserved_stock_once_and_rejects_staff(): void
    {
        $stock = BranchProductVariant::where('product_variant_id', 2)->firstOrFail();
        $stock->update(['stock_quantity' => 10]);
        $order = $this->order();
        $this->assertEquals(8, $stock->fresh()->stock_quantity);
        $this->actingAs(Staff::factory()->create(['role' => 'staff']), 'staff');
        $payload = ['expected_status' => 'pending', 'status' => 'cancelled', 'reason' => 'طلب العميل'];
        $this->post('/admin/orders/'.$order->id.'/status', $payload)->assertSessionHasErrors('status');
        $this->manager();
        $this->post('/admin/orders/'.$order->id.'/status', $payload)->assertSessionHasNoErrors();
        $this->assertEquals(10, $stock->fresh()->stock_quantity);
        $this->post('/admin/orders/'.$order->id.'/status', $payload)->assertSessionHasErrors('status');
        $this->assertEquals(10, $stock->fresh()->stock_quantity);
        $this->assertDatabaseCount('admin_events', 1);
    }

    public function test_online_payment_cannot_be_collected_or_prepared_when_unpaid(): void
    {
        $order = $this->order('moyasar');
        $this->manager();
        $this->post('/admin/orders/'.$order->id.'/status', ['expected_status' => 'pending', 'status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->post('/admin/orders/'.$order->id.'/collect', ['confirmed' => 1])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'payment_status' => 'pending']);
    }

    public function test_coupon_delivery_and_category_validation_and_persistence(): void
    {
        $this->manager();
        $coupon = ['code' => 'new20', 'discount_type' => 'percentage', 'discount_value' => 120, 'minimum_order' => 0, 'usage_per_customer' => 1, 'first_order_only' => 0, 'is_active' => 1];
        $this->post('/admin/coupons', $coupon)->assertSessionHasErrors('discount_value');
        $this->post('/admin/coupons', [...$coupon, 'discount_value' => 20, 'expires_at' => now()->addDay()->toDateTimeString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'NEW20', 'discount_value' => 20]);
        $this->post('/admin/delivery/zones', ['branch_id' => 1, 'name' => 'منطقة جديدة', 'delivery_fee' => 10, 'minimum_order' => 30, 'estimated_minutes' => 45, 'districts_text' => "الربيع\nالنرجس", 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['الربيع', 'النرجس'], DeliveryZone::where('name', 'منطقة جديدة')->firstOrFail()->districts);
        $this->post('/admin/delivery/slots', ['branch_id' => 1, 'day_of_week' => 0, 'start_time' => '22:00', 'end_time' => '21:00', 'max_orders' => 10, 'is_active' => 1])->assertSessionHasErrors('end_time');
        $this->post('/admin/delivery/slots', ['branch_id' => 1, 'day_of_week' => 0, 'start_time' => '22:00', 'end_time' => '23:00', 'max_orders' => 10, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/categories/1', ['name_ar' => 'الفواكه', 'name_en' => 'Fruits', 'sort_order' => 1, 'is_active' => 0])->assertSessionHasNoErrors();
        $this->getJson('/api/products/1')->assertNotFound();
    }

    public function test_manager_cannot_disable_self_and_can_provision_staff(): void
    {
        $manager = $this->manager();
        $this->post('/admin/team/'.$manager->id, ['name' => $manager->name, 'email' => $manager->email, 'role' => 'staff', 'is_active' => 1])->assertSessionHasErrors('role');
        $this->post('/admin/team', ['name' => 'موظف التجهيز', 'email' => 'staff@example.test', 'role' => 'staff', 'is_active' => 1, 'password' => 'StaffPassword123', 'password_confirmation' => 'StaffPassword123'])->assertSessionHasNoErrors();
        $staff = Staff::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StaffPassword123', $staff->password));
        $this->assertSame('staff', $staff->role);
    }
}
