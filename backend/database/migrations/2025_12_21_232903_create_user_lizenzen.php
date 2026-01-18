<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_lizenzen', function (Blueprint $table) {
            // Primärschlüssel: ID
            $table->id('ID');

            $table->integer('nummer');
            $table->string('name');
            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable();

            // Im Diagramm steht 'text'. In Laravel ist 'string' (VARCHAR) meist performanter für Pfade,
            // aber 'text' speichert mehr Zeichen. Ich nehme hier text wie im Bild.
            $table->text('datei')->nullable();

            // Fremdschlüssel zu user
            $table->unsignedBigInteger('fk_userID');

            // Definition der Beziehung
            $table->foreign('fk_userID')
                ->references('UserID')
                ->on('user')
                ->onDelete('cascade');

            // $table->timestamps(); // Optional
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_lizenzen');
    }
};
