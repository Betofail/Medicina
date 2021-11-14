<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Imports\AlumnoImport;
use App\Imports\AsignaturaImport;
use App\Imports\DocentesImport;
use App\Imports\MallaImport;
use App\Imports\SeccionImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CargadorController extends Controller
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function index()
    {
        return view('cargador');
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function export()
    {
        return Excel::download(new UsersExport(), 'users.xlsx');
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        if ($validator->passes()) {
            try {
                Excel::import(new AlumnoImport(), $request->file('file'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            } catch (\Error $e) {
                return back()->with('error', $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', 'Algo salio mal, revice su archivo');
            } catch (\Illuminate\Database\QueryException $e) {
                return back()->with('error', 'inconsistencia de datos');
            }

            return back()->with('success', 'se cargo el archivo correctamente');
        } else {
            return back()->with('error', $validator->errors()->first());
        }
    }

    public function import_secction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_section' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->passes()) {
            try {
                Excel::import(new SeccionImport(), request()->file('file_section'));
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', 'Algo salio mal, algun dato no es corrrecto');
            } catch (\Illuminate\Database\QueryException $e) {
                return back()->with('error', 'inconsistencia de datos');
            }

            return back()->with('success', 'se cargo el archivo correctamente');
        } else {
            return back()->with('error', $validator->errors()->first());
        }
    }

    public function import_docente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_docente' => 'required|mimes:xlsx,xls,csv',
        ]);
        if ($validator->passes()) {
            try {
                Excel::import(new DocentesImport(), request()->file('file_docente'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            } catch (\Error $e) {
                return back()->with('error', $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', 'Algo salio mal, algun dato no es corrrecto');
            } catch (\Illuminate\Database\QueryException $e) {
                return back()->with('error', 'inconsistencia de datos');
            }

            return back()->with('success', 'se cargo el archivo correctamente');
        } else {
            return back()->with('error', $validator->errors()->first());
        }
    }

    public function import_asignatura(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_asignatura' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->passes()) {
            try {
                Excel::import(new AsignaturaImport(), $request->file('file_asignatura'));
            } catch (\Exception $e) {
                return back()->with('error', 'Algo salio mal, revice su archivo');
            } catch (\Error $e) {
                return back()->with('error', 'Algo salio mal, revice su archivo');
            } catch (\InvalidArgument $e) {
                return back()->with('error', 'Algo salio mal, revice su archivo');
            } catch (\Illuminate\Database\QueryException $e) {
                return back()->with('error', 'inconsistencia de datos');
            }

            return back()->with('success', 'se cargo el archivo correctamente');
        } else {
            return back()->with('error', $validator->errors()->first());
        }
    }

    public function import_malla(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_malla' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->passes()) {
            try {
                Excel::import(new MallaImport(), $request->file('file_malla'));
            } catch (\Exception $e) {
                return back()->with('error', $e);
            } catch (\Error $e) {
                return back()->with('error', $e);
            } catch (\InvalidArgument $e) {
                return back()->with('error', $e);
            } catch (\Illuminate\Database\QueryException $e) {
                return back()->with('error', $e);
            }

            return back()->with('success', 'se cargo el archivo correctamente');
        } else {
            return back()->with('error', $validator->errors()->first());
        }
    }
}
