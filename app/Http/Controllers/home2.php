<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class home2 extends Controller
{
    private $rut;
    private $periodos;
    private $date;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        define('LS_BASEURL', 'http://limesurvey.test/index.php');  // adjust this one to your actual LimeSurvey URL
        define('LS_USER', 'Alberto');
        define('LS_PASSWORD', 'master12');

        $periodos = DB::connection('mysql3')->table('periodos')
            ->where('estado', '>', '1')->get()->toArray();

        if (empty($periodos[0]->idPeriodo)) {
            $this->periodos = '';
        } else {
            $this->periodos = $periodos[0]->idPeriodo;
        }
        $user = Auth::user()->email;
        $tipo = DB::connection('mysql3')->table('users')->where('email', $user)->value('tipo');

        if ('alumno' == $tipo) {
            //datos para alumnos
            $this->rut = DB::connection('mysql3')->table('alumnos')->where('email', $user)->value('rut');
            $asignaturas = DB::connection('mysql3')->table('alumno_seccions')
                ->join('seccion_semestres', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->join('asignaturas', 'alumno_seccions.nrc', '=', 'asignaturas.idAsignatura')
                ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->where([['alumno_seccions.rut_alumno', '=', $this->rut], ['asignaturas.Liga', '=', '']])
                ->select(
                    'seccion_semestres.nrc',
                    'asignaturas.nombre',
                    'docentes.nombre as doc_nom',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta',
                    'seccion_semestres.actividad',
                    'seccion_semestres.link_encuesta'
                )->distinct()->get();

            $campus_clinico = DB::connection('mysql3')->table('alumno_seccions')
                ->join('seccion_semestres', function ($join) {
                    $join->on('alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                        ->where('alumno_seccions.rut_alumno', '=', $this->rut);
                })
                ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                ->select('seccion_semestres.idPeriodo', 'seccion_semestres.nrc', 'asignaturas.nombre')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.Liga', '!=', '']])
                ->distinct()
                ->get();

            $rotaciones = DB::connection('mysql3')->table('alumno_seccions')
                ->join('campus_seccions', function ($join) {
                    $join->on('alumno_seccions.idSeccion', '=', 'campus_seccions.alumno_seccion')
                        ->where('alumno_seccions.rut_alumno', '=', $this->rut);
                })
                ->join('seccion_semestres', 'alumno_seccions.nrc', 'seccion_semestres.idSeccion')
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
                ->join('hospitals', 'rotacion_semestres.idHospital', '=', 'hospitals.idHospital')
                ->orderBy('hospitals.nombre')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->groupBy(
                    'campus_seccions.link_encuesta',
                    'seccion_semestres.idPeriodo',
                    'campus_seccions.nrc',
                    'campus_decretos.nombre',
                    'hospitals.nombre',
                    'rotacion_semestres.fecha_inicio',
                    'rotacion_semestres.fecha_termino',
                    'rotacion_semestres.fecha_inicio_encuesta',
                    'docentes.nombre'
                )
                ->select(
                    DB::raw('count(campus_seccions.alumno_seccion) as numero_alumno'),
                    'campus_seccions.link_encuesta',
                    'seccion_semestres.idPeriodo',
                    'campus_seccions.nrc',
                    'campus_decretos.nombre',
                    'hospitals.nombre',
                    'rotacion_semestres.fecha_inicio',
                    'rotacion_semestres.fecha_termino',
                    'rotacion_semestres.fecha_inicio_encuesta',
                    'docentes.nombre'
                )->get();

            $surveys_ids = [];
            $count = 0;
            $estatus = [];

            foreach ($asignaturas as $key => $value) {
                if ('none' == $value->link_encuesta) {
                    continue;
                } else {
                    $survey_id = Str::after($value->link_encuesta, 'limesurvey.test/index.php/');

                    $surveys_ids[$count] = ['id' => $survey_id, 'nrc' => $value->nrc];
                    ++$count;
                }
            }

            // instantiate a new client
            $myJSONRPCClient = new \org\jsonrpcphp\JsonRPCClient(LS_BASEURL.'/admin/remotecontrol');

            // receive session key
            $sessionKey = $myJSONRPCClient->get_session_key(LS_USER, LS_PASSWORD);

            $aConditions = ['email' => Auth::user()->email];

            $attributes = ['completed', 'usesleft'];

            $count = 0;

            foreach ($surveys_ids as $key => $value) {
                $list_participants = $myJSONRPCClient->list_participants($sessionKey, $value['id'], 0, 100, false, $attributes, $aConditions);

                if (!isset($list_participants[0])) {
                    continue;
                } else {
                    if ($list_participants[0]['participant_info']['email'] == Auth::user()->email and 'Y' == $list_participants[0]['completed']) {
                        DB::table('alumno_seccions')->where([['rut_alumno', '=', $this->rut], ['nrc', '=', $value['nrc']]])
                        ->update(['resp_encuesta' => 'Y']);
                    }
                    $estatus[$count] = ['nrc' => $value['nrc'], 'estado' => $list_participants[0]['completed']];
                    ++$count;
                }
            }
            $myJSONRPCClient->release_session_key($sessionKey);

            return view('home2', [
                'tipo' => $tipo,
                'periodos' => $periodos,
                'code_periodo' => $this->periodos,
                'campus_clinico' => $campus_clinico,
                'asignaturas' => $asignaturas,
                'rotaciones' => $rotaciones,
                'estados' => $estatus,
            ]);
        } elseif ('docente' == $tipo) {
            $periodos = DB::connection('mysql3')->table('periodos')
                ->where('estado', '>', '1')->get()->toArray();

            if (empty($periodos[0]->idPeriodo)) {
                $this->periodos = '';
            } else {
                $this->periodos = $periodos[0]->idPeriodo;
            }

            //datos para docentes
            $this->rut = DB::connection('mysql3')->table('docentes')->where('nombre', Auth::user()->name)->value('rut'); //rut del docente

            $asignatura = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', function ($join) {
                    $join->on('seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                        ->where('seccion_semestres.idDocente', '=', $this->rut);
                })->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select('seccion_semestres.idPeriodo', 'docentes.nombre as doc_nom', 'seccion_semestres.nrc', 'asignaturas.nombre', 'seccion_semestres.actividad', 'seccion_semestres.fecha_inicio_encuesta', 'seccion_semestres.fecha_termino_encuesta')->get();

            //seccion Alumnos Teoricos
            $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                    ->where('seccion_semestres.idDocente', '=', $this->rut);
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

            //datos Campus clinico Docente
            $campus_clinico = DB::connection('mysql3')->table('rotacion_semestres')
                ->join('campus_seccions', 'campus_seccions.seccion_semestre', 'rotacion_semestres.idRotacion')
                ->join('docente_seccions', function ($join) {
                    $join->on('docente_seccions.idProfesor_campus', '=', 'campus_seccions.profesor_seccion')
                        ->where('docente_seccions.idDocente', '=', $this->rut);
                })
                ->join('seccion_semestres', 'seccion_semestres.idSeccion', 'campus_seccions.seccion_semestre')
                ->join('campus_decretos', 'seccion_semestres.nrc', '=', 'campus_decretos.idAsignatura')
                ->orderBy('campus_seccions.nrc')
                ->select('seccion_semestres.idPeriodo', 'campus_seccions.nrc', 'campus_decretos.nombre')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->distinct()
                ->get();

            //seccion Alumnos campus clinicos
            $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
                ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
                ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
                ->groupBy('campus_seccions.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->select(
                    DB::raw('count(distinct(alumnos.rut)) as cant_alumnos_cli, count(campus_seccions.res_encuesta) as resp_encuesta'),
                    'campus_seccions.nrc'
                )->get();

            $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
                ->groupBy('campus_seccions.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->select(DB::raw('count(distinct(campus_seccions.profesor_seccion)) as cant_profesor'), 'campus_seccions.nrc')->get();

            //seccion rotaciones campus clinico
            $rotaciones = DB::connection('mysql3')->table('rotacion_semestres')
                ->join('campus_decretos', 'campus_decretos.codigo_campus', 'rotacion_semestres.idCampus')
                ->join('campus_seccions', 'rotacion_semestres.idRotacion', 'campus_seccions.rotacion')
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', 'docente_seccions.idDocente')
                ->join('seccion_semestres', 'seccion_semestres.idSeccion', 'campus_seccions.seccion_semestre')
                ->join('hospitals', 'hospitals.idhospital', 'rotacion_semestres.idhospital')
                ->where([
                    ['docente_seccions.idDocente', '=', $this->rut],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ])
                ->groupBy(
                    'campus_seccions.rotacion',
                    'campus_seccions.nrc',
                    'campus_decretos.nombre',
                    'hospitals.nombre',
                    'rotacion_semestres.fecha_inicio',
                    'rotacion_semestres.fecha_termino',
                    'rotacion_semestres.fecha_inicio_encuesta',
                    'docentes.nombre'
                )
                ->select(
                    DB::raw('count(campus_seccions.alumno_seccion) as numero_alumno'),
                    'campus_seccions.rotacion',
                    'campus_seccions.nrc',
                    'campus_decretos.nombre',
                    'hospitals.nombre',
                    'rotacion_semestres.fecha_inicio',
                    'rotacion_semestres.fecha_termino',
                    'rotacion_semestres.fecha_inicio_encuesta',
                    'docentes.nombre'
                )->get();

            $contador_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre')
                        ->where('seccion_semestres.idDocente', '=', $this->rut);
                })
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->groupBy('rotacion_semestres.idCampus')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(DB::raw('count(rotacion_semestres.idCampus) as count_campus'), 'rotacion_semestres.idCampus')->get();

            $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'alumno_seccions.nrc');
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['alumno_seccions.resp_encuesta', '=', 'Y'],
                ])
                ->select(
                    DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad'
                )->get();

            $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
                ->where([
                    ['campus_seccions.res_encuesta', '=', 'si'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
                ->select(DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

            $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->where([
                    ['campus_seccions.entrega_rubrica', '=', 'si'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->groupBy('campus_seccions.nrc')
                ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc')->get();

            $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
                ->where([
                    ['campus_seccions.res_encuesta', '=', 'si'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
                ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

            $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
                ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
                ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
                ->groupBy('campus_seccions.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['campus_seccions.res_encuesta', '=', 'si'],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->select(
                    DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'),
                    'campus_seccions.nrc'
                )->get();

            //dd($contador_alumnos_clinicos);
            //dd($campus_clinico,$alumnos_clinicos,$docentes_clinicos,$contador_rotaciones,$rotaciones);
            //dd($docentes_clinicos,$contador_rotaciones,$rotaciones);
            //dd($contador_alumnos_clinicos,$contador_docentes_clinicos);
            //dd($alumnos_teoricos,$asignatura->isEmpty(),$contador_alumnos);
            //dd($contador_alumnos_clinicos,$respuestas_clinicas,$respuestas_rotaciones,$entregas_rubrica,$rotaciones_rubrica);

            return view('home2', [
                'tipo' => $tipo,
                'periodos' => $periodos,
                'code_periodo' => $this->periodos,
                'asignaturas' => $asignatura,
                'cantidad_teoricos' => $contador_alumnos,
                'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
                'campus_clinico' => $campus_clinico,
                'contador_docentes_clinicos' => $contador_docentes_clinicos,
                'rotaciones' => $rotaciones,
                'contador_rotaciones' => $contador_rotaciones,
                'respuestas_teoricas' => $respuestas_teoricas,
                'respuestas_clinicas' => $respuestas_clinicas,
                'respuestas_rotaciones' => $respuestas_rotaciones,
                'entrego_rubrica' => $entregas_rubrica,
                'rotaciones_rubrica' => $rotaciones_rubrica,
            ]);
        } elseif ('PA' == $tipo) {
            $periodos = DB::connection('mysql3')->table('periodos')
                ->where('estado', '>', '1')->get()->toArray();

            if (empty($periodos[0]->idPeriodo)) {
                $this->periodos = '';
            } else {
                $this->periodos = $periodos[0]->idPeriodo;
            }
            //datos para docentes
            $this->rut = DB::connection('mysql3')->table('docentes')->where('nombre', Auth::user()->name)->value('rut'); //rut del docente

            $asignatura = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', function ($join) {
                    $join->on('seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                        ->where('seccion_semestres.idDocente', '=', $this->rut);
                })
                ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select('seccion_semestres.idPeriodo', 'docentes.nombre as doc_nom', 'seccion_semestres.link_encuesta', 'seccion_semestres.nrc', 'asignaturas.nombre', 'seccion_semestres.actividad', 'seccion_semestres.fecha_inicio_encuesta', 'seccion_semestres.fecha_termino_encuesta')->get();

            $alumnos_teoricos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                    ->where('seccion_semestres.idDocente', '=', $this->rut);
            })
                ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select('seccion_semestres.nrc', 'alumnos.rut', 'alumnos.nombre', 'alumno_seccions.resp_encuesta', 'seccion_semestres.actividad')
                ->distinct()->get();

            $alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                    ->where('seccion_semestres.idDocente', '=', $this->rut);
            })
                ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
                ->orderBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%']])
                ->select('seccion_semestres.nrc', 'alumnos.rut', 'alumnos.nombre', 'alumno_seccions.resp_encuesta', 'seccion_semestres.actividad')->get();

            //seccion Alumnos Teoricos
            $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                    ->where('seccion_semestres.idDocente', '=', $this->rut);
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

            //datos Campus clinico Docente
            $campus_clinico = DB::connection('mysql3')->table('rotacion_semestres')
                ->join('campus_seccions', 'campus_seccions.seccion_semestre', 'rotacion_semestres.idRotacion')
                ->join('docente_seccions', function ($join) {
                    $join->on('docente_seccions.idProfesor_campus', '=', 'campus_seccions.profesor_seccion')
                        ->where('docente_seccions.idDocente', '=', $this->rut);
                })
                ->join('seccion_semestres', 'seccion_semestres.idSeccion', 'campus_seccions.seccion_semestre')
                ->join('campus_decretos', 'seccion_semestres.nrc', '=', 'campus_decretos.idAsignatura')
                ->orderBy('campus_seccions.nrc')
                ->select('seccion_semestres.idPeriodo', 'campus_seccions.nrc', 'campus_decretos.nombre')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->distinct()
                ->get();

            $contador_registros = DB::connection('mysql3')->table('seccion_semestres')
                ->select(
                    DB::raw('count(seccion_semestres.nrc) as registros'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad',
                    'seccion_semestres.idDocente',
                    'seccion_semestres.idPeriodo'
                )
                ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                ->join('mallas', 'mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                ->whereRaw('mallas.Encuesta = 1 AND asignaturas.actividad = seccion_semestres.actividad')
                ->groupBy('seccion_semestres.nrc', 'seccion_semestres.actividad', 'seccion_semestres.idDocente', 'seccion_semestres.idPeriodo')
                ->distinct('seccion_semestres.actividad')->get();

            //seccion Alumnos campus clinicos
            $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
                ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
                ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
                ->groupBy('campus_seccions.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->select(
                    DB::raw('count(distinct(alumnos.rut)) as cant_alumnos_cli, count(campus_seccions.res_encuesta) as resp_encuesta'),
                    'campus_seccions.nrc'
                )->get();

            $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
                ->groupBy('campus_seccions.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->select(DB::raw('count(distinct(campus_seccions.profesor_seccion)) as cant_profesor'), 'campus_seccions.nrc')->get();

            //seccion rotaciones campus clinico
            $rotaciones = DB::connection('mysql3')->table('rotacion_semestres')
                ->join('campus_decretos', 'campus_decretos.codigo_campus', 'rotacion_semestres.idCampus')
                ->join('campus_seccions', 'rotacion_semestres.idRotacion', 'campus_seccions.rotacion')
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', 'docente_seccions.idDocente')
                ->join('seccion_semestres', 'seccion_semestres.idSeccion', 'campus_seccions.seccion_semestre')
                ->join('hospitals', 'hospitals.idhospital', 'rotacion_semestres.idhospital')
                ->where([
                    ['docente_seccions.idDocente', '=', $this->rut],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ])
                ->groupBy(
                    'campus_seccions.rotacion',
                    'campus_seccions.nrc',
                    'campus_decretos.nombre',
                    'hospitals.nombre',
                    'rotacion_semestres.fecha_inicio',
                    'rotacion_semestres.fecha_termino',
                    'rotacion_semestres.fecha_inicio_encuesta',
                    'docentes.nombre'
                )
                ->select(
                    DB::raw('count(campus_seccions.alumno_seccion) as numero_alumno'),
                    'campus_seccions.rotacion',
                    'campus_seccions.nrc',
                    'campus_decretos.nombre',
                    'hospitals.nombre',
                    'rotacion_semestres.fecha_inicio',
                    'rotacion_semestres.fecha_termino',
                    'rotacion_semestres.fecha_inicio_encuesta',
                    'docentes.nombre'
                )->get();

            $contador_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre')
                        ->where('seccion_semestres.idDocente', '=', $this->rut);
                })
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->groupBy('rotacion_semestres.idCampus')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(DB::raw('count(rotacion_semestres.idCampus) as count_campus'), 'rotacion_semestres.idCampus')->get();

            $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'alumno_seccions.nrc');
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['alumno_seccions.resp_encuesta', '=', 'si'],
                ])
                ->select(
                    DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad'
                )->get();

            $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
                ->where([
                    ['campus_seccions.res_encuesta', '=', 'si'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
                ->select(DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

            $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->where([
                    ['campus_seccions.entrega_rubrica', '=', 'si'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->groupBy('campus_seccions.nrc')
                ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc')->get();

            $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
                ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
                ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
                ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
                ->where([
                    ['campus_seccions.res_encuesta', '=', 'si'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
                ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

            $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
                ->join('campus_seccions', function ($join) {
                    $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
                })
                ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
                ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
                ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
                ->groupBy('campus_seccions.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['campus_seccions.res_encuesta', '=', 'si'],
                    ['docente_seccions.idDocente', '=', $this->rut],
                ])
                ->select(
                    DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'),
                    'campus_seccions.nrc'
                )->get();

            //dd($asignatura,$alumnos_teoricos,Auth::user());
            //dd($contador_alumnos_clinicos);
            //dd($campus_clinico,$alumnos_clinicos,$docentes_clinicos,$contador_rotaciones,$rotaciones);
            //dd($docentes_clinicos,$contador_rotaciones,$rotaciones);
            //dd($contador_alumnos_clinicos,$contador_docentes_clinicos);
            //dd($alumnos_teoricos,$asignatura->isEmpty(),$contador_alumnos);
            //dd($contador_alumnos_clinicos,$respuestas_clinicas,$respuestas_rotaciones,$entregas_rubrica,$rotaciones_rubrica);

            return view('home2', [
                'tipo' => $tipo,
                'periodos' => $periodos,
                'contador_registros' => $contador_registros,
                'alumnos_teoricos' => $alumnos_teoricos,
                'alumnos_clinicos' => $alumnos_clinicos,
                'code_periodo' => $this->periodos,
                'asignaturas' => $asignatura,
                'cantidad_teoricos' => $contador_alumnos,
                'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
                'campus_clinico' => $campus_clinico,
                'contador_docentes_clinicos' => $contador_docentes_clinicos,
                'rotaciones' => $rotaciones,
                'contador_rotaciones' => $contador_rotaciones,
                'respuestas_teoricas' => $respuestas_teoricas,
                'respuestas_clinicas' => $respuestas_clinicas,
                'respuestas_rotaciones' => $respuestas_rotaciones,
                'entrego_rubrica' => $entregas_rubrica,
                'rotaciones_rubrica' => $rotaciones_rubrica,
            ]);
        } elseif ('OFEM' == $tipo) {
            $periodos = DB::connection('mysql3')->table('periodos')
                ->where('estado', '>', '0')->get()->toArray();

            if (empty($periodos[0]->idPeriodo)) {
                $this->periodos = '';
            } else {
                $this->periodos = $periodos[0]->idPeriodo;
            }
            //Para usuarios con mas de una carrera
            $carreras = DB::connection('mysql3')->table('user_carrera')->join('carreras', 'carreras.idCarrera', '=', 'user_carrera.CodCarrera')
                ->select('carreras.nombre', 'carreras.idCarrera')
                ->where('idUser', '=', Auth::user()->id)->get();

            $code_carrera = $carreras[0]->idCarrera;
            $asignaturas = DB::connection('mysql3')->table('seccion_semestres')

                ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where('mallas.Encuesta', '=', 1);
                })
                ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->orderBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.Liga', '=', ''], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->select(
                    'seccion_semestres.idPeriodo',
                    'docentes.nombre as doc_nom',
                    'seccion_semestres.nrc',
                    'asignaturas.nombre',
                    'seccion_semestres.link_encuesta',
                    'seccion_semestres.actividad',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta'
                )
                ->distinct()->get();

            $alumnos_teoricos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(
                    'seccion_semestres.nrc',
                    'alumnos.rut',
                    'alumnos.nombre',
                    'alumno_seccions.resp_encuesta',
                    'seccion_semestres.actividad'
                )->distinct()->get();

            $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

            $contador_registros = DB::connection('mysql3')->table('seccion_semestres')
                ->select(
                    DB::raw('count(seccion_semestres.nrc) as registros'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad',
                    'seccion_semestres.idDocente',
                    'seccion_semestres.idPeriodo'
                )
                ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                ->join('mallas', 'mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                ->whereRaw('mallas.Encuesta = 1 AND asignaturas.actividad = seccion_semestres.actividad')
                ->groupBy('seccion_semestres.nrc', 'seccion_semestres.actividad', 'seccion_semestres.idDocente', 'seccion_semestres.idPeriodo')
                ->distinct('seccion_semestres.actividad')->get();

            $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->whereRaw('seccion_semestres.idDocente = alumno_seccions.idDocente
                AND alumno_seccions.resp_encuesta = "Y" AND seccion_semestres.idPeriodo = '.$this->periodos)
                ->select(
                    DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad'
                )->get();

            //datos Campus clinico Docente
            $campus_clinico = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where([
                            ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                            ['asignaturas.actividad', 'like', '%TEO%'],
                        ])
                        ->orWhere('asignaturas.actividad', 'like', '%CLI%');
                })
                ->select('seccion_semestres.idPeriodo', 'seccion_semestres.nrc', 'asignaturas.nombre as nombre_asignatura')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->orderBy('seccion_semestres.nrc')
                ->distinct()
                ->get();

            $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->groupBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%'], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->select(DB::raw('count(distinct(alumno_seccions.rut_alumno)) as cant_alumnos_cli'), 'seccion_semestres.nrc')->get();

            $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where([
                            ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                            ['asignaturas.actividad', 'like', '%TEO%'],
                        ])
                        ->orWhere('asignaturas.actividad', 'like', '%CLI%');
                })
                ->groupBy('asignaturas.Liga', 'seccion_semestres.nrc')
                ->orderBy('asignaturas.Liga')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->select(DB::raw('count(distinct(seccion_semestres.idDocente)) as cant_profesor'), 'asignaturas.Liga', 'seccion_semestres.nrc')->get();

            $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->groupBy('seccion_semestres.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['alumno_seccions.resp_encuesta', '=', 'si'],
                    ['seccion_semestres.actividad', 'like', '%ROTACION%'],
                ])
                ->select(
                    DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                    'seccion_semestres.nrc'
                )->get();

            $alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
                ->orderBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%']])
                ->select('seccion_semestres.nrc', 'alumnos.rut', 'alumnos.nombre', 'alumno_seccions.resp_encuesta', 'seccion_semestres.actividad')->get();

            $docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where([
                            ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                            ['asignaturas.actividad', 'like', '%TEO%'],
                        ])
                        ->orWhere('asignaturas.actividad', 'like', '%CLI%');
                })->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->select('seccion_semestres.nrc', 'docentes.nombre', 'seccion_semestres.idDocente')->distinct()->get();

            $rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.actividad', 'like', '%ROTACION%'], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->groupBy(
                    'asignaturas.actividad',
                    'seccion_semestres.idPeriodo',
                    'seccion_semestres.nrc',
                    'asignaturas.nombre',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta',
                    'docentes.nombre',
                    'asignaturas.Liga'
                )

                ->select(
                    'asignaturas.actividad',
                    'seccion_semestres.idPeriodo',
                    'seccion_semestres.nrc',
                    'asignaturas.nombre as nombre_asignatura',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta',
                    'docentes.nombre',
                    'asignaturas.Liga'
                )->distinct()->orderBy('asignaturas.Liga')->get();

            $contador_alumnos_rotacion = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->where('seccion_semestres.actividad', 'like', '%ROTACION%')
                ->groupBy('alumno_seccions.nrc')
                ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_alumno'), 'alumno_seccions.nrc')->get();

            $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->where([
                    ['alumno_seccions.entrega_rubrica', '=', 1],
                    ['asignaturas.actividad', 'like', '%ROTACION%'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['asignaturas.idCarrera', '=', $code_carrera],
                ])
                ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
                ->select(DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'), 'seccion_semestres.nrc', 'asignaturas.Liga as rotacion')->get();

            $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->where([
                    ['alumno_seccions.entrega_rubrica', '=', 1],
                    ['asignaturas.Liga', '!=', ''],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $code_carrera],
                ])
                ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
                ->select(DB::raw('count(alumno_seccions.entrega_rubrica) as entrego_rubrica'), 'seccion_semestres.nrc', 'asignaturas.Liga')->get();

            $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->where([
                    ['alumno_seccions.entrega_rubrica', '=', 1],
                    ['asignaturas.actividad', 'like', '%ROTACION%'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ])
                ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
                ->select(DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'), 'seccion_semestres.nrc', 'asignaturas.Liga as rotacion')->get();

            //dd($contador_docentes_clinicos,$contador_alumnos);
            //dd($contador_docentes_clinicos,$campus_clinico);
            //dd($campus_sa,$num_alumnos_campus_sa,$num_respuestas_campus_sa);
            //dd($rotaciones,$respuestas_rotaciones,$rotaciones_rubrica,$entregas_rubrica);
            //dd($asignaturas,$contador_alumnos,$respuestas_teoricas);
            //dd($campus_clinico,$rotaciones,$contador_alumnos_clinicos,$contador_docentes_clinicos);

            return view('home2', [
                'tipo' => $tipo,
                'periodos' => $periodos,
                'carreras' => $carreras,
                'code_carrera' => $code_carrera,
                'code_periodo' => $this->periodos,
                'asignaturas' => $asignaturas,
                'alumnos_teoricos' => $alumnos_teoricos,
                'alumnos_clinicos' => $alumnos_clinicos,
                'contador_registros' => $contador_registros,
                'cantidad_teoricos' => $contador_alumnos,
                'docentes_clinicos' => $docentes_clinicos,
                'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
                'campus_clinico' => $campus_clinico,
                'contador_alumnos_rotacion' => $contador_alumnos_rotacion,
                'contador_docentes_clinicos' => $contador_docentes_clinicos,
                'rotaciones' => $rotaciones,
                'respuestas_teoricas' => $respuestas_teoricas,
                'respuestas_clinicas' => $respuestas_clinicas,
                'respuestas_rotaciones' => $respuestas_rotaciones,
                'entrego_rubrica' => $entregas_rubrica,
                'rotaciones_rubrica' => $rotaciones_rubrica,
            ]);
        } elseif ('SA' == $tipo) {
            $periodos = DB::connection('mysql3')->table('periodos')
                ->where('estado', '>', '0')->get()->toArray();
            if (empty($periodos[0]->idPeriodo)) {
                $this->periodos = '';
            } else {
                $this->periodos = $periodos[0]->idPeriodo;
            }
            //Para usuarios con mas de una carrera
            $carreras = DB::connection('mysql3')->table('user_carrera')->join('carreras', 'carreras.idCarrera', '=', 'user_carrera.CodCarrera')
                ->select('carreras.nombre', 'carreras.idCarrera')
                ->where('idUser', '=', Auth::user()->id)->get();

            $code_carrera = $carreras[0]->idCarrera;

            //Asignaturas Teoricas
            $asignaturas = DB::connection('mysql3')->table('seccion_semestres')

                ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where('mallas.Encuesta', '=', 1);
                })
                ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->orderBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.Liga', '=', ''], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->select(
                    'seccion_semestres.idPeriodo',
                    'docentes.nombre as doc_nom',
                    'seccion_semestres.nrc',
                    'asignaturas.nombre',
                    'seccion_semestres.link_encuesta',
                    'seccion_semestres.actividad',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta'
                )
                ->distinct()->get();

            $alumnos_teoricos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(
                    'seccion_semestres.nrc',
                    'alumnos.rut',
                    'alumnos.nombre',
                    'alumno_seccions.resp_encuesta',
                    'seccion_semestres.actividad'
                )->distinct()->get();

            $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
                ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

            $contador_registros = DB::connection('mysql3')->table('seccion_semestres')
                ->select(
                    DB::raw('count(seccion_semestres.nrc) as registros'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad',
                    'seccion_semestres.idDocente',
                    'seccion_semestres.idPeriodo'
                )
                ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                ->join('mallas', 'mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                ->whereRaw('mallas.Encuesta = 1 AND asignaturas.actividad = seccion_semestres.actividad')
                ->groupBy('seccion_semestres.nrc', 'seccion_semestres.actividad', 'seccion_semestres.idDocente', 'seccion_semestres.idPeriodo')
                ->distinct('seccion_semestres.actividad')->get();

            $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
                ->orderBy('seccion_semestres.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['alumno_seccions.resp_encuesta', '=', 'si'],
                ])
                ->select(
                    DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                    'seccion_semestres.nrc',
                    'seccion_semestres.actividad'
                )->get();

            //datos Campus clinico Docente
            $campus_clinico = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where([
                            ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                            ['asignaturas.actividad', 'like', '%TEO%'],
                        ])
                        ->orWhere('asignaturas.actividad', 'like', '%CLI%');
                })
                ->select('seccion_semestres.idPeriodo', 'seccion_semestres.nrc', 'asignaturas.nombre as nombre_asignatura')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->orderBy('seccion_semestres.nrc')
                ->distinct()
                ->get();

            $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->groupBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%'], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->select(DB::raw('count(distinct(alumno_seccions.rut_alumno)) as cant_alumnos_cli'), 'seccion_semestres.nrc')->get();

            $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where([
                            ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                            ['asignaturas.actividad', 'like', '%TEO%'],
                        ])
                        ->orWhere('asignaturas.actividad', 'like', '%CLI%');
                })
                ->groupBy('asignaturas.Liga', 'seccion_semestres.nrc')
                ->orderBy('asignaturas.Liga')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->select(DB::raw('count(distinct(seccion_semestres.idDocente)) as cant_profesor'), 'asignaturas.Liga', 'seccion_semestres.nrc')->get();

            $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->groupBy('seccion_semestres.nrc')
                ->where([
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['alumno_seccions.resp_encuesta', '=', 'si'],
                    ['seccion_semestres.actividad', 'like', '%ROTACION%'],
                ])
                ->select(
                    DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                    'seccion_semestres.nrc'
                )->get();

            $alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
            })
                ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
                ->orderBy('seccion_semestres.nrc')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%']])
                ->select('seccion_semestres.nrc', 'alumnos.rut', 'alumnos.nombre', 'alumno_seccions.resp_encuesta', 'seccion_semestres.actividad')->get();

            $docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('mallas', function ($join) {
                    $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                        ->where([
                            ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                            ['asignaturas.actividad', 'like', '%TEO%'],
                        ])
                        ->orWhere('asignaturas.actividad', 'like', '%CLI%');
                })->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->select('seccion_semestres.nrc', 'docentes.nombre', 'seccion_semestres.idDocente')->distinct()->get();

            $rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
                ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.actividad', 'like', '%ROTACION%'], ['asignaturas.idCarrera', '=', $code_carrera]])
                ->groupBy(
                    'asignaturas.actividad',
                    'seccion_semestres.idPeriodo',
                    'seccion_semestres.nrc',
                    'asignaturas.nombre',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta',
                    'docentes.nombre',
                    'asignaturas.Liga'
                )

                ->select(
                    'asignaturas.actividad',
                    'seccion_semestres.idPeriodo',
                    'seccion_semestres.nrc',
                    'asignaturas.nombre as nombre_asignatura',
                    'seccion_semestres.fecha_inicio_encuesta',
                    'seccion_semestres.fecha_termino_encuesta',
                    'docentes.nombre',
                    'asignaturas.Liga'
                )->distinct()->orderBy('asignaturas.Liga')->get();

            $contador_alumnos_rotacion = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->where('seccion_semestres.actividad', 'like', '%ROTACION%')
                ->groupBy('alumno_seccions.nrc')
                ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_alumno'), 'alumno_seccions.nrc')->get();

            $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->where([
                    ['alumno_seccions.entrega_rubrica', '=', 1],
                    ['asignaturas.actividad', 'like', '%ROTACION%'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                    ['asignaturas.idCarrera', '=', $code_carrera],
                ])
                ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
                ->select(DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'), 'seccion_semestres.nrc', 'asignaturas.Liga as rotacion')->get();

            $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->where([
                    ['alumno_seccions.entrega_rubrica', '=', 1],
                    ['asignaturas.Liga', '!=', ''],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $code_carrera],
                ])
                ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
                ->select(DB::raw('count(alumno_seccions.entrega_rubrica) as entrego_rubrica'), 'seccion_semestres.nrc', 'asignaturas.Liga')->get();

            $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
                ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
                ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
                ->where([
                    ['alumno_seccions.entrega_rubrica', '=', 1],
                    ['asignaturas.actividad', 'like', '%ROTACION%'],
                    ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ])
                ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
                ->select(DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'), 'seccion_semestres.nrc', 'asignaturas.Liga as rotacion')->get();

            //dd($contador_docentes_clinicos,$contador_alumnos);
            //dd($contador_docentes_clinicos,$campus_clinico);
            //dd($campus_sa,$num_alumnos_campus_sa,$num_respuestas_campus_sa);
            //dd($rotaciones,$respuestas_rotaciones,$rotaciones_rubrica,$entregas_rubrica);
            //dd($asignaturas,$contador_alumnos,$respuestas_teoricas);
            //dd($campus_clinico,$rotaciones,$contador_alumnos_clinicos,$contador_docentes_clinicos);

            return view('home2', [
                'tipo' => $tipo,
                'periodos' => $periodos,
                'carreras' => $carreras,
                'code_carrera' => $code_carrera,
                'code_periodo' => $this->periodos,
                'asignaturas' => $asignaturas,
                'alumnos_teoricos' => $alumnos_teoricos,
                'alumnos_clinicos' => $alumnos_clinicos,
                'contador_registros' => $contador_registros,
                'cantidad_teoricos' => $contador_alumnos,
                'docentes_clinicos' => $docentes_clinicos,
                'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
                'campus_clinico' => $campus_clinico,
                'contador_alumnos_rotacion' => $contador_alumnos_rotacion,
                'contador_docentes_clinicos' => $contador_docentes_clinicos,
                'rotaciones' => $rotaciones,
                'respuestas_teoricas' => $respuestas_teoricas,
                'respuestas_clinicas' => $respuestas_clinicas,
                'respuestas_rotaciones' => $respuestas_rotaciones,
                'entrego_rubrica' => $entregas_rubrica,
                'rotaciones_rubrica' => $rotaciones_rubrica,
            ]);
        }
    }

    public function periodos_SA($id)
    {
        $user = Auth::user()->email;
        $tipo = DB::connection('mysql3')->table('users')->where('email', $user)->value('tipo');

        $periodos = DB::connection('mysql3')->table('periodos')
            ->where('estado', '>', '0')->get()->toArray();

        //Asignaturas Teoricas
        $asignaturas = DB::connection('mysql3')->table('seccion_semestres')

            ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->select('seccion_semestres.idPeriodo', 'seccion_semestres.nrc', 'asignaturas.nombre', 'seccion_semestres.actividad', 'seccion_semestres.fecha_inicio_encuesta', 'seccion_semestres.fecha_termino_encuesta')->get();

        $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
        })
            ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

        $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
        })
            ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
            ->orderBy('seccion_semestres.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $id],
                ['alumno_seccions.resp_encuesta', '=', 'si'],
            ])
            ->select(
                DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                'seccion_semestres.nrc',
                'seccion_semestres.actividad'
            )->get();

        //datos Campus clinico Docente
        $campus_clinico = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('campus_decretos', 'seccion_semestres.nrc', '=', 'campus_decretos.idAsignatura')
            ->orderBy('campus_seccions.nrc')
            ->select('seccion_semestres.idPeriodo', 'campus_seccions.nrc', 'campus_decretos.nombre')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->distinct()
            ->get();

        $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
            ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
            ->groupBy('campus_seccions.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->select(DB::raw('count(distinct(alumnos.rut)) as cant_alumnos_cli'), 'campus_seccions.nrc')->get();

        $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->groupBy('campus_seccions.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->select(DB::raw('count(distinct(campus_seccions.profesor_seccion)) as cant_profesor'), 'campus_seccions.nrc')->get();

        $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.rut_alumno')
            ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
            ->groupBy('campus_seccions.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $id],
                ['campus_seccions.res_encuesta', '=', 'si'],
            ])
            ->select(
                DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'),
                'campus_seccions.nrc'
            )->get();

        $rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
            ->join('hospitals', 'rotacion_semestres.idHospital', '=', 'hospitals.idHospital')
            ->orderBy('hospitals.nombre')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->groupBy('campus_seccions.rotacion', 'seccion_semestres.idPeriodo', 'campus_seccions.nrc', 'campus_decretos.nombre', 'hospitals.nombre', 'rotacion_semestres.fecha_inicio', 'rotacion_semestres.fecha_termino', 'rotacion_semestres.fecha_inicio_encuesta', 'docentes.nombre')

            ->select(DB::raw('count(campus_seccions.alumno_seccion) as numero_alumno'), 'campus_seccions.rotacion', 'seccion_semestres.idPeriodo', 'campus_seccions.nrc', 'campus_decretos.nombre', 'hospitals.nombre', 'rotacion_semestres.fecha_inicio', 'rotacion_semestres.fecha_termino', 'rotacion_semestres.fecha_inicio_encuesta', 'docentes.nombre')->get();

        $contador_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->groupBy('rotacion_semestres.idCampus')
            ->where('seccion_semestres.idPeriodo', '=', $id)
            ->select(DB::raw('count(rotacion_semestres.idCampus) as count_campus'), 'rotacion_semestres.idCampus')->get();

        $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
            ->where([
                ['campus_seccions.res_encuesta', '=', 'si'],
                ['seccion_semestres.idPeriodo', '=', $id],
            ])
            ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
            ->select(DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

        $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->where([
                ['campus_seccions.entrega_rubrica', '=', 'si'],
                ['seccion_semestres.idPeriodo', '=', $id],
            ])
            ->groupBy('campus_seccions.nrc')
            ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc')->get();

        $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->where([
                ['campus_seccions.entrega_rubrica', '=', 'si'],
                ['seccion_semestres.idPeriodo', '=', $id],
            ])
            ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
            ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

        //dd($campus_sa,$num_alumnos_campus_sa,$num_respuestas_campus_sa);
        //dd($rotaciones,$respuestas_rotaciones,$rotaciones_rubrica,$entregas_rubrica);
        //dd($asignaturas,$contador_alumnos,$respuestas_teoricas);

        return view('home2', [
            'tipo' => $tipo,
            'periodos' => $periodos,
            'code_periodo' => $id,
            'asignaturas' => $asignaturas,

            'cantidad_teoricos' => $contador_alumnos,

            'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
            'campus_clinico' => $campus_clinico,

            'contador_docentes_clinicos' => $contador_docentes_clinicos,
            'rotaciones' => $rotaciones,
            'contador_rotaciones' => $contador_rotaciones,
            'respuestas_teoricas' => $respuestas_teoricas,
            'respuestas_clinicas' => $respuestas_clinicas,
            'respuestas_rotaciones' => $respuestas_rotaciones,
            'entrego_rubrica' => $entregas_rubrica,
            'rotaciones_rubrica' => $rotaciones_rubrica,
        ]);
    }

    public function periodos_PA($id)
    {
        $tipo = Auth::user()->tipo;
        $periodos = DB::connection('mysql3')->table('periodos')
            ->where('estado', '>', '1')->get()->toArray();

        $this->periodos = $id;

        //datos para docentes
        $this->rut = DB::connection('mysql3')->table('docentes')->where('nombre', Auth::user()->name)->value('rut'); //rut del docente

        $asignatura = DB::connection('mysql3')->table('seccion_semestres')
            ->join('asignaturas', function ($join) {
                $join->on('seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
                    ->where('seccion_semestres.idDocente', '=', $this->rut);
            })
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->select('seccion_semestres.idPeriodo', 'seccion_semestres.nrc', 'asignaturas.nombre', 'seccion_semestres.actividad', 'seccion_semestres.fecha_inicio_encuesta', 'seccion_semestres.fecha_termino_encuesta')->get();

        $alumnos_teoricos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->where('seccion_semestres.idDocente', '=', $this->rut);
        })
            ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->select('seccion_semestres.nrc', 'alumnos.rut', 'alumnos.nombre', 'alumno_seccions.resp_encuesta', 'seccion_semestres.actividad')->get();

        //seccion Alumnos Teoricos
        $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
                ->where('seccion_semestres.idDocente', '=', $this->rut);
        })
            ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

        //datos Campus clinico Docente
        $campus_clinico = DB::connection('mysql3')->table('rotacion_semestres')
            ->join('campus_seccions', 'campus_seccions.seccion_semestre', 'rotacion_semestres.idRotacion')
            ->join('docente_seccions', function ($join) {
                $join->on('docente_seccions.idProfesor_campus', '=', 'campus_seccions.profesor_seccion')
                    ->where('docente_seccions.idDocente', '=', $this->rut);
            })
            ->join('seccion_semestres', 'seccion_semestres.idSeccion', 'campus_seccions.seccion_semestre')
            ->join('campus_decretos', 'seccion_semestres.nrc', '=', 'campus_decretos.idAsignatura')
            ->orderBy('campus_seccions.nrc')
            ->select('seccion_semestres.idPeriodo', 'campus_seccions.nrc', 'campus_decretos.nombre')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->distinct()
            ->get();

        //seccion Alumnos campus clinicos
        $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
            ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
            ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
            ->groupBy('campus_seccions.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['docente_seccions.idDocente', '=', $this->rut],
            ])
            ->select(
                DB::raw('count(distinct(alumnos.rut)) as cant_alumnos_cli, count(campus_seccions.res_encuesta) as resp_encuesta'),
                'campus_seccions.nrc'
            )->get();

        $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
            ->groupBy('campus_seccions.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['docente_seccions.idDocente', '=', $this->rut],
            ])
            ->select(DB::raw('count(distinct(campus_seccions.profesor_seccion)) as cant_profesor'), 'campus_seccions.nrc')->get();

        //seccion rotaciones campus clinico
        $rotaciones = DB::connection('mysql3')->table('rotacion_semestres')
            ->join('campus_decretos', 'campus_decretos.codigo_campus', 'rotacion_semestres.idCampus')
            ->join('campus_seccions', 'rotacion_semestres.idRotacion', 'campus_seccions.rotacion')
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', 'docente_seccions.idDocente')
            ->join('seccion_semestres', 'seccion_semestres.idSeccion', 'campus_seccions.seccion_semestre')
            ->join('hospitals', 'hospitals.idhospital', 'rotacion_semestres.idhospital')
            ->where([
                ['docente_seccions.idDocente', '=', $this->rut],
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
            ])
            ->groupBy(
                'campus_seccions.rotacion',
                'campus_seccions.nrc',
                'campus_decretos.nombre',
                'hospitals.nombre',
                'rotacion_semestres.fecha_inicio',
                'rotacion_semestres.fecha_termino',
                'rotacion_semestres.fecha_inicio_encuesta',
                'docentes.nombre'
            )
            ->select(
                DB::raw('count(campus_seccions.alumno_seccion) as numero_alumno'),
                'campus_seccions.rotacion',
                'campus_seccions.nrc',
                'campus_decretos.nombre',
                'hospitals.nombre',
                'rotacion_semestres.fecha_inicio',
                'rotacion_semestres.fecha_termino',
                'rotacion_semestres.fecha_inicio_encuesta',
                'docentes.nombre'
            )->get();

        $contador_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre')
                    ->where('seccion_semestres.idDocente', '=', $this->rut);
            })
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->groupBy('rotacion_semestres.idCampus')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->select(DB::raw('count(rotacion_semestres.idCampus) as count_campus'), 'rotacion_semestres.idCampus')->get();

        $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.idSeccion', '=', 'alumno_seccions.nrc');
        })
            ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
            ->orderBy('seccion_semestres.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['alumno_seccions.resp_encuesta', '=', 'si'],
            ])
            ->select(
                DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                'seccion_semestres.nrc',
                'seccion_semestres.actividad'
            )->get();

        $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
            ->where([
                ['campus_seccions.res_encuesta', '=', 'si'],
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['docente_seccions.idDocente', '=', $this->rut],
            ])
            ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
            ->select(DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

        $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->where([
                ['campus_seccions.entrega_rubrica', '=', 'si'],
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['docente_seccions.idDocente', '=', $this->rut],
            ])
            ->groupBy('campus_seccions.nrc')
            ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc')->get();

        $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('docente_seccions', 'campus_seccions.profesor_seccion', 'docente_seccions.idProfesor_campus')
            ->join('docentes', 'docentes.rut', '=', 'docente_seccions.idDocente')
            ->join('rotacion_semestres', 'campus_seccions.rotacion', '=', 'rotacion_semestres.idRotacion')
            ->join('campus_decretos', 'rotacion_semestres.idCampus', '=', 'campus_decretos.codigo_campus')
            ->where([
                ['campus_seccions.res_encuesta', '=', 'si'],
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['docente_seccions.idDocente', '=', $this->rut],
            ])
            ->groupBy('campus_seccions.nrc', 'campus_seccions.rotacion')
            ->select(DB::raw('count(campus_seccions.entrega_rubrica) as entrego_rubrica'), 'campus_seccions.nrc', 'campus_seccions.rotacion')->get();

        $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
            ->join('campus_seccions', function ($join) {
                $join->on('seccion_semestres.idSeccion', '=', 'campus_seccions.seccion_semestre');
            })
            ->join('alumno_seccions', 'campus_seccions.alumno_seccion', '=', 'alumno_seccions.idSeccion')
            ->join('alumnos', 'alumnos.rut', '=', 'alumno_seccions.rut_alumno')
            ->join('docente_seccions', 'docente_seccions.idProfesor_campus', 'campus_seccions.profesor_seccion')
            ->groupBy('campus_seccions.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['campus_seccions.res_encuesta', '=', 'si'],
                ['docente_seccions.idDocente', '=', $this->rut],
            ])
            ->select(
                DB::raw('count(campus_seccions.res_encuesta) as resp_encuesta'),
                'campus_seccions.nrc'
            )->get();

        //dd($asignatura,$alumnos_teoricos,Auth::user());
        //dd($contador_alumnos_clinicos);
        //dd($campus_clinico,$alumnos_clinicos,$docentes_clinicos,$contador_rotaciones,$rotaciones);
        //dd($docentes_clinicos,$contador_rotaciones,$rotaciones);
        //dd($contador_alumnos_clinicos,$contador_docentes_clinicos);
        //dd($alumnos_teoricos,$asignatura->isEmpty(),$contador_alumnos);
        //dd($contador_alumnos_clinicos,$respuestas_clinicas,$respuestas_rotaciones,$entregas_rubrica,$rotaciones_rubrica);

        return view('home2', [
            'tipo' => $tipo,
            'periodos' => $periodos,
            'alumnos_teoricos' => $alumnos_teoricos,
            'code_periodo' => $this->periodos,
            'asignaturas' => $asignatura,
            'cantidad_teoricos' => $contador_alumnos,
            'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
            'campus_clinico' => $campus_clinico,
            'contador_docentes_clinicos' => $contador_docentes_clinicos,
            'rotaciones' => $rotaciones,
            'contador_rotaciones' => $contador_rotaciones,
            'respuestas_teoricas' => $respuestas_teoricas,
            'respuestas_clinicas' => $respuestas_clinicas,
            'respuestas_rotaciones' => $respuestas_rotaciones,
            'entrego_rubrica' => $entregas_rubrica,
            'rotaciones_rubrica' => $rotaciones_rubrica,
        ]);
    }

    public function encuesta(Request $request)
    {
        define('LS_BASEURL', 'http://limesurvey.test/index.php');  // adjust this one to your actual LimeSurvey URL
        define('LS_USER', 'Alberto');
        define('LS_PASSWORD', 'master12');

        $this->rut = DB::connection('mysql3')->table('alumnos')->where('email', Auth::user()->email)->value('rut');

        $this->rut = substr((string) $this->rut, 0, 4);
        // the survey to process
        $url = $request->query('url');

        $survey_id = Str::after($url, 'limesurvey.test/index.php/');

        // instantiate a new client
        $myJSONRPCClient = new \org\jsonrpcphp\JsonRPCClient(LS_BASEURL.'/admin/remotecontrol');

        // receive session key
        $sessionKey = $myJSONRPCClient->get_session_key(LS_USER, LS_PASSWORD);

        // receive surveys list current user can read
        $groups = $myJSONRPCClient->list_surveys($sessionKey);

        $users = [['email' => Auth::user()->email, 'token' => $this->rut]];

        $attributes = ['completed', 'usesleft'];

        $adding_participants = $myJSONRPCClient->add_participants($sessionKey, $survey_id, $users, false);

        //dd($groups,$users,$url,$survey_id,$adding_participants,$this->rut);
        // release the session key
        $myJSONRPCClient->release_session_key($sessionKey);

        return redirect()->away('http://'.$url);
    }

    public function carreras_SA($id)
    {
        $tipo = Auth::user()->tipo;
        $periodos = DB::connection('mysql3')->table('periodos')
            ->where('estado', '>', '0')->get()->toArray();
        if (empty($periodos[0]->idPeriodo)) {
            $this->periodos = '';
        } else {
            $this->periodos = $periodos[0]->idPeriodo;
        }
        //Para usuarios con mas de una carrera
        $carreras = DB::connection('mysql3')->table('user_carrera')->join('carreras', 'carreras.idCarrera', '=', 'user_carrera.CodCarrera')
            ->select('carreras.nombre', 'carreras.idCarrera')
            ->where('idUser', '=', Auth::user()->id)->get();

        //Asignaturas Teoricas
        $asignaturas = DB::connection('mysql3')->table('seccion_semestres')

            ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
            ->join('mallas', function ($join) {
                $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                    ->where('mallas.Encuesta', '=', 1);
            })
            ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
            ->orderBy('seccion_semestres.nrc')
            ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.Liga', '=', ''], ['asignaturas.idCarrera', '=', $id]])
            ->select(
                'seccion_semestres.idPeriodo',
                'docentes.nombre as doc_nom',
                'seccion_semestres.nrc',
                'asignaturas.nombre',
                'seccion_semestres.link_encuesta',
                'seccion_semestres.actividad',
                'seccion_semestres.fecha_inicio_encuesta',
                'seccion_semestres.fecha_termino_encuesta'
            )
            ->distinct()->get();

        $alumnos_teoricos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
        })
            ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->select(
                'seccion_semestres.nrc',
                'alumnos.rut',
                'alumnos.nombre',
                'alumno_seccions.resp_encuesta',
                'seccion_semestres.actividad'
            )->distinct()->get();

        $contador_alumnos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
        })
            ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
            ->orderBy('seccion_semestres.nrc')
            ->where('seccion_semestres.idPeriodo', '=', $this->periodos)
            ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_seccion'), 'seccion_semestres.nrc', 'seccion_semestres.actividad')->get();

        $contador_registros = DB::connection('mysql3')->table('seccion_semestres')
            ->select(
                DB::raw('count(seccion_semestres.nrc) as registros'),
                'seccion_semestres.nrc',
                'seccion_semestres.actividad',
                'seccion_semestres.idDocente',
                'seccion_semestres.idPeriodo'
            )
            ->join('asignaturas', 'seccion_semestres.nrc', '=', 'asignaturas.idAsignatura')
            ->join('mallas', 'mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
            ->whereRaw('mallas.Encuesta = 1 AND asignaturas.actividad = seccion_semestres.actividad')
            ->groupBy('seccion_semestres.nrc', 'seccion_semestres.actividad', 'seccion_semestres.idDocente', 'seccion_semestres.idPeriodo')
            ->distinct('seccion_semestres.actividad')->get();

        $respuestas_teoricas = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
        })
            ->groupBy('seccion_semestres.actividad', 'seccion_semestres.nrc')
            ->orderBy('seccion_semestres.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['alumno_seccions.resp_encuesta', '=', 'si'],
            ])
            ->select(
                DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                'seccion_semestres.nrc',
                'seccion_semestres.actividad'
            )->get();

        //datos Campus clinico Docente
        $campus_clinico = DB::connection('mysql3')->table('seccion_semestres')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->join('mallas', function ($join) {
                $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                    ->where([
                        ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                        ['asignaturas.actividad', 'like', '%TEO%'],
                    ])
                    ->orWhere('asignaturas.actividad', 'like', '%CLI%');
            })
            ->select('seccion_semestres.idPeriodo', 'seccion_semestres.nrc', 'asignaturas.nombre as nombre_asignatura')
            ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $id]])
            ->orderBy('seccion_semestres.nrc')
            ->distinct()
            ->get();

        $contador_alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->groupBy('seccion_semestres.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%'],
                ['asignaturas.idCarrera', '=', $id],
            ])
            ->select(DB::raw('count(distinct(alumno_seccions.rut_alumno)) as cant_alumnos_cli'), 'seccion_semestres.nrc')->get();

        $contador_docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->join('mallas', function ($join) {
                $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                    ->where([
                        ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                        ['asignaturas.actividad', 'like', '%TEO%'],
                    ])
                    ->orWhere('asignaturas.actividad', 'like', '%CLI%');
            })
            ->groupBy('asignaturas.Liga', 'seccion_semestres.nrc')
            ->orderBy('asignaturas.Liga')
            ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $id]])
            ->select(DB::raw('count(distinct(seccion_semestres.idDocente)) as cant_profesor'), 'asignaturas.Liga', 'seccion_semestres.nrc')->get();

        $respuestas_clinicas = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
            ->groupBy('seccion_semestres.nrc')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['alumno_seccions.resp_encuesta', '=', 'si'],
                ['seccion_semestres.actividad', 'like', '%ROTACION%'],
            ])
            ->select(
                DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'),
                'seccion_semestres.nrc'
            )->get();

        $alumnos_clinicos = DB::connection('mysql3')->table('seccion_semestres')->join('alumno_seccions', function ($join) {
            $join->on('seccion_semestres.nrc', '=', 'alumno_seccions.nrc');
        })
            ->join('alumnos', 'alumno_seccions.rut_alumno', '=', 'alumnos.rut')
            ->orderBy('seccion_semestres.nrc')
            ->where([['seccion_semestres.idPeriodo', '=', $this->periodos], ['seccion_semestres.actividad', 'like', '%ROTACION%']])
            ->select('seccion_semestres.nrc', 'alumnos.rut', 'alumnos.nombre', 'alumno_seccions.resp_encuesta', 'seccion_semestres.actividad')->get();

        $docentes_clinicos = DB::connection('mysql3')->table('seccion_semestres')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->join('mallas', function ($join) {
                $join->on('mallas.CodAsign', '=', 'asignaturas.codigo_asignatura')
                    ->where([
                        ['mallas.CampusClinico', '=', 1], ['mallas.Encuesta', '=', 1],
                        ['asignaturas.actividad', 'like', '%TEO%'],
                    ])
                    ->orWhere('asignaturas.actividad', 'like', '%CLI%');
            })->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
            ->select('seccion_semestres.nrc', 'docentes.nombre', 'seccion_semestres.idDocente')->distinct()->get();

        $rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->join('docentes', 'docentes.rut', '=', 'seccion_semestres.idDocente')
            ->where([
                ['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.actividad', 'like', '%ROTACION%'],
                ['asignaturas.idCarrera', '=', $id],
            ])
            ->groupBy(
                'asignaturas.actividad',
                'seccion_semestres.idPeriodo',
                'seccion_semestres.nrc',
                'asignaturas.nombre',
                'seccion_semestres.fecha_inicio_encuesta',
                'seccion_semestres.fecha_termino_encuesta',
                'docentes.nombre',
                'asignaturas.Liga'
            )

            ->select(
                'asignaturas.actividad',
                'seccion_semestres.idPeriodo',
                'seccion_semestres.nrc',
                'asignaturas.nombre as nombre_asignatura',
                'seccion_semestres.fecha_inicio_encuesta',
                'seccion_semestres.fecha_termino_encuesta',
                'docentes.nombre',
                'asignaturas.Liga'
            )->distinct()->orderBy('asignaturas.Liga')->get();

        $contador_alumnos_rotacion = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'seccion_semestres.nrc', '=', 'alumno_seccions.nrc')
            ->where('seccion_semestres.actividad', 'like', '%ROTACION%')
            ->groupBy('alumno_seccions.nrc')
            ->select(DB::raw('count(alumno_seccions.nrc) as cantidad_alumno'), 'alumno_seccions.nrc')->get();

        $respuestas_rotaciones = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->where([
                ['alumno_seccions.entrega_rubrica', '=', 1],
                ['asignaturas.actividad', 'like', '%ROTACION%'],
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
                ['asignaturas.idCarrera', '=', $id],
            ])
            ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
            ->select(DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'), 'seccion_semestres.nrc', 'asignaturas.Liga as rotacion')->get();

        $entregas_rubrica = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->where([
                ['alumno_seccions.entrega_rubrica', '=', 1],
                ['asignaturas.Liga', '!=', ''],
                ['seccion_semestres.idPeriodo', '=', $this->periodos], ['asignaturas.idCarrera', '=', $id],
            ])
            ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
            ->select(DB::raw('count(alumno_seccions.entrega_rubrica) as entrego_rubrica'), 'seccion_semestres.nrc', 'asignaturas.Liga')->get();

        $rotaciones_rubrica = DB::connection('mysql3')->table('seccion_semestres')
            ->join('alumno_seccions', 'alumno_seccions.nrc', '=', 'seccion_semestres.nrc')
            ->join('asignaturas', 'asignaturas.idAsignatura', '=', 'seccion_semestres.nrc')
            ->where([
                ['alumno_seccions.entrega_rubrica', '=', 1],
                ['asignaturas.actividad', 'like', '%ROTACION%'],
                ['seccion_semestres.idPeriodo', '=', $this->periodos],
            ])
            ->groupBy('seccion_semestres.nrc', 'asignaturas.Liga')
            ->select(DB::raw('count(alumno_seccions.resp_encuesta) as resp_encuesta'), 'seccion_semestres.nrc', 'asignaturas.Liga as rotacion')->get();

        //dd($contador_docentes_clinicos,$contador_alumnos);
        //dd($contador_docentes_clinicos,$campus_clinico);
        //dd($campus_sa,$num_alumnos_campus_sa,$num_respuestas_campus_sa);
        //dd($rotaciones,$respuestas_rotaciones,$rotaciones_rubrica,$entregas_rubrica);
        //dd($asignaturas,$contador_alumnos,$respuestas_teoricas);
        //dd($campus_clinico,$rotaciones,$contador_alumnos_clinicos,$contador_docentes_clinicos);

        return view('home2', [
            'tipo' => $tipo,
            'periodos' => $periodos,
            'carreras' => $carreras,
            'code_carrera' => $id,
            'code_periodo' => $this->periodos,
            'asignaturas' => $asignaturas,
            'alumnos_teoricos' => $alumnos_teoricos,
            'alumnos_clinicos' => $alumnos_clinicos,
            'contador_registros' => $contador_registros,
            'cantidad_teoricos' => $contador_alumnos,
            'docentes_clinicos' => $docentes_clinicos,
            'contador_alumnos_clinicos' => $contador_alumnos_clinicos,
            'campus_clinico' => $campus_clinico,
            'contador_alumnos_rotacion' => $contador_alumnos_rotacion,
            'contador_docentes_clinicos' => $contador_docentes_clinicos,
            'rotaciones' => $rotaciones,
            'respuestas_teoricas' => $respuestas_teoricas,
            'respuestas_clinicas' => $respuestas_clinicas,
            'respuestas_rotaciones' => $respuestas_rotaciones,
            'entrego_rubrica' => $entregas_rubrica,
            'rotaciones_rubrica' => $rotaciones_rubrica,
        ]);
    }
}
