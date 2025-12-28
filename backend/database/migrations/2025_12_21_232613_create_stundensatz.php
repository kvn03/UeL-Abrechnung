<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stundensatz', function (Blueprint $table) {
            // Primärschlüssel: StundensatzID
            $table->id('StundensatzID');

            // Fremdschlüssel zu user
            $table->unsignedBigInteger('fk_userID');

            // NEU: Fremdschlüssel zur Abteilung
            // Damit kann ein User pro Abteilung einen eigenen Satz haben.
            $table->unsignedBigInteger('fk_abteilungID');

            // Werte
            $table->decimal('satz', 8, 2);
            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable();

            // Definition der Beziehung zu User
            $table->foreign('fk_userID')
                ->references('UserID')
                ->on('user')
                ->onDelete('cascade');

            // NEU: Definition der Beziehung zu Abteilung
            // Referenziert 'AbteilungID' in der Tabelle 'abteilung_definition'
            $table->foreign('fk_abteilungID')
                ->references('AbteilungID')
                ->on('abteilung_definition')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stundensatz');
    }
};
