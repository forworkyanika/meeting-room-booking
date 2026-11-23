<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_register_log_in_and_log_out(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Nok', 'email' => 'nok@example.com',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
        ])->assertCreated()->assertJsonPath('user.role', 'user');

        $token = $this->postJson('/api/login', ['email' => 'nok@example.com', 'password' => 'secret-pass'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'nok@example.com');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($token), 'logout should revoke the token it was called with');
    }

    #[Test]
    public function wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'nok@example.com']);

        $this->postJson('/api/login', ['email' => 'nok@example.com', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function the_api_requires_a_token(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    #[Test]
    public function an_api_call_without_json_accept_header_still_gets_a_401(): void
    {
        $this->get('/api/bookings')->assertUnauthorized();
    }
}
