<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'user';
    protected $primaryKey = 'UserID';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'vorname',
        'email',
        'password',
        'isAdmin',
        'isGeschaeftsstelle'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'isAdmin' => 'boolean',
        'isGeschaeftsstelle' => 'boolean',
        'password' => 'hashed',   // <- AUTOMATISCHES HASHING
    ];
}
