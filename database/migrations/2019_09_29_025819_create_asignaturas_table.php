<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAsignaturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql3')->create('asignaturas', function (Blueprint $table) {
            $table->bigIncrements('idAsignatura');
            $table->string('nrcAsignatura');
            $table->string('codigo_asignatura');
            $table->string('nombre');
            $table->string('idCarrera')->nullable();
            $table->string('semestre')->nullable();
            $table->string('sede')->nullable();
            $table->tinyInteger('confirmacion_semestre')->nullable();
            $table->string('actividad');
            $table->string('LCruzada')->nullable();
            $table->longText('Liga')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asignaturas');
    }
}
