<?php
/**
 * Admin → Analitika pregleda (vlastita, server-side).
 *
 * SAMO ČITA data/pregledi.json (puni ga php/pregledi.php) i data/products.json.
 * Pokazuje: Danas / 7 / 30 dana / ukupno, jedinstvene posjetioce, odakle dolaze,
 * i rang-listu PROIZVODA i KATEGORIJA. Ima samoisključenje vlasnika i reset.
 *
 * Pristup: admin sesija ili deploy token (?token=...).
 */

$token = (string) ($_GET['token'] ?? '');
$prekoTokena = hash_equals('58a51c2e80e100fa2f0b022c29b87f10d2e3c8ad', $token);
if (!$prekoTokena) {
    require_once __DIR__ . '/sesija.php';
    if (empty($_SESSION['admin_logged'])) { header('Location: index.php'); exit; }
}

$root = dirname(__DIR__);
require_once $root . '/php/slug.php';
$put = $root . '/data/pregledi.json';

// --- isključi/uključi SEBE (kolačić, važi na cijelom sajtu jer je path=/) ---
if (isset($_GET['nebroji'])) {
    $off = $_GET['nebroji'] !== '0';
    @setcookie('mmh_noview', $off ? '1' : '', [
        'expires' => $off ? time() + 31536000 : time() - 3600, 'path' => '/', 'samesite' => 'Lax',
    ]);
    $sufiks = $prekoTokena ? '?token=' . urlencode($token) : '';
    header('Location: pregledi.php' . $sufiks); exit;
}
$jaIskljucen = (($_COOKIE['mmh_noview'] ?? '') === '1');

// --- reset brojača (backup pa prazno) ---
$resetovano = isset($_GET['resetovano']);
if (isset($_GET['reset']) && $_GET['reset'] === 'POTVRDA') {
    if (is_file($put)) @copy($put, $root . '/data/pregledi.backup-' . date('Ymd-His') . '.json');
    @file_put_contents($put, '{}', LOCK_EX);
    $sufiks = $prekoTokena ? '&token=' . urlencode($token) : '';
    header('Location: pregledi.php?resetovano=1' . $sufiks); exit;
}

