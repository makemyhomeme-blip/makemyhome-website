<?php
/**
 * Datum posljednje izmjene po POJEDINOM proizvodu.
 *
 * Zasto postoji:
 * U sitemapu je svih 149 adresa nosilo isti <lastmod>, i to datum kad je
 * zadnji put diran product.php. A product.php je sablon — mijenja se pri
 * skoro svakom deployu, i kad se dira samo izgled. Rezultat: svaki deploy je
 * Googleu javio da su se SVE stranice promijenile.
 *
 * Google trazi da lastmod znaci stvarnu promjenu sadrzaja. Sajt koji pri
 * svakom deployu javi "sve se promijenilo" nauci Google da to polje ne vrijedi
 * citati — i sitemap prestane da nosi ikakav signal o tome kad treba doci
 * ponovo. Kroz pet mjeseci cestih deploya to je upravo ono sto se desilo.
 *
 * Kako radi:
 * Za svaki proizvod se racuna otisak (hash) NJEGOVIH podataka. Otisak i datum
 * se pamte u data/lastmod.json. Datum se mijenja samo kad se promijeni otisak
 * — dakle kad vlasnik stvarno izmijeni taj proizvod. Deploy sablona ne dira
 * nijedan datum.
 *
 * Prvo pokretanje:
 * Datum se ne izmislja. Uzima se iz imena fotografija tog proizvoda —
 * gallery-<id>-<vrijeme>-<broj>.jpg nosi vrijeme uploada. Ako ih nema, uzima
 * se vrijeme izmjene glavne slike, pa tek onda products.json.
 *
 * Fajl data/lastmod.json pravi SAM SERVER i ne salje se sa lokalnog — inace bi
 * se datumi vratili na stanje sa razvojne masine.
 */

/**
 * @param array $proizvodi  ucitan data/products.json
 * @return array            [id => 'Y-m-d']
 */
function mmhDatumiProizvoda(array $proizvodi): array
{
    $korijen = dirname(__DIR__);
    $put     = $korijen . '/data/lastmod.json';
    $pamcenje = json_decode(@file_get_contents($put), true);
    if (!is_array($pamcenje)) $pamcenje = [];

    $danas   = date('Y-m-d');
    $izlaz   = [];
    $promjena = false;

    foreach ($proizvodi as $p) {
        $id = (string)($p['id'] ?? '');
        if ($id === '') continue;

        // Otisak se racuna SAMO iz onoga sto kupac vidi. Redoslijed kljuceva se
        // sortira, da premjestanje polja u JSON-u ne bi izgledalo kao izmjena.
        $bitno = [];
        foreach (['name','sku','price','discount','unit','description','highlight',
                  'features','highlights','idealFor','image','gallery','category',
                  'badge','inStock','styleMatch'] as $k) {
            if (array_key_exists($k, $p)) $bitno[$k] = $p[$k];
        }
        ksort($bitno);
        $otisak = sha1(json_encode($bitno, JSON_UNESCAPED_UNICODE));

        if (isset($pamcenje[$id]['h']) && $pamcenje[$id]['h'] === $otisak
            && !empty($pamcenje[$id]['d'])) {
            $izlaz[$id] = $pamcenje[$id]['d'];
            continue;
        }

        $datum = isset($pamcenje[$id]) ? $danas : mmhPrviDatum($p, $korijen);
        $pamcenje[$id] = ['h' => $otisak, 'd' => $datum];
        $izlaz[$id] = $datum;
        $promjena = true;
    }

    if ($promjena) {
        @file_put_contents($put, json_encode($pamcenje, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
    return $izlaz;
}

/**
 * Datum za proizvod koji se prvi put vidi — iz stvarnih tragova na disku,
 * ne iz danasnjeg dana.
 */
function mmhPrviDatum(array $p, string $korijen): string
{
    $naj = 0;

    // Ime fotografije nosi vrijeme uploada: gallery-<id>-<vrijeme>-<broj>.jpg
    foreach (($p['gallery'] ?? []) as $g) {
        if (preg_match('/gallery-\d+-(\d{9,11})-/', (string)$g, $m)) {
            $t = (int)$m[1];
            if ($t > $naj) $naj = $t;
        }
    }
    // Pa vrijeme izmjene glavne slike
    if (!$naj && !empty($p['image'])) {
        $t = @filemtime($korijen . '/' . ltrim((string)$p['image'], '/'));
        if ($t) $naj = $t;
    }
    // Pa tek onda podaci
    if (!$naj) {
        $t = @filemtime($korijen . '/data/products.json');
        if ($t) $naj = $t;
    }
    return date('Y-m-d', $naj ?: time());
}
