<?php
/**
 * Slika za dijeljenje kategorije — mozaik od šest dezena umjesto jedne fotografije.
 *
 * Zasto postoji:
 * Kad se na Viberu, WhatsAppu ili Facebooku podijeli adresa kategorije, u
 * pregledu se do sada pokazivala PRVA fotografija iz te kategorije. Kupac je
 * vidio jedan panel i nije mogao znati da iza linka stoji trideset devet
 * dezena. Mozaik od sest kockica na prvi pogled kaze "ovdje ima izbora".
 *
 * Kako radi:
 * Slika se pravi JEDNOM i cuva u images/og/. Pravi se ponovo samo ako je
 * data/products.json noviji od nje — dakle kad vlasnik u adminu doda proizvod
 * ili promijeni sliku. Posjetilac nikad ne ceka na crtanje.
 *
 * Zasto se cuva kao pravi .jpg fajl, a ne kao PHP koji vraca sliku:
 * Programi koji citaju pregled linka (Viber, Facebook) uredno rade i sa
 * PHP-om, ali dio njih kesira po nastavku u adresi i ponasa se pouzdanije sa
 * pravim fajlom. Uz to, pravi fajl posluzuje Apache bez pokretanja PHP-a.
 *
 * Ako na serveru nema GD biblioteke ili neka slika fali, funkcija vrati null,
 * a products.php nastavi po starom — sa jednom fotografijom. Nista ne puca.
 */

/**
 * @param string $kategorija  kljuc kategorije, ili '' za cio katalog
 * @param array  $proizvodi   vec ucitan data/products.json
 * @return array|null         ['put' => 'images/og/...jpg', 'w' => 1200, 'h' => 630]
 */
