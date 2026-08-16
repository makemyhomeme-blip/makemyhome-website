<?php
require_once __DIR__ . '/php/slug.php';
require_once __DIR__ . '/php/dimenzije.php';
require_once __DIR__ . '/php/kalkulator.php';
$productsFile = __DIR__ . '/data/products.json';
$products = json_decode(@file_get_contents($productsFile), true) ?: [];

// Proizvod se otvara preko adrese /paneli/<ime>. Stari oblik ?id=63 i dalje radi,
// ali odmah salje 301 na novu adresu da Google prenese sve na jedno mjesto.
$slugParam = trim((string)($_GET['slug'] ?? ''));
$id        = (int)($_GET['id'] ?? 0);
$product   = null;

if ($slugParam !== '') {
    // Adresa sa velikim slovima vodi na istu stranicu, ali se 301-om vraca na mala
    // da ne postoje dvije adrese za isti proizvod.
    $slugMala = strtolower($slugParam);
    $product  = mmhProizvodPoSlugu($slugMala, $products);
    // Jedna provjera pokriva sve varijante iste adrese: velika slova u bilo kom
    // dijelu putanje, kosa crta na kraju, /PANELI/. Ako se stvarna putanja
    // razlikuje od kanonske — 301 na kanonsku.
    if ($product) {
        $putanja  = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');
        $kanonska = '/' . mmhSlugProizvoda($product);
        if ($putanja !== '' && $putanja !== $kanonska && strpos($putanja, '.php') === false) {
            $upit = ($_SERVER['QUERY_STRING'] ?? '');
            $upit = preg_replace('/(^|&)slug=[^&]*/', '', $upit);
            $upit = trim($upit, '&');
            header('Location: https://makemyhome.me' . $kanonska . ($upit !== '' ? '?' . $upit : ''), true, 301);
            exit;
        }
    }
    if (!$product) {
        // Adresa ne odgovara nijednom proizvodu — probaj staru logiku po sifri/imenu
        require_once __DIR__ . '/php/slug-match.php';
        $cilj = mmhSlugTarget($slugParam, $products);
        if ($cilj && $cilj !== 'https://makemyhome.me/products.html') {
            header('Location: ' . $cilj, true, 301);
            exit;
        }
    }
} elseif ($id > 0) {
    foreach ($products as $p) {
        if ((int)$p['id'] === $id) { $product = $p; break; }
    }
    if ($product) {
        header('Location: ' . mmhUrlProizvoda($product), true, 301);
        exit;
    }
}

// Nepostojeci proizvod MORA vratiti 404, ne 200.
// Inace Google to vidi kao "soft 404" i moze indeksirati beskonacno smecih URL-ova
// (?id=99999, ?id=abc...). Vidi: Google Search Central – Soft 404 errors.
if (!$product) {
    http_response_code(404);
    header('X-Robots-Tag: noindex', true);
}

// Kad se stranica otvori preko nove adrese (/paneli/...), u zahtjevu nema ?id=,
// pa je $id ostajao nula. Recenzije se traze po ID-u u data/reviews.json, a pod
// kljucem "0" nema nicega — zato je svaka stranica proizvoda pokazivala STARE
// tri recenzije ugradjene u products.json umjesto pet novijih iz reviews.json,
// i racunala ocjenu po njima. Otkad su adrese promijenjene, to je vazilo za
// svih 117 proizvoda. Ovdje se $id uzima iz pronadjenog proizvoda.
if ($product) $id = (int)($product['id'] ?? 0);

$ogTitle = $product
    ? htmlspecialchars($product['name'], ENT_QUOTES) . ' | Make My Home Decor'
    : 'Proizvod | Make My Home Decor';

$rawDesc = $product && !empty($product['description'])
    ? strip_tags($product['description'])
    : 'Detalji proizvoda – Make My Home Decor Podgorica. Zidni paneli i 3D letvice.';
// Pun opis za Product schema (Google dozvoljava do 5000 znakova) — meta description se skraćuje, schema NE
$schemaDesc = $rawDesc;
// Skrati na granici rijeci tako da opis ostane u opsegu koji Google prikazuje
// cijeli: 140-160 znakova. Ranije se sjeklo na 155 pa se trazio razmak unazad,
// a kad je zadnja rijec bila duga opis je padao na 139 i bio prekratak.
// Zato je gornja granica 158, a razmak se prihvata samo ako je iza 142. znaka;
// ako takvog nema (rijec duza od 16 znakova preko granice), sijece se tvrdo.
if (mb_strlen($rawDesc) > 158) {
    $cut = mb_substr($rawDesc, 0, 158);
    $lastSpace = mb_strrpos($cut, ' ');
    if ($lastSpace !== false && $lastSpace >= 142) $cut = mb_substr($cut, 0, $lastSpace);
    $rawDesc = rtrim($cut, " ,.;:-") . '…';
}
$ogDesc = htmlspecialchars($rawDesc, ENT_QUOTES);

// Facebook, WhatsApp i Viber prikazu veliku karticu tek od 600x315. Vecina
// fotografija panela je uspravna (507x900), pa se link dijelio kao sicusna
// slicica. Zato se bira prva dovoljno siroka slika ISTOG proizvoda —
// najprije glavna, pa galerija; tek ako nista ne odgovara ide showroom.
$ogKandidati = [];
if ($product) {
    if (!empty($product['image'])) $ogKandidati[] = $product['image'];
    foreach (($product['gallery'] ?? []) as $g) $ogKandidati[] = $g;
}
$ogIzbor = mmhSlikaZaDijeljenje($ogKandidati);
$ogImage = 'https://makemyhome.me/' . ltrim($ogIzbor['put'], '/');

$ogUrl = $product ? mmhUrlProizvoda($product) : 'https://makemyhome.me/products.html';
// <title> mora stati u ~60 znakova da ga Google ne siječe u rezultatima.
// Duga imena skraćujemo: prvo pada dio iza " | ", pa zagrada, pa rez na razmaku.
$titleName = $product['name'] ?? '';
$suffix    = ' | Make My Home Decor';
if (mb_strlen($titleName . $suffix) > 60 && mb_strpos($titleName, ' | ') !== false) {
    $titleName = trim(mb_substr($titleName, 0, mb_strpos($titleName, ' | ')));
}
if (mb_strlen($titleName . $suffix) > 60 && preg_match('/^(.*?)\s*\([^)]*\)\s*$/u', $titleName, $mm)) {
    $titleName = trim($mm[1]);
}
if (mb_strlen($titleName . $suffix) > 60) {
    $cut = mb_substr($titleName, 0, 60 - mb_strlen($suffix));
    $sp  = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp > 10) $cut = mb_substr($cut, 0, $sp);
    $titleName = rtrim($cut, " -–—,.");
}
// Ime proizvoda samo za sebe ne kaze STA je ni GDJE smo: 74 od 117 naslova
// bilo je krace od 45 znakova, npr. "Golden Teak | Make My Home Decor". Ko
// trazi "3d letvica cijena" ili "bambus panel podgorica" tu nema sta da nadje.
// Zato se ispred imena dodaje vrsta proizvoda, a iza cijena — ako sve stane
// u 60 znakova, koliko Google prikaze prije nego sto presijece.
$vrstaZaNaslov = [
    'bambus-paneli' => 'Bambus Panel', 'bambus-drveni' => 'Drveni Panel',
    'bambus-tekstilni' => 'Tekstilni Panel', 'bambus-mermerni' => 'Mermerni Panel',
    'bambus-metalni' => 'Metalni Panel', 'bambus-kozni' => 'Kožni Panel',
    'classic' => 'Zidni Panel', '3d-letvice' => '3D Letvica',
    'akusticni-paneli' => 'Akustični Panel', 'aluminijum-lajsne' => 'Alu Lajsna',
    'spc-pod' => 'SPC Pod', 'pu-kamen' => 'PU Kamen',
    'mdf' => 'MDF Panel', 'flex-stone' => 'Flex Stone',
];
if ($product) {
    // $prodCat se definise tek nize u fajlu — ovdje se uzima direktno
    $vrsta = $vrstaZaNaslov[$product['category'] ?? ''] ?? '';
    // Vrsta se preskace samo ako je CIJELA vec u imenu ("3D Letvica – Havana Oak").
    // Ranije se gledala samo prva rijec, pa je "MDF001" bio prepoznat kao da
    // vec sadrzi "MDF Panel" i ostajao bez ijedne rijeci koja kaze sta je.
    $imaVec = $vrsta !== '' && mb_stripos($titleName, $vrsta) !== false;
    $osnova = ($vrsta !== '' && !$imaVec) ? $vrsta . ' ' . $titleName : $titleName;

    $cij = (float)($product['price'] ?? 0);
    $pop = (int)($product['discount'] ?? 0);
    $kon = $pop > 0 ? round($cij * (1 - $pop / 100), 2) : $cij;
    $cijenaTekst = ($kon > 0 && ($product['inStock'] ?? true))
        ? ' – ' . number_format($kon, 2, ',', '') . ' €' : '';

    $sa  = $osnova . $cijenaTekst . $suffix;
    $bez = $osnova . $suffix;
    $pageTitle = htmlspecialchars(
        mb_strlen($sa) <= 62 ? $sa : (mb_strlen($bez) <= 62 ? $bez : $titleName . $suffix),
        ENT_QUOTES);
} else {
    $pageTitle = 'Proizvod | Make My Home Decor';
}

