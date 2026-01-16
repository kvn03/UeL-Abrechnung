<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbteilungDefinition extends Model
{
    use HasFactory;

    // 1. Name deiner Tabelle in der DB
    protected $table = 'abteilung_definition';

    // 2. Dein Primärschlüssel (laut deinem Bild ist es AbteilungID, nicht id)
    protected $primaryKey = 'AbteilungID';

    // 3. Spalten, die man sehen darf
    protected $fillable = ['name'];

    // Falls du keine created_at/updated_at spalten hast:
    public $timestamps = false;
}