$P = json_decode(@file_get_contents($root . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];

$D = json_decode(@file_get_contents($put), true);
if (!is_array($D)) $D = [];
$ukupnoAll = (isset($D['ukupno'])  && is_array($D['ukupno']))  ? $D['ukupno']  : [];
$dani      = (isset($D['dani'])    && is_array($D['dani']))    ? $D['dani']    : [];
$posjete   = (isset($D['posjete']) && is_array($D['posjete'])) ? $D['posjete'] : [];
$sati      = (isset($D['sati'])     && is_array($D['sati']))     ? $D['sati']     : [];
$uredjaji  = (isset($D['uredjaji']) && is_array($D['uredjaji'])) ? $D['uredjaji'] : [];

$catNames = [
  'bambus-paneli'=>'Bambus Paneli','bambus-drveni'=>'Drveni Paneli','bambus-tekstilni'=>'Tekstilni Paneli',
  'bambus-mermerni'=>'Mermerni Paneli','bambus-metalni'=>'Metalni Paneli','bambus-kozni'=>'Kožni Paneli',
  'classic'=>'Classic Paneli','3d-letvice'=>'3D Letvice','akusticni-paneli'=>'Akustični Paneli',
  'aluminijum-lajsne'=>'Aluminijum Lajsne','spc-pod'=>'SPC Pod','pu-kamen'=>'PU Kamen','mdf'=>'MDF Paneli',
  'flex-stone'=>'Flex Stone','akcija'=>'Akcija – Sniženja',
];

$danas = new DateTimeImmutable('today');
function mmhDaniUnazad(DateTimeImmutable $danas, int $n): array {
    $out = [];
    for ($i = 0; $i < $n; $i++) $out[] = $danas->sub(new DateInterval('P' . $i . 'D'))->format('Y-m-d');
    return $out;
}
// zbir pregleda po KLJUČU za zadnjih N dana
function mmhSumaKljuc(array $dani, array $datumi): array {
    $z = [];
    foreach ($datumi as $d) {
        if (empty($dani[$d]) || !is_array($dani[$d])) continue;
        foreach ($dani[$d] as $k => $n) $z[$k] = ($z[$k] ?? 0) + (int)$n;
    }
    return $z;
}
// zbir POSJETA (uk, jed, izvori) za zadnjih N dana
function mmhSumaPosjeta(array $posjete, array $datumi): array {
    $uk = 0; $jed = 0; $izv = [];
    foreach ($datumi as $d) {
        if (empty($posjete[$d])) continue;
        $uk  += (int)($posjete[$d]['uk']  ?? 0);
        $jed += (int)($posjete[$d]['jed'] ?? 0);
        foreach (($posjete[$d]['izvori'] ?? []) as $k => $n) $izv[$k] = ($izv[$k] ?? 0) + (int)$n;
    }
    return ['uk' => $uk, 'jed' => $jed, 'izvori' => $izv];
}

$d1  = mmhDaniUnazad($danas, 1);
$d7  = mmhDaniUnazad($danas, 7);
$d30 = mmhDaniUnazad($danas, 30);
$s1  = mmhSumaKljuc($dani, $d1);
$s7  = mmhSumaKljuc($dani, $d7);
$s30 = mmhSumaKljuc($dani, $d30);
$pDanas = mmhSumaPosjeta($posjete, $d1);
$p7     = mmhSumaPosjeta($posjete, $d7);
$p30    = mmhSumaPosjeta($posjete, $d30);

// --- prilagođeni period (biranje datuma od–do) ---
$od = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['od'] ?? '')) ? (string)$_GET['od'] : '';
$do = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['do'] ?? '')) ? (string)$_GET['do'] : '';
$customRange = ($od !== '' && $do !== '');
$sR = []; $pR = ['uk' => 0, 'jed' => 0, 'izvori' => []];
if ($customRange) {
    if ($od > $do) { $tmp = $od; $od = $do; $do = $tmp; } // obrnuto -> zamijeni
    try {
        $cur = new DateTimeImmutable($od); $end = new DateTimeImmutable($do);
        $rangeDatumi = []; $brc = 0;
        while ($cur <= $end && $brc < 400) { $rangeDatumi[] = $cur->format('Y-m-d'); $cur = $cur->add(new DateInterval('P1D')); $brc++; }
        $sR = mmhSumaKljuc($dani, $rangeDatumi);
        $pR = mmhSumaPosjeta($posjete, $rangeDatumi);
    } catch (\Throwable $e) { $customRange = false; }
}

$V = fn(array $a, string $k): int => (int)($a[$k] ?? 0);

// Lijepo ime za statičnu stranicu (ključ "page:<putanja>").
function mmhImeStranice(string $path): string {
    $map = [
        '/' => 'Početna strana', '/products.html' => 'Svi proizvodi (lista)',
        '/contact.html' => 'Kontakt', '/about.html' => 'O nama',
        '/korpa.html' => 'Korpa', '/checkout.html' => 'Plaćanje (checkout)',
        '/hvala.html' => 'Hvala (posle narudžbe)', '/faq.html' => 'Česta pitanja',
        '/montaza.html' => 'Montaža', '/blog.html' => 'Blog', '/decor-box.php' => 'Decor Box',
    ];
    if (isset($map[$path])) return $map[$path];
    if (substr($path, -5) === '.html') return ucfirst(str_replace('-', ' ', trim(substr($path, 1, -5))));
    return $path;
}

$tab  = $_GET['tab'] ?? 'proizvodi';
if (!in_array($tab, ['proizvodi','kategorije','stranice'], true)) $tab = 'proizvodi';
$dozvSort = $customRange ? ['danas','7','30','sve','period'] : ['danas','7','30','sve'];
$sort = $_GET['sort'] ?? ($customRange ? 'period' : '7');
if (!in_array($sort, $dozvSort, true)) $sort = $customRange ? 'period' : '7';
$sk = ['danas'=>'v1','7'=>'v7','30'=>'v30','sve'=>'vsve','period'=>'vR'][$sort];

