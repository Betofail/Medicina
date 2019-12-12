<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Malla;
use Maatwebsite\Excel\Concerns\ToCollection;

class MallaImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $chunk = $collection->slice(5);
        $programa = str_replace('-','',$chunk[5][1]);
        $programa = explode(" ",$programa);
        $programa = trim($programa[0]);
        $catalogo = trim($chunk[6][1]);
        $chunk = $chunk->slice(5);

        $malla = DB::connection('mysql3')->table('mallas')->update(
            ['Vigente' => 0]
        );

        foreach ($chunk as $key => $value) {
            if($value[0] == null or $value[1] == null){
                continue;
            }elseif ($value[0] == "Asignatura" and $value[1] == "Nombre Asignatura") {
                continue;
            }
            else{
                if((int)$value[2] > 0 and (int)$value[7] > 0){
                    if(Malla::where('CodAsign','=',$value[0])->count() > 0){
                        Malla::where('CodAsign',$value[0])->update(['Vigente' => 1]);
                    }else{
                        $malla = Malla::create([
                            'CodAsign' => $value[0],
                            'Nombre' => $value[1],
                            'Encuesta' => 0,
                            'CodCarrera' => $programa,
                            'PeriodoCatalogo' => $catalogo,
                            'Vigente' => 1,
                            'CampusClinico' => 1
                        ]);
                        $malla->save();
                    }
                }elseif((int)$value[2] > 0){
                    if(Malla::where('CodAsign','=',$value[0])->count() > 0){
                        Malla::where('CodAsign',$value[0])->update(['Vigente' => 1]);
                    }else{
                        $malla = Malla::create([
                            'CodAsign' => $value[0],
                            'Nombre' => $value[1],
                            'Encuesta' => 0,
                            'CodCarrera' => $programa,
                            'PeriodoCatalogo' => $catalogo,
                            'Vigente' => 1,
                            'CampusClinico' => 0
                        ]);
                        $malla->save();
                    }
                }
                else{
                    $contador = 3;
                    while(true){
                        if((int)$value[$contador] > 0 and $contador == 7){
                            if(Malla::where('CodAsign','=',$value[0])->count() > 0){
                                Malla::where('CodAsign',$value[0])->update(['Vigente'=> 1]);
                                break;
                            }else{
                                $malla = Malla::create([
                                    'CodAsign' => $value[0],
                                    'Nombre' => $value[1],
                                    'Encuesta' => 0,
                                    'CodCarrera' => $programa,
                                    'PeriodoCatalogo' => $catalogo,
                                    'Vigente' => 1,
                                    'CampusClinico' => 1
                                ]);
                                $malla->save();
                                break;
                            }
                        }elseif((int)$value[$contador] > 0){
                            if(Malla::where('CodAsign','=',$value[0])->count() > 0){
                                Malla::where('CodAsign',$value[0])->update(['Vigente'=> 1]);
                                break;
                            }else{
                                $malla = Malla::create([
                                    'CodAsign' => $value[0],
                                    'Nombre' => $value[1],
                                    'Encuesta' => 0,
                                    'CodCarrera' => $programa,
                                    'PeriodoCatalogo' => $catalogo,
                                    'Vigente' => 1,
                                    'CampusClinico' => 0
                                ]);
                                $malla->save();
                                break;
                            }
                        }
                        else{
                            if($contador >= 15){
                                break;
                            }
                        }
                        $contador++;
                    }
                }
            }

        }
    }
}
