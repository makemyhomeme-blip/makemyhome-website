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

header('Content-Type: text/html; charset=utf-8');
echo $html;
