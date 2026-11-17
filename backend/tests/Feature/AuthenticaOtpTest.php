<?php

namespace Tests\Feature;

use App\Models\OtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticaOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.otp_driver' => 'authentica', 'services.authentica.api_key' => 'fake-secret']);
        Http::preventStrayRequests();
    }

    private function sentChallenge(): void
    {
        Http::fake(['*/send-otp' => Http::response(['success' => true])]);
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500001234'])
            ->assertStatus(202)->assertJsonMissingPath('development_code');
    }

    public function test_send_uses_sms_and_secret_header_without_local_otp(): void
    {
        $this->sentChallenge();
        Http::assertSent(fn ($r) => $r->url() === 'https://api.authentica.sa/api/v2/send-otp'
            && $r->hasHeader('X-Authorization', 'fake-secret') && $r['phone'] === '+966500001234'
            && $r['method'] === 'sms' && ! isset($r['otp']));
        $this->assertDatabaseHas('otp_challenges', ['phone' => '+966500001234', 'provider' => 'authentica']);
    }

    public function test_remote_verification_issues_token_once(): void
    {
        $this->sentChallenge();
        Http::fake(['*/verify-otp' => Http::response(['status' => true])]);
        $input = ['phone' => '+966500001234', 'code' => '123456'];
        $this->postJson('/api/auth/verify-otp', $input)->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/auth/verify-otp', $input)->assertStatus(422);
        Http::assertSent(fn ($r) => $r['otp'] === '123456' && $r['phone'] === $input['phone']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_wrong_code_does_not_authenticate_and_counts_attempt(): void
    {
        $this->sentChallenge();
        Http::fake(['*/verify-otp' => Http::response(['status' => false])]);
        $this->postJson('/api/auth/verify-otp', ['phone' => '+966500001234', 'code' => '123456'])->assertStatus(422);
        $this->assertDatabaseHas('otp_challenges', ['attempts' => 1, 'consumed_at' => null]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_failed_send_creates_no_challenge_and_does_not_leak_secret(): void
    {
        Http::fake(['*/send-otp' => Http::response(['success' => false, 'message' => 'fake-secret'])]);
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500001234'])->assertStatus(503)->assertDontSee('fake-secret');
        $this->assertDatabaseCount('otp_challenges', 0);
        Http::assertSentCount(1);
    }

    public function test_missing_key_sends_nothing(): void
    {
        config(['services.authentica.api_key' => null]);
        Http::fake();
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500001234'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_timeout_does_not_retry_or_create_challenge(): void
    {
        Http::fake(['*/send-otp' => Http::failedConnection()]);
        $this->postJson('/api/auth/send-otp', ['phone' => '+966500001234'])->assertStatus(503);
        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_expired_and_other_provider_challenges_cannot_authenticate(): void
    {
        $this->sentChallenge();
        OtpChallenge::query()->update(['provider' => 'msegat']);
        Http::fake();
        $input = ['phone' => '+966500001234', 'code' => '123456'];
        $this->postJson('/api/auth/verify-otp', $input)->assertStatus(422);
        OtpChallenge::query()->update(['provider' => 'authentica', 'expires_at' => now()->subMinute()]);
        $this->postJson('/api/auth/verify-otp', $input)->assertStatus(422);
        Http::assertNothingSent();
    }
}
