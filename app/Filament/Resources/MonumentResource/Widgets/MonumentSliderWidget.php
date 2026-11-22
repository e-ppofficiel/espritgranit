<?php

namespace App\Filament\Resources\MonumentResource\Widgets;

use App\Models\Monument;
use Filament\Widgets\Widget;

class MonumentSliderWidget extends Widget
{
    protected static string $view = 'filament.resources.monument-resource.widgets.monument-slider-widget';

    protected int|string|array $columnSpan = 'full';

    public bool $steleArt = false;

    public function getMonuments()
    {
        $query = Monument::where('actif', true)
            ->whereNotNull('image_principale')
            ->orderBy('ordre_affichage');

        if ($this->steleArt) {
            $query->where('reference_interne', 'like', 'ST-%');
        } else {
            // Les monuments Stèle Art (référence ST-…) ne sont visibles que dans « Monuments Stèle Art ».
            // Les références vides (NULL) restent affichées.
            $query->where(function ($q) {
                $q->whereNull('reference_interne')
                    ->orWhere('reference_interne', 'not like', 'ST-%');
            });
        }

        return $query->get();
    }
}
