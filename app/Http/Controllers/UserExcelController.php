<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UserImport;

class UserExcelController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file'=>'required|file|mimes:xlsx',
        ]);

        $importer = new UserImport();
        try {
            Excel::import($importer, $request->file('file'));
            return response()->json(['status'=>'success','message'=>'Import user berhasil.']);
        } catch (\Exception $e) {
            return response()->json([
                'status'=>'error',
                'message'=>'Import user gagal.',
                'errors'=>$importer->errors
            ],422);
        }
    }
}
