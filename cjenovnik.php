<?php
// Cijene se citaju UZIVO iz data/products.json — cjenovnik nikad ne moze zastarjeti.
$cjP = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
$cjNames = [
  'bambus-drveni'=>'Drveni bambus paneli', 'bambus-tekstilni'=>'Tekstilni paneli',
  'bambus-mermerni'=>'Mermerni paneli', 'bambus-metalni'=>'Metalni paneli',
  'bambus-kozni'=>'Kožni paneli', 'classic'=>'Classic paneli',
  '3d-letvice'=>'3D dekorativne letvice', 'akusticni-paneli'=>'Akustični paneli',
  'mdf'=>'MDF kanelirani paneli', 'pu-kamen'=>'PU dekorativni kamen',
  'flex-stone'=>'Flex Stone savitljivi kamen', 'spc-pod'=>'SPC vodootporni pod',
  'aluminijum-lajsne'=>'Aluminijum lajsne',
];
$cjDim = [
  'bambus-drveni'=>'280×122 cm · 3,42 m²', 'bambus-tekstilni'=>'280×122 cm · 3,42 m²',
  'bambus-mermerni'=>'280×122 cm · 3,42 m²', 'bambus-metalni'=>'280×122 cm · 3,42 m²',
  'bambus-kozni'=>'280×122 cm · 3,42 m²', 'classic'=>'280×122 cm · 3,42 m²',
  '3d-letvice'=>'280×16 cm · 0,45 m²', 'akusticni-paneli'=>'60×60 cm · 0,36 m²',
  'mdf'=>'razne dimenzije', 'pu-kamen'=>'set 290×60 cm · 1,74 m²',
  'flex-stone'=>'120×60 cm · 0,72 m²', 'spc-pod'=>'prodaje se po m²',
  'aluminijum-lajsne'=>'dužina 270 cm',
];
$cjArea = [
  'bambus-drveni'=>3.42,'bambus-tekstilni'=>3.42,'bambus-mermerni'=>3.42,'bambus-metalni'=>3.42,
  'bambus-kozni'=>3.42,'classic'=>3.42,'3d-letvice'=>0.45,'akusticni-paneli'=>0.36,
  'pu-kamen'=>1.74,'flex-stone'=>0.72,'spc-pod'=>1.0,
];
// Povrsina po komadu se cita iz istih polja kao na stranici proizvoda, umjesto
// da stoji upisana u tabeli. Rucno upisane vrijednosti su bile zastarjele:
// akusticni paneli su vodjeni kao 60x60cm (0,36 m²) iako ih vecina ima
// 275x60cm (1,65 m²), pa je cjenovnik pokazivao 133 €/m² umjesto 46 €/m².
$cjPovrsina = function (array $x): ?float {
    if (($x['unit'] ?? '') === 'm²') return 1.0;
    foreach (($x['features'] ?? []) as $f) {
        if (preg_match('/\(\s*([\d]+[.,]\s*[\d]+)\s*m²/u', $f, $m)) {
            $v = (float) str_replace([' ', ','], ['', '.'], $m[1]);
            if ($v > 0.05) return $v;
        }
        if (preg_match('/(?:Dimenzije|Dimenzije seta)[^:]*:\s*([\d]+(?:[.,][\d]+)?)\s*[×x]\s*([\d]+(?:[.,][\d]+)?)\s*cm/u', $f, $m)) {
            $v = ((float) str_replace(',', '.', $m[1]) / 100) * ((float) str_replace(',', '.', $m[2]) / 100);
            if ($v > 0.05) return $v;
        }
    }
    return null;
};
$cjDimTekst = function (array $x): ?string {
    foreach (($x['features'] ?? []) as $f) {
        if (preg_match('/(?:Dimenzije|Dimenzije seta)[^:]*:\s*([\d]+(?:[.,][\d]+)?)\s*[×x]\s*([\d]+(?:[.,][\d]+)?)\s*cm/u', $f, $m))
            return $m[1] . '×' . $m[2] . ' cm';
        if (preg_match('/Dužina:\s*([\d]+(?:[.,][\d]+)?)\s*(m|cm)\b/u', $f, $m))
            return 'dužina ' . $m[1] . ' ' . $m[2];
    }
    return null;
};
$cjRows = [];
foreach ($cjP as $x) {
    $c = $x['category'] ?? '';
    if (!isset($cjNames[$c])) continue;
    $pr = (float)($x['price'] ?? 0);
    $dc = (int)($x['discount'] ?? 0);
    $sp = $dc > 0 ? round($pr * (1 - $dc / 100), 2) : $pr;
    $pov = $cjPovrsina($x);
    $cjRows[$c][] = [
        'puna'=>$pr, 'akcija'=>$sp, 'popust'=>$dc, 'jed'=>$x['unit'] ?? 'kom',
        'pov'=>$pov, 'dim'=>$cjDimTekst($x),
        'm2'=>($pov && $pov > 0.05 && ($x['unit'] ?? '') !== 'm²') ? $sp / round($pov, 2) : (($x['unit'] ?? '') === 'm²' ? $sp : null),
    ];
}
$cjOrder = ['bambus-drveni','bambus-tekstilni','bambus-mermerni','bambus-metalni','bambus-kozni','classic','3d-letvice','akusticni-paneli','mdf','pu-kamen','flex-stone','spc-pod','aluminijum-lajsne'];
$fmt = fn($v) => number_format($v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="sr-ME">
<head><meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Cijene zidnih panela, 3D letvica, PU kamena i SPC poda u Crnoj Gori — 117 modela na jednom mjestu i kalkulator koliko komada vam treba za vaš zid.">
  <meta name="keywords" content="cijena zidnih panela, cjenovnik zidni paneli, koliko koštaju paneli, cijena 3d letvica, cijena akustičnih panela, cijena spc pod, cijena pu kamen, Podgorica, Crna Gora">
  <meta property="og:title" content="Cijene Zidnih Panela u Crnoj Gori | Make My Home Decor">
  <meta property="og:description" content="Cijene zidnih panela, 3D letvica, PU kamena i SPC poda u Crnoj Gori — 117 modela na jednom mjestu i kalkulator koliko komada vam treba za vaš zid.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://makemyhome.me/cjenovnik.html">
  <meta property="og:image" content="https://makemyhome.me/images/og-dijeljenje.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:locale" content="sr_ME">
  <meta property="og:site_name" content="Make My Home Decor">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Cijene Zidnih Panela u Crnoj Gori | Make My Home Decor">
  <meta name="twitter:description" content="Cijene zidnih panela, 3D letvica, PU kamena i SPC poda u Crnoj Gori — 117 modela na jednom mjestu i kalkulator koliko komada vam treba za vaš zid.">
  <meta name="twitter:image" content="https://makemyhome.me/images/og-dijeljenje.jpg">
  <link rel="canonical" href="https://makemyhome.me/cjenovnik.html">
  <title>Cijene Zidnih Panela u Crnoj Gori | Make My Home Decor</title>
  <link rel="icon" type="image/x-icon" href="images/favicon.ico">
  <link rel="icon" type="image/png" href="images/favicon-512.png">
  <link rel="apple-touch-icon" sizes="512x512" href="images/favicon-512.png">
  <meta name="theme-color" content="#1a1a1a">
  <link rel="preload" href="fa/webfonts/fa-solid-900.woff2?v=aebfb638" as="font" type="font/woff2" crossorigin>
  <!-- Ikone se ucitavaju bez blokiranja: media="print" znaci da pregledac fajl
       preuzme ali ga ne ceka da bi iscrtao stranicu, a onload ga odmah vrati u
       upotrebu. Ikone su ukras — LCP element je tekst i ne smije da ceka na njih.
       Pravilo ispod cuva sirinu 1em za svaku ikonu dok CSS ne stigne, da se
       dugmad ne pomjere kad se ikone pojave (CLS ostaje 0). -->
  <style>i[class*="fa-"]{display:inline-block;width:1em;height:1em}</style>
  <link rel="stylesheet" href="fa/css/mmh-ikone.css?v=89e76a80" media="print" onload="this.media='all';this.onload=null">
  <noscript><link rel="stylesheet" href="fa/css/mmh-ikone.css?v=89e76a80"></noscript>
  <link rel="preload" href="fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="css/style-v5.css?v=05bd253b">
  <style>
    @media(min-width:769px){.nav-menu{gap:0!important;flex-wrap:nowrap!important;}.nav-link{font-size:12px!important;padding:8px 5px!important;white-space:nowrap!important;}.logo{flex-shrink:0!important;}.logo-text .name,.logo-text .tagline{white-space:nowrap!important;}#desk-search-wrap{flex-shrink:0!important;margin-right:4px!important;}}
    @media(max-width:768px){#desk-search-wrap{display:none!important;}}

    /* ===== FAQ ===== */
    .faq-wrap{padding:66px 0 80px;}
    .faq-group{margin-bottom:44px;}
    .faq-group-title{display:flex;align-items:center;gap:12px;font-family:var(--font-heading);font-size:22px;color:var(--dark);margin:0 0 20px;padding-bottom:12px;border-bottom:2px solid rgba(201,168,108,0.3);}
    .faq-group-title i{color:var(--primary);font-size:18px;}
    .faq-item{background:#fff;border:1px solid rgba(0,0,0,0.07);border-radius:14px;padding:22px 24px;margin-bottom:14px;box-shadow:0 2px 10px rgba(0,0,0,0.04);}
    .faq-item h3{font-size:17px;color:var(--dark);margin:0 0 10px;line-height:1.4;display:flex;gap:10px;align-items:flex-start;}
    .faq-item h3 span.q{color:#795f32;font-weight:800;flex-shrink:0;}
    .faq-item p{font-size:15px;color:var(--dark-2);line-height:1.8;margin:0;}
    .faq-item p + p{margin-top:10px;}
    .faq-intro{max-width:760px;margin:0 auto 44px;text-align:center;font-size:16px;color:var(--gray);line-height:1.8;}
    .faq-cta{background:var(--dark);border-radius:20px;padding:44px 32px;text-align:center;margin-top:16px;}
    .faq-cta h2{color:#fff;font-size:26px;margin-bottom:10px;}
    .faq-cta p{color:rgba(255,255,255,0.7);font-size:15px;margin-bottom:26px;}
    .faq-cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
    @media(max-width:768px){
      .faq-group-title{font-size:19px;}
      .faq-item{padding:18px 18px;}
      .faq-item h3{font-size:16px;}
      .faq-cta-btns{flex-direction:column;align-items:center;}
      .faq-cta-btns .btn{width:100%;max-width:320px;justify-content:center;}
    }
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
  <style>
    .page-hero .section-subtitle{margin-left:auto!important;margin-right:auto!important;text-align:center!important;}
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

  <script type="application/ld+json">
  {
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Početna", "item": "https://makemyhome.me/" },
    { "@type": "ListItem", "position": 2, "name": "Cijene", "item": "https://makemyhome.me/cjenovnik.html" }
  ]
}
  </script>
  <script type="application/ld+json">
  {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Koliko košta zidni panel po m²?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Bambus panel od 3,42 m² košta oko 69,59 € sa popustom, što je oko 20 € po m². 3D letvica od 0,45 m² košta 15,99 €, oko 36 € po m² zbog uskog profila. SPC pod se prodaje po m² i košta 17,49 €."
      }
    },
    {
      "@type": "Question",
      "name": "Koliko košta dostava?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Dostava kurirskom službom širom Crne Gore je okvirno 20 € i zavisi od količine i dimenzija paketa. Za veće narudžbe i partnere iz Decor Box programa dogovaramo posebne uslove."
      }
    },
    {
      "@type": "Question",
      "name": "Da li su cijene sa PDV-om?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Da, sve cijene na sajtu su konačne maloprodajne cijene. Za pravna lica izdajemo predračun i fakturu."
      }
    },
    {
      "@type": "Question",
      "name": "Kako se plaća?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Gotovinom pri preuzimanju od kurira, u showroomu ili virmanski za firme uz fakturu. Nema avansa."
      }
    },
    {
      "@type": "Question",
      "name": "Da li ima popusta za veće količine?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Da. Za veće projekte i za arhitekte, izvođače i prodavnice imamo Decor Box partnerski program sa posebnim cijenama."
      }
    },
    {
      "@type": "Question",
      "name": "Zašto su neke kategorije jeftinije po m²?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Veliki paneli 280×122 cm pokrivaju mnogo površine odjednom pa im je cijena po m² niža. Uski profili poput 3D letvica i mali formati poput akustičnih ploča 60×60 cm imaju višu cijenu po m² jer traže više komada za istu površinu."
      }
    }
  ]
}
  </script>
