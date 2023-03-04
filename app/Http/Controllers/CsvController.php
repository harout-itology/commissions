<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CsvImport;
use App\Services\CommissionsService;
use Illuminate\Http\RedirectResponse;

class CsvController extends Controller
{
    public function getCSV(): View
    {
        return view( view: 'csv_form');
    }

    public function postCSV(Request $request, CommissionsService $commissionsService): RedirectResponse
    {
        // Validate that the file is a CSV or TXT file with a maximum size of 1MB
        $validator = Validator::make(data: $request->all(), rules: [
            'csv_file' => 'required|mimes:csv,txt|max:1024',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors(provider: $validator);
        }

        $csvFile = Excel::toCollection(import: new CsvImport(), filePath: $request->file('csv_file'));
        // process the fist sheet only
        $commissions = $commissionsService->calculations(sheet: $csvFile[0]);

        return redirect()->back()->with(key: 'success', value: $commissions);
    }
}
