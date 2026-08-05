<?php

namespace Officialirk\PalworldMods\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PalworldModsService
{
    /**
     * Thunderstore community slug this plugin pulls mods from.
     */
    protected const COMMUNITY = 'palworld';

    /**
     * Palworld's Steam App ID, used to build Workshop links.
     */
    protected const STEAM_APP_ID = '1623730';

    /**
     * Folder the official Palworld mod loader reads Steam Workshop packages from.
     */
    protected const WORKSHOP_FOLDER = 'Mods/Workshop';

    /**
     * The mod loader's config file: enables mods and lists which packages are active.
     */
    protected const MOD_SETTINGS_PATH = 'Mods/PalModSettings.ini';

    /**
     * Folders mods can be installed into, keyed by path relative to the server root.
     *
     * @return array<string, string>
     */
    public static function targetFolders(): array
    {
        return [
            'Pal/Content/Paks/LogicMods' => 'Pak / LogicMods (most Blueprint & asset mods)',
            'Pal/Content/Paks/~mods' => 'Pak / ~mods (legacy pak mods)',
            'Pal/Binaries/Win64/ue4ss/Mods' => 'UE4SS Mods (Lua script mods, requires UE4SS)',
        ];
    }

    /**
     * Filenames that ship inside a Thunderstore package purely as package
     * metadata and should never be left behind in the server's mod folder.
     *
     * @return array<int, string>
     */
    protected static function packageMetadataFiles(): array
    {
        return ['manifest.json', 'readme.md', 'changelog.md', 'icon.png'];
    }

    public function isPalworldServer(Server $server): bool
    {
        $server->loadMissing('egg');

        $tags = array_map('strtolower', $server->egg->tags ?? []);
        $features = $server->egg->features ?? [];

        return in_array('palworld', $tags) || in_array('palworld_mods', $features);
    }

    /**
     * Fetch (and cache) the full Thunderstore package list for the Palworld community.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getRawPackages(): array
    {
        return Cache::remember('palworld_mods:thunderstore_packages', now()->addMinutes(15), function () {
            try {
                $packages = Http::asJson()
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->throw()
                    ->get('https://thunderstore.io/c/' . self::COMMUNITY . '/api/v1/package/')
                    ->json();

                return is_array($packages) ? $packages : [];
            } catch (Exception $exception) {
                report($exception);

                return [];
            }
        });
    }

    /** @return array<string, mixed>|null */
    protected function normalizePackage(array $package): ?array
    {
        $versions = $package['versions'] ?? [];

        if (empty($versions)) {
            return null;
        }

        $latest = $versions[0];

        return [
            'uuid4' => $package['uuid4'] ?? null,
            'full_name' => $package['full_name'] ?? $latest['full_name'] ?? null,
            'name' => $package['name'] ?? $latest['name'] ?? 'Unknown',
            'owner' => $package['owner'] ?? 'Unknown',
            'description' => $latest['description'] ?? '',
            'icon_url' => $latest['icon'] ?? null,
            'categories' => $package['categories'] ?? [],
            'is_deprecated' => (bool) ($package['is_deprecated'] ?? false),
            'is_pinned' => (bool) ($package['is_pinned'] ?? false),
            'rating_score' => (int) ($package['rating_score'] ?? 0),
            'downloads' => (int) collect($versions)->sum(fn ($version) => $version['downloads'] ?? 0),
            'date_updated' => $package['date_updated'] ?? $latest['date_created'] ?? null,
            'package_url' => $package['package_url'] ?? "https://thunderstore.io/c/" . self::COMMUNITY . "/p/{$package['owner']}/{$package['name']}/",
            'version_number' => $latest['version_number'] ?? '0.0.0',
            'download_url' => $latest['download_url'] ?? null,
            'file_size' => (int) ($latest['file_size'] ?? 0),
            'dependencies' => $latest['dependencies'] ?? [],
        ];
    }

