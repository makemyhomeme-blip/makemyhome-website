<?php
/**
 * Make My Home – Server Status Monitor
 * /admin/server-status.php?key=mkhstatus2025
 */
require_once __DIR__ . '/sesija.php';
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen.');
}
if (($_GET['key'] ?? '') !== 'mkhstatus2025') {
    die('Pogrešan ključ.');
}

// ── helpers ──────────────────────────────────────────────────────────────────
function sh(string $cmd): string {
    if (!function_exists('shell_exec')) return '';
    $r = @shell_exec($cmd . ' 2>/dev/null');
    return $r !== null ? trim($r) : '';
}
function procFile(string $path): string {
    return is_readable($path) ? trim(@file_get_contents($path)) : '';
}
function fmtBytes(int $b, int $dec = 1): string {
    $u = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
    return round($b, $dec) . ' ' . $u[$i];
}
function bar(float $pct, string $color = '#2ecc71'): string {
    $pct = min(100, max(0, $pct));
    $col = $pct > 85 ? '#e74c3c' : ($pct > 65 ? '#e67e22' : $color);
    return "<div style='background:#333;border-radius:6px;height:10px;overflow:hidden;margin-top:6px'>
        <div style='width:{$pct}%;background:{$col};height:100%;border-radius:6px;transition:width .5s'></div></div>
        <small style='color:#999'>" . round($pct, 1) . "%</small>";
}

// ── CPU load ─────────────────────────────────────────────────────────────────
$load = sys_getloadavg();
$cpuCores = (int)(sh("nproc") ?: sh("grep -c processor /proc/cpuinfo") ?: 1);
$loadPct  = $cpuCores > 0 ? ($load[0] / $cpuCores) * 100 : 0;

// ── uptime ───────────────────────────────────────────────────────────────────
$uptimeRaw = procFile('/proc/uptime');
$uptimeSec = $uptimeRaw ? (int)explode(' ', $uptimeRaw)[0] : 0;
$uptimeStr = '';
if ($uptimeSec) {
    $d = intdiv($uptimeSec, 86400);
    $h = intdiv($uptimeSec % 86400, 3600);
    $m = intdiv($uptimeSec % 3600, 60);
    $uptimeStr = ($d ? "{$d}d " : '') . ($h ? "{$h}h " : '') . "{$m}min";
}

// ── RAM ──────────────────────────────────────────────────────────────────────
$memTotal = $memFree = $memAvail = $memBuffers = $memCached = 0;
$memInfo  = procFile('/proc/meminfo');
if ($memInfo) {
    preg_match('/MemTotal:\s+(\d+)/',    $memInfo, $m); $memTotal   = (int)($m[1] ?? 0) * 1024;
    preg_match('/MemFree:\s+(\d+)/',     $memInfo, $m); $memFree    = (int)($m[1] ?? 0) * 1024;
    preg_match('/MemAvailable:\s+(\d+)/',$memInfo, $m); $memAvail   = (int)($m[1] ?? 0) * 1024;
    preg_match('/Buffers:\s+(\d+)/',     $memInfo, $m); $memBuffers = (int)($m[1] ?? 0) * 1024;
    preg_match('/Cached:\s+(\d+)/',      $memInfo, $m); $memCached  = (int)($m[1] ?? 0) * 1024;
}
$memUsed    = $memTotal - $memAvail;
$memUsedPct = $memTotal > 0 ? ($memUsed / $memTotal) * 100 : 0;

// ── disk ─────────────────────────────────────────────────────────────────────
$diskRoot  = dirname(__DIR__);
$diskTotal = disk_total_space($diskRoot);
$diskFree  = disk_free_space($diskRoot);
$diskUsed  = $diskTotal - $diskFree;
$diskPct   = $diskTotal > 0 ? ($diskUsed / $diskTotal) * 100 : 0;

