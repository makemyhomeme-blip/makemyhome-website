<?php
/**
 * Admin → Analitika pregleda (vlastita, server-side) — ČIST, period-vođen prikaz.
 *
 * Sve prati JEDAN izabrani period (Danas / 7 / 30 / 90 / prilagođeno Od–Do).
 * SAMO ČITA data/pregledi.json i data/products.json.
 * Pristup: admin sesija ili deploy token (?token=...).
 */

$token = (string) ($_GET['token'] ?? '');
$prekoTokena = hash_equals('58a51c2e80e100fa2f0b022c29b87f10d2e3c8ad', $token);
if (!$prekoTokena) {
    require_once __DIR__ . '/sesija.php';
    if (empty($_SESSION['admin_logged'])) { header('Location: index.php'); exit; }
}
$TOK = $prekoTokena ? $token : '';

$root = dirname(__DIR__);
require_once $root . '/php/slug.php';
$put = $root . '/data/pregledi.json';

// --- isključi/uključi SEBE ---
if (isset($_GET['nebroji'])) {
    $off = $_GET['nebroji'] !== '0';
    @setcookie('mmh_noview', $off ? '1' : '', ['expires' => $off ? time()+31536000 : time()-3600, 'path' => '/', 'samesite' => 'Lax']);
    header('Location: pregledi.php' . ($TOK !== '' ? '?token=' . urlencode($TOK) : '')); exit;
}
$jaIskljucen = (($_COOKIE['mmh_noview'] ?? '') === '1');

// --- reset ---
$resetovano = isset($_GET['resetovano']);
if (isset($_GET['reset']) && $_GET['reset'] === 'POTVRDA') {
    if (is_file($put)) @copy($put, $root . '/data/pregledi.backup-' . date('Ymd-His') . '.json');
    @file_put_contents($put, '{}', LOCK_EX);
    header('Location: pregledi.php?resetovano=1' . ($TOK !== '' ? '&token=' . urlencode($TOK) : '')); exit;
}

$P = json_decode(@file_get_contents($root . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];

$D = json_decode(@file_get_contents($put), true);
if (!is_array($D)) $D = [];
$ukupnoAll = (isset($D['ukupno'])   && is_array($D['ukupno']))   ? $D['ukupno']   : [];
$dani      = (isset($D['dani'])     && is_array($D['dani']))     ? $D['dani']     : [];
$posjete   = (isset($D['posjete'])  && is_array($D['posjete']))  ? $D['posjete']  : [];
$sati      = (isset($D['sati'])     && is_array($D['sati']))     ? $D['sati']     : [];
$uredjaji  = (isset($D['uredjaji']) && is_array($D['uredjaji'])) ? $D['uredjaji'] : [];
$imaPodataka = !empty($dani) || !empty($ukupnoAll) || !empty($posjete);

$catNames = [
  'bambus-paneli'=>'Bambus Paneli','bambus-drveni'=>'Drveni Paneli','bambus-tekstilni'=>'Tekstilni Paneli',
  'bambus-mermerni'=>'Mermerni Paneli','bambus-metalni'=>'Metalni Paneli','bambus-kozni'=>'Kožni Paneli',
  'classic'=>'Classic Paneli','3d-letvice'=>'3D Letvice','akusticni-paneli'=>'Akustični Paneli',
  'aluminijum-lajsne'=>'Aluminijum Lajsne','spc-pod'=>'SPC Pod','pu-kamen'=>'PU Kamen','mdf'=>'MDF Paneli',
  'flex-stone'=>'Flex Stone','akcija'=>'Akcija – Sniženja',
];
function mmhImeStranice(string $path): string {
    $map = ['/' => 'Početna strana','/products.html' => 'Svi proizvodi (lista)','/contact.html' => 'Kontakt',
        '/about.html' => 'O nama','/korpa.html' => 'Korpa','/checkout.html' => 'Plaćanje','/hvala.html' => 'Hvala (posle narudžbe)',
        '/faq.html' => 'Česta pitanja','/montaza.html' => 'Montaža','/blog.html' => 'Blog','/decor-box.php' => 'Decor Box'];
    if (isset($map[$path])) return $map[$path];
    if (substr($path, -5) === '.html') return ucfirst(str_replace('-', ' ', trim(substr($path, 1, -5))));
    return $path;
}

// ---------- PERIOD ----------
$danas = new DateTimeImmutable('today');
function mmhDaniUnazad(DateTimeImmutable $d, int $n): array { $o=[]; for($i=0;$i<$n;$i++)$o[]=$d->sub(new DateInterval('P'.$i.'D'))->format('Y-m-d'); return $o; }

$period = $_GET['period'] ?? '7';
if (!in_array($period, ['danas','7','30','90','prilagodjeno'], true)) $period = '7';
$od = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['od'] ?? '')) ? (string)$_GET['od'] : '';
$do = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['do'] ?? '')) ? (string)$_GET['do'] : '';
if ($period === 'prilagodjeno' && ($od === '' || $do === '')) $period = '7';

