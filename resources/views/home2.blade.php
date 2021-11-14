@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col">
            <div class="card text-center">
                <div class="card-header">
                    <div class="dropdown d-inline-block ">
                        <button class="btn btn-default dropdown-toggle" type="button" id="periodo_dropdown"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Resumen Periodo {{$code_periodo}}
                        </button>
                        <div class="dropdown-menu" aria-labelledby="periodo_dropdown">
                            @if($tipo == 'PA')
                            @foreach($periodos as $key=>$value)
                            <a class="dropdown-item"
                                href="{{route('periodo_pa',['id'=>$value->idPeriodo])}}">{{$value->descripcion}}</a>
                            @endforeach

                            @elseif($tipo == 'SA')
                            @foreach($periodos as $key=>$value)
                            <a class="dropdown-item"
                                href="{{route('periodo_sa',['id'=>$value->idPeriodo])}}">{{$value->descripcion}}</a>
                            @endforeach

                            @elseif($tipo == "docente")
                            @foreach($periodos as $key=>$value)
                            <a class="dropdown-item"
                                href="{{route('periodo_doc',['id'=>$value->idPeriodo])}}">{{$value->descripcion}}</a>
                            @endforeach
                            @endif
                        </div>
                    </div>
                    @if($tipo == 'SA' or $tipo == 'OFEM')
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-default dropdown-toggle" type="button" id="carrera_dropdown"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> Carrera {{$code_carrera}}
                        </button>
                        <div class="dropdown-menu" aria-labelledby="carrera_dropdown">
                            @if($tipo == 'SA')
                            @foreach ($carreras as $key => $value)
                            <a class="dropdown-item"
                                href="{{route('carrera_sa',['id'=>$value->codigo_carrera])}}">{{$value->nombre}}</a>
                            @endforeach
                            @elseif($tipo == 'OFEM')
                            @foreach ($carreras as $key => $value)
                            <a class="dropdown-item"
                                href="{{route('carrera_ofem',['id'=>$value->codigo_carrera])}}">{{$value->nombre}}</a>
                            @endforeach
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Asignaturas Teoricas  -->
                <div class="card-body">
                    @if($asignaturas->isEmpty())
                    <h2>Asignaturas Teoricas (Sin Carga Académica)</h2>
                    <br>
                    @else
                    <table class="table table-hover" width="100%" cellspacing="0">
                        <h2>Asignaturas Teoricas</h2>
                        <thead>
                            @if($tipo == 'alumno')
                            <th>NRC Asignatura</th>
                            <th>Asignatura</th>
                            <th>Docente</th>
                            <th>Inicio Encuesta</th>
                            <th>Termino Encuesta</th>
                            <th>Actividad</th>
                            <th>Encuesta</th>
                            @else
                            <th>NRC Asignatura</th>
                            <th>Asignatura</th>
                            <th>Docente</th>
                            <th>Inicio Encuesta</th>
                            <th>Termino Encuesta</th>
                            <th>Encuesta</th>
                            <th>Actividad</th>
                            <th>Alumnos</th>
                            <th>% respuestas</th>
                            @endif
                        </thead>
                        <tbody>
                        @foreach($asignaturas as $key => $value)
                            <tr>
                                <td>{{$value->nrc}}</td>
                                <td>{{$value->nombre}}</td>
                                <td>{{$value->doc_nom}}</td>
                                <td>{{$value->fecha_inicio_encuesta}}</td>
                                <td>{{$value->fecha_termino_encuesta}}</td>
                                <td>{{$value->actividad}}</td>

                        @if($tipo == 'alumno')
                            @if($value->link_encuesta == "none")
                                <td>none</td>
                            @else
                                @if(empty($estados))
                                    <td><a href="{{action('home2@encuesta',['url' =>$value->link_encuesta])}}"
                                        target="_blank">{{substr($value->link_encuesta,-6)}}</a></td>
                                @else
                                    @foreach($estados as $key_st => $value_st)
                                        @if($value_st['nrc'] == $value->nrc && $value_st['estado'] == "N")
                                            <td><a href="{{action('home2@encuesta',['url' =>$value->link_encuesta])}}"
                                            target="_blank">Encuesta: {{substr($value->link_encuesta,-6)}}</a></td>
                                            @break
                                        @elseif($value_st['nrc'] == $value->nrc && $value_st['estado'] != "N")
                                            <td>Completado</td>
                                            @break
                                        @elseif($value_st['nrc'] != $value->nrc)
                                            <td><a href="{{action('home2@encuesta',['url' =>$value->link_encuesta])}}"
                                            target="_blank">Encuesta: {{substr($value->link_encuesta,-6)}}</a></td>
                                            @break
                                        @endif
                                    @endforeach
                                @endif
                            @endif
                            @else
                                <td>Encuesta: {{substr($value->link_encuesta,-6)}}</td>
                                @if($cantidad_teoricos->isEmpty())
                                        <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#modal{{$key}}">
                                        <i class="fa fa-address-book" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>0</span></Button></td>

                                    @if($respuestas_teoricas->isEmpty())
                                        <td>0</td>
                                    @else
                                        @foreach($respuestas_teoricas as $key_resp => $val_resp)
                                            @if($val_resp->nrc == $cant_val->nrc && $cant_val->actividad == $val_resp->actividad)
                                            <td>{{number_format(($respuestas_teoricas[$key_resp]->resp_encuesta/$cant_val->cantidad_seccion) * 100,2)}}
                                            </td>
                                             @else

                                            @endif
                                        @endforeach
                                    @endif

                                @elseif($tipo == 'alumno')

                                @else
                                    @if($cantidad_teoricos->contains('nrc',$value->nrc))
                                        @foreach($cantidad_teoricos as $cant_key => $cant_val)
                                            @if($cant_val->nrc == $value->nrc && $cant_val->actividad == $value->actividad)
                                                @foreach ($contador_registros as $res_key => $res_val)
                                                    @if($cant_val->nrc == $res_val->nrc && $cant_val->actividad == $res_val->actividad)
                                                        <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                                            data-target="#modal{{$key}}">
                                                        <i class="fa fa-address-book" style="font-size: 16px" aria-hidden="true"></i>
                                                        <span>{{$cant_val->cantidad_seccion / $res_val->registros}}</span></Button></td>
                                                    @else

                                                    @endif
                                                @endforeach

                                            @if($respuestas_teoricas->isEmpty())
                                                <td>0</td>

                                            @else
                                                @foreach($respuestas_teoricas as $key_resp => $val_resp)
                                                    @if($val_resp->nrc == $cant_val->nrc and $cant_val->actividad == $val_resp->actividad)
                                                        <td>{{round(($respuestas_teoricas[$key_resp]->resp_encuesta/$cant_val->cantidad_seccion) * 100)}}
                                                        </td>
                                                    @elseif($val_resp->nrc != $cant_val->nrc)
                                                        <td>0</td>
                                                        @break
                                                    @else
                                                        <td>0</td>
                                                        @break
                                                    @endif
                                                @endforeach
                                            @endif

                                    @else
                                        @continue
                                    @endif
                                    @endforeach
                                @else
                                    <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#modal{{$key}}">
                                        <i class="fa fa-address-book" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>0</span></Button></td>

                                @if($respuestas_teoricas->isEmpty())
                                <td>0</td>

                                @else
                                @foreach($respuestas_teoricas as $key_resp => $val_resp)
                                @if($val_resp->nrc == $cant_val->nrc and $cant_val->actividad === $val_resp->actividad)
                                <td>{{round(($respuestas_teoricas[$key_resp]->resp_encuesta/$cant_val->cantidad_seccion) * 100)}}
                                </td>
                                @else
                                <td>0</td>
                                @break
                                @endif
                                @endforeach
                                @endif
                                @endif
                                @endif
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif

                    <!-- Fin Asignaturas Teoricas  -->

                    <!--Asignaturas Campus Clinicos -->

                    @if($campus_clinico->isEmpty())
                    <h2>Campus Clinicos (Sin Carga Académica)</h2>
                    @else
                    <table class="table table-responsive" width="100%" cellspacing="0">
                        <h2>Campus Clinicos</h2>
                        <thead>
                            @if($tipo == 'alumno')
                            <th>NRC Asignatura</th>
                            <th>Nombre Asignatura</th>
                            <th>Rotaciones</th>
                            @else
                            <th>NRC Asignatura</th>
                            <th>Nombre Asignatura</th>
                            <th>Alumnos</th>
                            <th>Docentes</th>
                            <th>Rotaciones</th>
                            <th>% respuesta</th>
                            <th>% entrega rubrica</th>
                            @endif
                        </thead>

                        <tbody>
                            @foreach($campus_clinico as $key => $value)
                            <tr>
                                <td>{{$value->nrc}}</td>
                                <td>{{$value->nombre_asignatura}}</td>
                                @if($tipo == 'docente' or $tipo == 'SA' or $tipo == 'PA' or $tipo == 'OFEM')
                                @if($contador_alumnos_clinicos->isEmpty())
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_alu{{$key}}">
                                        <i class="fa fa-address-book" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>0</span></Button></td>
                                @else
                                @foreach ($contador_alumnos_clinicos as $item => $val_al)
                                @if($val_al->Liga == '/'.$value->nrc)
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_alu{{$key}}">
                                        <i class="fa fa-address-book" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>{{$val_al->cant_alumnos_cli}}</span></Button>
                                </td>
                                @else

                                @endif
                                @endforeach
                                @endif
                                @if($contador_docentes_clinicos->isEmpty())
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_doc{{$key}}">
                                        <i class="fa fa-users" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>0</span></Button></td>
                                @else
                                @foreach ($contador_docentes_clinicos as $item => $val_dc)
                                @if($val_dc->nrc == $value->nrc)
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_doc{{$key}}">
                                        <i class="fa fa-users" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>{{$val_dc->cant_profesor}}</span></Button></td>
                                @elseif(!$contador_docentes_clinicos->contains('nrc',$value->nrc))
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_doc{{$key}}">
                                        <i class="fa fa-users" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>0</span></Button></td>
                                @break
                                @endif
                                @endforeach
                                @endif
                                <td data-toggle="collapse" data-target="#acrodion{{$key}}" class="accordion-toggle">
                                    <button class="btn btn-default"><i class="fa fa-arrow-circle-down"></i></button>
                                </td>
                                @if($respuestas_clinicas->isEmpty())
                                <td>0</td>
                                @else
                                @foreach($respuestas_clinicas as $cli_key => $cli_val)
                                @if($cli_val->nrc == $value->nrc)
                                <td>{{number_format(($cli_val->resp_encuesta/$contador_alumnos_clinicos[$key]->cant_alumnos_cli)*100,2)}}
                                </td>
                                @else

                                @endif
                                @endforeach
                                @endif

                                @if($entrego_rubrica->isEmpty())
                                <td>0</td>
                                @else
                                @foreach($entrego_rubrica as $cli_ent_key => $cli_ent_val)
                                @if($cli_ent_val->nrc == $value->nrc)
                                <td>{{number_format(($cli_ent_val->entrego_rubrica / $contador_alumnos_clinicos[$key]->cant_alumnos_cli)*100,2)}}
                                </td>
                                @else

                                @endif
                                @endforeach
                                @endif
                                @else
                                @if($tipo == 'alumno')

                                <td data-toggle="collapse" data-target="#acrodion{{$key}}" class="accordion-toggle">
                                    <button class="btn btn-default"><i class="fa fa-arrow-circle-down"></i></button>
                                </td>
                                @else
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_alu{{$key}}">
                                        <i class="fa fa-address-book" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>{{$contador_alumnos_clinicos[$key]->cant_alumnos_cli}}</span></Button>
                                </td>
                                <td><Button type="submit" class="btn btn-lg" data-toggle="modal"
                                        data-target="#campus_doc{{$key}}">
                                        <i class="fa fa-users" style="font-size: 16px" aria-hidden="true"></i>
                                        <span>{{$contador_docentes_clinicos[$key]->cant_profesor}}</span></Button></td>
                                <td data-toggle="collapse" data-target="#acrodion{{$key}}" class="accordion-toggle">
                                    <button class="btn btn-default"><i class="fa fa-arrow-circle-down"></i></button>
                                </td>

                                @endif
                                @if($tipo == 'alumno')

                                @else
                                @if($respuestas_clinicas->isEmpty())
                                <td>0</td>
                                @else
                                @foreach($respuestas_clinicas as $key_resp_cli => $val_resp_cli)
                                @if($val_resp_cli->nrc == $value->nrc)
                                <td>{{number_format(($respuestas_clinicas[$key]->resp_encuesta/$contador_alumnos_clinicos[$key]->cant_alumnos_cli)*100,2)}}
                                </td>
                                @else

                                @endif
                                @endforeach
                                @endif
                                @endif
                                @endif

                            </tr>
                            <tr>
                                <td colspan="5" class="hiddenRow">
                                    <div class="table-responsive collapse" id="acrodion{{$key}}">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    @if($tipo == 'alumno')
                                                    <th>NRC</th>
                                                    <th>N° Rotacion</th>
                                                    <th>Asignatura</th>

                                                    <th>Fecha inicio</th>
                                                    <th>Fecha termino</th>
                                                    <th>docente a cargo</th>
                                                    <th>N° Alumnos</th>
                                                    <th>Encuesta</th>
                                                    @else
                                                    <th>NRC</th>
                                                    <th>N° Rotacion</th>
                                                    <th>Asignatura</th>
                                                    <th>Fecha inicio</th>
                                                    <th>Fecha termino</th>
                                                    <th>docente a cargo</th>
                                                    <th>N° Alumnos</th>
                                                    <th>% respuesta</th>
                                                    <th>% entrega rubrica</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rotaciones as $key_rot => $value_rot)
                                                @if($value_rot->Liga == '/'.$value->nrc)
                                                <tr>
                                                    <td>{{$value_rot->nrc}}</td>
                                                    <td>{{$value_rot->actividad}}</td>
                                                    <td>{{$value_rot->nombre_asignatura}}</td>
                                                    <td>{{$value_rot->fecha_inicio_encuesta}}</td>
                                                    <td>{{$value_rot->fecha_termino_encuesta}}</td>
                                                    <td>{{$value_rot->nombre}}</td>

                                                    @if($tipo == 'alumno')
                                                    <td>{{$value_rot->link_encuesta}}</td>
                                                    @else
                                                    @if($contador_alumnos_rotacion->isEmpty())
                                                    <td>0</td>
                                                    @else
                                                    <td>{{$contador_alumnos_rotacion[$key]->cantidad_alumno}}</td>
                                                    @endif
                                                    @if($respuestas_rotaciones->isEmpty())
                                                    <td>0</td>
                                                    @else
                                                    @foreach($respuestas_rotaciones as $resp_key => $resp_value)
                                                    @if($resp_value->rotacion == '/'.$value_rot->nrc)
                                                    <td>{{number_format(($resp_value->resp_encuesta / $value_rot->numero_alumno) * 100,2)}}
                                                    </td>
                                                    @else

                                                    @endif
                                                    @endforeach
                                                    @endif
                                                    @if($rotaciones_rubrica->isEmpty())
                                                    <td>0</td>
                                                    @else
                                                    @foreach($rotaciones_rubrica as $rot_rub_key => $rot_rub_value)
                                                    @if($rot_rub_value->rotacion == '/'.$value_rot->nrc)
                                                    <td>{{number_format(($rot_rub_value->entrego_rubrica / $value_rot->numero_alumno) * 100,2)}}
                                                    </td>
                                                    @else

                                                    @endif
                                                    @endforeach
                                                    @endif
                                                    @endif
                                                </tr>
                                                @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!--Fin Asignaturas Campus Clinicos -->

        @if($tipo =='alumno' or $tipo == 'docente')

        @else
        @foreach($asignaturas as $key => $value)
        <!-- Modal Alumnos Teoricos-->
        <div id="modal{{$key}}" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Alumnos</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-responsive">
                            <thead>
                                <th>Rut</th>
                                <th>Nombre Completo</th>
                                <th>Encuesta S/N</th>
                            </thead>
                            <tbody>
                                @foreach($alumnos_teoricos as $key_alum => $value_alum)
                                @if($value_alum->nrc == $value->nrc && $value_alum->actividad == $value->actividad)
                                <tr>
                                    <td>{{$value_alum->rut}}</td>
                                    <td>{{$value_alum->nombre}}</td>
                                    <td>{{$value_alum->resp_encuesta}}</td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        @foreach($campus_clinico as $key => $value)
        <!-- Modal Campus Clinico Alumnos -->
        <div id="campus_alu{{$key}}" class="modal fade" role="dialog">
            <div class="modal-dialog modal-sm">
                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Alumnos</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-responsive">
                            <thead>
                                <th>Rut</th>
                                <th>Nombre Completo</th>
                            </thead>
                            <tbody>
                                @foreach($alumnos_clinicos as $key_alum => $value_alum)
                                @if($value_alum->nrc == $value->nrc)
                                <tr>
                                    <td>{{$value_alum->rut}}</td>
                                    <td>{{$value_alum->nombre}}</td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        @foreach($campus_clinico as $key => $value)
        <!-- Modal Campus Clinico Docentes -->
        <div id="campus_doc{{$key}}" class="modal fade" role="dialog">
            <div class="modal-dialog modal-sm">
                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Docente</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-responsive">
                            <thead>
                                <th>Rut</th>
                                <th>Nombre Completo</th>
                            </thead>
                            <tbody>
                                @foreach($docentes_clinicos as $key_doc => $value_doc)
                                @if($value_doc->nrc == $value->nrc)
                                <tr>
                                    <td>{{$value_doc->idDocente}}</td>
                                    <td>{{$value_doc->nombre}}</td>

                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

    </div>
</div>
@endif

@endsection
