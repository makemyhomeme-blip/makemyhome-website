<?php
/**
 * Admin → Analitika pregleda (vlastita, server-side).
 *
 * SAMO ČITA data/pregledi.json (puni ga php/pregledi.php pri svakom pregledu
 * proizvoda i kategorije) i data/products.json. Pokazuje:
 *   - zbir pregleda 7 / 30 dana / ukupno,
 *   - rang-listu PROIZVODA i rang-listu KATEGORIJA.
 * Ništa ne mijenja.
 *
 * Pristup: admin sesija ili deploy token (?token=...), kao i ostale admin alatke.
 */

$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) {
    require_once __DIR__ . '/sesija.php';
    if (empty($_SESSION['admin_logged'])) { header('Location: index.php'); exit; }
}

$root = dirname(__DIR__);
require_once $root . '/php/slug.php';

$P = json_decode(@file_get_contents($root . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];

$D = json_decode(@file_get_contents($root . '/data/pregledi.json'), true);
if (!is_array($D)) $D = [];
$ukupnoAll = (isset($D['ukupno']) && is_array($D['ukupno'])) ? $D['ukupno'] : [];
$dani      = (isset($D['dani'])   && is_array($D['dani']))   ? $D['dani']   : [];

// Imena kategorija (isto kao u products.php).
$catNames = [
  'bambus-paneli'=>'Bambus Paneli','bambus-drveni'=>'Drveni Paneli',
  'bambus-tekstilni'=>'Tekstilni Paneli','bambus-mermerni'=>'Mermerni Paneli',
  'bambus-metalni'=>'Metalni Paneli','bambus-kozni'=>'Kožni Paneli',
  'classic'=>'Classic Paneli','3d-letvice'=>'3D Letvice',
  'akusticni-paneli'=>'Akustični Paneli','aluminijum-lajsne'=>'Aluminijum Lajsne',
  'spc-pod'=>'SPC Pod','pu-kamen'=>'PU Kamen','mdf'=>'MDF Paneli',
  'flex-stone'=>'Flex Stone','akcija'=>'Akcija – Sniženja',
];

// --- sabiranje po periodima (vraća niz: kljuc => zbir) ---
$danas = new DateTimeImmutable('today');
function mmhSumaDana(array $dani, DateTimeImmutable $danas, int $brDana): array {
    $zbir = [];
    for ($i = 0; $i < $brDana; $i++) {
        $k = $danas->sub(new DateInterval('P' . $i . 'D'))->format('Y-m-d');
        if (empty($dani[$k]) || !is_array($dani[$k])) continue;
        foreach ($dani[$k] as $kljuc => $n) $zbir[$kljuc] = ($zbir[$kljuc] ?? 0) + (int)$n;
    }
    return $zbir;
}
$sum7  = mmhSumaDana($dani, $danas, 7);
$sum30 = mmhSumaDana($dani, $danas, 30);

$V = function (array $arr, string $kljuc): int { return (int)($arr[$kljuc] ?? 0); };

// --- izbor prikaza ---
$tab  = ($_GET['tab'] ?? 'proizvodi') === 'kategorije' ? 'kategorije' : 'proizvodi';
$sort = $_GET['sort'] ?? '30';
if (!in_array($sort, ['7', '30', 'sve'], true)) $sort = '30';
$sortKljuc = $sort === '7' ? 'v7' : ($sort === 'sve' ? 'vsve' : 'v30');

// --- redovi ---
$redovi = [];
if ($tab === 'proizvodi') {
    foreach ($P as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id <= 0) continue;
        $kljuc = 'p:' . $id;
        $redovi[] = [
            'ime'  => (string)($p['name'] ?? ('#' . $id)),
            'meta' => trim((string)($p['category'] ?? '') . (!empty($p['sku']) ? ' · ' . $p['sku'] : '')),
            'url'  => function_exists('mmhUrlProizvoda') ? mmhUrlProizvoda($p) : '',
            'v7'   => $V($sum7, $kljuc), 'v30' => $V($sum30, $kljuc), 'vsve' => $V($ukupnoAll, $kljuc),
        ];
    }
} else {
    foreach ($catNames as $k => $ime) {
        $kljuc = 'k:' . $k;
        $redovi[] = [
            'ime'  => $ime,
            'meta' => $k,
            'url'  => function_exists('mmhUrlKategorije') ? mmhUrlKategorije($k) : '',
            'v7'   => $V($sum7, $kljuc), 'v30' => $V($sum30, $kljuc), 'vsve' => $V($ukupnoAll, $kljuc),
        ];
    }
}
usort($redovi, function ($a, $b) use ($sortKljuc) {
    if ($b[$sortKljuc] !== $a[$sortKljuc]) return $b[$sortKljuc] - $a[$sortKljuc];
    if ($b['vsve'] !== $a['vsve']) return $b['vsve'] - $a['vsve'];
    return strcasecmp($a['ime'], $b['ime']);
});

