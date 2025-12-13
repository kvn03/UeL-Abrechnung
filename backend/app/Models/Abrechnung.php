<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Abrechnung extends Model
{
    use HasFactory;

    /**
     * Der Name der Tabelle, die mit dem Model verknüpft ist.
     */
    protected $table = 'abrechnung';

    /**
     * Der Primärschlüssel der Tabelle.
     */
    protected $primaryKey = 'AbrechnungID';

    /**
     * Gibt an, ob die IDs automatisch hochgezählt werden.
     */
    public $incrementing = true;

    /**
     * Laravel Timestamps Konfiguration.
     * Da nur 'createdAt' existiert und kein 'updated_at', passen wir das an.
     */
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null; // Deaktiviert updated_at

    /**
     * Die Attribute, die massenweise zugewiesen werden können.
     */
    protected $fillable = [
        'zeitraumVon',
        'zeitraumBis',
        'fk_abteilung',
        'createdBy',
        'createdAt'
    ];

    /**
     * Die Attribute, die als Datumsangaben behandelt werden sollen.
     */
    protected $casts = [
        'zeitraumVon' => 'date',
        'zeitraumBis' => 'date',
        'createdAt' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     * RELATIONSHIPS
     * -----------------------------------------------------------------
     */

    /**
     * Die Abteilung, zu der die Abrechnung gehört.
     * Annahme: Model heißt 'AbteilungDefinition'
     */
    public function abteilung(): BelongsTo
    {
        return $this->belongsTo(AbteilungDefinition::class, 'fk_abteilung', 'AbteilungID');
    }

    /**
     * Der User, der die Abrechnung erstellt hat.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdBy', 'UserID');
    }

    /**
     * Die Status-Logs dieser Abrechnung.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(AbrechnungStatusLog::class, 'fk_abrechnungID', 'AbrechnungID');
    }

    /**
     * Die Stundeneinträge dieser Abrechnung.
     */
    public function stundeneintraege(): HasMany
    {
        return $this->hasMany(Stundeneintrag::class, 'fk_abrechnungID', 'AbrechnungID');
    }
}
