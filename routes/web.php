<?php

use App\Livewire\DemandeAdhesionForm;
use App\Models\Devis;
use App\Services\DevisPdfGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Filament\Actions\Exports\Http\Controllers\DownloadExport;

Route::get('/filament/exports/{export}/download', DownloadExport::class)
    ->name('filament.exports.download')
    ->middleware(['web', 'auth:admin']);



Route::get('/demande-adhesion', DemandeAdhesionForm::class)->name('demande-adhesion');

Route::get('/devis/{devis}/telecharger-pdf', function (Devis $devis) {
    $utilisateurAdmin = Auth::guard('admin')->user();
    $utilisateurAgence = Auth::guard('agence')->user();

    if (! $utilisateurAdmin && ! $utilisateurAgence) {
        abort(403);
    }

    // Un admin voit tous les devis. Une agence ne peut télécharger que les siens
    // (ou ceux de toute sa société si "responsable de société") — même logique
    // de scoping que partout ailleurs sur l'application.
    if (! $utilisateurAdmin) {
        $autorise = ($utilisateurAgence->agence_id && $devis->agence_id === $utilisateurAgence->agence_id)
            || ($utilisateurAgence->societe_id && $devis->societe_id === $utilisateurAgence->societe_id);

        if (! $autorise) {
            abort(403);
        }
    }

    return DevisPdfGenerator::telecharger($devis);
})->name('devis.telecharger-pdf');

Route::get('/', function () {
    return view('welcome');
});
