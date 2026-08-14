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
        }

        return $query->get();
    }
}
