<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\DemandeAdhesionForm;

Route::get('/demande-adhesion', DemandeAdhesionForm::class)->name('demande-adhesion');

Route::get('/', function () {
    return view('welcome');
});
