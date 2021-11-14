<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCampusSeccionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql3')->create('campus_seccions', function (Blueprint $table) {
            $table->bigIncrements('idCampus_clinico');
            $table->unsignedBigInteger('alumno_seccion');
            $table->unsignedBigInteger('rotacion');
            $table->unsignedBigInteger('seccion_semestre');
            $table->string('link_encuesta');
            $table->string('profesor_seccion');
            $table->integer('nrc');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->string('res_encuesta')->nullable();
            $table->string('entrega_rubrica')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('campus_seccions');
    }
}
