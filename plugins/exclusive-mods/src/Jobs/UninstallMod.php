<?php

namespace Exclusive\Mods\Jobs;

use App\Models\Server;
use App\Models\User;
use Exclusive\Mods\Models\ServerMod;
use Exclusive\Mods\Services\ModManagerDriverRegistry;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UninstallMod implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Server $server,
        public int $serverModId,
    ) {}

    public function handle(ModManagerDriverRegistry $registry): void
    {
        $serverMod = ServerMod::query()->with('mod')->findOrFail($this->serverModId);
        $modName = $serverMod->mod->name;

        try {
            $driver = $registry->driverForServer($this->server);
            throw_unless($driver, new Exception("This server's egg does not support mod management."));

            $driver->uninstall($this->server, $serverMod->mod);

            $serverMod->delete();

            Notification::make()
                ->success()
                ->title('Mod uninstalled')
                ->body($modName)
                ->sendToDatabase($this->user);
        } catch (Exception $exception) {
            report($exception);

            $serverMod->update(['status' => ServerMod::STATUS_FAILED]);

            Notification::make()
                ->danger()
                ->title('Mod uninstall failed')
                ->body($exception->getMessage())
                ->sendToDatabase($this->user);
        }
    }

    public function uniqueId(): string
    {
        return "mod:uninstall:{$this->server->id}:{$this->serverModId}";
    }
}
