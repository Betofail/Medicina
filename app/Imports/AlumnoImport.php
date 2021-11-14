<?php

namespace App\Imports;

use App\Model\Alumno;
use App\Model\Carrera;
use App\Model\Periodo;
use App\Model\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;

class AlumnoImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        $periodo = $collection[7][2];
        $periodo = str_replace(':', '', trim($periodo));
        $periodo = trim($periodo);

        $nombre_periodo = trim($collection[7][4]);

        $carrera = str_replace(':', '', trim($collection[11][2]));
        $carrera = preg_replace('/[^A-Za-z0-9\-]/', '', $carrera);
        $nombre_carrera = trim($collection[11][4]);
        //carrera

        if (Carrera::where('codigo_carrera', '=', $carrera)->count() > 0) {
            $carreraActual = Carrera::firstWhere('codigo_carrera', '=', $carrera);
        } else {
            $carreraActual = Carrera::create([
                'codigo_carrera' => $carrera,
                'nombre' => $nombre_carrera,
            ]);
            $carreraActual->save();
        }

        //periodo de la carrera
        if (Periodo::where('idPeriodo', '=', $periodo)->count() > 0) {
        } else {
            if ('ACTIVO' == $collection[8][4]) {
                $pe_doc = Periodo::create([
                    'idPeriodo' => $periodo,
                    'descripcion' => $nombre_periodo,
                    'estado' => 2,
                ]);
                $pe_doc->save();
            } else {
                $pe_doc = Periodo::create([
                    'idPeriodo' => $periodo,
                    'descripcion' => $nombre_periodo,
                    'estado' => 0,
                ]);
            }
        }

        //alumnos
        $chunk = $collection->splice(21);
        foreach ($chunk as $key => $value) {
            if (Alumno::where('rut', '=', $value[1])->count() > 0) {
                continue;
            } else {
                if ('S/C' == $value[27]) {
                    $alum = Alumno::create([
                        'rut' => $value[1],
                        'nombre' => $value[2],
                        'email' => $value[28],
                        'idCarrera' => $carreraActual->idCarrera,
                    ]);
                    $alum->save();
                    $user = User::create([
                        'name' => $value[2],
                        'email' => $value[28],
                        'tipo' => 'alumno',
                        'password' => Hash::make(substr($value[1], 0, 4)),
                    ]);
                    $user->save();
                } else {
                    $alum = Alumno::create([
                        'rut' => $value[1],
                        'nombre' => $value[2],
                        'email' => $value[27],
                        'idCarrera' => $carreraActual->idCarrera,
                    ]);
                    $alum->save();
                    $user = User::create([
                        'name' => $value[2],
                        'email' => $value[27],
                        'tipo' => 'alumno',
                        'password' => Hash::make(substr($value[1], 0, 4)),
                    ]);
                    $user->save();
                }
            }
        }
    }
}
