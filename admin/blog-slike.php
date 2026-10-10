<?php
/**
 * Admin — slike za blog.
 *
 * Blog clanci imaju tacno odredjena mjesta za slike (images/blog/<ime>.jpg).
 * Ovdje vlasnik uploaduje sliku u pravo mjesto bez cPanela — slika se optimizuje,
 * pravi se i .webp, i odmah se pojavi u clanku umjesto crnog okvira.
 */
require_once __DIR__ . '/sesija.php';
if (empty($_SESSION['admin_logged'])) {
    header('Location: index.php');
    exit;
}

$blogDir = __DIR__ . '/../images/blog';
if (!is_dir($blogDir)) @mkdir($blogDir, 0755, true);

// ---- Slotovi (grupisano) ----
$grupe = [
  'Naslovne slike članaka (široki kadar)' => [
    'dekorativni-zidni-paneli-vodic.jpg'      => 'Kompletan vodič — naslovna',
    'kako-izabrati-panele-po-prostoriji.jpg'  => 'Kako izabrati panele — naslovna',
    'pu-kamen-izgled-kamena.jpg'              => 'PU kamen — naslovna',
    'koliko-kostaju-zidni-paneli.jpg'         => 'Cijene panela — naslovna',
    'cesta-pitanja.jpg'                       => 'Česta pitanja — naslovna',
    'paneli-za-kupatilo.jpg'                  => 'Paneli za kupatilo — naslovna',
    'tv-zid.jpg'                              => 'TV zid — naslovna',
    'akusticni-paneli-kancelarija.jpg'        => 'Akustični paneli — naslovna',
    'spc-ili-laminat.jpg'                     => 'SPC ili laminat — naslovna',
    'dostava-crna-gora.jpg'                   => 'Dostava — naslovna',
  ],
  'Slike unutar članaka' => [
    'dekorativni-zidni-paneli-vodic-1.jpg'    => 'Vodič — slika 1 (paneli u enterijeru)',
    'dekorativni-zidni-paneli-vodic-2.jpg'    => 'Vodič — slika 2 (montaža silikonom)',
    'kako-izabrati-panele-po-prostoriji-1.jpg'=> 'Izbor — slika 1 (uređena soba)',
    'pu-kamen-izgled-kamena-1.jpg'            => 'PU kamen — slika 1 (kameni zid)',
    'pu-kamen-izgled-kamena-2.jpg'            => 'PU kamen — slika 2 (reljef izbliza)',
    'koliko-kostaju-zidni-paneli-1.jpg'       => 'Cijene — slika 1 (uzorci)',
  ],
];
$sviSlotovi = array_merge(...array_values($grupe));

// ---- GD optimizacija (isti pristup kao u actions.php) ----
function bs_optimize($tmp, $dest, $maxW = 1600, $maxH = 1000, $q = 85) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    $map = ['image/jpeg'=>'imagecreatefromjpeg','image/jpg'=>'imagecreatefromjpeg',
            'image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'];
    if (!isset($map[$mime])) return false;
    $src = @$map[$mime]($tmp);
    if (!$src) return false;
    $sw = imagesx($src); $sh = imagesy($src);
    $r = min($maxW/$sw, $maxH/$sh, 1.0);
    $dw = (int)round($sw*$r); $dh = (int)round($sh*$r);
    $dst = imagecreatetruecolor($dw, $dh);
    imagecopyresampled($dst, $src, 0,0,0,0, $dw,$dh, $sw,$sh);
    imagedestroy($src);
    $ok = imagejpeg($dst, $dest, $q);
    // .webp blizanac
    if ($ok && function_exists('imagewebp')) {
        $webp = preg_replace('/\.jpg$/i', '.webp', $dest);
        @imagewebp($dst, $webp, $q);
    }
    imagedestroy($dst);
    return $ok;
}
function bs_obrisi($dest) {
    @unlink($dest);
    $webp = preg_replace('/\.jpg$/i', '.webp', $dest);
    if ($webp !== $dest) @unlink($webp);
}

