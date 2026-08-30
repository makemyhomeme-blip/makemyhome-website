<?php
require_once __DIR__ . '/php/slug.php';
require_once __DIR__ . '/php/dimenzije.php';
// Sve fotografije prostora koje se dodaju kroz admin (polje "gallery"), na jednom mjestu.
// Nista se ne upisuje rucno — cim se u adminu doda slika nekom proizvodu, pojavi se ovdje.
$insP = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
if (isset($insP['products'])) $insP = $insP['products'];

$insNames = [
  'bambus-drveni'=>'Drveni Paneli','bambus-tekstilni'=>'Tekstilni Paneli','bambus-mermerni'=>'Mermerni Paneli',
  'bambus-metalni'=>'Metalni Paneli','bambus-kozni'=>'Kožni Paneli','bambus-paneli'=>'Bambus Paneli',
  '3d-letvice'=>'3D Letvice','akusticni-paneli'=>'Akustični Paneli','aluminijum-lajsne'=>'Alu Lajsne',
  'spc-pod'=>'SPC Pod','pu-kamen'=>'PU Kamen','classic'=>'Classic Paneli','mdf'=>'MDF Paneli','flex-stone'=>'Flex Stone',
];

// Vrijeme uploada je u imenu fajla (gallery-<id>-<timestamp>-<rand>.jpg) — koristimo ga
// da najnovije fotografije uvijek budu pri vrhu, bez ijedne rucne izmjene.
$insVrijeme = function ($put) {
    return preg_match('/gallery-\d+-(\d{9,11})-/', $put, $m) ? (int) $m[1] : 0;
};

// Slike grupisane po proizvodu, unutar proizvoda najnovija prva
$insPo = [];
foreach ($insP as $p) {
    if (empty($p['gallery'])) continue;
    $g = array_values($p['gallery']);
    usort($g, fn($a, $b) => $insVrijeme($b) <=> $insVrijeme($a));
    $insPo[$p['id']] = ['p' => $p, 'g' => $g, 't' => $insVrijeme($g[0])];
}

// Proizvodi poredani po tome ko je zadnji dobio novu fotografiju,
// pa se kategorije smjenjuju da se ne naredaju dvije iste zaredom.
uasort($insPo, fn($a, $b) => $b['t'] <=> $a['t']);
$poKat = [];
foreach ($insPo as $id => $v) $poKat[$v['p']['category'] ?? ''][] = $id;
uasort($poKat, fn($a, $b) => $insPo[$b[0]]['t'] <=> $insPo[$a[0]]['t']);
$redom = [];
while ($poKat) {
    foreach (array_keys($poKat) as $k) {
        $redom[] = array_shift($poKat[$k]);
        if (!$poKat[$k]) unset($poKat[$k]);
    }
}

// Naizmjenicno po jedna slika od svakog proizvoda — nikad dvije iste sobe zaredom
$insSlike = [];
$krug = 0;
while (true) {
    $dodato = false;
    foreach ($redom as $id) {
        if (isset($insPo[$id]['g'][$krug])) {
            $put = $insPo[$id]['g'][$krug];
            $insSlike[] = ['src' => $put, 'p' => $insPo[$id]['p'], 't' => $insVrijeme($put)];
            $dodato = true;
        }
    }
    if (!$dodato) break;
    $krug++;
}

// Prolaz koji razmakne dvije slike istog proizvoda ako su ipak zavrsile jedna do druge
$n = count($insSlike);
$idAt = function ($k) use (&$insSlike, $n) {
    return ($k < 0 || $k >= $n) ? null : $insSlike[$k]['p']['id'];
};
for ($i = 1; $i < $n; $i++) {
    if ($idAt($i) !== $idAt($i - 1)) continue;
    for ($j = 0; $j < $n; $j++) {
        if ($j === $i) continue;
        $a = $idAt($i); $b = $idAt($j);
        if ($b === $a) continue;
        if ($b === $idAt($i - 1) || $b === $idAt($i + 1)) continue;
        if ($a === $idAt($j - 1) || $a === $idAt($j + 1)) continue;
        $t = $insSlike[$i]; $insSlike[$i] = $insSlike[$j]; $insSlike[$j] = $t;
        break;
    }
}

