<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Alumno_seccion extends Model
{
    protected $connection = 'mysql3';
    protected $fillable = ['rut_alumno', 'nrc', 'resp_encuesta', 'idDocente', 'entrega_rubrica'];
    public $timestamps = false;
}
