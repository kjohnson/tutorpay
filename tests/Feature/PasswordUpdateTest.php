<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_shows_the_change_password_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee(route('settings.password.update'));
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create();
        $originalRememberToken = $user->remember_token;

        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status', 'Password changed.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-password', $user->password));
        $this->assertNotSame($originalRememberToken, $user->remember_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.edit'))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'Your current password is incorrect.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_differ_from_the_current_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'The new password must be different from your current password.']);
    }

    public function test_password_errors_do_not_appear_in_the_settings_form_errors(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('settings.edit'))
            ->put(route('settings.password.update'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(session('errors')->getBag('default')->isEmpty());
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $this->put(route('settings.password.update'), [])->assertRedirect(route('login'));
    }
}
