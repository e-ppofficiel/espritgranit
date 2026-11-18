<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            @if(! $analyseEffectuee)
                <x-filament::button wire:click="analyser">
                    Analyser
                </x-filament::button>
            @else
                <x-filament::button wire:click="validerImport" color="success">
                    Valider l'import
                </x-filament::button>
                <x-filament::button wire:click="validerImport(true)" color="warning">
                    Valider l'import en ignorant les warnings
                </x-filament::button>
                <x-filament::button wire:click="reinitialiser" color="gray">
                    Recommencer
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