// --- sažetak (sve: proizvodi + kategorije) ---
$tot7  = array_sum($sum7);
$tot30 = array_sum($sum30);
$totSve = array_sum($ukupnoAll);
$gledano30 = count(array_filter($redovi, fn($r) => $r['v30'] > 0));
$bezView30 = count($redovi) - $gledano30;
$prviDan = '';
if ($dani) { $kk = array_keys($dani); sort($kk); $prviDan = $kk[0] ?? ''; }
$imaPodataka = !empty($dani) || !empty($ukupnoAll);

function mmhStrelica(string $o, string $t): string { return $o === $t ? ' <i class="fas fa-caret-down"></i>' : ''; }
$qTab = 'tab=' . $tab;
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
  * { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --primary:#c9a86c; --dark:#1a1a1a; --gray:#888; --white:#fff; --danger:#e74c3c; --success:#27ae60; }
  body { font-family:'Inter',sans-serif; background:#f0ede8; color:var(--dark); padding:24px; }
  .wrap { max-width:1000px; margin:0 auto; }
  .top { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
  h1 { font-size:22px; font-weight:700; display:flex; align-items:center; gap:10px; }
  h1 i { color:var(--primary); }
  .back { color:var(--gray); text-decoration:none; font-size:13px; font-weight:600; }
  .back:hover { color:var(--dark); }
  .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:22px; }
  .card { background:var(--white); border-radius:14px; padding:18px 20px; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
  .card .n { font-size:26px; font-weight:700; line-height:1; }
  .card .l { font-size:12px; color:var(--gray); margin-top:6px; }
  .bar { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
  .seg { display:inline-flex; background:#e7e1d8; border-radius:100px; padding:3px; }
  .seg a { padding:7px 15px; border-radius:100px; color:var(--dark); text-decoration:none; font-size:13px; font-weight:600; }
  .seg a.active { background:var(--dark); color:#fff; }
  .spacer { flex:1; }
  .lbl { font-size:12px; color:var(--gray); }
  table { width:100%; border-collapse:collapse; background:var(--white); border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
  th, td { padding:12px 14px; text-align:left; font-size:13.5px; border-bottom:1px solid #f0ede8; }
  th { background:#fbf9f6; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--gray); font-weight:700; }
  th a { color:inherit; text-decoration:none; }
  td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
  tr:last-child td { border-bottom:none; }
  .rank { color:var(--gray); font-weight:700; width:34px; }
  .rank.top { color:var(--primary); }
  .pname { font-weight:600; }
  .pname a { color:var(--dark); text-decoration:none; }
  .pname a:hover { color:var(--primary); text-decoration:underline; }
  .meta { font-size:11.5px; color:var(--gray); margin-top:2px; }
  .zero { color:#c9c3ba; }
  .big { font-weight:700; }
  .note { font-size:12.5px; color:var(--gray); margin-top:18px; line-height:1.6; }
  .empty { background:var(--white); border-radius:14px; padding:40px; text-align:center; color:var(--gray); box-shadow:0 2px 12px rgba(0,0,0,0.06); }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <h1><i class="fas fa-chart-line"></i> Analitika pregleda</h1>
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Nazad na admin</a>
  </div>

<?php if (!$imaPodataka): ?>
  <div class="empty">
    <p style="font-size:16px;font-weight:600;color:var(--dark);margin-bottom:8px;">Još nema zabilježenih pregleda.</p>
    <p>Brojač skuplja podatke od sad. Vrati se za koji dan — ovdje će biti rang-lista najgledanijih proizvoda i kategorija.</p>
  </div>
<?php else: ?>

  <div class="cards">
    <div class="card"><div class="n"><?= number_format($tot7, 0, ',', '.') ?></div><div class="l">Pregleda — 7 dana</div></div>
    <div class="card"><div class="n"><?= number_format($tot30, 0, ',', '.') ?></div><div class="l">Pregleda — 30 dana</div></div>
    <div class="card"><div class="n"><?= number_format($totSve, 0, ',', '.') ?></div><div class="l">Pregleda — ukupno</div></div>
    <div class="card"><div class="n"><?= $gledano30 ?></div><div class="l"><?= $tab==='kategorije'?'Kategorija':'Proizvoda' ?> gledano (30d)</div></div>
  </div>

  <div class="bar">
    <span class="seg">
      <a href="?tab=proizvodi&sort=<?= $sort ?>" class="<?= $tab==='proizvodi'?'active':'' ?>"><i class="fas fa-box"></i> Proizvodi</a>
      <a href="?tab=kategorije&sort=<?= $sort ?>" class="<?= $tab==='kategorije'?'active':'' ?>"><i class="fas fa-layer-group"></i> Kategorije</a>
    </span>
    <span class="spacer"></span>
    <span class="lbl">Poredaj:</span>
    <span class="seg">
      <a href="?<?= $qTab ?>&sort=7"   class="<?= $sort==='7'?'active':'' ?>">7 dana</a>
      <a href="?<?= $qTab ?>&sort=30"  class="<?= $sort==='30'?'active':'' ?>">30 dana</a>
      <a href="?<?= $qTab ?>&sort=sve" class="<?= $sort==='sve'?'active':'' ?>">Ukupno</a>
    </span>
  </div>

  <table>
    <thead>
      <tr>
        <th class="rank">#</th>
        <th><?= $tab==='kategorije'?'Kategorija':'Proizvod' ?></th>
        <th class="num"><a href="?<?= $qTab ?>&sort=7">7 dana<?= mmhStrelica('7', $sort) ?></a></th>
        <th class="num"><a href="?<?= $qTab ?>&sort=30">30 dana<?= mmhStrelica('30', $sort) ?></a></th>
        <th class="num"><a href="?<?= $qTab ?>&sort=sve">Ukupno<?= mmhStrelica('sve', $sort) ?></a></th>
      </tr>
    </thead>
    <tbody>
    <?php $rb = 0; foreach ($redovi as $r):
        if ($r[$sortKljuc] <= 0) continue;   // u svakom tabu/periodu pokazujemo samo gledane
        $rb++;
    ?>
      <tr>
        <td class="rank <?= $rb <= 3 ? 'top' : '' ?>"><?= $rb ?></td>
        <td>
          <div class="pname"><?php if ($r['url']): ?><a href="<?= htmlspecialchars($r['url']) ?>" target="_blank"><?= htmlspecialchars($r['ime']) ?></a><?php else: ?><?= htmlspecialchars($r['ime']) ?><?php endif; ?></div>
          <?php if ($r['meta'] !== ''): ?><div class="meta"><?= htmlspecialchars($r['meta']) ?></div><?php endif; ?>
        </td>
        <td class="num <?= $r['v7']===0?'zero':'' ?> <?= $sort==='7'?'big':'' ?>"><?= $r['v7'] ?></td>
        <td class="num <?= $r['v30']===0?'zero':'' ?> <?= $sort==='30'?'big':'' ?>"><?= $r['v30'] ?></td>
        <td class="num <?= $r['vsve']===0?'zero':'' ?> <?= $sort==='sve'?'big':'' ?>"><?= $r['vsve'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rb === 0): ?>
      <tr><td colspan="5" style="text-align:center;color:var(--gray);padding:28px;">Nema pregleda u izabranom periodu.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <div class="note">
    <i class="fas fa-circle-info"></i> Broji se <strong>svaki posjetilac</strong> koji otvori stranicu — nezavisno od Google Analyticsa i od kolačića. Botovi se ne broje.
    Prate se <strong>proizvodi</strong> (/paneli/…) i <strong>kategorije</strong> (/kategorija/…). Početna i blog su statične stranice pa ih ovaj brojač ne vidi (za njih bi trebao poseban mjerač).<br>
    <?php if ($bezView30 > 0): ?><?= $bezView30 ?> <?= $tab==='kategorije'?'kategorija':'proizvoda' ?> nije gledano u zadnjih 30 dana.<?php endif; ?>
    <?php if ($prviDan): ?> Podaci se skupljaju od <strong><?= htmlspecialchars($prviDan) ?></strong>.<?php endif; ?>
  </div>

<?php endif; ?>
</div>
</body>
</html>
