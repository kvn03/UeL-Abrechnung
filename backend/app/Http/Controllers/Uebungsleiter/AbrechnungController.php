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
     * Erstellt Abrechnungen aus ausgewählten Stundeneinträgen.
     * Erstellt PRO ABTEILUNG eine eigene Abrechnung.
     * Prüft vorher auf vorhandene Stammdaten.
     */
    public function erstellen(Request $request)
    {
        // 1. Validierung der IDs
        $validated = $request->validate([
            'stundeneintrag_ids' => 'required|array|min:1',
            'stundeneintrag_ids.*' => 'integer|exists:stundeneintrag,EintragID',
        ]);

        $userId = Auth::id();
        $ids = $validated['stundeneintrag_ids'];

        // --- NEU: Schritt A - Stammdaten prüfen ---
        // Wir prüfen, ob ein gültiger Stammdaten-Eintrag existiert (IBAN ist Pflicht)
        $stammdaten = DB::table('user_stammdaten')
            ->where('fk_userID', $userId)
            ->whereNull('gueltigBis') // Nur aktuell gültige
            ->orderBy('gueltigVon', 'desc')
            ->first();

        if (!$stammdaten || empty($stammdaten->iban)) {
            return response()->json([
                'message' => 'Bitte hinterlege zuerst deine Bankverbindung (IBAN) und Adresse in den Stammdaten, bevor du eine Abrechnung einreichst.'
            ], 422); // 422 Unprocessable Entity
        }
        // ------------------------------------------

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

        // 3. Quartal ermitteln (Global für alle Einträge)
        $minDatum = $eintraege->min('datum');
        $maxDatum = $eintraege->max('datum');

        $quartal = Quartal::where('beginn', '<=', $minDatum)
            ->where('ende', '>=', $minDatum)
            ->first();

        if (!$quartal) {
            return response()->json([
                'message' => 'Für das Datum ' . $minDatum->format('d.m.Y') . ' wurde kein Quartal im System gefunden.'
            ], 422);
        }

        // Sicherheitscheck: Alle Einträge müssen im selben Quartal liegen
        if ($maxDatum > $quartal->ende) {
            return response()->json([
                'message' => 'Die ausgewählten Einträge erstrecken sich über mehrere Quartale. Bitte wähle nur Einträge eines Quartals aus.'
            ], 422);
        }

        // --- NEU: Schritt B - Nach Abteilung gruppieren ---
        // Collection wird nach Abteilungs-ID gruppiert
        $eintraegeProAbteilung = $eintraege->groupBy('fk_abteilung');

        $statusIdNeu = 20; // Eingereicht
        $statusIdEintragInAbrechnung = 11; // In Abrechnung

        try {
            DB::transaction(function () use ($eintraegeProAbteilung, $quartal, $userId, $statusIdNeu, $statusIdEintragInAbrechnung) {

                // Wir iterieren durch jede Abteilungsgruppe
                foreach ($eintraegeProAbteilung as $abteilungId => $abteilungEintraege) {

                    // A. Abrechnung erstellen für diese Abteilung
                    $abrechnung = Abrechnung::create([
                        'fk_quartal'   => $quartal->ID,
                        'fk_abteilung' => $abteilungId,
                        'createdBy'    => $userId,
                    ]);

                    // B. Abrechnung Log schreiben
                    AbrechnungStatusLog::create([
                        'fk_abrechnungID' => $abrechnung->AbrechnungID,
                        'fk_statusID'     => $statusIdNeu,
                        'modifiedBy'      => $userId,
                        'modifiedAt'      => now(),
                        'kommentar'       => 'Abrechnung für ' . $quartal->bezeichnung . ' erstellt (Automatisch getrennt nach Abteilung).',
                    ]);

                    // C. Stundeneinträge dieser Gruppe aktualisieren
                    foreach ($abteilungEintraege as $eintrag) {
                        $eintrag->update([
                            'fk_abrechnungID' => $abrechnung->AbrechnungID
                        ]);

                        StundeneintragStatusLog::create([
                            'fk_stundeneintragID' => $eintrag->EintragID,
                            'fk_statusID'         => $statusIdEintragInAbrechnung,
                            'modifiedBy'          => $userId,
                            'modifiedAt'          => now(),
                            'kommentar'           => 'In Abrechnung #' . $abrechnung->AbrechnungID . ' aufgenommen.',
                        ]);
                    }
                }
            });

            return response()->json([
                'message' => 'Abrechnung(en) erfolgreich erstellt.',
                'quartal' => $quartal->bezeichnung,
                'anzahl_abrechnungen' => $eintraegeProAbteilung->count(),
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
                'abteilung', // <--- WICHTIG: Abteilung mitladen
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
                'abteilung' => $a->abteilung ? $a->abteilung->name : 'Unbekannt',
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
