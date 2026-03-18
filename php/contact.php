<?php
/**
 * Make My Home - Kontakt forma handler
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Nedozvoljena metoda.']);
    exit;
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$product = trim($_POST['product'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validacija
if (empty($name) || strlen($name) < 2) {
    echo json_encode(['success' => false, 'message' => 'Unesite ispravno ime.']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Unesite ispravnu email adresu.']);
    exit;
}

if (empty($message) || strlen($message) < 10) {
    echo json_encode(['success' => false, 'message' => 'Poruka mora sadržavati najmanje 10 karaktera.']);
    exit;
}

// Sanitizacija
$name    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email   = filter_var($email, FILTER_SANITIZE_EMAIL);
$phone   = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$product = htmlspecialchars($product, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

$to      = 'makemyhome.me@gmail.com';
$subject = 'Novi upit sa makemyhome.me' . ($product ? " – {$product}" : '');

$body  = "NOVI UPIT SA SAJTA\n";
$body .= "==================\n\n";
$body .= "Ime:      {$name}\n";
$body .= "Email:    {$email}\n";
$body .= "Telefon:  " . ($phone ?: 'Nije navedeno') . "\n";
$body .= "Proizvod: " . ($product ?: 'Nije navedeno') . "\n\n";
$body .= "Poruka:\n{$message}\n\n";
$body .= "---\nPoslano: " . date('d.m.Y H:i') . "\n";
$body .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A');

$headers  = "From: noreply@makemyhome.me\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = @mail($to, $subject, $body, $headers);

// Sačuvaj u log fajl (opciono)
$logDir = __DIR__ . '/../data/';
if (is_dir($logDir)) {
    $logFile = $logDir . 'inquiries.json';
    $inquiries = [];
    if (file_exists($logFile)) {
        $existing = file_get_contents($logFile);
        $inquiries = json_decode($existing, true) ?: [];
    }
    $inquiries[] = [
        'date'    => date('Y-m-d H:i:s'),
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'product' => $product,
        'message' => $message,
        'read'    => false
    ];
    // Čuvaj max 500 upita
    if (count($inquiries) > 500) {
        $inquiries = array_slice($inquiries, -500);
    }
    file_put_contents($logFile, json_encode($inquiries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'success' => true,
    'message' => 'Hvala, ' . htmlspecialchars($name) . '! Vaša poruka je primljena. Kontaktiraćemo Vas uskoro.'
]);
