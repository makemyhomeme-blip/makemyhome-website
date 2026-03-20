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

function redirect($msg = '', $err = '', $section = '') {
    $params = [];
    if ($msg) $params[] = 'msg=' . urlencode($msg);
    if ($err) $params[] = 'err=' . urlencode($err);
    if ($section) $params[] = 'section=' . urlencode($section);
    $query = $params ? '?' . implode('&', $params) : '';
    header('Location: dashboard.php' . $query);
    exit;
}

function saveProducts($products, $file) {
    $json = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json) !== false;
}

function handleImageUpload($fieldName) {
    if (empty($_FILES[$fieldName]['tmp_name'])) return null;
    $file = $_FILES[$fieldName];
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product-' . time() . '-' . rand(100,999) . '.' . strtolower($ext);
    $uploadDir = __DIR__ . '/../images/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return 'images/products/' . $filename;
    }
    return null;
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
        $uploadedImg = handleImageUpload('image_upload');
        $image       = $uploadedImg ?: trim($_POST['image'] ?? '');
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
        redirect("Proizvod '{$name}' je uspješno dodat!", '', 'add-product');
        break;

    case 'edit':
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = trim($_POST['price'] ?? '0');
        $unit        = trim($_POST['unit'] ?? 'm²');
        $description = trim($_POST['description'] ?? '');
        $featuresRaw = trim($_POST['features'] ?? '');
        $uploadedImg = handleImageUpload('image_upload');
        $image       = $uploadedImg ?: trim($_POST['image'] ?? '');
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
                if (!empty($image)) $p['image'] = $image; // zadržati staru ako nema novog
                $p['badge']       = $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null;
                $p['inStock']     = $inStock;
                $p['featured']    = $featured;
                break;
            }
        }
        unset($p);

        saveProducts($products, $productsFile);
        redirect("Proizvod '{$name}' je uspješno ažuriran!", '', 'products');
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $deletedName = '';
        foreach ($products as $p) {
            if ($p['id'] === $id) { $deletedName = $p['name']; break; }
        }
        $products = array_filter($products, fn($p) => $p['id'] !== $id);
        saveProducts($products, $productsFile);
        redirect("Proizvod '{$deletedName}' je obrisan.", '', 'products');
        break;

    default:
        redirect('', 'Nepoznata akcija.');
}
