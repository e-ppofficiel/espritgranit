<?php

namespace App\Filament\Widgets;

use App\Models\Commande;
use Filament\Widgets\ChartWidget;

class RepartitionCommandesWidget extends ChartWidget
{
    protected static ?string $heading = 'Commandes par statut';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $libelles = [
            'en_preparation' => 'En préparation',
            'confirmee' => 'Confirmée',
            'en_production' => 'En production',
            'en_transit' => 'En transit',
            'au_port_arrivee' => "Au port d'arrivée",
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
            'archivee' => 'Archivée',
        ];

        $couleurs = [
            '#9ca3af', '#3b82f6', '#f59e0b', '#8b5cf6',
            '#06b6d4', '#22c55e', '#ef4444', '#6b7280',
        ];

        $comptes = Commande::selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $labels = [];
        $data = [];
        $backgroundColor = [];
        $index = 0;

        foreach ($libelles as $cle => $libelle) {
            if (isset($comptes[$cle]) && $comptes[$cle] > 0) {
                $labels[] = $libelle;
                $data[] = $comptes[$cle];
                $backgroundColor[] = $couleurs[$index % count($couleurs)];
            }
            $index++;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Commandes',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