$redovi = [];
if ($tab === 'proizvodi') {
    foreach ($P as $p) {
        $id = (int)($p['id'] ?? 0); if ($id <= 0) continue;
        $k = 'p:' . $id;
        $redovi[] = ['ime'=>(string)($p['name'] ?? ('#'.$id)),
            'meta'=>trim((string)($p['category'] ?? '') . (!empty($p['sku']) ? ' · '.$p['sku'] : '')),
            'url'=>function_exists('mmhUrlProizvoda') ? mmhUrlProizvoda($p) : '',
            'v1'=>$V($s1,$k),'v7'=>$V($s7,$k),'v30'=>$V($s30,$k),'vsve'=>$V($ukupnoAll,$k),'vR'=>$V($sR,$k)];
    }
} elseif ($tab === 'kategorije') {
    foreach ($catNames as $k => $ime) {
        $kk = 'k:' . $k;
        $redovi[] = ['ime'=>$ime,'meta'=>$k,
            'url'=>function_exists('mmhUrlKategorije') ? mmhUrlKategorije($k) : '',
            'v1'=>$V($s1,$kk),'v7'=>$V($s7,$kk),'v30'=>$V($s30,$kk),'vsve'=>$V($ukupnoAll,$kk),'vR'=>$V($sR,$kk)];
    }
} else { // stranice (page:<putanja>) — početna, blog, products.html…
    $pageKeys = [];
    foreach (array_merge(array_keys($ukupnoAll), array_keys($s1), array_keys($s7), array_keys($s30)) as $kk)
        if (strncmp($kk, 'page:', 5) === 0) $pageKeys[$kk] = true;
    foreach (array_keys($pageKeys) as $kk) {
        $path = substr($kk, 5);
        $redovi[] = ['ime'=>mmhImeStranice($path),'meta'=>$path,
            'url'=>'https://makemyhome.me' . $path,
            'v1'=>$V($s1,$kk),'v7'=>$V($s7,$kk),'v30'=>$V($s30,$kk),'vsve'=>$V($ukupnoAll,$kk),'vR'=>$V($sR,$kk)];
    }
}
usort($redovi, function ($a, $b) use ($sk) {
    if ($b[$sk] !== $a[$sk]) return $b[$sk] - $a[$sk];
    if ($b['vsve'] !== $a['vsve']) return $b['vsve'] - $a['vsve'];
    return strcasecmp($a['ime'], $b['ime']);
});

$prviDan = '';
$sviDatumi = array_keys($dani + $posjete);
if ($sviDatumi) { sort($sviDatumi); $prviDan = $sviDatumi[0]; }
$imaPodataka = !empty($dani) || !empty($ukupnoAll) || !empty($posjete);

$izvoriImena = ['instagram'=>'Instagram','google'=>'Google','pretraga'=>'Ostale pretrage',
  'drustvene'=>'Društvene mreže','ai'=>'ChatGPT / AI','direktno'=>'Direktno (ukucali)',
  'sa-sajta'=>'Klik sa sajta','ostalo'=>'Ostalo'];
// Izvori za prikaz: izabrani period ako je biran, inače 30 dana.
$pIzvori = $customRange ? $pR['izvori'] : $p30['izvori'];
arsort($pIzvori);
$izvorUk = array_sum($pIzvori);
$izvoriLabel = $customRange ? ($od . ' – ' . $do) : '30 dana';

// --- grafikoni: sati, uređaji (za izabrani period / 30 dana), trend po danima ---
$periodDatumi = $customRange ? (isset($rangeDatumi) ? $rangeDatumi : $d30) : $d30;
$satiZbir = mmhSumaKljuc($sati, $periodDatumi);      // ključevi "0".."23"
$satiMax  = max(1, empty($satiZbir) ? 1 : max($satiZbir));
$urZbir   = mmhSumaKljuc($uredjaji, $periodDatumi);  // mobile/desktop/tablet
$urUk     = array_sum($urZbir);
$trend = [];
foreach (array_reverse(mmhDaniUnazad($danas, 14)) as $td) $trend[$td] = (int)($posjete[$td]['uk'] ?? 0);
$trendMax = max(1, empty($trend) ? 1 : max($trend));