    /** @return array{data: array<int, array<string, mixed>>, total: int} */
    public function getPackages(int $page = 1, string $search = '', int $perPage = 15): array
    {
        $packages = collect($this->getRawPackages())
            ->map(fn (array $package) => $this->normalizePackage($package))
            ->filter()
            ->filter(fn (array $package) => !empty($package['download_url']));

        if ($search !== '') {
            $needle = strtolower($search);
            $packages = $packages->filter(function (array $package) use ($needle) {
                return str_contains(strtolower($package['name']), $needle)
                    || str_contains(strtolower($package['owner']), $needle)
                    || str_contains(strtolower($package['description']), $needle);
            });
        }

        $packages = $packages
            ->sortByDesc(fn (array $package) => $package['is_pinned'] ? PHP_INT_MAX : $package['downloads'])
            ->values();

        $total = $packages->count();
        $data = $packages->forPage($page, $perPage)->values()->all();

        return ['data' => $data, 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function getPackageByFullName(string $fullName): ?array
    {
        $package = collect($this->getRawPackages())
            ->first(fn (array $package) => ($package['full_name'] ?? null) === $fullName);

        return $package ? $this->normalizePackage($package) : null;
    }

    protected function getMetadataFilePath(): string
    {
        return '.palworld-mods-metadata.json';
    }

    /**
     * @return array<int, array{full_name: string, name: string, owner: string, version_number: string, target_folder: string, entries: array<int, string>, icon_url: ?string, installed_at: string}>
     */
    public function getInstalledMods(Server $server): array
    {
        try {
            $fileRepository = app(DaemonFileRepository::class);

            $content = $fileRepository->setServer($server)->getContent($this->getMetadataFilePath());
            $metadata = json_decode($content, true);

            if (!is_array($metadata) || !isset($metadata['installed_mods']) || !is_array($metadata['installed_mods'])) {
                return [];
            }

            return array_values(array_filter($metadata['installed_mods'], fn ($entry) => is_array($entry) && isset($entry['full_name'])));
        } catch (Exception) {
            // No metadata file yet (e.g. nothing installed), or the file could not be read.
            return [];
        }
    }

    public function getInstalledMod(Server $server, string $fullName): ?array
    {
        foreach ($this->getInstalledMods($server) as $mod) {
            if ($mod['full_name'] === $fullName) {
                return $mod;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $entries
     * @param  array<string, mixed>  $extra  Extra fields to store, e.g. ['source' => 'steam_workshop', 'workshop_id' => ..., 'package_name' => ...]
     */
    public function saveModMetadata(
        Server $server,
        string $fullName,
        string $name,
        string $owner,
        string $versionNumber,
        string $targetFolder,
        array $entries,
        ?string $iconUrl = null,
        array $extra = [],
    ): bool {
        try {
            return Cache::lock("palworld_mods_metadata:{$server->id}", 10)->block(5, function () use ($server, $fullName, $name, $owner, $versionNumber, $targetFolder, $entries, $iconUrl, $extra) {
                $fileRepository = app(DaemonFileRepository::class);

                $installedMods = collect($this->getInstalledMods($server))
                    ->reject(fn ($mod) => $mod['full_name'] === $fullName)
                    ->values()
                    ->all();

                $installedMods[] = array_merge([
                    'full_name' => $fullName,
                    'name' => $name,
                    'owner' => $owner,
                    'version_number' => $versionNumber,
                    'target_folder' => $targetFolder,
                    'entries' => $entries,
                    'icon_url' => $iconUrl,
                    'installed_at' => now()->toIso8601String(),
                    'source' => 'thunderstore',
                ], $extra);

                $response = $fileRepository->setServer($server)->putContent(
                    $this->getMetadataFilePath(),
                    json_encode(['installed_mods' => $installedMods], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );

                return !$response->failed();
            }) === true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    public function removeModMetadata(Server $server, string $fullName): bool
    {
        try {
            return Cache::lock("palworld_mods_metadata:{$server->id}", 10)->block(5, function () use ($server, $fullName) {
                $fileRepository = app(DaemonFileRepository::class);

                $installedMods = collect($this->getInstalledMods($server))
                    ->reject(fn ($mod) => $mod['full_name'] === $fullName)
                    ->values()
                    ->all();

                $response = $fileRepository->setServer($server)->putContent(
                    $this->getMetadataFilePath(),
                    json_encode(['installed_mods' => $installedMods], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );

                return !$response->failed();
            }) === true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Downloads and extracts a Thunderstore package into the given target folder,
     * returning the list of top-level entries that were added by the install so
     * they can be tracked for later updates/removal.
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    public function installPackage(Server $server, array $package, string $targetFolder): array
    {
        $fileRepository = app(DaemonFileRepository::class);
        $fileRepository->setServer($server);

        $before = $this->listFolderEntries($fileRepository, $targetFolder);

        $zipName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $package['full_name'] . '-' . $package['version_number']) . '.zip';

        $fileRepository
            ->pull($package['download_url'], $targetFolder, ['filename' => $zipName, 'foreground' => true])
            ->throw();

        $fileRepository
            ->decompressFile($targetFolder, $zipName)
            ->throw();

        // Clean up the archive itself; it's not part of the mod payload.
        $fileRepository->deleteFiles($targetFolder, [$zipName]);

        $after = $this->listFolderEntries($fileRepository, $targetFolder);

        $newEntries = array_values(array_diff($after, $before));

        // Strip Thunderstore package metadata files (manifest.json, README.md, ...)
        // out of the mod folder — they aren't part of the mod itself.
        $clutter = array_values(array_intersect(
            $newEntries,
            array_filter($newEntries, fn ($entry) => in_array(strtolower($entry), self::packageMetadataFiles()))
        ));

        if (!empty($clutter)) {
            $fileRepository->deleteFiles($targetFolder, $clutter);
            $newEntries = array_values(array_diff($newEntries, $clutter));
        }

        return $newEntries;
    }

    /**
     * @param  array<int, string>  $entries
     *
     * @throws Exception
     */
    public function removePackageFiles(Server $server, string $targetFolder, array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        app(DaemonFileRepository::class)
            ->setServer($server)
            ->deleteFiles($targetFolder, $entries)
            ->throw();
    }

    /** @return array<int, string> */
    protected function listFolderEntries(DaemonFileRepository $fileRepository, string $folder): array
    {
        try {
            $files = $fileRepository->getDirectory($folder);

            if (!is_array($files) || isset($files['error'])) {
                return [];
            }

            return collect($files)->pluck('name')->filter()->values()->all();
        } catch (Exception) {
            // Folder likely doesn't exist yet; that's fine, it'll be created by the pull.
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Steam Workshop
    |--------------------------------------------------------------------------
    |
    | Since Palworld's "Home Sweet Home" (1.0) update, dedicated servers load
    | mods through an official loader: a package (with an Info.json describing
    | it) is dropped into Mods/Workshop/<folder>/ and enabled by listing its
    | PackageName in Mods/PalModSettings.ini. This is a different, simpler
    | mechanism than the raw Thunderstore extraction above, so it gets its own
    | install/uninstall path — but reuses the same installed-mods metadata file.
    |
    */

    /**
     * Pull a Steam Workshop item ID out of a raw ID or a workshop URL.
     */
    public function resolveWorkshopId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^\d+$/', $input)) {
            return $input;
        }

        if (preg_match('/[?&]id=(\d+)/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Look up a Steam Workshop item via Steam's public (keyless) web API.
     *
     * @return array<string, mixed>|null
     */
    public function getSteamWorkshopItem(string $workshopId): ?array
    {
        return Cache::remember("palworld_mods:workshop_item:$workshopId", now()->addMinutes(15), function () use ($workshopId) {
            try {
                $response = Http::asForm()
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->post('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/', [
                        'itemcount' => 1,
                        'publishedfileids[0]' => $workshopId,
                    ])
                    ->json();

                $details = $response['response']['publishedfiledetails'][0] ?? null;

                // result === 1 means "found"; anything else (9 = not found, etc.) is a miss.
                if (!is_array($details) || (int) ($details['result'] ?? 0) !== 1) {
                    return null;
                }

                return [
                    'workshop_id' => $workshopId,
                    'title' => $details['title'] ?? "Workshop item $workshopId",
                    'description' => $details['description'] ?? '',
                    'preview_url' => $details['preview_url'] ?? null,
                    'file_url' => $details['file_url'] ?? null,
                    'file_size' => (int) ($details['file_size'] ?? 0),
                    'time_updated' => (int) ($details['time_updated'] ?? 0),
                    'subscriptions' => (int) ($details['subscriptions'] ?? 0),
                    'workshop_url' => "https://steamcommunity.com/sharedfiles/filedetails/?id=$workshopId",
                ];
            } catch (Exception $exception) {
                report($exception);

                return null;
            }
        });
    }

    public function hasSteamApiKey(): bool
    {
        return !empty(config('palworld-mods.steam_api_key'));
    }

    /**
     * Search/browse the Steam Workshop for Palworld items. Requires a Steam
     * Web API key (config('palworld-mods.steam_api_key')) — returns an empty
     * result set without one rather than failing.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function searchWorkshopItems(int $page = 1, string $search = '', int $perPage = 15): array
    {
        $apiKey = config('palworld-mods.steam_api_key');

        if (empty($apiKey)) {
            return ['data' => [], 'total' => 0];
        }

        return Cache::remember("palworld_mods:workshop_search:$page:" . md5($search), now()->addMinutes(15), function () use ($apiKey, $page, $search) {
            try {
                $params = [
                    'key' => $apiKey,
                    'appid' => self::STEAM_APP_ID,
                    'numperpage' => 15,
                    'page' => $page,
                    'return_short_description' => true,
                    'return_previews' => true,
                    // query_type 12 = RankedByTextSearch (needed for search_text to actually
                    // affect ranking), 1 = RankedByVote (a reasonable "most popular" default).
                    'query_type' => $search !== '' ? 12 : 1,
                ];

                if ($search !== '') {
                    $params['search_text'] = $search;
                }

                $response = Http::asForm()
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->get('https://api.steampowered.com/IPublishedFileService/QueryFiles/v1/', $params)
                    ->json();

                $files = $response['response']['publishedfiledetails'] ?? [];
                $total = (int) ($response['response']['total'] ?? count($files));

                $data = collect($files)
                    ->filter(fn ($file) => (int) ($file['result'] ?? 1) === 1)
                    ->map(fn ($file) => [
                        'full_name' => 'workshop-' . $file['publishedfileid'],
                        'workshop_id' => $file['publishedfileid'],
                        'title' => $file['title'] ?? 'Untitled',
                        'description' => $file['short_description'] ?? ($file['description'] ?? ''),
                        'preview_url' => $file['preview_url'] ?? null,
                        'file_url' => $file['file_url'] ?? null,
                        'file_size' => (int) ($file['file_size'] ?? 0),
                        'time_updated' => (int) ($file['time_updated'] ?? 0),
                        'subscriptions' => (int) ($file['subscriptions'] ?? 0),
                        'workshop_url' => "https://steamcommunity.com/sharedfiles/filedetails/?id={$file['publishedfileid']}",
                    ])
                    ->values()
                    ->all();

                return ['data' => $data, 'total' => $total];
            } catch (Exception $exception) {
                report($exception);

                return ['data' => [], 'total' => 0];
            }
        });
    }

    /**
     * Download and deploy a Steam Workshop item into Mods/Workshop, then enable
     * it in Mods/PalModSettings.ini using the PackageName from its Info.json.
     *
     * @param  array<string, mixed>  $item
     * @return array{folder: string, package_name: string}
     *
     * @throws Exception
     */
    public function installWorkshopItem(Server $server, array $item): array
    {
        if (empty($item['file_url'])) {
            throw new Exception("This Workshop item doesn't expose a direct download through Steam's API, so it can't be fetched from the panel. Install it via SteamCMD/the Steam client and copy it into Mods/Workshop instead.");
        }

        $fileRepository = app(DaemonFileRepository::class);
        $fileRepository->setServer($server);

        $folder = self::WORKSHOP_FOLDER . '/' . $item['workshop_id'];
        $zipName = $item['workshop_id'] . '.zip';

        $fileRepository
            ->pull($item['file_url'], $folder, ['filename' => $zipName, 'foreground' => true])
            ->throw();

        $fileRepository
            ->decompressFile($folder, $zipName)
            ->throw();

        $fileRepository->deleteFiles($folder, [$zipName]);

        $packageName = $this->findPackageName($fileRepository, $folder);

        if (!$packageName) {
            throw new Exception("Couldn't find a valid Info.json with a PackageName in this Workshop item — it doesn't look like a Palworld mod package.");
        }

        $this->setModSettingsEnabled($server, true);
        $this->addActiveMod($server, $packageName);

        return ['folder' => (string) $item['workshop_id'], 'package_name' => $packageName];
    }

    /**
     * @throws Exception
     */
    public function removeWorkshopItem(Server $server, string $workshopId, ?string $packageName): void
    {
        $this->removePackageFiles($server, self::WORKSHOP_FOLDER, [$workshopId]);

        if ($packageName) {
            $this->removeActiveMod($server, $packageName);
        }
    }

    /**
     * For mods that can't be pulled directly (no direct Steam file_url) —
     * you download it via SteamCMD/the Steam client yourself and upload the
     * resulting folder into Mods/Workshop/<folderName>/ via the file manager.
     * This reads its Info.json and enables it, same as a normal install.
     *
     * @return array{folder: string, package_name: string}
     *
     * @throws Exception
     */
    public function registerExistingWorkshopMod(Server $server, string $folderName): array
    {
        $fileRepository = app(DaemonFileRepository::class);
        $fileRepository->setServer($server);

        $folder = self::WORKSHOP_FOLDER . '/' . $folderName;

        $packageName = $this->findPackageName($fileRepository, $folder);

        if (!$packageName) {
            throw new Exception("Couldn't find a valid Info.json with a PackageName in Mods/Workshop/$folderName — check the folder was uploaded/extracted there correctly.");
        }

        $this->setModSettingsEnabled($server, true);
        $this->addActiveMod($server, $packageName);

        return ['folder' => $folderName, 'package_name' => $packageName];
    }

    /**
     * Look for Info.json directly inside the given folder, or one level of
     * nesting deep (some package zips wrap everything in an extra folder).
     */
    protected function findPackageName(DaemonFileRepository $fileRepository, string $folder): ?string
    {
        $direct = $this->tryReadPackageName($fileRepository, "$folder/Info.json");
        if ($direct) {
            return $direct;
        }

        foreach ($this->listFolderEntries($fileRepository, $folder) as $entry) {
            $nested = $this->tryReadPackageName($fileRepository, "$folder/$entry/Info.json");
            if ($nested) {
                return $nested;
            }
        }

        return null;
    }

    protected function tryReadPackageName(DaemonFileRepository $fileRepository, string $path): ?string
    {
        try {
            $data = json_decode($fileRepository->getContent($path), true);

            return is_array($data) ? ($data['PackageName'] ?? null) : null;
        } catch (Exception) {
            return null;
        }
    }

    protected function readModSettings(Server $server): string
    {
        try {
            return app(DaemonFileRepository::class)->setServer($server)->getContent(self::MOD_SETTINGS_PATH);
        } catch (Exception) {
            // File doesn't exist yet; the game normally generates it after first launch.
            return "[PalModSettings]\nbGlobalEnableMod=false\n";
        }
    }

    /**
     * @throws Exception
     */
    protected function writeModSettings(Server $server, string $content): void
    {
        app(DaemonFileRepository::class)->setServer($server)->putContent(self::MOD_SETTINGS_PATH, $content)->throw();
    }

    /**
     * @throws Exception
     */
    public function setModSettingsEnabled(Server $server, bool $enabled): void
    {
        $content = $this->readModSettings($server);
        $value = $enabled ? 'true' : 'false';

        if (preg_match('/^bGlobalEnableMod\s*=.*$/mi', $content)) {
            $content = preg_replace('/^bGlobalEnableMod\s*=.*$/mi', "bGlobalEnableMod=$value", $content);
        } else {
            $content = rtrim($content) . "\nbGlobalEnableMod=$value\n";
        }

        $this->writeModSettings($server, $content);
    }

    /**
     * @throws Exception
     */
    public function addActiveMod(Server $server, string $packageName): void
    {
        $content = $this->readModSettings($server);

        if (in_array($packageName, $this->getActiveMods($content), true)) {
            return;
        }

        $content = rtrim($content) . "\nActiveModList=$packageName\n";

        $this->writeModSettings($server, $content);
    }

    /**
     * @throws Exception
     */
    public function removeActiveMod(Server $server, string $packageName): void
    {
        $content = $this->readModSettings($server);

        $lines = array_filter(
            preg_split('/\r\n|\r|\n/', $content),
            fn ($line) => !preg_match('/^ActiveModList\s*=\s*' . preg_quote($packageName, '/') . '\s*$/i', trim($line))
        );

        $this->writeModSettings($server, implode("\n", $lines));
    }

    /** @return array<int, string> */
    protected function getActiveMods(string $content): array
    {
        preg_match_all('/^ActiveModList\s*=\s*(.+)$/mi', $content, $matches);

        return array_map('trim', $matches[1] ?? []);
    }
}
