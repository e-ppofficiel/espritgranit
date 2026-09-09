<?php
namespace App\Models;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
#[Fillable(['name', 'email', 'password', 'societe_id', 'agence_id', 'actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function societe()
    {
        return $this->belongsTo(Societe::class);
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Un compte désactivé ne peut plus se connecter, sur aucun panel.
        if (! $this->actif) {
            return false;
        }

        if ($panel->getId() === 'adminIkksPov') {
            return $this->hasAnyRole(['super_admin', 'admin_esprit_granit', 'gestionnaire']);
        }

        if ($panel->getId() === 'agence') {
            // Le filet de secours "|| ! is_null($this->agence_id)" a été retiré : il
            // permettait à n'importe quel compte ayant une agence renseignée d'accéder
            // au panel agence, même sans le rôle correspondant. L'accès dépend
            // désormais uniquement du rôle attribué.
            return $this->hasAnyRole(['responsable_societe', 'utilisateur_agence']);
        }

        return false;
    }
}
