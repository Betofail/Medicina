<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $connection = 'mysql3';
    public $timestamps = false;
    protected $fillable = ['idCarrera', 'nombre', 'codigo_carrera'];
}
