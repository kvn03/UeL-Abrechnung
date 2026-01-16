<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRolleAbteilung extends Model
{
    use HasFactory;

    protected $table = 'user_rolle_abteilung'; // Dein Tabellenname
    protected $primaryKey = 'id'; // Dein Primärschlüssel

    // Damit wir UserRolleAbteilung::create([...]) nutzen können
    protected $fillable = [
        'fk_userID',
        'fk_abteilungID',
        'fk_rolleID'
    ];

    public function rolle()
    {
        return $this->belongsTo(RolleDefinition::class, 'fk_rolleID', 'RolleID');
    }

    public function abteilung()
    {
        return $this->belongsTo(AbteilungDefinition::class, 'fk_abteilungID', 'AbteilungID');
    }

    public $timestamps = false;

    // Falls deine Tabelle keine created_at/updated_at Spalten hat:
    // public $timestamps = false;
}
