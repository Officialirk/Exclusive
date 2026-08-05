<?php

namespace Exclusive\Theme;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;

/**
 * Matches the pattern used by Pelican's own reference theme plugin
 * (pelican-dev/plugins/pterodactyl-theme): colors()/font() only, no custom
 * CSS/JS assets and no render hooks. That reference plugin ships with zero
 * files under resources/ and an empty boot() - theming is done entirely
 * through Filament's built-in panel-level customization API, which needs no
 * Vite build step at all (an earlier version of this plugin shipped a
 * custom theme.css/theme.js wired through a render hook + @vite() call,
 * which broke with "Unable to locate file in Vite manifest" on a server
 * where the asset build never ran - see git history / README for that).
 *
 * Palette/fonts here match the "Pelican — Server Control" mockup: cool
 * navy-black surfaces, amber accent, IBM Plex Mono + Inter only (no serif).
 */
class ExclusiveThemePlugin implements Plugin
{
    // A single hex seed color; Color::hex() derives the hue from it and
    // generates a full 50-950 lightness ramp - Filament uses the dark end
    // (900/950) for dark-mode backgrounds and the light end (50/100) for
    // light-mode backgrounds, so one seed matching the mockup's --panel
    // value is enough to get a matching navy-black gray scale in both modes.
    public const PANEL_GRAY_SEED = '#12161F';

    public function getId(): string
    {
        return 'exclusive-theme';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->font(
                'Inter',
                url: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            )
            ->monoFont(
                'IBM Plex Mono',
                url: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&display=swap',
            )
            ->colors([
                'gray' => Color::hex(self::PANEL_GRAY_SEED),
                'primary' => Color::hex('#FFB454'),
                'danger' => Color::hex('#FF5C5C'),
                'success' => Color::hex('#5FD97A'),
                'info' => Color::hex('#5FB4D9'),
            ]);
    }

    public function boot(Panel $panel): void {}
}
