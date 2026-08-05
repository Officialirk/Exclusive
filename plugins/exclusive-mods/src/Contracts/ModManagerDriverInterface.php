<?php

namespace Exclusive\Mods\Contracts;

use App\Models\Server;
use Exclusive\Mods\Models\Mod;

interface ModManagerDriverInterface
{
    /**
     * Unique slug this driver is registered under (matches the value stored in
     * exclusive_mods_egg_drivers.driver).
     */
    public function slug(): string;

    public function label(): string;

    /**
     * Resolve a pasted Steam Workshop URL or raw numeric ID into catalog-ready
     * mod attributes (name/description/author/preview_image_url/workshop_id/...).
     *
     * @return array<string, mixed>
     */
    public function resolveWorkshopItem(string $urlOrId): array;

    /**
     * Path (relative to the server's root) mods for this driver are installed into.
     */
    public function installPath(Server $server): string;

    public function install(Server $server, Mod $mod): void;

    public function uninstall(Server $server, Mod $mod): void;

    public function supportsLoadOrder(): bool;
}
