<?php
/**
 * Pocetna stranica — index.html, ali sa proizvodima vec ispisanim.
 *
 * Zasto postoji:
 * Blok "Izdvojeni proizvodi" na pocetnoj bio je prazan u HTML-u — punio ga je
 * JavaScript tek poslije ucitavanja. Googlebot u prvom prolazu ne izvrsava
 * JavaScript, pa je na najvaznijoj stranici sajta vidio NULA proizvoda: ni
 * jedno ime panela, ni jednu cijenu, ni jednu sifru. Mjereno: bez JavaScripta
 * 1067 rijeci, sa njim 1600 — razlika od 533 rijeci su bas proizvodi.
 *
 * Zasto ovako a ne kao poseban fajl:
 * Da je napravljena druga kopija pocetne, dvije bi se vremenom razisle. Ovako
 * postoji SAMO index.html kao izvor — ovaj fajl ga procita, ubaci kartice
 * proizvoda na mjesto praznog bloka i posalje dalje. Ako bilo sta ne uspije,
 * salje index.html nepromijenjen, pa je najgori ishod ono sto je i do sada bilo.
 */
require_once __DIR__ . '/php/slug.php';
require_once __DIR__ . '/php/dimenzije.php';

$html = @file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    exit('Pocetna stranica nije dostupna.');
}

$P = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];

$katImena = [
    'bambus-tekstilni' => 'Tekstilni Paneli', 'bambus-drveni' => 'Drveni Paneli',
    'bambus-mermerni'  => 'Mermerni Paneli',  'bambus-metalni' => 'Metalni Paneli',
    'bambus-kozni'     => 'Kožni Paneli',     'bambus-paneli'  => 'Bambus Paneli',
    '3d-letvice'       => '3D Letvice',       'akusticni-paneli' => 'Akustični Paneli',
    'aluminijum-lajsne'=> 'Aluminijum Lajsne','spc-pod'        => 'SPC Pod',
    'pu-kamen'         => 'PU Kamen',         'classic'        => 'Classic Paneli',
    'mdf'              => 'MDF Paneli',       'flex-stone'     => 'Flex Stone',
];

// Isti izbor kao u JavaScriptu: oznaceni kao izdvojeni, najvise 6
$izdvojeni = [];
foreach ($P as $p) {
    if (!empty($p['featured'])) $izdvojeni[] = $p;
    if (count($izdvojeni) >= 6) break;
}

if ($izdvojeni) {
    ob_start();
    foreach ($izdvojeni as $p):
        $url    = mmhUrlProizvoda($p);
        $kat    = $katImena[$p['category'] ?? ''] ?? ($p['category'] ?? '');
        $cijena = (float)($p['price'] ?? 0);
        $popust = (int)($p['discount'] ?? 0);
        $nema   = ($p['inStock'] ?? true) === false;
        $jed    = $p['unit'] ?? 'kom';
    ?>
        <article class="product-card<?= $nema ? ' out-of-stock' : '' ?>" data-category="<?= htmlspecialchars($p['category'] ?? '') ?>" data-id="<?= (int)($p['id'] ?? 0) ?>">
          <a href="<?= htmlspecialchars($url) ?>" class="product-img" style="display:block;">
            <img src="<?= htmlspecialchars($p['image'] ?? '') ?>" loading="lazy"<?= mmhDimAtributi($p['image'] ?? '') ?>
                 alt="<?= htmlspecialchars(($p['name'] ?? '') . ' – ' . $kat . ' | Make My Home Decor Podgorica') ?>">
            <?php if ($popust > 0 && !$nema): ?>
            <div style="position:absolute;top:10px;right:10px;background:#c0392b;color:#fff;font-weight:800;font-size:13px;line-height:1;padding:6px 11px;border-radius:8px;z-index:4;">&minus;<?= $popust ?>%</div>
            <?php endif; ?>
            <?php if ($nema): ?><div class="oos-tag">Rasprodato</div><?php endif; ?>
          </a>
          <div class="product-body">
            <div class="product-category"><?= htmlspecialchars($kat) ?></div>
            <h3 class="product-name"><a href="<?= htmlspecialchars($url) ?>" style="color:inherit;"><?= htmlspecialchars($p['name'] ?? '') ?></a></h3>
            <?php if (!empty($p['sku'])): ?><div class="product-sku">Šifra: <strong><?= htmlspecialchars($p['sku']) ?></strong></div><?php endif; ?>
            <p class="product-desc"><?= htmlspecialchars(mb_substr((string)($p['description'] ?? ''), 0, 150)) ?>…</p>
            <div class="product-footer">
              <div class="product-price">
                <?php if ($popust > 0): ?>
                  <span style="text-decoration:line-through;color:#767676;font-size:13px;display:block;"><?= $cijena ?> €</span>
                  <span style="color:#c0392b;font-weight:700;"><?= number_format($cijena * (1 - $popust / 100), 2, ',', '') ?> €</span>
                  <span style="color:#666e7a;font-size:12px;"> / <?= htmlspecialchars($jed) ?></span>
                <?php else: ?>
                  <?= $cijena ?> € <span>/ <?= htmlspecialchars($jed) ?></span>
                <?php endif; ?>
              </div>
              <a href="<?= htmlspecialchars($url) ?>" class="btn-card-detail">Detaljnije <i class="fas fa-arrow-right"></i></a>
            </div>
          </div>
        </article>
    <?php endforeach;
    $kartice = ob_get_clean();

    // Zamijeni prazan blok sa tri sivа pravougaonika stvarnim karticama
    $prazno = '<!-- Učitava se dinamički -->' . "\n"
            . '      <div class="loading-placeholder"></div>' . "\n"
            . '      <div class="loading-placeholder"></div>' . "\n"
            . '      <div class="loading-placeholder"></div>';
    if (strpos($html, $prazno) !== false) {
        $html = str_replace($prazno, $kartice, $html);
    }
}

