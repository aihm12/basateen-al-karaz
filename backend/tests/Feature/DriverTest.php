<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function login(Staff $staff): void
    {
        $this->actingAs($staff, 'staff')->withSession(['staff_password_stamp' => hash('sha256', $staff->password)]);
    }

    private function order(): Order
    {
        Http::preventStrayRequests();
        $user = User::create(['name' => 'Delivery customer', 'phone' => '+966500002345']);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['branch_id' => 1, 'variant_id' => 2, 'quantity' => 1])->assertCreated();
        $response = $this->postJson('/api/orders', ['delivery_type' => 'pickup', 'payment_method' => 'cash'], ['Idempotency-Key' => 'driver-order'])->assertCreated();
        $order = Order::findOrFail($response->json('data.id'));
        $order->update(['delivery_type' => 'delivery', 'status' => 'ready', 'address_snapshot' => ['maps_url' => 'https://maps.app.goo.gl/example', 'district' => 'الربيع', 'latitude' => 24.7, 'longitude' => 46.7]]);
        $order->delivery()->firstOrCreate([]);

        return $order;
    }

    private function assign(Order $order, Staff $driver): void
    {
        $this->login(Staff::factory()->create());
        $this->post('/admin/orders/'.$order->id.'/assign', ['driver_staff_id' => $driver->id, 'expected_status' => 'ready', 'expected_driver' => ''])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_driver_can_only_access_own_orders_and_no_management_pages(): void
    {
        $order = $this->order();
        $owner = Staff::factory()->create(['role' => 'driver']);
        $other = Staff::factory()->create(['role' => 'driver']);
        $this->assign($order, $owner);
        $this->login($other);
        $this->get('/admin')->assertRedirect('/admin/driver');
        $this->get('/admin/orders')->assertForbidden()->assertSee('أنت الآن داخل حساب المندوب')->assertSee('تسجيل الخروج للدخول بحساب المدير');
        $this->get('/admin/driver')->assertOk()->assertDontSee($order->order_number);
        foreach (['/admin/orders', '/admin/kitchen', '/admin/products', '/admin/team', '/admin/orders/'.$order->id, '/admin/driver/orders/'.$order->id] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/admin/driver/orders/'.$order->id.'/status', ['expected_status' => 'assigned', 'status' => 'out_for_delivery'])->assertForbidden();
        $this->post('/admin/driver/orders/'.$order->id.'/collect', ['confirmed' => 1])->assertForbidden();
        $this->post('/admin/orders/'.$order->id.'/assign', ['driver_staff_id' => $other->id])->assertForbidden();
        $this->login($owner);
        $this->get('/admin/driver')->assertOk()->assertSee($order->order_number);
        $this->get('/admin/driver/orders/'.$order->id)->assertOk()->assertSee('www.google.com/maps', false)->assertSee('https://maps.app.goo.gl/example', false);
    }

    public function test_driver_workflow_requires_cash_collection_and_records_delivery(): void
    {
        $order = $this->order();
        $driver = Staff::factory()->create(['role' => 'driver']);
        $this->assign($order, $driver);
        $this->login($driver);
        $url = '/admin/driver/orders/'.$order->id;
        $this->post($url.'/collect', ['confirmed' => 1])->assertForbidden();
        $this->post($url.'/status', ['expected_status' => 'assigned', 'status' => 'delivered'])->assertSessionHasErrors('status');
        $this->post($url.'/status', ['expected_status' => 'assigned', 'status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->post($url.'/status', ['expected_status' => 'assigned', 'status' => 'out_for_delivery'])->assertSessionHasErrors('status');
        $this->post($url.'/status', ['expected_status' => 'out_for_delivery', 'status' => 'delivered'])->assertSessionHasErrors('status');
        $this->post($url.'/collect', [])->assertSessionHasErrors('confirmed');
        $this->post($url.'/collect', ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->post($url.'/collect', ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->post($url.'/status', ['expected_status' => 'out_for_delivery', 'status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'paid']);
        $this->assertNotNull($order->delivery->fresh()->picked_up_at);
        $this->assertNotNull($order->delivery->fresh()->delivered_at);
        $this->assertDatabaseCount('admin_events', 4);
    }

    public function test_assignment_validates_role_payment_and_stale_assignment(): void
    {
        $order = $this->order();
        $driver = Staff::factory()->create(['role' => 'driver']);
        $manager = Staff::factory()->create();
        $this->login($manager);
        $url = '/admin/orders/'.$order->id.'/assign';
        $data = ['expected_status' => 'ready', 'driver_staff_id' => $manager->id];
        $this->post($url, $data)->assertSessionHasErrors('driver_staff_id');
        $driver->update(['is_active' => false]);
        $data['driver_staff_id'] = $driver->id;
        $this->post($url, $data)->assertSessionHasErrors('driver_staff_id');
        $driver->update(['is_active' => true]);
        $order->update(['payment_method' => 'moyasar']);
        $this->post($url, $data)->assertSessionHasErrors('driver_staff_id');
        $order->update(['payment_method' => 'cash']);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $second = Staff::factory()->create(['role' => 'driver']);
        $this->post($url, [...$data, 'driver_staff_id' => $second->id])->assertSessionHasErrors('driver_staff_id');
        $this->post($url, ['driver_staff_id' => $second->id, 'expected_status' => 'assigned', 'expected_driver' => $driver->id])->assertSessionHasNoErrors();
        $this->login($driver);
        $this->get('/admin/driver/orders/'.$order->id)->assertForbidden();
        $this->login($second);
        $this->post('/admin/driver/orders/'.$order->id.'/status', ['expected_status' => 'assigned', 'status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->login($manager);
        $this->post($url, ['driver_staff_id' => $driver->id, 'expected_status' => 'assigned', 'expected_driver' => $second->id])->assertSessionHasErrors('driver_staff_id');
    }

    public function test_manager_can_create_driver_and_driver_login_redirects_to_deliveries(): void
    {
        $this->login(Staff::factory()->create());
        $this->post('/admin/team', ['name' => 'مندوب', 'email' => 'driver@example.test', 'role' => 'driver', 'is_active' => 1, 'password' => 'DriverPassword123', 'password_confirmation' => 'DriverPassword123'])->assertSessionHasNoErrors();
        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'driver@example.test', 'password' => 'DriverPassword123'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/driver');
        $this->get('/admin/orders')->assertForbidden()->assertSee('أنت الآن داخل حساب المندوب')->assertSee('تسجيل الخروج للدخول بحساب المدير');
        $this->get('/admin/driver')->assertOk();
        $this->get('/admin/account')->assertOk();
        Staff::where('email', 'driver@example.test')->update(['is_active' => false]);
        $this->login(Staff::where('email', 'driver@example.test')->firstOrFail());
        $this->get('/admin/driver')->assertRedirect('/admin/login');
    }
}
