<?php
/**
 * Kombinacije panela — automatsko prepoznavanje.
 *
 * Zamisao: vlasnik okaci ISTU fotografiju prostora u galeriju DVA (ili vise)
 * proizvoda — npr. sobu u kojoj su i mermerni i orah panel okaci i na jedan i
 * na drugi. Sajt onda sam prepozna da je to ista slika i tretira te proizvode
 * kao "kombinaciju": u Inspiraciji se slika prikaze jednom (ne duplira), a na
 * stranici svakog od tih proizvoda se ispise "Kombinacija: A + B" sa linkom na
 * onaj drugi.
 *
 * Kako se prepoznaje "ista slika": racuna se dHash (perceptualni otisak) — slika
 * se smanji na 9x8 sivih piksela i uporedi svaki sa susjedom (64 bita). Dvije
 * fotografije istog prostora daju isti otisak i kad su fajlovi razlicito
 * nazvani/prekodirani. Otisci se kesiraju (data/dhash-slika.json), a gotov
 * indeks kombinacija u data/kombi-index.json — racuna se ponovo samo kad se
 * galerije promijene.
 */

/** dHash slike kao 16-cifreni hex, ili null ako se ne moze procitati. */
function mmhDHash(string $rel): ?string
{
    static $kes = null, $izmijenjen = false, $put = null;
    if ($kes === null) {
        $put = dirname(__DIR__) . '/data/dhash-slika.json';
        $kes = json_decode(@file_get_contents($put), true) ?: [];
        register_shutdown_function(function () use (&$kes, &$izmijenjen, &$put) {
            if (!$izmijenjen) return;
            if (count($kes) > 3000) $kes = array_slice($kes, -3000, null, true);
            @file_put_contents($put, json_encode($kes), LOCK_EX);
        });
    }
    $rel = ltrim((string)$rel, '/');
    if ($rel === '') return null;
    $abs = dirname(__DIR__) . '/' . $rel;
    if (!is_file($abs)) return null;
    $kljuc = $rel . '|' . @filemtime($abs);
    if (isset($kes[$kljuc])) return $kes[$kljuc] ?: null;

    $hex = null;
    $data = @file_get_contents($abs);
    if ($data !== false) {
        $im = @imagecreatefromstring($data);
        if ($im) {
            $w = 9; $h = 8;
            $mali = imagecreatetruecolor($w, $h);
            imagecopyresampled($mali, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
            imagedestroy($im);
            $bits = '';
            for ($y = 0; $y < $h; $y++) {
                $prev = null;
                for ($x = 0; $x < $w; $x++) {
                    $rgb = imagecolorat($mali, $x, $y);
                    $g = (int)(0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
                    if ($prev !== null) $bits .= ($g > $prev) ? '1' : '0';
                    $prev = $g;
                }
            }
            imagedestroy($mali);
            $hex = '';
            for ($i = 0; $i < 64; $i += 4) $hex .= dechex(bindec(substr($bits, $i, 4)));
        }
    }
    $kes[$kljuc] = $hex ?: '';
    $izmijenjen = true;
    return $hex;
}

/**
 * Vrati indeks kombinacija: mapu id_proizvoda => niz kombinacija, gdje je svaka
 * kombinacija ['slika' => putanja te slike u galeriji OVOG proizvoda,
 *              'partneri' => [ {id, sku, name, image, url}, ... ] ].
 * Kesira se u data/kombi-index.json i racuna ponovo tek kad se galerije promijene.
 */
function mmhKombinacije(array $products): array
{
    $put = dirname(__DIR__) . '/data/kombi-index.json';

    // Potpis svih galerija — ako se ne promijeni, koristimo kes.
    $potpisSirovi = '';
    foreach ($products as $p) {
        $g = $p['gallery'] ?? [];
        if ($g) $potpisSirovi .= (int)($p['id'] ?? 0) . ':' . implode(',', $g) . ';';
    }
    $potpis = md5($potpisSirovi);

    $kes = json_decode(@file_get_contents($put), true);
    if (is_array($kes) && ($kes['_potpis'] ?? '') === $potpis && isset($kes['index'])) {
        return $kes['index'];
    }

    // Rebuild: dHash svake slike -> grupa; grupa sa 2+ RAZLICITA proizvoda = kombinacija.
    $byId = [];
    foreach ($products as $p) $byId[(int)($p['id'] ?? 0)] = $p;

    $grupe = [];               // hash => [ id => putanja slike tog proizvoda ]
    foreach ($products as $p) {
        $pid = (int)($p['id'] ?? 0);
        foreach (($p['gallery'] ?? []) as $slika) {
            if (!$slika) continue;
            $h = mmhDHash($slika);
            if (!$h) continue;
            // prvi put kad se ovaj hash vidi za ovaj proizvod — dovoljno je jednom
            if (!isset($grupe[$h][$pid])) $grupe[$h][$pid] = $slika;
        }
    }

    $index = [];
    foreach ($grupe as $h => $perId) {
        if (count($perId) < 2) continue; // nije kombinacija
        foreach ($perId as $pid => $slika) {
            $partneri = [];
            foreach ($perId as $pid2 => $sl2) {
                if ($pid2 === $pid) continue;
                $pp = $byId[$pid2] ?? null;
                if (!$pp) continue;
                $puna = (float)($pp['price'] ?? 0);
                $pop  = (int)($pp['discount'] ?? 0);
                $partneri[] = [
                    'id'    => $pid2,
                    'sku'   => $pp['sku'] ?? '',
                    'name'  => $pp['name'] ?? '',
                    'image' => $pp['image'] ?? '',
                    'price' => $pop > 0 ? round($puna * (1 - $pop / 100), 2) : $puna,
                    'unit'  => $pp['unit'] ?? 'kom',
                    'url'   => function_exists('mmhUrlProizvoda') ? mmhUrlProizvoda($pp) : '#',
                ];
            }
            if ($partneri) $index[$pid][] = ['slika' => $slika, 'partneri' => $partneri];
        }
    }

    @file_put_contents($put, json_encode(['_potpis' => $potpis, 'index' => $index]), LOCK_EX);
    return $index;
}

/**
 * Za Inspiraciju: vrati skup putanja koje treba PRESKOCITI da se kombinacija ne
 * bi duplirala. Za svaku grupu duplikata (ista slika kod vise proizvoda)
 * zadrzava se samo prva (po redu u $products), ostale se preskacu.
 * Vraca ['skip' => [putanja=>1...], 'combo' => [putanja=>[partneri...]]].
 */
function mmhKombiInspiracija(array $products): array
{
    $index = mmhKombinacije($products);
    // index je po id-u; grupe se vide preko partnera. Napravimo skup slika koje su
    // "druga+ pojava" iste kombinacije. Zadrzavamo sliku proizvoda sa NAJMANJIM id-om.
    $skip = [];
    $combo = [];
    $vidjeno = []; // kljuc grupe (sortirani id-evi) => zadrzana slika
    foreach ($index as $pid => $komboviProizvoda) {
        foreach ($komboviProizvoda as $k) {
            $ids = array_merge([$pid], array_map(fn($x) => $x['id'], $k['partneri']));
            sort($ids);
            $kljuc = implode('-', $ids);
            $combo[$k['slika']] = $k['partneri'];
            if (!isset($vidjeno[$kljuc])) {
                // prvi put — zadrzi sliku proizvoda sa najmanjim id-om
                $vidjeno[$kljuc] = ($pid === $ids[0]) ? $k['slika'] : null;
            }
        }
    }
    // Drugi prolaz: sve slike koje pripadaju grupi a nisu "zadrzana" -> skip
    foreach ($index as $pid => $komboviProizvoda) {
        foreach ($komboviProizvoda as $k) {
            $ids = array_merge([$pid], array_map(fn($x) => $x['id'], $k['partneri']));
            sort($ids);
            $kljuc = implode('-', $ids);
            $zadrzana = $vidjeno[$kljuc] ?? null;
            if ($zadrzana === null) { $vidjeno[$kljuc] = $k['slika']; $zadrzana = $k['slika']; }
            if ($k['slika'] !== $zadrzana) $skip[$k['slika']] = 1;
        }
    }
    return ['skip' => $skip, 'combo' => $combo];
}
