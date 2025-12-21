<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StundeneintragStatusLog extends Model
{
    protected $table = 'stundeneintrag_status_log';
    protected $primaryKey = 'ID';

    public $timestamps = false;
    const CREATED_AT = 'modifiedAt';

    protected $fillable = [
        'fk_stundeneintragID',
        'fk_statusID',
        'modifiedBy',
        'modifiedAt',
        'kommentar'
    ];

    // KEIN $casts Array mehr!

    // --- BEZIEHUNGEN ---

    // KORRIGIERT (Typo behoben):
    public function statusDefinition()
    {
        return $this->belongsTo(StatusDefinition::class, 'fk_statusID', 'StatusID');
    }

    public function eintrag()
    {
        return $this->belongsTo(Stundeneintrag::class, 'fk_stundeneintragID', 'EintragID');
    }

    public function bearbeiter()
    {
        return $this->belongsTo(User::class, 'modifiedBy', 'UserID');
    }
}
