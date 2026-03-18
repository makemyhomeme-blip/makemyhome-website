<?php
/**
 * Make My Home – Admin Actions (dodaj, uredi, obriši proizvode)
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    header('Location: index.php');
    exit;
}

$productsFile = __DIR__ . '/../data/products.json';
$products = json_decode(file_get_contents($productsFile), true) ?: [];

$action = $_POST['action'] ?? '';

function redirect($msg = '', $err = '') {
    $query = '';
    if ($msg) $query = '?msg=' . urlencode($msg);
    if ($err) $query = '?err=' . urlencode($err);
    header('Location: dashboard.php' . $query);
    exit;
}

function saveProducts($products, $file) {
    $json = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json) !== false;
}

function getNextId($products) {
    if (empty($products)) return 1;
    return max(array_column($products, 'id')) + 1;
}

switch ($action) {

    case 'add':
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = trim($_POST['price'] ?? '0');
        $unit        = trim($_POST['unit'] ?? 'm²');
        $description = trim($_POST['description'] ?? '');
        $featuresRaw = trim($_POST['features'] ?? '');
        $image       = trim($_POST['image'] ?? '');
        $badge       = trim($_POST['badge'] ?? '') ?: null;
        $inStock     = !empty($_POST['inStock']);
        $featured    = !empty($_POST['featured']);

        if (empty($name) || empty($category)) {
            redirect('', 'Naziv i kategorija su obavezni.');
        }

        $features = $featuresRaw
            ? array_map('trim', explode(',', $featuresRaw))
            : [];

        $newProduct = [
            'id'          => getNextId($products),
            'name'        => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'category'    => $category,
            'price'       => $price,
            'unit'        => $unit,
            'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
            'features'    => $features,
            'image'       => $image,
            'badge'       => $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null,
            'inStock'     => $inStock,
            'featured'    => $featured
        ];

        $products[] = $newProduct;
        saveProducts($products, $productsFile);
        redirect("Proizvod '{$name}' je uspješno dodat!");
        break;

    case 'edit':
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = trim($_POST['price'] ?? '0');
        $unit        = trim($_POST['unit'] ?? 'm²');
        $description = trim($_POST['description'] ?? '');
        $featuresRaw = trim($_POST['features'] ?? '');
        $image       = trim($_POST['image'] ?? '');
        $badge       = trim($_POST['badge'] ?? '') ?: null;
        $inStock     = !empty($_POST['inStock']);
        $featured    = !empty($_POST['featured']);

        if (empty($name) || !$id) {
            redirect('', 'Nedostaju podaci.');
        }

        $features = $featuresRaw
            ? array_map('trim', explode(',', $featuresRaw))
            : [];

        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $p['name']        = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                $p['category']    = $category;
                $p['price']       = $price;
                $p['unit']        = $unit;
                $p['description'] = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                $p['features']    = $features;
                $p['image']       = $image;
                $p['badge']       = $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null;
                $p['inStock']     = $inStock;
                $p['featured']    = $featured;
                break;
            }
        }
        unset($p);

        saveProducts($products, $productsFile);
        redirect("Proizvod '{$name}' je uspješno ažuriran!");
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $deletedName = '';
        foreach ($products as $p) {
            if ($p['id'] === $id) { $deletedName = $p['name']; break; }
        }
        $products = array_filter($products, fn($p) => $p['id'] !== $id);
        saveProducts($products, $productsFile);
        redirect("Proizvod '{$deletedName}' je obrisan.");
        break;

    default:
        redirect('', 'Nepoznata akcija.');
}
