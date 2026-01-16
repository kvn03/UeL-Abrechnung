<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\Limit;
use Illuminate\Http\Request;

// WICHTIG: Model importieren

class AdminLimitController extends Controller
{
    public function index()
    {
        // Sortieren nach Datum (neueste zuerst)
        return response()->json(Limit::orderBy('gueltigVon', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'wert'       => 'required|numeric|min:0',
            'gueltigVon' => 'required|date',
            'gueltigBis' => 'nullable|date|after_or_equal:gueltigVon',
        ]);

        $limit = Limit::create([
            'wert'       => $request->wert,
            'gueltigVon' => $request->gueltigVon,
            'gueltigBis' => $request->gueltigBis,
        ]);

        return response()->json($limit, 201);
    }

    public function update(Request $request, $id)
    {
        $limit = Limit::findOrFail($id);

        $request->validate([
            'wert'       => 'required|numeric|min:0',
            'gueltigVon' => 'required|date',
            'gueltigBis' => 'nullable|date|after_or_equal:gueltigVon',
        ]);

        $limit->update([
            'wert'       => $request->wert,
            'gueltigVon' => $request->gueltigVon,
            'gueltigBis' => $request->gueltigBis,
        ]);

        return response()->json($limit);
    }

    public function destroy($id)
    {
        $limit = Limit::findOrFail($id);
        $limit->delete();

        return response()->json(['message' => 'Limit erfolgreich gelöscht.']);
    }
}
