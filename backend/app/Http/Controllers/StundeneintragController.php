<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stundeneintrag;
use App\Models\StundeneintragStatusLog;
use Illuminate\Support\Facades\DB; // Wichtig für Transactions
use Illuminate\Support\Facades\Auth; // Um den User zu holen
use Carbon\Carbon; // Für Zeitberechnungen

class StundeneintragController extends Controller
{
    /**
     * Speichert einen neuen Stundeneintrag.
     */
    public function store(Request $request)
    {
        // 1. Validierung der Eingaben
        $validated = $request->validate([
            'datum'         => 'required|date',
            'beginn'        => 'required|date_format:H:i',
            'ende'          => 'required|date_format:H:i|after:beginn', // Ende muss nach Beginn sein
            'kurs'          => 'nullable|string',
            'fk_abteilung'  => 'nullable|exists:abteilung_definition,AbteilungID', // Prüft, ob Abteilung existiert
            'status_id'         => 'required|integer|in:10,11,12',
        ]);

        // 2. Dauer automatisch berechnen (optional, aber praktisch)
        // Wir nehmen an, Dauer ist ein Double (Industriestunden, z.B. 1.5 für 1h 30m)
        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);

        // Berechnet Differenz in Stunden als Dezimalzahl
        $dauer = $start->diffInMinutes($end) / 60;

