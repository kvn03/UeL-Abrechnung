<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feiertag extends Model
{

    protected $table = 'feiertag';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'datum',
        'fk_zuschlagID',
    ];

    protected $casts = [
        'datum' => 'date',
    ];

    /**
     * Ein Feiertag gehört zu genau einem Zuschlag.
     */
    public function zuschlag()
    {
        // belongsTo(Model, Fremdschlüssel, BesitzerSchlüssel)
        return $this->belongsTo(Zuschlag::class, 'fk_zuschlagID', 'ID');
    }
    public $timestamps = false;
}
