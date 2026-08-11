<?php
/**
 * Prave dimenzije slika — jedno mjesto za cijeli sajt.
 *
 * Zasto postoji:
 * Ako <img> nema width i height, pretrazivac prije ucitavanja ne zna koliko
 * mjesta da ostavi. Zbog toga se desavaju dvije stvari:
 *   1. sadrzaj skace dok slike stizu (Google to mjeri kao CLS i racuna u ocjenu),
 *   2. loading="lazy" ne radi — mreza je visoka nula piksela pa pretrazivac
 *      misli da su sve slike na ekranu i skida ih sve odjednom.
 *
 * Izmisljene dimenzije ne pomazu. Na stranicama kategorija je stajalo
 * width="400" height="300" za svaku sliku, a slike su stvarno 506x900 ili
 * 1600x1142 — rezervisani prostor je bio i do dva i po puta manji od pravog.
 *
 * getimagesize cita samo zaglavlje fajla, ali za stotinu slika to je pola
 * sekunde po zahtjevu. Zato se rezultat pamti u data/dimenzije-slika.json,
 * po imenu fajla i vremenu izmjene — kad vlasnik zamijeni sliku, kljuc se
 * promijeni i dimenzije se procitaju ponovo.
 */

/** Vrati [sirina, visina] za sliku, ili null ako se ne moze procitati. */
function mmhDimenzije(string $rel): ?array
{
    static $kes = null, $izmijenjen = false, $put = null;

    if ($kes === null) {
        $put = dirname(__DIR__) . '/data/dimenzije-slika.json';
        $kes = json_decode(@file_get_contents($put), true) ?: [];
        // Upis na kraju zahtjeva, da se ne pise po jednom za svaku sliku
        register_shutdown_function(function () use (&$kes, &$izmijenjen, &$put) {
            if (!$izmijenjen) return;
            if (count($kes) > 1500) $kes = array_slice($kes, -1500, null, true);
            @file_put_contents($put, json_encode($kes), LOCK_EX);
        });
    }

    $rel = ltrim((string)$rel, '/');
    if ($rel === '') return null;

    $puna = dirname(__DIR__) . '/' . $rel;
    $vrijeme = @filemtime($puna);
    if ($vrijeme === false) return null;

    $kljuc = $rel . '|' . $vrijeme;
    if (!array_key_exists($kljuc, $kes)) {
        $s = @getimagesize($puna);
        $kes[$kljuc] = ($s && !empty($s[0]) && !empty($s[1])) ? [(int)$s[0], (int)$s[1]] : null;
        $izmijenjen = true;
    }
    return $kes[$kljuc];
}

/** Vrati ' width="..." height="..."' spremno za ubacivanje u <img>, ili prazno. */
function mmhDimAtributi(string $rel): string
{
    $d = mmhDimenzije($rel);
    return $d ? sprintf(' width="%d" height="%d"', $d[0], $d[1]) : '';
}