if ($period === 'prilagodjeno') {
    if ($od > $do) { $t=$od; $od=$do; $do=$t; }
    $periodDatumi = []; $brc = 0;
    try { $c=new DateTimeImmutable($od); $e=new DateTimeImmutable($do);
        while ($c <= $e && $brc < 400) { $periodDatumi[]=$c->format('Y-m-d'); $c=$c->add(new DateInterval('P1D')); $brc++; }
    } catch (\Throwable $ex) { $periodDatumi = mmhDaniUnazad($danas, 7); $period='7'; }
    $periodLabel = $od . ' – ' . $do;
} else {
    $n = ['danas'=>1,'7'=>7,'30'=>30,'90'=>90][$period];
    $periodDatumi = mmhDaniUnazad($danas, $n);
    $periodLabel = ['danas'=>'Danas','7'=>'Zadnjih 7 dana','30'=>'Zadnjih 30 dana','90'=>'Zadnjih 90 dana'][$period];
}

function mmhSuma(array $src, array $datumi): array { $z=[]; foreach($datumi as $d){ if(empty($src[$d])||!is_array($src[$d]))continue; foreach($src[$d] as $k=>$n)$z[$k]=($z[$k]??0)+(int)$n;} return $z; }
function mmhSumaPosjeta(array $posjete, array $datumi): array { $uk=0;$jed=0;$iz=[]; foreach($datumi as $d){ if(empty($posjete[$d]))continue; $uk+=(int)($posjete[$d]['uk']??0); $jed+=(int)($posjete[$d]['jed']??0); foreach(($posjete[$d]['izvori']??[]) as $k=>$n)$iz[$k]=($iz[$k]??0)+(int)$n;} return ['uk'=>$uk,'jed'=>$jed,'izvori'=>$iz]; }

$sP   = mmhSuma($dani, $periodDatumi);          // pregledi po ključu
$pP   = mmhSumaPosjeta($posjete, $periodDatumi); // uk, jed, izvori
$satP = mmhSuma($sati, $periodDatumi);           // "0".."23"
$urP  = mmhSuma($uredjaji, $periodDatumi);       // mobile/desktop/tablet
$V = fn(array $a, string $k): int => (int)($a[$k] ?? 0);

// trend (hronološki, max 30 zadnjih dana perioda)
$pd = $periodDatumi; sort($pd); $trendDani = array_slice($pd, -30);
$trend = []; foreach ($trendDani as $td) $trend[$td] = (int)($posjete[$td]['uk'] ?? 0);
$trendMax = max(1, empty($trend) ? 1 : max($trend));
$satMax = max(1, empty($satP) ? 1 : max($satP));
$urUk = array_sum($urP);

