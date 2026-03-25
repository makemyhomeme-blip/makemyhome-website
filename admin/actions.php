<?php
/**
 * Make My Home – Admin Actions (dodaj, uredi, obriši proizvode)
 */
ob_start(); // buffer any PHP warnings so they don't corrupt JSON responses

// === GLOBAL DEBUG LOG (privremeno) ===
$_glog = __DIR__ . '/../data/actions_debug.log';
$_ginfo  = date('Y-m-d H:i:s') . " | METHOD=" . ($_SERVER['REQUEST_METHOD']??'?');
$_ginfo .= " | action=" . ($_POST['action']??'EMPTY');
$_ginfo .= " | POST_empty=" . (empty($_POST)?'YES':'NO');
$_ginfo .= " | FILES=" . json_encode(array_map(fn($f)=>['name'=>$f['name'],'size'=>$f['size'],'error'=>$f['error']], $_FILES??[]));
$_ginfo .= " | session_ok=" . (!empty($_SESSION['admin_logged'])?'YES':'NO(not_checked_yet)');
$_ginfo .= " | CONTENT_LENGTH=" . ($_SERVER['CONTENT_LENGTH']??'?');
$_ginfo .= "\n";
file_put_contents($_glog, $_ginfo, FILE_APPEND);
// === END DEBUG ===

session_start();
if (empty($_SESSION['admin_logged'])) {
    ob_end_clean();
    header('Location: index.php');
    exit;
}

$productsFile = __DIR__ . '/../data/products.json';
$products = json_decode(file_get_contents($productsFile), true) ?: [];

// If $_POST is empty but it was a POST request, the file exceeded post_max_size
// Return a JSON error immediately so AJAX handlers can show a proper message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && !empty($_SERVER['CONTENT_TYPE'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Fajl je prevelik za server (post_max_size: ' . ini_get('post_max_size') . '). Smanji veličinu slike.']);
    exit;
}

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

/**
 * Smanji i kompresuje sliku na max dimenzije, sačuvaj kao JPEG.
 * Vraća putanju sačuvanog fajla ili false ako ne uspije.
 */
function optimizeImage($tmpPath, $destPath, $maxW = 1200, $maxH = 900, $quality = 82) {
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($tmpPath);
    $gdCreate = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/jpg'  => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    if (!isset($gdCreate[$mimeReal])) return false;
    $src = $gdCreate[$mimeReal]($tmpPath);
    if (!$src) return false;
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    // Izračunaj nove dimenzije zadržavajući proporcije
    $ratio  = min($maxW / $srcW, $maxH / $srcH, 1.0); // ne uvećavaj male slike
    $dstW   = (int)round($srcW * $ratio);
    $dstH   = (int)round($srcH * $ratio);
    $dst    = imagecreatetruecolor($dstW, $dstH);
    // Zadrži prozirnost za PNG
    if ($mimeReal === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);
    $ok = imagejpeg($dst, $destPath, $quality);
    imagedestroy($dst);
    return $ok ? $destPath : false;
}

