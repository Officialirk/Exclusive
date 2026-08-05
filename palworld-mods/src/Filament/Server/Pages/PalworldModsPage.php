<?php

namespace Officialirk\PalworldMods\Filament\Server\Pages;

use App\Filament\Server\Resources\Files\Pages\ListFiles;
use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Officialirk\PalworldMods\Facades\PalworldMods;
use Officialirk\PalworldMods\Services\PalworldModsService;

class PalworldModsPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use HasTabs;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';

    protected static ?string $slug = 'palworld-mods';

    protected static ?int $navigationSort = 30;

    /**
     * Must match PalworldModsService::WORKSHOP_FOLDER.
     */
    protected const WORKSHOP_TARGET_FOLDER = 'Mods/Workshop';

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return parent::canAccess() && PalworldMods::isPalworldServer($server);
    }

    public static function getNavigationLabel(): string
    {
        return 'Mods';
    }

    public static function getModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'browse' => Tab::make('Browse'),
            'installed' => Tab::make('Installed'),
        ];
    }

    protected function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int $page) {
                if ($this->activeTab === 'installed') {
                    $installed = PalworldMods::getInstalledMods($this->server());

                    if ($search) {
                        $needle = strtolower($search);
                        $installed = array_values(array_filter($installed, fn (array $mod) => str_contains(strtolower($mod['name']), $needle) || str_contains(strtolower($mod['owner']), $needle)));
                    }

                    return new LengthAwarePaginator($installed, count($installed), 15, $page);
                }

                $response = PalworldMods::getPackages($page, $search ?? '');

                return new LengthAwarePaginator($response['data'], $response['total'], 15, $page);
            })
            ->paginated([15])
            ->columns($this->activeTab === 'installed' ? $this->installedColumns() : $this->browseColumns())
            ->recordUrl(function (array $record) {
                if ($this->activeTab === 'installed') {
                    return null;
                }

                return $record['package_url'] ?? null;
            }, true)
            ->recordActions($this->activeTab === 'installed' ? $this->installedActions() : $this->browseActions());
    }

    /** @return array<int, \Filament\Tables\Columns\Column> */
    protected function browseColumns(): array
    {
        return [
            ImageColumn::make('icon_url')
                ->label(''),
            TextColumn::make('name')
                ->searchable()
                ->formatStateUsing(fn ($state, array $record) => ($record['is_deprecated'] ?? false) ? "$state ⚠️" : $state)
                ->description(fn (array $record) => strlen($record['description']) > 120 ? substr($record['description'], 0, 120) . '...' : $record['description']),
            TextColumn::make('owner')
                ->url(fn ($state) => "https://thunderstore.io/c/palworld/p/$state/", true)
                ->toggleable(),
            TextColumn::make('downloads')
                ->icon('tabler-download')
                ->numeric()
                ->toggleable(),
            TextColumn::make('version_number')
                ->label('Latest')
                ->badge()
                ->toggleable(),
            TextColumn::make('date_updated')
                ->icon('tabler-calendar')
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->diffForHumans() : 'Unknown')
                ->toggleable(),
        ];
    }

    /** @return array<int, \Filament\Tables\Columns\Column> */
    protected function installedColumns(): array
    {
        return [
            ImageColumn::make('icon_url')
                ->label(''),
            TextColumn::make('name')
                ->description(fn (array $record) => $record['owner']),
            TextColumn::make('source')
                ->label('Source')
                ->badge()
                ->color(fn ($state) => $state === 'steam_workshop' ? 'info' : 'gray')
                ->formatStateUsing(fn ($state) => $state === 'steam_workshop' ? 'Steam Workshop' : 'Thunderstore'),
            TextColumn::make('version_number')
                ->label(fn (array $record) => ($record['source'] ?? null) === 'steam_workshop' ? 'Updated' : 'Version')
                ->formatStateUsing(fn ($state, array $record) => ($record['source'] ?? null) === 'steam_workshop' && $state ? Carbon::createFromTimestamp((int) $state)->diffForHumans() : $state)
                ->badge(),
            TextColumn::make('target_folder')
                ->label('Folder')
                ->formatStateUsing(fn ($state, array $record) => $this->installedModPath($record))
                ->toggleable(),
            TextColumn::make('installed_at')
                ->icon('tabler-calendar')
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->diffForHumans() : 'Unknown')
                ->toggleable(),
        ];
    }

    /**
     * The folder an installed mod's files actually live in — Steam Workshop
     * items each get their own subfolder under Mods/Workshop.
     *
     * @param  array<string, mixed>  $record
     */
    protected function installedModPath(array $record): string
    {
        if (($record['source'] ?? null) === 'steam_workshop' && !empty($record['entries'][0])) {
            return rtrim($record['target_folder'], '/') . '/' . $record['entries'][0];
        }

        return $record['target_folder'];
    }

    /** @return array<int, Action> */
    protected function browseActions(): array
    {
        return [
            Action::make('install')
                ->iconButton()
                ->icon('tabler-download')
                ->color('success')
                ->tooltip('Install')
                ->visible(fn (array $record) => is_null(PalworldMods::getInstalledMod($this->server(), $record['full_name'])))
                ->schema([
                    Select::make('target_folder')
                        ->label('Install to')
                        ->options(PalworldModsService::targetFolders())
                        ->default('Pal/Content/Paks/LogicMods')
                        ->helperText('Check the mod\'s Thunderstore page if you\'re not sure which folder it needs.')
                        ->required(),
                ])
                ->action(function (array $data, array $record) {
                    $this->performInstall($record, $data['target_folder']);
                }),
            Action::make('update')
                ->iconButton()
                ->icon('tabler-refresh')
                ->color('warning')
                ->tooltip('Update')
                ->visible(function (array $record) {
                    $installed = PalworldMods::getInstalledMod($this->server(), $record['full_name']);

                    return $installed && $installed['version_number'] !== $record['version_number'];
                })
                ->requiresConfirmation()
                ->modalHeading('Update mod')
                ->modalDescription(fn (array $record) => "Update {$record['name']} to version {$record['version_number']}?")
                ->action(function (array $record) {
                    $installed = PalworldMods::getInstalledMod($this->server(), $record['full_name']);
                    $this->performInstall($record, $installed['target_folder'] ?? 'Pal/Content/Paks/LogicMods', $installed);
                }),
            Action::make('installed')
                ->iconButton()
                ->icon('tabler-check')
                ->color('success')
                ->tooltip('Installed')
                ->disabled()
                ->visible(function (array $record) {
                    $installed = PalworldMods::getInstalledMod($this->server(), $record['full_name']);

                    return $installed && $installed['version_number'] === $record['version_number'];
                }),
            Action::make('uninstall')
                ->iconButton()
                ->icon('tabler-trash')
                ->color('danger')
                ->tooltip('Uninstall')
                ->visible(fn (array $record) => !is_null(PalworldMods::getInstalledMod($this->server(), $record['full_name'])))
                ->requiresConfirmation()
                ->modalHeading('Uninstall mod')
                ->modalDescription(fn (array $record) => "Remove {$record['name']} and its files from the server?")
                ->action(function (array $record) {
                    $installed = PalworldMods::getInstalledMod($this->server(), $record['full_name']);

                    if ($installed) {
                        $this->performUninstall($installed);
                    }
                }),
        ];
    }

    /** @return array<int, Action> */
    protected function installedActions(): array
    {
        return [
            Action::make('open_folder')
                ->iconButton()
                ->icon('tabler-folder-open')
                ->tooltip('Open folder')
                ->url(fn (array $record) => ListFiles::getUrl(['path' => $this->installedModPath($record)]), true),
            Action::make('update')
                ->iconButton()
                ->icon('tabler-refresh')
                ->color('warning')
                ->tooltip('Update')
                ->visible(function (array $record) {
                    if (($record['source'] ?? null) === 'steam_workshop') {
                        $item = PalworldMods::getSteamWorkshopItem($record['workshop_id'] ?? '');

                        return $item && (string) $item['time_updated'] !== (string) $record['version_number'];
                    }

                    $package = PalworldMods::getPackageByFullName($record['full_name']);

                    return $package && $package['version_number'] !== $record['version_number'];
                })
                ->requiresConfirmation()
                ->modalHeading('Update mod')
                ->action(function (array $record) {
                    if (($record['source'] ?? null) === 'steam_workshop') {
                        $this->performWorkshopInstall($record['workshop_id'] ?? '', $record);

                        return;
                    }

                    $package = PalworldMods::getPackageByFullName($record['full_name']);

                    if (!$package) {
                        Notification::make()->title('Mod is no longer available on Thunderstore')->danger()->send();

                        return;
                    }

                    $this->performInstall($package, $record['target_folder'], $record);
                }),
            Action::make('uninstall')
                ->iconButton()
                ->icon('tabler-trash')
                ->color('danger')
                ->tooltip('Uninstall')
                ->requiresConfirmation()
                ->modalHeading('Uninstall mod')
                ->modalDescription(fn (array $record) => "Remove {$record['name']} and its files from the server?")
                ->action(fn (array $record) => $this->performUninstall($record)),
        ];
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>|null  $previouslyInstalled
     */
    protected function performInstall(array $package, string $targetFolder, ?array $previouslyInstalled = null): void
    {
        try {
            $server = $this->server();

            // If we're updating and moving/reinstalling, clear out the old files first
            // so stale files from a previous version don't linger.
            if ($previouslyInstalled && !empty($previouslyInstalled['entries'])) {
                try {
                    PalworldMods::removePackageFiles($server, $previouslyInstalled['target_folder'], $previouslyInstalled['entries']);
                } catch (Exception $exception) {
                    report($exception);
                }
            }

            $entries = PalworldMods::installPackage($server, $package, $targetFolder);

            $saved = PalworldMods::saveModMetadata(
                $server,
                $package['full_name'],
                $package['name'],
                $package['owner'],
                $package['version_number'],
                $targetFolder,
                $entries,
                $package['icon_url'] ?? null,
            );

            if (!$saved) {
                throw new Exception('Failed to save mod metadata');
            }

            $this->resetTable();

            Notification::make()
                ->title('Mod installed')
                ->body("{$package['name']} {$package['version_number']} was installed into $targetFolder")
                ->success()
                ->send();
        } catch (Exception $exception) {
            report($exception);

            $this->resetTable();

            Notification::make()
                ->title('Install failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $installed
     */
    protected function performUninstall(array $installed): void
    {
        try {
            $server = $this->server();

            if (($installed['source'] ?? null) === 'steam_workshop') {
                PalworldMods::removeWorkshopItem($server, $installed['entries'][0] ?? '', $installed['package_name'] ?? null);
            } else {
                PalworldMods::removePackageFiles($server, $installed['target_folder'], $installed['entries']);
            }

            PalworldMods::removeModMetadata($server, $installed['full_name']);

            $this->resetTable();

            Notification::make()
                ->title('Mod removed')
                ->body("{$installed['name']} was uninstalled")
                ->success()
                ->send();
        } catch (Exception $exception) {
            report($exception);

            $this->resetTable();

            Notification::make()
                ->title('Uninstall failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Look up a Steam Workshop item by URL/ID and install (or reinstall, when
     * updating) it, keeping the installed-mods manifest in sync.
     *
     * @param  array<string, mixed>|null  $previouslyInstalled
     */
    protected function performWorkshopInstall(string $input, ?array $previouslyInstalled = null): void
    {
        try {
            $server = $this->server();

            $workshopId = PalworldMods::resolveWorkshopId($input);

            if (!$workshopId) {
                throw new Exception("Couldn't find a Steam Workshop item ID in that input.");
            }

            $item = PalworldMods::getSteamWorkshopItem($workshopId);

            if (!$item) {
                throw new Exception('Workshop item not found — it may be private, removed, or the ID/URL is wrong.');
            }

            // Reinstalling (update): clear out the old deployed folder first.
            if ($previouslyInstalled && !empty($previouslyInstalled['entries'])) {
                try {
                    PalworldMods::removePackageFiles($server, $previouslyInstalled['target_folder'], $previouslyInstalled['entries']);
                } catch (Exception $exception) {
                    report($exception);
                }
            }

            $result = PalworldMods::installWorkshopItem($server, $item);

            $saved = PalworldMods::saveModMetadata(
                $server,
                'workshop-' . $item['workshop_id'],
                $item['title'],
                'Steam Workshop',
                (string) $item['time_updated'],
                self::WORKSHOP_TARGET_FOLDER,
                [$result['folder']],
                $item['preview_url'] ?? null,
                [
                    'source' => 'steam_workshop',
                    'workshop_id' => $item['workshop_id'],
                    'package_name' => $result['package_name'],
                ],
            );

            if (!$saved) {
                throw new Exception('Failed to save mod metadata');
            }

            $this->resetTable();

            Notification::make()
                ->title('Workshop mod installed')
                ->body("{$item['title']} was installed and enabled ({$result['package_name']})")
                ->success()
                ->send();
        } catch (Exception $exception) {
            report($exception);

            $this->resetTable();

            Notification::make()
                ->title('Workshop install failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_workshop_item')
                ->label('Add from Steam Workshop')
                ->icon('tabler-brand-steam')
                ->color('primary')
                ->schema([
                    TextInput::make('input')
                        ->label('Steam Workshop URL or ID')
                        ->placeholder('https://steamcommunity.com/sharedfiles/filedetails/?id=...')
                        ->helperText('Find mods on the Palworld Steam Workshop page, then paste the link or ID here.')
                        ->required(),
                ])
                ->modalSubmitActionLabel('Install')
                ->action(fn (array $data) => $this->performWorkshopInstall($data['input'])),
            Action::make('open_logicmods')
                ->label('LogicMods folder')
                ->icon('tabler-folder-open')
                ->url(fn () => ListFiles::getUrl(['path' => 'Pal/Content/Paks/LogicMods']), true),
            Action::make('open_ue4ss')
                ->label('UE4SS Mods folder')
                ->icon('tabler-folder-open')
                ->url(fn () => ListFiles::getUrl(['path' => 'Pal/Binaries/Win64/ue4ss/Mods']), true),
            Action::make('open_workshop')
                ->label('Workshop folder')
                ->icon('tabler-folder-open')
                ->url(fn () => ListFiles::getUrl(['path' => self::WORKSHOP_TARGET_FOLDER]), true),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $server = $this->server();

        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        TextEntry::make('installed')
                            ->label('Installed mods')
                            ->state(fn () => count(PalworldMods::getInstalledMods($server)))
                            ->badge(),
                    ]),
                $this->getTabsContentComponent(),
                EmbeddedTable::make(),
            ]);
    }
}
