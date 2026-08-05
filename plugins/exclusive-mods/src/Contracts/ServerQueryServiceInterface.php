<?php

namespace Exclusive\Mods\Contracts;

use App\Models\Server;

interface ServerQueryServiceInterface
{
    /** @return array<int, array{name: string, ping: int|null}> */
    public function getPlayers(Server $server): array;

    public function isSupported(Server $server): bool;
}
