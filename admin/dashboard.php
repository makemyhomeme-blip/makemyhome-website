<?php
/**
 * Make My Home – Admin Dashboard
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    header('Location: index.php');
    exit;
}

$dataDir      = __DIR__ . '/../data/';
$productsFile = $dataDir . 'products.json';
$inquiriesFile = $dataDir . 'inquiries.json';

$products  = json_decode(file_get_contents($productsFile), true) ?: [];
$inquiries = [];
if (file_exists($inquiriesFile)) {
    $inquiries = json_decode(file_get_contents($inquiriesFile), true) ?: [];
    $inquiries = array_reverse($inquiries); // Najnoviji prvi
}

$unread = count(array_filter($inquiries, fn($i) => !$i['read']));
?>
<!DOCTYPE html>
<html lang="bs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Make My Home</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #c9a86c; --dark: #1a1a1a; --gray: #888;
      --light: #f5f0eb; --white: #fff; --danger: #e74c3c; --success: #27ae60;
      --sidebar-w: 260px;
    }
    body { font-family: 'Inter', sans-serif; background: #f0ede8; color: var(--dark); display: flex; min-height: 100vh; }

    /* SIDEBAR */
    .sidebar {
      width: var(--sidebar-w); background: var(--dark); position: fixed;
      top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100;
      display: flex; flex-direction: column;
    }
    .sidebar-header {
      padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08);
      display: flex; align-items: center; gap: 12px;
    }
    .sidebar-logo-icon {
      width: 40px; height: 40px; background: var(--primary); border-radius: 10px;
      display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--dark);
    }
    .sidebar-logo-text .name { font-size: 15px; font-weight: 700; color: #fff; }
    .sidebar-logo-text .sub { font-size: 10px; color: var(--primary); letter-spacing: 1.5px; text-transform: uppercase; }
    .sidebar-nav { flex: 1; padding: 16px 0; }
    .nav-section-label {
      font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px;
      color: rgba(255,255,255,0.3); padding: 12px 20px 6px; font-weight: 600;
    }
    .sidebar-link {
      display: flex; align-items: center; gap: 12px; padding: 12px 20px;
      color: rgba(255,255,255,0.65); text-decoration: none; font-size: 14px;
      font-weight: 500; transition: all .2s; position: relative; cursor: pointer; border: none; background: none; width: 100%; text-align: left;
    }
    .sidebar-link:hover, .sidebar-link.active {
      color: #fff; background: rgba(201,168,108,0.12);
    }
    .sidebar-link.active { color: var(--primary); }
    .sidebar-link i { width: 18px; text-align: center; font-size: 15px; }
    .badge-count {
      margin-left: auto; background: var(--danger); color: #fff;
      font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 100px;
    }
    .sidebar-footer {
      padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.08);
    }

    /* MAIN */
    .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* TOPBAR */
    .topbar {
      background: var(--white); padding: 16px 32px; display: flex; align-items: center;
      justify-content: space-between; box-shadow: 0 1px 10px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 50;
    }
    .topbar-title { font-size: 20px; font-weight: 700; color: var(--dark); }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .topbar-site { font-size: 13px; color: var(--gray); }
    .topbar-site a { color: var(--primary); text-decoration: none; }

    /* CONTENT */
    .content { padding: 32px; flex: 1; }
    .section { display: none; }
    .section.active { display: block; }

    /* STATS */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 20px; margin-bottom: 32px; }
    .stat-card {
      background: var(--white); border-radius: 16px; padding: 24px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px;
    }
    .stat-icon {
      width: 52px; height: 52px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
    }
    .stat-icon.gold { background: rgba(201,168,108,0.15); color: var(--primary); }
    .stat-icon.blue { background: rgba(52,152,219,0.15); color: #3498db; }
    .stat-icon.green { background: rgba(39,174,96,0.15); color: var(--success); }
    .stat-icon.red { background: rgba(231,76,60,0.15); color: var(--danger); }
    .stat-number { font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1; }
    .stat-label { font-size: 13px; color: var(--gray); margin-top: 4px; }

    /* TABLE */
    .card {
      background: var(--white); border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden;
    }
    .card-header {
      padding: 20px 24px; border-bottom: 1px solid rgba(0,0,0,0.06);
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    }
    .card-title { font-size: 16px; font-weight: 700; color: var(--dark); }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th {
      background: #f8f6f3; padding: 12px 16px; text-align: left;
      font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray);
    }
    td { padding: 14px 16px; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px; color: var(--dark); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(201,168,108,0.04); }

    .product-thumb {
      width: 52px; height: 52px; border-radius: 10px; object-fit: cover;
      background: var(--light); display: flex; align-items: center; justify-content: center;
      font-size: 20px; color: var(--gray);
    }
    .product-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }

    .badge {
      padding: 4px 10px; border-radius: 100px; font-size: 11px; font-weight: 600;
    }
    .badge-success { background: rgba(39,174,96,0.1); color: var(--success); }
    .badge-warning { background: rgba(243,156,18,0.1); color: #f39c12; }

    /* BUTTONS */
    .btn {
      padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 500;
      cursor: pointer; border: none; font-family: inherit; display: inline-flex;
      align-items: center; gap: 6px; transition: all .2s; text-decoration: none;
    }
    .btn-primary { background: var(--primary); color: var(--dark); }
    .btn-primary:hover { background: #a8863f; }
    .btn-danger { background: rgba(231,76,60,0.1); color: var(--danger); }
    .btn-danger:hover { background: var(--danger); color: #fff; }
    .btn-sm { padding: 7px 14px; font-size: 12px; }
    .btn-edit { background: rgba(52,152,219,0.1); color: #3498db; }
    .btn-edit:hover { background: #3498db; color: #fff; }

    /* FORM */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 0; }
    .form-group.full { grid-column: 1 / -1; }
    label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
    input, select, textarea {
      width: 100%; padding: 11px 14px; border: 1.5px solid rgba(0,0,0,0.12);
      border-radius: 8px; font-size: 14px; color: var(--dark); outline: none;
      transition: all .2s; font-family: inherit; background: #fff;
    }
    input:focus, select:focus, textarea:focus {
      border-color: var(--primary); box-shadow: 0 0 0 3px rgba(201,168,108,0.15);
    }
    textarea { resize: vertical; min-height: 100px; }

    /* MODAL */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.6); z-index: 1000;
      align-items: center; justify-content: center; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: #fff; border-radius: 20px; width: 100%; max-width: 680px;
      max-height: 90vh; overflow-y: auto; padding: 32px;
    }
    .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
    .modal-title { font-size: 20px; font-weight: 700; color: var(--dark); }
    .modal-close {
      width: 36px; height: 36px; border-radius: 50%; border: none; background: rgba(0,0,0,0.06);
      cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center;
      transition: all .2s;
    }
    .modal-close:hover { background: var(--danger); color: #fff; }
    .form-actions { display: flex; gap: 12px; margin-top: 24px; justify-content: flex-end; }

    .alert {
      padding: 14px 16px; border-radius: 8px; font-size: 14px;
      margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
    }
    .alert-success { background: rgba(39,174,96,0.1); color: var(--success); border: 1px solid rgba(39,174,96,0.3); }
    .alert-error { background: rgba(231,76,60,0.1); color: var(--danger); border: 1px solid rgba(231,76,60,0.3); }

    .inquiry-card {
      background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 3px solid transparent;
    }
    .inquiry-card.unread { border-left-color: var(--primary); }
    .inquiry-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
    .inquiry-name { font-weight: 700; font-size: 15px; color: var(--dark); }
    .inquiry-date { font-size: 12px; color: var(--gray); }
    .inquiry-contact { font-size: 13px; color: var(--gray); margin-bottom: 10px; }
    .inquiry-contact a { color: var(--primary); }
    .inquiry-product { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); margin-bottom: 8px; }
    .inquiry-message { font-size: 14px; color: var(--dark-2); line-height: 1.6; }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); transition: transform .3s; }
      .sidebar.open { transform: translateX(0); }
      .main { margin-left: 0; }
      .content { padding: 20px 16px; }
      .form-grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo-icon"><i class="fas fa-home"></i></div>
    <div class="sidebar-logo-text">
      <div class="name">Make My Home</div>
      <div class="sub">Admin Panel</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Upravljanje</div>
    <button class="sidebar-link active" onclick="showSection('overview')">
      <i class="fas fa-chart-pie"></i> Pregled
    </button>
    <button class="sidebar-link" onclick="showSection('products')">
      <i class="fas fa-box"></i> Proizvodi
    </button>
    <button class="sidebar-link" onclick="showSection('add-product')">
      <i class="fas fa-plus-circle"></i> Dodaj Proizvod
    </button>
    <div class="nav-section-label">Komunikacija</div>
    <button class="sidebar-link" onclick="showSection('inquiries')">
      <i class="fas fa-envelope"></i> Upiti
      <?php if ($unread > 0): ?>
        <span class="badge-count"><?= $unread ?></span>
      <?php endif; ?>
    </button>
    <div class="nav-section-label">Katalog</div>
    <a href="sifre.php" class="sidebar-link">
      <i class="fas fa-barcode"></i> Unos Šifri
    </a>
    <div class="nav-section-label">Web sajt</div>
    <a href="../index.html" target="_blank" class="sidebar-link">
      <i class="fas fa-external-link-alt"></i> Pogledaj Sajt
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-link" style="padding:10px 0;">
      <i class="fas fa-sign-out-alt"></i> Odjava
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
      <button id="sidebar-toggle" style="display:none;border:none;background:none;font-size:20px;cursor:pointer;padding:4px;" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
      </button>
      <div class="topbar-title" id="page-title">Pregled</div>
    </div>
    <div class="topbar-right">
      <div class="topbar-site">
        <a href="../index.html" target="_blank">makemyhome.me <i class="fas fa-external-link-alt"></i></a>
      </div>
      <a href="logout.php" class="btn btn-sm" style="background:rgba(0,0,0,0.06);color:var(--dark);">
        <i class="fas fa-sign-out-alt"></i> Odjavi se
      </a>
    </div>
  </header>

  <div class="content">

    <!-- ALERT -->
    <?php if (!empty($_GET['msg'])): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['err'])): ?>
      <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['err']) ?></div>
    <?php endif; ?>

    <!-- OVERVIEW -->
    <section id="section-overview" class="section active">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon gold"><i class="fas fa-box"></i></div>
          <div>
            <div class="stat-number"><?= count($products) ?></div>
            <div class="stat-label">Ukupno proizvoda</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-tag"></i></div>
          <div>
            <div class="stat-number"><?= count(array_unique(array_column($products, 'category'))) ?></div>
            <div class="stat-label">Kategorija</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-envelope"></i></div>
          <div>
            <div class="stat-number"><?= count($inquiries) ?></div>
            <div class="stat-label">Ukupno upita</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red"><i class="fas fa-bell"></i></div>
          <div>
            <div class="stat-number"><?= $unread ?></div>
            <div class="stat-label">Nepročitanih upita</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Posljednjih 5 upita</span>
          <button class="btn btn-sm btn-primary" onclick="showSection('inquiries')">Svi upiti</button>
        </div>
        <?php if (empty($inquiries)): ?>
          <div style="padding:40px;text-align:center;color:var(--gray);">Nema upita.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Datum</th><th>Ime</th><th>Email</th><th>Proizvod</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($inquiries, 0, 5) as $inq): ?>
                <tr>
                  <td style="white-space:nowrap;"><?= htmlspecialchars(substr($inq['date'], 0, 10)) ?></td>
                  <td><strong><?= htmlspecialchars($inq['name']) ?></strong></td>
                  <td><?= htmlspecialchars($inq['email']) ?></td>
                  <td><?= htmlspecialchars($inq['product'] ?: '—') ?></td>
                  <td>
                    <?php if (!$inq['read']): ?>
                      <span class="badge badge-warning">Novo</span>
                    <?php else: ?>
                      <span class="badge badge-success">Pročitano</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- PRODUCTS -->
    <section id="section-products" class="section">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Svi Proizvodi (<?= count($products) ?>)</span>
          <button class="btn btn-primary" onclick="showSection('add-product')">
            <i class="fas fa-plus"></i> Dodaj Proizvod
          </button>
        </div>
        <?php if (empty($products)): ?>
          <div style="padding:40px;text-align:center;color:var(--gray);">
            <i class="fas fa-box" style="font-size:48px;margin-bottom:16px;display:block;"></i>
            Nema proizvoda. <button class="btn btn-primary" onclick="showSection('add-product')">Dodajte prvi</button>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Slika</th>
                  <th>Naziv</th>
                  <th>Kategorija</th>
                  <th>Cijena</th>
                  <th>Badge</th>
                  <th>Status</th>
                  <th>Akcije</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                  <td>
                    <div class="product-thumb">
                      <img src="../<?= htmlspecialchars($p['image'] ?? '') ?>" alt=""
                        onerror="this.parentElement.innerHTML='<i class=\'fas fa-image\'></i>'">
                    </div>
                  </td>
                  <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                  <td><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $p['category']))) ?></td>
                  <td><?= htmlspecialchars($p['price']) ?> €/<?= htmlspecialchars($p['unit']) ?></td>
                  <td><?= htmlspecialchars($p['badge'] ?? '—') ?></td>
                  <td>
                    <?php if ($p['inStock']): ?>
                      <span class="badge badge-success">Na lageru</span>
                    <?php else: ?>
                      <span class="badge" style="background:rgba(231,76,60,0.1);color:var(--danger);">Nema</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <button class="btn btn-sm btn-edit" onclick="editProduct(<?= $p['id'] ?>)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="POST" action="actions.php" style="margin:0;" onsubmit="return confirm('Obrisati proizvod: <?= htmlspecialchars($p['name']) ?>?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ADD PRODUCT -->
    <section id="section-add-product" class="section">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Dodaj Novi Proizvod</span>
        </div>
        <div style="padding:28px;">
          <form method="POST" action="actions.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
              <div class="form-group">
                <label>Naziv proizvoda *</label>
                <input type="text" name="name" required placeholder="npr. 3D Letvice Premium Oak">
              </div>
              <div class="form-group">
                <label>Kategorija *</label>
                <select name="category" required>
                  <option value="">Odaberi kategoriju</option>
                  <optgroup label="── 3D Letvice ──">
                    <option value="3d-letvice">3D Letvice</option>
                    <option value="3d-letvice-drvene">3D Letvice › Drvene</option>
                    <option value="3d-letvice-mdf">3D Letvice › MDF</option>
                    <option value="3d-letvice-pvc">3D Letvice › PVC</option>
                  </optgroup>
                  <optgroup label="── Bambus Paneli ──">
                    <option value="bambus-drveni">Bambus › Drveni</option>
                    <option value="bambus-tekstilni">Bambus › Tekstilni</option>
                    <option value="bambus-mermerni">Bambus › Mermerni</option>
                    <option value="bambus-metalni">Bambus › Metalni</option>
                    <option value="bambus-kozni">Bambus › Kožni</option>
                  </optgroup>
                  <optgroup label="── Ostalo ──">
                    <option value="akusticni-paneli">Akustični Paneli</option>
                  </optgroup>
                </select>
              </div>
              <div class="form-group">
                <label>Cijena (€) *</label>
                <input type="text" name="price" required placeholder="45.00">
              </div>
              <div class="form-group">
                <label>Jedinica mjere</label>
                <select name="unit">
                  <option value="m²">m²</option>
                  <option value="kom">Komad</option>
                  <option value="m">Metar (m)</option>
                  <option value="pak">Pakovanje</option>
                </select>
              </div>
              <div class="form-group full">
                <label>Opis</label>
                <textarea name="description" placeholder="Opišite proizvod..."></textarea>
              </div>
              <div class="form-group full">
                <label>Karakteristike (odvojite zarezom)</label>
                <input type="text" name="features" placeholder="Prirodno drvo, Laka montaža, UV zaštita, Dimenzije: 270x12x2cm">
              </div>
              <div class="form-group full">
                <label>Slika proizvoda</label>
                <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                  <div style="flex:1;min-width:200px;">
                    <input type="file" name="image_upload" accept="image/*" id="add-img-file"
                      style="padding:8px;background:#f8f6f3;border:2px dashed #c9a86c;cursor:pointer;"
                      onchange="previewImg(this,'add-img-preview')">
                    <div style="font-size:11px;color:#888;margin-top:4px;">JPG, PNG, WEBP — max 5MB</div>
                  </div>
                  <img id="add-img-preview" style="width:80px;height:80px;object-fit:cover;border-radius:8px;display:none;border:2px solid #c9a86c;">
                </div>
              </div>
              <div class="form-group">
                <label>Badge/Oznaka (opciono)</label>
                <input type="text" name="badge" placeholder="Novo, Akcija, Premium...">
              </div>
              <div class="form-group">
                <label>Opcije</label>
                <div style="display:flex;flex-direction:column;gap:10px;padding-top:8px;">
                  <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
                    <input type="checkbox" name="inStock" value="1" checked style="width:auto;padding:0;">
                    Na lageru
                  </label>
                  <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
                    <input type="checkbox" name="featured" value="1" style="width:auto;padding:0;">
                    Prikaži na početnoj (Istaknuto)
                  </label>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button type="button" class="btn" style="background:rgba(0,0,0,0.08);" onclick="showSection('products')">
                Otkaži
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Sačuvaj Proizvod
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <!-- INQUIRIES -->
    <section id="section-inquiries" class="section">
      <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;">
        Upiti kupaca
        <?php if ($unread): ?>
          <span class="badge" style="background:rgba(231,76,60,0.1);color:var(--danger);margin-left:8px;"><?= $unread ?> novih</span>
        <?php endif; ?>
      </h2>
      <?php if (empty($inquiries)): ?>
        <div style="background:#fff;border-radius:16px;padding:60px;text-align:center;color:var(--gray);">
          <i class="fas fa-envelope-open" style="font-size:48px;margin-bottom:16px;display:block;"></i>
          Nema upita od kupaca.
        </div>
      <?php else: ?>
        <?php
        // Označi sve kao pročitano
        $updatedInquiries = array_reverse($inquiries);
        foreach ($updatedInquiries as &$inq) $inq['read'] = true;
        file_put_contents($inquiriesFile, json_encode($updatedInquiries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        ?>
        <?php foreach ($inquiries as $inq): ?>
          <div class="inquiry-card <?= !$inq['read'] ? 'unread' : '' ?>">
            <div class="inquiry-meta">
              <div class="inquiry-name">
                <?php if (!$inq['read']): ?><span style="color:var(--danger);font-size:10px;margin-right:6px;">●</span><?php endif; ?>
                <?= htmlspecialchars($inq['name']) ?>
              </div>
              <div class="inquiry-date"><?= htmlspecialchars($inq['date']) ?></div>
            </div>
            <div class="inquiry-contact">
              <a href="mailto:<?= htmlspecialchars($inq['email']) ?>"><?= htmlspecialchars($inq['email']) ?></a>
              <?php if (!empty($inq['phone'])): ?> · <a href="tel:<?= htmlspecialchars($inq['phone']) ?>"><?= htmlspecialchars($inq['phone']) ?></a><?php endif; ?>
            </div>
            <?php if (!empty($inq['product'])): ?>
              <div class="inquiry-product"><i class="fas fa-box"></i> <?= htmlspecialchars($inq['product']) ?></div>
            <?php endif; ?>
            <div class="inquiry-message"><?= nl2br(htmlspecialchars($inq['message'])) ?></div>
            <div style="margin-top:12px;">
              <a href="mailto:<?= htmlspecialchars($inq['email']) ?>?subject=Re: Upit - <?= urlencode($inq['product'] ?? '') ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-reply"></i> Odgovori
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </div><!-- /content -->
</div><!-- /main -->

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Uredi Proizvod</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <form method="POST" action="actions.php" id="edit-form" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grid">
        <div class="form-group">
          <label>Naziv *</label>
          <input type="text" name="name" id="edit-name" required>
        </div>
        <div class="form-group">
          <label>Kategorija *</label>
          <select name="category" id="edit-category" required>
            <optgroup label="── 3D Letvice ──">
              <option value="3d-letvice">3D Letvice</option>
              <option value="3d-letvice-drvene">3D Letvice › Drvene</option>
              <option value="3d-letvice-mdf">3D Letvice › MDF</option>
              <option value="3d-letvice-pvc">3D Letvice › PVC</option>
            </optgroup>
            <optgroup label="── Bambus Paneli ──">
              <option value="bambus-drveni">Bambus › Drveni</option>
              <option value="bambus-tekstilni">Bambus › Tekstilni</option>
              <option value="bambus-mermerni">Bambus › Mermerni</option>
              <option value="bambus-metalni">Bambus › Metalni</option>
              <option value="bambus-kozni">Bambus › Kožni</option>
            </optgroup>
            <optgroup label="── Ostalo ──">
              <option value="akusticni-paneli">Akustični Paneli</option>
            </optgroup>
          </select>
        </div>
        <div class="form-group">
          <label>Cijena (€)</label>
          <input type="text" name="price" id="edit-price">
        </div>
        <div class="form-group">
          <label>Jedinica</label>
          <select name="unit" id="edit-unit">
            <option value="m²">m²</option>
            <option value="kom">Komad</option>
            <option value="m">Metar</option>
            <option value="pak">Pakovanje</option>
          </select>
        </div>
        <div class="form-group full">
          <label>Opis</label>
          <textarea name="description" id="edit-description"></textarea>
        </div>
        <div class="form-group full">
          <label>Karakteristike (odvojite zarezom)</label>
          <input type="text" name="features" id="edit-features">
        </div>
        <div class="form-group full">
          <label>Slika proizvoda</label>
          <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
              <input type="file" name="image_upload" accept="image/*" id="edit-img-file"
                style="padding:8px;background:#f8f6f3;border:2px dashed #c9a86c;cursor:pointer;"
                onchange="previewImg(this,'edit-img-preview')">
              <div style="font-size:11px;color:#888;margin-top:4px;">Ostavite prazno da zadržite postojeću sliku</div>
            </div>
            <img id="edit-img-preview" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #c9a86c;">
          </div>
          <input type="hidden" name="image" id="edit-image">
        </div>
        <div class="form-group">
          <label>Badge</label>
          <input type="text" name="badge" id="edit-badge">
        </div>
        <div class="form-group">
          <label>Opcije</label>
          <div style="display:flex;flex-direction:column;gap:10px;padding-top:8px;">
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
              <input type="checkbox" name="inStock" id="edit-inStock" value="1" style="width:auto;padding:0;"> Na lageru
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
              <input type="checkbox" name="featured" id="edit-featured" value="1" style="width:auto;padding:0;"> Prikaži istaknuto
            </label>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn" style="background:rgba(0,0,0,0.08);" onclick="closeModal()">Otkaži</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Sačuvaj</button>
      </div>
    </form>
  </div>
</div>

<script>
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;

function showSection(name) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
  document.getElementById('section-' + name).classList.add('active');
  const titles = {
    'overview': 'Pregled', 'products': 'Proizvodi',
    'add-product': 'Dodaj Proizvod', 'inquiries': 'Upiti'
  };
  document.getElementById('page-title').textContent = titles[name] || '';
  event?.target?.classList.add('active');
}

// Otvori sekciju iz URL parametra (npr. nakon dodavanja proizvoda)
(function() {
  const urlParams = new URLSearchParams(window.location.search);
  const section = urlParams.get('section');
  if (section) showSection(section);
})();

function editProduct(id) {
  const p = products.find(x => x.id === id);
  if (!p) return;
  document.getElementById('edit-id').value = p.id;
  document.getElementById('edit-name').value = p.name;
  document.getElementById('edit-category').value = p.category;
  document.getElementById('edit-price').value = p.price;
  document.getElementById('edit-unit').value = p.unit;
  document.getElementById('edit-description').value = p.description || '';
  document.getElementById('edit-features').value = (p.features || []).join(', ');
  document.getElementById('edit-image').value = p.image || '';
  document.getElementById('edit-badge').value = p.badge || '';
  document.getElementById('edit-inStock').checked = p.inStock;
  document.getElementById('edit-featured').checked = p.featured;
  document.getElementById('edit-modal').classList.add('open');
}

function closeModal() {
  document.getElementById('edit-modal').classList.remove('open');
}

document.getElementById('edit-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

function previewImg(input, previewId) {
  const preview = document.getElementById(previewId);
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

</body>
</html>