function mmhStr(string $o, string $t): string { return $o === $t ? ' <i class="fas fa-caret-down"></i>' : ''; }
$rangeQ = $customRange ? '&od=' . urlencode($od) . '&do=' . urlencode($do) : '';
$q = 'tab=' . $tab . ($prekoTokena ? '&token=' . urlencode($token) : '') . $rangeQ;
$qTok = $prekoTokena ? '&token=' . urlencode($token) : '';
?><!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Analitika pregleda | Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
  * { box-sizing:border-box; margin:0; padding:0; }
  :root { --primary:#c9a86c; --dark:#1a1a1a; --gray:#888; --white:#fff; --danger:#e74c3c; --success:#27ae60; }
  body { font-family:'Inter',sans-serif; background:#f0ede8; color:var(--dark); padding:24px; }
  .wrap { max-width:1000px; margin:0 auto; }
  .top { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
  h1 { font-size:22px; font-weight:700; display:flex; align-items:center; gap:10px; } h1 i { color:var(--primary); }
  .back { color:var(--gray); text-decoration:none; font-size:13px; font-weight:600; } .back:hover { color:var(--dark); }
  .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:18px; }
  .card { background:var(--white); border-radius:14px; padding:16px 18px; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
  .card.hl { background:var(--dark); color:#fff; }
  .card .n { font-size:25px; font-weight:700; line-height:1; } .card .l { font-size:12px; color:var(--gray); margin-top:6px; }
  .card.hl .l { color:rgba(255,255,255,0.6); } .card .s { font-size:12px; color:var(--primary); margin-top:3px; font-weight:600; }
  .panel { background:var(--white); border-radius:14px; padding:18px 20px; box-shadow:0 2px 12px rgba(0,0,0,0.06); margin-bottom:18px; }
  .panel h2 { font-size:13px; text-transform:uppercase; letter-spacing:.5px; color:var(--gray); margin-bottom:14px; }
  .src { margin-bottom:10px; } .src:last-child { margin-bottom:0; }
  .src .row { display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px; }
  .src .bar { height:8px; background:#eee4d6; border-radius:5px; overflow:hidden; }
  .src .bar > span { display:block; height:100%; background:var(--primary); }
  .bar2 { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
  .seg { display:inline-flex; background:#e7e1d8; border-radius:100px; padding:3px; flex-wrap:wrap; }
  .seg a { padding:7px 13px; border-radius:100px; color:var(--dark); text-decoration:none; font-size:13px; font-weight:600; }
  .seg a.active { background:var(--dark); color:#fff; } .spacer { flex:1; } .lbl { font-size:12px; color:var(--gray); }
  table { width:100%; border-collapse:collapse; background:var(--white); border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
  th, td { padding:11px 12px; text-align:left; font-size:13px; border-bottom:1px solid #f0ede8; }
  th { background:#fbf9f6; font-size:10.5px; text-transform:uppercase; letter-spacing:.4px; color:var(--gray); font-weight:700; }
  th a { color:inherit; text-decoration:none; }
  td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
  tr:last-child td { border-bottom:none; }
  .rank { color:var(--gray); font-weight:700; width:30px; } .rank.top { color:var(--primary); }
  .pname { font-weight:600; } .pname a { color:var(--dark); text-decoration:none; } .pname a:hover { color:var(--primary); text-decoration:underline; }
  .meta { font-size:11px; color:var(--gray); margin-top:2px; } .zero { color:#c9c3ba; } .big { font-weight:700; }
  .note { font-size:12.5px; color:var(--gray); margin-top:16px; line-height:1.6; }
  .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 14px; border-radius:9px; font-size:12.5px; font-weight:600; text-decoration:none; cursor:pointer; border:1px solid transparent; }
  .btn.ok { background:rgba(39,174,96,0.12); color:#1e8449; } .btn.warn { background:rgba(231,76,60,0.1); color:#c0392b; }
  .btn.ghost { background:#f0ede8; color:var(--dark); }
  .flag { display:inline-block; padding:3px 10px; border-radius:100px; font-size:12px; font-weight:600; }
  .flag.on { background:rgba(39,174,96,0.14); color:#1e8449; } .flag.off { background:#f0ede8; color:var(--gray); }
  .empty { background:var(--white); border-radius:14px; padding:40px; text-align:center; color:var(--gray); box-shadow:0 2px 12px rgba(0,0,0,0.06); }
  .ok-msg { background:rgba(39,174,96,0.12); color:#1e8449; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; font-weight:600; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <h1><i class="fas fa-chart-line"></i> Analitika pregleda</h1>
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Nazad na admin</a>
  </div>

  <?php if ($resetovano): ?><div class="ok-msg"><i class="fas fa-check"></i> Brojač je resetovan — kreće čist od danas.</div><?php endif; ?>

<?php if (!$imaPodataka): ?>
  <div class="empty">
    <p style="font-size:16px;font-weight:600;color:var(--dark);margin-bottom:8px;">Još nema zabilježenih pregleda.</p>
    <p>Brojač skuplja podatke od sad (ne uvozi staru istoriju iz Google Analyticsa).<br>Vrati se za koji dan — ovdje će biti prave brojke.</p>
  </div>
<?php else: ?>

  <div class="cards">
    <?php if ($customRange): ?>
    <div class="card hl"><div class="n"><?= number_format($V($pR,'uk'),0,',','.') ?></div><div class="l">Izabrani period</div><div class="s"><?= $V($pR,'jed') ?> posjetilaca · <?= htmlspecialchars($od) ?> → <?= htmlspecialchars($do) ?></div></div>
    <?php endif; ?>
    <div class="card <?= $customRange?'':'hl' ?>"><div class="n"><?= number_format($V($pDanas,'uk'),0,',','.') ?></div><div class="l">Pregleda DANAS</div><div class="s"><?= $V($pDanas,'jed') ?> posjetilaca</div></div>
    <div class="card"><div class="n"><?= number_format($V($p7,'uk'),0,',','.') ?></div><div class="l">Pregleda — 7 dana</div><div class="s"><?= $V($p7,'jed') ?> posjetilaca</div></div>
    <div class="card"><div class="n"><?= number_format($V($p30,'uk'),0,',','.') ?></div><div class="l">Pregleda — 30 dana</div><div class="s"><?= $V($p30,'jed') ?> posjetilaca</div></div>
    <div class="card"><div class="n"><?= number_format(array_sum($ukupnoAll),0,',','.') ?></div><div class="l">Pregleda — ukupno</div></div>
  </div>

  <div class="panel" style="padding:14px 18px;">
    <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <?php if ($prekoTokena): ?><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><?php endif; ?>
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <label style="font-size:12px;color:var(--gray);">Od<br><input type="date" name="od" value="<?= htmlspecialchars($od) ?>" style="padding:8px;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:14px;"></label>
      <label style="font-size:12px;color:var(--gray);">Do<br><input type="date" name="do" value="<?= htmlspecialchars($do) ?>" style="padding:8px;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:14px;"></label>
      <button type="submit" class="btn ok" style="padding:10px 16px;"><i class="fas fa-calendar-check"></i> Prikaži</button>
      <?php if ($customRange): ?><a class="btn ghost" href="?tab=<?= htmlspecialchars($tab) . $qTok ?>" style="padding:10px 14px;">Poništi datume</a><?php endif; ?>
    </form>
    <p style="font-size:11.5px;color:var(--gray);margin-top:8px;">Izaberi <strong>Od</strong> i <strong>Do</strong> pa „Prikaži" — kartica „Izabrani period" i tabela (kolona <strong>Period</strong>) pokažu baš te dane. (Čuva se zadnjih 120 dana.)</p>
  </div>

  <?php if ($izvorUk > 0): ?>
  <div class="panel">
    <h2><i class="fas fa-location-arrow"></i> Odakle dolaze posjetioci (<?= htmlspecialchars($izvoriLabel) ?>)</h2>
    <?php foreach ($pIzvori as $k => $n): if ($n <= 0) continue; $pct = round($n / max(1,$izvorUk) * 100); ?>
      <div class="src">
        <div class="row"><span><?= htmlspecialchars($izvoriImena[$k] ?? $k) ?></span><span><strong><?= $n ?></strong> &middot; <?= $pct ?>%</span></div>
        <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="bar2">
    <span class="seg">
      <a href="?tab=proizvodi&sort=<?= $sort . $qTok ?>" class="<?= $tab==='proizvodi'?'active':'' ?>"><i class="fas fa-box"></i> Proizvodi</a>
      <a href="?tab=kategorije&sort=<?= $sort . $qTok ?>" class="<?= $tab==='kategorije'?'active':'' ?>"><i class="fas fa-layer-group"></i> Kategorije</a>
      <a href="?tab=stranice&sort=<?= $sort . $qTok ?>" class="<?= $tab==='stranice'?'active':'' ?>"><i class="fas fa-file-lines"></i> Stranice</a>
    </span>
    <span class="spacer"></span>
    <span class="lbl">Poredaj:</span>
    <span class="seg">
      <?php if ($customRange): ?><a href="?<?= $q ?>&sort=period" class="<?= $sort==='period'?'active':'' ?>">Period</a><?php endif; ?>
      <a href="?<?= $q ?>&sort=danas" class="<?= $sort==='danas'?'active':'' ?>">Danas</a>
      <a href="?<?= $q ?>&sort=7"     class="<?= $sort==='7'?'active':'' ?>">7 dana</a>
      <a href="?<?= $q ?>&sort=30"    class="<?= $sort==='30'?'active':'' ?>">30 dana</a>
      <a href="?<?= $q ?>&sort=sve"   class="<?= $sort==='sve'?'active':'' ?>">Ukupno</a>
    </span>
  </div>

  <table>
    <thead>
      <tr>
        <th class="rank">#</th>
        <th><?= $tab==='kategorije'?'Kategorija':($tab==='stranice'?'Stranica':'Proizvod') ?></th>
        <?php if ($customRange): ?><th class="num"><a href="?<?= $q ?>&sort=period">Period<?= mmhStr('period',$sort) ?></a></th><?php endif; ?>
        <th class="num"><a href="?<?= $q ?>&sort=danas">Danas<?= mmhStr('danas',$sort) ?></a></th>
        <th class="num"><a href="?<?= $q ?>&sort=7">7 dana<?= mmhStr('7',$sort) ?></a></th>
        <th class="num"><a href="?<?= $q ?>&sort=30">30 dana<?= mmhStr('30',$sort) ?></a></th>
        <th class="num"><a href="?<?= $q ?>&sort=sve">Ukupno<?= mmhStr('sve',$sort) ?></a></th>
      </tr>
    </thead>
    <tbody>
    <?php $rb = 0; foreach ($redovi as $r): if ($r[$sk] <= 0) continue; $rb++; ?>
      <tr>
        <td class="rank <?= $rb<=3?'top':'' ?>"><?= $rb ?></td>
        <td>
          <div class="pname"><?php if ($r['url']): ?><a href="<?= htmlspecialchars($r['url']) ?>" target="_blank"><?= htmlspecialchars($r['ime']) ?></a><?php else: ?><?= htmlspecialchars($r['ime']) ?><?php endif; ?></div>
          <?php if ($r['meta'] !== ''): ?><div class="meta"><?= htmlspecialchars($r['meta']) ?></div><?php endif; ?>
        </td>
        <?php if ($customRange): ?><td class="num <?= $r['vR']===0?'zero':'' ?> <?= $sort==='period'?'big':'' ?>"><?= $r['vR'] ?></td><?php endif; ?>
        <td class="num <?= $r['v1']===0?'zero':'' ?> <?= $sort==='danas'?'big':'' ?>"><?= $r['v1'] ?></td>
        <td class="num <?= $r['v7']===0?'zero':'' ?> <?= $sort==='7'?'big':'' ?>"><?= $r['v7'] ?></td>
        <td class="num <?= $r['v30']===0?'zero':'' ?> <?= $sort==='30'?'big':'' ?>"><?= $r['v30'] ?></td>
        <td class="num <?= $r['vsve']===0?'zero':'' ?> <?= $sort==='sve'?'big':'' ?>"><?= $r['vsve'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rb === 0): ?><tr><td colspan="<?= $customRange?7:6 ?>" style="text-align:center;color:var(--gray);padding:26px;">Nema pregleda u izabranom periodu.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <div class="panel" style="margin-top:18px;">
    <h2><i class="fas fa-chart-column"></i> Promet po danima (zadnjih 14 dana)</h2>
    <div style="display:flex;align-items:flex-end;gap:4px;height:120px;">
      <?php foreach ($trend as $td => $tn): $hh = round($tn / $trendMax * 100); ?>
        <div title="<?= htmlspecialchars($td) ?>: <?= $tn ?> pregleda" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
          <div style="font-size:10px;color:var(--gray);line-height:1;"><?= $tn ?></div>
          <div style="width:100%;background:var(--primary);border-radius:4px 4px 0 0;height:<?= max(2,$hh) ?>%;margin-top:3px;"></div>
          <div style="font-size:9px;color:var(--gray);margin-top:3px;"><?= (int)substr($td,8,2) ?>.</div>
        </div>
      <?php endforeach; ?>
    </div>
    <p style="font-size:11.5px;color:var(--gray);margin-top:8px;">Pregledi cijelog sajta po danima (broj iznad stuba, dan ispod).</p>
  </div>

  <div class="panel" style="margin-top:18px;">
    <h2><i class="fas fa-clock"></i> Najprometniji sati (<?= htmlspecialchars($izvoriLabel) ?>)</h2>
    <div style="display:flex;align-items:flex-end;gap:2px;height:90px;">
      <?php for ($h = 0; $h < 24; $h++): $hn = (int)($satiZbir[(string)$h] ?? 0); $hh = round($hn / $satiMax * 100); ?>
        <div title="<?= $h ?>:00 – <?= $hn ?> pregleda" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
          <div style="width:100%;background:#3498db;border-radius:3px 3px 0 0;height:<?= max(2,$hh) ?>%;"></div>
          <?php if ($h % 3 === 0): ?><div style="font-size:9px;color:var(--gray);margin-top:3px;"><?= $h ?></div><?php else: ?><div style="height:14px;"></div><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <p style="font-size:11.5px;color:var(--gray);margin-top:8px;">Kad ljudi najviše ulaze (sat u danu, 0–23h). Korisno za objave/akcije.</p>
  </div>

  <div class="panel" style="margin-top:18px;">
    <h2><i class="fas fa-mobile-screen"></i> Uređaji (<?= htmlspecialchars($izvoriLabel) ?>)</h2>
    <?php if ($urUk > 0): foreach (['mobile'=>'Mobilni telefon','desktop'=>'Kompjuter','tablet'=>'Tablet'] as $uk => $uime): $un = (int)($urZbir[$uk] ?? 0); if ($un <= 0) continue; $upct = round($un / max(1,$urUk) * 100); ?>
      <div class="src"><div class="row"><span><?= $uime ?></span><span><strong><?= $un ?></strong> &middot; <?= $upct ?>%</span></div><div class="bar"><span style="width:<?= $upct ?>%"></span></div></div>
    <?php endforeach; else: ?><p style="font-size:12.5px;color:var(--gray);">Još nema podataka o uređajima.</p><?php endif; ?>
    <p style="font-size:11.5px;color:var(--gray);margin-top:8px;">Na čemu ljudi gledaju sajt (najčešće mobilni) — da znaš da sajt mora dobro da izgleda na telefonu.</p>
  </div>

  <div class="panel" style="margin-top:18px;">
    <h2><i class="fas fa-shield-halved"></i> Jesu li posjete realne?</h2>
    <p style="font-size:13px;line-height:1.7;color:var(--dark);">
      ✅ <strong>Botovi i alati se NE broje</strong> (Google, pretraživači, WhatsApp/Viber pregled…).<br>
      ✅ <strong>Jedinstveni posjetioci</strong> se broje odvojeno od pregleda — ako je malo ljudi a puno pregleda, to je ili jedan čovjek koji više puta otvara, ili nije realno.<br>
      <?php if ($jaIskljucen): ?>
      ✅ <strong>Tvoji pregledi se NE broje</strong> na ovom uređaju <span class="flag on">isključeno</span>
      <?php else: ?>
      ⚠️ <strong>Tvoji pregledi se TRENUTNO broje</strong> na ovom uređaju <span class="flag off">uključeno</span>
      <?php endif; ?>
    </p>
    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
      <?php if ($jaIskljucen): ?>
        <a class="btn ghost" href="?nebroji=0<?= $qTok ?>"><i class="fas fa-eye"></i> Ponovo broji moje preglede</a>
      <?php else: ?>
        <a class="btn ok" href="?nebroji=1<?= $qTok ?>"><i class="fas fa-user-slash"></i> Ne broji MOJE preglede (ovaj uređaj)</a>
      <?php endif; ?>
      <a class="btn warn" href="?reset=POTVRDA<?= $qTok ?>" onclick="return confirm('Obrisati sve dosadašnje brojke i krenuti čist od danas? (pravi se backup)')"><i class="fas fa-rotate-left"></i> Resetuj brojač</a>
    </div>
    <p style="font-size:11.5px;color:var(--gray);margin-top:10px;">„Ne broji moje" važi po uređaju/pregledaču (zapamti se kolačićem) — uradi isto na telefonu i na kompjuteru.</p>
  </div>

  <div class="note">
    <i class="fas fa-circle-info"></i> Broji se <strong>cijeli sajt</strong> — proizvodi (/paneli/…), kategorije (/kategorija/…) i ostale stranice (<strong>početna</strong>, blog, kontakt…). Kartice „Danas / posjetilaca / izvori" gore pokazuju ukupno za cijeli sajt. Tab <strong>Stranice</strong> pokazuje baš početnu i ostale statične stranice (tu reklame vode ljude).
    <?php if ($prviDan): ?> Podaci se skupljaju od <strong><?= htmlspecialchars($prviDan) ?></strong>.<?php endif; ?>
  </div>

<?php endif; ?>
</div>
</body>
</html>
