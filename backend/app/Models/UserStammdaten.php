<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserStammdaten extends Model
{
    use HasFactory;

    protected $table = 'user_stammdaten';
    protected $primaryKey = 'ID'; // Wichtig, da nicht 'id'

    protected $fillable = [
        'fk_userID',
        'iban',
        'plz',
        'ort',
        'strasse',
        'hausnr',
        'gueltigVon',
        'gueltigBis'
    ];

    protected $casts = [
        'gueltigVon' => 'date',
        'gueltigBis' => 'date',
    ];

    public $timestamps = false;
}
