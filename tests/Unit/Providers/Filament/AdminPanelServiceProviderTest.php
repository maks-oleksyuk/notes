<?php
/**
 * Tests for App\Providers\Filament\AdminPanelServiceProvider
 *
 * Framework: PHPUnit (Laravel Tests\TestCase) with Mockery for fluent API expectations.
 * Focus: Validates configuration applied within the panel() method.
 */

namespace Tests\Unit\Providers\Filament;

use App\Providers\Filament\AdminPanelServiceProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Mockery as m;
use Mockery\MockInterface;
use Tests\TestCase;

final class AdminPanelServiceProviderConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        // Ensure Mockery expectations are verified and cleaned between tests
        m::close();
        parent::tearDown();
    }

    /**
     * Define the expected fluent configuration calls against Filament\Panel.
     *
     * @param MockInterface $panel
     * @param int $times Number of times the configuration is expected to be applied.
     */
    private function expectDefaultPanelConfig(MockInterface $panel, int $times = 1): void
    {
        // Basic panel identity and access
        $panel->shouldReceive('default')->times($times)->andReturnSelf();
        $panel->shouldReceive('id')->times($times)->with('admin')->andReturnSelf();
        $panel->shouldReceive('path')->times($times)->with('admin')->andReturnSelf();
        $panel->shouldReceive('login')->times($times)->andReturnSelf();

        // Theming and layout
        $panel->shouldReceive('colors')->times($times)->with(m::on(function ($colors) {
            return is_array($colors) && array_key_exists('primary', $colors) && $colors['primary'] === Color::Green;
        }))->andReturnSelf();

        // We accept any URL/string that contains 'favicon.ico' to avoid environment-specific URL coupling
        $panel->shouldReceive('favicon')->times($times)->with(m::on(function ($url) {
            return is_string($url) && str_contains($url, 'favicon.ico');
        }))->andReturnSelf();

        $panel->shouldReceive('sidebarCollapsibleOnDesktop')->times($times)->andReturnSelf();
        $panel->shouldReceive('databaseNotifications')->times($times)->andReturnSelf();
        $panel->shouldReceive('maxContentWidth')->times($times)->with(Width::Full)->andReturnSelf();

        // Resource, page, and widget discovery + registration
        $panel->shouldReceive('discoverResources')->times($times)
            ->with(app_path('Filament/Resources'), 'App\\Filament\\Resources')->andReturnSelf();

        $panel->shouldReceive('discoverPages')->times($times)
            ->with(app_path('Filament/Pages'), 'App\\Filament\\Pages')->andReturnSelf();

        $panel->shouldReceive('pages')->times($times)->with(m::on(function ($pages) {
            return is_array($pages) && $pages === [Pages\Dashboard::class];
        }))->andReturnSelf();

        $panel->shouldReceive('discoverWidgets')->times($times)
            ->with(app_path('Filament/Widgets'), 'App\\Filament\\Widgets')->andReturnSelf();

        $panel->shouldReceive('widgets')->times($times)->with(m::on(function ($widgets) {
            $expected = [
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ];
            return is_array($widgets) && array_values($widgets) === $expected;
        }))->andReturnSelf();

        // HTTP middleware stacks
        $panel->shouldReceive('middleware')->times($times)->with([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ])->andReturnSelf();

        $panel->shouldReceive('authMiddleware')->times($times)->with([
            Authenticate::class,
        ])->andReturnSelf();
    }

    public function test_panel_configures_admin_panel_with_expected_settings(): void
    {
        $panel = m::mock(Panel::class);
        $this->expectDefaultPanelConfig($panel, 1);

        $provider = new AdminPanelServiceProvider(app());

        $result = $provider->panel($panel);

        // The provider should return the same Panel instance (fluent configuration)
        $this->assertSame($panel, $result, 'panel() should return the provided Panel instance for chaining.');
    }

    public function test_panel_configuration_is_idempotent_across_repeated_invocations(): void
    {
        $panel = m::mock(Panel::class);
        $this->expectDefaultPanelConfig($panel, 2);

        $provider = new AdminPanelServiceProvider(app());

        // Apply configuration twice to ensure no side-effects or exceptions
        $provider->panel($panel);
        $provider->panel($panel);

        // Mockery verifies call counts; we assert true to make intent explicit
        $this->assertTrue(true, 'Invoking panel() twice should re-apply configuration without failure.');
    }

    public function test_provider_is_final_and_extends_filament_panelprovider(): void
    {
        $ref = new \ReflectionClass(AdminPanelServiceProvider::class);

        $this->assertTrue($ref->isFinal(), 'AdminPanelServiceProvider should be declared final.');
        $this->assertTrue(is_subclass_of(AdminPanelServiceProvider::class, PanelProvider::class), 'Provider must extend Filament\\PanelProvider.');
        $this->assertTrue($ref->hasMethod('panel'), 'panel() method must be defined.');

        $method = $ref->getMethod('panel');
        $returnType = $method->getReturnType();

        // Verify declared return type is Filament\Panel
        $this->assertNotNull($returnType, 'panel() must declare a return type.');
        $this->assertSame(Panel::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null);
    }
}