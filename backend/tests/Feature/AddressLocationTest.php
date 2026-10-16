<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressLocationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_google_map_links_are_saved_and_unrelated_or_unsafe_links_rejected(): void
    {
        Sanctum::actingAs(User::create(['name' => 'Maps customer', 'phone' => '+966500004444']));
        $data = ['label' => 'Home', 'district' => 'حي تجريبي', 'street' => 'Street', 'delivery_zone_id' => 1];
        foreach (['https://maps.app.goo.gl/example', 'https://goo.gl/maps/example', 'https://www.google.com/maps?q=24.7,46.7'] as $url) {
            $this->postJson('/api/addresses', [...$data, 'maps_url' => $url])->assertCreated()->assertJsonPath('data.maps_url', $url);
        }
        foreach (['javascript:alert(1)', 'https://maps.app.goo.gl.evil.test/x', 'https://www.google.com/url?q=https://evil.test', 'https://evil.test/maps', 'https://user@maps.app.goo.gl/x', 'http://maps.app.goo.gl/x'] as $url) {
            $this->postJson('/api/addresses', [...$data, 'maps_url' => $url])->assertUnprocessable()->assertJsonValidationErrors('maps_url');
        }
        $this->assertDatabaseCount('addresses', 3);
    }
}
