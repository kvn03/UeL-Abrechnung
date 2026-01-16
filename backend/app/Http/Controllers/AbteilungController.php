<?php

namespace App\Http\Controllers;

use App\Models\AbteilungDefinition;
use App\Models\UserRolleAbteilung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // <--- WICHTIG: Hat gefehlt für die destroy-Methode

class AbteilungController extends Controller
{
    // --- USER / PUBLIC METHODEN ---

    // Alle Abteilungen (für Dropdowns etc.)
    public function getAbteilung()
    {
        return AbteilungDefinition::select('AbteilungID as id', 'name')
            ->orderBy('name')
            ->get();
    }

    // Nur die Abteilungen, wo der User "Uebungsleiter" ist
    public function getMeineUelAbteilungen(Request $request)
    {
        $user = $request->user();

        $zuweisungen = UserRolleAbteilung::where('fk_userID', $user->UserID)
            ->whereHas('rolle', function ($query) {
                $query->where('bezeichnung', 'Uebungsleiter');
            })
            ->with('abteilung')
            ->get();

        $abteilungen = $zuweisungen->map(function ($item) {
            return $item->abteilung;
        })->unique('AbteilungID');

        $result = $abteilungen->map(function ($dept) {
            return [
                'id' => $dept->AbteilungID,
                'name' => $dept->name
            ];
        })->values();

        return response()->json($result);
    }

    // Nur die Abteilungen, wo der User "Abteilungsleiter" ist
    public function getMeineLeiterAbteilungen(Request $request)
    {
        $user = $request->user();

        $zuweisungen = UserRolleAbteilung::where('fk_userID', $user->UserID)
            ->whereHas('rolle', function ($query) {
                $query->where('bezeichnung', 'Abteilungsleiter');
            })
            ->with('abteilung')
            ->get();

        $abteilungen = $zuweisungen->map(function ($item) {
            return $item->abteilung;
        })->unique('AbteilungID');

        $result = $abteilungen->map(function ($dept) {
            return [
                'id' => $dept->AbteilungID,
                'name' => $dept->name
            ];
        })->values();

        return response()->json($result);
    }

    // --- ADMIN METHODEN ---

    /**
     * GET /api/admin/abteilungen
     * Listet alle Abteilungen für die Administrator-Tabelle auf.
     */
    public function index()
    {
        // Wir geben hier das komplette Model zurück.
        // Falls dein Frontend 'id' statt 'AbteilungID' erwartet, müsste man das hier mappen.
        // Standardmäßig:
        return response()->json(AbteilungDefinition::orderBy('name')->get());
    }

    /**
     * POST /api/admin/abteilungen
     * Erstellt eine neue Abteilung.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:abteilung_definition,name'
        ]);

        $abteilung = AbteilungDefinition::create([
            'name' => $request->name
        ]);

        return response()->json($abteilung, 201);
    }

    /**
     * PUT/PATCH /api/admin/abteilungen/{id}
     * NEU: Aktualisiert den Namen einer Abteilung.
     */
    public function update(Request $request, $id)
    {
        $abteilung = AbteilungDefinition::findOrFail($id);

        $request->validate([
            // unique Regel: Tabelle, Spalte, ID-die-ignoriert-wird, Name-der-ID-Spalte
            // Das verhindert Fehler, wenn man speichert, ohne den Namen zu ändern.
            'name' => 'required|string|max:100|unique:abteilung_definition,name,' . $id . ',AbteilungID'
        ]);

        $abteilung->update([
            'name' => $request->name
        ]);

        return response()->json($abteilung);
    }

    /**
     * DELETE /api/admin/abteilungen/{id}
     * Löscht eine Abteilung (mit Sicherheitscheck).
     */
    public function destroy($id)
    {
        $abteilung = AbteilungDefinition::findOrFail($id);

        // SICHERHEITSCHECK: Wird diese Abteilung noch verwendet?

        // 1. Prüfen ob User diese Abteilung haben
        $hasUsers = DB::table('user_rolle_abteilung')
            ->where('fk_abteilungID', $id)
            ->exists();

        // 2. Prüfen ob Abrechnungen existieren
        $hasAbrechnungen = DB::table('abrechnung')
            ->where('fk_abteilung', $id)
            ->exists();

        // 3. Prüfen ob Stundensätze existieren
        $hasStundensaetze = DB::table('stundensatz')
            ->where('fk_abteilungID', $id)
            ->exists();

        // 4. Prüfen ob Stundeneinträge existieren
        $hasEintraege = DB::table('stundeneintrag')
            ->where('fk_abteilung', $id)
            ->exists();

        if ($hasUsers || $hasAbrechnungen || $hasStundensaetze || $hasEintraege) {
            return response()->json([
                'message' => 'Diese Abteilung kann nicht gelöscht werden, da sie noch in Verwendung ist (User, Abrechnungen oder Einträge).'
            ], 409); // 409 Conflict
        }

        $abteilung->delete();

        return response()->json(['message' => 'Abteilung erfolgreich gelöscht.']);
    }
}
