<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Stundeneintrag;
use App\Models\Abrechnung;
use App\Models\AbrechnungStatusLog;
use App\Models\StundeneintragStatusLog;
use App\Models\Quartal; // <--- WICHTIG: Quartal importieren

class AbrechnungController extends Controller
{
    /**
     * Erstellt eine neue Abrechnung aus ausgewählten Stundeneinträgen.
     */
    public function erstellen(Request $request)
    {
        // 1. Validierung
        $validated = $request->validate([
            'stundeneintrag_ids' => 'required|array|min:1',
            'stundeneintrag_ids.*' => 'integer|exists:stundeneintrag,EintragID',
        ]);

        $userId = Auth::id();
        $ids = $validated['stundeneintrag_ids'];

        // 2. Einträge laden
        $eintraege = Stundeneintrag::whereIn('EintragID', $ids)
            ->where('createdBy', $userId)
            ->whereNull('fk_abrechnungID')
            ->get();

        if ($eintraege->count() !== count($ids)) {
            return response()->json([
                'message' => 'Einige Einträge sind ungültig oder bereits abgerechnet.'
            ], 422);
        }

        // 3. Quartal ermitteln
        // Wir nehmen das Datum des ersten Eintrags, um das Quartal zu finden.
        $minDatum = $eintraege->min('datum');
        $maxDatum = $eintraege->max('datum');

        // Suche das Quartal in der DB, das dieses Datum abdeckt
        $quartal = Quartal::where('beginn', '<=', $minDatum)
            ->where('ende', '>=', $minDatum)
            ->first();

        if (!$quartal) {
            return response()->json([
                'message' => 'Für das Datum ' . $minDatum->format('d.m.Y') . ' wurde kein Quartal im System gefunden.'
            ], 422);
        }

        // Optional: Sicherheitscheck - Liegen alle Einträge im selben Quartal?
        if ($maxDatum > $quartal->ende) {
            return response()->json([
                'message' => 'Die ausgewählten Einträge erstrecken sich über mehrere Quartale. Bitte nur Einträge eines Quartals wählen.'
            ], 422);
        }

        $abteilungId = $eintraege->first()->fk_abteilung;

        // IDs für Status (Annahme)
        $statusIdNeu = 20;
        $statusIdEintragInAbrechnung = 11;

        try {
            DB::transaction(function () use ($eintraege, $quartal, $abteilungId, $userId, $statusIdNeu, $statusIdEintragInAbrechnung) {

                // A. Abrechnung erstellen (Jetzt mit fk_quartal statt Datum)
                $abrechnung = Abrechnung::create([
                    'fk_quartal'   => $quartal->ID, // <--- HIER GEÄNDERT
                    'fk_abteilung' => $abteilungId,
                    'createdBy'    => $userId,
                ]);

                // B. Abrechnung Log schreiben
                // (Falls du keine Observer nutzt, bleibt das hier drin)
                AbrechnungStatusLog::create([
                    'fk_abrechnungID' => $abrechnung->AbrechnungID,
                    'fk_statusID'     => $statusIdNeu,
                    'modifiedBy'      => $userId,
                    'modifiedAt'      => now(),
                    'kommentar'       => 'Abrechnung für ' . $quartal->bezeichnung . ' erstellt.'
                ]);

                // C. Stundeneinträge aktualisieren
                foreach ($eintraege as $eintrag) {
                    $eintrag->update([
                        'fk_abrechnungID' => $abrechnung->AbrechnungID
                    ]);

                    // Hinweis: Wenn du im Stundeneintrag Model den 'updated' Observer hast,
                    // wird das Log eventuell doppelt geschrieben. Wenn nicht, lass es hier stehen.
                    StundeneintragStatusLog::create([
                        'fk_stundeneintragID' => $eintrag->EintragID,
                        'fk_statusID'         => $statusIdEintragInAbrechnung,
                        'modifiedBy'          => $userId,
                        'modifiedAt'          => now(),
                        'kommentar'           => 'In Abrechnung #' . $abrechnung->AbrechnungID . ' aufgenommen.',
                    ]);
                }
            });

            return response()->json([
                'message' => 'Abrechnung erfolgreich erstellt.',
                'quartal' => $quartal->bezeichnung,
                'anzahl_eintraege' => $eintraege->count(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fehler beim Erstellen der Abrechnung',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMeineAbrechnungen(Request $request)
    {
        $userId = Auth::id();

        $abrechnungen = Abrechnung::where('createdBy', $userId)
            ->with([
                'quartal',            // <--- WICHTIG: Relation laden
                'stundeneintraege',
                'statusLogs.statusDefinition'
            ])
            ->orderBy('createdAt', 'desc')
            ->get();

        $result = $abrechnungen->map(function($a) {
            $neuestesLog = $a->statusLogs->sortByDesc('modifiedAt')->first();
            $statusName = $neuestesLog ? $neuestesLog->statusDefinition->name : 'Unbekannt';
            $statusId   = $neuestesLog ? $neuestesLog->fk_statusID : 0;

            // Zeitraum kommt jetzt aus dem Quartal Model
            $zeitraumString = 'Unbekannt';
            if ($a->quartal) {
                // Zugriff auf Carbon Objekte dank Casts im Model
                $zeitraumString = $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y');
            }

            return [
                'id' => $a->AbrechnungID,
                'zeitraum' => $zeitraumString, // <--- Angepasst
                'quartal_name' => $a->quartal ? $a->quartal->bezeichnung : 'N/A', // Optional, falls du "Q1 2024" anzeigen willst
                'stunden' => round($a->stundeneintraege->sum('dauer'), 2),
                'status' => $statusName,
                'status_id' => $statusId,
                'datum_erstellt' => $a->createdAt->format('d.m.Y'),
            ];
        });

        return response()->json($result);
    }
}
