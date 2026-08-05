<?php

namespace Exclusive\Mods\Filament\Widgets;

use App\Models\Server;
use Exclusive\Mods\Services\ServerQuery\PalworldRestQueryService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class PlayersOnline extends Widget
{
    protected string $view = 'exclusive-mods::filament.widgets.players-online';

    protected ?string $pollingInterval = '10s';

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return app(PalworldRestQueryService::class)->isSupported($server);
    }

    /** @return array<int, array{name: string, ping: int|null}> */
    public function getPlayers(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return app(PalworldRestQueryService::class)->getPlayers($server);
    }
}