// ── PHP memory ───────────────────────────────────────────────────────────────
$phpMemUsed  = memory_get_usage(true);
$phpMemLimit = ini_get('memory_limit');
$phpMemLimitBytes = (function($v) {
    $v = trim($v); $l = strtolower($v[-1]);
    $n = (int)$v;
    return $l === 'g' ? $n * 1073741824 : ($l === 'm' ? $n * 1048576 : ($l === 'k' ? $n * 1024 : $n));
})($phpMemLimit);
$phpMemPct = $phpMemLimitBytes > 0 ? ($phpMemUsed / $phpMemLimitBytes) * 100 : 0;

// ── processes ────────────────────────────────────────────────────────────────
$phpProcs   = (int)(sh("pgrep -c php") ?: 0);
$apacheProcs= (int)(sh("pgrep -c -f 'apache|httpd'") ?: 0);
$totalProcs = (int)(sh("ps aux | wc -l") ?: 0);

// ── products.json size ───────────────────────────────────────────────────────
$productsFile = dirname(__DIR__) . '/data/products.json';
$productsSize = file_exists($productsFile) ? filesize($productsFile) : 0;
$productsCount = 0;
if ($productsSize) {
    $p = @json_decode(@file_get_contents($productsFile), true);
    $productsCount = $p ? count($p) : 0;
}

