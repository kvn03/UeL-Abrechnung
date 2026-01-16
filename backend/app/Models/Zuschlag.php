<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zuschlag extends Model
{
    protected $table = 'zuschlag';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'faktor',
        'gueltigVon',
        'gueltigBis',
    ];

    protected $casts = [
        'faktor' => 'float',
        'gueltigVon' => 'date',
        'gueltigBis' => 'date',
    ];
    public $timestamps = false;

    /**
     * Ein Zuschlag kann vielen Feiertagen zugeordnet sein.
     */
    public function feiertag()
    {
        // hasMany(Model, Fremdschlüssel, LokalerSchlüssel)
        return $this->hasMany(Feiertag::class, 'fk_zuschlagID', 'ID');
    }
}
