<?php

namespace App\Imports;

use App\Model\Asignatura;
use App\Model\Carrera;
use App\Model\Periodo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class AsignaturaImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        $chunk = $collection->splice(5);
        $periodo = str_replace(':', '', trim($chunk[0][2]));
        $periodo = substr($periodo, 1);

        $nombre_periodo = trim($chunk[0][4]);
        $carrera = str_replace(':', '', trim($chunk[2][2]));
        $carrera = preg_replace('/[^A-Za-z0-9\-]/', '', $carrera);
        $nombre_carrera = trim($chunk[2][4]);

        if (0 == Periodo::where('idPeriodo', '=', $periodo)->count()) {
            $periodo_doc = Periodo::create([
                'idPeriodo' => $periodo,
                'descripcion' => $nombre_periodo,
                'estado' => 0,
            ]);
            $periodo_doc->save();
        }

        if (0 == Carrera::where('codigo_carrera', '=', $carrera)->count()) {
            $carrera_doc = Carrera::create([
                'codigo_carrera' => $carrera,
                'nombre' => $nombre_carrera,
                ]);
            $carrera_doc->save();
        }

        $chunk = $chunk->splice(6);

        foreach ($chunk as $key => $value) {
            if (0 == Asignatura::where('idAsignatura', '=', $value[8])->where('actividad', '=', $value[18])->count()) {
                if ($value[6] < 5) {
                    if (1 == strlen($value[5])) {
                        $codigo_asig = $value[4].'00'.$value[5];
                    } elseif (2 == strlen($value[5])) {
                        $codigo_asig = $value[4].'0'.$value[5];
                    } elseif (strlen($value[5]) >= 3) {
                        $codigo_asig = $value[4].$value[5];
                    }

                    $liga = str_replace(' ', '', $value[10]);

                    $asignatura = Asignatura::Create([
                        'nrcAsignatura' => $value[8],
                        'codigo_asignatura' => $codigo_asig,
                        'nombre' => $value[16],
                        'idCarrera' => $carrera,
                        'semestre' => $periodo,
                        'confirmacion_semestre' => 2,
                        'sede' => $value[1],
                        'Liga' => $liga,
                        'LCruzada' => $value[11],
                        'actividad' => $value[18],
                    ]);

                    $asignatura->save();
                }
            }
        }
    }
}
