<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Stundeneintrag;
use App\Models\Abrechnung;
use App\Models\AbrechnungStatusLog; // Das neue Model für das Abrechnungs-Log
use App\Models\StundeneintragStatusLog;

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
        // Wir holen nur Einträge, die noch keiner Abrechnung zugeordnet sind (fk_abrechnungID is null)
        $eintraege = Stundeneintrag::whereIn('EintragID', $ids)
            ->where('createdBy', $userId)
            ->whereNull('fk_abrechnungID')
            ->get();

        if ($eintraege->count() !== count($ids)) {
            return response()->json([
                'message' => 'Einige Einträge sind ungültig oder bereits abgerechnet.'
            ], 422);
        }

        // 3. Metadaten für die Abrechnung ermitteln
        // Da die Tabelle 'abrechnung' kein Feld 'summe_stunden' hat, speichern wir das nicht (oder berechnen es on-the-fly).
        // Aber wir brauchen zeitraumVon, zeitraumBis und die Abteilung.

        $minDatum = $eintraege->min('datum');
        $maxDatum = $eintraege->max('datum');

        // Wir nehmen an, alle Einträge gehören zur selben Abteilung. Wir nehmen die vom ersten Eintrag.
        $abteilungId = $eintraege->first()->fk_abteilung;

        // IDs für Status definieren (Beispielwerte - musst du an deine status_definition Tabelle anpassen)
        $statusIdNeu = 20; // z.B. "Neu" oder "Eingereicht"
        $statusIdEintragInAbrechnung = 11; // Status für den Stundeneintrag

        try {
            DB::transaction(function () use ($eintraege, $minDatum, $maxDatum, $abteilungId, $userId, $statusIdNeu, $statusIdEintragInAbrechnung) {

                // A. Abrechnung erstellen
                $abrechnung = Abrechnung::create([
                    'zeitraumVon'  => $minDatum,
                    'zeitraumBis'  => $maxDatum,
                    'fk_abteilung' => $abteilungId,
                    'createdBy'    => $userId,
                    // 'createdAt' wird durch timestamps/Model automatisch gesetzt
                ]);

                // B. Abrechnung Log schreiben (DAS WAR DEIN WUNSCH)
                AbrechnungStatusLog::create([
                    'fk_abrechnungID' => $abrechnung->AbrechnungID, // Wichtig: Primary Key nutzen
                    'fk_statusID'     => $statusIdNeu,
                    'modifiedBy'      => $userId,
                    'modifiedAt'      => now(),
                    'kommentar'       => 'Abrechnung initial erstellt.'
                ]);

                // C. Stundeneinträge aktualisieren und loggen
                foreach ($eintraege as $eintrag) {

                    // Verknüpfung herstellen
                    $eintrag->update([
                        'fk_abrechnungID' => $abrechnung->AbrechnungID
                    ]);

                    // Log für den Stundeneintrag schreiben
                    StundeneintragStatusLog::create([
                        'fk_stundeneintragID' => $eintrag->EintragID,
                        'fk_statusID'         => $statusIdEintragInAbrechnung,
                        'modifiedBy'          => $userId,
                        'modifiedAt'          => now(),
                        'kommentar'           => 'Zu Abrechnung #' . $abrechnung->AbrechnungID . ' hinzugefügt.',
                    ]);
                }
            });

            return response()->json([
                'message' => 'Abrechnung erfolgreich erstellt.',
                'anzahl_eintraege' => $eintraege->count(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fehler beim Erstellen der Abrechnung',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSummary(Request $request)
    {
        $userId = Auth::id();

        // 1. Alle Abrechnungen laden
        // Wir laden die Logs (statusLogs) und darin verschachtelt die Definition (statusDefinition)
        // Wir sortieren die Abrechnungen absteigend (neueste zuerst)
        $abrechnungen = Abrechnung::where('createdBy', $userId)
            ->with([
                'stundeneintraege',
                'statusLogs' => function($query) {
                    // Wichtig: Wir sortieren die Logs, damit der erste Eintrag im Array der NEUESTE ist
                    $query->orderBy('modifiedAt', 'desc');
                },
                'statusLogs.statusDefinition' // Eager Loading des Namens
            ])
            ->orderBy('zeitraumBis', 'desc')
            ->get();

        // Fallback
        if ($abrechnungen->isEmpty()) {
            return response()->json([
                'status'      => 'Keine Abrechnungen',
                'totalHours'  => 0,
                'totalAmount' => 0,
                'periodLabel' => 'Keine Abrechnungen vorhanden',
            ]);
        }

        // 2. Summen berechnen
        $totalHours = 0;
        foreach ($abrechnungen as $abrechnung) {
            $totalHours += $abrechnung->stundeneintraege->sum('dauer');
        }

        $pauschalerSatz = 0;
        $totalAmount = $totalHours * $pauschalerSatz;

        // 3. Zeitraum Label
        $minDate = $abrechnungen->min('zeitraumVon');
        $maxDate = $abrechnungen->max('zeitraumBis');

        $periodLabel = 'Gesamtzeitraum';
        if ($minDate && $maxDate) {
            $periodLabel = \Carbon\Carbon::parse($minDate)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($maxDate)->format('d.m.Y');
        }

        // 4. Status ermitteln (Logik angepasst!)
        $statusText = 'Unbekannt';

        // Wir nehmen die allerneueste Abrechnung (steht an Index 0, da wir oben sortiert haben)
        $neuesteAbrechnung = $abrechnungen->first();

        if ($neuesteAbrechnung) {
            // Aus dieser Abrechnung holen wir das NEUESTE Log (steht auch an Index 0, siehe "with"-Funktion oben)
            $neuestesLog = $neuesteAbrechnung->statusLogs->first();

            if ($neuestesLog && $neuestesLog->statusDefinition) {
                $statusText = $neuestesLog->statusDefinition->name;
            }
        }

        return response()->json([
            'status'      => $statusText,
            'totalHours'  => round($totalHours, 2),
            'totalAmount' => round($totalAmount, 2),
            'periodLabel' => $periodLabel,
        ]);
    }

    /**
     * [GET] Liefert alle Abrechnungen, die der Abteilungsleiter freigeben darf.
     */
    public function getOffeneAbrechnungen(Request $request)
    {
        $userId = Auth::id();

        // 1. Herausfinden, welche Abteilungen dieser User leitet
        // Wir suchen in user_rolle_abteilung nach Einträgen, wo er "Abteilungsleiter" ist.
        $managedAbteilungIds = \App\Models\UserRolleAbteilung::where('fk_userID', $userId)
            ->whereHas('rolle', function($q) {
                $q->where('bezeichnung', 'Abteilungsleiter'); // Oder ID prüfen
            })
            ->pluck('fk_abteilungID');

        if ($managedAbteilungIds->isEmpty()) {
            return response()->json([]);
        }

        // 2. Abrechnungen laden, die zu diesen Abteilungen gehören
        $abrechnungen = Abrechnung::whereIn('fk_abteilung', $managedAbteilungIds)
            ->with([
                'creator', // Damit wir den Namen des Mitarbeiters sehen
                'stundeneintraege',
                'statusLogs' => function($q) { $q->orderBy('modifiedAt', 'desc'); },
                'statusLogs.statusDefinition'
            ])
            ->get();

        // 3. Filtern: Wir wollen nur die sehen, deren AKTUELLER Status "Eingereicht" (ID 1) ist.
        // (Oder Status != Genehmigt). Hier filtern wir in PHP.
        $offeneAbrechnungen = $abrechnungen->filter(function($abrechnung) {
            $neuestesLog = $abrechnung->statusLogs->first();
            // Annahme: Status ID 1 = Eingereicht.
            // Falls du alle sehen willst, nimm den Filter raus.
            return $neuestesLog && $neuestesLog->fk_statusID == 20;
        });

        // 4. Daten formatieren für das Frontend
        $result = $offeneAbrechnungen->map(function($a) {
            return [
                'AbrechnungID' => $a->AbrechnungID,
                'mitarbeiterName' => $a->creator->vorname . ' ' . $a->creator->name,
                'stunden' => round($a->stundeneintraege->sum('dauer'), 2),
                'zeitraum' => \Carbon\Carbon::parse($a->zeitraumVon)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($a->zeitraumBis)->format('d.m.Y'),
                'datumEingereicht' => $a->createdAt->format('d.m.Y'),

                // NEU: Details mitliefern
                'details' => $a->stundeneintraege->map(function($eintrag) {
                    return [
                        'datum' => \Carbon\Carbon::parse($eintrag->datum)->format('d.m.Y'),
                        'beginn' => \Carbon\Carbon::parse($eintrag->beginn)->format('H:i'),
                        'ende'   => \Carbon\Carbon::parse($eintrag->ende)->format('H:i'),
                        'dauer'  => $eintrag->dauer,
                        'kurs'   => $eintrag->kurs ?? '-', // Falls vorhanden
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

    /**
     * [GET] Geschäftsstelle: Alle Abrechnungen, die vom AL genehmigt wurden.
     * Inklusive Info, WER genehmigt hat.
     */
    public function getAbrechnungenFuerGeschaeftsstelle(Request $request)
    {
        $statusGenehmigtAL = 21; // ID für "Genehmigt durch AL"

        $abrechnungen = Abrechnung::with([
            'creator',
            'abteilung',
            'stundeneintraege',
            'statusLogs' => function($q) {
                $q->orderBy('modifiedAt', 'desc');
            },
            // NEU: Wir laden den User, der den Status geändert hat
            'statusLogs.modifier'
        ])
            ->get();

        // Filtern: Nur Abrechnungen, die aktuell auf Status 21 stehen
        $filterteAbrechnungen = $abrechnungen->filter(function($a) use ($statusGenehmigtAL) {
            $neuestesLog = $a->statusLogs->first();
            return $neuestesLog && $neuestesLog->fk_statusID == $statusGenehmigtAL;
        });

        // Formatieren
        $result = $filterteAbrechnungen->map(function($a) use ($statusGenehmigtAL) {

            // Wir suchen den spezifischen Log-Eintrag für die AL-Genehmigung
            // (Das ist meistens der neueste, aber sicherheitshalber suchen wir nach ID 21)
            $genehmigungsLog = $a->statusLogs->firstWhere('fk_statusID', $statusGenehmigtAL);

            $genehmigerName = 'Unbekannt';
            if ($genehmigungsLog && $genehmigungsLog->modifier) {
                $genehmigerName = $genehmigungsLog->modifier->vorname . ' ' . $genehmigungsLog->modifier->name;
            }

            return [
                'AbrechnungID' => $a->AbrechnungID,
                'mitarbeiterName' => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung' => $a->abteilung->name ?? 'Unbekannt',
                'stunden' => round($a->stundeneintraege->sum('dauer'), 2),
                'zeitraum' => \Carbon\Carbon::parse($a->zeitraumVon)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($a->zeitraumBis)->format('d.m.Y'),

                // Datum aus dem Log nehmen
                'datumGenehmigtAL' => $genehmigungsLog ? \Carbon\Carbon::parse($genehmigungsLog->modifiedAt)->format('d.m.Y') : '-',

                // NEU: Wer hat genehmigt?
                'genehmigtDurch' => $genehmigerName,

                'details' => $a->stundeneintraege->map(function($e) {
                    return [
                        'datum' => \Carbon\Carbon::parse($e->datum)->format('d.m.Y'),
                        'dauer' => $e->dauer,
                        'kurs'  => $e->kurs
                    ];
                })
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * [POST] Geschäftsstelle: Finale Freigabe (Auszahlung)
     */
    public function finalize(Request $request, $id)
    {
        $userId = Auth::id();
        $abrechnung = Abrechnung::findOrFail($id);

        $statusFinal = 22; // "Abgeschlossen" / "Ausbezahlt"

        // Log schreiben
        \App\Models\AbrechnungStatusLog::create([
            'fk_abrechnungID' => $abrechnung->AbrechnungID,
            'fk_statusID'     => $statusFinal,
            'modifiedBy'      => $userId,
            'modifiedAt'      => now(),
            'kommentar'       => 'Finale Freigabe durch Geschäftsstelle'
        ]);

        return response()->json(['message' => 'Abrechnung final abgeschlossen.']);
    }
}
