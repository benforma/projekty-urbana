<?php
/**
 * =============================================================
 * KONFIGURACJA SMTP — uzupełnij danymi swojego serwera poczty
 * =============================================================
 */
$config = [
    'smtp_host'     => 'smtp.example.com',      // np. smtp.gmail.com, poczta.o2.pl, ssl0.ovh.net
    'smtp_port'     => 587,                       // 587 (TLS) lub 465 (SSL)
    'smtp_user'     => 'twoj@email.pl',          // login SMTP
    'smtp_pass'     => 'twoje_haslo',            // hasło SMTP
    'smtp_secure'   => 'tls',                     // 'tls' lub 'ssl'
    'mail_from'     => 'twoj@email.pl',          // adres nadawcy
    'mail_from_name'=> 'Miejski Krajobraz',      // nazwa nadawcy
    'mail_to'       => 'mateusz.bucko@4ma.pl',   // adres docelowy
    'mail_subject'  => 'Nowe zapytanie o projekt placu zabaw',
];

/**
 * =============================================================
 * Poniżej nie trzeba nic zmieniać
 * =============================================================
 */

// Nagłówki CORS i JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metoda niedozwolona']);
    exit;
}

// Odbierz dane
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Brak danych']);
    exit;
}

$imie    = htmlspecialchars(strip_tags($input['imie'] ?? ''));
$email   = htmlspecialchars(strip_tags($input['email'] ?? ''));
$telefon = htmlspecialchars(strip_tags($input['telefon'] ?? ''));
$uwagi   = htmlspecialchars(strip_tags($input['uwagi'] ?? ''));

// Walidacja
if (empty($imie) || empty($email) || empty($telefon) || empty($uwagi)) {
    echo json_encode(['success' => false, 'message' => 'Wypełnij wszystkie pola']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowy adres e-mail']);
    exit;
}

// Treść maila (HTML)
$body = "
<html>
<head><meta charset='utf-8'></head>
<body style='font-family: Arial, sans-serif; color: #333;'>
    <h2 style='color: #3AAD71; border-bottom: 2px solid #3AAD71; padding-bottom: 10px;'>
        Nowe zapytanie ze strony Miejski Krajobraz
    </h2>
    <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
        <tr style='background: #f5f5f5;'>
            <td style='padding: 12px; font-weight: bold; width: 140px; border: 1px solid #ddd;'>Imię</td>
            <td style='padding: 12px; border: 1px solid #ddd;'>{$imie}</td>
        </tr>
        <tr>
            <td style='padding: 12px; font-weight: bold; border: 1px solid #ddd;'>E-mail</td>
            <td style='padding: 12px; border: 1px solid #ddd;'><a href='mailto:{$email}'>{$email}</a></td>
        </tr>
        <tr style='background: #f5f5f5;'>
            <td style='padding: 12px; font-weight: bold; border: 1px solid #ddd;'>Telefon</td>
            <td style='padding: 12px; border: 1px solid #ddd;'><a href='tel:{$telefon}'>{$telefon}</a></td>
        </tr>
        <tr>
            <td style='padding: 12px; font-weight: bold; border: 1px solid #ddd;'>Uwagi</td>
            <td style='padding: 12px; border: 1px solid #ddd;'>" . nl2br($uwagi) . "</td>
        </tr>
    </table>
    <p style='margin-top: 20px; font-size: 12px; color: #999;'>
        Wiadomość wysłana automatycznie ze strony mkrajobraz.pl
    </p>
</body>
</html>
";

// --- Sprawdź czy jest PHPMailer, jeśli nie — użyj wbudowanego mail() ---

$phpmailer_path = __DIR__ . '/vendor/autoload.php';

if (file_exists($phpmailer_path)) {
    // ========== WYSYŁKA PRZEZ PHPMAILER (SMTP) ==========
    require $phpmailer_path;

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['mail_from'], $config['mail_from_name']);
        $mail->addAddress($config['mail_to']);
        $mail->addReplyTo($email, $imie);

        $mail->isHTML(true);
        $mail->Subject = $config['mail_subject'] . ' — ' . $imie;
        $mail->Body    = $body;
        $mail->AltBody = "Imię: {$imie}\nE-mail: {$email}\nTelefon: {$telefon}\nUwagi: {$uwagi}";

        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Wiadomość wysłana']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Błąd wysyłania: ' . $mail->ErrorInfo]);
    }

} else {
    // ========== FALLBACK: wbudowana funkcja mail() ==========
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['mail_from_name']} <{$config['mail_from']}>\r\n";
    $headers .= "Reply-To: {$email}\r\n";

    $subject = '=?UTF-8?B?' . base64_encode($config['mail_subject'] . ' — ' . $imie) . '?=';

    if (mail($config['mail_to'], $subject, $body, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Wiadomość wysłana']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Błąd wysyłania maila']);
    }
}
