<?php

namespace Exclusive\Theme;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class ExclusiveThemePlugin implements Plugin
{
    public function getId(): string
    {
        return 'exclusive-theme';
    }

    public function register(Panel $panel): void
    {
        $panel->font('Inter');

        FilamentColor::register([
            'primary' => Color::hex('#E8B84B'),
            'danger' => Color::hex('#D0483E'),
            'success' => Color::hex('#5FD97A'),
            'info' => Color::hex('#5FB4D9'),
        ]);
    }

    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_BEFORE,
            fn () => Blade::render("@vite(['plugins/exclusive-theme/resources/css/theme.css'])"),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn () => Blade::render("@vite(['plugins/exclusive-theme/resources/js/theme.js'])"),
        );
    }
}
