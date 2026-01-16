<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('limit', function (Blueprint $table) {
            $table->id('ID');

            // Numeric im Bild entspricht meist Decimal in Laravel
            $table->decimal('wert', 10, 2);

            $table->date('gueltigVon');
            $table->date('gueltigBis')->nullable();

            //$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('limit');
    }
};
