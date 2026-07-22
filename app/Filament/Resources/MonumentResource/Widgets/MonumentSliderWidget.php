<?php

namespace App\Filament\Resources\MonumentResource\Widgets;

use App\Models\Monument;
use Filament\Widgets\Widget;

class MonumentSliderWidget extends Widget
{
    protected static string $view = 'filament.resources.monument-resource.widgets.monument-slider-widget';

    protected int|string|array $columnSpan = 'full';

    public function getMonuments()
    {
        return Monument::where('actif', true)
            ->whereNotNull('image_principale')
            ->orderBy('ordre_affichage')
            ->get();
    }
}
