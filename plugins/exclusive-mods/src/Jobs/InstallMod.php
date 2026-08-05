<?php

namespace Exclusive\Mods\Jobs;

use App\Models\Server;
use App\Models\User;
use Exclusive\Mods\Models\Mod;
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

class InstallMod implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Server $server,
        public string $workshopInput,
    ) {}

    public function handle(ModManagerDriverRegistry $registry, SteamWorkshopService $steam): void
    {
        try {
            $driver = $registry->driverForServer($this->server);
            throw_unless($driver, new Exception("This server's egg does not support mod management."));

            $resolved = $driver->resolveWorkshopItem($this->workshopInput);

            $mod = Mod::query()->updateOrCreate(
                ['driver' => $driver->slug(), 'workshop_id' => $resolved['workshop_id']],
                [
                    'name' => $resolved['name'],
                    'description' => $resolved['description'] ?? null,
                    'author' => $resolved['author'] ?? null,
                    'preview_image_url' => $resolved['preview_image_url'] ?? null,
                    'file_size' => $resolved['file_size'] ?? null,
                    'metadata' => $resolved['metadata'] ?? [],
                ],
            );

            $serverMod = ServerMod::query()->updateOrCreate(
                ['server_id' => $this->server->id, 'mod_id' => $mod->id],
                ['status' => ServerMod::STATUS_INSTALLING],
            );

            $driver->install($this->server, $mod);

            $serverMod->update([
                'status' => ServerMod::STATUS_INSTALLED,
                'installed_version' => (string) ($resolved['file_size'] ?? '1'),
                'installed_at' => now(),
            ]);

            Notification::make()
                ->success()
                ->title('Mod installed')
                ->body($mod->name)
                ->sendToDatabase($this->user);
        } catch (Exception $exception) {
            report($exception);

            if (isset($serverMod)) {
                $serverMod->update(['status' => ServerMod::STATUS_FAILED]);
            }

            Notification::make()
                ->danger()
                ->title('Mod installation failed')
                ->body($exception->getMessage())
                ->sendToDatabase($this->user);
        }
    }

    public function uniqueId(): string
    {
        return "mod:install:{$this->server->id}:{$this->workshopInput}";
    }
}
