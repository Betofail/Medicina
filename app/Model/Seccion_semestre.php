<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Seccion_semestre extends Model
{
    protected $connection = 'mysql3';
    public $timestamps = false;
    protected $fillable = ['idAsignatura', 'idDocente', 'idPeriodo', 'link_encuesta', 'nrc', 'actividad'];
}
