<?php

namespace Exclusive\Mods\Services;

use App\Models\Server;
use Exclusive\Mods\Contracts\ModManagerDriverInterface;
use Exclusive\Mods\Models\EggDriver;

class ModManagerDriverRegistry
{
    /** @var array<string, ModManagerDriverInterface> */
    private array $drivers = [];

    public function register(ModManagerDriverInterface $driver): void
    {
        $this->drivers[$driver->slug()] = $driver;
    }

    public function get(?string $slug): ?ModManagerDriverInterface
    {
        return $slug ? $this->drivers[$slug] ?? null : null;
    }

    public function isSupported(?string $slug): bool
    {
        return $this->get($slug) !== null;
    }

    /** @return ModManagerDriverInterface[] */
    public function all(): array
    {
        return $this->drivers;
    }

    public function driverSlugForServer(Server $server): ?string
    {
        return EggDriver::query()->where('egg_id', $server->egg_id)->value('driver');
    }

    public function driverForServer(Server $server): ?ModManagerDriverInterface
    {
        return $this->get($this->driverSlugForServer($server));
    }
}
