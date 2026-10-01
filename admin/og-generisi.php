<?php
/**
 * Pred-generisanje OG slika za dijeljenje (Viber/WhatsApp/Facebook) za SVE
 * proizvode, da prvi put kad bot dodje kartica vec postoji (bez cekanja) i da
 * nijedan proizvod ne ostane sa starom/pretamnom karticom.
 *
 * Radi nad ZIVIM products.json, samo CITA podatke — ne mijenja products.json.
 * Za svaki proizvod ponovi isti izbor kao product.php: glavna slika ako je
 * dovoljno siroka (>=600x315), inace se napravi kartica 1200x630 (mmhOgProizvod).
 *
 * Ne pravi nista ako slika vec postoji i novija je od izvora. ?force=1 da se
 * ponovo iscrta sve. Pristup: samo deploy token.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/php/dimenzije.php';   // mmhSlikaZaDijeljenje, mmhDimenzije
require_once $root . '/php/og-mozaik.php';   // mmhOgProizvod

$force = isset($_GET['force']);

$sirovo = @file_get_contents($root . '/data/products.json');
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

// Ako se forsira, pobrisi stare proizvod-*-v4 da se sigurno iscrtaju iznova.
if ($force) {
    foreach (glob($root . '/images/og/proizvod-*-v4.jpg') ?: [] as $f) @unlink($f);
}

$gen = 0; $direktno = 0; $greske = 0; $redovi = [];
foreach ($flat as $p) {
    $ime = (string)($p['name'] ?? ('ID ' . ($p['id'] ?? '?')));
    $kand = [];
    if (!empty($p['image'])) $kand[] = $p['image'];
    $izbor = mmhSlikaZaDijeljenje($kand);
    if ($izbor['put'] === 'images/showcase-room.jpg') {
        $svoja = mmhOgProizvod($p);
        if ($svoja) {
            $izbor = $svoja; $gen++;
            $redovi[] = "  [kartica] $ime  -> " . preg_replace('/\?.*/', '', $svoja['put']);
        } else {
            $greske++;
            $redovi[] = "  [GRESKA ] $ime  (nema GD ili slika fali: " . ($p['image'] ?? '-') . ")";
        }
    } else {
        $direktno++;
        $redovi[] = "  [siroka ] $ime  -> " . $izbor['put'];
    }
}

echo "Proizvoda: " . count($flat) . "\n";
echo "Generisane kartice (v4): $gen\n";
echo "Direktna glavna slika (dovoljno siroka): $direktno\n";
echo "Greske: $greske\n\n";
foreach ($redovi as $r) echo $r . "\n";
