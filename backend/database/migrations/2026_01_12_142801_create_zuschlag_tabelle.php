<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zuschlag', function (Blueprint $table) {
            $table->id('ID');

            // Beispiel: Faktor 1.50 -> 5 Stellen insgesamt, 2 Nachkomma
            $table->decimal('faktor', 5, 2);
            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable();
            //$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('zuschlag');
    }
};
