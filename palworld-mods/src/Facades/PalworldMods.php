<?php

namespace Officialirk\PalworldMods\Facades;

use App\Models\Server;
use Illuminate\Support\Facades\Facade;
use Officialirk\PalworldMods\Services\PalworldModsService;

/**
 * @method static bool isPalworldServer(Server $server)
 * @method static array{data: array<int, array<string, mixed>>, total: int} getPackages(int $page = 1, string $search = '', int $perPage = 15)
 * @method static array<string, mixed>|null getPackageByFullName(string $fullName)
 * @method static array<int, array<string, mixed>> getInstalledMods(Server $server)
 * @method static array<string, mixed>|null getInstalledMod(Server $server, string $fullName)
 * @method static bool saveModMetadata(Server $server, string $fullName, string $name, string $owner, string $versionNumber, string $targetFolder, array $entries, ?string $iconUrl = null, array $extra = [])
 * @method static bool removeModMetadata(Server $server, string $fullName)
 * @method static array<int, string> installPackage(Server $server, array $package, string $targetFolder)
 * @method static void removePackageFiles(Server $server, string $targetFolder, array $entries)
 * @method static ?string resolveWorkshopId(string $input)
 * @method static array<string, mixed>|null getSteamWorkshopItem(string $workshopId)
 * @method static bool hasSteamApiKey()
 * @method static array{data: array<int, array<string, mixed>>, total: int} searchWorkshopItems(int $page = 1, string $search = '', int $perPage = 15)
 * @method static bool isSteamCmdInstalled(Server $server)
 * @method static void installSteamCmd(Server $server)
 * @method static array{folder: string, package_name: string} installWorkshopItem(Server $server, array $item)
 * @method static array{folder: string, package_name: string} registerExistingWorkshopMod(Server $server, string $folderName)
 * @method static void removeWorkshopItem(Server $server, string $workshopId, ?string $packageName)
 * @method static void setModSettingsEnabled(Server $server, bool $enabled)
 * @method static void addActiveMod(Server $server, string $packageName)
 * @method static void removeActiveMod(Server $server, string $packageName)
 *
 * @see PalworldModsService
 */
class PalworldMods extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PalworldModsService::class;
    }
}