        // 3. Speichern in einer Transaktion
        try {
            DB::transaction(function () use ($validated, $dauer) {

                // A. Den Stundeneintrag erstellen
                $eintrag = Stundeneintrag::create([
                    'datum'           => $validated['datum'],
                    'beginn'          => $validated['beginn'],
                    'ende'            => $validated['ende'],
                    'dauer'           => $dauer,
                    'kurs'            => $validated['kurs'] ?? null,
                    'fk_abteilung'    => $validated['fk_abteilung'] ?? null,
                    // Aktuell eingeloggter User als Ersteller
                    'createdBy'       => Auth::id(),
                    // Automatisch aktueller Zeitstempel (falls nicht durch DB gesetzt)
                    'createdAt'       => now(),
                ]);


                StundeneintragStatusLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID, // Die ID vom gerade erstellten Eintrag
                    'fk_statusID'         => $validated['status_id'],
                    'modifiedBy'          => Auth::id(),
                    'modifiedAt'          => now(),
                    'kommentar'           => 'Stundeneintrag erstellt',
                ]);
            });

        } catch (\Exception $e) {
            // HIER ÄNDERN: Immer JSON zurückgeben bei Fehler
            return response()->json([
                'message' => 'Fehler beim Speichern',
                'error' => $e->getMessage()
            ], 500);
        }

        // HIER ÄNDERN: Am Ende der Funktion immer JSON zurückgeben (kein redirect!)
            return response()->json([
                'message' => 'Stundeneintrag erfolgreich erstell',
            ], 201);
    }

    /**
     * Gibt alle Einträge zurück, deren aktueller Status "Entwurf" (ID 4) ist.
     */
    public function getEntwuerfe(Request $request)
    {
        $userId = $request->user()->UserID; // Oder ->id, je nach Model

        // 1. Alle Einträge des Users laden
        // Wir laden 'abteilung' und 'aktuellerStatusLog' direkt mit (Eager Loading)
        $eintraege = Stundeneintrag::where('createdBy', $userId)
            ->with(['abteilung', 'aktuellerStatusLog'])
            ->orderBy('datum', 'desc') // Neueste zuerst
            ->get();

        // 2. Filtern: Wir wollen nur die, wo der NEUESTE Status == 4 (Entwurf) ist
        $entwuerfe = $eintraege->filter(function ($eintrag) {
            // Prüfen, ob es überhaupt einen Status gibt und ob dieser 4 ist
            return $eintrag->aktuellerStatusLog && $eintrag->aktuellerStatusLog->fk_statusID == 10;
        })->values(); // Keys zurücksetzen für sauberes JSON Array

        return response()->json($entwuerfe);
    }

    /**
     * Löscht einen Stundeneintrag (und via Cascade auch die Logs).
     */
    public function deleteEintrag(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        if (!$eintrag) {
            return response()->json(['message' => 'Eintrag nicht gefunden.'], 404);
        }

        $user = $request->user();
        $isOwner = $eintrag->createdBy == $user->UserID;
        $isGs    = $user && property_exists($user, 'isGeschaeftsstelle') ? $user->isGeschaeftsstelle : false;

        // Nur Owner oder Geschäftsstelle dürfen löschen
        if ($isOwner && $isGs) {
            return response()->json(['message' => 'Dazu bist du nicht berechtigt.'], 403);
        }

        $eintrag->delete();

        return response()->json(['message' => 'Eintrag erfolgreich gelöscht.']);
    }

    /**
     * Lädt einen einzelnen Eintrag zum Bearbeiten
     */
    public function show(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        $user = $request->user();
        $isOwner = $eintrag && $eintrag->createdBy == $user->UserID;
        $isGs    = $user && property_exists($user, 'isGeschaeftsstelle') ? $user->isGeschaeftsstelle : false;

        // Nur Owner oder Geschäftsstelle dürfen laden
        if (!$eintrag || (!$isOwner && !$isGs)) {
            return response()->json(['message' => 'Nicht gefunden oder kein Zugriff'], 403);
        }

        return response()->json($eintrag);
    }

    /**
     * Aktualisiert einen bestehenden Eintrag
     */
    public function update(Request $request, $id)
    {
        $eintrag = Stundeneintrag::find($id);

        $user = $request->user();
        $isOwner = $eintrag && $eintrag->createdBy == $user->UserID;
        $isGs    = $user && property_exists($user, 'isGeschaeftsstelle') ? $user->isGeschaeftsstelle : false;

        // Nur Owner oder Geschäftsstelle dürfen aktualisieren
        if (!$eintrag || (!$isOwner && !$isGs)) {
            return response()->json(['message' => 'Kein Zugriff'], 403);
        }

        // 1. Validierung (fast gleich wie store, status_id wieder wichtig)
        $validated = $request->validate([
            'datum'         => 'required|date',
            'beginn'        => 'required|date_format:H:i',
            'ende'          => 'required|date_format:H:i|after:beginn',
            'kurs'          => 'nullable|string',
            'fk_abteilung'  => 'nullable|exists:abteilung_definition,AbteilungID',
            'status_id'     => 'required|integer|in:10,11,12',
        ]);

        $start = Carbon::createFromFormat('H:i', $validated['beginn']);
        $end   = Carbon::createFromFormat('H:i', $validated['ende']);
        $dauer = $start->diffInMinutes($end) / 60;

        try {
            DB::transaction(function () use ($validated, $dauer, $eintrag, $request) {

                // A. Hauptdatensatz updaten
                $eintrag->update([
                    'datum'        => $validated['datum'],
                    'beginn'       => $validated['beginn'],
                    'ende'         => $validated['ende'],
                    'dauer'        => $dauer,
                    'kurs'         => $validated['kurs'] ?? null,
                    'fk_abteilung' => $validated['fk_abteilung'] ?? null,
                    // createdBy bleibt unverändert!
                ]);

                // B. NEUEN Status-Log Eintrag schreiben (Historie fortschreiben)
                StundeneintragStatusLog::create([
                    'fk_stundeneintragID' => $eintrag->EintragID,
                    'fk_statusID'         => $validated['status_id'],
                    'modifiedBy'          => $request->user()->UserID,
                    'modifiedAt'          => now(),
                    'kommentar'           => ($validated['status_id'] == 10) ? 'Entwurf aktualisiert' : 'Entwurf final eingereicht',
                ]);
            });

            return response()->json(['message' => 'Erfolgreich aktualisiert']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Fehler beim Update', 'error' => $e->getMessage()], 500);
        }
    }
}
