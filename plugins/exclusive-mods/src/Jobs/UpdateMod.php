<?php

namespace Exclusive\Mods\Jobs;

use App\Models\Server;
use App\Models\User;
use Exclusive\Mods\Models\ServerMod;
use Exclusive\Mods\Services\ModManagerDriverRegistry;
use Exclusive\Mods\Services\SteamWorkshopService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateMod implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Server $server,
        public int $serverModId,
    ) {}

    public function handle(ModManagerDriverRegistry $registry, SteamWorkshopService $steam): void
    {
        $serverMod = ServerMod::query()->with('mod')->findOrFail($this->serverModId);

        try {
            $driver = $registry->driverForServer($this->server);
            throw_unless($driver, new Exception("This server's egg does not support mod management."));

            $mod = $serverMod->mod;
            $resolved = $driver->resolveWorkshopItem($mod->workshop_id);
            $latestVersion = (string) ($resolved['file_size'] ?? '1');

            if ($latestVersion === $serverMod->installed_version) {
                Notification::make()
                    ->info()
                    ->title('Already up to date')
                    ->body($mod->name)
                    ->sendToDatabase($this->user);

                return;
            }

            $serverMod->update(['status' => ServerMod::STATUS_UPDATING]);

            $mod->update([
                'name' => $resolved['name'],
                'description' => $resolved['description'] ?? null,
                'preview_image_url' => $resolved['preview_image_url'] ?? null,
                'file_size' => $resolved['file_size'] ?? null,
                'metadata' => $resolved['metadata'] ?? [],
            ]);

            $driver->install($this->server, $mod);

            $serverMod->update([
                'status' => ServerMod::STATUS_INSTALLED,
                'installed_version' => $latestVersion,
                'installed_at' => now(),
            ]);

            Notification::make()
                ->success()
                ->title('Mod updated')
                ->body($mod->name)
                ->sendToDatabase($this->user);
        } catch (Exception $exception) {
            report($exception);

            $serverMod->update(['status' => ServerMod::STATUS_FAILED]);

            Notification::make()
                ->danger()
                ->title('Mod update failed')
                ->body($exception->getMessage())
                ->sendToDatabase($this->user);
        }
    }

    public function uniqueId(): string
    {
        return "mod:update:{$this->server->id}:{$this->serverModId}";
    }
}
