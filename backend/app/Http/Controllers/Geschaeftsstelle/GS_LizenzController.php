<?php

namespace App\Http\Controllers\Geschaeftsstelle;

use App\Http\Controllers\Controller;
use App\Models\UserLizenzen;
use Illuminate\Http\Request;

class GS_LizenzController extends Controller
{
    /**
     * Alle Lizenzen inkl. User-Daten laden
     */
    public function index()
    {
        // Wir laden die Lizenzen und verknüpfen sie mit dem User (Name/Vorname)
        // WICHTIG: Dein User-Model braucht die Relation 'lizenzen' oder wir joinen manuell.
        // Hier via Join für Performance und Einfachheit:
        $lizenzen = UserLizenzen::join('user', 'user_lizenzen.fk_userID', '=', 'user.UserID')
            ->select(
                'user_lizenzen.*',
                'user.name as nachname',
                'user.vorname as vorname',
                'user.email'
            )
            ->orderBy('user.name')
            ->orderBy('user_lizenzen.gueltigBis', 'asc')
            ->get();

        return response()->json($lizenzen);
    }

    /**
     * Lizenz aktualisieren (Link hinterlegen)
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'datei' => 'nullable|url', // Hier trägt die GS den Link ein
            'nummer' => 'nullable|string',
            'gueltigVon' => 'required|date',
            'gueltigBis' => 'required|date',
            'name' => 'required|string'
        ]);

        $lizenz = UserLizenzen::findOrFail($id);

        $lizenz->update([
            'datei' => $validated['datei'],
            'nummer' => $validated['nummer'],
            'gueltigVon' => $validated['gueltigVon'],
            'gueltigBis' => $validated['gueltigBis'],
            'name' => $validated['name'],
        ]);

        return response()->json(['message' => 'Lizenz aktualisiert.', 'data' => $lizenz]);
    }

    /**
     * Lizenz löschen (falls Unsinn eingetragen wurde)
     */
    public function destroy($id)
    {
        UserLizenzen::destroy($id);
        return response()->json(['message' => 'Gelöscht']);
    }
}
