<?php
/**
 * Admin → Najgledaniji proizvodi (paneli).
 *
 * SAMO ČITA data/pregledi.json (puni ga php/pregledi.php pri svakom pregledu
 * proizvoda) i data/products.json, i pokazuje rang-listu: 7 dana / 30 dana /
 * ukupno. Ništa ne mijenja.
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
$ukupno = (isset($D['ukupno']) && is_array($D['ukupno'])) ? $D['ukupno'] : [];
$dani   = (isset($D['dani'])   && is_array($D['dani']))   ? $D['dani']   : [];

// --- sabiranje po periodima ---
$danas = new DateTimeImmutable('today');
function mmhSumaDana(array $dani, DateTimeImmutable $danas, int $brDana): array {
    $zbir = [];
    for ($i = 0; $i < $brDana; $i++) {
        $kljuc = $danas->sub(new DateInterval('P' . $i . 'D'))->format('Y-m-d');
        if (empty($dani[$kljuc]) || !is_array($dani[$kljuc])) continue;
        foreach ($dani[$kljuc] as $id => $n) {
            $zbir[$id] = ($zbir[$id] ?? 0) + (int)$n;
        }
    }
    return $zbir;
}
$sum7  = mmhSumaDana($dani, $danas, 7);
$sum30 = mmhSumaDana($dani, $danas, 30);

// --- spoji sa proizvodima ---
$sort = $_GET['sort'] ?? '30';
if (!in_array($sort, ['7', '30', 'sve'], true)) $sort = '30';

$redovi = [];
foreach ($P as $p) {
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) continue;
    $k = (string)$id;
    $redovi[] = [
        'id'    => $id,
        'ime'   => (string)($p['name'] ?? ('#' . $id)),
        'sku'   => (string)($p['sku'] ?? ''),
        'kat'   => (string)($p['category'] ?? ''),
        'url'   => function_exists('mmhUrlProizvoda') ? mmhUrlProizvoda($p) : '',
        'v7'    => (int)($sum7[$k]   ?? 0),
        'v30'   => (int)($sum30[$k]  ?? 0),
        'vsve'  => (int)($ukupno[$k] ?? 0),
    ];
}

usort($redovi, function ($a, $b) use ($sort) {
    $kljuc = $sort === '7' ? 'v7' : ($sort === 'sve' ? 'vsve' : 'v30');
    if ($b[$kljuc] !== $a[$kljuc]) return $b[$kljuc] - $a[$kljuc];
    if ($b['vsve'] !== $a['vsve']) return $b['vsve'] - $a['vsve'];
    return strcasecmp($a['ime'], $b['ime']);
});

// --- sažetak ---
$totSve  = array_sum($ukupno);
$tot7    = array_sum($sum7);
$tot30   = array_sum($sum30);
$saViews30 = count(array_filter($redovi, fn($r) => $r['v30'] > 0));
$bezViews30 = count($redovi) - $saViews30;
$prviDan = '';
if ($dani) { $kljucevi = array_keys($dani); sort($kljucevi); $prviDan = $kljucevi[0] ?? ''; }

function mmhStrelica(string $ovaj, string $trenutni): string {
    return $ovaj === $trenutni ? ' <i class="fas fa-caret-down"></i>' : '';
}
?><!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Najgledaniji paneli | Admin</title>
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
  .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:22px; }
  .card { background:var(--white); border-radius:14px; padding:18px 20px; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
  .card .n { font-size:26px; font-weight:700; line-height:1; }
  .card .l { font-size:12px; color:var(--gray); margin-top:6px; }
  .tabs { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
  .tab { padding:8px 16px; border-radius:100px; background:var(--white); color:var(--dark); text-decoration:none; font-size:13px; font-weight:600; box-shadow:0 1px 6px rgba(0,0,0,0.06); }
  .tab.active { background:var(--dark); color:#fff; }
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
    <h1><i class="fas fa-chart-line"></i> Najgledaniji paneli</h1>
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Nazad na admin</a>
  </div>

<?php if (!$dani && !$ukupno): ?>
  <div class="empty">
    <p style="font-size:16px;font-weight:600;color:var(--dark);margin-bottom:8px;">Još nema zabilježenih pregleda.</p>
    <p>Brojač počinje da skuplja podatke čim ova nadogradnja ode na sajt (sync).<br>
       Vrati se za koji dan — ovdje će biti rang-lista najgledanijih proizvoda.</p>
  </div>
<?php else: ?>

  <div class="cards">
    <div class="card"><div class="n"><?= number_format($tot7, 0, ',', '.') ?></div><div class="l">Pregleda — zadnjih 7 dana</div></div>
    <div class="card"><div class="n"><?= number_format($tot30, 0, ',', '.') ?></div><div class="l">Pregleda — zadnjih 30 dana</div></div>
    <div class="card"><div class="n"><?= number_format($totSve, 0, ',', '.') ?></div><div class="l">Pregleda — ukupno</div></div>
    <div class="card"><div class="n"><?= $saViews30 ?></div><div class="l">Proizvoda gledano (30 dana)</div></div>
  </div>

  <div class="tabs">
    <span style="font-size:12px;color:var(--gray);align-self:center;margin-right:4px;">Poredaj po:</span>
    <a class="tab <?= $sort==='7'?'active':'' ?>"   href="?sort=7">7 dana</a>
    <a class="tab <?= $sort==='30'?'active':'' ?>"  href="?sort=30">30 dana</a>
    <a class="tab <?= $sort==='sve'?'active':'' ?>" href="?sort=sve">Ukupno</a>
  </div>

  <table>
    <thead>
      <tr>
        <th class="rank">#</th>
        <th>Proizvod</th>
        <th class="num"><a href="?sort=7">7 dana<?= mmhStrelica('7', $sort) ?></a></th>
        <th class="num"><a href="?sort=30">30 dana<?= mmhStrelica('30', $sort) ?></a></th>
        <th class="num"><a href="?sort=sve">Ukupno<?= mmhStrelica('sve', $sort) ?></a></th>
      </tr>
    </thead>
    <tbody>
    <?php $rb = 0; foreach ($redovi as $r):
        // Preskoči proizvode bez ijednog pregleda u IZABRANOM periodu — osim "Ukupno".
        $kljuc = $sort === '7' ? 'v7' : ($sort === 'sve' ? 'vsve' : 'v30');
        if ($r[$kljuc] <= 0 && $sort !== 'sve') continue;
        if ($sort === 'sve' && $r['vsve'] <= 0) continue;
        $rb++;
    ?>
      <tr>
        <td class="rank <?= $rb <= 3 ? 'top' : '' ?>"><?= $rb ?></td>
        <td>
          <div class="pname"><?php if ($r['url']): ?><a href="<?= htmlspecialchars($r['url']) ?>" target="_blank"><?= htmlspecialchars($r['ime']) ?></a><?php else: ?><?= htmlspecialchars($r['ime']) ?><?php endif; ?></div>
          <div class="meta"><?= htmlspecialchars($r['kat']) ?><?= $r['sku'] !== '' ? ' · ' . htmlspecialchars($r['sku']) : '' ?></div>
        </td>
        <td class="num <?= $r['v7']===0?'zero':'' ?> <?= $sort==='7'?'big':'' ?>"><?= $r['v7'] ?></td>
        <td class="num <?= $r['v30']===0?'zero':'' ?> <?= $sort==='30'?'big':'' ?>"><?= $r['v30'] ?></td>
        <td class="num <?= $r['vsve']===0?'zero':'' ?> <?= $sort==='sve'?'big':'' ?>"><?= $r['vsve'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="note">
    <i class="fas fa-circle-info"></i> Broji se <strong>svaki posjetilac</strong> koji otvori stranicu proizvoda — nezavisno od Google Analyticsa i od pristanka na kolačiće. Botovi (Google, pretraživači, alati) se ne broje.<br>
    <?php if ($bezViews30 > 0): ?><?= $bezViews30 ?> proizvoda nije imalo nijedan pregled u zadnjih 30 dana.<?php endif; ?>
    <?php if ($prviDan): ?> Podaci se skupljaju od <strong><?= htmlspecialchars($prviDan) ?></strong>.<?php endif; ?>
  </div>

<?php endif; ?>
</div>
</body>
</html>
