<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $connection = 'mysql3';
    protected $fillable = ['rut', 'nombre', 'email'];
    public $timestamps = false;
}