// ---- Obrada (PRG: obradi pa preusmjeri) ----
$poruka = ''; $greska = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot = $_POST['slot'] ?? '';
    if (!isset($sviSlotovi[$slot])) {
        $greska = 'Nepoznato mjesto slike.';
    } elseif (($_POST['akcija'] ?? '') === 'obrisi') {
        bs_obrisi($blogDir . '/' . $slot);
        header('Location: blog-slike.php?ok=obrisano'); exit;
    } elseif (isset($_FILES['slika']) && $_FILES['slika']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['slika'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($f['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
            $greska = 'Dozvoljeni formati: JPG, PNG, WEBP.';
        } elseif ($f['size'] > 15*1024*1024) {
            $greska = 'Slika je prevelika (maks. 15 MB).';
        } elseif (!bs_optimize($f['tmp_name'], $blogDir . '/' . $slot)) {
            $greska = 'Snimanje slike nije uspjelo.';
        } else {
            header('Location: blog-slike.php?ok=snimljeno'); exit;
        }
    } else {
        $greska = 'Nije odabrana slika.';
    }
}
if (($_GET['ok'] ?? '') === 'snimljeno') $poruka = 'Slika je snimljena i objavljena.';
if (($_GET['ok'] ?? '') === 'obrisano')  $poruka = 'Slika je obrisana.';

$koliko = count(array_filter(array_keys($sviSlotovi), fn($s)=>is_file($blogDir.'/'.$s)));
?>
<!DOCTYPE html>
<html lang="sr-ME">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Blog slike — Admin</title>
<style>
  *{box-sizing:border-box;}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f2ee;color:#1a1a1a;margin:0;padding:0 0 60px;}
  header{background:#141210;color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
  header h1{font-size:18px;margin:0;}
  header a{color:#e6cf9f;text-decoration:none;font-size:14px;font-weight:600;}
  .wrap{max-width:1000px;margin:0 auto;padding:0 16px;}
  .uvod{color:#5a5648;font-size:14px;margin:18px 0;line-height:1.6;}
  .poruka{background:#e7f6ec;border:1px solid #a7d8b8;color:#1e6b3a;padding:12px 16px;border-radius:10px;margin:16px 0;font-weight:600;}
  .greska{background:#fdecea;border:1px solid #f1a9a0;color:#a02318;padding:12px 16px;border-radius:10px;margin:16px 0;font-weight:600;}
  h2{font-size:16px;margin:28px 0 12px;padding-bottom:8px;border-bottom:2px solid rgba(201,168,108,0.4);color:#141210;}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}
  .kart{background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;}
  .kart-slika{aspect-ratio:16/10;background:#141210;display:flex;align-items:center;justify-content:center;color:#c9a86c;position:relative;}
  .kart-slika img{width:100%;height:100%;object-fit:cover;display:block;}
  .kart-slika .prazno{font-size:13px;text-align:center;padding:14px;}
  .kart-slika .prazno i{display:block;font-size:26px;margin-bottom:6px;}
  .znak{position:absolute;top:8px;left:8px;background:#2ecc71;color:#fff;font-size:11px;font-weight:800;padding:3px 8px;border-radius:6px;}
  .kart-telo{padding:14px;display:flex;flex-direction:column;gap:10px;flex:1;}
  .kart-telo .naziv{font-weight:700;font-size:14px;}
  .kart-telo .fajl{font-size:11px;color:#8a8570;word-break:break-all;}
  .red{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:auto;}
  input[type=file]{font-size:12px;max-width:170px;}
  button{border:0;border-radius:9px;padding:9px 14px;font-weight:700;font-size:13px;cursor:pointer;}
  .btn-up{background:#c9a86c;color:#1a1a1a;}
  .btn-up:hover{background:#dcbd82;}
  .btn-del{background:#fff;color:#a02318;border:1px solid #e7b7b0;padding:8px 12px;}
  .btn-del:hover{background:#fdecea;}
</style>
</head>
<body>
<header>
  <h1>Blog slike <span style="color:#8a8570;font-weight:400;font-size:13px;">(<?= $koliko ?>/<?= count($sviSlotovi) ?> dodato)</span></h1>
  <a href="dashboard.php">&larr; Nazad na admin</a>
</header>
<div class="wrap">
  <p class="uvod">Ovdje dodaješ slike za blog. Svaka slika ide u tačno svoje mjesto — čim je snimiš, odmah se pojavi u članku umjesto crnog okvira. Najbolje su tvoje fotografije (showroom, realizacije). Format: JPG/PNG/WEBP, do 15 MB; slika se sama smanji i optimizuje.</p>
  <?php if ($poruka): ?><div class="poruka"><?= htmlspecialchars($poruka) ?></div><?php endif; ?>
  <?php if ($greska): ?><div class="greska"><?= htmlspecialchars($greska) ?></div><?php endif; ?>

  <?php foreach ($grupe as $naslov => $slotovi): ?>
    <h2><?= htmlspecialchars($naslov) ?></h2>
    <div class="grid">
      <?php foreach ($slotovi as $fajl => $opis):
        $put = $blogDir . '/' . $fajl;
        $ima = is_file($put);
        $src = 'https://makemyhome.me/images/blog/' . rawurlencode($fajl) . ($ima ? '?t=' . filemtime($put) : '');
      ?>
      <div class="kart">
        <div class="kart-slika">
          <?php if ($ima): ?>
            <span class="znak">✓ dodato</span>
            <img src="<?= $src ?>" alt="<?= htmlspecialchars($opis) ?>">
          <?php else: ?>
            <div class="prazno"><i>🖼️</i>Nema slike</div>
          <?php endif; ?>
        </div>
        <div class="kart-telo">
          <div class="naziv"><?= htmlspecialchars($opis) ?></div>
          <div class="fajl"><?= htmlspecialchars($fajl) ?></div>
          <form method="post" enctype="multipart/form-data" class="red">
            <input type="hidden" name="slot" value="<?= htmlspecialchars($fajl) ?>">
            <input type="file" name="slika" accept="image/*" required>
            <button class="btn-up" type="submit"><?= $ima ? 'Zamijeni' : 'Dodaj' ?></button>
          </form>
          <?php if ($ima): ?>
          <form method="post" onsubmit="return confirm('Obrisati ovu sliku?');">
            <input type="hidden" name="slot" value="<?= htmlspecialchars($fajl) ?>">
            <input type="hidden" name="akcija" value="obrisi">
            <button class="btn-del" type="submit">Obriši</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
