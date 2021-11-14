<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMallasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql3')->create('mallas', function (Blueprint $table) {
            $table->bigIncrements('idMalla');
            $table->string('CodAsign', 50)->unique();
            $table->string('Nombre', 100);
            $table->boolean('Encuesta');
            $table->string('CodCarrera', 50);
            $table->string('PeriodoCatalogo', 100);
            $table->boolean('Vigente');
            $table->boolean('CampusClinico');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mallas');
    }
}
