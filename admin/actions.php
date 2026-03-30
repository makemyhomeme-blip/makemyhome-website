<?php
/**
 * Make My Home – Admin Actions (dodaj, uredi, obriši proizvode)
 */
ob_start(); // buffer any PHP warnings so they don't corrupt JSON responses


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

function backupData($file) {
    if (!file_exists($file)) return;
    $dir     = dirname($file);
    $base    = basename($file, '.json');
    $backups = glob($dir . '/' . $base . '.bak.*.json');
    // Zadrži max 5 backupa, obriši najstarije
    if (count($backups) >= 5) {
        sort($backups);
        foreach (array_slice($backups, 0, count($backups) - 4) as $old) {
            @unlink($old);
        }
    }
    $dest = $dir . '/' . $base . '.bak.' . date('Ymd-His') . '.json';
    @copy($file, $dest);
}

function saveProducts($products, $file) {
    backupData($file);
    $json = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $tmp  = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

function saveCategories($cats, $file) {
    backupData($file);
    $json = json_encode(array_values($cats), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $tmp  = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $file);
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
        if (!saveProducts($products, $productsFile)) {
            redirect('', 'GREŠKA: Proizvod nije sačuvan – problem sa diskom ili dozvolama. Kontaktirajte admina.', 'add-product');
        }
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
                if (!empty($image) && $image !== ($p['image'] ?? '')) {
                    // Obriši staru sliku ako postoji i nije ista
                    $oldImg = $p['image'] ?? '';
                    if ($oldImg && str_starts_with($oldImg, 'images/products/')) {
                        @unlink(__DIR__ . '/../' . $oldImg);
                    }
                    $p['image'] = $image;
                }
                $p['badge']       = $badge ? htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') : null;
                $p['sku']         = $sku ? strtoupper(htmlspecialchars($sku, ENT_QUOTES, 'UTF-8')) : ($p['sku'] ?? null);
                $p['inStock']     = $inStock;
                $p['featured']    = $featured;
                break;
            }
        }
        unset($p);

        if (!saveProducts($products, $productsFile)) {
            redirect('', 'GREŠKA: Izmjene nisu sačuvane – problem sa diskom ili dozvolama.', 'products');
        }
        redirect("Proizvod '{$name}' je uspješno ažuriran!", '', 'products');
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $deletedName = '';
        foreach ($products as $p) {
            if ($p['id'] === $id) { $deletedName = $p['name']; break; }
        }
        $products = array_filter($products, fn($p) => $p['id'] !== $id);
        if (!saveProducts($products, $productsFile)) {
            redirect('', 'GREŠKA: Brisanje nije sačuvano – problem sa diskom ili dozvolama.', 'products');
        }
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
        if (!saveProducts($products, $productsFile)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju – pokušaj ponovo.']);
            exit;
        }
        $newCount = count(array_filter($products, fn($p) => $p['featured'] ?? false));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'featured' => !$currentlyFeatured, 'count' => $newCount]);
        exit;

    case 'toggle_stock':
        $id = (int)($_POST['id'] ?? 0);
        $currentStock = true;
        foreach ($products as $p) {
            if ($p['id'] === $id) { $currentStock = (bool)($p['inStock'] ?? true); break; }
        }
        foreach ($products as &$p) {
            if ($p['id'] === $id) { $p['inStock'] = !$currentStock; break; }
        }
        unset($p);
        if (!saveProducts($products, $productsFile)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju.']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'inStock' => !$currentStock]);
        exit;

    case 'reorder_products':
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (!is_array($ids)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Neispravan format.']);
            exit;
        }
        $indexed = [];
        foreach ($products as $p) { $indexed[$p['id']] = $p; }
        $reordered = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if (isset($indexed[$id])) $reordered[] = $indexed[$id];
        }
        // append any products not in the submitted list (safety)
        foreach ($products as $p) {
            if (!in_array($p['id'], $ids)) $reordered[] = $p;
        }
        if (!saveProducts($reordered, $productsFile)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju.']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
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
        if (!saveProducts($products, $productsFile)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju badge-a – pokušaj ponovo.']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'badge' => $badge]);
        exit;

    case 'upload_category_image':
        ob_end_clean(); // clear buffer, send pure JSON
        ob_start();

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
                // Obriši staru sliku kategorije ako postoji
                $oldImg = $c['image'] ?? '';
                if ($oldImg && str_starts_with($oldImg, 'images/categories/')) {
                    @unlink(__DIR__ . '/../' . $oldImg);
                }
                $c['image'] = $imgPath;
                $c['imagePosition'] = ['posX' => 50.0, 'posY' => 50.0, 'zoom' => 1.0];
                break;
            }
        }
        unset($c);
        if (!saveCategories($cats, $catsFile)) {
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
        if (!saveCategories($cats, $catsFile)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju pozicije – pokušaj ponovo.']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;

    case 'upload_showcase':
        ob_end_clean();
        header('Content-Type: application/json');
        if (!isset($_FILES['showcase_image']) || $_FILES['showcase_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Nije odabrana slika ili upload nije uspio.']);
            exit;
        }
        $file    = $_FILES['showcase_image'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
        if (!in_array($mime, $allowed)) {
            echo json_encode(['ok' => false, 'error' => 'Dozvoljeni formati: JPG, PNG, WEBP.']);
            exit;
        }
        if ($file['size'] > 15 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Slika je prevelika. Maksimalno 15MB.']);
            exit;
        }
        $dest = __DIR__ . '/../images/showcase-room.jpg';
        $saved = optimizeImage($file['tmp_name'], $dest, 1920, 800, 88);
        if (!$saved) {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['ok' => false, 'error' => 'Snimanje slike nije uspjelo.']);
                exit;
            }
        }
        echo json_encode(['ok' => true]);
        exit;

    case 'upload_about':
        ob_end_clean();
        header('Content-Type: application/json');
        if (!isset($_FILES['about_image']) || $_FILES['about_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Nije odabrana slika ili upload nije uspio.']); exit;
        }
        $file  = $_FILES['about_image'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
            echo json_encode(['ok' => false, 'error' => 'Dozvoljeni formati: JPG, PNG, WEBP.']); exit;
        }
        if ($file['size'] > 15 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Slika je prevelika. Maksimalno 15MB.']); exit;
        }
        $dest  = __DIR__ . '/../images/about-showroom.jpg';
        $saved = optimizeImage($file['tmp_name'], $dest, 900, 900, 88);
        if (!$saved && !move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['ok' => false, 'error' => 'Snimanje slike nije uspjelo.']); exit;
        }
        echo json_encode(['ok' => true]); exit;

    case 'upload_hero_slide':
        ob_end_clean();
        header('Content-Type: application/json');
        $slot = intval($_POST['slot'] ?? 0);
        $type = (($_POST['type'] ?? 'desktop') === 'mobile') ? 'mobile' : 'desktop';
        if ($slot < 1 || $slot > 3) {
            echo json_encode(['ok'=>false,'error'=>'Neispravni slot.']); exit;
        }
        if (!isset($_FILES['slide_image']) || $_FILES['slide_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok'=>false,'error'=>'Nije odabrana slika ili upload nije uspio.']); exit;
        }
        $file  = $_FILES['slide_image'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
            echo json_encode(['ok'=>false,'error'=>'Dozvoljeni formati: JPG, PNG, WEBP.']); exit;
        }
        if ($file['size'] > 15*1024*1024) {
            echo json_encode(['ok'=>false,'error'=>'Slika je prevelika. Maksimalno 15MB.']); exit;
        }
        $dir = __DIR__ . '/../images/hero-slides';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = ($type === 'mobile') ? 'slide-' . $slot . '-mobile.jpg' : 'slide-' . $slot . '.jpg';
        $dest  = $dir . '/' . $fname;
        if ($type === 'mobile') {
            $saved = optimizeImage($file['tmp_name'], $dest, 750, 1334, 88);
        } else {
            $saved = optimizeImage($file['tmp_name'], $dest, 1920, 1080, 88);
        }
        if (!$saved) {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['ok'=>false,'error'=>'Snimanje slike nije uspjelo.']); exit;
            }
        }
        $jsonFile = __DIR__ . '/../data/hero-slides.json';
        $slides   = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: []) : [];
        // Migrate old format (array of strings) or ensure 3 slots
        if (!empty($slides) && is_string($slides[0])) {
            $old = $slides; $slides = [[], [], []];
            foreach ($old as $u) {
                if (preg_match('/slide-(\d+)\.jpg$/', $u, $m)) {
                    $s = intval($m[1]) - 1;
                    if ($s >= 0 && $s < 3) $slides[$s]['d'] = $u;
                }
            }
        }
        while (count($slides) < 3) $slides[] = [];
        $url = 'images/hero-slides/' . $fname;
        $key = ($type === 'mobile') ? 'm' : 'd';
        $slides[$slot - 1][$key] = $url;
        file_put_contents($jsonFile, json_encode(array_values($slides), JSON_PRETTY_PRINT));
        // Return preview URL with ../ prefix (admin is one level deeper than root)
        echo json_encode(['ok'=>true, 'url'=>'../' . $url.'?v='.time()]); exit;

    case 'delete_hero_slide':
        ob_end_clean();
        header('Content-Type: application/json');
        $slot = intval($_POST['slot'] ?? 0);
        $type = (($_POST['type'] ?? 'desktop') === 'mobile') ? 'mobile' : 'desktop';
        if ($slot < 1 || $slot > 3) {
            echo json_encode(['ok'=>false,'error'=>'Neispravni slot.']); exit;
        }
        $fname   = ($type === 'mobile') ? 'slide-' . $slot . '-mobile.jpg' : 'slide-' . $slot . '.jpg';
        $imgPath = __DIR__ . '/../images/hero-slides/' . $fname;
        if (file_exists($imgPath)) @unlink($imgPath);
        $jsonFile = __DIR__ . '/../data/hero-slides.json';
        $slides   = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: []) : [];
        while (count($slides) < 3) $slides[] = [];
        $key = ($type === 'mobile') ? 'm' : 'd';
        unset($slides[$slot - 1][$key]);
        file_put_contents($jsonFile, json_encode(array_values($slides), JSON_PRETTY_PRINT));
        echo json_encode(['ok'=>true]); exit;

    case 'gallery_add':
        ob_end_clean();
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if (!$id || empty($_FILES['gallery_image'])) {
            echo json_encode(['ok' => false, 'error' => 'Nedostaju podaci.']); exit;
        }
        $file = $_FILES['gallery_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Upload nije uspio (kod: ' . $file['error'] . ').']); exit;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
            echo json_encode(['ok' => false, 'error' => 'Dozvoljeni formati: JPG, PNG, WEBP.']); exit;
        }
        if ($file['size'] > 8 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Slika je prevelika (max 8MB).']); exit;
        }
        $filename  = 'gallery-' . $id . '-' . time() . '-' . rand(100,999) . '.jpg';
        $uploadDir = __DIR__ . '/../images/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $destPath = $uploadDir . $filename;
        $imgPath  = 'images/products/' . $filename;
        if (!optimizeImage($file['tmp_name'], $destPath, 1200, 900, 82)) {
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                echo json_encode(['ok' => false, 'error' => 'Snimanje slike nije uspjelo.']); exit;
            }
        }
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                if (!isset($p['gallery']) || !is_array($p['gallery'])) $p['gallery'] = [];
                $p['gallery'][] = $imgPath;
                break;
            }
        }
        unset($p);
        if (!saveProducts($products, $productsFile)) {
            @unlink($destPath);
            echo json_encode(['ok' => false, 'error' => 'Slika snimljena ali baza nije upisana.']); exit;
        }
        echo json_encode(['ok' => true, 'path' => $imgPath]); exit;

    case 'gallery_remove':
        ob_end_clean();
        header('Content-Type: application/json');
        $id  = (int)($_POST['id'] ?? 0);
        $img = trim($_POST['img'] ?? '');
        if (!$id || !$img) {
            echo json_encode(['ok' => false, 'error' => 'Nedostaju podaci.']); exit;
        }
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $gallery = $p['gallery'] ?? [];
                $p['gallery'] = array_values(array_filter($gallery, fn($g) => $g !== $img));
                break;
            }
        }
        unset($p);
        // Obriši fajl s diska ako je u images/products/
        if (str_starts_with($img, 'images/products/')) {
            @unlink(__DIR__ . '/../' . $img);
        }
        if (!saveProducts($products, $productsFile)) {
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju.']); exit;
        }
        echo json_encode(['ok' => true]); exit;

    default:
        redirect('', 'Nepoznata akcija.');
}
