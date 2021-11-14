<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeccionSemestresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql3')->create('seccion_semestres', function (Blueprint $table) {
            $table->bigIncrements('idSeccion');
            $table->string('idAsignatura')->nullable();
            $table->string('idPeriodo')->nullable();
            $table->string('idDocente')->nullable();
            $table->string('link_encuesta')->nullable();
            $table->integer('nrc');
            $table->date('fecha_inicio_encuesta')->nullable();
            $table->date('fecha_termino_encuesta')->nullable();
            $table->string('actividad')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seccion_semestres');
    }
}
