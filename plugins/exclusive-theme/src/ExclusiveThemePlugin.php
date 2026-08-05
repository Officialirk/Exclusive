<?php

namespace Exclusive\Theme;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;

/**
 * Rebuilt to match the pattern used by Pelican's own reference theme plugin
 * (pelican-dev/plugins/pterodactyl-theme): colors()/font() only, no custom
 * CSS/JS assets and no render hooks. That reference plugin ships with zero
 * files under resources/ and an empty boot() - theming is done entirely
 * through Filament's built-in panel-level customization API, which needs no
 * Vite build step at all. The previous version of this plugin shipped a
 * custom theme.css/theme.js wired through a render hook + @vite() call,
 * which requires `yarn build` to have produced a manifest entry for it -
 * on a server where that build never ran (or failed), the render hook
 * throws "Unable to locate file in Vite manifest" on every single page.
 * This version can't break that way, at the cost of less visual fidelity
 * (no gauge bars, no console retint, no bespoke sidebar layout) than the
 * original mockup - see README.md for the trade-off.
 */
class ExclusiveThemePlugin implements Plugin
{
    // A single hex seed color; Color::hex() derives the hue from it and
    // generates a full 50-950 lightness ramp - Filament uses the dark end
    // (900/950) for dark-mode backgrounds and the light end (50/100) for
    // light-mode backgrounds, so one warm near-black seed is enough to get
    // a void/gold-tinted gray scale in both modes.
    public const VOID_GRAY_SEED = '#15110a';

    public function getId(): string
    {
        return 'exclusive-theme';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->font(
                'Inter',
                url: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            )
            ->monoFont(
                'IBM Plex Mono',
                url: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&display=swap',
            )
            ->serifFont(
                'Playfair Display',
                url: 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&display=swap',
            )
            ->colors([
                'gray' => Color::hex(self::VOID_GRAY_SEED),
                'primary' => Color::hex('#E8B84B'),
                'danger' => Color::hex('#D0483E'),
                'success' => Color::hex('#5FD97A'),
                'info' => Color::hex('#5FB4D9'),
            ]);
    }

    public function boot(Panel $panel): void {}
}
