<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quartal', function (Blueprint $table) {
            // Im Bild heißt der Primary Key explizit "ID" (großgeschrieben)
            $table->id('ID');

            $table->date('beginn');
            $table->date('ende');

            // Optional: Standard Laravel Timestamps (created_at, updated_at)
            // Falls du die nicht willst, lass die Zeile weg.
            //$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quartal');
    }
};
