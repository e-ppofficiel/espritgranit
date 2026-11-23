<?php

namespace App\Filament\Agence\Resources\MonumentCatalogResource\Pages;

use App\Filament\Agence\Resources\MonumentCatalogResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageMonumentCatalogs extends ManageRecords
{
    protected static string $resource = MonumentCatalogResource::class;

    /**
     * 1 = « Catalogue Stèle Art » (références ST-), 0 = « Catalogue » standard.
     * Propriété synchronisée avec l'URL (?stele_art=1) : contrairement à request(), elle survit
     * aux requêtes Livewire suivantes (recherche, tri, filtres, pagination).
     */
    #[Url(as: 'stele_art')]
    public int $steleArt = 0;

    public function getTitle(): string
    {
        return $this->steleArt === 1 ? 'Catalogue Stèle Art' : 'Catalogue';
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($this->steleArt === 1) {
            $query->where('reference_interne', 'like', 'ST-%');
        } else {
            // Les monuments Stèle Art (référence ST-…) ne sont visibles que dans « Catalogue Stèle Art ».
            // Les références vides (NULL) restent affichées.
            $query->where(function (Builder $q) {
                $q->whereNull('reference_interne')
                    ->orWhere('reference_interne', 'not like', 'ST-%');
            });
        }

        return $query;
    }

    /** Pas de bouton « Créer » : le catalogue est en lecture seule pour les agences. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
