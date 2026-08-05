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
    ): bool {
        try {
            return Cache::lock("palworld_mods_metadata:{$server->id}", 10)->block(5, function () use ($server, $fullName, $name, $owner, $versionNumber, $targetFolder, $entries, $iconUrl) {
                $fileRepository = app(DaemonFileRepository::class);

                $installedMods = collect($this->getInstalledMods($server))
                    ->reject(fn ($mod) => $mod['full_name'] === $fullName)
                    ->values()
                    ->all();

                $installedMods[] = [
                    'full_name' => $fullName,
                    'name' => $name,
                    'owner' => $owner,
                    'version_number' => $versionNumber,
                    'target_folder' => $targetFolder,
                    'entries' => $entries,
                    'icon_url' => $iconUrl,
                    'installed_at' => now()->toIso8601String(),
                ];

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
}