// ---- Brojke u hero bloku ------------------------------------------------
// Pisalo je "8+ kategorija" i "90+ modela" jos otkad je toliko i bilo. U
// medjuvremenu je asortiman narastao na 13 kategorija i 117 modela, pa je
// pocetna umanjivala sopstvenu ponudu. Ovdje se brojke racunaju iz istih
// podataka iz kojih se pravi i katalog, pa vise ne mogu zastarjeti kad
// vlasnik doda proizvod. Broj kupaca se ne dira — to nije podatak iz baze.
if ($P) {
    $brKat = count(array_unique(array_filter(array_column($P, 'category'))));
    $brMod = count($P);
    $html = str_replace(
        '<div class="number">8+</div>' . "\n" . '          <div class="label">Kategorija proizvoda</div>',
        '<div class="number">' . $brKat . '</div>' . "\n" . '          <div class="label">Kategorija proizvoda</div>',
        $html
    );
    $html = str_replace(
        '<div class="number">90+</div>' . "\n" . '          <div class="label">Modela i dezena</div>',
        '<div class="number">' . $brMod . '</div>' . "\n" . '          <div class="label">Modela i dezena</div>',
        $html
    );
}

// ---- Prva slika hero slidera --------------------------------------------
// Ovo je bila najskuplja greska na pocetnoj. Slajdovi su se pravili tek iz
// JavaScripta, i to ovim redom: HTML -> tri skripte -> fetch hero-slides.json
// -> tek onda <img>. Slika koja pokriva cijeli prvi ekran kretala je posljednja
// u nizu, pa je PageSpeed mjerio LCP 6,8 s. Uz to je i JSON i svaka slika isla
// sa "?v=" + trenutno vrijeme, pa se NIJEDNA nikad nije kesirala — svaka
// posjeta je iznova skidala sve tri (preko 200 kB), a WebP i kes od 30 dana
// nisu vrijedili nista.
//
// Sada prvi slajd stoji u samom HTML-u. Pregledac ga vidi cim procita <head>
// i krece da ga skida odmah, prije ijedne skripte. <picture> bira uspravni
// kadar za telefon i polozeni za racunar, jer to nisu iste slike nego dva
// razlicita kadra. Ostala dva slajda i dalje pravi JavaScript — njih niko ne
// vidi u prvom trenutku.
$slajdovi = json_decode(@file_get_contents(__DIR__ . '/data/hero-slides.json'), true) ?: [];
$slajdovi = array_values(array_filter($slajdovi, fn($s) => is_string($s) ? $s !== '' : !empty($s['d'])));

if ($slajdovi) {
    $prvi = $slajdovi[0];
    $desk = is_string($prvi) ? $prvi : $prvi['d'];
    $mob  = is_string($prvi) ? $prvi : ($prvi['m'] ?? $prvi['d']);

    $slika = '<picture>'
           . '<source media="(max-width: 768px)" srcset="' . htmlspecialchars($mob) . '">'
           . '<img src="' . htmlspecialchars($desk) . '" alt="Enterijer sa zidnim panelima Make My Home Decor"'
           . ' data-slajd="0" fetchpriority="high" decoding="async"' . mmhDimAtributi($desk) . '>'
           . '</picture>';

    // Slider se od pocetka vidi; ranije je stajao sakriven dok ga JavaScript ne otkrije
    $html = str_replace(
        '<div id="hs-bg" style="position:absolute;inset:0;overflow:hidden;display:none;">' . "\n" . '    <div id="hs-track"></div>',
        '<div id="hs-bg" style="position:absolute;inset:0;overflow:hidden;">' . "\n" . '    <div id="hs-track">' . $slika . '</div>',
        $html
    );

    // Fotografija iz CSS-a (.hero-bg) stoji ISPOD slidera i kad ima slajdova se
    // uopste ne vidi — a pregledac ju je svejedno skidao, 40 kB uzalud pri
    // svakoj posjeti. Gasi se samo slika; tamna podloga i gradient ostaju,
    // preko slajda ionako ide #hs-overlay.
    $html = str_replace('</head>',
        '  <style>#hero .hero-bg{background-image:none}</style>' . "\n" . '</head>', $html);

    // Najava se prebacuje na sliku koja se STVARNO prikaze.
    $html = preg_replace(
        '#<link rel="preload" as="image" href="images/hero-mobile\.webp"[^>]*>\s*<link rel="preload" as="image" href="images/hero-desktop\.webp"[^>]*>#',
        '<link rel="preload" as="image" href="' . htmlspecialchars($mob) . '" media="(max-width: 768px)" fetchpriority="high">' . "\n"
        . '  <link rel="preload" as="image" href="' . htmlspecialchars($desk) . '" media="(min-width: 769px)" fetchpriority="high">',
        $html, 1
    );
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
