<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: index.php'); exit; }

// Category mapping by page number
function getCategory($page) {
    if ($page >= 1 && $page <= 9)   return ['id' => 'drveni-paneli',    'name' => 'Drveni Paneli',    'price' => '89.99'];
    if ($page >= 10 && $page <= 18)  return ['id' => 'tekstilni-paneli', 'name' => 'Tekstilni Paneli', 'price' => '74.99'];
    if ($page >= 19 && $page <= 31)  return ['id' => 'mermerni-paneli',  'name' => 'Mermerni Paneli',  'price' => '94.99'];
    if ($page >= 32 && $page <= 35)  return null; // skip
    if ($page >= 36 && $page <= 39)  return ['id' => 'metalni-paneli',   'name' => 'Metalni Paneli',   'price' => '115.99'];
    if ($page >= 40 && $page <= 67)  return ['id' => '3d-letvice',       'name' => '3D Letvice',       'price' => '45.00'];
    if ($page >= 68 && $page <= 72)  return ['id' => 'akusticni-paneli', 'name' => 'Akustični Paneli', 'price' => '65.00'];
    return null;
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $products = [];
    $jsonFile = '../data/products.json';
    if (file_exists($jsonFile)) {
        $products = json_decode(file_get_contents($jsonFile), true) ?? [];
    }

    // Remove existing catalog products (pages 1-72) to avoid duplicates
    $products = array_filter($products, fn($p) => !isset($p['from_katalog']));
    $products = array_values($products);

    $nextId = count($products) > 0 ? max(array_column($products, 'id')) + 1 : 1;
    $saved = 0;

    foreach ($_POST['sifra'] as $page => $sifra) {
        $sifra = trim($sifra);
        if (empty($sifra)) continue;

        $cat = getCategory((int)$page);
        if (!$cat) continue;

        $pageStr = str_pad($page, 4, '0', STR_PAD_LEFT);
        $srcFile = "../images/katalog/page-{$pageStr}.jpg";
        if (!file_exists($srcFile)) continue;

        $sifraLower = strtolower(str_replace(' ', '-', $sifra));
        $imgDest = "../images/products/{$sifraLower}.jpg";
        copy($srcFile, $imgDest);

        $products[] = [
            'id' => $nextId++,
            'name' => $cat['name'] . ' ' . strtoupper($sifra),
            'category' => $cat['id'],
            'price' => $cat['price'],
            'unit' => 'm²',
            'description' => $cat['name'] . ' ' . strtoupper($sifra) . ' - kvalitetan panel za uređenje interijera.',
            'features' => ['Visoka kvaliteta', 'Jednostavna montaža', 'Šifra: ' . strtoupper($sifra)],
            'image' => "images/products/{$sifraLower}.jpg",
            'badge' => null,
            'inStock' => true,
            'featured' => false,
            'from_katalog' => true
        ];
        $saved++;
    }

    file_put_contents($jsonFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "✅ Sačuvano $saved proizvoda!";
}

// Load existing codes
$existingCodes = [];
$jsonFile = '../data/products.json';
if (file_exists($jsonFile)) {
    $prods = json_decode(file_get_contents($jsonFile), true) ?? [];
    foreach ($prods as $p) {
        if (isset($p['from_katalog']) && $p['from_katalog']) {
            // Extract page from image name - find which page maps to this
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unos Šifri | Make My Home Admin</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f5f5f5; color: #1a1a1a; }
.header {
    background: #1a1a1a; color: #fff; padding: 16px 24px;
    display: flex; align-items: center; gap: 16px; position: sticky; top: 0; z-index: 100;
}
.header h1 { font-size: 18px; font-weight: 700; }
.header a { color: #c9a86c; text-decoration: none; font-size: 13px; }
.btn-save {
    margin-left: auto; background: #c9a86c; color: #1a1a1a;
    border: none; padding: 10px 24px; border-radius: 8px;
    font-size: 15px; font-weight: 700; cursor: pointer;
}
.btn-save:hover { background: #a8863f; }
.message {
    background: #d4edda; color: #155724; padding: 14px 24px;
    font-size: 15px; font-weight: 600; border-left: 4px solid #28a745;
}
.section {
    margin: 20px 24px;
}
.section-title {
    background: #1a1a1a; color: #c9a86c;
    padding: 10px 16px; border-radius: 8px 8px 0 0;
    font-size: 15px; font-weight: 700; letter-spacing: 1px;
}
.section-info {
    background: #fff3cd; padding: 8px 16px;
    font-size: 13px; color: #856404;
    border: 1px solid #ffc107; border-top: none;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    padding: 16px;
    background: #fff;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 8px 8px;
}
.card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    transition: border-color .2s;
}
.card:has(input:not(:placeholder-shown)) {
    border-color: #28a745;
}
.card-img {
    width: 100%; height: 140px;
    object-fit: cover; display: block;
    background: #f0f0f0;
}
.card-body {
    padding: 8px;
}
.page-num {
    font-size: 11px; color: #888; margin-bottom: 4px;
}
.card-body input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.card-body input:focus {
    outline: none;
    border-color: #c9a86c;
}
.card-body input:not(:placeholder-shown) {
    border-color: #28a745;
    background: #f0fff4;
}
.skip-badge {
    background: #f8d7da; color: #721c24;
    padding: 4px 8px; border-radius: 4px;
    font-size: 12px; text-align: center;
}
</style>
</head>
<body>
<form method="POST">
<div class="header">
    <a href="dashboard.php">← Dashboard</a>
    <h1>📋 Unos Šifri Proizvoda</h1>
    <button type="submit" name="save" class="btn-save">💾 Sačuvaj sve</button>
</div>

<?php if ($message): ?>
<div class="message"><?= $message ?></div>
<?php endif; ?>

<?php
$sections = [
    ['title' => '🪵 DRVENI PANELI', 'pages' => range(1, 9),   'info' => 'Stranice 1-9 | Cijena: 89.99€/m²'],
    ['title' => '🧵 TEKSTILNI PANELI', 'pages' => range(10, 18), 'info' => 'Stranice 10-18 | Cijena: 74.99€/m²'],
    ['title' => '🪨 MERMERNI PANELI', 'pages' => range(19, 31), 'info' => 'Stranice 19-31 | Cijena: 94.99€/m²'],
    ['title' => '⚙️ METALNI PANELI', 'pages' => range(36, 39), 'info' => 'Stranice 36-39 | Cijena: 115.99€/m²'],
    ['title' => '📏 3D LETVICE', 'pages' => range(40, 67), 'info' => 'Stranice 40-67 | Cijena: 45.00€/m²'],
    ['title' => '🔊 AKUSTIČNI PANELI', 'pages' => range(68, 72), 'info' => 'Stranice 68-72 | Cijena: 65.00€/m²'],
];

foreach ($sections as $sec):
?>
<div class="section">
    <div class="section-title"><?= $sec['title'] ?></div>
    <div class="section-info"><?= $sec['info'] ?></div>
    <div class="grid">
    <?php foreach ($sec['pages'] as $page):
        $pageStr = str_pad($page, 4, '0', STR_PAD_LEFT);
        $imgPath = "../images/katalog/page-{$pageStr}.jpg";
        $imgExists = file_exists($imgPath);
    ?>
    <div class="card">
        <?php if ($imgExists): ?>
        <img class="card-img" src="../images/katalog/page-<?= $pageStr ?>.jpg" alt="Stranica <?= $page ?>">
        <?php else: ?>
        <div class="card-img" style="display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">Nema slike</div>
        <?php endif; ?>
        <div class="card-body">
            <div class="page-num">Stranica <?= $page ?></div>
            <input type="text" name="sifra[<?= $page ?>]" placeholder="Upiši šifru..."
                   autocomplete="off" autocorrect="off" spellcheck="false">
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<div style="padding:20px 24px;text-align:center;">
    <button type="submit" name="save" class="btn-save" style="font-size:17px;padding:14px 40px;">
        💾 Sačuvaj sve proizvode
    </button>
</div>
</form>
</body>
</html>
