<?php
/**
 * Kombinacije panela — EKSPLICITNE (bez pogadjanja).
 *
 * Kombinuju se ISKLJUCIVO proizvodi koje vlasnik navede u data/kombinacije.json.
 * (Ranija auto-detekcija po "izgledu slike" je znala pogresno da spoji dvije
 * razlicite fotografije, pa je pravila kombinacije koje niko nije trazio. Zato
 * je izbacena — sada se nista ne pogadja.)
 *
 * Format data/kombinacije.json:
 *   [ { "proizvodi": ["SKU1","SKU2"], "slike": ["images/products/...jpg", ...] } ]
 *   - "proizvodi": paneli koji se vide na toj fotografiji (SKU-ovi).
 *   - "slike": putanja(e) te iste fotografije. Ako je okacena u galeriju vise
 *     proizvoda (pa postoji vise kopija), navedu se sve: prva se prikaze u
 *     Inspiraciji, ostale se preskacu da se ne dupliraju.
 */
function mmhKombiInspiracija(array $products): array
{
    $put = dirname(__DIR__) . '/data/kombinacije.json';
    $def = is_file($put) ? (json_decode(@file_get_contents($put), true) ?: []) : [];

    $poSku = [];
    foreach ($products as $p) if (!empty($p['sku'])) $poSku[$p['sku']] = $p;

    $skip  = [];   // 2. i dalje kopija iste slike -> preskoci u galeriji
    $combo = [];   // putanja koja se ZADRZAVA -> [ostali paneli {name,url}]

    foreach ($def as $k) {
        $skus  = $k['proizvodi'] ?? [];
        $slike = array_values($k['slike'] ?? []);
        if (count($skus) < 2 || !$slike) continue;

        // Paneli (ime + link) za sve u kombinaciji
        $paneli = [];
        foreach ($skus as $sk) {
            if (!isset($poSku[$sk])) continue;
            $pp = $poSku[$sk];
            $paneli[$sk] = [
                'name' => $pp['name'] ?? $sk,
                'url'  => function_exists('mmhUrlProizvoda') ? mmhUrlProizvoda($pp) : '#',
            ];
        }
        if (count($paneli) < 2) continue;

        foreach ($slike as $i => $slika) {
            if ($i > 0) { $skip[$slika] = 1; continue; } // duplikat -> preskoci

            // Vlasnika prve slike prepoznajemo po id-u u imenu (gallery-<id>-...),
            // pa mu na cip-listu dodajemo OSTALE panele (njega doda sam render).
            $ownerSku = null;
            if (preg_match('/gallery-(\d+)-/', $slika, $m)) {
                $oid = (int) $m[1];
                foreach ($skus as $sk) {
                    if (isset($poSku[$sk]) && (int) ($poSku[$sk]['id'] ?? 0) === $oid) { $ownerSku = $sk; break; }
                }
            }
            $ostali = [];
            foreach ($paneli as $sk => $pd) { if ($sk !== $ownerSku) $ostali[] = $pd; }
            $combo[$slika] = $ostali;
        }
    }

    return ['skip' => $skip, 'combo' => $combo];
}
