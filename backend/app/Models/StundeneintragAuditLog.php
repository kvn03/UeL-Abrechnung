<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StundeneintragAuditLog extends Model
{
    use HasFactory;

    /**
     * Der Name der Tabelle.
     */
    protected $table = 'stundeneintrag_audit_log';

    /**
     * Der Primärschlüssel.
     */
    protected $primaryKey = 'ID';

    /**
     * Laravel Timestamp Konfiguration.
     * Da dieses Log erstellt wird, wenn eine Änderung passiert, mappen wir
     * CREATED_AT auf 'modifiedAt'. UPDATED_AT wird deaktiviert, da Logs
     * nachträglich nicht bearbeitet werden sollten.
     */
    const CREATED_AT = 'modifiedAt';
    const UPDATED_AT = null;

    /**
     * Die Attribute, die massenweise zugewiesen werden können.
     */
    protected $fillable = [
        'fk_stundeneintragID',
        'feldname',
        'alter_wert',
        'neuer_wert',
        'modifiedBy',
        'modifiedAt',
        'kommentar',
    ];

    /**
     * Casting für Datentypen.
     */
    protected $casts = [
        'modifiedAt' => 'datetime',
        // Optional: Falls 'alter_wert'/'neuer_wert' JSON enthalten könnten,
        // könnte man hier 'array' nutzen, aber 'text' ist laut DB-Schema sicherer als String.
    ];

    /* -----------------------------------------------------------------
     * RELATIONSHIPS
     * -----------------------------------------------------------------
     */

    /**
     * Der Stundeneintrag, auf den sich die Änderung bezieht.
     */
    public function stundeneintrag(): BelongsTo
    {
        // PK von Stundeneintrag ist 'EintragID'
        return $this->belongsTo(Stundeneintrag::class, 'fk_stundeneintragID', 'EintragID');
    }

    /**
     * Der User, der die Änderung vorgenommen hat.
     */
    public function modifier(): BelongsTo
    {
        // Wir nennen die Relation 'modifier', da der FK 'modifiedBy' heißt
        return $this->belongsTo(User::class, 'modifiedBy', 'UserID');
    }
}
