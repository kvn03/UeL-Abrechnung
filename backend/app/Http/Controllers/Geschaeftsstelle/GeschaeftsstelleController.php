<?php

namespace App\Http\Controllers\Geschaeftsstelle;

use App\Http\Controllers\Controller;
use App\Models\Abrechnung;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragAuditLog;
use App\Models\StundeneintragStatusLog;
use App\Models\Stundensatz;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// <--- WICHTIG: Import hinzugefügt

class GeschaeftsstelleController extends Controller
{
    /**
     * [GET] Geschäftsstelle: Alle Abrechnungen, die vom AL genehmigt wurden.
     */
    public function getAbrechnungenFuerGeschaeftsstelle(Request $request)
    {
        $statusGenehmigtAL = 21;

        $abrechnungen = Abrechnung::with([
            'creator',
            'abteilung',
            'stundeneintraege',
            'quartal', // <--- WICHTIG: Relation laden
            'statusLogs' => function($q) {
                $q->orderBy('modifiedAt', 'desc');
            },
            'statusLogs.modifier'
        ])->get();

        $filterteAbrechnungen = $abrechnungen->filter(function($a) use ($statusGenehmigtAL) {
            $neuestesLog = $a->statusLogs->first();
            return $neuestesLog && $neuestesLog->fk_statusID == $statusGenehmigtAL;
        });

        $result = $filterteAbrechnungen->map(function($a) use ($statusGenehmigtAL) {
            $genehmigungsLog = $a->statusLogs->firstWhere('fk_statusID', $statusGenehmigtAL);

            $genehmigerName = 'Unbekannt';
            if ($genehmigungsLog && $genehmigungsLog->modifier) {
                $genehmigerName = $genehmigungsLog->modifier->vorname . ' ' . $genehmigungsLog->modifier->name;
            }

            // --- Quartal Logik ---
            $quartalName = $a->quartal ? $a->quartal->bezeichnung : '';
            // ---------------------

            return [
                'AbrechnungID' => $a->AbrechnungID,
                'mitarbeiterName' => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung' => $a->abteilung->name ?? 'Unbekannt',
                'stunden' => round($a->stundeneintraege->sum('dauer'), 2),

                // Hier fügen wir das Quartal hinzu
                'quartal' => $quartalName,

                // Zeitraum kommt jetzt idealerweise aus dem Quartalsobjekt
                'zeitraum' => $a->quartal
                    ? $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y')
                    : 'Unbekannt',

                'datumGenehmigtAL' => $genehmigungsLog ? \Carbon\Carbon::parse($genehmigungsLog->modifiedAt)->format('d.m.Y') : '-',
                'genehmigtDurch' => $genehmigerName,
                'details' => $a->stundeneintraege->map(function($e) {
                    return [
                        'EintragID'    => $e->EintragID,
                        'datum' => \Carbon\Carbon::parse($e->datum)->format('Y-m-d'),
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
        $statusFinal = 22;

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
     * [GET] Geschäftsstelle: Historische Abrechnungen
     */
    public function getAbrechnungenHistorieFuerGeschaeftsstelle(Request $request)
    {
        $year = (int) $request->query('year', Carbon::now()->year);
        $quarter = $request->query('quarter');

        // Zeitraum je nach Quartal bestimmen
        if ($quarter === 'Q1') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end   = Carbon::create($year, 3, 31)->endOfDay();
        } elseif ($quarter === 'Q2') {
            $start = Carbon::create($year, 4, 1)->startOfDay();
            $end   = Carbon::create($year, 6, 30)->endOfDay();
        } elseif ($quarter === 'Q3') {
            $start = Carbon::create($year, 7, 1)->startOfDay();
            $end   = Carbon::create($year, 9, 30)->endOfDay();
        } elseif ($quarter === 'Q4') {
            $start = Carbon::create($year, 10, 1)->startOfDay();
            $end   = Carbon::create($year, 12, 31)->endOfDay();
        } else {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end   = Carbon::create($year, 12, 31)->endOfDay();
        }

        // Abrechnungen über das verknüpfte Quartal filtern (beginn/ende)
        $abrechnungen = Abrechnung::with([
                'creator',
                'abteilung',
                'stundeneintraege',
                'quartal',
                'statusLogs' => function ($q) { $q->orderBy('modifiedAt', 'desc'); },
                'statusLogs.statusDefinition',
            ])
            ->whereHas('quartal', function ($q) use ($start, $end) {
                $q->whereBetween('beginn', [$start, $end])
                  ->orWhereBetween('ende', [$start, $end]);
            })
            ->orderBy('AbrechnungID', 'asc')
            ->get();

        $result = $abrechnungen->map(function ($a) {
            $latestLog = $a->statusLogs->first();
            $statusName = $latestLog && $latestLog->statusDefinition
                ? $latestLog->statusDefinition->name
                : 'Unbekannt';

            $zeitraumText = 'Unbekannt';
            if ($a->quartal) {
                $zeitraumText = $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y');
            }

            return [
                'AbrechnungID'    => $a->AbrechnungID,
                'mitarbeiterName' => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung'       => $a->abteilung->name ?? 'Unbekannt',
                'stunden'         => round($a->stundeneintraege->sum('dauer'), 2),
                'zeitraum'        => $zeitraumText,
                'status'          => $statusName,
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * 1. HINZUFÜGEN
     */
    public function addEntry(Request $request)
    {
        $validated = $request->validate([
            'datum'           => 'required|date',
            'beginn'          => 'required|date_format:H:i',
            'ende'            => 'required|date_format:H:i|after:beginn',
            'kurs'            => 'nullable|string',
            'fk_abteilung'    => 'nullable|exists:abteilung_definition,AbteilungID',
            'fk_abrechnungID' => 'required|exists:abrechnung,AbrechnungID',
            'status_id'       => 'required|integer',
        ]);

        $abrechnung = Abrechnung::findOrFail($validated['fk_abrechnungID']);
        $uelId = $abrechnung->createdBy;
        $abteilungId = $abrechnung->fk_abteilung;

        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            DB::transaction(function () use ($validated, $dauer, $uelId, $abteilungId) {
                $eintrag = Stundeneintrag::create([
                    'datum'           => $validated['datum'],
                    'beginn'          => $validated['beginn'],
                    'ende'            => $validated['ende'],
                    'dauer'           => $dauer,
                    'kurs'            => $validated['kurs'] ?? null,
                    'fk_abteilung'    => $validated['fk_abteilung'] ?? $abteilungId,
                    'fk_abrechnungID' => $validated['fk_abrechnungID'],
                    'createdBy'       => $uelId,
                    'createdAt'       => now(),
                ]);

                StundeneintragStatusLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID,
                    'fk_statusID'         => 11,
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
     * 2. BEARBEITEN (Update) MIT AUDIT LOG
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
        ]);

        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            DB::transaction(function () use ($eintrag, $validated, $dauer, $request) {

                // Zeitformat sicherstellen (H:i -> H:i:s)
                $beginnSauber = strlen($validated['beginn']) == 5 ? $validated['beginn'] . ':00' : $validated['beginn'];
                $endeSauber   = strlen($validated['ende'])   == 5 ? $validated['ende']   . ':00' : $validated['ende'];

                // 1. Neue Werte vorbereiten (noch nicht speichern)
                $eintrag->fill([
                    'datum'        => $validated['datum'],
                    'beginn'       => $beginnSauber,
                    'ende'         => $endeSauber,
                    'dauer'        => $dauer,
                    'kurs'         => $validated['kurs'] ?? null,
                    'fk_abteilung' => $validated['fk_abteilung'] ?? $eintrag->fk_abteilung,
                ]);

                // 2. Prüfen auf Änderungen
                if ($eintrag->isDirty()) {

                    $changes = $eintrag->getDirty();
                    $original = $eintrag->getOriginal();

                    // Speichern
                    $eintrag->save();

                    // 3. Audit Logs schreiben (mit smartem Vergleich)
                    foreach ($changes as $field => $newValue) {
                        if ($field === 'updated_at') continue;

                        $oldValue = $original[$field] ?? null;

                        // Datum normalisieren
                        if ($oldValue instanceof \DateTimeInterface) {
                            $oldValue = $oldValue->format('Y-m-d');
                        }

                        // Uhrzeit (nur H:i vergleichen)
                        if (in_array($field, ['beginn', 'ende'])) {
                            $t1 = substr((string)$oldValue, 0, 5);
                            $t2 = substr((string)$newValue, 0, 5);
                            if ($t1 === $t2) continue;
                        }

                        // Dauer (Float Rundung)
                        if ($field === 'dauer') {
                            if (abs((float)$oldValue - (float)$newValue) < 0.01) continue;
                        }

                        // Genereller Check
                        if ($oldValue == $newValue) continue;

                        StundeneintragAuditLog::create([
                            'fk_stundeneintragID' => $eintrag->EintragID,
                            'feldname'            => $field,
                            'alter_wert'          => (string)$oldValue,
                            'neuer_wert'          => (string)$newValue,
                            'modifiedBy'          => Auth::id(),
                            'modifiedAt'          => now(),
                            'kommentar'           => 'Korrektur durch GS'
                        ]);
                    }

                    /*// 4. Status Log
                    StundeneintragStatusLog::create([
                        'fk_stundeneintragID' => $eintrag->EintragID,
                        'fk_statusID'         => $request->input('status_id', $eintrag->aktuellerStatusLog->fk_statusID ?? 11),
                        'modifiedBy'          => Auth::id(),
                        'modifiedAt'          => now(),
                        'kommentar'           => 'Von Geschaeftsstelle korrigiert (siehe Audit)',
                    ]);*/
                }
            });

            return response()->json(['message' => 'Eintrag aktualisiert.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Update fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. LÖSCHEN (Soft-Delete)
     */
    public function deleteEntry(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        if (!$eintrag) {
            return response()->json(['message' => 'Nicht gefunden'], 404);
        }

        try {
            DB::transaction(function () use ($eintrag) {

                // A. Audit Log schreiben (optional, aber gut für Historie)
                // Wir protokollieren, dass der Eintrag aus der Abrechnung entfernt wurde.
                \App\Models\StundeneintragAuditLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID,
                    'feldname'            => 'fk_abrechnungID',
                    'alter_wert'          => (string)$eintrag->fk_abrechnungID,
                    'neuer_wert'          => 'NULL', // Weil wir ihn gleich entkoppeln
                    'modifiedBy'          => Auth::id(),
                    'modifiedAt'          => now(),
                    'kommentar'           => 'Eintrag gelöscht (Soft-Delete)'
                ]);

                // B. Verknüpfung zur Abrechnung entfernen
                // Damit die Stunden nicht mehr zur Summe zählen und er aus der Liste verschwindet.
                $eintrag->update([
                    'fk_abrechnungID' => null
                ]);

                // C. Status auf 12 setzen (Dein Wunsch)
                \App\Models\StundeneintragStatusLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID,
                    'fk_statusID'         => 12, // Status 12 = Gelöscht / Storniert
                    'modifiedBy'          => Auth::id(),
                    'modifiedAt'          => now(),
                    'kommentar'           => 'Gelöscht durch Geschäftsstelle',
                ]);
            });

            return response()->json(['message' => 'Eintrag wurde gelöscht (Status 12).']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Löschen fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/geschaeftsstelle/abrechnungen/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'grund' => 'required|string|min:5',
        ]);

        $gsUserId = Auth::id();
        $grund = $request->input('grund');

        $statusResetA = 24;
        $statusResetS = 12;

        try {
            DB::transaction(function () use ($id, $gsUserId, $grund, $statusResetA, $statusResetS) {

                \App\Models\AbrechnungStatusLog::create([
                    'fk_abrechnungID' => $id,
                    'fk_statusID'     => $statusResetA,
                    'modifiedBy'      => $gsUserId,
                    'modifiedAt'      => now(),
                    'kommentar'       => 'ABGELEHNT DURCH GS: ' . $grund
                ]);

                $eintraege = \App\Models\Stundeneintrag::where('fk_abrechnungID', $id)->get();

                foreach ($eintraege as $eintrag) {
                    \App\Models\StundeneintragStatusLog::create([
                        'fk_stundeneintragID' => $eintrag->EintragID,
                        'fk_statusID'         => $statusResetS,
                        'modifiedBy'          => $gsUserId,
                        'modifiedAt'          => now(),
                        'kommentar'           => 'Rückweisung durch Geschäftsstelle',
                    ]);
                }
            });

            return response()->json(['message' => 'Abrechnung wurde abgelehnt und zurückgewiesen.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Fehler beim Ablehnen: ' . $e->getMessage()], 500);
        }
    }
    public function getAllMitarbeiter()
    {
        // 1. Alle User-Abteilungs-Paare laden.
        $data = DB::table('user')
            ->join('user_rolle_abteilung', 'user.UserID', '=', 'user_rolle_abteilung.fk_userID')
            ->join('abteilung_definition', 'user_rolle_abteilung.fk_abteilungID', '=', 'abteilung_definition.AbteilungID')
            ->select(
                'user.UserID',
                'user.name',
                'user.vorname',
                'user.email',
                'abteilung_definition.name as abteilung_name',
                'abteilung_definition.AbteilungID as abteilung_id'
            )
            // WICHTIG: distinct() sorgt dafür, dass User nur einmal pro Abteilung auftauchen,
            // auch wenn sie dort mehrere Rollen haben.
            ->distinct()
            ->orderBy('user.name')
            ->orderBy('abteilung_definition.name')
            ->get();

        // 2. Den spezifischen Stundensatz für diese Kombination laden
        $result = $data->map(function ($row) {

            $currentRate = DB::table('stundensatz')
                ->where('fk_userID', $row->UserID)
                ->where('fk_abteilungID', $row->abteilung_id)
                ->whereNull('gueltigBis')
                ->orderBy('gueltigVon', 'desc')
                ->first();

            return [
                'id' => $row->UserID,
                'name' => $row->name,
                'vorname' => $row->vorname,
                'email' => $row->email,
                'abteilungen' => $row->abteilung_name,
                'abteilung_id' => $row->abteilung_id,
                'aktuellerSatz' => $currentRate ? $currentRate->satz : null,
                'gueltigSeit' => $currentRate ? $currentRate->gueltigVon : null,
            ];
        });

        return response()->json($result);
    }
}
