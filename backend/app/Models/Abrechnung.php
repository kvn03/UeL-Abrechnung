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
     * Laut Diagramm gibt es 'createdAt', aber kein 'updatedAt'.
     */
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    /**
     * Die Attribute, die massenweise zugewiesen werden können.
     */
    protected $fillable = [
        'fk_quartal',   // Hinzugefügt statt zeitraumVon/Bis
        'fk_abteilung',
        'createdBy',
        'createdAt'
    ];

    /**
     * Die Attribute, die gecastet werden sollen.
     * 'zeitraumVon/Bis' entfernt, da nicht in dieser Tabelle.
     */
    protected $casts = [
        'createdAt' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     * RELATIONSHIPS
     * -----------------------------------------------------------------
     */

    /**
     * Das Quartal, zu dem die Abrechnung gehört.
     * Hierüber erhältst du Zugriff auf 'beginn' und 'ende'.
     */
    public function quartal(): BelongsTo
    {
        // Annahme: Das Model heißt 'Quartal' und der PK dort ist 'ID'
        return $this->belongsTo(Quartal::class, 'fk_quartal', 'ID');
    }

    /**
     * Die Abteilung, zu der die Abrechnung gehört.
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