// ---------- TABELA ----------
$tab = $_GET['tab'] ?? 'proizvodi';
if (!in_array($tab, ['proizvodi','kategorije','stranice'], true)) $tab = 'proizvodi';
$redovi = [];
if ($tab === 'proizvodi') {
    foreach ($P as $p) { $id=(int)($p['id']??0); if($id<=0)continue; $k='p:'.$id;
        $redovi[] = ['ime'=>(string)($p['name']??('#'.$id)), 'meta'=>trim((string)($p['category']??'').(!empty($p['sku'])?' · '.$p['sku']:'')),
            'url'=>function_exists('mmhUrlProizvoda')?mmhUrlProizvoda($p):'', 'v'=>$V($sP,$k), 'vsve'=>$V($ukupnoAll,$k)]; }
} elseif ($tab === 'kategorije') {
    foreach ($catNames as $k=>$ime) { $kk='k:'.$k;
        $redovi[] = ['ime'=>$ime,'meta'=>$k,'url'=>function_exists('mmhUrlKategorije')?mmhUrlKategorije($k):'','v'=>$V($sP,$kk),'vsve'=>$V($ukupnoAll,$kk)]; }
} else {
    $pk=[]; foreach(array_merge(array_keys($ukupnoAll),array_keys($sP)) as $kk) if(strncmp($kk,'page:',5)===0)$pk[$kk]=1;
    foreach (array_keys($pk) as $kk) { $path=substr($kk,5);
        $redovi[] = ['ime'=>mmhImeStranice($path),'meta'=>$path,'url'=>'https://makemyhome.me'.$path,'v'=>$V($sP,$kk),'vsve'=>$V($ukupnoAll,$kk)]; }
}
usort($redovi, fn($a,$b)=> ($b['v']<=>$a['v']) ?: ($b['vsve']<=>$a['vsve']) ?: strcasecmp($a['ime'],$b['ime']));

$izvoriImena = ['instagram'=>'Instagram','google'=>'Google','pretraga'=>'Ostale pretrage','drustvene'=>'Društvene mreže',
  'ai'=>'ChatGPT / AI','direktno'=>'Direktno (ukucali)','sa-sajta'=>'Klik sa sajta','ostalo'=>'Ostalo'];
$izvoriP = $pP['izvori']; arsort($izvoriP); $izvUk = array_sum($izvoriP);

