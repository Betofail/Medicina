<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    protected $connection = 'mysql3';
    public $timestamps = false;
    protected $fillable = ['idAsignatura', 'codigo_asignatura', 'nombre', 'idCarrera', 'semestre', 'confirmacion_semestre', 'sede', 'actividad', 'LCruzada', 'Liga'];
}
