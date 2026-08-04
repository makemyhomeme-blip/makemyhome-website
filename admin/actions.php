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
/**
 * Karakteristike: jedna po redu. Ako korisnik nije koristio nove redove
 * (stari nacin unosa), tek onda cijepamo po zarezu.
 */
function mmhParseFeatures(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];
    $sep = (strpos($raw, "\n") !== false) ? "\n" : ',';
    $out = array_map('trim', explode($sep, str_replace("\r", '', $raw)));
    return array_values(array_filter($out, fn($x) => $x !== ''));
}

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

/**
 * Napravi (ili osvježi) .webp pored .jpg. Ako WebP nije podržan ili snimanje padne,
 * ukloni postojeći .webp da <picture> nikad ne posluži staru sliku.
 */
function syncWebp($destPath, $gdImage = null, $quality = 82) {
    $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $destPath);
    if ($webpPath === $destPath) return false;
    $made = false;
    if (function_exists('imagewebp')) {
        $img = $gdImage;
        $own = false;
        if (!$img && is_file($destPath)) {
            $info = @getimagesize($destPath);
            if ($info) {
                $img = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($destPath) : @imagecreatefromjpeg($destPath);
                $own = true;
            }
        }
        if ($img) {
            $made = @imagewebp($img, $webpPath, $quality);
            if ($own) imagedestroy($img);
        }
    }
    if (!$made && is_file($webpPath)) @unlink($webpPath);
    return $made;
}