// Oznaku "novo" nosi sve iz posljednje tri sedmice. Racuna se od najnovije fotografije,
// ne od danasnjeg datuma — da oznaka ne nestane ako se par sedmica nista ne doda.
$insNajnovija = 0;
foreach ($insSlike as $s) if ($s['t'] > $insNajnovija) $insNajnovija = $s['t'];
$insPrag = max($insNajnovija, time()) - 21 * 24 * 3600;
$insNovih = 0;
foreach ($insSlike as $s) if ($s['t'] > $insPrag) $insNovih++;

$insKat = [];
foreach ($insSlike as $s) {
    $c = $s['p']['category'] ?? '';
    if ($c) $insKat[$c] = ($insKat[$c] ?? 0) + 1;
}
arsort($insKat);
?>
<!DOCTYPE html>
<html lang="sr-ME">
<head><meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Zidni paneli i 3D letvice u pravim prostorima — dnevne sobe, spavaće, kupatila i kancelarije. Kliknite na sobu i vidite koji je panel na njoj.">
  <meta name="keywords" content="zidni paneli enterijer, ideje za zid, dnevna soba paneli, spavaća soba zid, kupatilo paneli, inspiracija, Podgorica, Crna Gora">
  <meta property="og:title" content="Inspiracija – Paneli u Prostorima | Make My Home Decor">
  <meta property="og:description" content="Zidni paneli i 3D letvice u pravim prostorima — dnevne sobe, spavaće, kupatila i kancelarije. Kliknite na sobu i vidite koji je panel na njoj.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://makemyhome.me/inspiracija.html">
  <meta property="og:image" content="https://makemyhome.me/images/og-dijeljenje.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:locale" content="sr_ME">
  <meta property="og:site_name" content="Make My Home Decor">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Inspiracija – Paneli u Prostorima | Make My Home Decor">
  <meta name="twitter:description" content="Zidni paneli i 3D letvice u pravim prostorima — dnevne sobe, spavaće, kupatila i kancelarije. Kliknite na sobu i vidite koji je panel na njoj.">
  <meta name="twitter:image" content="https://makemyhome.me/images/og-dijeljenje.jpg">
  <link rel="canonical" href="https://makemyhome.me/inspiracija.html">
  <title>Inspiracija – Paneli u Prostorima | Make My Home Decor</title>
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
  <link rel="stylesheet" href="css/style-v5.css?v=9c2ecdc5">
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

<section class="page-hero hero-usko">
  <div class="container">
    <div class="page-hero-content">
      <div class="breadcrumb"><a href="/">Početna</a><i class="fas fa-chevron-right"></i><span>Inspiracija</span></div>
      <h1 class="section-title">Paneli u prostorima</h1>
    </div>
  </div>
</section>

