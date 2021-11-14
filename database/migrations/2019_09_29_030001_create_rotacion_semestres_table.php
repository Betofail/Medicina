<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRotacionSemestresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql3')->create('rotacion_semestres', function (Blueprint $table) {
            $table->integer('idRotacion');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->unsignedBigInteger('idCampus');
            $table->unsignedBigInteger('idHospital')->nullable();
            $table->integer('cupos');
            $table->unsignedBigInteger('idPeriodo');
            $table->date('fecha_inicio_encuesta')->nullable();
            $table->date('fecha_termino_encuesta')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rotacion_semestres');
    }
}
