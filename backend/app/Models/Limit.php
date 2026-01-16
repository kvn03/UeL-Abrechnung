<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Limit extends Model
{
    // Tabelle heißt 'limit' (Singular)
    protected $table = 'limit';

    // Primärschlüssel ist 'ID' (Großgeschrieben)
    protected $primaryKey = 'ID';

    // Felder, die massenweise befüllt werden dürfen
    protected $fillable = [
        'wert',
        'gueltigVon',
        'gueltigBis',
    ];

    // Typ-Konvertierung für einfacheres Arbeiten
    protected $casts = [
        'wert' => 'float',
        'gueltigVon' => 'date',
        'gueltigBis' => 'date',
    ];
    public $timestamps = false;
}