function mmhOgMozaik(string $kategorija, array $proizvodi): ?array
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) return null;

    $korijen = dirname(__DIR__);
    $ime     = $kategorija === '' ? 'katalog' : 'kategorija-' . preg_replace('/[^a-z0-9-]/', '', $kategorija);
    $rel     = 'images/og/' . $ime . '.jpg';
    $put     = $korijen . '/' . $rel;
    $izvor   = $korijen . '/data/products.json';

    // Vec napravljena i novija od podataka — nista se ne racuna.
    if (is_file($put) && is_file($izvor) && filemtime($put) >= filemtime($izvor)) {
        // Viber i Facebook pamte sliku po adresi. Bez oznake u adresi bi i
        // poslije dodavanja novog proizvoda mjesecima prikazivali stari mozaik.
        return ['put' => $rel . '?v=' . filemtime($put), 'w' => 1200, 'h' => 630];
    }

    // Slike: po jedna glavna fotografija sa svakog proizvoda iz kategorije.
    // Za cio katalog se uzima po jedna iz svake kategorije, da se ne dobije
    // sest puta isti dezen.
    $slike = [];
    if ($kategorija === '') {
        $viđene = [];
        foreach ($proizvodi as $p) {
            $k = $p['category'] ?? '';
            if ($k === '' || isset($viđene[$k]) || empty($p['image'])) continue;
            if (!is_file($korijen . '/' . ltrim($p['image'], '/'))) continue;
            $viđene[$k] = true;
            $slike[] = $p['image'];
            if (count($slike) >= 6) break;
        }
    } else {
        // "bambus-paneli" je krovna kategorija: nijedan proizvod nema bas taj
        // kljuc, nego jedan od pet podtipova. Bez ovoga bi bas ta kategorija —
        // sa najvise dezena — jedina ostala bez mozaika. Uzima se po jedan
        // dezen iz svakog podtipa, da se u pregledu vidi cio raspon.
        $podtipovi = ['bambus-drveni','bambus-tekstilni','bambus-mermerni','bambus-kozni','bambus-metalni'];
        if ($kategorija === 'bambus-paneli') {
            foreach ([1, 2] as $krug) {          // prvi krug: po jedan iz svakog podtipa
                $uzeto = [];
                foreach ($proizvodi as $p) {
                    $k = $p['category'] ?? '';
                    if (!in_array($k, $podtipovi, true) || empty($p['image'])) continue;
                    if ($krug === 1 && isset($uzeto[$k])) continue;
                    if (in_array($p['image'], $slike, true)) continue;
                    if (!is_file($korijen . '/' . ltrim($p['image'], '/'))) continue;
                    $uzeto[$k] = true;
                    $slike[] = $p['image'];
                    if (count($slike) >= 6) break 2;
                }
            }
        } else {
            foreach ($proizvodi as $p) {
                if (($p['category'] ?? '') !== $kategorija || empty($p['image'])) continue;
                if (!is_file($korijen . '/' . ltrim($p['image'], '/'))) continue;
                $slike[] = $p['image'];
                if (count($slike) >= 6) break;
            }
        }
    }

    // Ispod cetiri slike mozaik nema smisla — bolje je jedna cijela fotografija.
    if (count($slike) < 4) return null;

    // Mreza se prilagodjava broju slika, da nijedna kockica ne ostane prazna.
    if (count($slike) >= 6)      { $kol = 3; $red = 2; $slike = array_slice($slike, 0, 6); }
    else                         { $kol = 2; $red = 2; $slike = array_slice($slike, 0, 4); }

    $Š = 1200; $V = 630; $razmak = 6;      // 1200x630 je format koji trazi Facebook i Viber
    $cŠ = (int) round(($Š - $razmak * ($kol - 1)) / $kol);
    $cV = (int) round(($V - $razmak * ($red - 1)) / $red);

    $platno = imagecreatetruecolor($Š, $V);
    imagefill($platno, 0, 0, imagecolorallocate($platno, 255, 255, 255));

    foreach ($slike as $i => $rel1) {
        $f = $korijen . '/' . ltrim($rel1, '/');
        $vrsta = @exif_imagetype($f);
        $im = false;
        if ($vrsta === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg'))      $im = @imagecreatefromjpeg($f);
        elseif ($vrsta === IMAGETYPE_PNG && function_exists('imagecreatefrompng'))    $im = @imagecreatefrompng($f);
        elseif ($vrsta === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp'))  $im = @imagecreatefromwebp($f);
        if (!$im) { imagedestroy($platno); return null; }

        // Isjecak iz sredine, da se dezen vidi u pravom odnosu i bez izduzivanja.
        $iŠ = imagesx($im); $iV = imagesy($im);
        $odnosC = $cŠ / $cV; $odnosI = $iŠ / $iV;
        if ($odnosI > $odnosC) { $sŠ = (int) round($iV * $odnosC); $sV = $iV; $sx = (int) round(($iŠ - $sŠ) / 2); $sy = 0; }
        else                   { $sŠ = $iŠ; $sV = (int) round($iŠ / $odnosC); $sx = 0; $sy = (int) round(($iV - $sV) / 2); }

        $x = ($i % $kol) * ($cŠ + $razmak);
        $y = intdiv($i, $kol) * ($cV + $razmak);
        imagecopyresampled($platno, $im, $x, $y, $sx, $sy, $cŠ, $cV, $sŠ, $sV);
        imagedestroy($im);
    }

    if (!is_dir(dirname($put))) @mkdir(dirname($put), 0755, true);
    $ok = @imagejpeg($platno, $put, 82);
    imagedestroy($platno);
    if (!$ok) return null;
    clearstatcache(true, $put);

    return ['put' => $rel . '?v=' . filemtime($put), 'w' => $Š, 'h' => $V];
}

/**
 * Slika za dijeljenje POJEDINOG proizvoda kad njegova fotografija nije dovoljno
 * siroka.
 *
 * Zasto postoji:
 * Facebook, Viber i WhatsApp prikazu veliku karticu tek od 600x315 navise.
 * Fotografije panela su uspravne — npr. 392x900 — pa je dvanaest proizvoda
 * (cijela PU serija, tri SPC poda i jedan mermerni panel) padalo na zajednicku
 * fotografiju showrooma. Ko podijeli link na taj panel, u pregledu nije vidio
 * taj panel nego neciju dnevnu sobu.
 *
 * Kako radi:
 * Uspravna fotografija se stavi na platno 1200x630 u punoj visini i po sredini,
 * a pozadinu popunjava ista ta fotografija razvucena i zamucena. Zamucenje se
 * radi na sicusnom platnu pa se uvecava — tako je jako, a jeftino.
 *
 * Rezultat se cuva u images/og/ i pravi se ponovo samo kad se promijeni sama
 * fotografija. Ako nema GD-a, vrati null i sve ostaje kako je bilo.
 */
function mmhOgProizvod(array $p): ?array
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) return null;

    $rel1 = (string)($p['image'] ?? '');
    if ($rel1 === '') return null;

    $korijen = dirname(__DIR__);
    $izvor   = $korijen . '/' . ltrim($rel1, '/');
    if (!is_file($izvor)) return null;

    $id  = preg_replace('/[^0-9]/', '', (string)($p['id'] ?? '0'));
    $rel = 'images/og/proizvod-' . $id . '.jpg';
    $put = $korijen . '/' . $rel;

    if (is_file($put) && filemtime($put) >= filemtime($izvor)) {
        return ['put' => $rel . '?v=' . filemtime($put), 'w' => 1200, 'h' => 630];
    }

    $vrsta = @exif_imagetype($izvor);
    $im = false;
    if ($vrsta === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg'))      $im = @imagecreatefromjpeg($izvor);
    elseif ($vrsta === IMAGETYPE_PNG && function_exists('imagecreatefrompng'))    $im = @imagecreatefrompng($izvor);
    elseif ($vrsta === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp'))  $im = @imagecreatefromwebp($izvor);
    if (!$im) return null;

    $Š = 1200; $V = 630;
    $iŠ = imagesx($im); $iV = imagesy($im);
    if ($iŠ < 5 || $iV < 5) { imagedestroy($im); return null; }

    $platno = imagecreatetruecolor($Š, $V);

    // Fotografija se ponavlja jedna do druge, u punoj visini platna. Panel je
    // uspravan i uzak, pa jedna kopija pokriva tek cetvrtinu sirine; niz kopija
    // izgleda kao zid oblozen tim panelom, a slika ostaje ostra jer se SMANJUJE.
    // (Razvlacenje jedne kopije preko cijelog platna dalo bi trostruko uvecanje
    // i mutnu sliku.)
    $kŠ = max(1, (int) round($iŠ * ($V / $iV)));
    $kom = (int) ceil($Š / $kŠ);
    $poc = (int) round(($Š - $kom * $kŠ) / 2);   // visak se podjednako odsijeca lijevo i desno
    for ($i = 0; $i < $kom; $i++) {
        imagecopyresampled($platno, $im, $poc + $i * $kŠ, 0, 0, 0, $kŠ, $V, $iŠ, $iV);
    }
    imagedestroy($im);

    if (!is_dir(dirname($put))) @mkdir(dirname($put), 0755, true);
    $ok = @imagejpeg($platno, $put, 84);
    imagedestroy($platno);
    if (!$ok) return null;
    clearstatcache(true, $put);

    return ['put' => $rel . '?v=' . filemtime($put), 'w' => $Š, 'h' => $V];
}
