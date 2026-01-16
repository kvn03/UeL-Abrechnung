<?php

namespace App\Http\Controllers\Uebungsleiter;

use App\Http\Controllers\Controller;
use App\Models\UserLizenzen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LizenzController extends Controller
{
    /**
     * Alle Lizenzen des Users abrufen
     */
    public function getLizenzen()
    {
        $userId = Auth::id();
        // Wir sortieren nach Ablaufdatum, damit die kritischen oben stehen
        $lizenzen = UserLizenzen::where('fk_userID', $userId)
            ->orderBy('gueltigBis', 'asc')
            ->get();

        return response()->json($lizenzen);
    }

    /**
     * Lizenz hinzufügen oder bearbeiten
     */
    public function saveLizenz(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'id'          => 'nullable|integer|exists:user_lizenzen,ID', // ID ist optional (nur bei Bearbeitung)
            'nummer'      => 'nullable|string|max:50',
            'name'        => 'required|string|max:100',
            'gueltigVon'  => 'required|date',
            'gueltigBis'  => 'required|date|after_or_equal:gueltigVon',
            'datei'       => 'nullable|url',
        ]);

        if (!empty($validated['id'])) {
            // Update existierender Eintrag
            $lizenz = UserLizenzen::where('ID', $validated['id'])
                ->where('fk_userID', $userId) // Sicherheitscheck: Gehört die Lizenz mir?
                ->firstOrFail();

            $lizenz->update([
                'nummer'     => $validated['nummer'],
                'name'       => $validated['name'],
                'gueltigVon' => $validated['gueltigVon'],
                'gueltigBis' => $validated['gueltigBis'],
                'datei'      => $validated['datei'],
            ]);
        } else {
            // Neuer Eintrag
            $lizenz = UserLizenzen::create([
                'fk_userID'  => $userId,
                'nummer'     => $validated['nummer'],
                'name'       => $validated['name'],
                'gueltigVon' => $validated['gueltigVon'],
                'gueltigBis' => $validated['gueltigBis'],
                'datei'      => $validated['datei'],
            ]);
        }

        return response()->json(['message' => 'Lizenz gespeichert.', 'data' => $lizenz]);
    }

    /**
     * Lizenz löschen
     */
    public function deleteLizenz($id)
    {
        $userId = Auth::id();
        $lizenz = UserLizenzen::where('ID', $id)->where('fk_userID', $userId)->firstOrFail();
        $lizenz->delete();

        return response()->json(['message' => 'Lizenz gelöscht.']);
    }
}
