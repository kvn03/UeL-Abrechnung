<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quartal extends Model
{
    use HasFactory;

    /**
     * Der Name der Tabelle.
     */
    protected $table = 'quartal';

    /**
     * Der Primärschlüssel.
     */
    protected $primaryKey = 'ID';

    /**
     * Timestamps deaktivieren, da das Diagramm keine created_at/updated_at Spalten zeigt.
     */
    public $timestamps = false;

    /**
     * Die Attribute, die massenweise zugewiesen werden können.
     */
    protected $fillable = [
        'beginn',
        'ende',
    ];

    /**
     * Casting für Datumsfelder, damit Carbon-Instanzen zurückgegeben werden.
     */
    protected $casts = [
        'beginn' => 'date',
        'ende'   => 'date',
    ];

    /* -----------------------------------------------------------------
     * RELATIONSHIPS
     * -----------------------------------------------------------------
     */

    /**
     * Ein Quartal kann für viele Abrechnungen verwendet werden.
     */
    public function abrechnungen(): HasMany
    {
        return $this->hasMany(Abrechnung::class, 'fk_quartal', 'ID');
    }

    /* -----------------------------------------------------------------
     * HELPER / ACCESSORS (Optional)
     * -----------------------------------------------------------------
     */

    /**
     * Optional: Hilfsmethode, um den Namen des Quartals anzuzeigen (z.B. "Q1 2024").
     * Nutzung: $quartal->bezeichnung
     */
    public function getBezeichnungAttribute(): string
    {
        if (!$this->beginn) return 'Unbekanntes Quartal';

        // Berechnet die Quartalsnummer (1-4) und das Jahr anhand des Beginns
        return 'Q' . $this->beginn->quarter . ' ' . $this->beginn->year;
    }
}
