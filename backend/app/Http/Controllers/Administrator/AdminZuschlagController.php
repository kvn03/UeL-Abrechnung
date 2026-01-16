<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\Zuschlag;
use Illuminate\Http\Request;

class AdminZuschlagController extends Controller
{
    public function index()
    {
        // Sortieren nach Datum, dann nach Faktor
        return response()->json(Zuschlag::orderBy('gueltigVon', 'desc')->orderBy('faktor')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'faktor'     => 'required|numeric|min:1.00',
            'gueltigVon' => 'required|date',
            'gueltigBis' => 'nullable|date|after_or_equal:gueltigVon',
        ]);

        $zuschlag = Zuschlag::create([
            'faktor'     => $request->faktor,
            'gueltigVon' => $request->gueltigVon,
            'gueltigBis' => $request->gueltigBis,
        ]);

        return response()->json($zuschlag, 201);
    }

    public function update(Request $request, $id)
    {
        $zuschlag = Zuschlag::findOrFail($id);

        $request->validate([
            'faktor'     => 'required|numeric|min:1.00',
            'gueltigVon' => 'required|date',
            'gueltigBis' => 'nullable|date|after_or_equal:gueltigVon',
        ]);

        $zuschlag->update([
            'faktor'     => $request->faktor,
            'gueltigVon' => $request->gueltigVon,
            'gueltigBis' => $request->gueltigBis,
        ]);

        return response()->json($zuschlag);
    }

    public function destroy($id)
    {
        $zuschlag = Zuschlag::findOrFail($id);

        // Prüfen, ob Feiertage diesen Zuschlag nutzen
        if ($zuschlag->feiertag()->exists()) { // Methode heißt im Model 'feiertag' (Singular)
            return response()->json([
                'message' => 'Dieser Zuschlag kann nicht gelöscht werden, da er Feiertagen zugeordnet ist.'
            ], 409);
        }

        $zuschlag->delete();

        return response()->json(['message' => 'Zuschlag erfolgreich gelöscht.']);
    }
}