function handleImageUpload($fieldName) {
    if (empty($_FILES[$fieldName]['tmp_name'])) return null;
    $file    = $_FILES[$fieldName];
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) return null;
    if ($file['size'] > 8 * 1024 * 1024) return null;
    $filename  = 'product-' . time() . '-' . rand(100,999) . '.jpg';
    $uploadDir = __DIR__ . '/../images/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $destPath  = $uploadDir . $filename;
    if (optimizeImage($file['tmp_name'], $destPath, 1200, 900, 82)) {
        return 'images/products/' . $filename;
    }
    // Fallback: sačuvaj original ako GD nije dostupan
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
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
        ob_end_clean(); // clear buffer, send pure JSON
        ob_start();
        // Debug log
        $debugLog = __DIR__ . '/../data/upload_debug.log';
        $debugInfo = date('Y-m-d H:i:s') . "\n";
        $debugInfo .= "POST action: " . ($_POST['action'] ?? 'MISSING') . "\n";
        $debugInfo .= "POST cat_id: " . ($_POST['cat_id'] ?? 'MISSING') . "\n";
        $debugInfo .= "FILES set: " . (isset($_FILES['cat_image']) ? 'YES' : 'NO') . "\n";
        if (isset($_FILES['cat_image'])) {
            $debugInfo .= "FILE error code: " . $_FILES['cat_image']['error'] . "\n";
            $debugInfo .= "FILE size: " . ($_FILES['cat_image']['size'] ?? 0) . " bytes\n";
            $debugInfo .= "FILE name: " . ($_FILES['cat_image']['name'] ?? '') . "\n";
            $debugInfo .= "FILE type: " . ($_FILES['cat_image']['type'] ?? '') . "\n";
        }
        $debugInfo .= "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
        $debugInfo .= "PHP post_max_size: " . ini_get('post_max_size') . "\n";
        $debugInfo .= "---\n";
        file_put_contents($debugLog, $debugInfo, FILE_APPEND);

        $catsFile = __DIR__ . '/../data/categories.json';
        $cats     = json_decode(file_get_contents($catsFile), true) ?: [];
        $catId    = trim($_POST['cat_id'] ?? '');
        if (!$catId) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Nedostaje ID kategorije.']);
            exit;
        }
        if (!isset($_FILES['cat_image']) || $_FILES['cat_image']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['cat_image']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
                $errMsg = 'Fajl je prevelik. Server limit: ' . ini_get('upload_max_filesize') . '. Smanji sliku ispod tog limita.';
            } elseif ($errCode === UPLOAD_ERR_PARTIAL) {
                $errMsg = 'Upload je prekinut. Pokušajte ponovo.';
            } elseif ($errCode === UPLOAD_ERR_NO_FILE) {
                $errMsg = 'Nije odabran fajl.';
            } else {
                $errMsg = 'Upload nije uspio (kod: ' . $errCode . '). Pokušajte ponovo.';
            }
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $errMsg]);
            exit;
        }
        $file = $_FILES['cat_image'];
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($file['tmp_name']);
        $mimeMap  = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($mimeMap[$mimeReal])) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Nedozvoljen format. Dozvoljeni: JPG, PNG, WEBP.']);
            exit;
        }
        if ($file['size'] > 15 * 1024 * 1024) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Fajl je prevelik. Maksimalno 15MB.']);
            exit;
        }
        $filename  = 'cat-' . $catId . '-' . time() . '.jpg';
        $uploadDir = __DIR__ . '/../images/categories/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Ne mogu kreirati upload folder. Kontaktirajte admina.']);
                exit;
            }
        }
        $destPath = $uploadDir . $filename;
        $saved    = optimizeImage($file['tmp_name'], $destPath, 1400, 1050, 82);
        if (!$saved) {
            $ext      = $mimeMap[$mimeReal];
            $filename = 'cat-' . $catId . '-' . time() . '.' . $ext;
            $destPath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Snimanje slike nije uspjelo. Provjeri dozvole foldera.']);
                exit;
            }
        }
        $imgPath = 'images/categories/' . $filename;
        foreach ($cats as &$c) {
            if ($c['id'] === $catId) {
                $c['image'] = $imgPath;
                $c['imagePosition'] = ['posX' => 50.0, 'posY' => 50.0, 'zoom' => 1.0];
                break;
            }
        }
        unset($c);
        $saved = file_put_contents($catsFile, json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($saved === false) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Slika snimljena ali baza nije upisana. Provjeri dozvole data/categories.json.']);
            exit;
        }
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'path' => $imgPath]);
        exit;

    case 'save_category_position':
        $catsFile = __DIR__ . '/../data/categories.json';
        $cats     = json_decode(file_get_contents($catsFile), true) ?: [];
        $catId    = trim($_POST['cat_id'] ?? '');
        $posX     = max(0.0,  min(100.0, (float)($_POST['posX'] ?? 50)));
        $posY     = max(0.0,  min(100.0, (float)($_POST['posY'] ?? 50)));
        $zoom     = max(1.0,  min(3.0,   (float)($_POST['zoom'] ?? 1.0)));
        foreach ($cats as &$c) {
            if ($c['id'] === $catId) {
                $c['imagePosition'] = ['posX' => $posX, 'posY' => $posY, 'zoom' => $zoom];
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
