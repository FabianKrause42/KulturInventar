<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/session.php';

// Bereits eingeloggt → direkt weiterleiten
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/../src/config/database.php';

$fehler = '';

const MAX_VERSUCHE  = 3;
const SPERRE_MINUTEN = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if ($pin === '') {
        $fehler = 'Bitte PIN eingeben.';
    } elseif ($pdo === null) {
        $fehler = 'Keine Datenbankverbindung.';
    } else {
        // Alle aktiven Nutzer laden und PIN prüfen
        $stmt = $pdo->query(
            'SELECT id, name, pin_code_hash, login_versuche, gesperrt_bis
             FROM users WHERE aktiv = 1'
        );
        $users = $stmt->fetchAll();

        $eingeloggt  = false;
        $user_gefunden = null;

        foreach ($users as $user) {
            if (password_verify($pin, $user['pin_code_hash'])) {
                $user_gefunden = $user;
                $eingeloggt    = true;
                break;
            }
        }

        if ($eingeloggt && $user_gefunden !== null) {
            // Sperre prüfen (könnte noch aktiv sein, auch wenn PIN stimmt)
            if ($user_gefunden['gesperrt_bis'] !== null
                && new DateTime() < new DateTime($user_gefunden['gesperrt_bis'])
            ) {
                $bis = (new DateTime($user_gefunden['gesperrt_bis']))->format('H:i');
                $fehler = 'Zu viele Fehlversuche. Bitte bis ' . $bis . ' Uhr warten.';
            } else {
                // Erfolgreicher Login – Zähler zurücksetzen
                $pdo->prepare(
                    'UPDATE users SET login_versuche = 0, gesperrt_bis = NULL WHERE id = ?'
                )->execute([$user_gefunden['id']]);

                $_SESSION['user_id']   = $user_gefunden['id'];
                $_SESSION['user_name'] = $user_gefunden['name'];

                header('Location: /index.php');
                exit;
            }
        } else {
            // Falsche PIN – gegen alle aktiven Nutzer zählen
            // Wir erhöhen den Zähler für jeden Nutzer, der noch nicht gesperrt ist,
            // da wir nicht wissen, welcher Nutzer gemeint war.
            // Sinnvoller Kompromiss ohne Benutzernamen-Eingabe.
            $pdo->exec(
                'UPDATE users
                 SET
                   login_versuche = login_versuche + 1,
                   gesperrt_bis = CASE
                     WHEN login_versuche + 1 >= ' . MAX_VERSUCHE . '
                     THEN DATE_ADD(NOW(), INTERVAL ' . SPERRE_MINUTEN . ' MINUTE)
                     ELSE gesperrt_bis
                   END
                 WHERE aktiv = 1 AND (gesperrt_bis IS NULL OR gesperrt_bis <= NOW())'
            );

            $fehler = 'Falsche PIN. Bitte erneut versuchen.';

            // Prüfen ob jetzt gesperrt
            $stmt2 = $pdo->query(
                'SELECT MIN(login_versuche) as versuche FROM users WHERE aktiv = 1'
            );
            $row = $stmt2->fetch();
            if ((int)($row['versuche'] ?? 0) >= MAX_VERSUCHE) {
                $fehler = 'Zu viele Fehlversuche. Bitte ' . SPERRE_MINUTEN . ' Minuten warten.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – KulturInventar</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="login-body">

    <main class="login-wrap">

        <?php if ($fehler !== ''): ?>
            <p class="error-message"><?= htmlspecialchars($fehler) ?></p>
        <?php endif; ?>

        <!-- Verstecktes Formular – wird per JS abgeschickt -->
        <form method="post" action="/login.php" id="pin-form">
            <input type="hidden" name="pin" id="pin-value">
        </form>

        <!-- PIN-Anzeige -->
        <div class="pin-display" id="pin-display" aria-live="polite" aria-label="PIN-Eingabe">
            <span class="pin-dot">_</span>
            <span class="pin-dot">_</span>
            <span class="pin-dot">_</span>
            <span class="pin-dot">_</span>
        </div>

        <!-- Nummerntastatur -->
        <div class="pin-pad" role="group" aria-label="Nummernblock">
            <button type="button" class="pin-key" data-digit="1">1</button>
            <button type="button" class="pin-key" data-digit="2">2</button>
            <button type="button" class="pin-key" data-digit="3">3</button>
            <button type="button" class="pin-key" data-digit="4">4</button>
            <button type="button" class="pin-key" data-digit="5">5</button>
            <button type="button" class="pin-key" data-digit="6">6</button>
            <button type="button" class="pin-key" data-digit="7">7</button>
            <button type="button" class="pin-key" data-digit="8">8</button>
            <button type="button" class="pin-key" data-digit="9">9</button>
            <button type="button" class="pin-key pin-key-delete" id="pin-delete" aria-label="Alles löschen">
                <img src="/assets/img/icons/button_delete_red.png" alt="Löschen">
            </button>
            <button type="button" class="pin-key" data-digit="0">0</button>
            <button type="button" class="pin-key pin-key-enter" id="pin-enter" aria-label="Bestätigen">
                <img src="/assets/img/icons/button_enter_green.png" alt="Bestätigen">
            </button>
        </div>

    </main>

    <script>
    (function () {
        var MAX_LEN = 4;
        var pin = '';
        var dots = document.querySelectorAll('.pin-dot');
        var hidden = document.getElementById('pin-value');
        var form   = document.getElementById('pin-form');

        function updateDisplay() {
            dots.forEach(function (dot, i) {
                dot.textContent = i < pin.length ? '\u25CF' : '_';
            });
        }

        document.querySelectorAll('.pin-key[data-digit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (pin.length < MAX_LEN) {
                    pin += btn.dataset.digit;
                    updateDisplay();
                    if (pin.length === MAX_LEN) { submit(); }
                }
            });
        });

        document.getElementById('pin-delete').addEventListener('click', function () {
            pin = '';
            updateDisplay();
        });

        document.getElementById('pin-enter').addEventListener('click', function () {
            if (pin.length > 0) { submit(); }
        });

        function submit() {
            hidden.value = pin;
            form.submit();
        }
    }());
    </script>

</body>
</html>
