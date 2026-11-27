<?php

namespace App\Filament\Agence\Pages;

use App\Models\Agence;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * « Mon agence » : l'utilisateur d'une agence y modifie le logo de SON agence (celui de l'en-tête du PDF
 * de ses devis). Seul le logo est modifiable ; les autres informations de l'agence restent gérées par
 * Esprit Granit. Un utilisateur rattaché à une société sans agence propre (responsable de société) n'a pas
 * cette rubrique : il n'y a pas d'agence unique à modifier.
 */
class MonAgence extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Mon agence';

    protected static ?string $title = 'Mon agence';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.agence.pages.mon-agence';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->agence_id;
    }

    protected function agence(): ?Agence
    {
        $agenceId = Auth::user()?->agence_id;

        return $agenceId ? Agence::find($agenceId) : null;
    }

    public function mount(): void
    {
        abort_unless($this->agence(), 403);

        $this->form->fill([
            'logo' => $this->agence()->logo,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('nom')
                    ->label('Agence')
                    ->content(fn () => $this->agence()?->nom ?? '—'),

                Forms\Components\FileUpload::make('logo')
                    ->label('Logo de l\'agence')
                    ->helperText('Affiché dans l\'en-tête du PDF de vos devis. PNG, JPG ou WebP, 2 Mo maximum (fond transparent accepté).')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('agences/logos')
                    ->visibility('public'),
            ])
            ->statePath('data');
    }

    public function enregistrer(): void
    {
        $agence = $this->agence();
        abort_unless($agence, 403);

        $etat = $this->form->getState();
        $nouveau = $etat['logo'] ?? null;
        $ancien = $agence->logo;

        $agence->update(['logo' => $nouveau ?: null]);

        // Logo remplacé ou retiré : l'ancien fichier n'est plus utilisé nulle part, on le supprime.
        if ($ancien && $ancien !== $nouveau) {
            try {
                Storage::disk('public')->delete($ancien);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        Notification::make()
            ->title($nouveau ? 'Logo enregistré' : 'Logo retiré')
            ->success()
            ->send();
    }
}
