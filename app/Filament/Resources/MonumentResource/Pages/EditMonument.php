<?php

namespace App\Filament\Resources\MonumentResource\Pages;

use App\Filament\Resources\MonumentResource;
use App\Models\Monument;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMonument extends EditRecord
{
    protected static string $resource = MonumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Un monument présent dans un devis ou une commande ne peut pas être supprimé (clés étrangères en RESTRICT).
            Actions\DeleteAction::make()
                ->visible(fn (Monument $record): bool => $record->estSupprimable()),

            Actions\Action::make('desactiver')
                ->label('Désactiver')
                ->icon('heroicon-m-eye-slash')
                ->color('warning')
                ->visible(fn (Monument $record): bool => $record->actif && ! $record->estSupprimable())
                ->requiresConfirmation()
                ->modalHeading('Désactiver ce monument')
                ->modalDescription('Ce monument figure dans des devis ou des commandes : il ne peut pas être supprimé. Désactivé, il reste dans l\'historique. Vous pourrez le réactiver avec l\'option « Actif ».')
                ->action(function (Monument $record): void {
                    $record->update(['actif' => false]);
                    $this->refreshFormData(['actif']);

                    Notification::make()
                        ->title('Monument désactivé')
                        ->success()
                        ->send();
                }),
        ];
    }
}
