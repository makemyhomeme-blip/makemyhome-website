<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: index.php'); exit; }

$categories = [
    'drveni-paneli'    => ['name' => 'Drveni Paneli',    'price' => '86.99', 'unit' => 'kom', 'dims' => '280×122cm'],
    'tekstilni-paneli' => ['name' => 'Tekstilni Paneli', 'price' => '86.99', 'unit' => 'kom', 'dims' => '280×122cm'],
    'mermerni-paneli'  => ['name' => 'Mermerni Paneli',  'price' => '86.99', 'unit' => 'kom', 'dims' => '280×122cm'],
    'metalni-paneli'   => ['name' => 'Metalni Paneli',   'price' => '115.99','unit' => 'kom', 'dims' => '280×122cm'],
    '3d-letvice'       => ['name' => '3D Letvice',       'price' => '19.99', 'unit' => 'kom', 'dims' => '280×16cm'],
    'akusticni-paneli' => ['name' => 'Akustični Paneli', 'price' => '94.99', 'unit' => 'kom', 'dims' => '280×60cm'],
    'bambus-paneli'    => ['name' => 'Bambus Paneli',    'price' => '86.99', 'unit' => 'kom', 'dims' => '280×122cm'],
];

$message = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonFile = '../data/products.json';
    $products = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?? []) : [];
    $nextId   = count($products) > 0 ? max(array_column($products, 'id')) + 1 : 1;
    $saved    = 0;
    $errors   = [];

    $count = count($_POST['sifra'] ?? []);
    for ($i = 0; $i < $count; $i++) {
        $sifra   = trim($_POST['sifra'][$i] ?? '');
        $catId   = $_POST['category'][$i] ?? '';
        $imgFile = $_FILES['image']['name'][$i] ?? '';

        if (empty($sifra) || empty($catId)) continue;
        if (!isset($categories[$catId])) continue;

        $cat = $categories[$catId];
        $sifraSlug = strtolower(str_replace([' ', '/'], '-', $sifra));
        $imgDest   = "../images/products/{$sifraSlug}.jpg";
        $imgPath   = "images/products/{$sifraSlug}.jpg";

        // Handle image upload
        if (!empty($imgFile) && $_FILES['image']['error'][$i] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image']['tmp_name'][$i];
            $ext = strtolower(pathinfo($imgFile, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                if (!is_dir('../images/products')) mkdir('../images/products', 0755, true);
                move_uploaded_file($tmp, $imgDest);
            }
        }

        // Remove existing product with same šifra if exists
        $products = array_values(array_filter($products, fn($p) => strtolower(str_replace([' ','/'],'-', $p['name'])) !== $sifraSlug));

        $products[] = [
            'id'          => $nextId++,
            'name'        => $cat['name'] . ' ' . strtoupper($sifra),
            'category'    => $catId,
            'price'       => $cat['price'],
            'unit'        => $cat['unit'],
            'description' => $cat['name'] . ' ' . strtoupper($sifra) . '. Dimenzije: ' . $cat['dims'] . '.',
            'features'    => ['Dimenzije: ' . $cat['dims'], 'Visoka kvaliteta', 'Jednostavna montaža', 'Šifra: ' . strtoupper($sifra)],
            'image'       => $imgPath,
            'badge'       => null,
            'inStock'     => true,
            'featured'    => false,
        ];
        $saved++;
    }

    file_put_contents($jsonFile, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "✅ Sačuvano $saved proizvoda!";
    if ($errors) { $message .= ' ⚠️ Greške: ' . implode(', ', $errors); $msgType = 'warning'; }
}
?>
<!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dodaj Proizvode | Make My Home Admin</title>
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
    padding: 14px 24px; font-size: 15px; font-weight: 600;
    border-left: 4px solid #28a745; background: #d4edda; color: #155724;
}
.container { padding: 24px; max-width: 1200px; margin: 0 auto; }

