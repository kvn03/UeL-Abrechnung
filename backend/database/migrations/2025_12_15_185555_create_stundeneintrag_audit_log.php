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
        Schema::create('stundeneintrag_audit_log', function (Blueprint $table) {
            $table->id('ID');

            // Bezug zum Stundeneintrag
            $table->unsignedBigInteger('fk_stundeneintragID');
            $table->foreign('fk_stundeneintragID')
                ->references('EintragID')
                ->on('stundeneintrag')
                ->onDelete('cascade');

            // Welches Feld wurde geändert
            $table->string('feldname');

            $table->text('alter_wert')->nullable();
            $table->text('neuer_wert')->nullable();

            // Wer / Wann
            $table->unsignedBigInteger('modifiedBy')->nullable();
            $table->foreign('modifiedBy')->references('UserID')->on('user');

            $table->timestamp('modifiedAt')->useCurrent();
            $table->text('kommentar')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stundeneintrag_audit_log');
    }
};