// linkovi (čuvaju period/datume/tab/token)
function mmhLink(array $ovr, array $baza): string { $p = array_merge($baza, $ovr); $p = array_filter($p, fn($v)=>$v!=='' && $v!==null); return '?' . http_build_query($p); }
$baza = ['tab'=>$tab,'period'=>$period,'od'=>$od,'do'=>$do]; if ($TOK!=='') $baza['token']=$TOK;
?><!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Analitika | Make My Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  *{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#f4f6fb;--card:#fff;--ink:#141a2e;--muted:#8a91a6;--line:#eef0f6;--brand:#6c5ce7;--brand2:#00b894;--blue:#0984e3;--soft:#f0f2f9}
  body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);padding:20px;-webkit-font-smoothing:antialiased}
  .wrap{max-width:960px;margin:0 auto}
  .top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
  h1{font-size:21px;font-weight:800;display:flex;align-items:center;gap:9px}
  h1 .dot{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--brand),var(--blue));display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px}
  .back{color:var(--muted);text-decoration:none;font-size:13px;font-weight:600}.back:hover{color:var(--ink)}
  .okmsg{background:#e4fbf1;color:#0b8457;padding:12px 16px;border-radius:12px;font-size:13.5px;font-weight:600;margin-bottom:16px}
  /* period bar */
  .periodbar{background:var(--card);border-radius:16px;padding:14px;box-shadow:0 4px 24px rgba(20,26,46,.05);margin-bottom:16px}
  .chips{display:flex;gap:8px;flex-wrap:wrap}
  .chip{padding:9px 16px;border-radius:100px;background:var(--soft);color:var(--ink);text-decoration:none;font-size:13.5px;font-weight:600;border:1.5px solid transparent;transition:.15s}
  .chip:hover{background:#e7eaf6}
  .chip.on{background:var(--ink);color:#fff}
  .daterow{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
  .daterow label{font-size:11.5px;color:var(--muted);font-weight:600}
  .daterow input{display:block;margin-top:4px;padding:9px 11px;border:1.5px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px;background:var(--bg)}
  .btn{display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border-radius:10px;font-size:13.5px;font-weight:700;text-decoration:none;cursor:pointer;border:none;font-family:inherit}
  .btn.p{background:var(--brand);color:#fff}
  /* stats */
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px}
  .stat{background:var(--card);border-radius:16px;padding:18px 20px;box-shadow:0 4px 24px rgba(20,26,46,.05)}
  .stat .big{font-size:33px;font-weight:800;line-height:1;letter-spacing:-1px}
  .stat .lab{font-size:12.5px;color:var(--muted);margin-top:7px;font-weight:600}
  .stat.accent{background:linear-gradient(135deg,var(--brand),var(--blue));color:#fff}
  .stat.accent .lab{color:rgba(255,255,255,.8)}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
  @media(max-width:720px){.grid2{grid-template-columns:1fr}}
  .panel{background:var(--card);border-radius:16px;padding:18px 20px;box-shadow:0 4px 24px rgba(20,26,46,.05);margin-bottom:16px}
  .panel h2{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:14px;font-weight:700}
  .src{margin-bottom:11px}.src:last-child{margin-bottom:0}
  .src .r{display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:5px;font-weight:500}
  .src .track{height:8px;background:var(--soft);border-radius:6px;overflow:hidden}.src .track>span{display:block;height:100%;background:var(--brand);border-radius:6px}
  .bars{display:flex;align-items:flex-end;gap:4px;height:110px}
  .bars .col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}
  .bars .col .n{font-size:10px;color:var(--muted);line-height:1}
  .bars .col .b{width:100%;border-radius:5px 5px 0 0;margin-top:3px}
  .bars .col .x{font-size:9px;color:var(--muted);margin-top:4px}
  /* table */
  .tabs{display:flex;gap:6px;margin-bottom:12px;background:var(--card);padding:6px;border-radius:14px;box-shadow:0 4px 24px rgba(20,26,46,.05);width:fit-content}
  .tabs a{padding:9px 16px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:13.5px;font-weight:700}
  .tabs a.on{background:var(--ink);color:#fff}
  table{width:100%;border-collapse:collapse;background:var(--card);border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(20,26,46,.05)}
  th,td{padding:13px 16px;text-align:left;font-size:13.5px;border-bottom:1px solid var(--line)}
  th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;background:#fafbfe}
  td.n,th.n{text-align:right;font-variant-numeric:tabular-nums}
  tr:last-child td{border-bottom:none}
  .rk{color:var(--muted);font-weight:800;width:34px}.rk.t{color:var(--brand)}
  .nm{font-weight:600}.nm a{color:var(--ink);text-decoration:none}.nm a:hover{color:var(--brand)}
  .mt{font-size:11px;color:var(--muted);margin-top:2px}
  .big2{font-weight:800;font-size:15px}
  .sub{color:var(--muted);font-size:11px}
  .note{font-size:12.5px;color:var(--muted);line-height:1.6;margin-top:14px}
  details.set{background:var(--card);border-radius:14px;box-shadow:0 4px 24px rgba(20,26,46,.05);margin-top:16px;padding:4px 18px}
  details.set summary{cursor:pointer;font-size:13.5px;font-weight:700;padding:14px 0;color:var(--muted);list-style:none}
  details.set summary::-webkit-details-marker{display:none}
  .setbtn{display:inline-flex;gap:7px;align-items:center;padding:9px 14px;border-radius:10px;font-size:12.5px;font-weight:700;text-decoration:none;cursor:pointer}
  .setbtn.ok{background:#e4fbf1;color:#0b8457}.setbtn.warn{background:#fdeceb;color:#c0392b}.setbtn.g{background:var(--soft);color:var(--ink)}
  .flag{display:inline-block;padding:2px 9px;border-radius:100px;font-size:11.5px;font-weight:700}.flag.on{background:#e4fbf1;color:#0b8457}.flag.off{background:var(--soft);color:var(--muted)}
  .empty{background:var(--card);border-radius:16px;padding:44px;text-align:center;color:var(--muted);box-shadow:0 4px 24px rgba(20,26,46,.05)}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <h1><span class="dot"><i class="fas fa-chart-simple"></i></span> Analitika pregleda</h1>
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Admin</a>
  </div>

  <?php if ($resetovano): ?><div class="okmsg"><i class="fas fa-check"></i> Brojač je resetovan — kreće čist.</div><?php endif; ?>

<?php if (!$imaPodataka): ?>
  <div class="empty"><p style="font-size:16px;font-weight:700;color:var(--ink);margin-bottom:8px;">Još nema zabilježenih pregleda.</p><p>Brojač skuplja podatke od sad. Vrati se za koji dan.</p></div>
<?php else: ?>

  <!-- PERIOD -->
  <div class="periodbar">
    <div class="chips">
      <?php foreach (['danas'=>'Danas','7'=>'7 dana','30'=>'30 dana','90'=>'90 dana'] as $pk=>$pl): ?>
        <a class="chip <?= ($period===$pk)?'on':'' ?>" href="<?= mmhLink(['period'=>$pk,'od'=>'','do'=>''],$baza) ?>"><?= $pl ?></a>
      <?php endforeach; ?>
      <a class="chip <?= $period==='prilagodjeno'?'on':'' ?>" href="<?= mmhLink(['period'=>'prilagodjeno','od'=>($od?:$danas->sub(new DateInterval('P7D'))->format('Y-m-d')),'do'=>($do?:$danas->format('Y-m-d'))],$baza) ?>"><i class="fas fa-calendar-days"></i> Biraj datume</a>
    </div>
    <?php if ($period==='prilagodjeno'): ?>
    <form method="get" class="daterow">
      <?php if ($TOK!==''): ?><input type="hidden" name="token" value="<?= htmlspecialchars($TOK) ?>"><?php endif; ?>
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><input type="hidden" name="period" value="prilagodjeno">
      <label>Od<input type="date" name="od" value="<?= htmlspecialchars($od) ?>"></label>
      <label>Do<input type="date" name="do" value="<?= htmlspecialchars($do) ?>"></label>
      <button type="submit" class="btn p"><i class="fas fa-magnifying-glass"></i> Prikaži</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- STATS -->
  <div class="stats">
    <div class="stat accent"><div class="big"><?= number_format($V($pP,'uk'),0,',','.') ?></div><div class="lab">Pregleda &middot; <?= htmlspecialchars($periodLabel) ?></div></div>
    <div class="stat"><div class="big"><?= number_format($V($pP,'jed'),0,',','.') ?></div><div class="lab">Posjetilaca (jedinstveni)</div></div>
    <div class="stat"><div class="big"><?= $urUk>0 ? round(($V($urP,'mobile')/$urUk)*100) : 0 ?>%</div><div class="lab">Sa mobilnog telefona</div></div>
    <div class="stat"><div class="big"><?= number_format(array_sum($ukupnoAll),0,',','.') ?></div><div class="lab">Pregleda — sve vrijeme</div></div>
  </div>

  <div class="grid2">
    <!-- IZVORI -->
    <div class="panel">
      <h2><i class="fas fa-location-arrow"></i> Odakle dolaze</h2>
      <?php if ($izvUk>0): foreach ($izvoriP as $k=>$n): if($n<=0)continue; $pct=round($n/max(1,$izvUk)*100); ?>
        <div class="src"><div class="r"><span><?= htmlspecialchars($izvoriImena[$k]??$k) ?></span><span><strong><?= $n ?></strong> &middot; <?= $pct ?>%</span></div><div class="track"><span style="width:<?= $pct ?>%"></span></div></div>
      <?php endforeach; else: ?><p class="sub">Još nema podataka.</p><?php endif; ?>
    </div>
    <!-- UREĐAJI -->
    <div class="panel">
      <h2><i class="fas fa-mobile-screen"></i> Uređaji</h2>
      <?php if ($urUk>0): foreach (['mobile'=>'Mobilni telefon','desktop'=>'Kompjuter','tablet'=>'Tablet'] as $uk=>$ui): $un=$V($urP,$uk); if($un<=0)continue; $up=round($un/max(1,$urUk)*100); ?>
        <div class="src"><div class="r"><span><?= $ui ?></span><span><strong><?= $un ?></strong> &middot; <?= $up ?>%</span></div><div class="track"><span style="width:<?= $up ?>%;background:var(--brand2)"></span></div></div>
      <?php endforeach; else: ?><p class="sub">Još nema podataka.</p><?php endif; ?>
    </div>
  </div>

  <!-- TREND -->
  <div class="panel">
    <h2><i class="fas fa-chart-column"></i> Pregledi po danima</h2>
    <div class="bars">
      <?php foreach ($trend as $td=>$tn): $h=round($tn/$trendMax*100); ?>
        <div class="col" title="<?= htmlspecialchars($td) ?>: <?= $tn ?>"><div class="n"><?= $tn ?></div><div class="b" style="height:<?= max(3,$h) ?>%;background:var(--brand)"></div><div class="x"><?= (int)substr($td,8,2) ?>.</div></div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SATI -->
  <div class="panel">
    <h2><i class="fas fa-clock"></i> Najprometniji sati (kad ljudi dolaze)</h2>
    <div class="bars" style="height:80px">
      <?php for ($h=0;$h<24;$h++): $hn=(int)($satP[(string)$h]??0); $hh=round($hn/$satMax*100); ?>
        <div class="col" title="<?= $h ?>:00 – <?= $hn ?>"><div class="b" style="height:<?= max(3,$hh) ?>%;background:var(--blue)"></div><div class="x"><?= $h%3===0?$h:'' ?></div></div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- TABELA -->
  <div class="tabs">
    <a class="<?= $tab==='proizvodi'?'on':'' ?>" href="<?= mmhLink(['tab'=>'proizvodi'],$baza) ?>">Proizvodi</a>
    <a class="<?= $tab==='kategorije'?'on':'' ?>" href="<?= mmhLink(['tab'=>'kategorije'],$baza) ?>">Kategorije</a>
    <a class="<?= $tab==='stranice'?'on':'' ?>" href="<?= mmhLink(['tab'=>'stranice'],$baza) ?>">Stranice</a>
  </div>
  <table>
    <thead><tr><th class="rk">#</th><th><?= $tab==='kategorije'?'Kategorija':($tab==='stranice'?'Stranica':'Proizvod') ?></th><th class="n">Pregleda<br><span class="sub" style="font-weight:600;text-transform:none;letter-spacing:0"><?= htmlspecialchars($periodLabel) ?></span></th><th class="n">Ukupno</th></tr></thead>
    <tbody>
    <?php $rb=0; foreach ($redovi as $r): if ($r['v']<=0) continue; $rb++; ?>
      <tr>
        <td class="rk <?= $rb<=3?'t':'' ?>"><?= $rb ?></td>
        <td><div class="nm"><?php if($r['url']): ?><a href="<?= htmlspecialchars($r['url']) ?>" target="_blank"><?= htmlspecialchars($r['ime']) ?></a><?php else: ?><?= htmlspecialchars($r['ime']) ?><?php endif; ?></div><?php if($r['meta']!==''): ?><div class="mt"><?= htmlspecialchars($r['meta']) ?></div><?php endif; ?></td>
        <td class="n big2"><?= $r['v'] ?></td>
        <td class="n sub"><?= $r['vsve'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rb===0): ?><tr><td colspan="4" style="text-align:center;color:var(--muted);padding:30px;">Nema pregleda u izabranom periodu.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <p class="note"><i class="fas fa-circle-info"></i> Broji se cijeli sajt (proizvodi, kategorije, početna, blog). Botovi se ne broje. Tvoji pregledi: <?= $jaIskljucen?'<span class="flag on">ne broje se</span>':'<span class="flag off">trenutno se broje</span>' ?>.</p>

  <details class="set">
    <summary><i class="fas fa-gear"></i> Podešavanja (isključi sebe, reset)</summary>
    <div style="padding:4px 0 16px;display:flex;gap:10px;flex-wrap:wrap;">
      <?php if ($jaIskljucen): ?><a class="setbtn g" href="?nebroji=0<?= $TOK!==''?'&token='.urlencode($TOK):'' ?>"><i class="fas fa-eye"></i> Ponovo broji moje preglede</a>
      <?php else: ?><a class="setbtn ok" href="?nebroji=1<?= $TOK!==''?'&token='.urlencode($TOK):'' ?>"><i class="fas fa-user-slash"></i> Ne broji MOJE preglede (ovaj uređaj)</a><?php endif; ?>
      <a class="setbtn warn" href="?reset=POTVRDA<?= $TOK!==''?'&token='.urlencode($TOK):'' ?>" onclick="return confirm('Obrisati sve brojke i krenuti čisto? (pravi se backup)')"><i class="fas fa-rotate-left"></i> Resetuj brojač</a>
    </div>
    <p class="sub" style="padding-bottom:14px;">„Ne broji moje" važi po uređaju (kolačić) — uradi na telefonu i na kompjuteru.</p>
  </details>

<?php endif; ?>
</div>
</body>
</html>
