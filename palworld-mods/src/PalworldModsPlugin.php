<?php

namespace Officialirk\PalworldMods;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;

class PalworldModsPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

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

    /** @return array<string, mixed> */
    public function getSettingsFormData(): array
    {
        return config('palworld-mods');
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public function getSettingsForm(): array
    {
        return [
            TextInput::make('steam_api_key')
                ->label('Steam Web API key')
                ->password()
                ->revealable()
                ->helperText('Optional — only needed for the in-panel Steam Workshop search tab. Get a free key at steamcommunity.com/dev/apikey. Looking up/installing a specific Workshop item by URL works without one.')
                ->default(fn () => config('palworld-mods.steam_api_key')),
        ];
    }

    /** @param array<mixed, mixed> $data */
    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'PALWORLD_MODS_STEAM_API_KEY' => $data['steam_api_key'] ?? '',
        ]);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
