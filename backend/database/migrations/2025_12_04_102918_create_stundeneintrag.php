<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stundeneintrag', function (Blueprint $table) {
            $table->id('EintragID');

            $table->date('datum')->nullable();
            $table->time('beginn')->nullable();
            $table->time('ende')->nullable();
            $table->double('dauer')->nullable();
            $table->text('kurs')->nullable();
            $table->decimal('verguetung', 10, 2)->nullable();

            // User Referenz
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->foreign('createdBy')->references('UserID')->on('user');

            $table->timestamp('createdAt')->useCurrent();

            $table->integer('fk_abrechnungID')->nullable();

            // HIER: Jetzt mit Foreign Key Verknüpfung
            $table->unsignedBigInteger('fk_abteilung')->nullable();

            // Diese Zeile aktiviert die Prüfung gegen die existierende Tabelle
            $table->foreign('fk_abteilung')
                ->references('AbteilungID')
                ->on('abteilung_definition');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stundeneintrag');
    }
};
