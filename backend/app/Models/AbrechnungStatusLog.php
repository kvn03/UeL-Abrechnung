<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbrechnungStatusLog extends Model
{
    use HasFactory;

    protected $table = 'abrechnung_status_log';
    protected $primaryKey = 'ID';

    /**
     * Timestamp Konfiguration.
     * Im Diagramm heißt es 'modifiedAt'. Wir nutzen das als Erstellzeitpunkt des Logs.
     */
    const CREATED_AT = 'modifiedAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'fk_abrechnungID',
        'fk_statusID',
        'modifiedBy',
        'modifiedAt',
        'kommentar'
    ];

    protected $casts = [
        'modifiedAt' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     * RELATIONSHIPS
     * -----------------------------------------------------------------
     */

    /**
     * Die zugehörige Abrechnung.
     */
    public function abrechnung(): BelongsTo
    {
        return $this->belongsTo(Abrechnung::class, 'fk_abrechnungID', 'AbrechnungID');
    }

    /**
     * Die Status-Definition (z.B. "Eingereicht", "Genehmigt").
     * Annahme: Model heißt 'StatusDefinition'
     */
    public function statusDefinition(): BelongsTo
    {
        return $this->belongsTo(StatusDefinition::class, 'fk_statusID', 'StatusID');
    }

    /**
     * Der User, der den Status geändert hat.
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifiedBy', 'UserID');
    }
}
