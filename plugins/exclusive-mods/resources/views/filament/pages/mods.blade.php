<x-filament-panels::page>
    @if (!$this->isSupported())
        <x-filament::section>
            <div class="text-center py-8">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Mod management isn't set up for this server's game yet. An administrator can map this
                    server's egg to a mod driver to enable it.
                </p>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">
            <div class="flex flex-col gap-4 min-w-0">
                <x-filament::section>
                    <div class="flex items-center gap-3">
                        <x-filament::icon icon="tabler-brand-steam" class="h-6 w-6 text-primary-500" />
                        <div class="flex-1">
                            <p class="text-sm font-semibold">Install from Steam Workshop</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Paste a workshop link or item ID</p>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <x-filament::input.wrapper class="flex-1">
                            <x-filament::input
                                type="text"
                                wire:model="workshopInput"
                                placeholder="https://steamcommunity.com/sharedfiles/filedetails/?id=…"
                            />
                        </x-filament::input.wrapper>
                        <x-filament::button wire:click="install">
                            Install
                        </x-filament::button>
                    </div>
                </x-filament::section>

                <div class="flex items-center gap-2">
                    <x-filament::input.wrapper class="flex-1">
                        <x-filament::input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search installed mods…"
                        />
                    </x-filament::input.wrapper>
                    <x-filament::button
                        :color="$activeFilter === 'all' ? 'primary' : 'gray'"
                        wire:click="$set('activeFilter', 'all')"
                        size="sm"
                    >
                        All
                    </x-filament::button>
                    <x-filament::button
                        :color="$activeFilter === 'installed' ? 'primary' : 'gray'"
                        wire:click="$set('activeFilter', 'installed')"
                        size="sm"
                    >
                        Installed
                    </x-filament::button>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse ($this->getServerMods() as $serverMod)
                        <x-filament::section>
                            <div class="flex items-center gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold">{{ $serverMod->mod->name }}</span>
                                        @if ($serverMod->mod->author)
                                            <span class="text-xs text-gray-500 font-mono">by {{ $serverMod->mod->author }}</span>
                                        @endif
                                    </div>
                                    @if ($serverMod->mod->description)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $serverMod->mod->description }}</p>
                                    @endif
                                    <p class="text-xs mt-1 font-mono uppercase tracking-wide
                                        {{ $serverMod->status === 'installed' ? 'text-success-600' : ($serverMod->status === 'failed' ? 'text-danger-600' : 'text-gray-500') }}">
                                        {{ $serverMod->status }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <x-filament::icon-button
                                        icon="tabler-arrow-up"
                                        wire:click="moveUp({{ $serverMod->id }})"
                                        label="Move up"
                                    />
                                    <x-filament::icon-button
                                        icon="tabler-arrow-down"
                                        wire:click="moveDown({{ $serverMod->id }})"
                                        label="Move down"
                                    />
                                    <x-filament::button size="sm" color="gray" wire:click="updateMod({{ $serverMod->id }})">
                                        Update
                                    </x-filament::button>
                                    <x-filament::button size="sm" color="danger" wire:click="uninstall({{ $serverMod->id }})">
                                        Remove
                                    </x-filament::button>
                                </div>
                            </div>
                        </x-filament::section>
                    @empty
                        <x-filament::section>
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                No mods installed yet. Paste a Steam Workshop link above to get started.
                            </p>
                        </x-filament::section>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <x-filament::section>
                    <x-slot name="heading">Load Order</x-slot>
                    <div class="flex flex-col gap-1">
                        @forelse ($this->getServerMods() as $index => $serverMod)
                            <div class="flex items-center gap-2 py-1 text-sm">
                                <span class="font-mono text-xs text-gray-500 w-4 text-center">{{ $index + 1 }}</span>
                                <span class="flex-1 truncate">{{ $serverMod->mod->name }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">No mods to order yet.</p>
                        @endforelse
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Steam Workshop</x-slot>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Configure a Steam Web API key in the admin Plugins settings to enable Workshop search.
                        Pasting a direct link or item ID works without a key.
                    </p>
                </x-filament::section>
            </div>
        </div>
    @endif
</x-filament-panels::page>