<section class="insp-wrap">
  <div class="container">

    <div class="insp-filter-bar">
      <!-- Telefon: padajuci izbornik. Sirok ekran: ista lista kao dugmad. -->
      <div class="insp-select-wrap">
        <select id="insp-select" class="insp-select" aria-label="Filter po vrsti panela">
          <option value="" data-novo="">Sve fotografije (<?= count($insSlike) ?>)</option>
          <?php if ($insNovih): ?>
          <option value="" data-novo="1">Novo (<?= $insNovih ?>)</option>
          <?php endif; ?>
          <?php foreach ($insKat as $k => $n): ?>
          <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" data-novo=""><?= htmlspecialchars($insNames[$k] ?? $k) ?> (<?= $n ?>)</option>
          <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div class="insp-filter" role="group" aria-label="Filter po vrsti panela">
        <button type="button" class="insp-chip is-on" data-k="">Sve <span><?= count($insSlike) ?></span></button>
        <?php if ($insNovih): ?>
        <button type="button" class="insp-chip" data-novo="1">Novo <span><?= $insNovih ?></span></button>
        <?php endif; ?>
        <?php foreach ($insKat as $k => $n): ?>
        <button type="button" class="insp-chip" data-k="<?= htmlspecialchars($k, ENT_QUOTES) ?>">
          <?= htmlspecialchars($insNames[$k] ?? $k) ?> <span><?= $n ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php $insBroj = []; ?>
    <div class="insp-grid" id="insp-grid">
      <?php foreach ($insSlike as $i => $s):
        $p = $s['p'];
        $kat = $insNames[$p['category'] ?? ''] ?? 'Zidni panel';
        // Redni broj razlikuje fotografije istog proizvoda. Bez njega je
        // sest razlicitih slika imalo isti alt tekst, pa ih Google slike
        // nisu mogle razdvojiti.
        $redni = ($insBroj[$p['id']] = ($insBroj[$p['id']] ?? 0) + 1);
        $alt = $p['name'] . ' u enterijeru ' . $redni . ' – ' . $kat . ' | Make My Home Decor Podgorica';
      ?>
      <a class="insp-kart" href="/<?= mmhSlugProizvoda($p) ?>"
         data-k="<?= htmlspecialchars($p['category'] ?? '', ENT_QUOTES) ?>"
         data-novo="<?= $s['t'] > $insPrag ? '1' : '' ?>">
        <img src="<?= htmlspecialchars($s['src'], ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($alt, ENT_QUOTES) ?>"
             <?= ltrim(mmhDimAtributi($s['src'])) ?>
             loading="<?= $i < 6 ? 'eager' : 'lazy' ?>" decoding="async"
             fetchpriority="<?= $i < 6 ? 'high' : 'low' ?>"
             onerror="this.onerror=null;this.closest(&quot;.insp-kart&quot;).remove();">
        <?php if ($s['t'] > $insPrag): ?><span class="insp-novo">Novo</span><?php endif; ?>
        <span class="insp-info">
          <strong><?= htmlspecialchars($p['name']) ?></strong>
          <em><?= htmlspecialchars($kat) ?> &rsaquo;</em>
        </span>
      </a>
      <?php endforeach; ?>
    </div>

    <p class="insp-prazno" id="insp-prazno" hidden>Nema fotografija u ovoj kategoriji.</p>

    <div class="insp-cta">
      <h2>Vidjeli ste nešto što vam se svidjelo?</h2>
      <p>Kliknite na sliku i otvoriće vam se tačno taj panel — sa cijenom, dimenzijama i koliko komada
         treba za vaš zid. Ako niste sigurni, pošaljite nam mjere i izračunamo isti dan.</p>
      <div class="insp-cta-dugmad">
        <a href="tel:+38269105222" class="btn btn-gold btn-lg"><i class="fas fa-phone"></i> 069 105 222</a>
        <a href="products.html" class="btn btn-outline btn-lg"><i class="fas fa-th-large"></i> Svi proizvodi</a>
        <a href="cjenovnik.html" class="btn btn-outline btn-lg"><i class="fas fa-tags"></i> Cijene</a>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  /* Fotografija ima blizu stotinu. Ranije su one poslije osme nosile data-src
     umjesto src, pa ih je ucitavao JavaScript. To je stedjelo saobracaj, ali
     je imalo dvije mane: takva slika je neispravan HTML (W3C je prijavio 95
     gresaka), a Google Images nije mogao da vidi nijednu od tih fotografija —
     jer u izvoru stranice nije pisalo gdje je slika.
     Sada svaka slika ima pravi src i loading="lazy", sto pretrazivac radi sam:
     ucitava se tek kad se priblizi ekranu, isto kao ranije, ali je adresa
     slike vidljiva i u izvoru stranice. */

  var grid = document.getElementById('insp-grid');
  var prazno = document.getElementById('insp-prazno');

  /* Kartice se rasporedjuju naizmjenicno po kolonama, pa u prvom redu stoje
     fotografija 1 i fotografija 2 — a ne 1 i 46 kao sa CSS kolonama. */
  var kartice = [].slice.call(grid.querySelectorAll('.insp-kart'));
  var kolonaSad = 0;
  function brojKolona() {
    var w = window.innerWidth;
    return w >= 1100 ? 4 : w >= 800 ? 3 : 2;
  }
  function rasporedi() {
    var n = brojKolona();
    if (n === kolonaSad) return;
    kolonaSad = n;
    var kol = [];
    for (var i = 0; i < n; i++) { var d = document.createElement('div'); d.className = 'insp-kol'; kol.push(d); }
    kartice.forEach(function (k, i) { kol[i % n].appendChild(k); });
    grid.textContent = '';
    kol.forEach(function (d) { grid.appendChild(d); });
  }
  rasporedi();
  var tajmer;
  window.addEventListener('resize', function () { clearTimeout(tajmer); tajmer = setTimeout(rasporedi, 200); });
  var izbor = document.getElementById('insp-select');
  var dugmad = [].slice.call(document.querySelectorAll('.insp-chip'));

  function filtriraj(k, samoNovo) {
    var vidljivih = 0;
    grid.querySelectorAll('.insp-kart').forEach(function (a) {
      var ok = samoNovo ? a.dataset.novo === '1' : (!k || a.dataset.k === k);
      a.hidden = !ok;
      if (ok) vidljivih++;
    });
    prazno.hidden = vidljivih > 0;
    /* Oba filtera se drze istog izbora, pa prelazak telefon/kompjuter ne zbunjuje */
    dugmad.forEach(function (b) {
      b.classList.toggle('is-on', (b.dataset.novo === '1') === samoNovo && (samoNovo || (b.dataset.k || '') === (k || '')));
    });
    if (izbor) {
      for (var i = 0; i < izbor.options.length; i++) {
        var o = izbor.options[i];
        if ((o.dataset.novo === '1') === samoNovo && (samoNovo || o.value === (k || ''))) { izbor.selectedIndex = i; break; }
      }
    }
  }

  dugmad.forEach(function (b) {
    b.addEventListener('click', function () { filtriraj(b.dataset.k || '', b.dataset.novo === '1'); });
  });
  if (izbor) {
    izbor.addEventListener('change', function () {
      var o = izbor.options[izbor.selectedIndex];
      filtriraj(o.value || '', o.dataset.novo === '1');
    });
  }
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
      </div>
      <div>
        <h3 class="footer-title">Navigacija</h3>
        <ul class="footer-links footer-links-grid">
          <li><a href="/"><i class="fas fa-chevron-right"></i> Početna</a></li>
          <li><a href="products.html"><i class="fas fa-chevron-right"></i> Svi Proizvodi</a></li>
          <li><a href="decor-box.html"><i class="fas fa-chevron-right"></i> Decor Box</a></li>
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
      <div>
        <h3 class="footer-title">Kontakt</h3>
        <ul class="footer-contact-list">
          <li><i class="fas fa-map-marker-alt"></i><span>Vojvode Maša Đurovića 43, City Kvart, Podgorica</span></li>
          <li><i class="fas fa-phone"></i><span><a href="tel:+38269105222">069 105 222</a></span></li>
          <li><i class="fas fa-envelope"></i><span><a href="mailto:makemyhome.me@gmail.com">makemyhome.me@gmail.com</a></span></li>
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
  <a href="https://wa.me/38269105222?text=Zdravo%2C%20imam%20pitanje%20o%20zidnim%20panelima." target="_blank" rel="noopener" aria-label="Kontaktirajte nas na WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="30" height="30" fill="white"><path d="M16 0C7.164 0 0 7.163 0 16c0 2.822.736 5.469 2.027 7.774L0 32l8.469-2.003A15.93 15.93 0 0 0 16 32c8.836 0 16-7.163 16-16S24.836 0 16 0zm0 29.333a13.27 13.27 0 0 1-6.771-1.856l-.485-.288-5.028 1.188 1.215-4.895-.316-.503A13.247 13.247 0 0 1 2.667 16C2.667 8.636 8.637 2.667 16 2.667S29.333 8.636 29.333 16 23.363 29.333 16 29.333zm7.27-9.907c-.398-.199-2.355-1.162-2.72-1.295-.365-.133-.63-.199-.896.199-.265.398-1.028 1.295-1.26 1.56-.232.265-.464.298-.862.1-.398-.199-1.681-.62-3.203-1.977-1.184-1.056-1.984-2.36-2.216-2.758-.232-.398-.025-.613.174-.811.178-.178.398-.465.597-.697.199-.232.265-.398.398-.663.133-.265.066-.497-.033-.696-.1-.199-.896-2.162-1.228-2.96-.323-.776-.651-.67-.896-.683l-.763-.013c-.265 0-.696.1-.1061.497-.365.398-1.393 1.362-1.393 3.322s1.427 3.854 1.626 4.119c.199.265 2.808 4.286 6.804 6.014.951.41 1.693.655 2.271.838.954.303 1.823.26 2.51.158.765-.114 2.355-.963 2.688-1.893.333-.93.333-1.727.232-1.893-.1-.165-.365-.265-.763-.464z"/></svg></a>
  <span class="wa-tooltip">Pišite nam na WhatsApp</span>
</div>

<button id="scroll-top" aria-label="Nazad na vrh"><i class="fas fa-chevron-up"></i></button>
<script src="js/main-v4.js?v=d86f5c22"></script>
<script src="js/cart.js?v=2906a9ed"></script>
<script src="js/analytics-events.js?v=6b69b9c0" defer></script>
<script type="application/ld+json">
{"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [{"@type": "ListItem", "position": 1, "name": "Početna", "item": "https://makemyhome.me/"}, {"@type": "ListItem", "position": 2, "name": "Inspiracija", "item": "https://makemyhome.me/inspiracija.html"}]}
</script>
</body>
</html>
