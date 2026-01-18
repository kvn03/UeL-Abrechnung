<?php

namespace App\Http\Controllers\Abteilungsleiter;

use App\Http\Controllers\Controller;
use App\Models\Abrechnung;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragStatusLog;
use App\Models\UserRolleAbteilung;
use Carbon\Carbon;
use App\Models\Zuschlag;
use Yasumi\Yasumi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// <--- Hinzufügen

class AbteilungsleiterController extends Controller
{
    /**
     * HILFSFUNKTION: Prüft, ob der aktuelle User Abteilungsleiter
     * für die angegebene Abteilung ist.
     */
    private function isAbteilungsleiter(Request $request, $abteilungId)
    {
        $userId = $request->user()->UserID; // oder ->id

        // Administrator darf alles (optional)
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
            'kurs'            => 'required|string',
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
        // NEU: Wir holen die Abteilungs-ID direkt aus der Abrechnung,
        // falls sie im Request nicht explizit drin war.
        $abteilungId = $abrechnung->fk_abteilung;

        // Dauer berechnen
        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            // Variable $uelId an die Closure übergeben ('use')
            DB::transaction(function () use ($validated, $dauer, $uelId, $abteilungId) {
                // Eintrag erstellen
                $eintrag = Stundeneintrag::create([
                    'datum'           => $validated['datum'],
                    'beginn'          => $validated['beginn'],
                    'ende'            => $validated['ende'],
                    'dauer'           => $dauer,
                    'kurs'            => $validated['kurs'] ?? null,
                    'fk_abteilung'    => $validated['fk_abteilung'] ?? $abteilungId,
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
    /**
     * 2. BEARBEITEN (Update) mit Audit-Log
     */
    public function updateEntry(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        if (!$eintrag) {
            return response()->json(['message' => 'Eintrag nicht gefunden'], 404);
        }

        // Berechtigungsprüfung
        if ($eintrag->fk_abteilung) {
            if (!$this->isAbteilungsleiter($request, $eintrag->fk_abteilung)) {
                return response()->json(['message' => 'Keine Berechtigung (Kein AL dieser Abteilung).'], 403);
            }
        }

        $validated = $request->validate([
            'datum'         => 'required|date',
            'beginn'        => 'required|date_format:H:i', // Format H:i reicht für Input
            'ende'          => 'required|date_format:H:i|after:beginn',
            'kurs'          => 'nullable|string',
            'fk_abteilung'  => 'nullable|exists:abteilung_definition,AbteilungID',
        ]);

        // Dauer berechnen
        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            DB::transaction(function () use ($eintrag, $validated, $dauer, $request) {

                // 1. Neue Werte erst einmal "befüllen" (aber noch nicht speichern!)
                //    Wir formatieren die Zeiten auf H:i:s, damit der Vergleich mit der DB sauber klappt
                $eintrag->fill([
                    'datum'        => $validated['datum'],
                    'beginn'       => $validated['beginn'], // Ggf. . ':00' anhängen, falls DB H:i:s erwartet
                    'ende'         => $validated['ende'],
                    'dauer'        => $dauer,
                    'kurs'         => $validated['kurs'] ?? null,
                    'fk_abteilung' => $validated['fk_abteilung'] ?? $eintrag->fk_abteilung,
                ]);

                // 2. Prüfen, was sich geändert hat
                if ($eintrag->isDirty()) {

                    // Array der geänderten Felder (Feldname => Neuer Wert)
                    $changes = $eintrag->getDirty();
                    // Die Originalwerte vor dem fill()
                    $original = $eintrag->getOriginal();

                    // 3. Eintrag speichern
                    $eintrag->save();

                    // 4. Audit Logs schreiben für jedes geänderte Feld
                    // ... (innerhalb von updateEntry, nach $eintrag->save()) ...

                    // 4. Audit Logs schreiben (MIT VERGLEICHS-LOGIK)
                    foreach ($changes as $field => $newValue) {

                        // Ignorieren: Systemfelder
                        if ($field === 'updated_at') continue;

                        $oldValue = $original[$field] ?? null;

                        // --- FIX: Werte normalisieren für den Vergleich ---

                        // 1. Datum: Falls alter Wert ein Objekt ist (Carbon), mach einen String draus
                        if ($oldValue instanceof \DateTimeInterface) {
                            $oldValue = $oldValue->format('Y-m-d');
                        }

                        // 2. Uhrzeit: Wir vergleichen nur die ersten 5 Zeichen (10:00 vs 10:00:00)
                        if (in_array($field, ['beginn', 'ende'])) {
                            $t1 = substr((string)$oldValue, 0, 5);
                            $t2 = substr((string)$newValue, 0, 5);

                            // Wenn "10:00" == "10:00", überspringen wir das Loggen
                            if ($t1 === $t2) continue;
                        }

                        // 3. Dauer: Float-Vergleich (gegen Rundungsfehler)
                        if ($field === 'dauer') {
                            // Wenn Differenz kleiner als 0.01 ist, betrachten wir es als gleich
                            if (abs((float)$oldValue - (float)$newValue) < 0.01) continue;
                        }

                        // 4. Genereller Check (Loose Comparison fängt "1" == 1 ab)
                        if ($oldValue == $newValue) continue;

                        // --------------------------------------------------

                        \App\Models\StundeneintragAuditLog::create([
                            'fk_stundeneintragID' => $eintrag->EintragID,
                            'feldname'            => $field,
                            'alter_wert'          => (string)$oldValue,
                            'neuer_wert'          => (string)$newValue,
                            'modifiedBy'          => Auth::id(),
                            'modifiedAt'          => now(),
                            'kommentar'           => 'Korrektur durch AL'
                        ]);
                    }

                    // 5. Status Log schreiben (Allgemeiner Hinweis, dass bearbeitet wurde)
                    /*StundeneintragStatusLog::create([
                        'fk_stundeneintragID' => $eintrag->EintragID,
                        'fk_statusID'         => $request->input('status_id', $eintrag->aktuellerStatusLog->fk_statusID ?? 11),
                        'modifiedBy'          => Auth::id(),
                        'modifiedAt'          => now(),
                        'kommentar'           => 'Bearbeitet (siehe Audit Log)',
                    ]);*/
                }
            });

            return response()->json(['message' => 'Eintrag aktualisiert.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Update fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }


     /* 3. LÖSCHEN (Soft-Delete: Status auf 12 setzen)
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
                    'kommentar'           => 'Gelöscht durch Abteilungsleiter',
                ]);
            });

            return response()->json(['message' => 'Eintrag wurde gelöscht (Status 12).']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Löschen fehlgeschlagen', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * [GET] Liefert alle Abrechnungen, die der Abteilungsleiter freigeben darf.
     */
    public function getOffeneAbrechnungen(Request $request)
    {
        try {
            $userId = Auth::id();

            // 1. Abteilungen laden
            $managedAbteilungIds = UserRolleAbteilung::where('fk_userID', $userId)
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
                    'quartal',
                    'statusLogs' => function($q) { $q->orderBy('modifiedAt', 'desc'); },
                ])
                ->get();

            // 3. Nur Status 20 (Offen für AL) filtern
            $offeneAbrechnungen = $abrechnungen->filter(function($abrechnung) {
                $neuestesLog = $abrechnung->statusLogs->first();
                return $neuestesLog && $neuestesLog->fk_statusID == 20;
            });

            // --- NEU: Alle Zuschlags-Regeln laden (Performance: nur 1 DB Query) ---
            $alleZuschlaege = Zuschlag::orderBy('gueltigVon')->get();

            // 4. Daten mappen
            $result = $offeneAbrechnungen->map(function($a) use ($alleZuschlaege) {

                // Stundensätze laden
                $rates = DB::table('stundensatz')
                    ->where('fk_userID', $a->createdBy)
                    ->where('fk_abteilungID', $a->fk_abteilung)
                    ->get();

                // Details berechnen
                $mappedDetails = $a->stundeneintraege->map(function($eintrag) use ($rates, $alleZuschlaege) {

                    $eintragDatum = Carbon::parse($eintrag->datum)->startOfDay();

                    // A. Passenden Stundensatz suchen
                    $validRate = $rates->first(function($rate) use ($eintragDatum) {
                        $start = Carbon::parse($rate->gueltigVon)->startOfDay();
                        $end   = $rate->gueltigBis ? Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                        return $eintragDatum->gte($start) && ($end === null || $eintragDatum->lte($end));
                    });

                    $stundensatz = $validRate ? (float)$validRate->satz : 0;

                    // B. Feiertag prüfen (Yasumi)
                    $provider = Yasumi::create('Germany/NorthRhineWestphalia', $eintragDatum->year);
                    $isFeiertag = $provider->isHoliday($eintragDatum);

                    $multiplikator = 1.0;
                    $faktorZuschlag = 0; // Standard: Kein Zuschlag

                    if ($isFeiertag) {
                        // C. Passenden Zuschlag aus DB suchen
                        $passenderZuschlag = $alleZuschlaege->first(function($z) use ($eintragDatum) {
                            $start = $z->gueltigVon;
                            $end = $z->gueltigBis;
                            return $eintragDatum->gte($start) &&
                                ($end === null || $eintragDatum->lte($end));
                        });

                        if ($passenderZuschlag) {
                            // WICHTIG: Wir übernehmen den Wert direkt (1.35)
                            $multiplikator = $passenderZuschlag->faktor;

                            // Optional: Fürs Frontend ausrechnen, wie viel % das sind (z.B. 0.35)
                            $faktorZuschlag = $multiplikator - 1.0;
                        }
                    }

                    // Berechnung: Stunden * Satz * (1 + Zuschlag)
                    // Beispiel: 10€ * (1.0 + 0.5) = 15€
                    $betrag = round($eintrag->dauer * $stundensatz * $multiplikator, 2);

                    return [
                        'EintragID' => $eintrag->EintragID,
                        'datum'     => $eintragDatum->format('Y-m-d'),
                        'beginn'    => Carbon::parse($eintrag->beginn)->format('H:i'),
                        'ende'      => Carbon::parse($eintrag->ende)->format('H:i'),
                        'dauer'     => $eintrag->dauer,
                        'kurs'      => $eintrag->kurs,
                        'betrag'    => $betrag,
                        'isFeiertag'=> $isFeiertag,
                        'zuschlagFaktor' => $faktorZuschlag // Optional für Frontend Info
                    ];
                });

                // Metadaten zusammenbauen
                $quartalName = $a->quartal ? $a->quartal->bezeichnung : 'Unbekannt';
                $zeitraumString = $a->quartal
                    ? $a->quartal->beginn->format('d.m.Y') . ' - ' . $a->quartal->ende->format('d.m.Y')
                    : '-';

                return [
                    'AbrechnungID'     => $a->AbrechnungID,
                    'mitarbeiterName'  => $a->creator ? ($a->creator->vorname . ' ' . $a->creator->name) : 'Unbekannt',
                    'quartal'          => $quartalName,
                    'zeitraum'         => $zeitraumString,
                    'stunden'          => round($a->stundeneintraege->sum('dauer'), 2),
                    'gesamtBetrag'     => $mappedDetails->sum('betrag'),
                    'datumEingereicht' => $a->createdAt ? $a->createdAt->format('d.m.Y') : '-',
                    'details'          => $mappedDetails,
                ];
            })->values();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fehler in getOffeneAbrechnungen',
                'error' => $e->getMessage()
            ], 500);
        }
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
    // In App\Http\Controllers\AbteilungsleiterController.php

    public function reject(Request $request, $id)
    {
        // Validierung: Ein Grund ist Pflicht!
        $request->validate([
            'grund' => 'required|string|min:5',
        ]);

        $adminId = Auth::id();
        $grund = $request->input('grund');

        // Status-IDs (an deine DB anpassen)
        // 10 = Offen / Korrektur erforderlich (Damit der User es wieder bearbeiten kann)
        // 90 = Abgelehnt (Falls du einen expliziten Status willst)
        $statusNeuA = 24;
        $statusNeuS = 12;

        try {
            DB::transaction(function () use ($id, $adminId, $statusNeuA, $statusNeuS, $grund) {
                // 1. Abrechnung Status ändern
                $abrechnung = Abrechnung::findOrFail($id);
                // Optional: Prüfen, ob sie überhaupt im Status "Eingereicht" (11) ist

                // 2. Log für Abrechnung schreiben
                \App\Models\AbrechnungStatusLog::create([
                    'fk_abrechnungID' => $id,
                    'fk_statusID'     => $statusNeuA,
                    'modifiedBy'      => $adminId,
                    'modifiedAt'      => now(),
                    'kommentar'       => 'ABGELEHNT: ' . $grund
                ]);

                // 3. Alle zugehörigen Stundeneinträge auch zurücksetzen
                // Damit der Mitarbeiter diese wieder bearbeiten kann
                $eintraege = Stundeneintrag::where('fk_abrechnungID', $id)->get();

                foreach($eintraege as $eintrag) {
                    // Log für den Eintrag
                    StundeneintragStatusLog::create([
                        'fk_stundeneintragID' => $eintrag->EintragID,
                        'fk_statusID'         => $statusNeuS,
                        'modifiedBy'          => $adminId,
                        'modifiedAt'          => now(),
                        'kommentar'           => 'Abrechnung abgelehnt.',
                    ]);
                }
            });

            return response()->json(['message' => 'Abrechnung wurde abgelehnt und zur Korrektur zurückgewiesen.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Fehler beim Ablehnen: ' . $e->getMessage()], 500);
        }
    }
    /**
     * [GET] Abteilungsleiter: Historie / Archiv
     * Zeigt ALLE Abrechnungen (Status egal), aber NUR für Abteilungen, die er leitet.
     */
    public function getAbrechnungenHistorie(Request $request)
    {
        $userId = Auth::id();
        $year = (int) $request->query('year', Carbon::now()->year);
        $quarter = $request->query('quarter');

        // 1. Abteilungen laden
        $managedAbteilungIds = \App\Models\UserRolleAbteilung::where('fk_userID', $userId)
            ->whereHas('rolle', function($q) {
                $q->where('bezeichnung', 'Abteilungsleiter');
            })
            ->pluck('fk_abteilungID');

        if ($managedAbteilungIds->isEmpty()) {
            return response()->json([]);
        }

        // 2. Zeitraum bestimmen (wie gehabt...)
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

        // 3. Daten laden
        $abrechnungen = Abrechnung::whereIn('fk_abteilung', $managedAbteilungIds)
            ->with([
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

        // 4. Mapping
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

                // Stundensatz suchen
                $validRate = $rates->first(function($rate) use ($eintragDatum) {
                    $start = Carbon::parse($rate->gueltigVon)->startOfDay();
                    $end   = $rate->gueltigBis ? Carbon::parse($rate->gueltigBis)->endOfDay() : null;
                    return $eintragDatum->gte($start) && ($end === null || $eintragDatum->lte($end));
                });
                $stundensatz = $validRate ? (float)$validRate->satz : 0;

                // Feiertag & Zuschlag prüfen
                $provider = Yasumi::create('Germany/NorthRhineWestphalia', $eintragDatum->year);
                $isFeiertag = $provider->isHoliday($eintragDatum);

                $faktorZuschlag = 0;

                // ÄNDERUNG: Standard 1.0
                $multiplikator = 1.0;

                if ($isFeiertag) {
                    $passenderZuschlag = $alleZuschlaege->first(function($z) use ($eintragDatum) {
                        return $eintragDatum->gte($z->gueltigVon) &&
                            ($z->gueltigBis === null || $eintragDatum->lte($z->gueltigBis));
                    });

                    if ($passenderZuschlag) {
                        // WICHTIG: Direkt den DB-Wert nehmen (1.35)
                        $multiplikator = $passenderZuschlag->faktor;
                    }
                }

                $betrag = round($e->dauer * $stundensatz * $multiplikator, 2);

                return [
                    'datum'  => $e->datum,
                    'dauer'  => $e->dauer,
                    'kurs'   => $e->kurs,
                    'betrag' => $betrag,
                    'isFeiertag' => $isFeiertag
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
                    // ... dein bestehender History Code ...
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
}