// Keyword za H1 – mapa kategorija (dodaje se uz ime proizvoda za bolji SEO)
$catKeywords = [
    'bambus-paneli' => 'Bambus Zidni Panel', 'bambus-drveni' => 'Drveni Zidni Panel',
    'bambus-tekstilni' => 'Tekstilni Zidni Panel', 'bambus-mermerni' => 'Mermerni Zidni Panel',
    'bambus-metalni' => 'Metalni Zidni Panel', 'bambus-kozni' => 'Kožni Zidni Panel',
    '3d-letvice' => '3D Dekorativna Letvica', 'akusticni-paneli' => 'Akustični Panel',
    'aluminijum-lajsne' => 'Aluminijum Lajsna', 'spc-pod' => 'SPC Pod',
    'pu-kamen' => 'PU Kamen Panel', 'mdf' => 'MDF Zidni Panel',
    'flex-stone' => 'Flex Stone Obloga', 'classic' => 'Classic Zidni Panel',
];
$h1Keyword = $product && isset($catKeywords[$product['category'] ?? '']) ? $catKeywords[$product['category']] : '';

// Naziv kategorije za breadcrumb (vidljiv + schema)
$catNames = [
    'bambus-tekstilni' => 'Tekstilni Paneli', 'bambus-drveni' => 'Drveni Paneli',
    'bambus-mermerni'  => 'Mermerni Paneli',  'bambus-metalni' => 'Metalni Paneli',
    'bambus-kozni'     => 'Kožni Paneli',     'bambus-paneli'  => 'Bambus Paneli',
    '3d-letvice'       => '3D Letvice',       'akusticni-paneli' => 'Akustični Paneli',
    'aluminijum-lajsne'=> 'Aluminijum Lajsne','spc-pod'        => 'SPC Pod',
    'pu-kamen'         => 'PU Kamen',         'classic'        => 'Classic Paneli',
    'mdf'              => 'MDF Paneli',       'flex-stone'     => 'Flex Stone',
];
$prodCat     = $product['category'] ?? '';
$prodCatName = $catNames[$prodCat] ?? '';
$prodCatUrl  = $prodCat ? mmhUrlKategorije($prodCat) : 'https://makemyhome.me/products.html';

// --- Srodni proizvodi: racunaju se na serveru da bi linkovi bili vidljivi bez JavaScripta ---
$srodneKat = [
    'bambus-drveni' => ['bambus-tekstilni', 'bambus-mermerni', 'classic'],
    'bambus-tekstilni' => ['bambus-drveni', 'bambus-mermerni', 'classic'],
    'bambus-mermerni' => ['bambus-drveni', 'bambus-metalni', 'classic'],
    'bambus-metalni' => ['bambus-mermerni', 'bambus-kozni', 'classic'],
    'bambus-kozni' => ['bambus-tekstilni', 'bambus-metalni', 'classic'],
    'bambus-paneli' => ['bambus-drveni', 'bambus-tekstilni', 'classic'],
    'classic' => ['bambus-drveni', 'bambus-tekstilni', 'bambus-mermerni'],
    '3d-letvice' => ['akusticni-paneli', 'aluminijum-lajsne', 'mdf'],
    'akusticni-paneli' => ['3d-letvice', 'mdf', 'aluminijum-lajsne'],
    'aluminijum-lajsne' => ['3d-letvice', 'bambus-drveni', 'akusticni-paneli'],
    'mdf' => ['3d-letvice', 'akusticni-paneli', 'classic'],
    'spc-pod' => ['bambus-drveni', 'flex-stone', 'pu-kamen'],
    'pu-kamen' => ['flex-stone', 'bambus-mermerni', 'spc-pod'],
    'flex-stone' => ['pu-kamen', 'bambus-mermerni', 'spc-pod'],
];
$srodni = [];
if ($product) {
    $mojId = (int)($product['id'] ?? 0);
    $uzmi = function (array $kategorije, int $koliko) use ($products, $mojId, &$srodni) {
        $vec = array_map(fn($p) => (int)$p['id'], $srodni);
        foreach ($kategorije as $k) {
            foreach ($products as $p) {
                if (count($srodni) >= $koliko) return;
                if (($p['category'] ?? '') !== $k) continue;
                $pid = (int)($p['id'] ?? 0);
                if ($pid === $mojId || in_array($pid, $vec, true)) continue;
                $srodni[] = $p; $vec[] = $pid;
            }
        }
    };
    // Iz iste kategorije uzimamo 6 KOJI SLIJEDE poslije ovog proizvoda, u krug.
    // Da nije tako, u velikoj kategoriji bi svih 30 proizvoda linkovalo istih
    // prvih 6, a ostali bi ostali bez ijedne veze sa susjedima.
    $uKat = array_values(array_filter($products, fn($p) => ($p['category'] ?? '') === $prodCat));
    $n = count($uKat);
    if ($n > 1) {
        $poz = 0;
        foreach ($uKat as $i => $p) { if ((int)($p['id'] ?? 0) === $mojId) { $poz = $i; break; } }
        for ($i = 1; $i < $n && count($srodni) < 6; $i++) $srodni[] = $uKat[($poz + $i) % $n];
    }
    if (count($srodni) < 6) $uzmi($srodneKat[$prodCat] ?? ['bambus-drveni', '3d-letvice'], 6);
}

// Vodič koji tematski odgovara ovoj kategoriji
$vodicZaKat = [
    'spc-pod' => ['spc-ili-laminat.html', 'SPC pod ili laminat — šta je bolje za stan'],
    'akusticni-paneli' => ['akusticni-paneli-kancelarija.html', 'Akustični paneli u kancelariji — koliko stvarno smanjuju buku'],
    '3d-letvice' => ['tv-zid.html', 'TV zid od letvica — kako se planira i koliko košta'],
    'mdf' => ['tv-zid.html', 'TV zid od panela — kako se planira i koliko košta'],
    'pu-kamen' => ['paneli-za-kupatilo.html', 'Paneli za kupatilo — šta podnosi vlagu'],
    'flex-stone' => ['paneli-za-kupatilo.html', 'Paneli za kupatilo — šta podnosi vlagu'],
    'bambus-drveni' => ['paneli-ili-lamperija.html', 'Zidni paneli ili lamperija — poređenje'],
    'classic' => ['paneli-ili-lamperija.html', 'Zidni paneli ili lamperija — poređenje'],
];
$vodic = $vodicZaKat[$prodCat] ?? ['montaza.html', 'Kako se paneli montiraju — korak po korak'];
?>
<!DOCTYPE html>
<html lang="sr-ME">
<head><meta charset="utf-8">

    <!-- Stranica se servira sa /paneli/... odnosno /kategorija/..., dakle jedan
       nivo dublje. Bez ovoga bi se svaka relativna putanja (css, slike, linkovi,
       i one koje pravi JavaScript) trazila unutar tog foldera i vracala 404. -->
  <base href="https://makemyhome.me/">
  <script>/* Nova adresa /paneli/<ime> nema ?id=, pa JS ovdje dobija koji je proizvod otvoren.
     Mora biti prije svake skripte koja ga cita. */
  window.__mmhProduct = <?= $product ? json_encode(['id' => (int)$product['id']]) : 'null' ?>;</script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $ogDesc ?>">
  <meta property="og:title" content="<?= $ogTitle ?>">
  <meta property="og:description" content="<?= $ogDesc ?>">
  <meta property="og:type" content="product">
  <meta property="og:url" content="<?= htmlspecialchars($ogUrl, ENT_QUOTES) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
  <meta property="og:image:width" content="<?= (int)$ogIzbor['w'] ?>">
  <meta property="og:image:height" content="<?= (int)$ogIzbor['h'] ?>">
  <meta property="og:locale" content="sr_ME">
  <meta property="og:site_name" content="Make My Home Decor">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $ogTitle ?>">
  <meta name="twitter:description" content="<?= $ogDesc ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($ogUrl, ENT_QUOTES) ?>">
  <title><?= $pageTitle ?></title>
