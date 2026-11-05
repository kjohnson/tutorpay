<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'pay period' => ['get', '/'],
            'schedule' => ['get', '/schedule'],
            'settings' => ['get', '/settings'],
            'add schedule block' => ['post', '/schedule'],
            'add day block' => ['post', '/days/2026-11-11/blocks'],
            'update settings' => ['put', '/settings'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect(route('login'));
    }

    public function test_login_page_renders_without_app_navigation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Log in')
            ->assertDontSee('Calendar');
    }

    public function test_users_can_log_in_and_are_sent_to_the_page_they_wanted(): void
    {
        $user = User::factory()->create();

        $this->get(route('settings.edit'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('settings.edit'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_log_in_with_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_too_many_failed_attempts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);

        $this->assertGuest();
    }

    public function test_logged_in_users_are_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect();
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_command_creates_a_login(): void
    {
        $this->artisan('user:create', ['email' => 'Tutor@Example.com', '--name' => 'Tutor'])
            ->expectsQuestion('Password (at least 8 characters)', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->expectsOutput('Login created for tutor@example.com.')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame('tutor@example.com', $user->email);
        $this->assertSame('Tutor', $user->name);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_command_resets_the_password_of_an_existing_login(): void
    {
        $user = User::factory()->create(['email' => 'tutor@example.com']);

        $this->artisan('user:create', ['email' => 'tutor@example.com'])
            ->expectsConfirmation('A login for tutor@example.com already exists. Set a new password for it?', 'yes')
            ->expectsQuestion('Password (at least 8 characters)', 'another-password')
            ->expectsQuestion('Confirm password', 'another-password')
            ->expectsOutput('Password updated for tutor@example.com.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('another-password', $user->fresh()->password));
    }

    public function test_command_rejects_short_or_mismatched_passwords(): void
    {
        $this->artisan('user:create', ['email' => 'tutor@example.com'])
            ->expectsQuestion('Password (at least 8 characters)', 'short')
            ->assertFailed();

        $this->artisan('user:create', ['email' => 'tutor@example.com'])
            ->expectsQuestion('Password (at least 8 characters)', 'secret-password')
            ->expectsQuestion('Confirm password', 'different-password')
            ->expectsOutput('The passwords do not match.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
