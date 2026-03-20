<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: index.php'); exit; }

$productsFile = __DIR__ . '/../data/products.json';
$imagesDir    = __DIR__ . '/../images/products/';
$imagesUrl    = '../images/products/';

$products = file_exists($productsFile) ? (json_decode(file_get_contents($productsFile), true) ?? []) : [];

$categories = [
    'bambus-drveni'    => 'Bambus › Drveni',
    'bambus-tekstilni' => 'Bambus › Tekstilni',
    'bambus-mermerni'  => 'Bambus › Mermerni',
    'bambus-metalni'   => 'Bambus › Metalni',
    'bambus-3d'        => 'Bambus › 3D Letvice',
    'bambus-akusticni' => 'Bambus › Akustični',
    'aluminijum'       => 'Aluminijumske Lajsne',
];

$message = '';

// Save submitted products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['img'])) {
    $nextId = count($products) > 0 ? max(array_column($products, 'id')) + 1 : 1;
    $saved  = 0;

    foreach ($_POST['img'] as $i => $imgPath) {
        $name     = trim($_POST['name'][$i] ?? '');
        $category = trim($_POST['category'][$i] ?? '');
        $price    = trim($_POST['price'][$i] ?? '');
        $unit     = trim($_POST['unit'][$i] ?? 'kom');

        if (empty($name) || empty($category) || empty($price)) continue;

        // Skip if already exists with this image
        $exists = false;
        foreach ($products as $p) {
            if ($p['image'] === $imgPath) { $exists = true; break; }
        }
        if ($exists) continue;

        $products[] = [
            'id'          => $nextId++,
            'name'        => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'category'    => $category,
            'price'       => $price,
            'discount'    => 0,
            'unit'        => $unit,
            'description' => '',
            'features'    => [],
            'image'       => $imgPath,
            'badge'       => null,
            'inStock'     => true,
            'featured'    => true,
        ];
        $saved++;
    }

    file_put_contents($productsFile, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "✅ Sačuvano $saved proizvoda!";

    // Reload products
    $products = json_decode(file_get_contents($productsFile), true) ?? [];
}

// Get all images from server
$allImages = [];
if (is_dir($imagesDir)) {
    foreach (scandir($imagesDir) as $f) {
        if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $f)) {
            $allImages[] = $f;
        }
    }
}

// Find images NOT in any product
$usedImages = array_column($products, 'image');
$orphanImages = [];
foreach ($allImages as $f) {
    $path = 'images/products/' . $f;
    if (!in_array($path, $usedImages)) {
        $orphanImages[] = $f;
    }
}

// Sort by timestamp (newest first)
usort($orphanImages, function($a, $b) {
    preg_match('/product-(\d+)/', $a, $ma);
    preg_match('/product-(\d+)/', $b, $mb);
    $ta = $ma[1] ?? 0;
    $tb = $mb[1] ?? 0;
    return $ta - $tb; // oldest first (order they were added)
});
?>
<!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Oporavak Proizvoda | Admin</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f5f5f5; color: #1a1a1a; }

