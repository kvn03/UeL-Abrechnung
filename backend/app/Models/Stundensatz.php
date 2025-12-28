<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stundensatz extends Model
{
    use HasFactory;

    protected $table = 'stundensatz';
    protected $primaryKey = 'StundensatzID';

    // Zeitstempel deaktivieren, falls nicht in Migration vorhanden
    public $timestamps = false;

    protected $fillable = [
        'fk_userID',
        'fk_abteilungID', // <--- NEU
        'satz',
        'gueltigVon',
        'gueltigBis',
    ];

    protected $casts = [
        'satz' => 'decimal:2',
        'gueltigVon' => 'date',
        'gueltigBis' => 'date',
    ];

    /**
     * Der User, zu dem der Satz gehört.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_userID', 'UserID');
    }

    /**
     * NEU: Die Abteilung, für die dieser Satz gilt.
     * Wir nutzen hier ein generisches Model 'Abteilung', das auf 'abteilung_definition' zeigt.
     * Falls du noch kein Model dafür hast, siehe unten.
     */
    public function abteilung(): BelongsTo
    {
        // Falls dein Model anders heißt (z.B. AbteilungDefinition), hier anpassen.
        // Parameter: Model, FK-Lokal, PK-Fremd
        return $this->belongsTo(AbteilungDefinition::class, 'fk_abteilungID', 'AbteilungID');
    }
}
