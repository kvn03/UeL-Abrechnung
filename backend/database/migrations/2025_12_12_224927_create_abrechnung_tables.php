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
        // 1. Tabelle: abrechnung
        Schema::create('abrechnung', function (Blueprint $table) {
            $table->integer('AbrechnungID')->autoIncrement(); // Primary Key
            $table->date('zeitraumVon')->nullable();
            $table->date('zeitraumBis')->nullable();

            // Foreign Keys
            // Hinweis: Im Diagramm heißt die Spalte fk_abteilung.
            // Ich nehme an, die Zieltabelle ist 'abteilung_definition' und der Key dort 'AbteilungID'.
            $table->integer('fk_abteilung')->nullable();

            $table->integer('createdBy')->nullable(); // Verweis auf user.UserID

            // createdAt ist im Diagramm explizit, Laravel nutzt normalerweise timestamps()
            // Hier nutzen wir explizit den Namen aus dem Diagramm
            $table->timestamp('createdAt')->useCurrent();

            // Indizes und Constraints für Foreign Keys
            // Annahme: Die anderen Tabellen heißen 'abteilung_definition' und 'user'
            $table->foreign('fk_abteilung')->references('AbteilungID')->on('abteilung_definition')->nullOnDelete();
            $table->foreign('createdBy')->references('UserID')->on('user')->nullOnDelete();
        });

        // 2. Tabelle: abrechnung_status_log
        Schema::create('abrechnung_status_log', function (Blueprint $table) {
            $table->integer('ID')->autoIncrement(); // Primary Key

            $table->integer('fk_abrechnungID'); // FK zur Tabelle 'abrechnung'
            $table->integer('fk_statusID');     // FK zur Tabelle 'status_definition'
            $table->integer('modifiedBy')->nullable(); // FK zur Tabelle 'user'

            $table->timestamp('modifiedAt')->useCurrent();
            $table->text('kommentar')->nullable();

            // Indizes und Constraints
            $table->foreign('fk_abrechnungID')->references('AbrechnungID')->on('abrechnung')->cascadeOnDelete();

            // Annahme: Zieltabelle heißt 'status_definition' mit PK 'StatusID'
            $table->foreign('fk_statusID')->references('StatusID')->on('status_definition');

            $table->foreign('modifiedBy')->references('UserID')->on('user')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abrechnung_status_log');
        Schema::dropIfExists('abrechnung');
    }
};