<?php if ($product):
  $price     = (float)($product['price'] ?? 0);
  $discount  = (int)($product['discount'] ?? 0);
  $salePrice = $discount > 0 ? round($price * (1 - $discount / 100), 2) : $price;
  $inStock   = $product['inStock'] ?? true;
  $images    = array_filter(array_merge(
    [$ogImage],
    array_map(fn($g) => 'https://makemyhome.me/' . $g, $product['gallery'] ?? [])
  ));
  $offers = [
    '@type'        => 'Offer',
    'url'          => $ogUrl,
    'price'        => (string)$salePrice,
    'priceCurrency'=> 'EUR',
    // Google preporucuje oba polja uz ponudu; bez njih u Search Console stoji
    // upozorenje. Datum je kraj sljedeceg mjeseca i racuna se pri svakom
    // ucitavanju, pa nikad ne zastari.
    // Google (Merchant listings) trazi i pocetak vazenja cijene uz priceValidUntil.
    // Popust ide po mjesecima, pa je pocetak prvi dan tekuceg mjeseca.
    'validFrom'      => date('Y-m-01'),
    'itemCondition'   => 'https://schema.org/NewCondition',
    'priceValidUntil' => date('Y-m-t', strtotime('first day of next month')),
    'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    'seller'       => ['@type' => 'Organization', 'name' => 'Make My Home Decor', 'url' => 'https://makemyhome.me'],
    'hasMerchantReturnPolicy' => [
      '@type'                => 'MerchantReturnPolicy',
      'applicableCountry'    => 'ME',
      'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
      'merchantReturnDays'   => 7,
      'returnMethod'         => 'https://schema.org/ReturnByMail',
      'returnFees'           => 'https://schema.org/FreeReturn',
    ],
    'shippingDetails' => [
      '@type'              => 'OfferShippingDetails',
      'shippingRate'       => [
        '@type'    => 'MonetaryAmount',
        'value'    => '20',
        'currency' => 'EUR',
      ],
      'shippingDestination' => [
        '@type'          => 'DefinedRegion',
        'addressCountry' => 'ME',
      ],
      'deliveryTime' => [
        '@type'        => 'ShippingDeliveryTime',
        'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 2, 'unitCode' => 'DAY'],
        'transitTime'  => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 4, 'unitCode' => 'DAY'],
      ],
    ],
  ];
  if ($discount > 0) {
    $offers['priceSpecification'] = [
      ['@type' => 'UnitPriceSpecification', 'price' => (string)$salePrice,  'priceCurrency' => 'EUR'],
      ['@type' => 'UnitPriceSpecification', 'priceType' => 'https://schema.org/ListPrice', 'price' => (string)$price, 'priceCurrency' => 'EUR'],
    ];
  }
  // Reviews & aggregate rating — jedan izvor istine: data/reviews.json
  // (spojene recenzije: bogat tekst + crnogorski gradovi + svjezi datumi)
  $allReviews = json_decode(@file_get_contents(__DIR__ . '/data/reviews.json'), true) ?: [];
  $rvBlock    = $allReviews[(string)$id] ?? null;
  if ($rvBlock && !empty($rvBlock['items'])) {
    $reviews = array_map(fn($r) => [
      'author' => $r['name'], 'city' => $r['city'], 'date' => $r['date'],
      'rating' => $r['stars'], 'text' => $r['text'],
    ], $rvBlock['items']);
    $revCount  = (int)$rvBlock['count'];
    $avgRating = $rvBlock['avg'];
    $rvDist    = $rvBlock['dist'];
  } else {
    $reviews   = $product['reviews'] ?? [];
    $revCount  = count($reviews);
    $avgRating = $revCount > 0 ? round(array_sum(array_column($reviews, 'rating')) / $revCount, 1) : null;
    $rvDist    = [];
    foreach ([5,4,3,2,1] as $s) $rvDist[(string)$s] = count(array_filter($reviews, fn($r) => (int)($r['rating'] ?? 5) === $s));
  }
  // Pomocne funkcije za prikaz ocjena — definisane RANO jer ih koristi i bocni blok i recenzije
  $revPlural = function(int $n) {
    $d1 = $n % 10; $d2 = $n % 100;
    if ($d1 === 1 && $d2 !== 11) return 'recenzija';
    if ($d1 >= 2 && $d1 <= 4 && !($d2 >= 12 && $d2 <= 14)) return 'recenzije';
    return 'recenzija';
  };
  $stars = function(int $n) {
    $o = '';
    for ($i = 1; $i <= 5; $i++) $o .= '<i class="fas fa-star ' . ($i <= $n ? 'rv-star-gold' : 'rev-star-empty') . '"></i>';
    return $o;
  };
  $monthMap   = ['Januar'=>'01','Februar'=>'02','Mart'=>'03','April'=>'04','Maj'=>'05','Juni'=>'06',
                 'Juli'=>'07','Avgust'=>'08','Septembar'=>'09','Oktobar'=>'10','Novembar'=>'11','Decembar'=>'12'];
  $schema = [
    '@context'   => 'https://schema.org',
    '@type'      => 'Product',
    'name'       => $product['name'] ?? '',
    'description'=> $schemaDesc,
    'image'      => array_values($images),
    'sku'        => preg_replace('/\s+/', '-', trim($product['sku'] ?? $product['name'] ?? '')),
    'brand'      => ['@type' => 'Brand', 'name' => 'Make My Home Decor'],
    'offers'     => $offers,
  ];
  // category / color / material — Google ih koristi da poveze proizvod sa upitom
  // ("bijeli mermerni panel", "bambus zidni panel"). Citaju se iz vec postojecih polja.
  if ($prodCatName) $schema['category'] = $prodCatName;
  foreach (($product['features'] ?? []) as $sf) {
      if (!isset($schema['color']) && str_starts_with($sf, 'Boja:')) {
          $schema['color'] = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', mb_substr($sf, 5)));
      }
      if (!isset($schema['material']) && str_starts_with($sf, 'Materijal:')) {
          $schema['material'] = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', mb_substr($sf, 10)));
      }
  }
  // ---- OCJENE U STRUKTURIRANIM PODACIMA — NAMJERNO ISKLJUCENO ----
  // Google (Review snippet – General guidelines): "Ratings must be sourced directly from users".
  // Dok se ne potvrdi da svaka recenzija dolazi od stvarnog kupca, aggregateRating i review
  // NE saljemo Google-u. Rizik je rucna kazna i gubitak zvjezdica na CIJELOM sajtu.
  // Recenzije i dalje stoje vidljivo na stranici (to je dozvoljeno) — samo nisu oznacene u schemi.
  // Kada budu iz forme koju popunjavaju kupci, ovaj blok se vraca uklanjanjem "false &&".
  if (false && $avgRating !== null) {
    $schema['aggregateRating'] = [
      '@type'       => 'AggregateRating',
      'ratingValue' => (string)$avgRating,
      'bestRating'  => '5',
      'worstRating' => '1',
      'reviewCount' => $revCount,
    ];
    $schema['review'] = array_map(function($r) use ($monthMap) {
      $date = '';
      if (preg_match('/^(\w+)\s+(\d{4})$/', trim($r['date'] ?? ''), $m)) {
        $date = $m[2] . '-' . ($monthMap[$m[1]] ?? '01') . '-01';
      }
      $rev = [
        '@type'        => 'Review',
        'author'       => ['@type' => 'Person', 'name' => $r['author'] ?? ''],
        'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (string)($r['rating'] ?? 5), 'bestRating' => '5', 'worstRating' => '1'],
        'reviewBody'   => $r['text'] ?? '',
      ];
      // datePublished samo ako je datum validan — prazan string bi Google prijavio kao invalid value
      if ($date !== '') $rev['datePublished'] = $date;
      return $rev;
    }, $reviews);
  }
  echo '<script type="application/ld+json">' . "\n";
  echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  echo "\n</script>\n";
?>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Početna", "item": "https://makemyhome.me/" },
      { "@type": "ListItem", "position": 2, "name": "Proizvodi", "item": "https://makemyhome.me/products.html" },
<?php if ($prodCatName): ?>      { "@type": "ListItem", "position": 3, "name": "<?= htmlspecialchars($prodCatName, ENT_QUOTES) ?>", "item": "<?= htmlspecialchars($prodCatUrl, ENT_QUOTES) ?>" },
      { "@type": "ListItem", "position": 4, "name": "<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>", "item": "<?= htmlspecialchars($ogUrl, ENT_QUOTES) ?>" }
<?php else: ?>      { "@type": "ListItem", "position": 3, "name": "<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>", "item": "<?= htmlspecialchars($ogUrl, ENT_QUOTES) ?>" }
<?php endif; ?>
    ]
  }
  </script>
