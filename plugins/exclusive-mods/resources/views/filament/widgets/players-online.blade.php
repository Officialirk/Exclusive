<x-filament::widget wire:poll.10s>
    <x-filament::section>
        <x-slot name="heading">
            Players Online — {{ count($this->getPlayers()) }}
        </x-slot>

        <div class="flex flex-col gap-1">
            @forelse ($this->getPlayers() as $player)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="font-medium">{{ $player['name'] }}</span>
                    @if ($player['ping'] !== null)
                        <span class="font-mono text-xs text-success-600">{{ $player['ping'] }}ms</span>
                    @endif
                </div>
            @empty
                <p class="text-xs text-gray-500 dark:text-gray-400">No players online.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament::widget>