.header {
    background: #1a1a1a; color: #fff; padding: 16px 24px;
    display: flex; align-items: center; gap: 16px;
    position: sticky; top: 0; z-index: 100;
}
.header h1 { font-size: 18px; font-weight: 700; flex: 1; }
.header a { color: #c9a86c; text-decoration: none; font-size: 13px; }
.btn-save {
    background: #c9a86c; color: #1a1a1a; border: none;
    padding: 10px 28px; border-radius: 8px; font-size: 15px;
    font-weight: 700; cursor: pointer;
}
.btn-save:hover { background: #a8863f; }

.message {
    padding: 16px 24px; font-size: 15px; font-weight: 600;
    background: #d4edda; color: #155724; border-left: 4px solid #28a745;
}

.info-bar {
    padding: 12px 24px; background: #fff3cd; color: #856404;
    font-size: 14px; border-left: 4px solid #ffc107;
}

.container { padding: 24px; }

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.card {
    background: #fff; border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden;
}
.card img {
    width: 100%; height: 180px; object-fit: cover;
    display: block; background: #eee;
}
.card-body { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.card-body input, .card-body select {
    width: 100%; padding: 9px 12px; border: 1px solid #ddd;
    border-radius: 8px; font-size: 14px; font-family: inherit;
}
.card-body input:focus, .card-body select:focus {
    outline: none; border-color: #c9a86c;
}
.row2 { display: grid; grid-template-columns: 2fr 1fr; gap: 8px; }
.img-name { font-size: 11px; color: #999; padding: 4px 0; }

.done-badge {
    background: #28a745; color: #fff; text-align: center;
    padding: 10px; font-weight: 700; font-size: 14px;
}

.no-orphans {
    text-align: center; padding: 60px; color: #666; font-size: 18px;
}

.apply-all {
    background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px;
    padding: 16px 24px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.apply-all label { font-weight: 600; font-size: 14px; }
.apply-all select, .apply-all input {
    padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;
    font-size: 14px;
}
.apply-all button {
    padding: 8px 20px; background: #1a1a1a; color: #fff;
    border: none; border-radius: 6px; cursor: pointer; font-size: 14px;
}
</style>
</head>
<body>

<div class="header">
    <h1>🔄 Oporavak Proizvoda (<?= count($orphanImages) ?> bez podataka)</h1>
    <a href="dashboard.php">← Nazad</a>
    <?php if (!empty($orphanImages)): ?>
    <button class="btn-save" form="recovery-form">💾 Sačuvaj Sve</button>
    <?php endif; ?>
</div>

<?php if ($message): ?>
<div class="message"><?= $message ?></div>
<?php endif; ?>

<?php if (!empty($orphanImages)): ?>
<div class="info-bar">
    📸 Pronađeno <strong><?= count($orphanImages) ?> slika</strong> koje nemaju podatke. Upišite naziv, kategoriju i cijenu za svaku, pa kliknite "Sačuvaj Sve".
</div>

<div class="container">

    <!-- Apply to all -->
    <div class="apply-all">
        <label>Primijeni na sve:</label>
        <select id="bulk-cat">
            <option value="">-- Kategorija --</option>
            <?php foreach ($categories as $val => $label): ?>
            <option value="<?= $val ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" id="bulk-price" placeholder="Cijena €" step="0.01" style="width:120px">
        <select id="bulk-unit">
            <option value="kom">kom</option>
            <option value="m²">m²</option>
            <option value="kom/set">kom/set</option>
        </select>
        <button type="button" onclick="applyAll()">Primijeni na sve ↓</button>
    </div>

    <form id="recovery-form" method="POST">
    <div class="grid">
    <?php foreach ($orphanImages as $i => $file): ?>
        <?php $imgPath = 'images/products/' . $file; ?>
        <div class="card">
            <img src="<?= $imagesUrl . htmlspecialchars($file) ?>" loading="lazy" alt="">
            <input type="hidden" name="img[<?= $i ?>]" value="<?= htmlspecialchars($imgPath) ?>">
            <div class="card-body">
                <div class="img-name"><?= htmlspecialchars($file) ?></div>
                <input type="text" name="name[<?= $i ?>]" placeholder="Naziv proizvoda *" required class="inp-name">
                <select name="category[<?= $i ?>]" required class="inp-cat">
                    <option value="">-- Kategorija * --</option>
                    <?php foreach ($categories as $val => $label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="row2">
                    <input type="number" name="price[<?= $i ?>]" placeholder="Cijena (€) *" step="0.01" min="0" required class="inp-price">
                    <select name="unit[<?= $i ?>]" class="inp-unit">
                        <option value="kom">kom</option>
                        <option value="m²">m²</option>
                        <option value="kom/set">kom/set</option>
                    </select>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    </form>

    <br>
    <?php if (!empty($orphanImages)): ?>
    <div style="text-align:center">
        <button class="btn-save" form="recovery-form" style="padding:14px 48px;font-size:16px">💾 Sačuvaj Sve Proizvode</button>
    </div>
    <?php endif; ?>
</div>

<script>
function applyAll() {
    const cat   = document.getElementById('bulk-cat').value;
    const price = document.getElementById('bulk-price').value;
    const unit  = document.getElementById('bulk-unit').value;
    if (cat)   document.querySelectorAll('.inp-cat').forEach(s => s.value = cat);
    if (price) document.querySelectorAll('.inp-price').forEach(s => s.value = price);
    if (unit)  document.querySelectorAll('.inp-unit').forEach(s => s.value = unit);
}
</script>

<?php else: ?>
<div class="no-orphans">
    ✅ Sve slike imaju proizvode! Nema ništa za oporavak.
</div>
<?php endif; ?>

</body>
</html>
