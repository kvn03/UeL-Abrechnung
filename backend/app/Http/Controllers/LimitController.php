<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRolleAbteilung;
use App\Models\Limit;
use App\Models\Stundeneintrag;
use App\Models\Stundensatz;
use App\Models\RolleDefinition;
use App\Models\Zuschlag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LimitController extends Controller
{
    /**
     * Gibt Limit-Auslastung aller Übungsleiter zurück (basierend auf View)
     * GET /api/limits/trainer-overview?view=abteilungsleiter|geschaeftsstelle
     */
    public function getUebungsleiterLimitOverview(Request $request)
    {
        $validated = $request->validate([
            'view' => 'required|in:abteilungsleiter,geschaeftsstelle',
        ]);

        $currentUser = $request->user();
        $view = $validated['view'];

        // 1. Berechtigungsprüfung
        if ($view === 'abteilungsleiter') {
            if (!$this->isAbteilungsleiter($currentUser)) {
                return response()->json(['message' => 'Zugriff verweigert'], 403);
            }
        } elseif ($view === 'geschaeftsstelle') {
            if (!$currentUser->isGeschaeftsstelle && !$currentUser->isAdmin) {
                return response()->json(['message' => 'Zugriff verweigert'], 403);
            }
        }

        // 2. Hole die Übungsleiter-IDs basierend auf View
        $uebungsleiterIds = $this->getAuthorizedUebungsleiterIds($currentUser, $view);

        if ($uebungsleiterIds->isEmpty()) {
            return response()->json(['limits' => []]);
        }

        // 3. Gültiges Limit laden
        $limitObj = Limit::where('gueltigVon', '<=', now())
            ->where(function ($query) {
                $query->whereNull('gueltigBis')
                    ->orWhere('gueltigBis', '>=', now());
            })
            ->orderBy('gueltigVon', 'desc')
            ->first();

        $limitWert = $limitObj ? $limitObj->wert : 0;

        // 4. Für jeden Übungsleiter Auslastung berechnen
        $limits = [];

        foreach ($uebungsleiterIds as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            // Berechne genutzten Betrag
            $usedAmount = $this->calculateUsedAmount($user);

            // Hole Abteilungsinformationen
            $userDepts = UserRolleAbteilung::where('fk_userID', $userId)
                ->where('fk_rolleID', $this->getUebungsleiterRoleId())
                ->with('abteilung')
                ->get();

            $abteilungsNamen = $userDepts
                ->map(fn ($ura) => $ura->abteilung->name ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $limits[] = [
                'uebungsleiter_id' => $user->UserID,
                'uebungsleiter_name' => trim(($user->vorname ?? '') . ' ' . ($user->name ?? '')),
                'abteilung' => implode(', ', $abteilungsNamen),
                'limit' => $limitWert,
                'usedAmount' => round($usedAmount, 2),
                'remaining' => round($limitWert - $usedAmount, 2),
            ];
        }

        return response()->json(['limits' => $limits]);
    }

    /**
     * Berechnet den genutzten Betrag für einen User (wie im Dashboard)
     */
    private function calculateUsedAmount(User $user): float
    {
        $usedAmount = 0;

        try {
            $year = now()->year;

            // A. Daten laden
            $eintraege = Stundeneintrag::where('createdBy', $user->UserID)
                ->whereYear('datum', $year)
                ->get();

            $stundensaetze = Stundensatz::where('fk_userID', $user->UserID)->get();

            // Zuschläge laden (für den Faktor)
            $alleZuschlaege = \App\Models\Zuschlag::orderBy('gueltigVon')->get();

            // B. Yasumi laden (Fehler abfangen, falls Library fehlt)
            $provider = null;
            if (class_exists(\Yasumi\Yasumi::class)) {
                $provider = \Yasumi\Yasumi::create('Germany/BadenWurttemberg', $year);
            }

            foreach ($eintraege as $eintrag) {
                $datum = Carbon::parse($eintrag->datum)->startOfDay();

                // Passenden Satz finden
                $passenderSatz = $stundensaetze->first(function ($satz) use ($datum, $eintrag) {
                    // Abteilung Check
                    if ($satz->fk_abteilungID != $eintrag->fk_abteilung) return false;

                    // Datum Check
                    $start = Carbon::parse($satz->gueltigVon)->startOfDay();
                    $end = $satz->gueltigBis ? Carbon::parse($satz->gueltigBis)->endOfDay() : null;

                    return $datum->gte($start) && ($end === null || $datum->lte($end));
                });

                if ($passenderSatz) {
                    $basisSatz = (float)$passenderSatz->satz;
                    $multiplikator = 1.0; // Standard 100%

                    // Feiertags-Check
                    if ($provider && $provider->isHoliday($datum)) {
                        // Passenden Zuschlag aus DB suchen
                        $zuschlagRegel = $alleZuschlaege->first(function($z) use ($datum) {
                            $zStart = Carbon::parse($z->gueltigVon)->startOfDay();
                            $zEnd = $z->gueltigBis ? Carbon::parse($z->gueltigBis)->endOfDay() : null;

                            return $datum->gte($zStart) && ($zEnd === null || $datum->lte($zEnd));
                        });

                        if ($zuschlagRegel) {
                            // Faktor übernehmen (z.B. 1.35)
                            $multiplikator = (float)$zuschlagRegel->faktor;
                        }
                    }

                    // Berechnung: Dauer * Satz * Multiplikator
                    $usedAmount += round($eintrag->dauer * $basisSatz * $multiplikator, 2);
                }
            }

        } catch (\Exception $e) {
            // Fehler loggen, damit Berechnung nicht abstürzt
            \Illuminate\Support\Facades\Log::error("LimitController Calculation Error: " . $e->getMessage());
        }

        return round($usedAmount, 2);
    }

    /**
     * Gibt die Übungsleiter-IDs zurück, die der User sehen darf
     */
    private function getAuthorizedUebungsleiterIds(User $currentUser, string $view)
    {
        $uebungsleiterRoleId = $this->getUebungsleiterRoleId();

        if ($view === 'geschaeftsstelle') {
            return UserRolleAbteilung::where('fk_rolleID', $uebungsleiterRoleId)
                ->distinct()
                ->pluck('fk_userID');
        } else {
            // Alle Abteilungen des Abteilungsleiters holen
            $myDepartmentIds = UserRolleAbteilung::where('fk_userID', $currentUser->UserID)
                ->where('fk_rolleID', $this->getAbteilungsleiterRoleId())
                ->distinct()
                ->pluck('fk_abteilungID');

            if ($myDepartmentIds->isEmpty()) {
                return collect();
            }

            // Alle Übungsleiter in diesen Abteilungen
            return UserRolleAbteilung::whereIn('fk_abteilungID', $myDepartmentIds)
                ->where('fk_rolleID', $uebungsleiterRoleId)
                ->distinct()
                ->pluck('fk_userID');
        }
    }


    /**
     * Prüft, ob User Abteilungsleiter ist
     */
    private function isAbteilungsleiter(User $user): bool
    {
        return UserRolleAbteilung::where('fk_userID', $user->UserID)
            ->where('fk_rolleID', $this->getAbteilungsleiterRoleId())
            ->exists();
    }

    /**
     * Gibt die Rollen-ID für Abteilungsleiter zurück
     */
    private function getAbteilungsleiterRoleId(): int
    {
        return RolleDefinition::where('bezeichnung', 'Abteilungsleiter')
            ->first()
            ?->RolleID ?? 1;
    }

    /**
     * Gibt die Rollen-ID für Übungsleiter zurück
     */
    private function getUebungsleiterRoleId(): int
    {
        return RolleDefinition::where('bezeichnung', 'Uebungsleiter')
            ->first()
            ?->RolleID ?? 2;
    }
}
