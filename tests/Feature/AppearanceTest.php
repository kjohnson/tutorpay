<?php

namespace Tests\Feature;

use App\Models\User;
use App\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_follows_the_system_theme_without_a_cookie(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('calendar.show'))
            ->assertOk()
            ->assertDontSee('data-theme=', false);
    }

    public function test_settings_page_shows_the_theme_options_with_the_current_one_selected(): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie(Theme::COOKIE, 'dark')
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSeeInOrder(['System default', 'Light', 'Dark'])
            ->assertSee('value="dark" checked', false)
            ->assertDontSee('value="system" checked', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function explicitThemes(): array
    {
        return [
            'dark' => ['dark'],
            'light' => ['light'],
        ];
    }

    #[DataProvider('explicitThemes')]
    public function test_choosing_a_theme_saves_it_in_a_cookie(string $theme): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('settings.appearance.update'), ['theme' => $theme])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status', 'Appearance saved for this browser.')
            ->assertCookie(Theme::COOKIE, $theme);
    }

    #[DataProvider('explicitThemes')]
    public function test_pages_use_the_theme_from_the_cookie(string $theme): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie(Theme::COOKIE, $theme)
            ->get(route('calendar.show'))
            ->assertSee('data-theme="'.$theme.'"', false);
    }

    public function test_choosing_the_system_default_removes_the_cookie(): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie(Theme::COOKIE, 'dark')
            ->put(route('settings.appearance.update'), ['theme' => 'system'])
            ->assertRedirect(route('settings.edit'))
            ->assertCookieExpired(Theme::COOKIE);
    }

    public function test_rejects_unknown_themes(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('settings.appearance.update'), ['theme' => 'sepia'])
            ->assertSessionHasErrors('theme')
            ->assertCookieMissing(Theme::COOKIE);
    }

    public function test_ignores_an_invalid_cookie_value(): void
    {
        $this->withCookie(Theme::COOKIE, 'sepia')
            ->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-theme=', false);
    }

    public function test_login_page_uses_the_theme_from_the_cookie(): void
    {
        $this->withCookie(Theme::COOKIE, 'dark')
            ->get(route('login'))
            ->assertSee('data-theme="dark"', false);
    }
}