// ── images size ──────────────────────────────────────────────────────────────
$imgDir   = dirname(__DIR__) . '/images/products/';
$imgTotal = 0; $imgCount = 0;
foreach (glob($imgDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
    $imgTotal += filesize($f); $imgCount++;
}

// ── error log ────────────────────────────────────────────────────────────────
$errorLog  = ini_get('error_log');
$errorLines = [];
$logCleared = false;
$logWritable = $errorLog && is_writable($errorLog);

// Clear action
if (($_POST['action'] ?? '') === 'clear_log' && $logWritable) {
    file_put_contents($errorLog, '');
    $logCleared = true;
}

if ($errorLog && is_readable($errorLog) && !$logCleared) {
    $raw = sh("tail -30 " . escapeshellarg($errorLog));
    foreach (array_filter(explode("\n", $raw)) as $line) {
        $age = '';
        if (preg_match('/\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} UTC)\]/', $line, $m)) {
            $ts  = @strtotime($m[1]);
            $diff = time() - $ts;
            if ($diff < 3600)      $age = round($diff/60) . ' min staro';
            elseif ($diff < 86400) $age = round($diff/3600) . ' h staro';
            elseif ($diff < 2592000) $age = round($diff/86400) . ' dan/a staro';
            else                   $age = round($diff/2592000) . ' mj. staro';
        }
        $errorLines[] = ['text' => $line, 'age' => $age];
    }
    $errorLines = array_slice($errorLines, -5);
}

$refreshSec = 30;
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="<?= $refreshSec ?>">
<title>Server Status | Make My Home Admin</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #111; color: #eee; padding: 24px; }
h1 { font-size: 22px; color: #c9a86c; margin-bottom: 4px; }
.subtitle { color: #777; font-size: 13px; margin-bottom: 24px; }
.subtitle span { color: #aaa; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 18px; }
.card h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 12px; }
.val { font-size: 28px; font-weight: 700; color: #fff; line-height: 1; }
.val.ok { color: #2ecc71; }
.val.warn { color: #e67e22; }
.val.bad { color: #e74c3c; }
.row { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; font-size: 13px; color: #aaa; }
.tag { display: inline-block; background: #222; border: 1px solid #333; border-radius: 5px; padding: 2px 8px; font-size: 12px; color: #aaa; margin: 2px; }
.section-title { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #555; margin: 28px 0 12px; border-bottom: 1px solid #222; padding-bottom: 8px; }
pre.errors { background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 14px; font-size: 11px; color: #e74c3c; overflow-x: auto; white-space: pre-wrap; }
.back { display: inline-flex; align-items: center; gap: 8px; color: #c9a86c; text-decoration: none; font-size: 14px; margin-bottom: 24px; }
.back:hover { opacity: .8; }
.refresh-bar { height: 3px; background: #222; border-radius: 2px; margin-bottom: 20px; overflow: hidden; }
.refresh-bar div { height: 100%; background: #c9a86c; animation: shrink <?= $refreshSec ?>s linear forwards; border-radius: 2px; }
@keyframes shrink { from { width: 100%; } to { width: 0%; } }
.timestamp { color: #555; font-size: 12px; margin-left: 8px; }
</style>
</head>
<body>

<a class="back" href="dashboard.php">← Admin panel</a>

<h1>Server Status <span class="timestamp">Osvježeno: <?= date('H:i:s') ?></span></h1>
<p class="subtitle">Auto-refresh svakih <?= $refreshSec ?>s &nbsp;·&nbsp; <span><?= php_uname('n') ?></span></p>
<div class="refresh-bar"><div></div></div>

<div class="grid">

  <!-- CPU -->
  <div class="card">
    <h2>CPU Load</h2>
    <div class="val <?= $loadPct > 85 ? 'bad' : ($loadPct > 65 ? 'warn' : 'ok') ?>">
      <?= round($load[0], 2) ?>
    </div>
    <?= bar($loadPct) ?>
    <div class="row">
      <span>1 min avg</span>
      <span><?= $cpuCores ?> core<?= $cpuCores > 1 ? 's' : '' ?></span>
    </div>
    <div class="row" style="margin-top:4px">
      <span style="color:#777">5 min: <?= round($load[1], 2) ?></span>
      <span style="color:#777">15 min: <?= round($load[2], 2) ?></span>
    </div>
  </div>

  <!-- RAM -->
  <div class="card">
    <h2>Memorija (RAM)</h2>
    <div class="val <?= $memUsedPct > 90 ? 'bad' : ($memUsedPct > 75 ? 'warn' : 'ok') ?>">
      <?= $memTotal ? fmtBytes($memUsed) : 'N/A' ?>
    </div>
    <?php if ($memTotal): ?>
    <?= bar($memUsedPct) ?>
    <div class="row">
      <span>od <?= fmtBytes($memTotal) ?></span>
      <span>slobodno <?= fmtBytes($memAvail) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Disk -->
  <div class="card">
    <h2>Disk (web root)</h2>
    <div class="val <?= $diskPct > 90 ? 'bad' : ($diskPct > 75 ? 'warn' : 'ok') ?>">
      <?= fmtBytes($diskUsed) ?>
    </div>
    <?= bar($diskPct) ?>
    <div class="row">
      <span>od <?= fmtBytes($diskTotal) ?></span>
      <span>slobodno <?= fmtBytes($diskFree) ?></span>
    </div>
  </div>

  <!-- Uptime -->
  <div class="card">
    <h2>Server Uptime</h2>
    <div class="val ok" style="font-size:20px"><?= $uptimeStr ?: 'N/A' ?></div>
    <div class="row" style="margin-top:12px">
      <span>PHP <?= PHP_VERSION ?></span>
      <span><?= PHP_OS ?></span>
    </div>
    <div class="row">
      <span>Server API: <?= PHP_SAPI ?></span>
    </div>
  </div>

  <!-- PHP -->
  <div class="card">
    <h2>PHP Memorija</h2>
    <div class="val <?= $phpMemPct > 85 ? 'bad' : ($phpMemPct > 65 ? 'warn' : 'ok') ?>">
      <?= fmtBytes($phpMemUsed) ?>
    </div>
    <?= bar($phpMemPct) ?>
    <div class="row">
      <span>limit: <?= $phpMemLimit ?></span>
      <span>max_exec: <?= ini_get('max_execution_time') ?>s</span>
    </div>
  </div>

  <!-- Procesi -->
  <div class="card">
    <h2>Procesi</h2>
    <div class="val"><?= $totalProcs ?: 'N/A' ?></div>
    <div class="row" style="margin-top:12px">
      <?php if ($phpProcs): ?><span>PHP: <strong><?= $phpProcs ?></strong></span><?php endif; ?>
      <?php if ($apacheProcs): ?><span>Apache: <strong><?= $apacheProcs ?></strong></span><?php endif; ?>
    </div>
  </div>

</div>

<!-- Sajt podaci -->
<div class="section-title">Podaci sajta</div>
<div class="grid">

  <div class="card">
    <h2>Proizvodi (products.json)</h2>
    <div class="val ok"><?= $productsCount ?></div>
    <div class="row" style="margin-top:12px">
      <span>Veličina fajla</span>
      <span><?= fmtBytes($productsSize) ?></span>
    </div>
  </div>

  <div class="card">
    <h2>Slike proizvoda</h2>
    <div class="val"><?= $imgCount ?></div>
    <div class="row" style="margin-top:12px">
      <span>Ukupna veličina</span>
      <span><?= fmtBytes($imgTotal) ?></span>
    </div>
    <div class="row">
      <span>Prosjek po slici</span>
      <span><?= $imgCount ? fmtBytes((int)($imgTotal / $imgCount)) : 'N/A' ?></span>
    </div>
  </div>

  <div class="card">
    <h2>PHP Konfiguracija</h2>
    <div style="margin-top:4px">
      <?php
      $exts = ['gd', 'curl', 'json', 'mbstring', 'fileinfo', 'zip'];
      foreach ($exts as $e):
        $ok = extension_loaded($e);
      ?>
      <span class="tag" style="color:<?= $ok ? '#2ecc71' : '#e74c3c' ?>">
        <?= $ok ? '✓' : '✗' ?> <?= $e ?>
      </span>
      <?php endforeach; ?>
    </div>
    <div class="row" style="margin-top:10px">
      <span>upload_max: <?= ini_get('upload_max_filesize') ?></span>
      <span>post_max: <?= ini_get('post_max_size') ?></span>
    </div>
  </div>

  <div class="card">
    <h2>Backup fajlovi</h2>
    <?php
    $backupImgDir = dirname(__DIR__) . '/images/products-backup/';
    $backupImgs   = count(glob($backupImgDir . '*') ?: []);
    $backupImgSz  = 0;
    foreach (glob($backupImgDir . '*') ?: [] as $f) $backupImgSz += filesize($f);
    $backupJsons  = glob(dirname(__DIR__) . '/data/products.bak.*.json') ?: [];
    ?>
    <div class="val"><?= $backupImgs ?></div>
    <div class="row" style="margin-top:12px">
      <span>Backup slika</span>
      <span><?= fmtBytes($backupImgSz) ?></span>
    </div>
    <div class="row">
      <span>Backup products.json</span>
      <span><?= count($backupJsons) ?> fajl<?= count($backupJsons) !== 1 ? 'a' : '' ?></span>
    </div>
  </div>

</div>

<?php if ($logCleared || !empty($errorLines)): ?>
<div class="section-title" style="display:flex;align-items:center;justify-content:space-between;">
  <span>Posljednje PHP greške</span>
  <?php if ($logWritable): ?>
  <form method="post" style="margin:0" onsubmit="return confirm('Obrisati cijeli error log?')">
    <input type="hidden" name="action" value="clear_log">
    <button type="submit" style="background:#e74c3c;color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:12px;cursor:pointer;">
      🗑 Obriši log
    </button>
  </form>
  <?php endif; ?>
</div>
<?php if ($logCleared): ?>
  <p style="color:#2ecc71;font-size:13px;margin-bottom:12px">✓ Log je uspješno obrisan.</p>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:6px">
  <?php foreach ($errorLines as $e): ?>
    <div style="background:#0a0a0a;border:1px solid #2a2a2a;border-radius:8px;padding:10px 14px;display:flex;gap:12px;align-items:flex-start">
      <?php if ($e['age']): ?>
      <span style="flex-shrink:0;background:#2a1a1a;color:#e74c3c;border-radius:5px;padding:2px 8px;font-size:11px;white-space:nowrap;margin-top:1px"><?= htmlspecialchars($e['age']) ?></span>
      <?php endif; ?>
      <code style="font-size:11px;color:#e74c3c;word-break:break-all;line-height:1.5"><?= htmlspecialchars($e['text']) ?></code>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php endif; ?>

</body>
</html>