<?php endif; ?>
  <link rel="icon" type="image/x-icon" href="images/favicon.ico">
  <link rel="icon" type="image/png" href="images/favicon-512.png">
  <link rel="apple-touch-icon" sizes="512x512" href="images/favicon-512.png">
  <meta name="theme-color" content="#1a1a1a">
  <link rel="preload" href="fa/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
  <!-- Ikone se ucitavaju bez blokiranja: media="print" znaci da pregledac fajl
       preuzme ali ga ne ceka da bi iscrtao stranicu, a onload ga odmah vrati u
       upotrebu. Ikone su ukras — LCP element je tekst i ne smije da ceka na njih.
       Pravilo ispod cuva sirinu 1em za svaku ikonu dok CSS ne stigne, da se
       dugmad ne pomjere kad se ikone pojave (CLS ostaje 0). -->
  <style>i[class*="fa-"]{display:inline-block;width:1em;height:1em}</style>
  <link rel="stylesheet" href="fa/css/mmh-ikone.css?v=2" media="print" onload="this.media='all';this.onload=null">
  <noscript><link rel="stylesheet" href="fa/css/mmh-ikone.css?v=2"></noscript>
  <link rel="preload" href="fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="css/style-v5.css?v=54">
  <style>
    @media(min-width:769px){.nav-menu{gap:0!important;flex-wrap:nowrap!important;}.nav-link{font-size:12px!important;padding:8px 5px!important;white-space:nowrap!important;}.logo{flex-shrink:0!important;}.logo-text .name,.logo-text .tagline{white-space:nowrap!important;}#desk-search-wrap{flex-shrink:0!important;margin-right:4px!important;}}
  @media(max-width:768px){#desk-search-wrap{display:none!important;}}
  </style>
<style id="nav-fix">
@media(min-width:769px){
  .header-inner{flex-wrap:nowrap!important;}
  .logo{flex-shrink:0!important;margin-right:20px!important;}
  .logo-img{height:44px!important;}
  .logo-text .name{white-space:nowrap!important;font-size:16px!important;}
  .logo-text .tagline{white-space:nowrap!important;font-size:9px!important;}
  .nav-menu{gap:0!important;flex-wrap:nowrap!important;flex-shrink:1!important;margin-left:auto!important;}
  .nav-link{white-space:nowrap!important;font-size:12px!important;padding:8px 5px!important;}
  .nav-link.nav-cta{padding:7px 14px!important;margin-left:4px!important;}
  #desk-search-wrap{flex-shrink:0!important;margin-right:4px!important;}
}
</style>
  <!-- Google Analytics u rezimu BEZ KOLACICA (Consent Mode v2, trajno "denied").
       GA4 ne postavlja nijedan kolacic i ne cuva identifikator na uredjaju posjetioca,
       pa traka za saglasnost nije potrebna. Statistika i dalje stize u agregatnom obliku:
       posjete, izvori, najgledanije stranice, uredjaji, drzave. -->

<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('consent','default',{
    ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied',
    analytics_storage:'denied', functionality_storage:'granted',
    security_storage:'granted', wait_for_update:500
  });
  gtag("js", new Date());
  gtag("config", "G-4LLQCZ8CV4");
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-4LLQCZ8CV4"></script>
<style>
  /* Footer kategorije: čiste 2 kolone bez lomljenja u dva reda */
  .footer-links-grid{display:block!important;column-count:2!important;column-gap:18px!important;}
  .footer-links-grid li{break-inside:avoid;margin-bottom:9px;}
  .footer-links-grid a{font-size:13px!important;white-space:nowrap;}
</style>
<style id="nav-wide">
/* Laptopovi 769–1599px: sve stavke MORAJU stati u red (Kontakt je ranije ispadao van ekrana) */
@media(min-width:769px) and (max-width:1599px){
  .header-inner{max-width:100%!important;padding-left:14px!important;padding-right:14px!important;}
  .nav-menu{gap:0!important;flex-wrap:nowrap!important;}
  .nav-link{font-size:11.5px!important;padding:8px 4px!important;letter-spacing:0!important;}
  .nav-link.nav-cta{padding:7px 11px!important;margin-left:3px!important;}
  .logo{margin-right:8px!important;}
  .logo-img{height:36px!important;}
  .logo-text .name{font-size:13.5px!important;}
  .logo-text .tagline{display:none!important;}
  #desk-search-wrap{width:150px!important;margin-right:4px!important;}
}
/* 769–1099px: previše stavki za jedan red — koristi se hamburger meni (kao na telefonu) */
@media(min-width:769px) and (max-width:1149px){
  .nav-menu{display:none!important;position:absolute!important;top:75px!important;left:0!important;right:0!important;
    background:#1a1a1a!important;flex-direction:column!important;padding:20px!important;gap:4px!important;
    border-top:1px solid rgba(201,168,108,0.2)!important;z-index:9999!important;max-height:calc(100vh - 90px)!important;max-height:calc(100dvh - 90px)!important;overflow-y:auto!important;}
  .nav-menu.open{display:flex!important;}
  .hamburger{display:flex!important;}
  .nav-link{width:100%!important;justify-content:center!important;font-size:14px!important;padding:11px 8px!important;}
  .nav-link.nav-cta{width:100%!important;margin-left:0!important;padding:11px 8px!important;}
  #mob-search-box{display:block!important;}
  #desk-search-wrap{display:none!important;}
  .logo-text .tagline{display:none!important;}
}
/* Široki ekrani: header koristi više prostora, stavke razmaknute */
@media(min-width:1600px){
  .header-inner{max-width:1560px!important;}
  .nav-link{font-size:13px!important;padding:8px 10px!important;}
  #desk-search-wrap{width:250px!important;}
}
@media(min-width:1700px){
  .header-inner{max-width:1720px!important;}
  .nav-link{font-size:13.5px!important;padding:8px 13px!important;}
}
</style>
</head>
<body>

<header id="header">
  <div class="header-inner">
    <a href="/" class="logo">
      <img src="images/logo-transparent.png" alt="Make My Home Decor" class="logo-img" width="567" height="567">
      <div class="logo-text">
        <span class="name">Make My Home Decor</span>
        <span class="tagline">Dekorativni Bambus Paneli</span>
      </div>
    </a>

    <div id="desk-search-wrap" style="position:relative;flex-shrink:0;margin-left:auto;margin-right:8px;width:210px;">
      <div style="position:relative;">
        <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#c9a86c;font-size:12px;pointer-events:none;"></i>
        <input id="desk-search-input" type="text" aria-label="Pretraga proizvoda" placeholder="Traži proizvod…" autocomplete="off"
          style="width:100%;box-sizing:border-box;padding:8px 10px 8px 30px;border-radius:20px;border:1.5px solid rgba(201,168,108,0.4);background:rgba(255,255,255,0.06);color:#fff;font-size:12px;font-family:inherit;outline:none;-webkit-appearance:none;transition:border-color .2s,background .2s;"
          onfocus="this.style.borderColor='rgba(201,168,108,0.85)';this.style.background='rgba(255,255,255,0.1)'"
          onblur="this.style.borderColor='rgba(201,168,108,0.4)';this.style.background='rgba(255,255,255,0.06)'">
      </div>
      <div id="desk-search-results" style="display:none;position:absolute;top:calc(100% + 6px);left:0;width:300px;background:#1a1814;border:1px solid rgba(201,168,108,0.25);border-radius:12px;overflow:hidden;max-height:420px;overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,0.5);z-index:99999;"></div>
    </div>

    <nav id="nav-menu" class="nav-menu">
      <div id="mob-search-box" style="padding:4px 0 14px;width:100%;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#c9a86c;font-size:14px;pointer-events:none;z-index:1;"></i>
          <input id="mob-search-input" type="text" aria-label="Pretraga proizvoda po imenu ili šifri" placeholder="Traži po imenu ili šifri…" autocomplete="off"
            style="width:100%;box-sizing:border-box;padding:12px 14px 12px 40px;border-radius:10px;border:1.5px solid rgba(201,168,108,0.35);background:rgba(255,255,255,0.07);color:#fff;font-size:15px;font-family:inherit;outline:none;-webkit-appearance:none;">
        </div>
        <div id="mob-search-results" style="display:none;margin-top:6px;border-radius:10px;overflow:hidden;max-height:52vh;overflow-y:auto;background:rgba(20,18,15,0.97);border:1px solid rgba(201,168,108,0.2);"></div>
      </div>
      <a href="/" class="nav-link">Početna</a>
      <a href="inspiracija.html" class="nav-link nav-insp">Inspiracija</a>
      <a href="/kategorija/bambus-paneli" class="nav-link">Bambus Paneli</a>
      <a href="/kategorija/3d-letvice" class="nav-link">3D Letvice</a>
      <a href="/kategorija/akusticni-paneli" class="nav-link">Akustični</a>
      <a href="/kategorija/mdf" class="nav-link">MDF</a>
      <a href="/kategorija/aluminijum-lajsne" class="nav-link">Alu Lajsne</a>
      <a href="/kategorija/pu-kamen" class="nav-link">PU Kamen</a>
      <a href="/kategorija/flex-stone" class="nav-link">Flex Stone</a>
      <a href="/kategorija/spc-pod" class="nav-link">SPC Pod</a>
      <a href="decor-box.html" class="nav-link">Decor Box</a>
      <a href="faq.html" class="nav-link">Pitanja</a>
      <a href="about.html" class="nav-link">O Nama</a>
      <a href="contact.html" class="nav-link nav-cta">Kontakt</a>
    </nav>
    <a href="korpa.html" class="cart-icon-btn" aria-label="Korpa" style="position:relative;display:flex;align-items:center;justify-content:center;width:40px;height:40px;color:#c9a86c;font-size:18px;text-decoration:none;flex-shrink:0;margin-right:4px;">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-badge" style="display:none;position:absolute;top:3px;right:3px;background:#c0392b;color:#fff;border-radius:50%;width:17px;height:17px;font-size:9px;font-weight:700;align-items:center;justify-content:center;line-height:1;"></span>
    </a>
    <button id="hamburger" class="hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<main id="sadrzaj">

<section class="product-detail-section" style="padding-top:140px;">
  <div class="container">
<?php if (!$product): ?>
    <div style="max-width:640px;margin:0 auto;text-align:center;padding:30px 16px 60px;">
      <i class="fas fa-box-open" style="font-size:56px;color:#c9a86c;margin-bottom:22px;display:block;"></i>
      <h1 style="font-size:1.7em;color:#1a1a1a;margin-bottom:14px;">Proizvod nije pronađen</h1>
      <p style="color:#5a6672;line-height:1.75;margin-bottom:10px;">
        Ovaj proizvod više nije u ponudi ili je link pogrešan. Cijela ponuda zidnih panela,
        bambus obloga, 3D letvica, akustičnih panela, PU kamena i SPC podova je i dalje dostupna u katalogu.
      </p>
      <p style="color:#5a6672;line-height:1.75;margin-bottom:26px;">
        Ako tražite određeni dezen, pozovite nas na <a href="tel:+38269105222" style="color:#c9a86c;font-weight:600;">069 105 222</a>
        ili svratite u showroom u Podgorici — Vojvode Maša Đurovića 43, City Kvart.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="products.html" class="btn btn-gold">Pogledaj sve proizvode</a>
        <a href="contact.html" class="btn btn-outline">Kontakt</a>
      </div>
      <div style="margin-top:34px;text-align:left;">
        <p style="font-weight:700;color:#1a1a1a;margin-bottom:10px;">Popularne kategorije:</p>
        <ul style="list-style:none;padding:0;display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach ($catNames as $ck => $cn): ?>
          <li><a href="/kategorija/<?= htmlspecialchars($ck, ENT_QUOTES) ?>"
                 style="display:inline-block;background:#f5f0eb;color:#8a6d2f;padding:6px 13px;border-radius:18px;font-size:13px;text-decoration:none;"><?= htmlspecialchars($cn) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
<?php endif; ?>
<?php if ($product): ?>
    <nav class="breadcrumb" aria-label="Navigacija" style="margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;color:#8a8a8a;">
      <a href="/" style="color:#666e7a;text-decoration:none;">Početna</a>
      <i class="fas fa-chevron-right" style="font-size:9px;"></i>
      <a href="products.html" style="color:#666e7a;text-decoration:none;">Proizvodi</a>
<?php if ($prodCatName): ?>      <i class="fas fa-chevron-right" style="font-size:9px;"></i>
      <a href="/kategorija/<?= htmlspecialchars($prodCat, ENT_QUOTES) ?>" style="color:#795f32;text-decoration:none;font-weight:600;"><?= htmlspecialchars($prodCatName) ?></a>
<?php endif; ?>      <i class="fas fa-chevron-right" style="font-size:9px;"></i>
      <span style="color:#5a5a5a;"><?= htmlspecialchars($product['name']) ?></span>
    </nav>
<?php endif; ?>
    <div class="product-detail-grid">

      <div class="product-gallery">
        <div class="gallery-main" id="gallery-main">
          <?php
          /* Glavna slika i sličice su ranije bile prazne dok ih JavaScript ne
             popuni — Googlebot na svih 117 stranica proizvoda nije vidio
             nijednu fotografiju u HTML-u. Sada ih ispisuje server; JavaScript
             ih poslije zamijeni istima, uz uvećavanje i listanje. */
          $galSlike = [];
          if ($product) {
              if (!empty($product['image'])) $galSlike[] = [$product['image'], $product['name'] . ' – ' . $prodCatName . ' | Make My Home Decor Podgorica'];
              foreach (($product['gallery'] ?? []) as $gi => $gsrc) {
                  $galSlike[] = [$gsrc, $product['name'] . ' u enterijeru ' . ($gi + 1) . ' – ' . $prodCatName . ' | Make My Home Decor'];
              }
          }
          if ($galSlike): ?>
          <img id="gallery-main-img" src="<?= htmlspecialchars($galSlike[0][0]) ?>" alt="<?= htmlspecialchars($galSlike[0][1]) ?>"<?= mmhDimAtributi($galSlike[0][0]) ?> style="width:100%;height:auto;display:block;border-radius:16px;">
          <?php else: ?>
          <div class="loading-placeholder" style="width:100%;height:100%;border-radius:16px;"></div>
          <?php endif; ?>
        </div>
        <div class="gallery-thumbs" id="gallery-thumbs">
          <?php foreach ($galSlike as $gi => $g): ?>
          <img class="gallery-thumb<?= $gi === 0 ? ' active' : '' ?>" src="<?= htmlspecialchars($g[0]) ?>" alt="<?= htmlspecialchars($g[1]) ?>" loading="lazy"<?= mmhDimAtributi($g[0]) ?> onclick="window._goToGallery && window._goToGallery(<?= $gi ?>)">
          <?php endforeach; ?>
        </div>
        <p class="gallery-open-hint"><i class="fas fa-search-plus"></i> Tapni na sliku za prikaz u punoj rezoluciji</p>
        <div id="gallery-specs" class="gallery-specs-desktop"<?= $product ? ' data-ssr="1"' : '' ?>><?php
          /* Harmoniku ispisuje server. Ranije je ovdje stajao obican spisak,
             a JavaScript bi ga zamijenio harmonikom — pa se pri svakom
             osvjezavanju vidjela promjena. Sada postoji samo jedan ispis. */
          /* Naslov "Karakteristike – <ime>" je stajao ovdje prije nego sto je
             blok zamijenjen harmonikom. Harmonika koristi dugme umjesto
             naslova, pa je H2 nestao sa svih 117 stranica proizvoda — a u
             njemu stoji i ime proizvoda i rijec koju ljudi kucaju. Nasla ga
             je tek uporedba snimka prije i poslije (alat/snimak.py). */
          if ($product) {
              echo '<h2 class="specs-h2">Karakteristike – '
                 . htmlspecialchars($product['name'] ?? '') . '</h2>';
              echo mmhHarmonikaHTML($product);
          }
        ?></div>
      </div>

      <div class="product-info">
        <?php
        /* ===== DESNA KOLONA — ISPISUJE SERVER, JAVASCRIPT JE VISE NE CRTA =====
         *
         * Ranije je server ispisivao jednu, jednostavniju verziju (naslov,
         * cijena, opis, stanje), a JavaScript bi je — tek posto skine
         * data/products.json — u cjelini zamijenio drugom: sa sifrom, ocjenom,
         * drugacije slozenom cijenom i kalkulatorom. Kupac je pri osvjezavanju
         * vidio prvo jedan raspored pa drugi, a kalkulator se pojavljivao
         * zadnji. To se dogadjalo na SVAKOJ stranici proizvoda.
         *
         * Sada server odmah ispisuje konacan izgled. Predlozak u
         * js/products.js je obrisan da ne ostanu dvije kopije istog HTML-a
         * koje se vremenom raziđu — JavaScript samo ozivi dugmad.
         * Oznaka data-ssr="1" mu kaze da ovdje nema sta da crta.
         */
        $pKat      = $prodCatName ?: ($product['category'] ?? '');
        $pokriva   = $product ? mmhPokrivenostPoKomadu($product) : null;
        $dimKom    = $product ? mmhDimenzijeKomada($product) : null;
        $letvW     = ($product && ($product['category'] ?? '') === '3d-letvice') ? mmhSirinaLetviceCm($product) : null;
        $jeLajsna  = ($product['category'] ?? '') === 'aluminijum-lajsne';
        $jeSpc     = ($product['category'] ?? '') === 'spc-pod';
        $jedinica  = $product['unit'] ?? 'kom';
        $waTekst   = !empty($product['sku']) ? 'šifra: ' . $product['sku'] : ($product['name'] ?? '');
        $waLink    = 'https://wa.me/38269105222?text=Zdravo%2C%20zanima%20me%20panel%20' . rawurlencode($waTekst);
        ?>
        <div id="product-info-content"<?= $product ? ' data-ssr="1"' : '' ?>>
          <?php if ($product): ?>
          <div class="product-category"><?= htmlspecialchars($pKat) ?></div>
          <h1 class="product-name"><?= htmlspecialchars($product['name'] ?? '') ?></h1>
          <?php if (!empty($product['sku'])): ?>
          <div style="display:inline-flex;align-items:center;gap:6px;background:#f5f0eb;border:1.5px solid rgba(201,168,108,0.4);border-radius:8px;padding:5px 12px;margin:6px 0 12px;vertical-align:middle;"><span style="font-size:10px;color:#795f32;font-weight:700;text-transform:uppercase;letter-spacing:1px;line-height:1;">Šifra</span><span style="font-size:13px;color:#1a1a1a;font-family:monospace;font-weight:700;letter-spacing:0.5px;line-height:1;"><?= htmlspecialchars($product['sku']) ?></span></div>
          <?php endif; ?>
          <?php
          /* Ocjena uz naslov cita STVARNI prosjek tog proizvoda.
             Ranije je ovdje stajalo tvrdo upisano "(4.8) · Odlično" za svaki
             proizvod, dok blok nize prikazuje pravi prosjek — pa je Nordic Oak
             na istoj stranici imao 4,8 uz naslov i 4,6 ispod. Pogadjalo je 43
             proizvoda: deset ih ima prosjek 4,6, trideset tri 5,0.
             Bez recenzija se ne prikazuje nista, umjesto izmisljene ocjene. */
          if ($avgRating !== null && $revCount > 0):
            $oc = (float) $avgRating;
            // Zaokruzivanje na najblizu polovinu: 4,8 se prikazuje kao pet
            // punih zvjezdica, 4,6 kao cetiri i po. Racunanje "cijeli dio pa
            // ostatak" davalo je 4,8 → cetiri pune i jedna prazna, sto izgleda
            // kao 4,0 iako pise 4.8.
            $pol  = (int) round($oc * 2);      // broj polovina, 0..10
            $pun  = intdiv($pol, 2);
            $pola = ($pol % 2) === 1;
            $rijec = $oc >= 4.75 ? 'Odlično' : ($oc >= 4.0 ? 'Vrlo dobro' : 'Dobro');
          ?>
          <div class="product-rating">
            <span class="rating-stars"><?php
              for ($i = 1; $i <= 5; $i++) {
                  if ($i <= $pun)                   echo '<i class="fas fa-star"></i>';
                  elseif ($i === $pun + 1 && $pola) echo '<i class="fas fa-star-half-alt"></i>';
                  else                              echo '<i class="far fa-star"></i>';
              }
            ?></span>
            <span class="rating-count">(<?= htmlspecialchars(number_format($oc, 1, '.', '')) ?>) · <?= $rijec ?></span>
          </div>
          <?php endif; ?>

          <?php if ($discount > 0): ?>
          <div class="product-price-lg">
            <span style="text-decoration:line-through;color:#767676;font-size:18px;font-weight:400;"><?= mmhBroj($price) ?> €</span>
            <span style="margin-left:8px;"><?= mmhBroj($salePrice) ?> €</span>
            <span style="background:#c0392b;color:#fff;border-radius:14px;padding:3px 12px;font-size:13px;font-weight:700;margin-left:8px;vertical-align:middle;">-<?= $discount ?>% POPUST</span>
            <span style="color:#666e7a;font-size:14px;"> / <?= htmlspecialchars($jedinica) ?></span>
          </div>
          <?php else: ?>
          <div class="product-price-lg"><?= mmhBroj($price) ?> € <span>/ <?= htmlspecialchars($jedinica) ?></span></div>
          <?php endif; ?>

          <?php if (str_starts_with($product['category'] ?? '', 'bambus') || ($product['category'] ?? '') === 'classic'): ?>
          <a href="/kategorija/aluminijum-lajsne" style="display:flex;align-items:center;gap:10px;background:rgba(201,168,108,0.1);border:1.5px solid rgba(201,168,108,0.35);border-radius:12px;padding:12px 16px;margin:14px 0 18px;text-decoration:none;color:inherit;">
            <i class="fas fa-ruler-combined" style="color:#c9a86c;font-size:18px;flex-shrink:0;"></i>
            <span style="font-size:13.5px;color:#3a3a3a;line-height:1.4;">Potrebne su vam <strong>lajsne za spajanje panela</strong>? <span style="color:#795f32;font-weight:700;white-space:nowrap;">Pogledajte ovdje <i class="fas fa-arrow-right" style="font-size:11px;"></i></span></span>
          </a>
          <?php endif; ?>

          <?php if ($jeLajsna): ?>
          <!-- Lajsne se prodaju na komad — bez kalkulatora m² -->
          <div class="pq-panel" id="pq-qty" style="display:block;">
            <div class="pq-stepper">
              <button type="button" class="pq-step-btn" onclick="stepPqQty(-1)">−</button>
              <span class="pq-step-val" id="pq-qty-val">1</span>
              <button type="button" class="pq-step-btn" onclick="stepPqQty(1)">+</button>
            </div>
          </div>
          <div id="pq-calc" style="display:none;"><div class="pq-calc-result" id="calc-result"></div></div>
          <?php else: ?>
          <div class="pq-tabs">
            <button class="pq-tab active" onclick="switchPqTab('calc', this)">
              <i class="fas fa-calculator"></i> Kalkulator m²
            </button>
            <button class="pq-tab" onclick="switchPqTab('qty', this)">
              <i class="fas fa-list-ol"></i> Unesi Količinu
            </button>
          </div>

          <div class="pq-panel" id="pq-qty" style="display:none;">
            <div class="pq-stepper">
              <button type="button" class="pq-step-btn" onclick="stepPqQty(-1)">−</button>
              <span class="pq-step-val" id="pq-qty-val">1</span>
              <button type="button" class="pq-step-btn" onclick="stepPqQty(1)">+</button>
            </div>
            <?php if (!$jeSpc && $pokriva): ?>
            <div class="pq-m2-badge" id="pq-m2-badge">
              1 <?= $jedinica === 'm²' ? 'm²' : 'kom' ?> = <?= mmhBroj($pokriva) ?> m²
            </div>
            <?php endif; ?>
          </div>

          <div class="pq-panel" id="pq-calc">
            <?php if ($letvW && $pokriva): ?>
            <div style="background:rgba(201,168,108,0.12);border:1px solid rgba(201,168,108,0.35);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#c9a86c;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-ruler-horizontal"></i>
              <span>Svaka letvica: <strong>280cm visina × <?= rtrim(rtrim(number_format($letvW, 1, ',', ''), '0'), ',') ?>cm širina</strong> → 1 letvica = <?= mmhBroj($pokriva) ?> m²</span>
            </div>
            <?php elseif ($dimKom && !$jeSpc && $pokriva): ?>
            <div style="background:rgba(201,168,108,0.12);border:1px solid rgba(201,168,108,0.35);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#c9a86c;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-ruler-combined"></i>
              <span>Svaki panel: <strong><?= $dimKom['w'] ?> × <?= $dimKom['h'] ?> cm</strong> &nbsp;·&nbsp; 1 kom = <?= mmhBroj($pokriva) ?> m² &nbsp;·&nbsp; Uključuje <strong>+5% rezerva</strong></span>
            </div>
            <?php elseif ($jeSpc): ?>
            <div style="background:rgba(92,74,50,0.12);border:1px solid rgba(92,74,50,0.4);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#9b7d56;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-ruler-combined"></i>
              <?php if ($dimKom): ?>
              <span>Svaka daska: <strong><?= rtrim(rtrim(number_format($dimKom['w'], 1, ',', ''), '0'), ',') ?> × <?= rtrim(rtrim(number_format($dimKom['h'], 1, ',', ''), '0'), ',') ?> cm</strong> &nbsp;·&nbsp; Kalkulator uključuje <strong>+10% otpad</strong> za rezove</span>
              <?php else: ?>
              <span>Kalkulator uključuje <strong>+10% otpad</strong> za rezove</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="pq-calc-inner">
              <div class="pq-calc-field">
                <label for="wall-w"><?= $jeSpc ? 'Dužina prostorije' : 'Širina zida' ?></label>
                <div class="pq-calc-stepper">
                  <button type="button" onclick="stepCalc('wall-w',-0.5)">−</button>
                  <input type="number" id="wall-w" aria-label="<?= $jeSpc ? 'Dužina prostorije u metrima' : 'Širina zida u metrima' ?>" value="<?= $jeSpc ? '3' : '1' ?>" min="0.5" max="50" step="0.5" oninput="calcPanels()">
                  <span class="pq-calc-unit">m</span>
                  <button type="button" onclick="stepCalc('wall-w',0.5)">+</button>
                </div>
              </div>
              <div class="pq-calc-field">
                <label for="wall-h"><?= $jeSpc ? 'Širina prostorije' : 'Visina zida' ?></label>
                <div class="pq-calc-stepper">
                  <button type="button" onclick="stepCalc('wall-h',-0.1)">−</button>
                  <input type="number" id="wall-h" aria-label="<?= $jeSpc ? 'Širina prostorije u metrima' : 'Visina zida u metrima' ?>" value="<?= $jeSpc ? '3.5' : '2.8' ?>" min="0.5" max="50" step="0.1" oninput="calcPanels()">
                  <span class="pq-calc-unit">m</span>
                  <button type="button" onclick="stepCalc('wall-h',0.1)">+</button>
                </div>
              </div>
            </div>
            <div class="pq-calc-result" id="calc-result"></div>
          </div>
          <?php endif; ?>

          <?php
          // "Karakteristike:" je nekada stajalo i u opisu i u listi ispod — ista lista dva puta
          // na istoj stranici. Sada je iz opisa uklonjeno; ovaj rez ostaje za slucaj da se
          // preko admina opet unese takav tekst.
          $fullDesc = strip_tags($product['description'] ?? '');
          $cutAt = mb_strpos($fullDesc, 'Karakteristike:');
          if ($cutAt !== false) $fullDesc = trim(mb_substr($fullDesc, 0, $cutAt));
          if ($fullDesc): ?>
          <div class="product-short-desc"><?= nl2br(htmlspecialchars($fullDesc), false) ?></div>
          <?php endif; ?>

          <?php if ($inStock): ?>
          <p style="color:#1e8449;font-weight:600;margin:14px 0 0;display:flex;align-items:center;gap:8px;"><i class="fas fa-check"></i> Na stanju</p>
          <?php else: ?>
          <p style="color:#c0392b;font-weight:700;margin:14px 0 0;display:flex;align-items:center;gap:8px;"><i class="fas fa-circle-exclamation"></i> Trenutno nije na stanju</p>
          <?php endif; ?>

          <div style="display:flex;flex-direction:column;gap:10px;margin:22px 0 28px;">
            <?php if (!$inStock): ?>
            <div style="background:#fdf3f2;border:1px solid rgba(192,57,43,.3);border-radius:14px;padding:16px 18px;color:#8a3a30;font-size:14.5px;line-height:1.65;">
              Ovaj model je trenutno rasprodat. Javite nam se — reći ćemo vam kada stiže ili predložiti najbliži model koji imamo.
            </div>
            <a href="tel:+38269105222" style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:#c9a86c;color:#0a0a0a;border-radius:14px;padding:18px 24px;font-size:17px;font-weight:700;text-decoration:none;font-family:inherit;letter-spacing:0.4px;box-sizing:border-box;">
              <i class="fas fa-phone" style="font-size:17px;"></i><span>069 105 222</span>
            </a>
            <?php else: ?>
            <button onclick="addProductToCartById(<?= (int)($product['id'] ?? 0) ?>, 1)" style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:#c9a86c;color:#0a0a0a;border:none;border-radius:14px;padding:18px 24px;font-size:17px;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:0.4px;">
              <i class="fas fa-bag-shopping" style="font-size:18px;"></i>
              <span>Dodaj u Korpu</span>
            </button>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($waLink, ENT_QUOTES) ?>" target="_blank" rel="noopener" style="width:100%;display:flex;align-items:center;justify-content:center;gap:9px;padding:14px 20px;border:1.5px solid rgba(37,211,102,0.4);border-radius:14px;background:rgba(37,211,102,0.08);color:#0f7a36;font-size:14px;font-weight:600;text-decoration:none;font-family:inherit;box-sizing:border-box;">
              <i class="fab fa-whatsapp" style="font-size:17px;"></i> Pitaj nas na WhatsApp-u
            </a>
          </div>

          <!-- Na telefonu harmonika stoji ovdje; na racunaru ispod glavne slike -->
          <div class="accordion-mobile-only"><?= mmhHarmonikaHTML($product) ?></div>
          <?php else: ?>
          <div class="loading-placeholder" style="height:400px;"></div>
          <?php endif; ?>
        </div>

        <?php if ($product): ?>
        <!-- Bocni blok: popunjava prazninu pored duge liste karakteristika i
             daje kupcu razloge da kupi bas ovdje (ocjena, dostava, showroom, kontakt) -->
        <aside class="p-side">
          <?php /* Mala kutija sa ocjenom je uklonjena — isti broj je stajao na
                   tri mjesta na istoj stranici: uz naslov, ovdje, i u odjeljku
                   "Sta kazu kupci" ispod. Ostala su dva: kratka ocjena uz naslov
                   i odjeljak sa samim recenzijama. */ ?>

          <ul class="p-side-list">
            <li><i class="fas fa-truck"></i><span><strong>Dostava za 1–4 dana</strong> kurirskom službom na adresu, širom Crne Gore</span></li>
            <li><i class="fas fa-hand-holding-dollar"></i><span><strong>Plaćate kad preuzmete</strong> — gotovinom kuriru, bez avansa</span></li>
            <li><i class="fas fa-rotate-left"></i><span><strong>Zamjena u roku od 7 dana</strong> ako niste zadovoljni</span></li>
            <li><i class="fas fa-screwdriver-wrench"></i><span><strong>Montaža bez majstora</strong> — lijepi se silikonom, siječe skalpelom</span></li>
          </ul>

          <div class="p-side-box">
            <p class="p-side-box-t"><i class="fas fa-store"></i> Pogledajte uživo prije kupovine</p>
            <p class="p-side-box-p">
              Uzorak možete opipati u našem showroomu u Podgorici — <strong>Vojvode Maša Đurovića 43, City Kvart</strong>.
              Donesite mjere zida i na licu mjesta vam izračunamo koliko komada treba i koliko će koštati.
            </p>
            <p class="p-side-box-h"><i class="fas fa-clock"></i> Pon–Pet 09:00–20:00 &nbsp;·&nbsp; Sub 10:00–17:00</p>
            <div class="p-side-btns">
              <a href="tel:+38269105222" class="p-side-btn p-side-btn--call"><i class="fas fa-phone"></i> 069 105 222</a>
              <a href="https://maps.google.com/?cid=46019722303886518" target="_blank" rel="noopener"
                 class="p-side-btn p-side-btn--map"><i class="fas fa-location-dot"></i> Otvori lokaciju u Google Mapama</a>
            </div>
          </div>
        </aside>
        <?php endif; ?>
      </div>

    </div>

    <!-- Recenzije -->
    <?php
      // Recenzije — renderovane na serveru (Google ih vidi odmah, kupac dobija isti prikaz).
      // JS ih NE crta ponovo (guard: data-ssr="1" u js/products.js).
      if ($product && !empty($reviews)):
    ?>
    <div id="product-reviews" data-ssr="1" style="margin-top:60px;">
      <div class="rv-wrap">
        <h2 class="rv-title">Šta kažu kupci – <?= htmlspecialchars($product['name']) ?></h2>
        <div class="rv-summary">
          <div class="rv-score-col">
            <div class="rv-big-num"><?= htmlspecialchars(number_format((float)$avgRating, 1)) ?></div>
            <div class="rv-big-stars"><?= $stars((int)round((float)$avgRating)) ?></div>
            <div class="rv-count"><?= $revCount ?> <?= $revPlural($revCount) ?></div>
          </div>
          <div class="rv-bars-col">
            <?php foreach ([5,4,3,2,1] as $s):
              $n   = (int)($rvDist[(string)$s] ?? 0);
              $pct = $revCount > 0 ? round($n / $revCount * 100) : 0; ?>
            <div class="rv-bar-row">
              <span class="rv-bar-label"><?= $s ?></span><i class="fas fa-star rv-star-gold"></i>
              <div class="rv-bar-track"><div class="rv-bar-fill<?= $n === 0 ? ' rv-bar-fill--empty' : '' ?>" style="width:<?= $pct ?>%"></div></div>
              <span class="rv-bar-num"><?= $n ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="rv-list">
          <?php $rvI = 0; foreach ($reviews as $r): $rvI++; ?>
          <div class="rv-card<?= $rvI > 3 ? ' rv-card--hidden' : '' ?>">
            <div class="rv-card-top">
              <div class="rv-avatar"><?= htmlspecialchars(mb_substr($r['author'] ?? '?', 0, 1)) ?></div>
              <div class="rv-card-meta">
                <div class="rv-card-name"><?= htmlspecialchars($r['author'] ?? '') ?><?= !empty($r['city']) ? ' <span class="rv-card-city">· ' . htmlspecialchars($r['city']) . '</span>' : '' ?></div>
                <div class="rv-card-stars"><?= $stars((int)($r['rating'] ?? 5)) ?></div>
              </div>
              <span class="rv-card-date"><?= htmlspecialchars($r['date'] ?? '') ?></span>
            </div>
            <p class="rv-card-text"><?= htmlspecialchars($r['text'] ?? '') ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($revCount > 3): ?>
        <button type="button" class="rv-more-btn" id="rv-more-btn"
                onclick="var w=this.closest('.rv-wrap');w.classList.add('rv-expanded');this.remove();">
          Prikaži još <?= $revCount - 3 ?> <?= ($revCount - 3) === 1 ? 'komentar' : 'komentara' ?>
          <i class="fas fa-chevron-down"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Slični proizvodi — renderuje server, da linkovi rade i bez JavaScripta -->
    <?php if ($product && $srodni): ?>
    <div style="margin-top:80px;">
      <div class="gold-line"></div>
      <h2 class="section-title" style="margin-bottom:40px;">Slični Proizvodi</h2>
      <div class="products-grid" id="related-products">
        <?php foreach ($srodni as $sp):
          $spUrl   = mmhUrlProizvoda($sp);
          $spKat   = $catNames[$sp['category'] ?? ''] ?? ($sp['category'] ?? '');
          $spCijena = (float)($sp['price'] ?? 0);
          $spPopust = (int)($sp['discount'] ?? 0);
          $spNema   = ($sp['inStock'] ?? true) === false;
          $spJedin  = $sp['unit'] ?? 'kom';
        ?>
        <article class="product-card<?= $spNema ? ' out-of-stock' : '' ?>" data-category="<?= htmlspecialchars($sp['category'] ?? '') ?>" data-id="<?= (int)($sp['id'] ?? 0) ?>">
          <a href="<?= htmlspecialchars($spUrl) ?>" class="product-img" style="display:block;">
            <img src="<?= htmlspecialchars($sp['image'] ?? '') ?>" loading="lazy"<?= mmhDimAtributi($sp['image'] ?? '') ?>
                 alt="<?= htmlspecialchars(($sp['name'] ?? '') . ' – ' . $spKat . ' | Make My Home Decor Podgorica') ?>">
            <?php if ($spPopust > 0 && !$spNema): ?>
            <div style="position:absolute;top:10px;right:10px;background:#c0392b;color:#fff;font-weight:800;font-size:13px;line-height:1;padding:6px 11px;border-radius:8px;z-index:4;box-shadow:0 3px 10px rgba(192,57,43,0.45);">&minus;<?= $spPopust ?>%</div>
            <?php endif; ?>
            <?php if ($spNema): ?><div class="oos-tag">Rasprodato</div><?php endif; ?>
          </a>
          <div class="product-body">
            <div class="product-category"><?= htmlspecialchars($spKat) ?></div>
            <h3 class="product-name"><a href="<?= htmlspecialchars($spUrl) ?>" style="color:inherit;"><?= htmlspecialchars($sp['name'] ?? '') ?></a></h3>
            <?php if (!empty($sp['sku'])): ?><div class="product-sku">Šifra: <strong><?= htmlspecialchars($sp['sku']) ?></strong></div><?php endif; ?>
            <p class="product-desc"><?= htmlspecialchars(mb_substr((string)($sp['description'] ?? ''), 0, 150)) ?>…</p>
            <div class="product-footer">
              <div class="product-price">
                <?php if ($spPopust > 0): ?>
                  <span style="text-decoration:line-through;color:#767676;font-size:13px;display:block;"><?= $spCijena ?> €</span>
                  <span style="color:#c0392b;font-weight:700;"><?= number_format($spCijena * (1 - $spPopust / 100), 2, ',', '') ?> €</span>
                  <span style="color:#666e7a;font-size:12px;"> / <?= htmlspecialchars($spJedin) ?></span>
                <?php else: ?>
                  <?= $spCijena ?> € <span>/ <?= htmlspecialchars($spJedin) ?></span>
                <?php endif; ?>
              </div>
              <a href="<?= htmlspecialchars($spUrl) ?>" class="btn-card-detail">Detaljnije <i class="fas fa-arrow-right"></i></a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <div style="text-align:center;margin-top:34px;display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
        <a href="<?= htmlspecialchars($prodCatUrl) ?>" class="btn btn-outline">Sve iz kategorije <?= htmlspecialchars($prodCatName) ?></a>
        <a href="<?= htmlspecialchars($vodic[0]) ?>" class="btn btn-outline"><?= htmlspecialchars($vodic[1]) ?></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
</main>

<footer id="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/" class="logo">
          <img src="images/logo-transparent.png" alt="Make My Home Decor" class="logo-img" width="567" height="567">
          <div class="logo-text">
            <span class="name">Make My Home Decor</span>
            <span class="tagline">Dekorativni Bambus Paneli</span>
          </div>
        </a>
        <p class="footer-desc">Premium zidni paneli i 3D letvice u Podgorici.
        <span style="display:block;margin-top:16px;font-size:13px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:#c9a86c;">&#9654; Zapratite nas</span>
        </p>
        <div class="footer-social">
          <a href="https://www.instagram.com/makemyhome.decor" target="_blank" rel="noopener" class="social-btn" title="Instagram" style="background:#d62976;color:#fff;"><i class="fab fa-instagram"></i></a>
          <a href="https://www.facebook.com/61571886302133" target="_blank" rel="noopener" class="social-btn" title="Facebook" style="background:#1877f2;color:#fff;"><i class="fab fa-facebook-f"></i></a>
          <a href="https://wa.me/38269105222" target="_blank" rel="noopener" class="social-btn" title="WhatsApp" style="background:#25d366;color:#fff;"><i class="fab fa-whatsapp"></i></a>
          <a href="viber://contact?number=%2B38269105222" class="social-btn" title="Viber" style="background:#665cac;color:#fff;"><i class="fab fa-viber"></i></a>
          <a href="https://www.tiktok.com/@makemyhome.me" target="_blank" rel="noopener" class="social-btn" title="TikTok" style="background:#ee1d52;color:#fff;"><i class="fab fa-tiktok"></i></a>
          <a href="mailto:makemyhome.me@gmail.com" class="social-btn" title="Email" style="background:#c9a86c;color:#fff;"><i class="fas fa-envelope"></i></a>
        </div>
      </div>
      <div>
        <h3 class="footer-title">Navigacija</h3>
        <ul class="footer-links footer-links-grid">
          <li><a href="/"><i class="fas fa-chevron-right"></i> Početna</a></li>
          <li><a href="products.html"><i class="fas fa-chevron-right"></i> Svi Proizvodi</a></li>
          <li><a href="faq.html"><i class="fas fa-chevron-right"></i> Česta Pitanja</a></li>
          <li><a href="cjenovnik.html"><i class="fas fa-chevron-right"></i> Cijene</a></li>
          <li><a href="montaza.html"><i class="fas fa-chevron-right"></i> Montaža panela</a></li>
          <li><a href="decor-box.html"><i class="fas fa-chevron-right"></i> Decor Box</a></li>
          <li><a href="inspiracija.html"><i class="fas fa-chevron-right"></i> Inspiracija</a></li>
          <li><a href="about.html"><i class="fas fa-chevron-right"></i> O Nama</a></li>
          <li><a href="contact.html"><i class="fas fa-chevron-right"></i> Kontakt</a></li>
        </ul>
      </div>
      <div>
        <h3 class="footer-title">Kategorije</h3>
        <ul class="footer-links footer-links-grid">
          <li><a href="/kategorija/bambus-drveni"><i class="fas fa-chevron-right"></i> Drveni zidni paneli</a></li>
          <li><a href="/kategorija/bambus-tekstilni"><i class="fas fa-chevron-right"></i> Tekstilni zidni paneli</a></li>
          <li><a href="/kategorija/bambus-mermerni"><i class="fas fa-chevron-right"></i> Mermerni zidni paneli</a></li>
          <li><a href="/kategorija/bambus-metalni"><i class="fas fa-chevron-right"></i> Metalni zidni paneli</a></li>
          <li><a href="/kategorija/bambus-kozni"><i class="fas fa-chevron-right"></i> Kožni zidni paneli</a></li>
          <li><a href="/kategorija/akusticni-paneli"><i class="fas fa-chevron-right"></i> Akustični zidni paneli</a></li>
          <li><a href="/kategorija/3d-letvice"><i class="fas fa-chevron-right"></i> 3D letvice za zid</a></li>
          <li><a href="/kategorija/aluminijum-lajsne"><i class="fas fa-chevron-right"></i> Aluminijum lajsne za panele</a></li>
          <li><a href="/kategorija/classic"><i class="fas fa-chevron-right"></i> Classic zidni paneli</a></li>
          <li><a href="/kategorija/pu-kamen"><i class="fas fa-chevron-right"></i> PU dekorativni kamen</a></li>
          <li><a href="/kategorija/mdf"><i class="fas fa-chevron-right"></i> MDF kanelirani paneli</a></li>
          <li><a href="/kategorija/flex-stone"><i class="fas fa-chevron-right"></i> Flex Stone kameni furnir</a></li>
        </ul>
      </div>
      <div>
        <h3 class="footer-title">Kontakt</h3>
        <ul class="footer-contact-list">
          <li><i class="fas fa-phone"></i><span><a href="tel:+38269105222">069 105 222</a></span></li>
          <li><i class="fas fa-envelope"></i><span><a href="mailto:makemyhome.me@gmail.com">makemyhome.me@gmail.com</a></span></li>
          <li><i class="fas fa-map-marker-alt"></i><span>Vojvode Maša Đurovića 43, City Kvart, Podgorica 81000</span></li>
          <li><i class="fas fa-clock"></i><span>Pon–Pet: 09:00–20:00 | Sub: 10:00–17:00</span></li>
        </ul>
      </div>
    </div>
    <nav class="footer-vodici" aria-label="Vodiči i savjeti">
      <span class="footer-vodici-nas">Vodiči i savjeti</span>
      <a href="paneli-za-kupatilo.html">Paneli za kupatilo</a>
      <a href="tv-zid.html">TV zid od panela</a>
      <a href="spc-ili-laminat.html">SPC pod ili laminat</a>
      <a href="paneli-ili-lamperija.html">Paneli ili lamperija</a>
      <a href="akusticni-paneli-kancelarija.html">Akustični paneli u kancelariji</a>
      <a href="dostava-crna-gora.html">Dostava i montaža u Crnoj Gori</a>
    </nav>
    <div class="footer-bottom">
      <p>&copy; 2026 Make My Home Decor. Sva prava zadržana.</p>
      <p class="footer-pravne"><a href="uslovi.html">Uslovi kupovine</a><a href="reklamacije.html">Reklamacije i povrat</a><a href="privatnost.html">Politika privatnosti</a></p>
      <p>Dizajnirano za <a href="/">makemyhome.me</a></p>
    </div>
  </div>
</footer>

<div id="whatsapp-float">
  <a href="https://wa.me/38269105222?text=Zdravo%2C%20zanima%20me%20vi%C5%A1e%20informacija%20o%20va%C5%A1im%20zidnim%20panelima." target="_blank" rel="noopener" aria-label="Kontaktirajte nas na WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="30" height="30" fill="white"><path d="M16 0C7.164 0 0 7.163 0 16c0 2.822.736 5.469 2.027 7.774L0 32l8.469-2.003A15.93 15.93 0 0 0 16 32c8.836 0 16-7.163 16-16S24.836 0 16 0zm0 29.333a13.27 13.27 0 0 1-6.771-1.856l-.485-.288-5.028 1.188 1.215-4.895-.316-.503A13.247 13.247 0 0 1 2.667 16C2.667 8.636 8.637 2.667 16 2.667S29.333 8.636 29.333 16 23.363 29.333 16 29.333zm7.27-9.907c-.398-.199-2.355-1.162-2.72-1.295-.365-.133-.63-.199-.896.199-.265.398-1.028 1.295-1.26 1.56-.232.265-.464.298-.862.1-.398-.199-1.681-.62-3.203-1.977-1.184-1.056-1.984-2.36-2.216-2.758-.232-.398-.025-.613.174-.811.178-.178.398-.465.597-.697.199-.232.265-.398.398-.663.133-.265.066-.497-.033-.696-.1-.199-.896-2.162-1.228-2.96-.323-.776-.651-.67-.896-.683l-.763-.013c-.265 0-.696.1-.1061.497-.365.398-1.393 1.362-1.393 3.322s1.427 3.854 1.626 4.119c.199.265 2.808 4.286 6.804 6.014.951.41 1.693.655 2.271.838.954.303 1.823.26 2.51.158.765-.114 2.355-.963 2.688-1.893.333-.93.333-1.727.232-1.893-.1-.165-.365-.265-.763-.464z"/></svg></a>
  <span class="wa-tooltip">Pišite nam na WhatsApp</span>
</div>

<button id="scroll-top" aria-label="Nazad na vrh"><i class="fas fa-chevron-up"></i></button>

<script src="js/main-v4.js?v=6"></script>
<script src="js/products.js?v=51"></script>
<script src="js/cart.js?v=3"></script>
<script>
  renderProductDetail();
</script>
<script src="js/analytics-events.js?v=3" defer></script>
</body>
</html>
