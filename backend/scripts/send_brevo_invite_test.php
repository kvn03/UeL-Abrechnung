<?php
/**
 * Quick-Test: erstellt (oder findet) einen Test-User, erzeugt ein Password-Reset Token
 * und sendet die Invite-Mail über Brevo API.
 *
 * Usage (PowerShell):
 *   cd backend
 *   php scripts/send_brevo_invite_test.php test@example.com "Max" "Mustermann"
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Mail\UserInviteMail;
use App\Models\User;
use App\Services\BrevoMailService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Password;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? null;
$vorname = $argv[2] ?? 'Test';
$name = $argv[3] ?? 'User';

if (!$email) {
    fwrite(STDERR, "Fehler: Bitte Empfänger-Email übergeben.\n");
    fwrite(STDERR, "Beispiel: php scripts/send_brevo_invite_test.php test@example.com \"Max\" \"Mustermann\"\n");
    exit(1);
}

$user = User::where('email', $email)->first();
if (!$user) {
    $user = User::create([
        'email' => $email,
        'vorname' => $vorname,
        'name' => $name,
        'password' => null,
        'isAdmin' => false,
        'isGeschaeftsstelle' => false,
    ]);
    echo "User angelegt: {$user->UserID} ({$user->email})\n";
} else {
    echo "User gefunden: {$user->UserID} ({$user->email})\n";
}

$token = Password::createToken($user);
$mailable = new UserInviteMail($user, $token);

$html = $mailable->render();
$subject = $mailable->envelope()->subject ?? 'Willkommen! Bitte Passwort setzen';

app(BrevoMailService::class)->sendTransactional(
    toEmail: $user->email,
    toName: trim(($user->vorname ?? '') . ' ' . ($user->name ?? '')),
    subject: $subject,
    htmlContent: $html
);

$frontendBase = rtrim((string) config('app.frontend_url', 'http://localhost:5173/'), '/');
$inviteLink = $frontendBase . '/set-password?' . http_build_query([
    'token' => $token,
    'email' => $user->email,
]);

echo "Invite Mail gesendet an {$user->email}\n";
echo "Invite-Link (zum Kopieren):\n";
echo $inviteLink . "\n";
