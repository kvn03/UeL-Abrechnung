<?php

namespace App\Http\Controllers\Uebungsleiter;

use App\Http\Controllers\Controller;
use App\Models\Abrechnung;
use App\Models\AbrechnungStatusLog;
use App\Models\Quartal;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// <--- WICHTIG: Quartal importieren

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
                'quartal',
                'stundeneintraege',
                'statusLogs.statusDefinition'
            ])
            ->orderBy('createdAt', 'desc')
            ->get();

        $result = $abrechnungen->map(function($a) use ($userId) {

            // --- NEU: Berechnung Gesamtbetrag ---
            // 1. Sätze laden für diesen User in der Abteilung der Abrechnung
            $rates = DB::table('stundensatz')
                ->where('fk_userID', $userId)
                ->where('fk_abteilungID', $a->fk_abteilung)
                ->get();

            // 2. Summe berechnen
            $gesamtBetrag = $a->stundeneintraege->sum(function($eintrag) use ($rates) {
                $datum = \Carbon\Carbon::parse($eintrag->datum)->startOfDay();

                // Passenden Satz finden
                $validRate = $rates->first(function($rate) use ($datum) {
                    $start = \Carbon\Carbon::parse($rate->gueltigVon)->startOfDay();
                    $end   = $rate->gueltigBis ? \Carbon\Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                    return $datum->gte($start) && ($end === null || $datum->lte($end));
                });

                $satz = $validRate ? (float)$validRate->satz : 0;
                return round($eintrag->dauer * $satz, 2);
            });
            // ------------------------------------

            $neuestesLog = $a->statusLogs->sortByDesc('modifiedAt')->first();
            $statusName = $neuestesLog ? $neuestesLog->statusDefinition->name : 'Unbekannt';
            $statusId   = $neuestesLog ? $neuestesLog->fk_statusID : 0;

            $zeitraumString = 'Unbekannt';
            if ($a->quartal) {
                $zeitraumString = $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y');
            }

            return [
                'id' => $a->AbrechnungID,
                'zeitraum' => $zeitraumString,
                'quartal_name' => $a->quartal ? $a->quartal->bezeichnung : 'N/A',
                'stunden' => round($a->stundeneintraege->sum('dauer'), 2),
                'gesamtBetrag' => $gesamtBetrag, // <--- NEU: Ins Frontend schicken
                'status' => $statusName,
                'status_id' => $statusId,
                'datum_erstellt' => $a->createdAt->format('d.m.Y'),
            ];
        });

        return response()->json($result);
    }
    /**
     * Lädt die Details einer einzelnen Abrechnung inkl. aller Einträge und deren Historie.
     */
    public function show($id)
    {
        $userId = Auth::id();

        // 1. Abrechnung laden
        $abrechnung = Abrechnung::where('AbrechnungID', $id)
            ->where('createdBy', $userId)
            ->with(['quartal', 'statusLogs.statusDefinition'])
            ->first();

        if (!$abrechnung) {
            return response()->json(['message' => 'Abrechnung nicht gefunden'], 404);
        }

        // --- NEU: Stundensätze laden (einmalig für die Abrechnung) ---
        $rates = DB::table('stundensatz')
            ->where('fk_userID', $userId)
            ->where('fk_abteilungID', $abrechnung->fk_abteilung)
            ->get();
        // -------------------------------------------------------------

        // 2. Historie (bleibt gleich)
        $abrechnungHistory = $abrechnung->statusLogs ? $abrechnung->statusLogs->map(function ($log) {
            return [
                'date'       => $log->modifiedAt,
                'user_id'    => $log->modifiedBy,
                'title'      => $log->statusDefinition->name ?? 'Status geändert',
                'kommentar'  => $log->kommentar
            ];
        })->sortByDesc('date')->values() : [];

        // 3. Einträge laden
        $eintraege = Stundeneintrag::where('fk_abrechnungID', $abrechnung->AbrechnungID)
            ->with(['auditLogs', 'statusLogs.statusDefinition'])
            ->orderBy('datum', 'asc')
            ->get();

        // 4. Einträge formatieren & PREIS BERECHNEN
        $eintraegeFormatted = $eintraege->map(function ($eintrag) use ($rates) {

            // --- NEU: Preisberechnung pro Eintrag ---
            $datum = \Carbon\Carbon::parse($eintrag->datum)->startOfDay();
            $validRate = $rates->first(function($rate) use ($datum) {
                $start = \Carbon\Carbon::parse($rate->gueltigVon)->startOfDay();
                $end   = $rate->gueltigBis ? \Carbon\Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                return $datum->gte($start) && ($end === null || $datum->lte($end));
            });
            $satz = $validRate ? (float)$validRate->satz : 0;
            $betrag = round($eintrag->dauer * $satz, 2);
            // ----------------------------------------

            // ... (Hier dein bestehender Code für Audit/Status Logs) ...
            $audits = $eintrag->auditLogs ? $eintrag->auditLogs->map(function ($log) {
                return [
                    'type' => 'audit', 'date' => $log->modifiedAt,
                    'title' => "Feld '{$log->feldname}' geändert",
                    'details' => "'{$log->alter_wert}' → '{$log->neuer_wert}'", 'kommentar' => $log->kommentar
                ];
            }) : collect([]);

            $statuses = $eintrag->statusLogs ? $eintrag->statusLogs->map(function ($log) {
                return [
                    'type' => 'status', 'date' => $log->modifiedAt,
                    'title' => "Status: " . ($log->statusDefinition->name ?? 'Unbekannt'),
                    'details' => '', 'kommentar' => $log->kommentar
                ];
            }) : collect([]);

            $history = $audits->concat($statuses)->sortByDesc('date')->values();

            return [
                'id' => $eintrag->EintragID,
                'datum' => \Carbon\Carbon::parse($eintrag->datum)->format('d.m.Y'),
                'start' => \Carbon\Carbon::parse($eintrag->beginn)->format('H:i'),
                'ende'  => \Carbon\Carbon::parse($eintrag->ende)->format('H:i'),
                'dauer' => (float) $eintrag->dauer,
                'kurs'  => $eintrag->kurs ?? '',
                'betrag' => $betrag, // <--- NEU: Ins Frontend schicken
                'history' => $history
            ];
        });

        return response()->json([
            'abrechnung_id' => $abrechnung->AbrechnungID,
            'quartal' => $abrechnung->quartal ? $abrechnung->quartal->bezeichnung : '-',
            'abrechnung_history' => $abrechnungHistory,
            'eintraege' => $eintraegeFormatted
        ]);
    }
    /**
     * Gibt alle Stundensätze des eingeloggten Übungsleiters zurück.
     * GET /api/uebungsleiter/meine-saetze
     */
    public function getMeineSaetze(Request $request)
    {
        $userId = Auth::id();

        // Alle Sätze holen inkl. Abteilungsname
        $saetze = DB::table('stundensatz')
            ->join('abteilung_definition', 'stundensatz.fk_abteilungID', '=', 'abteilung_definition.AbteilungID')
            ->where('fk_userID', $userId)
            ->orderBy('abteilung_definition.name') // Erst nach Abteilung sortieren
            ->orderBy('gueltigVon', 'desc')        // Dann nach Datum (neueste zuerst)
            ->select(
                'stundensatz.satz',
                'stundensatz.gueltigVon',
                'stundensatz.gueltigBis',
                'abteilung_definition.name as abteilung',
                'abteilung_definition.AbteilungID as abteilung_id'
            )
            ->get();

        // Wir gruppieren die Daten direkt hier oder im Frontend.
        // Für eine einfache API geben wir die Liste zurück, das Frontend gruppiert.

        // Casten
        $saetze = $saetze->map(function($s) {
            $s->satz = (float)$s->satz;
            return $s;
        });

        return response()->json($saetze);
    }
}
