<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Neue Tabelle erstellen
        Schema::create('user_stammdaten', function (Blueprint $table) {
            // Primärschlüssel 'ID' wie im Diagramm
            $table->id('ID');

            // Fremdschlüssel
            $table->unsignedBigInteger('fk_userID');

            // Stammdaten
            // Hinweis: 'text' speichert viel, 'string' (VARCHAR) wäre oft performanter.
            // Ich nehme 'text' wie im Diagramm angegeben.
            $table->text('iban');
            $table->text('plz'); // Achtung: Führende Nullen (z.B. 01067) gehen bei int verloren! Besser wäre string.
            $table->text('ort');
            $table->text('strasse');
            $table->text('hausnr');

            // Zeitliche Steuerung
            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable(); // Null = Aktuell gültig

            // Laravel Standard Timestamps (created_at, updated_at)
            //$table->timestamps();

            // Foreign Key Constraint
            $table->foreign('fk_userID')
                ->references('UserID')
                ->on('user')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Tabelle löschen
        Schema::dropIfExists('user_stammdaten');
    }
};