function handleImageUpload($fieldName) {
    // Korisnik nije izabrao novu sliku — zadrži postojeću (nije greška)
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    // Slika JE poslata ali sa greškom — NE laži "sačuvano", prikaži pravu grešku
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])
            ? 'Slika je prevelika za server (maksimum 15 MB). Smanji sliku i pokušaj ponovo.'
            : 'Slika nije uploadovana (greška ' . $file['error'] . '). Pokušaj ponovo.';
        redirect('', $msg);
    }
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) {
        redirect('', 'Slika mora biti JPG, PNG ili WEBP format. Slika nije sačuvana.');
    }
    if ($file['size'] > 15 * 1024 * 1024) {
        redirect('', 'Slika je prevelika (' . round($file['size'] / 1048576, 1) . ' MB). Maksimum je 15 MB — smanji je i pokušaj ponovo.');
    }
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
    redirect('', 'Slika se nije mogla obraditi na serveru. Pokušaj sa JPG slikom manje rezolucije.');
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

        // Cijepamo po NOVOM REDU, ne po zarezu — inace se "Montaza: lijepi se silikonom,
        // sijece se skalpelom" razbijalo u dvije stavke pri svakom snimanju.
        // Stari unosi u jednom redu i dalje rade (fallback na zarez).
        $features = mmhParseFeatures($featuresRaw);

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

        // Ubaci novi proizvod odmah iza zadnjeg proizvoda iste kategorije
        $lastIdx = -1;
        foreach ($products as $i => $p) {
            if ($p['category'] === $category) $lastIdx = $i;
        }
        if ($lastIdx === -1) {
            // Nema proizvoda u toj kategoriji – dodaj na kraj
            $products[] = $newProduct;
        } else {
            array_splice($products, $lastIdx + 1, 0, [$newProduct]);
        }
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

        // Cijepamo po NOVOM REDU, ne po zarezu — inace se "Montaza: lijepi se silikonom,
        // sijece se skalpelom" razbijalo u dvije stavke pri svakom snimanju.
        // Stari unosi u jednom redu i dalje rade (fallback na zarez).
        $features = mmhParseFeatures($featuresRaw);

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
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju.']);
                exit;
            }
            redirect('', 'GREŠKA: Izmjene nisu sačuvane – problem sa diskom ili dozvolama.', 'products');
        }
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'msg' => "Proizvod '{$name}' je uspješno ažuriran!"]);
            exit;
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
        syncWebp($dest);
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
        syncWebp($dest);
        echo json_encode(['ok' => true]); exit;

    case 'upload_decorbox':
        ob_end_clean();
        header('Content-Type: application/json');
        // slot: 'banner' (pored teksta) ili 'fabrika' (sekcija fabrike)
        $slot = $_POST['slot'] ?? '';
        $map  = [
            'banner'  => ['file' => 'decor-box-banner.jpg',  'w' => 1400, 'h' => 1000],
            'fabrika' => ['file' => 'decor-box-fabrika.jpg', 'w' => 1400, 'h' => 1000],
        ];
        if (!isset($map[$slot])) {
            echo json_encode(['ok' => false, 'error' => 'Nepoznat slot.']); exit;
        }
        if (!isset($_FILES['decorbox_image']) || $_FILES['decorbox_image']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['decorbox_image']['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])
                ? 'Slika je prevelika za server (maksimum 15 MB).'
                : 'Nije odabrana slika ili upload nije uspio.';
            echo json_encode(['ok' => false, 'error' => $msg]); exit;
        }
        $file  = $_FILES['decorbox_image'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
            echo json_encode(['ok' => false, 'error' => 'Dozvoljeni formati: JPG, PNG, WEBP.']); exit;
        }
        if ($file['size'] > 15 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Slika je prevelika. Maksimalno 15MB.']); exit;
        }
        $dest  = __DIR__ . '/../images/' . $map[$slot]['file'];
        $saved = optimizeImage($file['tmp_name'], $dest, $map[$slot]['w'], $map[$slot]['h'], 88);
        if (!$saved && !move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['ok' => false, 'error' => 'Snimanje slike nije uspjelo.']); exit;
        }
        syncWebp($dest);
        echo json_encode(['ok' => true, 'file' => 'images/' . $map[$slot]['file']]); exit;

    case 'save_decorbox_style':
        ob_end_clean();
        header('Content-Type: application/json');
        $page = __DIR__ . '/../decor-box.html';
        if (!is_file($page) || !is_writable($page)) {
            echo json_encode(['ok' => false, 'error' => 'decor-box.html nije dostupan za upis.']); exit;
        }
        // banner = slika pored teksta, fabrika = slika u sekciji proizvodnje
        $cfg = [];
        foreach (['banner' => '.db-intro-img', 'fabrika' => '.db-factory-img'] as $slot => $sel) {
            $mode = ($_POST[$slot . '_mode'] ?? 'auto') === 'fixed' ? 'fixed' : 'auto';
            $h    = max(160, min(900, (int)($_POST[$slot . '_height'] ?? 340)));
            $fit  = ($_POST[$slot . '_fit'] ?? 'cover') === 'contain' ? 'contain' : 'cover';
            $cfg[$slot] = ['sel' => $sel, 'mode' => $mode, 'height' => $h, 'fit' => $fit];
        }

        $css = "    /* DB-IMG-SETTINGS-START — ovaj blok mijenja admin (Decor Box Slike) */\n";
        foreach ($cfg as $c) {
            if ($c['mode'] === 'fixed') {
                $css .= "    {$c['sel']}{min-height:{$c['height']}px;}\n";
                $css .= "    {$c['sel']} img{height:{$c['height']}px;object-fit:{$c['fit']};}\n";
            } else {
                $css .= "    {$c['sel']}{min-height:auto;}\n";
                $css .= "    {$c['sel']} img{height:auto;object-fit:{$c['fit']};}\n";
            }
        }
        $css .= "    /* DB-IMG-SETTINGS-END */";

        $html = file_get_contents($page);
        $pattern = '/[ \t]*\/\* DB-IMG-SETTINGS-START.*?DB-IMG-SETTINGS-END \*\//s';
        if (!preg_match($pattern, $html)) {
            echo json_encode(['ok' => false, 'error' => 'Marker blok nije nađen u decor-box.html.']); exit;
        }
        $new = preg_replace($pattern, str_replace('$', '\\$', $css), $html, 1);
        if ($new === null || $new === '' || strlen($new) < strlen($html) - 400) {
            echo json_encode(['ok' => false, 'error' => 'Sigurnosna provjera: izmjena odbijena.']); exit;
        }
        // backup pa upis
        @copy($page, __DIR__ . '/../decor-box.html.bak');
        if (file_put_contents($page, $new) === false) {
            echo json_encode(['ok' => false, 'error' => 'Upis u decor-box.html nije uspio.']); exit;
        }
        @file_put_contents(__DIR__ . '/../data/decor-box-style.json', json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
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

    case 'delete_inquiry':
        ob_end_clean();
        header('Content-Type: application/json');
        $date = trim($_POST['id'] ?? '');
        if (!$date) { echo json_encode(['ok' => false, 'error' => 'Nedostaje ID.']); exit; }
        $inquiriesFile = __DIR__ . '/../data/inquiries.json';
        $inquiries = file_exists($inquiriesFile)
            ? (json_decode(file_get_contents($inquiriesFile), true) ?: [])
            : [];
        $inquiries = array_values(array_filter($inquiries, fn($i) => ($i['date'] ?? '') !== $date));
        file_put_contents($inquiriesFile, json_encode($inquiries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok' => true]); exit;

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

    case 'gallery_reorder':
        ob_end_clean();
        header('Content-Type: application/json');
        $id    = (int)($_POST['id'] ?? 0);
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (!$id || !is_array($order)) {
            echo json_encode(['ok' => false, 'error' => 'Nedostaju podaci.']); exit;
        }
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $p['gallery'] = array_values($order);
                break;
            }
        }
        unset($p);
        if (!saveProducts($products, $productsFile)) {
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju.']); exit;
        }
        echo json_encode(['ok' => true]); exit;

    case 'bulk_discount':
        ob_end_clean();
        header('Content-Type: application/json');
        $categories = json_decode($_POST['categories'] ?? '[]', true);
        $discount   = max(0, min(99, (int)($_POST['discount'] ?? 0)));
        $overwrite  = !empty($_POST['overwrite']);
        if (!is_array($categories) || empty($categories)) {
            echo json_encode(['ok' => false, 'error' => 'Nema odabranih kategorija.']); exit;
        }
        $changed = 0;
        foreach ($products as &$p) {
            if (!in_array($p['category'] ?? '', $categories, true)) continue;
            if (!$overwrite && (int)($p['discount'] ?? 0) > 0) continue;
            $p['discount'] = $discount;
            $changed++;
        }
        unset($p);
        if (!saveProducts($products, $productsFile)) {
            echo json_encode(['ok' => false, 'error' => 'Greška pri snimanju.']); exit;
        }
        $verb = $discount === 0 ? 'Uklonjen popust sa' : "Postavljen {$discount}% popust na";
        echo json_encode(['ok' => true, 'changed' => $changed, 'msg' => "{$verb} {$changed} proizvoda."]);
        exit;

    case 'delete_json_backup':
        ob_end_clean();
        header('Content-Type: application/json');
        $file = basename($_POST['file'] ?? '');
        if (!$file || !preg_match('/^products\.bak\.\d+\.json$/', $file)) {
            echo json_encode(['ok' => false, 'error' => 'Nevažeći naziv fajla.']); exit;
        }
        $path = __DIR__ . '/../data/' . $file;
        if (!file_exists($path)) {
            echo json_encode(['ok' => false, 'error' => 'Fajl ne postoji.']); exit;
        }
        @unlink($path);
        echo json_encode(['ok' => true]);
        exit;

    case 'delete_all_image_backups':
        ob_end_clean();
        header('Content-Type: application/json');
        $backupDir = __DIR__ . '/../images/products-backup/';
        $deleted = 0;
        foreach (glob($backupDir . '*') ?: [] as $f) {
            if (is_file($f)) { @unlink($f); $deleted++; }
        }
        echo json_encode(['ok' => true, 'deleted' => $deleted, 'msg' => "Obrisano {$deleted} backup slika."]);
        exit;

    default:
        redirect('', 'Nepoznata akcija.');
}
