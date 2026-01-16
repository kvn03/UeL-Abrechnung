<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLizenzen extends Model
{
    // Tabelle explizit angeben
    protected $table = 'user_lizenzen';

    // Primary Key ist großgeschrieben
    protected $primaryKey = 'ID';

    // Automatische Timestamps (created_at, updated_at) ausschalten,
    // falls sie in der Tabelle fehlen (im Diagramm sind sie nicht zu sehen).
    public $timestamps = false;

    protected $fillable = [
        'nummer',
        'name',
        'gueltigVon',
        'gueltigBis',
        'datei', // Hier kommt der Link rein
        'fk_userID',
    ];

    protected $casts = [
        'gueltigVon' => 'date',
        'gueltigBis' => 'date',
    ];
}
