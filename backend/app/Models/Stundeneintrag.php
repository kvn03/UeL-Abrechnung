<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stundeneintrag extends Model
{
    protected $table = 'stundeneintrag';
    protected $primaryKey = 'EintragID';

    public $timestamps = false;
    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'datum',
        'beginn',
        'ende',
        'dauer',
        'kurs',
        'createdBy',
        'fk_abrechnungID',
        'fk_abteilung',
        'createdAt'
    ];

    // KEIN $casts Array mehr!

    // --- BEZIEHUNGEN ---

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'createdBy', 'UserID');
    }

    public function abteilung()
    {
        return $this->belongsTo(AbteilungDefinition::class, 'fk_abteilung', 'AbteilungID');
    }

    public function statusLogs()
    {
        return $this->hasMany(StundeneintragStatusLog::class, 'fk_stundeneintragID', 'EintragID');
    }

    public function aktuellerStatusLog()
    {
        return $this->hasOne(StundeneintragStatusLog::class, 'fk_stundeneintragID', 'EintragID')
            ->latest('modifiedAt');
    }

    public function auditLogs()
    {
        return $this->hasMany(StundeneintragAuditLog::class, 'fk_stundeneintragID', 'EintragID');
    }
}
