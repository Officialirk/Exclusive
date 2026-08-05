<?php

namespace Officialirk\PalworldMods;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PalworldModsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'palworld-mods';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "Officialirk\\PalworldMods\\Filament\\$id\\Pages");
    }

    public function boot(Panel $panel): void {}
}
