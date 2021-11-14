<?php

namespace App\Imports;

use App\Model\Alumno_seccion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class SeccionImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        $chunk = $collection->splice(8);
        $nombre = str_replace(':', '', $chunk[5][1]);
        $nombre = explode(' ', $nombre);

        $nrc = str_replace(':', '', $chunk[3][1]);
        $nrc = str_replace(' ', '', $nrc);
        $query = DB::connection('mysql3')->table('docentes')->select('docentes.rut')->join('seccion_semestres', 'seccion_semestres.idDocente', '=', 'docentes.rut')
        ->where([['docentes.nombre', 'like', '%'.$nombre[1].'%'],
        ['seccion_semestres.nrc', '=', $nrc], ])->first();

        foreach ($chunk as $key => $value) {
            if (null == $value[3]) {
                continue;
            } else {
                if ('RUT' == $value[1]) {
                    continue;
                } else {
                    Alumno_seccion::create([
                        'rut_alumno' => $value[1],
                        'nrc' => $nrc,
                        'resp_encuesta' => 'none',
                        'idDocente' => $query->rut,
                        'entrega_rubrica' => 0,
                    ]);
                }
            }
        }
    }
}
