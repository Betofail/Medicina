<?php

namespace App\Http\Controllers;

use App\Model\Notification;
use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', Auth::user()->id)->whereIn('tipo', ['SA', 'PA', 'docente'])->get();
        $query = DB::connection('mysql3')->table('notifications')->select('sender_id', 'body', 'created_at')
      ->where([['receptor_id', '=', Auth::user()->id]])->get();
        $emisores = [];

        $tipo = Auth::user()->tipo;
        $n = 0;
        foreach ($query as $user) {
            $senders = DB::connection('mysql3')->table('users')->select('name')->where('id', '=', $user->sender_id)->get();
            $emisores[$n]['name'] = $senders[0]->name;
            $emisores[$n]['sender_id'] = $user->sender_id;
            $emisores[$n]['body'] = $user->body;
            $emisores[$n]['created_at'] = $user->created_at;
            ++$n;
        }

        return view('notification', compact('users', 'emisores', 'tipo'));
    }

    public function enviar(Request $request)
    {
        Notification::create([
        'sender_id' => Auth::user()->id,
        'receptor_id' => $request->receptor_id,
         'body' => $request->body,
     ]);

        return back()->with('success', 'se envio la notificacion');
    }

    public function encuestas(Request $request)
    {
        if ('clinica' == $request->tipo_encuesta) {
            $alumnos_clinicos = DB::connection('mysql3')->table('campus_clinico_seccion')
         ->where([['rotacion', $request->id_rotacion], ['seccion_semestre', $request->seccion_semestre]])
         ->join('alumno_seccion', 'campus_clinico_seccion.seccion_semestre', '=', 'alumno_seccion.nrc')->select('alumno_seccion.rut_alumno')->distinct()->get();

            $docentes_clinicos = DB::connection('mysql3')->table('campus_clinico_seccion')
          ->where([['rotacion', $request->id_rotacion], ['seccion_semestre', $request->seccion_semestre]])
          ->join('profesor_seccion', 'campus_clinico_seccion.idProfesor_seccion', '=', 'profesor_seccion.idProfesor_Campus_clinico')
          ->select('profesor_seccion.Docente_idDocente')->distinct()->get();

            DB::table('campus_clinico_seccion')
         ->where([['rotacion', $request->id_rotacion], ['seccion_semestre', $request->seccion_semestre]])
         ->update(['link_encuesta' => $request->link]);

            foreach ($alumnos_clinicos as $key => $alumno) {
                Notification::create([
               'sender_id' => Auth::user()->id,
               'receptor_id' => $alumno->rut_alumno,
               'body' => 'se envio la encuesta para la asignatura '.$request->nombre.' para la rotacion numero '.$request->id_rotacion.' nrc: '.$request->nrc,
            ]);
            }

            foreach ($docentes_clinicos as $key => $docente) {
                Notification::create([
               'sender_id' => Auth::user()->id,
               'receptor_id' => $docente->Docente_idDocente,
               'body' => 'se envio la encuesta para la asignatura '.$request->nombre.' para la rotacion numero '.$request->id_rotacion.' nrc: '.$request->nrc,
            ]);
            }
        } else {
            $alumnos = DB::connection('mysql3')->table('seccion_por_semestre')->where([['Asignatura_idAsignatura', $request->id], ['actividad', $request->tipo_asig]])
         ->join('alumno_seccion', 'seccion_por_semestre.idRamo_seccion', '=', 'alumno_seccion.nrc')
         ->select('alumno_seccion.rut_alumno')->get();

            $docentes = DB::connection('mysql3')->table('seccion_por_semestre')->where([['Asignatura_idAsignatura', $request->id], ['actividad', $request->tipo_asig]])
         ->select('Docente_idDocente')->get();

            DB::connection('mysql3')->table('seccion_por_semestre')->where([['Asignatura_idAsignatura', $request->id],
         ['actividad', $request->tipo_asig], ])->update(['link_encuesta' => $request->link]);

            foreach ($alumnos as $key => $alumno) {
                Notification::create([
               'sender_id' => Auth::user()->id,
               'receptor_id' => $alumno->rut_alumno,
               'body' => 'se envio la encuesta para la asignatura '.$request->nombre,
            ]);
            }

            foreach ($docentes as $key => $docente) {
                Notification::create([
               'sender_id' => Auth::user()->id,
               'receptor_id' => $docente->Docente_idDocente,
               'body' => 'se envio la encuesta para la asignatura '.$request->nombre,
            ]);
            }
        }

        return back()->with('success', 'se ingreso correctamente la encuesta y se envio la notificacion');
    }
}
