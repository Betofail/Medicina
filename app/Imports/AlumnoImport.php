<?php

namespace App\Imports;

use App\Alumno;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use App\Periodo;
use App\Carrera;
use App\Model\User;
use Maatwebsite\Excel\Concerns\ToCollection;

class AlumnoImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $periodo = $collection[7][2];
        $periodo = str_replace(':','',trim($periodo));
        $periodo = trim($periodo);

        $nombre_periodo = trim($collection[7][4]);

        $carrera = str_replace(':','',trim($collection[11][2]));
        $nombre_carrera = trim($collection[11][4]);
        //carrera
        if(Carrera::where('idCarrera','=',$carrera)->count() > 0){
        }
        else{
            Carrera::create([
                'idCarrera' => $carrera,
                'nombre' => $nombre_carrera
            ]);
        }
        //periodo de la carrera
        if (Periodo::where('idPeriodo','=',$periodo)->count() > 0) {
        }
        else{
            if ($collection[8][4] == 'ACTIVO') {
                $pe_doc = Periodo::create([
                    'idPeriodo' => $periodo,
                    'descripcion' => $nombre_periodo,
                    'estado' => 2
                ]);
                $pe_doc->save();
            }else{
                $pe_doc = Periodo::create([
                    'idPeriodo' => $periodo,
                    'descripcion' => $nombre_periodo,
                    'estado' => 0
                ]);
            }
        }

        //alumnos
        $chunk = $collection->splice(21);
        foreach ($chunk as $key => $value) {
            if (Alumno::where('rut','=',$value[1])->count() > 0) {
                continue;
            }else{
                if ($value[27] == 'S/C') {
                    $alum = Alumno::create([
                        'rut' => $value[1],
                        'nombre' => $value[2],
                        'email' => $value[28],
                        'idCarrera' => $carrera
                    ]);
                    $alum->save();
                    $user = User::create([
                        'name' => $value[2],
                        'email' => $value[28],
                        'tipo' => 'alumno',
                        'password' => Hash::make(substr($value[1],0,4))
                    ]);
                    $user->save();
                }else{
                    $alum =Alumno::create([
                        'rut' => $value[1],
                        'nombre' => $value[2],
                        'email' => $value[27],
                        'idCarrera' => $carrera
                    ]);
                    $alum->save();
                    $user = User::create([
                        'name' => $value[2],
                        'email' => $value[27],
                        'tipo' => 'alumno',
                        'password' => Hash::make(substr($value[1],0,4))
                    ]);
                    $user->save();
                }
            }
        }
    }
}
