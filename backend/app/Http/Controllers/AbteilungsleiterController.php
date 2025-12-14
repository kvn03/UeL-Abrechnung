<?php

namespace App\Http\Controllers;

use App\Models\Abrechnung;
use Illuminate\Http\Request;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragStatusLog;
use App\Models\UserRolleAbteilung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AbteilungsleiterController extends Controller
{
    /**
     * HILFSFUNKTION: Prüft, ob der aktuelle User Abteilungsleiter
     * für die angegebene Abteilung ist.
     */
    private function isAbteilungsleiter(Request $request, $abteilungId)
    {
        $userId = $request->user()->UserID; // oder ->id

        // Admin darf alles (optional)
        if ($request->user()->isAdmin) return true;

        return UserRolleAbteilung::where('fk_userID', $userId)
            ->where('fk_abteilungID', $abteilungId)
            ->whereHas('rolle', function($q) {
                $q->where('bezeichnung', 'Abteilungsleiter');
                // oder RolleID prüfen, falls bekannt (z.B. 3)
            })
            ->exists();
    }

    /**
     * 1. HINZUFÜGEN (Innerhalb einer Abrechnung)
     */
    public function addEntry(Request $request)
    {
        // Validierung
        $validated = $request->validate([
            'datum'           => 'required|date',
            'beginn'          => 'required|date_format:H:i',
            'ende'            => 'required|date_format:H:i|after:beginn',
            'kurs'            => 'nullable|string',
            'fk_abteilung'    => 'nullable|exists:abteilung_definition,AbteilungID',
            'fk_abrechnungID' => 'required|exists:abrechnung,AbrechnungID',
            'status_id'       => 'required|integer',
        ]);

        // AL-Check für die Abteilung
        if (!empty($validated['fk_abteilung'])) {
            if (!$this->isAbteilungsleiter($request, $validated['fk_abteilung'])) {
                return response()->json(['message' => 'Du bist kein AL für diese Abteilung.'], 403);
            }
        }

        // --- NEU: Übungsleiter ID ermitteln ---
        // Wir laden die Abrechnung, um zu sehen, wem sie gehört.
        $abrechnung = Abrechnung::findOrFail($validated['fk_abrechnungID']);
        $uelId = $abrechnung->createdBy; // Das ist die ID des Übungsleiters

        // Dauer berechnen
        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            // Variable $uelId an die Closure übergeben ('use')
            DB::transaction(function () use ($validated, $dauer, $uelId) {
                // Eintrag erstellen
                $eintrag = Stundeneintrag::create([
                    'datum'           => $validated['datum'],
                    'beginn'          => $validated['beginn'],
                    'ende'            => $validated['ende'],
                    'dauer'           => $dauer,
                    'kurs'            => $validated['kurs'] ?? null,
                    'fk_abteilung'    => $validated['fk_abteilung'] ?? null,
                    'fk_abrechnungID' => $validated['fk_abrechnungID'],

                    // ÄNDERUNG: Hier setzen wir den Übungsleiter als Besitzer
                    'createdBy'       => $uelId,
                    'createdAt'       => now(),
                ]);

                // Log schreiben
                StundeneintragStatusLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID,
                    'fk_statusID'         => $validated['status_id'],
                    // Im Log steht weiterhin der AL (Auth::id()), damit man nachvollziehen kann, wer es war.
                    'modifiedBy'          => Auth::id(),
                    'modifiedAt'          => now(),
                    'kommentar'           => 'Vom Abteilungsleiter hinzugefügt',
                ]);
            });

            return response()->json(['message' => 'Eintrag erfolgreich hinzugefügt.'], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Fehler beim Speichern', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 2. BEARBEITEN (Update)
     */
    public function updateEntry(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        if (!$eintrag) {
            return response()->json(['message' => 'Eintrag nicht gefunden'], 404);
        }

        // Berechtigungsprüfung: Ist User AL dieser Abteilung?
        // (Falls der Eintrag keine Abteilung hat, müsste man prüfen ob er AL der Abrechnung ist)
        if ($eintrag->fk_abteilung) {
            if (!$this->isAbteilungsleiter($request, $eintrag->fk_abteilung)) {
                return response()->json(['message' => 'Keine Berechtigung (Kein AL dieser Abteilung).'], 403);
            }
        }

        $validated = $request->validate([
            'datum'         => 'required|date',
            'beginn'        => 'required|date_format:H:i',
            'ende'          => 'required|date_format:H:i|after:beginn',
            'kurs'          => 'nullable|string',
            'fk_abteilung'  => 'nullable|exists:abteilung_definition,AbteilungID',
            // status_id ist hier optional, da der Status meist gleich bleibt ("In Abrechnung")
        ]);

        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            DB::transaction(function () use ($eintrag, $validated, $dauer, $request) {

                $eintrag->update([
                    'datum'        => $validated['datum'],
                    'beginn'       => $validated['beginn'],
                    'ende'         => $validated['ende'],
                    'dauer'        => $dauer,
                    'kurs'         => $validated['kurs'] ?? null,
                    // Abteilung darf ggf. geändert werden
                    'fk_abteilung' => $validated['fk_abteilung'] ?? $eintrag->fk_abteilung,
                ]);

                // Log schreiben (optional bei Edit, aber gut für Historie)
                StundeneintragStatusLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID,
                    // Wir behalten den alten Status bei, oder nehmen einen neuen wenn gesendet
                    'fk_statusID'         => $request->input('status_id', $eintrag->aktuellerStatusLog->fk_statusID ?? 11),
                    'modifiedBy'          => Auth::id(),
                    'modifiedAt'          => now(),
                    'kommentar'           => 'Vom Abteilungsleiter korrigiert',
                ]);
            });

            return response()->json(['message' => 'Eintrag aktualisiert.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Update fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. LÖSCHEN (Delete)
     */
    public function deleteEntry(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        if (!$eintrag) {
            return response()->json(['message' => 'Nicht gefunden'], 404);
        }

        // Berechtigungsprüfung
        if ($eintrag->fk_abteilung) {
            if (!$this->isAbteilungsleiter($request, $eintrag->fk_abteilung)) {
                return response()->json(['message' => 'Keine Berechtigung zum Löschen.'], 403);
            }
        }

        try {
            // Löschen (Logs werden via Cascade in DB gelöscht)
            $eintrag->delete();
            return response()->json(['message' => 'Eintrag gelöscht.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Löschen fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * [GET] Liefert alle Abrechnungen, die der Abteilungsleiter freigeben darf.
     */
    public function getOffeneAbrechnungen(Request $request)
    {
        $userId = Auth::id();

        // 1. Herausfinden, welche Abteilungen dieser User leitet
        $managedAbteilungIds = \App\Models\UserRolleAbteilung::where('fk_userID', $userId)
            ->whereHas('rolle', function($q) {
                $q->where('bezeichnung', 'Abteilungsleiter');
            })
            ->pluck('fk_abteilungID');

        if ($managedAbteilungIds->isEmpty()) {
            return response()->json([]);
        }

        // 2. Abrechnungen laden
        $abrechnungen = Abrechnung::whereIn('fk_abteilung', $managedAbteilungIds)
            ->with([
                'creator',
                'stundeneintraege',
                'statusLogs' => function($q) { $q->orderBy('modifiedAt', 'desc'); },
                // 'statusLogs.statusDefinition' // Kannst du laden, wenn du den Status-Namen brauchst
            ])
            ->get();

        // 3. Filtern (Status 20 = Erstellt/Offen für AL)
        $offeneAbrechnungen = $abrechnungen->filter(function($abrechnung) {
            $neuestesLog = $abrechnung->statusLogs->first();
            return $neuestesLog && $neuestesLog->fk_statusID == 20;
        });

        // 4. Daten formatieren
        $result = $offeneAbrechnungen->map(function($a) {
            return [
                'AbrechnungID' => $a->AbrechnungID, // Wichtig für die Abrechnung selbst
                'mitarbeiterName' => $a->creator ? ($a->creator->vorname . ' ' . $a->creator->name) : 'Unbekannt',
                'stunden' => round($a->stundeneintraege->sum('dauer'), 2),
                'zeitraum' => \Carbon\Carbon::parse($a->zeitraumVon)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($a->zeitraumBis)->format('d.m.Y'),
                'datumEingereicht' => $a->createdAt->format('d.m.Y'),

                // DETAILS FORMATIEREN
                'details' => $a->stundeneintraege->map(function($eintrag) {
                    return [
                        // WICHTIG !!! Hier hat die ID gefehlt:
                        'EintragID' => $eintrag->EintragID,

                        'datum' => \Carbon\Carbon::parse($eintrag->datum)->format('Y-m-d'), // Besser Y-m-d für Inputs!
                        'beginn' => \Carbon\Carbon::parse($eintrag->beginn)->format('H:i'),
                        'ende'   => \Carbon\Carbon::parse($eintrag->ende)->format('H:i'),
                        'dauer'  => $eintrag->dauer,
                        'kurs'   => $eintrag->kurs,
                    ];
                }),
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * [POST] Eine Abrechnung genehmigen.
     */
    public function approve(Request $request, $id)
    {
        $userId = Auth::id();
        $abrechnung = Abrechnung::findOrFail($id);

        // Sicherheitscheck: Ist der User wirklich Abteilungsleiter DIESER Abteilung?
        $isManager = \App\Models\UserRolleAbteilung::where('fk_userID', $userId)
            ->where('fk_abteilungID', $abrechnung->fk_abteilung)
            ->whereHas('rolle', fn($q) => $q->where('bezeichnung', 'Abteilungsleiter'))
            ->exists();

        if (!$isManager && !Auth::user()->isAdmin) { // Admins dürfen zur Not auch
            return response()->json(['message' => 'Keine Berechtigung für diese Abteilung'], 403);
        }

        // Status ID 2 = Genehmigt (Anpassen!)
        $statusGenehmigt = 21;

        // Log schreiben
        \App\Models\AbrechnungStatusLog::create([
            'fk_abrechnungID' => $abrechnung->AbrechnungID,
            'fk_statusID'     => $statusGenehmigt,
            'modifiedBy'      => $userId,
            'modifiedAt'      => now(),
            'kommentar'       => 'Freigabe durch Abteilungsleiter'
        ]);

        return response()->json(['message' => 'Abrechnung erfolgreich genehmigt.']);
    }
}
