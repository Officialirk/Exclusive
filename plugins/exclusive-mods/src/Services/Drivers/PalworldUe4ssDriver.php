<?php

namespace Exclusive\Mods\Services\Drivers;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Exclusive\Mods\Contracts\ModManagerDriverInterface;
use Exclusive\Mods\Models\Mod;
use Exclusive\Mods\Services\SteamWorkshopService;
use RuntimeException;

/**
 * NOTE: the install path below and the assumption that Workshop items are
 * directly HTTP-fetchable (rather than requiring a SteamCMD download step) are
 * both flagged assumptions from the plan - verify against Palworld/UE4SS
 * modding docs and a real Workshop item before relying on this in production.
 */
class PalworldUe4ssDriver implements ModManagerDriverInterface
{
    public function __construct(
        private SteamWorkshopService $steamWorkshopService,
    ) {}

    public function slug(): string
    {
        return 'palworld-ue4ss';
    }

    public function label(): string
    {
        return 'Palworld (UE4SS)';
    }

    public function resolveWorkshopItem(string $urlOrId): array
    {
        return $this->steamWorkshopService->resolve($urlOrId);
    }

    public function installPath(Server $server): string
    {
        return 'Pal/Content/Paks/LogicMods';
    }

    public function install(Server $server, Mod $mod): void
    {
        $fileUrl = $mod->metadata['file_url'] ?? null;

        throw_unless($fileUrl, new RuntimeException("Mod \"{$mod->name}\" has no direct download URL - it may require a SteamCMD-based download instead of a direct HTTP pull."));

        $path = $this->installPath($server);
        $archiveName = "workshop-{$mod->workshop_id}.zip";

        /** @var DaemonFileRepository $files */
        $files = app(DaemonFileRepository::class)->setServer($server);
        // ->throw() matters here: pull() can return a failed response instead
        // of throwing on its own (confirmed against the real
        // pelican-dev/plugins/minecraft-modrinth installer, which does the
        // same) - without it, a failed download falls through into
        // decompressFile() against a file that was never written.
        $files->pull($fileUrl, $path, ['filename' => $archiveName])->throw();
        $files->decompressFile($path, $archiveName)->throw();
    }

    public function uninstall(Server $server, Mod $mod): void
    {
        /** @var DaemonFileRepository $files */
        $files = app(DaemonFileRepository::class)->setServer($server);
        $files->deleteFiles($this->installPath($server), ["workshop-{$mod->workshop_id}.zip"]);
    }

    public function supportsLoadOrder(): bool
    {
        return true;
    }
}
