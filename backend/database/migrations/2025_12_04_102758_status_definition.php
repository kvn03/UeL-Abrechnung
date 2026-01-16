<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('status_definition', function (Blueprint $table) {
            // Im Diagramm ist StatusID der Primary Key
            $table->id('StatusID');

            $table->text('name')->nullable();
            $table->text('beschreibung')->nullable();

            // Optional: Timestamps, falls gewünscht
            // $table->timestamps();
        });
        DB::table('status_definition')->insert([
            ['StatusID' => 10, 'name' => 'Offen'],
            ['StatusID' => 11, 'name' => 'Eingereicht'],
            ['StatusID' => 12, 'name' => 'Ungueltig'],
            ['StatusID' => 20, 'name' => 'Erstellt'],
            ['StatusID' => 21, 'name' => 'AL_Freigabe'],
            ['StatusID' => 22, 'name' => 'GS_Freigabe'],
            ['StatusID' => 23, 'name' => 'Bezahlt'],
            ['StatusID' => 24, 'name' => 'Abgebrochen'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('status_definition');
    }
};
