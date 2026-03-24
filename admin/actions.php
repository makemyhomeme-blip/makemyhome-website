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
        $discount    = max(0, min(99, (int)($_POST['discount'] ?? 0)));
        $description = trim($_POST['description'] ?? '');
        $featuresRaw = trim($_POST['features'] ?? '');
        $uploadedImg = handleImageUpload('image_upload');
        $image       = $uploadedImg ?: trim($_POST['image'] ?? '');
        $badge       = trim($_POST['badge'] ?? '') ?: null;
        $sku         = trim($_POST['sku'] ?? '') ?: null;
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
            'discount'    => $discount,
            'unit'        => $unit,
            'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
            'features'    => $features,
            'image'       => $image,
            'badge'       => $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null,
            'sku'         => $sku ? strtoupper(htmlspecialchars($sku, ENT_QUOTES, 'UTF-8')) : null,
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
        $discount    = max(0, min(99, (int)($_POST['discount'] ?? 0)));
        $description = trim($_POST['description'] ?? '');
        $featuresRaw = trim($_POST['features'] ?? '');
        $uploadedImg = handleImageUpload('image_upload');
        $image       = $uploadedImg ?: trim($_POST['image'] ?? '');
        $badge       = trim($_POST['badge'] ?? '') ?: null;
        $sku         = trim($_POST['sku'] ?? '') ?: null;
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
                $p['discount']    = $discount;
                $p['unit']        = $unit;
                $p['description'] = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                $p['features']    = $features;
                if (!empty($image)) $p['image'] = $image; // zadržati staru ako nema novog
                $p['badge']       = $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null;
                $p['sku']         = $sku ? strtoupper(htmlspecialchars($sku, ENT_QUOTES, 'UTF-8')) : ($p['sku'] ?? null);
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

    case 'toggle_featured':
        $id = (int)($_POST['id'] ?? 0);
        $currentlyFeatured = false;
        foreach ($products as $p) {
            if ($p['id'] === $id) { $currentlyFeatured = (bool)($p['featured'] ?? false); break; }
        }
        if (!$currentlyFeatured) {
            $featuredCount = count(array_filter($products, fn($p) => $p['featured'] ?? false));
            if ($featuredCount >= 6) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Maksimalno 6 istaknuta proizvoda. Ukloni jedan pa pokušaj ponovo.']);
                exit;
            }
        }
        foreach ($products as &$p) {
            if ($p['id'] === $id) { $p['featured'] = !$currentlyFeatured; break; }
        }
        unset($p);
        saveProducts($products, $productsFile);
        $newCount = count(array_filter($products, fn($p) => $p['featured'] ?? false));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'featured' => !$currentlyFeatured, 'count' => $newCount]);
        exit;

    case 'set_badge':
        $id = (int)($_POST['id'] ?? 0);
        $badge = trim($_POST['badge'] ?? '') ?: null;
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $p['badge'] = $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null;
                break;
            }
        }
        unset($p);
        saveProducts($products, $productsFile);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'badge' => $badge]);
        exit;

    case 'upload_category_image':
        $catsFile = __DIR__ . '/../data/categories.json';
        $cats     = json_decode(file_get_contents($catsFile), true) ?: [];
        $catId    = trim($_POST['cat_id'] ?? '');
        if (!$catId || empty($_FILES['cat_image']['tmp_name'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Nedostaju podaci.']);
            exit;
        }
        $file    = $_FILES['cat_image'];
        $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed) || $file['size'] > 8 * 1024 * 1024) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Nedozvoljen tip ili veličina fajla.']);
            exit;
        }
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'cat-' . $catId . '-' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../images/categories/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Upload nije uspio.']);
            exit;
        }
        $imgPath = 'images/categories/' . $filename;
        foreach ($cats as &$c) {
            if ($c['id'] === $catId) { $c['image'] = $imgPath; break; }
        }
        unset($c);
        file_put_contents($catsFile, json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'path' => $imgPath]);
        exit;

    case 'save_category_position':
        $catsFile = __DIR__ . '/../data/categories.json';
        $cats     = json_decode(file_get_contents($catsFile), true) ?: [];
        $catId    = trim($_POST['cat_id'] ?? '');
        $tx       = max(-50.0, min(50.0, (float)($_POST['tx']   ?? 0)));
        $ty       = max(-50.0, min(50.0, (float)($_POST['ty']   ?? 0)));
        $zoom     = max(1.0,   min(3.0,  (float)($_POST['zoom'] ?? 1.0)));
        foreach ($cats as &$c) {
            if ($c['id'] === $catId) {
                $c['imagePosition'] = ['tx' => $tx, 'ty' => $ty, 'zoom' => $zoom];
                break;
            }
        }
        unset($c);
        file_put_contents($catsFile, json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;

    default:
        redirect('', 'Nepoznata akcija.');
}
