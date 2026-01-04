<?php

namespace App\Http\Controllers\Abteilungsleiter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StundensatzController extends Controller
{
    /**
     * Lädt alle Mitarbeiter einer bestimmten Abteilung inkl. aktuellem Stundensatz DIESER Abteilung.
     * GET /api/abteilungsleiter/mitarbeiter?abteilung_id=X
     */
    public function getMitarbeiter(Request $request)
    {
        $abteilungId = $request->query('abteilung_id');

        if (!$abteilungId) {
            return response()->json(['message' => 'Abteilung ID fehlt.'], 400);
        }

        // WICHTIG: Hier die ID eintragen, die "Übungsleiter" in deiner Tabelle 'rolle_definition' hat.
        // Falls du nicht sicher bist, schau in die DB. Oft ist Admin=1, ÜL=2.
        $uelRolleId = 2;

        // 1. Hole alle User der Abteilung, die die Rolle Übungsleiter haben
        $users = DB::table('user')
            ->join('user_rolle_abteilung', 'user.UserID', '=', 'user_rolle_abteilung.fk_userID')
            // Join zur Rollen-Definition
            ->join('rolle_definition', 'user_rolle_abteilung.fk_rolleID', '=', 'rolle_definition.RolleID')
            ->where('user_rolle_abteilung.fk_abteilungID', $abteilungId)
            // Filter auf den Namen (Prüfe Schreibweise: "Übungsleiter" oder "Uebungsleiter")
            ->where('rolle_definition.bezeichnung', 'Uebungsleiter')
            ->select('user.UserID', 'user.name', 'user.vorname', 'user.email')
            ->distinct()
            ->get();
        // 2. Stundensatz laden (spezifisch für diese Abteilung!)
        $result = $users->map(function ($user) use ($abteilungId) {

            $currentRate = DB::table('stundensatz')
                ->where('fk_userID', $user->UserID)
                ->where('fk_abteilungID', $abteilungId)
                ->whereNull('gueltigBis')
                ->first();

            return [
                'id' => $user->UserID,
                'name' => $user->name,
                'vorname' => $user->vorname,
                'email' => $user->email,
                'aktuellerSatz' => $currentRate ? $currentRate->satz : null,
                'gueltigSeit' => $currentRate ? $currentRate->gueltigVon : null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Speichert einen neuen Stundensatz für eine spezifische Abteilung.
     * POST /api/abteilungsleiter/stundensatz
     */
    public function updateStundensatz(Request $request)
    {
        // Validierung
        $validated = $request->validate([
            'user_id'      => 'required|integer|exists:user,UserID',
            'abteilung_id' => 'required|integer|exists:abteilung_definition,AbteilungID', // <--- NEU & WICHTIG
            'satz'         => 'required|numeric|min:0',
            'gueltig_ab'   => 'required|date|after:today',
        ]);

        $userId      = $validated['user_id'];
        $abteilungId = $validated['abteilung_id']; // <--- NEU
        $newSatz     = $validated['satz'];
        $validFrom   = Carbon::parse($validated['gueltig_ab']);

        try {
            DB::transaction(function () use ($userId, $abteilungId, $newSatz, $validFrom) {

                // 1. Den aktuell gültigen Satz für DIESE Abteilung suchen
                $currentEntry = DB::table('stundensatz')
                    ->where('fk_userID', $userId)
                    ->where('fk_abteilungID', $abteilungId) // <--- NEU: Eingrenzung auf Abteilung
                    ->whereNull('gueltigBis')
                    ->first();

                // 2. Falls es einen gibt, beenden
                if ($currentEntry) {
                    $currentStart = Carbon::parse($currentEntry->gueltigVon);

                    if ($validFrom->lte($currentStart)) {
                        throw new \Exception('Das neue Datum muss nach dem Startdatum des aktuellen Satzes liegen.');
                    }

                    $newEndDate = $validFrom->copy()->subDay();

                    DB::table('stundensatz')
                        ->where('StundensatzID', $currentEntry->StundensatzID)
                        ->update(['gueltigBis' => $newEndDate->format('Y-m-d')]);
                }

                // 3. Den neuen Satz anlegen (mit Abteilungs-ID)
                DB::table('stundensatz')->insert([
                    'fk_userID'      => $userId,
                    'fk_abteilungID' => $abteilungId, // <--- NEU: Speichern der Abteilung
                    'satz'           => $newSatz,
                    'gueltigVon'     => $validFrom->format('Y-m-d'),
                    'gueltigBis'     => null,
                ]);
            });

            return response()->json(['message' => 'Stundensatz erfolgreich aktualisiert.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Lädt die Historie aller Stundensätze für einen Mitarbeiter in einer Abteilung.
     * GET /api/abteilungsleiter/stundensatz-historie?user_id=X&abteilung_id=Y
     */
    public function getStundensatzHistorie(Request $request)
    {
        // 1. Validierung der Parameter
        $validated = $request->validate([
            'user_id'      => 'required|integer|exists:user,UserID',
            'abteilung_id' => 'required|integer|exists:abteilung_definition,AbteilungID',
        ]);

        $userId = $validated['user_id'];
        $abteilungId = $validated['abteilung_id'];

        // 2. Historie laden
        $history = DB::table('stundensatz')
            ->where('fk_userID', $userId)
            ->where('fk_abteilungID', $abteilungId)
            // Wichtig: Neueste zuerst (damit der aktuelle oben steht)
            ->orderBy('gueltigVon', 'desc')
            ->select('satz', 'gueltigVon', 'gueltigBis')
            ->get();

        // 3. Typkonvertierung (optional, aber sauberer für JS)
        // Laravel gibt Decimal oft als String zurück, wir casten zu Float
        $history = $history->map(function($entry) {
            $entry->satz = (float) $entry->satz;
            return $entry;
        });

        return response()->json($history);
    }
}
