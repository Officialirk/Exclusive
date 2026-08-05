<?php

namespace Exclusive\Mods\Filament\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Models\Server;
use BackedEnum;
use Exclusive\Mods\Jobs\InstallMod;
use Exclusive\Mods\Jobs\UninstallMod;
use Exclusive\Mods\Jobs\UpdateMod;
use Exclusive\Mods\Models\ServerMod;
use Exclusive\Mods\Services\ModManagerDriverRegistry;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class Mods extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Package;

    protected static ?int $navigationSort = 2;

    protected string $view = 'exclusive-mods::filament.pages.mods';

    public string $search = '';

    public string $activeFilter = 'all';

    public string $workshopInput = '';

    public static function canAccess(): bool
    {
        return parent::canAccess() && user()?->can(SubuserPermission::FileRead, Filament::getTenant());
    }

    public function getTitle(): string
    {
        return 'Mods';
    }

    public static function getNavigationLabel(): string
    {
        return 'Mods';
    }

    private function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function isSupported(): bool
    {
        return app(ModManagerDriverRegistry::class)->isSupported(
            app(ModManagerDriverRegistry::class)->driverSlugForServer($this->server()),
        );
    }

    /** @return Collection<int, ServerMod> */
    public function getServerMods(): Collection
    {
        $query = ServerMod::query()
            ->with('mod')
            ->where('server_id', $this->server()->id)
            ->orderBy('load_order');

        if ($this->activeFilter === 'installed') {
            $query->where('status', ServerMod::STATUS_INSTALLED);
        }

        return $query->get()->filter(function (ServerMod $serverMod) {
            if ($this->search === '') {
                return true;
            }

            return str_contains(strtolower($serverMod->mod->name), strtolower($this->search));
        })->values();
    }

    public function install(): void
    {
        abort_unless(user()?->can(SubuserPermission::FileUpdate, $this->server()), 403);

        if (trim($this->workshopInput) === '') {
            return;
        }

        InstallMod::dispatch(user(), $this->server(), $this->workshopInput);

        Notification::make()
            ->title('Mod queued for installation')
            ->body('You will be notified once it finishes.')
            ->success()
            ->send();

        $this->workshopInput = '';
    }

    public function updateMod(int $serverModId): void
    {
        abort_unless(user()?->can(SubuserPermission::FileUpdate, $this->server()), 403);

        UpdateMod::dispatch(user(), $this->server(), $serverModId);

        Notification::make()
            ->title('Checking for updates…')
            ->success()
            ->send();
    }

    public function uninstall(int $serverModId): void
    {
        abort_unless(user()?->can(SubuserPermission::FileDelete, $this->server()), 403);

        UninstallMod::dispatch(user(), $this->server(), $serverModId);

        Notification::make()
            ->title('Mod queued for removal')
            ->success()
            ->send();
    }

    public function toggleEnabled(int $serverModId): void
    {
        abort_unless(user()?->can(SubuserPermission::FileUpdate, $this->server()), 403);

        $serverMod = ServerMod::query()->where('server_id', $this->server()->id)->findOrFail($serverModId);
        $serverMod->update(['enabled' => !$serverMod->enabled]);
    }

    public function moveUp(int $serverModId): void
    {
        $this->swapLoadOrder($serverModId, -1);
    }

    public function moveDown(int $serverModId): void
    {
        $this->swapLoadOrder($serverModId, 1);
    }

    private function swapLoadOrder(int $serverModId, int $direction): void
    {
        abort_unless(user()?->can(SubuserPermission::FileUpdate, $this->server()), 403);

        $ordered = ServerMod::query()
            ->where('server_id', $this->server()->id)
            ->orderBy('load_order')
            ->get();

        $index = $ordered->search(fn (ServerMod $serverMod) => $serverMod->id === $serverModId);
        $swapIndex = $index + $direction;

        if ($index === false || !$ordered->has($swapIndex)) {
            return;
        }

        $current = $ordered->get($index);
        $swapWith = $ordered->get($swapIndex);

        [$currentOrder, $swapOrder] = [$current->load_order, $swapWith->load_order];

        $current->update(['load_order' => $swapOrder]);
        $swapWith->update(['load_order' => $currentOrder]);
    }
}
