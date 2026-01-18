<?php

namespace App\Http\Controllers\Uebungsleiter;

use App\Http\Controllers\Controller;
use App\Models\UserLizenzen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LizenzController extends Controller
{
    public function getLizenzen()
    {
        $userId = Auth::id();
        $lizenzen = UserLizenzen::where('fk_userID', $userId)
            ->orderBy('gueltigBis', 'asc')
            ->get();

        return response()->json($lizenzen);
    }

    /**
     * Lizenz hinzufügen (Bearbeiten entfernt, Datei-Upload entfernt)
     */
    public function saveLizenz(Request $request)
    {
        $userId = Auth::id();

        // Validierung: Keine ID und keine Datei mehr
        $validated = $request->validate([
            'nummer'      => 'nullable|string|max:50',
            'name'        => 'required|string|max:100',
            'gueltigVon'  => 'required|date',
            'gueltigBis'  => 'required|date|after_or_equal:gueltigVon',
        ]);

        // Immer einen neuen Eintrag erstellen
        $lizenz = UserLizenzen::create([
            'fk_userID'  => $userId,
            'nummer'     => $validated['nummer'],
            'name'       => $validated['name'],
            'gueltigVon' => $validated['gueltigVon'],
            'gueltigBis' => $validated['gueltigBis'],
            'datei'      => null, // Datei bleibt leer, wird von GS/AL gemacht
        ]);

        return response()->json(['message' => 'Lizenz gemeldet.', 'data' => $lizenz]);
    }

    public function deleteLizenz($id)
    {
        $userId = Auth::id();
        $lizenz = UserLizenzen::where('ID', $id)->where('fk_userID', $userId)->firstOrFail();
        $lizenz->delete();

        return response()->json(['message' => 'Lizenz gelöscht.']);
    }
}
