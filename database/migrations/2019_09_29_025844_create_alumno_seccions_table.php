<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlumnoSeccionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql3')->create('alumno_seccions', function (Blueprint $table) {
            $table->bigIncrements('idSeccion');
            $table->string('rut_alumno');
            $table->integer('nrc');
            $table->string('resp_encuesta');
            $table->integer('idDocente');
            $table->boolean('entrega_rubrica');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alumno_seccions');
    }
}