/* Product rows */
.products-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.products-table th {
    background: #1a1a1a; color: #c9a86c; padding: 12px 16px;
    text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
}
.products-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.products-table tr:last-child td { border-bottom: none; }
.products-table tr:hover td { background: #faf8f5; }

.img-preview-wrap {
    width: 80px; height: 80px; border: 2px dashed #ddd; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; cursor: pointer; position: relative; flex-shrink: 0;
    background: #f9f9f9;
}
.img-preview-wrap:hover { border-color: #c9a86c; }
.img-preview-wrap img { width: 100%; height: 100%; object-fit: cover; display: none; }
.img-preview-wrap .placeholder { font-size: 11px; color: #aaa; text-align: center; padding: 4px; }
.img-preview-wrap input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }

.sifra-input {
    width: 140px; padding: 8px 12px; border: 1.5px solid #ddd;
    border-radius: 6px; font-size: 14px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 1px;
}
.sifra-input:focus { outline: none; border-color: #c9a86c; }
.sifra-input:not(:placeholder-shown) { border-color: #28a745; background: #f0fff4; }

.cat-select {
    padding: 8px 12px; border: 1.5px solid #ddd; border-radius: 6px;
    font-size: 13px; background: #fff; cursor: pointer; min-width: 160px;
}
.cat-select:focus { outline: none; border-color: #c9a86c; }

.price-badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    background: #f0fff4; color: #28a745; font-size: 12px; font-weight: 700;
    border: 1px solid #c3e6cb;
}

.btn-add-row {
    background: #1a1a1a; color: #c9a86c; border: none; padding: 12px 28px;
    border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;
    margin-top: 16px; display: inline-flex; align-items: center; gap: 8px;
}
.btn-add-row:hover { background: #333; }

.btn-remove { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 18px; padding: 4px 8px; }
.btn-remove:hover { color: #c0392b; }

.row-num { width: 30px; text-align: center; font-size: 13px; color: #888; font-weight: 700; }
</style>
</head>
<body>
<form method="POST" enctype="multipart/form-data">
<div class="header">
    <a href="dashboard.php">← Dashboard</a>
    <h1>📦 Dodaj Proizvode</h1>
    <button type="submit" class="btn-save">💾 Sačuvaj sve</button>
</div>

<?php if ($message): ?>
<div class="message"><?= $message ?></div>
<?php endif; ?>

<div class="container">
    <table class="products-table" id="product-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Slika</th>
                <th>Šifra proizvoda</th>
                <th>Kategorija</th>
                <th>Cijena</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="rows">
            <!-- rows added by JS -->
        </tbody>
    </table>

    <button type="button" class="btn-add-row" onclick="addRow()">
        + Dodaj red
    </button>

    <div style="margin-top:24px;text-align:right;">
        <button type="submit" class="btn-save" style="font-size:16px;padding:14px 40px;">
            💾 Sačuvaj sve proizvode
        </button>
    </div>
</div>
</form>

<script>
const cats = <?= json_encode(array_map(fn($k,$v) => ['id'=>$k,'name'=>$v['name'],'price'=>$v['price'],'dims'=>$v['dims']], array_keys($categories), $categories)) ?>;
let rowCount = 0;

function addRow() {
    const tbody = document.getElementById('rows');
    const idx = rowCount++;
    const tr = document.createElement('tr');
    tr.id = 'row-' + idx;

    const catOptions = cats.map(c =>
        `<option value="${c.id}">${c.name} — ${c.price}€</option>`
    ).join('');

    tr.innerHTML = `
        <td class="row-num">${idx+1}</td>
        <td>
            <div class="img-preview-wrap" id="wrap-${idx}" onclick="document.getElementById('file-${idx}').click()">
                <img id="preview-${idx}" src="" alt="">
                <span class="placeholder" id="ph-${idx}">📷<br>Klikni</span>
                <input type="file" name="image[${idx}]" id="file-${idx}"
                    accept="image/*" onchange="previewImg(this, ${idx})" onclick="event.stopPropagation()">
            </div>
        </td>
        <td>
            <input type="text" name="sifra[${idx}]" class="sifra-input"
                placeholder="npr. DW110" autocomplete="off" spellcheck="false">
        </td>
        <td>
            <select name="category[${idx}]" class="cat-select" onchange="updatePrice(this, ${idx})">
                ${catOptions}
            </select>
        </td>
        <td>
            <span class="price-badge" id="price-${idx}">${cats[0].price}€/kom<br><small>${cats[0].dims}</small></span>
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="removeRow(${idx})">✕</button>
        </td>
    `;
    tbody.appendChild(tr);
}

function previewImg(input, idx) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('preview-' + idx);
            img.src = e.target.result;
            img.style.display = 'block';
            document.getElementById('ph-' + idx).style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updatePrice(sel, idx) {
    const cat = cats.find(c => c.id === sel.value);
    if (cat) {
        document.getElementById('price-' + idx).innerHTML =
            cat.price + '€/kom<br><small>' + cat.dims + '</small>';
    }
}

function removeRow(idx) {
    const row = document.getElementById('row-' + idx);
    if (row) row.remove();
}

// Start with 5 rows
for (let i = 0; i < 5; i++) addRow();
</script>
</body>
</html>