<style id="info-page">
  .info-wrap { padding: 46px 0 70px; background: #fff; }
  .info-wrap .container { max-width: 900px; }
  .info-lead { font-size: 17px; color: #5a6672; line-height: 1.8; margin-bottom: 30px; }
  .info-wrap h2 { font-size: 25px; color: #1a1a1a; margin: 40px 0 14px; line-height: 1.3; }
  .info-wrap h3 { font-size: 18px; color: #1a1a1a; margin: 26px 0 10px; }
  .info-wrap p { color: #5a6672; line-height: 1.8; margin-bottom: 14px; }
  .info-wrap ul, .info-wrap ol { color: #5a6672; line-height: 1.8; padding-left: 22px; margin-bottom: 16px; }
  .info-wrap li { margin-bottom: 8px; }
  .info-wrap a { color: #c9a86c; font-weight: 600; }
  .step { display: flex; gap: 18px; align-items: flex-start; background: #faf7f2;
          border: 1px solid rgba(201,168,108,0.28); border-radius: 14px; padding: 20px 22px; margin-bottom: 14px; }
  .step-n { flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background: #c9a86c; color: #1a1a1a;
            font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 16px; }
  .step h3 { margin: 4px 0 6px; font-size: 17px; }
  .step p { margin: 0; font-size: 14.5px; }
  .note { background: #fff8e6; border-left: 4px solid #e0a800; border-radius: 8px; padding: 15px 18px; margin: 18px 0; }
  .note p { margin: 0; font-size: 14.5px; color: #6b5620; }
  .info-table { width: 100%; border-collapse: collapse; margin: 18px 0 8px; font-size: 14.5px; }
  .info-table th { background: #1a1a1a; color: #fff; text-align: left; padding: 12px 14px; font-weight: 700; }
  .info-table td { padding: 12px 14px; border-bottom: 1px solid rgba(0,0,0,0.07); color: #5a6672; }
  .info-table tr:hover td { background: #faf7f2; }
  .info-table a { white-space: nowrap; }
  .tbl-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  /* Na telefonu tabela od 5 kolona se ne moze procitati — svaki red postaje kartica. */
  @media (max-width: 720px) {
    .tbl-scroll { overflow-x: visible; }
    .info-table, .info-table tbody, .info-table tr, .info-table td { display: block; width: 100%; }
    .info-table thead { display: none; }
    .info-table tr {
      background: #fff; border: 1px solid rgba(0,0,0,.09); border-radius: 12px;
      padding: 16px 18px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    .info-table td { border: none; padding: 0; }
    .info-table td[data-l] {
      display: flex; justify-content: space-between; align-items: baseline; gap: 14px;
      padding: 9px 0; border-top: 1px solid rgba(0,0,0,.06);
    }
    .info-table td[data-l]::before {
      content: attr(data-l); color: #8a8a8a; font-size: 13px; flex-shrink: 0;
    }
    .info-table td[data-l] > * { text-align: right; }
    /* Cijena zna imati raspon + precrtanu cijenu + bedz popusta — u redu se lomi,
       pa joj dajemo svoj red ispod oznake. */
    .info-table td[data-l="Cijena"] {
      display: block; padding: 10px 0;
    }
    .info-table td[data-l="Cijena"]::before {
      display: block; margin-bottom: 5px;
    }
    .info-table td[data-l="Cijena"] > * { text-align: left; }
    .info-table td[data-l="Cijena"] strong { font-size: 19px !important; }
    .info-table td[data-l="Cijena"] span { vertical-align: middle; }
    .info-table td.cj-naziv { font-size: 17px; color: #1a1a1a; padding-bottom: 4px; }
    .info-table td.cj-naziv strong { font-size: 17px; }
    .info-table td.cj-link { padding-top: 12px; margin-top: 10px; border-top: 1px solid rgba(0,0,0,.06); }
    .info-table td.cj-link a {
      display: block; text-align: center; background: #1a1a1a; color: #fff;
      padding: 11px; border-radius: 9px; font-weight: 600; font-size: 14px; text-decoration: none;
    }
    .info-table tr:hover td { background: transparent; }
  }
  .calc { background: #faf7f2; border: 1px solid rgba(201,168,108,0.35); border-radius: 16px; padding: 24px; margin: 22px 0; }
  .calc-row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
  .calc-f { flex: 1; min-width: 140px; }
  .calc-f label { display: block; font-size: 13px; font-weight: 700; color: #1a1a1a; margin-bottom: 6px; }
  .calc-f input, .calc-f select {
    width: 100%; box-sizing: border-box; padding: 11px 13px; border: 1.5px solid rgba(0,0,0,0.14);
    border-radius: 10px; font-size: 15px; font-family: inherit; background: #fff; color: #1a1a1a;
  }
  .calc-f input:focus, .calc-f select:focus { outline: none; border-color: #c9a86c; }
  .calc-out { background: #fff; border-radius: 12px; padding: 18px 20px; margin-top: 6px; border: 1px solid rgba(0,0,0,0.08); }
  .calc-out .big { font-size: 27px; font-weight: 800; color: #1a1a1a; }
  .calc-out .sub { font-size: 14px; color: #5a6672; line-height: 1.7; margin-top: 6px; }
  .info-cta { background: #faf7f2; border: 1px solid rgba(201,168,108,0.35); border-radius: 16px;
              padding: 30px 26px; text-align: center; margin-top: 44px; }
  .info-cta h2 { margin: 0 0 10px; font-size: 22px; color: #1a1a1a; line-height: 1.3; }
  .info-cta p { margin: 0 auto 22px; color: #5a6672; max-width: 46ch; line-height: 1.7; }
  .info-cta-dugmad { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  .info-cta .btn { margin: 0; }
  @media (max-width: 600px) {
    .info-cta { padding: 24px 18px; }
    .info-cta-dugmad { flex-direction: column; }
    .info-cta .btn { width: 100%; justify-content: center; }
  }
  @media (max-width: 768px) {
    .info-wrap h2 { font-size: 21px; }
    .step { padding: 16px; gap: 14px; }
    .calc { padding: 18px; }
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

<section class="page-hero">
  <div class="container">
    <div class="page-hero-content">
      <div class="breadcrumb">
        <a href="/">Početna</a>
        <i class="fas fa-chevron-right"></i>
        <span>Cijene</span>
      </div>
      <h1 class="section-title">Cijene Zidnih Panela u Crnoj Gori</h1>
      <p class="section-subtitle" style="margin-left:auto;margin-right:auto;text-align:center;">
        Aktuelne cijene po kategoriji, cijena dostave i kalkulator koliko vam komada treba
      </p>
    </div>
  </div>
</section>

<section class="info-wrap">
  <div class="container">
    <p class="info-lead">
      Ovdje su cijene svih kategorija koje držimo na stanju. Tabela se povlači direktno iz naše baze
      proizvoda, pa ne može zastarjeti — ono što ovdje vidite je ono što plaćate danas.
      Sve cijene su konačne maloprodajne, u eurima.
    </p>

    <h2>Cjenovnik po kategorijama</h2>
    <div class="tbl-scroll">
    <table class="info-table">
      <thead>
        <tr>
          <th>Kategorija</th>
          <th>Dimenzija</th>
          <th>Cijena</th>
          <th>Po m²</th>
          <!-- Zaglavlje zadnje kolone bilo je prazno, pa celije u njoj nisu
               imale svoje zaglavlje. Citac ekrana tada ne zna sta je ta kolona,
               a Lighthouse to prijavljuje (td-has-header). Naslov postoji ali
               se ne vidi — u zaglavlju tabele nema mjesta za jos jednu rijec. -->
          <th><span class="sr-only">Veza ka kategoriji</span></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($cjOrder as $c):
  if (empty($cjRows[$c])) continue;
  $lo = min(array_column($cjRows[$c], 'akcija'));
  $hi = max(array_column($cjRows[$c], 'akcija'));
  $loPuna = min(array_column($cjRows[$c], 'puna'));
  $maxPop = max(array_column($cjRows[$c], 'popust'));
  $minPop = min(array_column($cjRows[$c], 'popust'));
  // Ranije je stajao samo najveci popust, pa je za 12 od 13 mermernih pisalo
  // -30% iako imaju -20%. Kad se popusti razlikuju, pise se raspon.
  $popTekst = $minPop === $maxPop ? '−' . $maxPop . '%' : '−' . $minPop . '% do −' . $maxPop . '%';
  $jed = $cjRows[$c][0]['jed'];
  // Dimenzija: ako svi modeli u kategoriji imaju istu, pise se tacno;
  // ako se razlikuju, pise "razne dimenzije" i daje se raspon cijene po m².
  $dims = array_values(array_unique(array_filter(array_column($cjRows[$c], 'dim'))));
  $povs = array_values(array_filter(array_column($cjRows[$c], 'pov')));
  $m2s  = array_values(array_filter(array_column($cjRows[$c], 'm2')));
  if (count($dims) === 1 && count($povs) === count($cjRows[$c])) {
      $dimTekst = $dims[0] . ' · ' . $fmt(round($povs[0], 2)) . ' m²';
  } elseif ($jed === 'm²') {
      $dimTekst = 'prodaje se po m²';
  } elseif (count($dims) === 1) {
      $dimTekst = $dims[0];
  } elseif (count($dims) > 1) {
      $dimTekst = 'razne dimenzije';
  } else {
      $dimTekst = '—';
  }
  $m2Tekst = '—';
  if ($m2s) {
      $m2lo = min($m2s); $m2hi = max($m2s);
      $m2Tekst = abs($m2hi - $m2lo) < 0.01
          ? '~' . $fmt($m2lo) . ' €'
          : $fmt($m2lo) . ' – ' . $fmt($m2hi) . ' €';
  }
?>
        <tr>
          <td class="cj-naziv"><strong><?= htmlspecialchars($cjNames[$c]) ?></strong><br>
              <span style="font-size:12.5px;color:#666e7a;"><?= count($cjRows[$c]) ?> dezena</span></td>
          <td data-l="Dimenzija"><?= htmlspecialchars($dimTekst) ?></td>
          <td data-l="Cijena">
            <?php if ($maxPop > 0): ?>
              <strong style="color:#795f32;font-size:15px;"><?= $fmt($lo) ?><?= $hi > $lo ? ' – ' . $fmt($hi) : '' ?> €</strong>
              <span style="display:block;font-size:12px;color:#767676;text-decoration:line-through;">od <?= $fmt($loPuna) ?> €</span>
              <span style="display:inline-block;background:#c0392b;color:#fff;font-size:11px;font-weight:700;padding:2px 7px;border-radius:12px;margin-top:3px;white-space:nowrap;"><?= $popTekst ?></span>
            <?php else: ?>
              <strong style="font-size:15px;"><?= $fmt($lo) ?><?= $hi > $lo ? ' – ' . $fmt($hi) : '' ?> €</strong>
            <?php endif; ?>
            <span style="display:block;font-size:12px;color:#666e7a;">/ <?= htmlspecialchars($jed) ?></span>
          </td>
          <td data-l="Po m&sup2;"><?= htmlspecialchars($m2Tekst) ?></td>
          <td class="cj-link"><a href="/kategorija/<?= htmlspecialchars($c, ENT_QUOTES) ?>">Pogledaj proizvode &rsaquo;</a></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p style="font-size:13px;color:#666e7a;">
      Cijene su ažurirane <?= date('d.m.Y.') ?> i mijenjaju se automatski kada promijenimo cijenu u ponudi.
      Kolona „po m²" je informativna — računa se iz najniže cijene u kategoriji.
    </p>

    <h2 id="kalkulator">Kalkulator – koliko panela vam treba</h2>
    <p>Unesite mjere zida i odaberite šta vas zanima. Račun uključuje 10% rezerve na sječenje.</p>
    <div class="calc">
      <div class="calc-row">
        <div class="calc-f">
          <label for="cw">Širina zida (m)</label>
          <input type="number" id="cw" value="4" min="0.1" step="0.1" inputmode="decimal">
        </div>
        <div class="calc-f">
          <label for="ch">Visina zida (m)</label>
          <input type="number" id="ch" value="2.6" min="0.1" step="0.1" inputmode="decimal">
        </div>
        <div class="calc-f">
          <label for="ck">Šta postavljate</label>
          <select id="ck">
<?php foreach ($cjOrder as $c):
  if (empty($cjRows[$c]) || empty($cjArea[$c])) continue;
  $lo = min(array_column($cjRows[$c], 'akcija'));
?>
            <option value="<?= $cjArea[$c] ?>|<?= $lo ?>|<?= htmlspecialchars($cjNames[$c], ENT_QUOTES) ?>|<?= htmlspecialchars($c, ENT_QUOTES) ?>"><?= htmlspecialchars($cjNames[$c]) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="calc-out" id="cout">
        <div class="big">—</div>
        <div class="sub">Unesite mjere zida</div>
      </div>
    </div>

    <h2>Šta utiče na konačnu cijenu</h2>
    <ul>
      <li><strong>Površina zida.</strong> Veliki paneli 280×122 cm pokrivaju 3,42 m² odjednom, pa je cijena po m² najniža kod njih.</li>
      <li><strong>Otpad na sječenju.</strong> Računajte 5–10% više od tačne kvadrature, zavisno od toga koliko uglova i otvora ima zid.</li>
      <li><strong>Lajsne.</strong> Aluminijum lajsne za ivice i uglove su odvojena stavka, obično 20–40 € po zidu.</li>
      <li><strong>Silikon.</strong> Jedna tuba montažnog silikona pokrije 2–3 panela, oko 5 € po tubi.</li>
      <li><strong>Dostava.</strong> Okvirno 20 € kurirskom službom širom Crne Gore, zavisno od količine.</li>
    </ul>

    <h2>Primjer: koliko košta zid od 10 m²</h2>
    <p>
      Zid širine 4 m i visine 2,6 m ima 10,4 m². Sa 10% rezerve to je 11,4 m², odnosno
      <strong>4 bambus panela</strong>. Uz aktuelnu cijenu to je oko <strong>278 €</strong> za panele,
      plus oko 35 € za lajsne i silikon, plus 20 € dostava — <strong>ukupno oko 333 €</strong>.
      Isti zid u pločicama traži majstora, ljepilo, fugu i tri do četiri dana rada.
    </p>
    <p>
      Ako niste sigurni šta vam odgovara, dođite u showroom u Podgorici sa mjerama zida ili nam ih pošaljite —
      izračunamo količinu i cijenu istog dana. Pogledajte i <a href="montaza.html">uputstvo za montažu</a>
      da vidite koliko je posao jednostavan.
    </p>

    <h2>Česta pitanja o cijenama</h2>
    <h3>Koliko košta zidni panel po m²?</h3>
    <p>Bambus panel od 3,42 m² košta oko 69,59 € sa popustom, što je oko 20 € po m². 3D letvica od 0,45 m² košta 15,99 €, oko 36 € po m² zbog uskog profila. SPC pod se prodaje po m² i košta 17,49 €.</p>
    <h3>Koliko košta dostava?</h3>
    <p>Dostava kurirskom službom širom Crne Gore je okvirno 20 € i zavisi od količine i dimenzija paketa. Za veće narudžbe i partnere iz Decor Box programa dogovaramo posebne uslove.</p>
    <h3>Da li su cijene sa PDV-om?</h3>
    <p>Da, sve cijene na sajtu su konačne maloprodajne cijene. Za pravna lica izdajemo predračun i fakturu.</p>
    <h3>Kako se plaća?</h3>
    <p>Gotovinom pri preuzimanju od kurira, u showroomu ili virmanski za firme uz fakturu. Nema avansa.</p>
    <h3>Da li ima popusta za veće količine?</h3>
    <p>Da. Za veće projekte i za arhitekte, izvođače i prodavnice imamo Decor Box partnerski program sa posebnim cijenama.</p>
    <h3>Zašto su neke kategorije jeftinije po m²?</h3>
    <p>Veliki paneli 280×122 cm pokrivaju mnogo površine odjednom pa im je cijena po m² niža. Uski profili poput 3D letvica i mali formati poput akustičnih ploča 60×60 cm imaju višu cijenu po m² jer traže više komada za istu površinu.</p>

    <div class="info-cta">
      <h2>Treba vam tačan izračun?</h2>
      <p>Pošaljite mjere zida — dobićete ponudu istog dana, bez obaveze kupovine.</p>
      <div class="info-cta-dugmad">
      <a href="tel:+38269105222" class="btn btn-gold btn-lg"><i class="fas fa-phone"></i> 069 105 222</a>
        <a href="contact.html" class="btn btn-outline btn-lg"><i class="fas fa-envelope"></i> Pošalji upit</a>
        <a href="decor-box.html" class="btn btn-outline btn-lg"><i class="fas fa-handshake"></i> Partnerske cijene</a>
    </div>
  
    </div>
  </div>
</section>

<script>
(function () {
  var w = document.getElementById('cw'), h = document.getElementById('ch'),
      k = document.getElementById('ck'), out = document.getElementById('cout');
  if (!w || !h || !k || !out) return;
  function calc() {
    var sw = parseFloat(w.value), sh = parseFloat(h.value);
    var parts = k.value.split('|');
    var area = parseFloat(parts[0]), cijena = parseFloat(parts[1]), naziv = parts[2], slug = parts[3];
    if (!(sw > 0) || !(sh > 0)) {
      out.innerHTML = '<div class="big">—</div><div class="sub">Unesite mjere zida</div>';
      return;
    }
    var m2 = sw * sh, sRezervom = m2 * 1.1;
    var kom = Math.ceil(sRezervom / area);
    var ukupno = kom * cijena;
    var jed = slug === 'spc-pod' ? 'm²' : 'kom';
    out.innerHTML =
      '<div class="big">' + kom + ' ' + jed + ' &middot; ' + ukupno.toFixed(2).replace('.', ',') + ' €</div>' +
      '<div class="sub">Zid ' + m2.toFixed(2).replace('.', ',') + ' m² (' + sRezervom.toFixed(2).replace('.', ',') +
      ' m² sa 10% rezerve) &middot; ' + naziv +
      '<br>Bez lajsni, silikona i dostave (okvirno 20 €). ' +
      '<a href="/kategorija/' + slug + '">Pogledaj ' + naziv.toLowerCase() + ' &rsaquo;</a></div>';
    if (typeof window.mmhTrack === 'function') window.mmhTrack('kalkulator_koristen', { kategorija: slug, kom: kom });
  }
  [w, h, k].forEach(function (el) { el.addEventListener('input', calc); el.addEventListener('change', calc); });
  calc();
})();
</script>
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
        <p class="footer-desc">Premium zidni paneli i 3D letvice u Podgorici. Transformišite Vaš prostor.
        <span style="display:block;margin-top:16px;font-size:13px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:#c9a86c;">&#9654; Zapratite nas</span>
        </p>
        <div class="footer-social">
          <a href="https://www.instagram.com/makemyhome.decor" target="_blank" rel="noopener" class="social-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="https://www.facebook.com/61571886302133" target="_blank" rel="noopener" class="social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="https://wa.me/38269105222" target="_blank" rel="noopener" class="social-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
          <a href="viber://contact?number=%2B38269105222" class="social-btn" title="Viber"><i class="fab fa-viber"></i></a>
          <a href="https://www.tiktok.com/@makemyhome.me" target="_blank" rel="noopener" class="social-btn" title="TikTok"><i class="fab fa-tiktok"></i></a>
          <a href="mailto:makemyhome.me@gmail.com" class="social-btn" title="Email"><i class="fas fa-envelope"></i></a>
        </div>
        <ul class="footer-contact-list footer-kontakt-brend">
          <li><i class="fas fa-map-marker-alt"></i><span>Vojvode Maša Đurovića 43, City Kvart, Podgorica</span></li>
          <li><i class="fas fa-phone"></i><span><a href="tel:+38269105222">069 105 222</a></span></li>
          <li><i class="fas fa-envelope"></i><span><a href="mailto:makemyhome.me@gmail.com">makemyhome.me@gmail.com</a></span></li>
          <li><i class="fas fa-clock"></i><span>Pon–Pet: 09:00–20:00 | Sub: 10:00–17:00</span></li>
        </ul>
      </div>
      <div>
        <h3 class="footer-title">Navigacija</h3>
        <ul class="footer-links footer-links-grid">
          <li><a href="/"><i class="fas fa-chevron-right"></i> Početna</a></li>
          <li><a href="products.html"><i class="fas fa-chevron-right"></i> Svi Proizvodi</a></li>
          <li><a href="decor-box.html"><i class="fas fa-chevron-right"></i> Decor Box</a></li>
          <li><a href="inspiracija.html"><i class="fas fa-chevron-right"></i> Inspiracija</a></li>
          <li><a href="faq.html"><i class="fas fa-chevron-right"></i> Česta Pitanja</a></li>
          <li><a href="cjenovnik.html"><i class="fas fa-chevron-right"></i> Cijene</a></li>
          <li><a href="montaza.html"><i class="fas fa-chevron-right"></i> Montaža panela</a></li>
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
          <li><a href="/kategorija/pu-kamen"><i class="fas fa-chevron-right"></i> PU dekorativni kamen</a></li>
          <li><a href="/kategorija/mdf"><i class="fas fa-chevron-right"></i> MDF kanelirani paneli</a></li>
          <li><a href="/kategorija/flex-stone"><i class="fas fa-chevron-right"></i> Flex Stone kameni furnir</a></li>
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
  <a href="https://wa.me/38269105222?text=Zdravo%2C%20imam%20pitanje%20o%20zidnim%20panelima." target="_blank" rel="noopener" aria-label="Kontaktirajte nas na WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="30" height="30" fill="white"><path d="M16 0C7.164 0 0 7.163 0 16c0 2.822.736 5.469 2.027 7.774L0 32l8.469-2.003A15.93 15.93 0 0 0 16 32c8.836 0 16-7.163 16-16S24.836 0 16 0zm0 29.333a13.27 13.27 0 0 1-6.771-1.856l-.485-.288-5.028 1.188 1.215-4.895-.316-.503A13.247 13.247 0 0 1 2.667 16C2.667 8.636 8.637 2.667 16 2.667S29.333 8.636 29.333 16 23.363 29.333 16 29.333zm7.27-9.907c-.398-.199-2.355-1.162-2.72-1.295-.365-.133-.63-.199-.896.199-.265.398-1.028 1.295-1.26 1.56-.232.265-.464.298-.862.1-.398-.199-1.681-.62-3.203-1.977-1.184-1.056-1.984-2.36-2.216-2.758-.232-.398-.025-.613.174-.811.178-.178.398-.465.597-.697.199-.232.265-.398.398-.663.133-.265.066-.497-.033-.696-.1-.199-.896-2.162-1.228-2.96-.323-.776-.651-.67-.896-.683l-.763-.013c-.265 0-.696.1-.1061.497-.365.398-1.393 1.362-1.393 3.322s1.427 3.854 1.626 4.119c.199.265 2.808 4.286 6.804 6.014.951.41 1.693.655 2.271.838.954.303 1.823.26 2.51.158.765-.114 2.355-.963 2.688-1.893.333-.93.333-1.727.232-1.893-.1-.165-.365-.265-.763-.464z"/></svg></a>
  <span class="wa-tooltip">Pišite nam na WhatsApp</span>
</div>

<button id="scroll-top" aria-label="Nazad na vrh"><i class="fas fa-chevron-up"></i></button>
<script src="js/main-v4.js?v=d86f5c22"></script>
<script src="js/cart.js?v=2906a9ed"></script>
<script src="js/analytics-events.js?v=6b69b9c0" defer></script>
</body>
</html>
