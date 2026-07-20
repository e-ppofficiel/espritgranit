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
#[Fillable(['name', 'email', 'password', 'societe_id', 'agence_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
        if ($panel->getId() === 'adminIkksPov') {
            return $this->hasAnyRole(['super_admin', 'admin_esprit_granit', 'gestionnaire']);
        }

        if ($panel->getId() === 'agence') {
            return $this->hasAnyRole(['responsable_societe', 'utilisateur_agence']) || ! is_null($this->agence_id);
        }

        return false;
    }
}
