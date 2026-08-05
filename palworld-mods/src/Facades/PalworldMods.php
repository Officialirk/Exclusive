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
 * @method static bool saveModMetadata(Server $server, string $fullName, string $name, string $owner, string $versionNumber, string $targetFolder, array $entries, ?string $iconUrl = null)
 * @method static bool removeModMetadata(Server $server, string $fullName)
 * @method static array<int, string> installPackage(Server $server, array $package, string $targetFolder)
 * @method static void removePackageFiles(Server $server, string $targetFolder, array $entries)
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
