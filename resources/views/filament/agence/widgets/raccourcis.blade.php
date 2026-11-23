<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
            <x-filament::button tag="a" :href="$urlNouveauDevis" icon="heroicon-o-plus" size="lg">
                Nouveau devis
            </x-filament::button>

            <x-filament::button tag="a" :href="$urlCatalogue" icon="heroicon-o-squares-2x2" size="lg" color="gray">
                Catalogue
            </x-filament::button>

            <x-filament::button tag="a" :href="$urlCatalogueSteleArt" icon="heroicon-o-squares-2x2" size="lg" color="gray">
                Catalogue Stèle Art
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
