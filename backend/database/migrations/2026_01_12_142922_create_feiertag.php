<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Yasumi\Yasumi;
use App\Models\Feiertag;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('feiertag', function (Blueprint $table) {
            $table->id('ID');

            $table->date('datum');

            // Fremdschlüssel zu zuschlag
            $table->unsignedBigInteger('fk_zuschlagID');
            $table->foreign('fk_zuschlagID')
                ->references('ID')
                ->on('zuschlag');

            //$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('feiertag');
    }
};
