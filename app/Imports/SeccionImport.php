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
        $nrc = (int) str_replace(' ', '', $nrc);
        $query = DB::connection('mysql3')->table('docentes')->select('docentes.rut')
        ->join('seccion_semestres', 'seccion_semestres.idDocente', '=', 'docentes.rut')
        ->where([['docentes.nombre', 'like', '%'.$nombre[1].'%'],
        ['seccion_semestres.nrc', '=', $nrc], ])->first();

        $validator = DB::connection('mysql3')->table('alumno_seccions')
        ->select('alumno_seccions.idSeccion')->where([['alumno_seccions.nrc', '=', $nrc]])->count();

        if (0 == $validator) {
            foreach ($chunk as $key => $value) {
                if (null != $value[3] && null != $query) {
                    if ('RUT' != $value[1]) {
                        $seccion = Alumno_seccion::create([
                        'rut_alumno' => $value[1],
                        'nrc' => $nrc,
                        'resp_encuesta' => 'none',
                        'idDocente' => $query->rut,
                        'entrega_rubrica' => 0,
                    ]);
                        $seccion->save();
                    }
                }
            }
        }
    }
}
