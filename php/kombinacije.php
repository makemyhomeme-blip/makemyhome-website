<?php
/**
 * Kombinacije panela za Inspiraciju — koji se paneli vide zajedno na istoj slici.
 *
 * Dva POUZDANA signala (bez pogadjanja "po izgledu", koje je znalo lazno da
 * spoji dvije razlicite sobe jer su na ovom sajtu sve slicne — svijetli
 * minimalizam):
 *
 *  1) ISTI FAJL kod dva proizvoda (md5 sadrzaja). Kad vlasnik okaci ISTU
 *     fotografiju u galeriju vise panela, to je kombinacija — automatski, bez
 *     ijedne rucne izmjene i bez laznih spajanja (identican fajl = ista slika).
 *
 *  2) EKSPLICITNA lista data/kombinacije.json — za slucaj kad su fajlovi ista
 *     fotografija ali NISU bajt-identicni (npr. razlicita velicina/rez pri
 *     izvozu iz telefona), pa ih md5 ne moze povezati. Tu se par navede rucno.
 *
 * Rezultat (za inspiracija.php):
 *   [ 'skip'  => [ putanja => 1, ... ],            // duplikati -> NE prikazuj
 *     'combo' => [ putanja => [ {name,url}, ... ]] // slika koja OSTAJE -> OSTALI paneli
 *   ]
 * Slika koja "ostaje" prikazuje se na svom prirodnom mjestu u galeriji (ne
 * gura se na vrh) i dobija oznaku "Kombinacija" + cipove svih panela na njoj.
 */
function mmhKombiInspiracija(array $products): array
{
    $korijen = dirname(__DIR__);

    $poId = [];
    foreach ($products as $p) {
        $id = (string) ($p['id'] ?? '');
        if ($id !== '') $poId[$id] = $p;
    }
    // Ime + link jednog panela (za cipove)
    $panel = function (array $p): array {
        return [
            'name' => $p['name'] ?? ($p['sku'] ?? ''),
            'url'  => function_exists('mmhSlugProizvoda') ? '/' . mmhSlugProizvoda($p) : '#',
        ];
    };
    // Vlasnik slike se prepoznaje po id-u u imenu: gallery-<id>-<vrijeme>-<n>.jpg
    $vlasnik = function (string $put) use ($poId): ?string {
        if (preg_match('/gallery-(\d+)-/', $put, $m) && isset($poId[(string) (int) $m[1]])) {
            return (string) (int) $m[1];
        }
        return null;
    };

    $skip  = [];
    $combo = [];

    // ---- 1) AUTOMATSKI: isti fajl (md5) kod razlicitih proizvoda ----------
    // md5 se kesira po putanji + vremenu izmjene (data/kombi-hash-kes.json) da
    // se stotine slika ne bi racunale iznova pri svakom otvaranju stranice.
    $kesPut  = $korijen . '/data/kombi-hash-kes.json';
    $kes     = is_file($kesPut) ? (json_decode(@file_get_contents($kesPut), true) ?: []) : [];
    $kesPrije = $kes;

    $poHash = [];   // md5 => [ ['put'=>rel,'id'=>id], ... ] redom pojavljivanja
    foreach ($products as $p) {
        $id = (string) ($p['id'] ?? '');
        foreach (($p['gallery'] ?? []) as $rel) {
            $aps = $korijen . '/' . ltrim($rel, '/');
            $mt  = @filemtime($aps);
            if ($mt === false) continue;             // fajla nema (npr. lokalno)
            $sz  = @filesize($aps);
            // Jednobojni uzorci boje (~16 kB) nisu "kombinacija u prostoru".
            // Prave sobne fotografije su >40 kB. Time izbjegavamo i da se dva
            // ista uzorka boje slucajno spoje u kombinaciju.
            if ($sz !== false && $sz < 40000) continue;

            if (isset($kes[$rel]) && ($kes[$rel]['m'] ?? -1) === $mt) {
                $h = $kes[$rel]['h'];
            } else {
                $h = @md5_file($aps);
                if ($h === false) continue;
                $kes[$rel] = ['m' => $mt, 'h' => $h];
            }
            $poHash[$h][] = ['put' => $rel, 'id' => $id];
        }
    }
    if ($kes !== $kesPrije) {
        @file_put_contents($kesPut, json_encode($kes, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    foreach ($poHash as $poj) {
        $ids = array_values(array_unique(array_map(fn ($x) => $x['id'], $poj)));
        if (count($ids) < 2) continue;               // isti fajl, ali isti proizvod
        $prva  = $poj[0]['put'];
        $vlId  = $poj[0]['id'];
        for ($i = 1; $i < count($poj); $i++) $skip[$poj[$i]['put']] = 1;
        $ostali = [];
        foreach ($ids as $iid) {
            if ($iid === $vlId || !isset($poId[$iid])) continue;
            $ostali[] = $panel($poId[$iid]);
        }
        if ($ostali) $combo[$prva] = $ostali;
    }

    // ---- 2) EKSPLICITNO: data/kombinacije.json ----------------------------
    $defPut = $korijen . '/data/kombinacije.json';
    $def    = is_file($defPut) ? (json_decode(@file_get_contents($defPut), true) ?: []) : [];
    $poSku  = [];
    foreach ($products as $p) if (!empty($p['sku'])) $poSku[$p['sku']] = $p;

    foreach ($def as $k) {
        $skus  = $k['proizvodi'] ?? [];
        $slike = array_values($k['slike'] ?? []);
        if (count($skus) < 2 || !$slike) continue;

        $prva = $slike[0];
        for ($i = 1; $i < count($slike); $i++) $skip[$slike[$i]] = 1;
        unset($skip[$prva]);                          // prva OSTAJE, makar je md5 ranije preskocio

        $vlId = $vlasnik($prva);
        $ostali = [];
        foreach ($skus as $sk) {
            if (!isset($poSku[$sk])) continue;
            if ($vlId !== null && (string) ($poSku[$sk]['id'] ?? '') === $vlId) continue;
            $ostali[] = $panel($poSku[$sk]);
        }
        if ($ostali) $combo[$prva] = $ostali;         // eksplicitno dopunjuje/pobjedjuje
    }

    return ['skip' => $skip, 'combo' => $combo];
}
