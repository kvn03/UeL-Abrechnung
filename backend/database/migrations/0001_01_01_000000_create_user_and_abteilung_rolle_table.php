<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->id('UserID');
            $table->string('name');
            $table->string('vorname');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('isAdmin')->default(false);
            $table->boolean('isGeschaeftsstelle')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        DB::table('user')->insert([
            'name' => 'Administrator',
            'vorname' => 'Super',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin'), // Passwort immer hashen!
            'isAdmin' => true,
            'isGeschaeftsstelle' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        /*Schema::create('stundensatz', function (Blueprint $table) {
            $table->id('StundensatzID');
            $table->foreignId('fk_userID')->constrained('user', 'UserID')->onDelete('cascade');
            $table->double('satz');
            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable();
            $table->timestamps();
        });*/


        /*Schema::create('lizenzen_definition', function (Blueprint $table) {
            $table->id('LizenzID');
            $table->string('art');
            $table->double('standardstundensatz');
            $table->timestamps();
        });*/



        /*Schema::create('user_lizenzen', function (Blueprint $table) {
            $table->id('ID');
            $table->integer('nummer');
            $table->string('name');
            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable();
            $table->string('datei')->nullable();

            $table->foreignId('fk_userID')->constrained('user', 'UserID')->onDelete('cascade');
            $table->timestamps();
        });*/



        Schema::create('abteilung_definition', function (Blueprint $table) {
            $table->id('AbteilungID');
            $table->string('name');
            //$table->timestamps();
        });
        DB::table('abteilung_definition')->insert([
            ['name' => 'Turnen'],
            ['name' => 'Fußball'],
            ['name' => 'Handball'],
            ['name' => 'Schwimmen'],
            ['name' => 'Leichtathletik'],
            ['name' => 'Badminton'],
        ]);


        Schema::create('rolle_definition', function (Blueprint $table) {
            $table->id('RolleID');
            $table->string('bezeichnung');
            //$table->timestamps();
        });
        DB::table('rolle_definition')->insert([
            ['RolleID' => 1, 'bezeichnung' => 'Uebungsleiter'],
            ['RolleID' => 2, 'bezeichnung' => 'Abteilungsleiter']
        ]);



        Schema::create('user_rolle_abteilung', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fk_rolleID')->constrained('rolle_definition', 'RolleID')->onDelete('cascade');
            $table->foreignId('fk_abteilungID')->constrained('abteilung_definition', 'AbteilungID')->onDelete('cascade');
            $table->foreignId('fk_userID')->constrained('user', 'UserID')->onDelete('cascade');

            //$table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_rolle_abteilung');
        Schema::dropIfExists('rolle_definition');
        Schema::dropIfExists('abteilung_definition');
        //Schema::dropIfExists('user_lizenzen');
        //Schema::dropIfExists('lizenzen_definition');
        //Schema::dropIfExists('stundensatz');
        Schema::dropIfExists('user');
    }
};
