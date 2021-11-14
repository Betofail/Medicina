<?php

namespace App\Imports;

use App\Model\Carrera;
use App\Model\Malla;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class MallaImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        $chunk = $collection->slice(5);
        $programa = str_replace('-', '', $chunk[5][1]);
        $programa = explode(' ', $programa);
        $nombre = trim($programa[2]);
        $programa = trim($programa[0]);
        $programa = preg_replace('/[^A-Za-z0-9\-]/', '', $programa);
        $catalogo = trim($chunk[6][1]);
        $chunk = $chunk->slice(5);

        $malla = DB::connection('mysql3')->table('mallas')->update(
            ['Vigente' => 0]
        );
        if (0 == Carrera::where('codigo_carrera', '=', $programa)->count()) {
            $carrera = Carrera::create([
                'nombre' => $nombre,
                'codigo_carrera' => $programa,
            ]);

            $carrera->save();
        }

        foreach ($chunk as $key => $value) {
            if (null == $value[0] or null == $value[1]) {
                continue;
            } elseif ('Asignatura' == $value[0] and 'Nombre Asignatura' == $value[1]) {
                continue;
            } else {
                if ((int) $value[2] > 0 and (int) $value[7] > 0) {
                    if (Malla::where('CodAsign', '=', $value[0])->count() > 0) {
                        Malla::where('CodAsign', $value[0])->update(['Vigente' => 1]);
                    } else {
                        $malla = Malla::create([
                            'CodAsign' => $value[0],
                            'Nombre' => $value[1],
                            'Encuesta' => 0,
                            'CodCarrera' => $programa,
                            'PeriodoCatalogo' => $catalogo,
                            'Vigente' => 1,
                            'CampusClinico' => 1,
                        ]);
                        $malla->save();
                    }
                } elseif ((int) $value[2] > 0) {
                    if (Malla::where('CodAsign', '=', $value[0])->count() > 0) {
                        Malla::where('CodAsign', $value[0])->update(['Vigente' => 1]);
                    } else {
                        $malla = Malla::create([
                            'CodAsign' => $value[0],
                            'Nombre' => $value[1],
                            'Encuesta' => 0,
                            'CodCarrera' => $programa,
                            'PeriodoCatalogo' => $catalogo,
                            'Vigente' => 1,
                            'CampusClinico' => 0,
                        ]);
                        $malla->save();
                    }
                } else {
                    $contador = 3;
                    while (true) {
                        if ((int) $value[$contador] > 0 and 7 == $contador) {
                            if (Malla::where('CodAsign', '=', $value[0])->count() > 0) {
                                Malla::where('CodAsign', $value[0])->update(['Vigente' => 1]);
                                break;
                            } else {
                                $malla = Malla::create([
                                    'CodAsign' => $value[0],
                                    'Nombre' => $value[1],
                                    'Encuesta' => 0,
                                    'CodCarrera' => $programa,
                                    'PeriodoCatalogo' => $catalogo,
                                    'Vigente' => 1,
                                    'CampusClinico' => 1,
                                ]);
                                $malla->save();
                                break;
                            }
                        } elseif ((int) $value[$contador] > 0) {
                            if (Malla::where('CodAsign', '=', $value[0])->count() > 0) {
                                Malla::where('CodAsign', $value[0])->update(['Vigente' => 1]);
                                break;
                            } else {
                                $malla = Malla::create([
                                    'CodAsign' => $value[0],
                                    'Nombre' => $value[1],
                                    'Encuesta' => 0,
                                    'CodCarrera' => $programa,
                                    'PeriodoCatalogo' => $catalogo,
                                    'Vigente' => 1,
                                    'CampusClinico' => 0,
                                ]);
                                $malla->save();
                                break;
                            }
                        } else {
                            if ($contador >= 15) {
                                break;
                            }
                        }
                        ++$contador;
                    }
                }
            }
        }
    }
}
