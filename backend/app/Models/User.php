<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'user';
    protected $primaryKey = 'UserID';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'vorname',
        'email',
        'password',
        'isAdmin',
        'isGeschaeftsstelle'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'isAdmin' => 'boolean',
        'isGeschaeftsstelle' => 'boolean',
        'password' => 'hashed',   // <- AUTOMATISCHES HASHING
    ];

    public function rollenZuweisungen(): HasMany
    {
        return $this->hasMany(UserRolleAbteilung::class, 'fk_userID', 'UserID');
    }
    public function isAbteilungsleiter(): bool
    {
        // Vartiante A: Du kennst die ID der Rolle (z.B. 2)
        // return $this->rollenZuweisungen()->where('fk_rolleID', 2)->exists();

        // Variante B: Sicherer - Wir prüfen über den Namen der Rolle (falls sich IDs ändern)
        return $this->rollenZuweisungen()
            ->whereHas('rolle', function($query) {
                $query->where('bezeichnung', 'Abteilungsleiter'); // Name muss exakt stimmen
            })
            ->exists();
    }

    public function stundensaetze()
    {
        return $this->hasMany(Stundensatz::class, 'fk_userID', 'UserID');
    }

    // Hilfsmethode: Den aktuell gültigen Satz abrufen
    public function aktuellerStundensatz()
    {
        return $this->hasOne(Stundensatz::class, 'fk_userID', 'UserID')
            ->whereNull('gueltigBis')
            ->latest('gueltigVon');
    }
}
