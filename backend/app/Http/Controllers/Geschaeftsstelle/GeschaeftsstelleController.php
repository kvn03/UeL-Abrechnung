<?php

namespace App\Http\Controllers\Geschaeftsstelle;

use App\Http\Controllers\Controller;
use App\Models\Abrechnung;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragAuditLog;
use App\Models\StundeneintragStatusLog;
use App\Models\Stundensatz;
use Carbon\Carbon;
use App\Models\Zuschlag;
use Yasumi\Yasumi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// <--- WICHTIG: Import hinzugefügt

class GeschaeftsstelleController extends Controller
{
    /**
     * [GET] Geschäftsstelle: Alle Abrechnungen, die vom AL genehmigt wurden.
     */
    /**
     * [GET] Geschäftsstelle: Alle Abrechnungen, die vom AL genehmigt wurden (Status 21).
     */
    public function getAbrechnungenFuerGeschaeftsstelle(Request $request)
    {
        $statusGenehmigtAL = 21;

        // 1. Abrechnungen laden
        $abrechnungen = \App\Models\Abrechnung::with([
            'creator',
            'abteilung',
            'stundeneintraege',
            'quartal',
            'statusLogs' => function($q) { $q->orderBy('modifiedAt', 'desc'); },
            'statusLogs.modifier'
        ])->get();

        // 2. Filtern
        $filterteAbrechnungen = $abrechnungen->filter(function($a) use ($statusGenehmigtAL) {
            $neuestesLog = $a->statusLogs->first();
            return $neuestesLog && $neuestesLog->fk_statusID == $statusGenehmigtAL;
        });

        // --- NEU: Zuschläge laden ---
        $alleZuschlaege = Zuschlag::orderBy('gueltigVon')->get();

        // 3. Mapping
        $result = $filterteAbrechnungen->map(function($a) use ($statusGenehmigtAL, $alleZuschlaege) {

            // Genehmiger Logik
            $genehmigungsLog = $a->statusLogs->firstWhere('fk_statusID', $statusGenehmigtAL);
            $genehmigerName = ($genehmigungsLog && $genehmigungsLog->modifier)
                ? $genehmigungsLog->modifier->vorname . ' ' . $genehmigungsLog->modifier->name
                : 'Unbekannt';

            // Sätze laden
            $rates = DB::table('stundensatz')
                ->where('fk_userID', $a->createdBy)
                ->where('fk_abteilungID', $a->fk_abteilung)
                ->get();

            // Details berechnen
            $mappedDetails = $a->stundeneintraege->map(function($e) use ($rates, $alleZuschlaege) {
                $eintragDatum = \Carbon\Carbon::parse($e->datum)->startOfDay();

                // Satz finden
                $validRate = $rates->first(function($rate) use ($eintragDatum) {
                    $start = \Carbon\Carbon::parse($rate->gueltigVon)->startOfDay();
                    $end   = $rate->gueltigBis ? \Carbon\Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                    return $eintragDatum->gte($start) && ($end === null || $eintragDatum->lte($end));
                });
                $satz = $validRate ? (float)$validRate->satz : 0;

                // --- NEU: Feiertag + Zuschlag ---
                $provider = Yasumi::create('Germany/NorthRhineWestphalia', $eintragDatum->year);
                $isFeiertag = $provider->isHoliday($eintragDatum);
                $multiplikator = 1.0;

                if ($isFeiertag) {
                    $passenderZuschlag = $alleZuschlaege->first(function($z) use ($eintragDatum) {
                        return $eintragDatum->gte($z->gueltigVon) &&
                            ($z->gueltigBis === null || $eintragDatum->lte($z->gueltigBis));
                    });
                    if ($passenderZuschlag) {
                        $multiplikator = $passenderZuschlag->faktor; // z.B. 1.35
                    }
                }

                $betrag = round($e->dauer * $satz * $multiplikator, 2);

                return [
                    'EintragID' => $e->EintragID,
                    'datum'     => $eintragDatum->format('Y-m-d'),
                    'beginn'    => \Carbon\Carbon::parse($e->beginn)->format('H:i'),
                    'ende'      => \Carbon\Carbon::parse($e->ende)->format('H:i'),
                    'dauer'     => $e->dauer,
                    'kurs'      => $e->kurs,
                    'betrag'    => $betrag,
                    'isFeiertag'=> $isFeiertag // <--- NEU
                ];
            });

            $quartalName = $a->quartal ? $a->quartal->bezeichnung : '';
            $zeitraum = $a->quartal
                ? $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y')
                : 'Unbekannt';

            return [
                'AbrechnungID'     => $a->AbrechnungID,
                'mitarbeiterName'  => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung'        => $a->abteilung->name ?? 'Unbekannt',
                'stunden'          => round($a->stundeneintraege->sum('dauer'), 2),
                'gesamtBetrag'     => $mappedDetails->sum('betrag'),
                'quartal'          => $quartalName,
                'zeitraum'         => $zeitraum,
                'datumGenehmigtAL' => $genehmigungsLog ? \Carbon\Carbon::parse($genehmigungsLog->modifiedAt)->format('d.m.Y') : '-',
                'genehmigtDurch'   => $genehmigerName,
                'details'          => $mappedDetails
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * [GET] Geschäftsstelle: Historie / Archiv
     * Zeigt ALLE Abrechnungen eines Quartals an (egal welcher Status).
     * Inklusive Berechnung der damaligen Kosten.
     */
    public function getAbrechnungenHistorieFuerGeschaeftsstelle(Request $request)
    {
        $year = (int) $request->query('year', Carbon::now()->year);
        $quarter = $request->query('quarter');

        // 1. Zeitraum (Code bleibt gleich...)
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

        // 2. Daten laden
        $abrechnungen = Abrechnung::with([
            'creator',
            'abteilung',
            'stundeneintraege',
            'quartal',
            'statusLogs.statusDefinition',
            'statusLogs.modifier'
        ])
            ->whereHas('quartal', function ($q) use ($start, $end) {
                $q->whereBetween('beginn', [$start, $end])
                    ->orWhereBetween('ende', [$start, $end]);
            })
            ->orderBy('AbrechnungID', 'desc')
            ->get();

        // --- NEU: Zuschläge laden ---
        $alleZuschlaege = Zuschlag::orderBy('gueltigVon')->get();

        // 3. Mapping
        $result = $abrechnungen->map(function ($a) use ($alleZuschlaege) {

            $latestLog = $a->statusLogs->sortByDesc('modifiedAt')->first();
            $statusName = $latestLog && $latestLog->statusDefinition ? $latestLog->statusDefinition->name : 'Unbekannt';
            $statusId = $latestLog ? $latestLog->fk_statusID : 0;

            $rates = DB::table('stundensatz')
                ->where('fk_userID', $a->createdBy)
                ->where('fk_abteilungID', $a->fk_abteilung)
                ->get();

            $mappedDetails = $a->stundeneintraege->map(function($e) use ($rates, $alleZuschlaege) {
                $eintragDatum = Carbon::parse($e->datum)->startOfDay();

                $validRate = $rates->first(function($rate) use ($eintragDatum) {
                    $start = Carbon::parse($rate->gueltigVon)->startOfDay();
                    $end   = $rate->gueltigBis ? Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                    return $eintragDatum->gte($start) && ($end === null || $eintragDatum->lte($end));
                });
                $satz = $validRate ? (float)$validRate->satz : 0;

                // --- NEU: Feiertag + Zuschlag ---
                $provider = Yasumi::create('Germany/NorthRhineWestphalia', $eintragDatum->year);
                $isFeiertag = $provider->isHoliday($eintragDatum);
                $multiplikator = 1.0;

                if ($isFeiertag) {
                    $passenderZuschlag = $alleZuschlaege->first(function($z) use ($eintragDatum) {
                        return $eintragDatum->gte($z->gueltigVon) &&
                            ($z->gueltigBis === null || $eintragDatum->lte($z->gueltigBis));
                    });
                    if ($passenderZuschlag) {
                        $multiplikator = $passenderZuschlag->faktor;
                    }
                }

                $betrag = round($e->dauer * $satz * $multiplikator, 2);

                return [
                    'datum'  => $e->datum,
                    'dauer'  => $e->dauer,
                    'kurs'   => $e->kurs,
                    'betrag' => $betrag,
                    'isFeiertag' => $isFeiertag // <--- NEU
                ];
            });

            $zeitraumText = $a->quartal
                ? $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y')
                : '-';

            return [
                'AbrechnungID'    => $a->AbrechnungID,
                'mitarbeiterName' => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung'       => $a->abteilung->name ?? 'Unbekannt',
                'stunden'         => round($a->stundeneintraege->sum('dauer'), 2),
                'gesamtBetrag'    => $mappedDetails->sum('betrag'),
                'zeitraum'        => $zeitraumText,
                'quartal'         => $a->quartal ? $a->quartal->bezeichnung : '',
                'status'          => $statusName,
                'status_id'       => $statusId,
                'details'         => $mappedDetails,
                'history' => $a->statusLogs->sortByDesc('modifiedAt')->map(function($log) {
                    // ... (History Code bleibt gleich) ...
                    return [
                        'date' => Carbon::parse($log->modifiedAt)->format('d.m.Y H:i'),
                        'status' => $log->statusDefinition->name ?? 'Status',
                        'user' => $log->modifier ? ($log->modifier->vorname.' '.$log->modifier->name) : 'System',
                        'kommentar' => $log->kommentar
                    ];
                })->values()
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

        // 1. Den aktuellsten Status-Log-Eintrag finden
        $currentLog = $abrechnung->statusLogs()
            ->orderBy('modifiedAt', 'desc')
            ->first();

        // Fallback, falls gar kein Status existiert (sollte nicht passieren)
        $currentStatus = $currentLog ? $currentLog->fk_statusID : 0;

        $newStatus = null;
        $kommentar = '';

        // 2. Logik: Welcher Schritt ist dran?
        if ($currentStatus == 21) {
            // Szenario: AL hat genehmigt -> GS gibt zur Zahlung frei
            $newStatus = 22;
            $kommentar = 'Finale Freigabe durch Geschäftsstelle (Wartet auf Zahlung)';
        } elseif ($currentStatus == 22) {
            // Szenario: Wartet auf Zahlung -> GS markiert als bezahlt
            $newStatus = 23;
            $kommentar = 'Auszahlung getätigt / Vorgang abgeschlossen';
        } else {
            // Fehlerfall: Status passt nicht in den Workflow
            return response()->json([
                'message' => 'Status kann nicht fortgesetzt werden. Aktueller Status: ' . $currentStatus
            ], 400);
        }

        // 3. Neuen Status schreiben
        try {
            DB::transaction(function () use ($abrechnung, $newStatus, $userId, $kommentar) {
                \App\Models\AbrechnungStatusLog::create([
                    'fk_abrechnungID' => $abrechnung->AbrechnungID,
                    'fk_statusID'     => $newStatus,
                    'modifiedBy'      => $userId,
                    'modifiedAt'      => now(),
                    'kommentar'       => $kommentar
                ]);
            });

            return response()->json(['message' => 'Status erfolgreich auf ' . $newStatus . ' aktualisiert.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Datenbankfehler beim Speichern.', 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * POST /api/geschaeftsstelle/abrechnungen/finalize-bulk
     * Markiert mehrere Abrechnungen gleichzeitig als bezahlt (Status 23).
     */
    public function finalizeBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:abrechnung,AbrechnungID'
        ]);

        $userId = Auth::id();
        $ids = $validated['ids'];
        $count = 0;

        try {
            DB::transaction(function () use ($ids, $userId, &$count) {
                foreach ($ids as $id) {
                    $abrechnung = Abrechnung::find($id);

                    // Aktuellen Status prüfen
                    $currentLog = $abrechnung->statusLogs()->orderBy('modifiedAt', 'desc')->first();
                    $currentStatus = $currentLog ? $currentLog->fk_statusID : 0;

                    // Nur wenn Status 22 (Wartet auf Zahlung), schalten wir auf 23
                    if ($currentStatus == 22) {
                        \App\Models\AbrechnungStatusLog::create([
                            'fk_abrechnungID' => $id,
                            'fk_statusID'     => 23, // Bezahlt
                            'modifiedBy'      => $userId,
                            'modifiedAt'      => now(),
                            'kommentar'       => 'Sammel-Auszahlung getätigt / Vorgang abgeschlossen'
                        ]);
                        $count++;
                    }
                }
            });

            return response()->json(['message' => "$count Abrechnungen wurden als bezahlt markiert."]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Fehler bei der Verarbeitung.', 'error' => $e->getMessage()], 500);
        }
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
            'kurs'            => 'required|string',
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
            ->join('rolle_definition', 'user_rolle_abteilung.fk_rolleID', '=', 'rolle_definition.RolleID')
            ->where('rolle_definition.bezeichnung', 'Uebungsleiter')
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
    /**
     * Lädt die Historie für die GS (User + Abteilung).
     * GET /api/geschaeftsstelle/stundensatz-historie?user_id=X&abteilung_id=Y
     */
    public function getStundensatzHistorie(Request $request)
    {
        $validated = $request->validate([
            'user_id'      => 'required|integer|exists:user,UserID',
            'abteilung_id' => 'required|integer|exists:abteilung_definition,AbteilungID',
        ]);

        $history = DB::table('stundensatz')
            ->where('fk_userID', $validated['user_id'])
            ->where('fk_abteilungID', $validated['abteilung_id'])
            ->orderBy('gueltigVon', 'desc')
            ->select('satz', 'gueltigVon', 'gueltigBis')
            ->get();

        // Casten auf Float für sauberes JSON
        $history = $history->map(function($entry) {
            $entry->satz = (float) $entry->satz;
            return $entry;
        });

        return response()->json($history);
    }
    /**
     * [GET] Liefert alle Abrechnungen, die bereit zur Auszahlung sind (Status 22).
     */
    public function getAuszahlungen(Request $request)
    {
        $statusReadyForPayment = 22;

        $abrechnungen = \App\Models\Abrechnung::with([
            'creator',
            'abteilung',
            'stundeneintraege',
            'quartal',
            'statusLogs' => function($q) { $q->orderBy('modifiedAt', 'desc'); },
            'statusLogs.modifier'
        ])->get();

        $filterteAbrechnungen = $abrechnungen->filter(function($a) use ($statusReadyForPayment) {
            $neuestesLog = $a->statusLogs->first();
            return $neuestesLog && $neuestesLog->fk_statusID == $statusReadyForPayment;
        });

        // Zuschläge laden
        $alleZuschlaege = \App\Models\Zuschlag::orderBy('gueltigVon')->get();

        $result = $filterteAbrechnungen->map(function($a) use ($alleZuschlaege) {

            $logAL = $a->statusLogs->firstWhere('fk_statusID', 21);
            $alName = ($logAL && $logAL->modifier) ? $logAL->modifier->vorname . ' ' . $logAL->modifier->name : '-';
            $alDatum = $logAL ? \Carbon\Carbon::parse($logAL->modifiedAt)->format('d.m.Y') : '-';

            $logGS = $a->statusLogs->firstWhere('fk_statusID', 22);
            $gsName = ($logGS && $logGS->modifier) ? $logGS->modifier->vorname . ' ' . $logGS->modifier->name : 'GS';
            $gsDatum = $logGS ? \Carbon\Carbon::parse($logGS->modifiedAt)->format('d.m.Y') : '-';

            $rates = DB::table('stundensatz')
                ->where('fk_userID', $a->createdBy)
                ->where('fk_abteilungID', $a->fk_abteilung)
                ->get();

            $mappedDetails = $a->stundeneintraege->map(function($e) use ($rates, $alleZuschlaege) {
                $eintragDatum = \Carbon\Carbon::parse($e->datum)->startOfDay();

                $validRate = $rates->first(function($rate) use ($eintragDatum) {
                    $start = \Carbon\Carbon::parse($rate->gueltigVon)->startOfDay();
                    $end   = $rate->gueltigBis ? \Carbon\Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                    return $eintragDatum->gte($start) && ($end === null || $eintragDatum->lte($end));
                });
                $satz = $validRate ? (float)$validRate->satz : 0;

                // Feiertag + Zuschlag
                $provider = \Yasumi\Yasumi::create('Germany/NorthRhineWestphalia', $eintragDatum->year);
                $isFeiertag = $provider->isHoliday($eintragDatum);
                $multiplikator = 1.0;

                if ($isFeiertag) {
                    $passenderZuschlag = $alleZuschlaege->first(function($z) use ($eintragDatum) {
                        return $eintragDatum->gte($z->gueltigVon) &&
                            ($z->gueltigBis === null || $eintragDatum->lte($z->gueltigBis));
                    });
                    if ($passenderZuschlag) {
                        $multiplikator = $passenderZuschlag->faktor;
                    }
                }

                $betrag = round($e->dauer * $satz * $multiplikator, 2);

                return [
                    'datum'  => $e->datum,
                    'beginn' => $e->beginn,
                    'ende'   => $e->ende,
                    'dauer'  => $e->dauer,
                    'kurs'   => $e->kurs,
                    'betrag' => $betrag,
                    'isFeiertag' => $isFeiertag // <--- HIER WAR DER FEHLER (Das fehlte)
                ];
            });

            $stammdaten = DB::table('user_stammdaten')
                ->where('fk_userID', $a->creator->UserID)
                ->whereNull('gueltigBis')
                ->orderBy('gueltigVon', 'desc')
                ->first();
            $iban = $stammdaten ? $stammdaten->iban : null;

            return [
                'AbrechnungID'     => $a->AbrechnungID,
                'mitarbeiterName'  => $a->creator->vorname . ' ' . $a->creator->name,
                'abteilung'        => $a->abteilung->name ?? 'Unbekannt',
                'stunden'          => round($a->stundeneintraege->sum('dauer'), 2),
                'gesamtBetrag'     => $mappedDetails->sum('betrag'),
                'iban'             => $iban,
                'zeitraum'         => $a->quartal ? $a->quartal->bezeichnung : '-',
                'mitarbeiterID'    => $a->creator->UserID,
                'datumGenehmigtAL' => $alDatum,
                'genehmigtDurchAL' => $alName,
                'datumFreigabeGS'  => $gsDatum,
                'freigabeDurchGS'  => $gsName,
                'strasse' => $stammdaten ? $stammdaten->strasse : '',
                'hausnr'  => $stammdaten ? $stammdaten->hausnr : '',
                'plz'     => $stammdaten ? $stammdaten->plz : '',
                'ort'     => $stammdaten ? $stammdaten->ort : '',
                'details'          => $mappedDetails
            ];
        })->values();

        return response()->json($result);
    }
}
