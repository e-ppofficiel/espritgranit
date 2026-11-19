<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * État du configurateur 3D d'un monument, retrouvable par un code court (paramètre d'adresse « c »).
 *
 * L'état est TOUJOURS revalidé côté serveur (granit actif, semelle valable pour ce monument, ambiance connue…) :
 * une valeur falsifiée ou périmée est remplacée par « non défini » et le configurateur retombe sur ses valeurs par défaut.
 *
 * Clés de l'état :
 *   g  id du granit (int|null)          sb  soubassement noir (bool)      se  avec semelle (bool)
 *   st id du type de semelle (int|null) sd  id de la dimension (int|null)
 *   fo fond « blanc »|« noir » (null si non proposé)   de décor jardin (bool|null)   am clé d'ambiance (string|null)
 */
class ConfigurationPartagee extends Model
{
    protected $table = 'configurations_partagees';

    protected $fillable = ['code', 'monument_id', 'etat', 'user_id', 'dernier_acces_at'];

    protected $casts = [
        'etat' => 'array',
        'dernier_acces_at' => 'datetime',
    ];

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nettoie et complète un état venu du navigateur. Ne lève jamais d'exception.
     * L'ordre des clés est fixe : c'est ce qui rend le code (MD5) reproductible.
     */
    public static function normaliser(Monument $monument, $brut): array
    {
        $brut = is_array($brut) ? $brut : [];

        $entierOuNull = static function ($valeur) {
            if (is_int($valeur)) {
                return $valeur > 0 ? $valeur : null;
            }
            if (is_string($valeur) && ctype_digit($valeur) && strlen($valeur) <= 12) {
                return (int) $valeur > 0 ? (int) $valeur : null;
            }

            return null;
        };
        $booleen = static fn ($valeur, $defaut) => is_bool($valeur) ? $valeur : $defaut;

        // Granit : doit exister et être actif.
        $granit = $entierOuNull($brut['g'] ?? null);
        if ($granit !== null && ! Granit::where('actif', true)->whereKey($granit)->exists()) {
            $granit = null;
        }

        // Semelle : le type doit être proposé (et actif) pour CE monument ; la dimension doit lui appartenir
        // et être compatible avec le type (dimension sans type = valable pour tous les types).
        $type = $entierOuNull($brut['st'] ?? null);
        if ($type !== null && ! $monument->semelleTypes()->where('semelle_types.actif', true)->where('semelle_types.id', $type)->exists()) {
            $type = null;
        }
        $dimension = $entierOuNull($brut['sd'] ?? null);
        if ($dimension !== null) {
            $dim = $monument->semelleDimensions->firstWhere('id', $dimension);
            if (! $dim || ($dim->semelle_type_id !== null && $type !== null && (int) $dim->semelle_type_id !== $type)) {
                $dimension = null;
            }
        }

        $fond = $brut['fo'] ?? null;
        $fond = in_array($fond, ['blanc', 'noir'], true) ? $fond : null;

        $ambiance = $brut['am'] ?? null;
        $ambiancesConnues = array_merge(['defaut'], array_map('strval', array_keys((array) config('configurateur.ambiances', []))));
        $ambiance = is_string($ambiance) && in_array($ambiance, $ambiancesConnues, true) ? $ambiance : null;

        return [
            'g' => $granit,
            'sb' => $booleen($brut['sb'] ?? null, false),
            'se' => $booleen($brut['se'] ?? null, true),
            'st' => $type,
            'sd' => $dimension,
            'fo' => $fond,
            'de' => array_key_exists('de', $brut) && is_bool($brut['de']) ? $brut['de'] : null,
            'am' => $ambiance,
        ];
    }

    /**
     * Enregistre (ou retrouve) la configuration et renvoie son code court.
     * Même monument + même état = même code (pas de doublon). En cas de collision improbable sur le début
     * du MD5, on allonge le code jusqu'à trouver un emplacement libre.
     */
    public static function enregistrer(Monument $monument, $brut, ?int $userId = null): string
    {
        $etat = static::normaliser($monument, $brut);
        $json = json_encode($etat);
        $empreinte = md5($monument->id . '|' . $json);

        for ($longueur = 12; $longueur <= 32; $longueur++) {
            $code = substr($empreinte, 0, $longueur);

            for ($essai = 0; $essai < 2; $essai++) {
                $existante = static::where('code', $code)->first();

                if ($existante) {
                    if ((int) $existante->monument_id === (int) $monument->id && json_encode($existante->etat) === $json) {
                        return $code;
                    }
                    continue 2; // code pris par une autre configuration : on allonge
                }

                try {
                    static::create(['code' => $code, 'monument_id' => $monument->id, 'etat' => $etat, 'user_id' => $userId]);

                    return $code;
                } catch (UniqueConstraintViolationException $e) {
                    // Enregistrement simultané du même code : on relit.
                }
            }
        }

        throw new \RuntimeException('Impossible de générer un code de configuration.');
    }

    /**
     * Retrouve l'état d'un code pour CE monument (revalidé), ou null si le code est absent, mal formé ou d'un autre monument.
     */
    public static function lire(Monument $monument, $code): ?array
    {
        if (! is_string($code) || ! preg_match('/^[0-9a-f]{12,32}$/', $code)) {
            return null;
        }

        $configuration = static::where('code', $code)->where('monument_id', $monument->id)->first();
        if (! $configuration) {
            return null;
        }

        // Date du dernier accès (au plus une mise à jour par jour) : sert à repérer plus tard les codes jamais rouverts.
        static::whereKey($configuration->id)
            ->where(fn ($q) => $q->whereNull('dernier_acces_at')->orWhere('dernier_acces_at', '<', date('Y-m-d H:i:s', time() - 86400)))
            ->update(['dernier_acces_at' => date('Y-m-d H:i:s')]);

        return static::normaliser($monument, $configuration->etat);
    }
}
