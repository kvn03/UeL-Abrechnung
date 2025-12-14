<?php

namespace App\Http\Controllers;

use App\Models\Abrechnung;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragStatusLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GeschaeftsstelleController extends Controller
{
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
                        'id'    => $e->EintragID, // <<— NEU: eindeutige ID des Stundeneintrags
                        'datum' => \Carbon\Carbon::parse($e->datum)->format('Y-m-d'), // Besser Y-m-d für Inputs!
                        'beginn' => \Carbon\Carbon::parse($e->beginn)->format('H:i'),
                        'ende'   => \Carbon\Carbon::parse($e->ende)->format('H:i'),
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
    /**
     * [GET] Geschäftsstelle: Historische Abrechnungen nach Quartal/Jahr.
     * Wird von /api/geschaeftsstelle/abrechnungen-historie aufgerufen.
     */
    public function getAbrechnungenHistorieFuerGeschaeftsstelle(Request $request)
    {
        $year = (int) $request->query('year', \Carbon\Carbon::now()->year);
        $quarter = $request->query('quarter'); // 'Q1' | 'Q2' | 'Q3' | 'Q4' | null

        // Start-/Enddatum anhand Quartal bestimmen
        if ($quarter === 'Q1') {
            $start = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
            $end   = \Carbon\Carbon::create($year, 3, 31)->endOfDay();
        } elseif ($quarter === 'Q2') {
            $start = \Carbon\Carbon::create($year, 4, 1)->startOfDay();
            $end   = \Carbon\Carbon::create($year, 6, 30)->endOfDay();
        } elseif ($quarter === 'Q3') {
            $start = \Carbon\Carbon::create($year, 7, 1)->startOfDay();
            $end   = \Carbon\Carbon::create($year, 9, 30)->endOfDay();
        } elseif ($quarter === 'Q4') {
            $start = \Carbon\Carbon::create($year, 10, 1)->startOfDay();
            $end   = \Carbon\Carbon::create($year, 12, 31)->endOfDay();
        } else {
            // Kein Quartal angegeben: komplettes Jahr
            $start = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
            $end   = \Carbon\Carbon::create($year, 12, 31)->endOfDay();
        }

        $abrechnungen = Abrechnung::with([
            'creator',
            'abteilung',
            'stundeneintraege',
            'statusLogs' => function ($q) {
                $q->orderBy('modifiedAt', 'desc');
            },
            'statusLogs.statusDefinition',
        ])
            // Abrechnung fällt ins Quartal, wenn ihr Zeitraum sich mit dem Quartalszeitraum schneidet
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('zeitraumVon', [$start, $end])
                    ->orWhereBetween('zeitraumBis', [$start, $end]);
            })
            ->orderBy('zeitraumVon', 'asc')
            ->get();

        $result = $abrechnungen->map(function ($a) {
            $latestLog = $a->statusLogs->first();
            $statusName = $latestLog && $latestLog->statusDefinition
                ? $latestLog->statusDefinition->name
                : 'Unbekannt';

            return [
                'AbrechnungID'    => $a->AbrechnungID,
                'mitarbeiterName' => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung'       => $a->abteilung->name ?? 'Unbekannt',
                'stunden'         => round($a->stundeneintraege->sum('dauer'), 2),
                'zeitraum'        => \Carbon\Carbon::parse($a->zeitraumVon)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($a->zeitraumBis)->format('d.m.Y'),
                'status'          => $statusName,
            ];
        })->values();

        return response()->json($result);
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
                    // Im Log steht weiterhin der GS (Auth::id()), damit man nachvollziehen kann, wer es war.
                    'modifiedBy'          => Auth::id(),
                    'modifiedAt'          => now(),
                    'kommentar'           => 'Von Geschaeftsstelle hinzugefügt',
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
                    'kommentar'           => 'Von Geschaeftsstelle korrigiert',
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

        try {
            // Löschen (Logs werden via Cascade in DB gelöscht)
            $eintrag->delete();
            return response()->json(['message' => 'Eintrag gelöscht.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Löschen fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }
}
