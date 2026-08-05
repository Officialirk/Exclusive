<?php

namespace Exclusive\Mods;

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\ConsoleWidgetPosition;
use App\Filament\Server\Pages\Console;
use App\Traits\EnvironmentWriterTrait;
use Exclusive\Mods\Filament\Pages\Mods;
use Exclusive\Mods\Filament\Widgets\PlayersOnline;
use Exclusive\Mods\Services\Drivers\PalworldUe4ssDriver;
use Exclusive\Mods\Services\ModManagerDriverRegistry;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Panel;

class ExclusiveModsPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'exclusive-mods';
    }

    public function register(Panel $panel): void
    {
        app()->singleton(ModManagerDriverRegistry::class, function () {
            $registry = new ModManagerDriverRegistry();
            $registry->register(app(PalworldUe4ssDriver::class));

            return $registry;
        });

        if ($panel->getId() === 'server') {
            $panel->pages([Mods::class]);
        }
    }

    public function boot(Panel $panel): void
    {
        if ($panel->getId() === 'server') {
            Console::registerCustomWidgets(ConsoleWidgetPosition::AboveConsole, [PlayersOnline::class]);
        }
    }

    /** @return array<string, mixed> */
    public function getSettingsFormData(): array
    {
        return [
            'steam_api_key' => config('exclusive-mods.steam_api_key'),
        ];
    }

    /** @return \Filament\Schemas\Components\Component[] */
    public function getSettingsForm(): array
    {
        return [
            TextInput::make('steam_api_key')
                ->label('Steam Web API Key')
                ->password()
                ->revealable()
                ->helperText('Only required to enable Workshop search. Pasting a direct Workshop link or item ID works without a key.'),
        ];
    }

    /** @param array<mixed, mixed> $data */
    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment(['EXCLUSIVE_MODS_STEAM_API_KEY' => $data['steam_api_key'] ?? '']);

        config()->set('exclusive-mods.steam_api_key', $data['steam_api_key'] ?? null);
    }
}
