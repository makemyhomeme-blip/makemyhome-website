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
      #sidebar-toggle { display: block !important; }
      #sidebar-overlay { display: block; }
    }
  </style>
</head>
<body>

<!-- MOBILE OVERLAY -->
<div id="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('open');this.style.display='none';" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;"></div>

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
    <button class="sidebar-link" onclick="showSection('cat-images')">
      <i class="fas fa-images"></i> Slike Kategorija
    </button>
    <button class="sidebar-link" onclick="showSection('showcase-img')">
      <i class="fas fa-image"></i> Showcase Slika
    </button>
    <button class="sidebar-link" onclick="showSection('about-img')">
      <i class="fas fa-store"></i> Slika Radnje (O Nama)
    </button>
    <button class="sidebar-link" onclick="showSection('hero-slides')">
      <i class="fas fa-film"></i> Hero Slike (Slider)
    </button>
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
      <button id="sidebar-toggle" style="display:none;border:none;background:none;font-size:20px;cursor:pointer;padding:4px;" onclick="var s=document.getElementById('sidebar'),o=document.getElementById('sidebar-overlay');s.classList.toggle('open');o.style.display=s.classList.contains('open')?'block':'none';">
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
    <!-- TEST_MARKER_V<?= date('Hi') ?> -->
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
          <?php
            $featuredCount = count(array_filter($products, fn($p) => $p['featured'] ?? false));
          ?>
          <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:10px;gap:10px;">
            <span style="font-size:13px;color:var(--gray);">
              <i class="fas fa-star" style="color:#c9a86c;"></i>
              Istaknuti na početnoj:
              <strong id="featured-counter" style="color:<?= $featuredCount >= 6 ? 'var(--danger)' : 'var(--dark)' ?>;">
                <?= $featuredCount ?>/6
              </strong>
              <span id="featured-max-msg" style="font-size:11px;color:var(--danger);margin-left:4px;<?= $featuredCount >= 6 ? '' : 'display:none;' ?>">— maksimum dostignut</span>
            </span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th style="width:36px;"></th>
                  <th>Slika</th>
                  <th>Naziv</th>
                  <th>Kategorija</th>
                  <th>Cijena</th>
                  <th style="text-align:center;">Istaknuti</th>
                  <th style="text-align:center;">Badge</th>
                  <th>Status</th>
                  <th>Akcije</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $catLabels = [
                  'bambus-drveni'    => ['label'=>'Bambus › Drveni',      'icon'=>'fas fa-tree',         'color'=>'#8B6914'],
                  'bambus-tekstilni' => ['label'=>'Bambus › Tekstilni',   'icon'=>'fas fa-grip-lines',   'color'=>'#7a6a5a'],
                  'bambus-mermerni'  => ['label'=>'Bambus › Mermerni',    'icon'=>'fas fa-gem',          'color'=>'#6b7f8c'],
                  'bambus-metalni'   => ['label'=>'Bambus › Metalni',     'icon'=>'fas fa-circle',       'color'=>'#b08020'],
                  'bambus-kozni'     => ['label'=>'Bambus › Kožni',       'icon'=>'fas fa-couch',        'color'=>'#8B3A1A'],
                  'classic'          => ['label'=>'Bambus › Classic',     'icon'=>'fas fa-border-all',   'color'=>'#5a7a5a'],
                  '3d-letvice'       => ['label'=>'3D Letvice',           'icon'=>'fas fa-layer-group',  'color'=>'#3a6ea8'],
                  'akusticni-paneli' => ['label'=>'Akustični Paneli',     'icon'=>'fas fa-volume-off',   'color'=>'#5a5a8a'],
                  'aluminijum-lajsne'=> ['label'=>'Aluminijum Lajsne',    'icon'=>'fas fa-minus',        'color'=>'#888'],
                  'spc-pod'          => ['label'=>'SPC Pod',              'icon'=>'fas fa-th-large',     'color'=>'#6a8a6a'],
                  'pu-kamen'         => ['label'=>'PU Kamen',             'icon'=>'fas fa-mountain',     'color'=>'#8a7a6a'],
                ];
                $lastCat = null;
                foreach ($products as $p):
                  $isFeatured = !empty($p['featured']);
                  $curBadge   = $p['badge'] ?? null;
                  $badgeOptions = ['Bestseller', 'Najpopularniji', 'Novo', 'Akcija', 'Preporučujemo', 'Limitirano'];
                  $cat = $p['category'] ?? '';
                  if ($cat !== $lastCat):
                    $lastCat = $cat;
                    $cl = $catLabels[$cat] ?? ['label'=>$cat,'icon'=>'fas fa-box','color'=>'#888'];
                ?>
                <tr style="background:<?= $cl['color'] ?>18;border-top:2px solid <?= $cl['color'] ?>33;pointer-events:none;" class="cat-separator-row">
                  <td colspan="9" style="padding:8px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:<?= $cl['color'] ?>;">
                    <i class="<?= $cl['icon'] ?>" style="margin-right:6px;"></i><?= htmlspecialchars($cl['label']) ?>
                  </td>
                </tr>
                <?php endif; ?>
                <tr data-id="<?= $p['id'] ?>" style="<?= $isFeatured ? 'background:rgba(201,168,108,0.06);' : '' ?>">
                  <td class="drag-handle" title="Povuci da promijeniš redosljed" style="cursor:grab;text-align:center;color:#bbb;font-size:16px;user-select:none;">
                    <i class="fas fa-grip-vertical"></i>
                  </td>
                  <td>
                    <div class="product-thumb">
                      <img src="../<?= htmlspecialchars($p['image'] ?? '') ?>" alt=""
                        onerror="this.parentElement.innerHTML='<i class=\'fas fa-image\'></i>'">
                    </div>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                    <?php if (!empty($p['sku'])): ?>
                      <div style="font-size:11px;color:#888;margin-top:2px;font-family:monospace;"><?= htmlspecialchars($p['sku']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $p['category']))) ?></td>
                  <td>
                    <?php if (!empty($p['discount']) && $p['discount'] > 0): ?>
                      <span style="text-decoration:line-through;color:#aaa;font-size:12px;"><?= htmlspecialchars($p['price']) ?> €</span><br>
                      <strong style="color:#e74c3c;"><?= number_format($p['price'] * (1 - $p['discount']/100), 2) ?> €</strong>
                      <span style="font-size:11px;background:#e74c3c;color:#fff;border-radius:4px;padding:1px 5px;margin-left:3px;">-<?= (int)$p['discount'] ?>%</span>
                      /<?= htmlspecialchars($p['unit']) ?>
                    <?php else: ?>
                      <?= htmlspecialchars($p['price']) ?> €/<?= htmlspecialchars($p['unit']) ?>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:center;">
                    <button
                      onclick="toggleFeatured(this, <?= $p['id'] ?>)"
                      data-featured="<?= $isFeatured ? '1' : '0' ?>"
                      title="<?= $isFeatured ? 'Ukloni iz istaknuti' : 'Dodaj u istaknuti' ?>"
                      style="background:none;border:none;cursor:pointer;padding:4px 8px;border-radius:8px;
                             font-size:20px;line-height:1;
                             color:<?= $isFeatured ? '#c9a86c' : '#ccc' ?>;
                             transition:all 0.2s;">
                      <i class="fas fa-star"></i>
                    </button>
                  </td>
                  <td style="text-align:center;">
                    <select
                      onchange="setBadge(this, <?= $p['id'] ?>)"
                      style="font-size:12px;padding:3px 6px;border:1px solid <?= $curBadge ? '#c9a86c' : '#ddd' ?>;
                             border-radius:6px;background:#fff;cursor:pointer;max-width:120px;
                             color:<?= $curBadge ? '#c9a86c' : '#999' ?>;
                             font-weight:<?= $curBadge ? '600' : '400' ?>;">
                      <option value="">— bez oznake</option>
                      <?php foreach ($badgeOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $curBadge === $opt ? 'selected' : '' ?>>
                          <?= htmlspecialchars($opt) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="stock-badge">
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
                      <button class="btn btn-sm" id="stock-btn-<?= $p['id'] ?>"
                        style="background:<?= $p['inStock'] ? 'rgba(39,174,96,0.15)' : 'rgba(231,76,60,0.15)' ?>;color:<?= $p['inStock'] ? '#27ae60' : '#e74c3c' ?>;border:1px solid <?= $p['inStock'] ? '#27ae60' : '#e74c3c' ?>;"
                        onclick="toggleStock(<?= $p['id'] ?>)" title="<?= $p['inStock'] ? 'Označi kao nema na stanju' : 'Označi kao ima na stanju' ?>">
                        <i class="fas <?= $p['inStock'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
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
                  </optgroup>
                  <optgroup label="── Aluminijumske Lajsne ──">
                    <option value="aluminijum-lajsne">Aluminijumske Lajsne</option>
                  </optgroup>
                  <optgroup label="── Bambus Paneli ──">
                    <option value="bambus-drveni">Bambus › Drveni</option>
                    <option value="bambus-tekstilni">Bambus › Tekstilni</option>
                    <option value="bambus-mermerni">Bambus › Mermerni</option>
                    <option value="bambus-metalni">Bambus › Metalni</option>
                    <option value="bambus-kozni">Bambus › Kožni</option>
                    <option value="classic">Bambus › Classic</option>
                  </optgroup>
                  <optgroup label="── Ostalo ──">
                    <option value="akusticni-paneli">Akustični Paneli</option>
                  </optgroup>
                  <optgroup label="── SPC Pod ──">
                    <option value="spc-pod">SPC Pod</option>
                  </optgroup>
                  <optgroup label="── PU Kamen ──">
                    <option value="pu-kamen">PU Kamen</option>
                  </optgroup>
                </select>
              </div>
              <div class="form-group">
                <label>Cijena (€) *</label>
                <input type="text" name="price" id="add-price" required placeholder="45.00" oninput="updateAddDiscount()">
              </div>
              <div class="form-group">
                <label>Popust (%)</label>
                <input type="number" name="discount" id="add-discount" min="0" max="99" placeholder="0 = bez popusta" style="border:2px solid #e74c3c20;" oninput="updateAddDiscount()">
                <div id="add-discount-preview" style="font-size:12px;color:#e74c3c;margin-top:4px;"></div>
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
                <label>Šifra proizvoda (SKU)</label>
                <input type="text" name="sku" placeholder="npr. I3D160CQ006" style="font-family:monospace;">
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
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
              <a href="mailto:<?= htmlspecialchars($inq['email']) ?>?subject=Re: Upit - <?= urlencode($inq['product'] ?? '') ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-reply"></i> Odgovori
              </a>
              <button class="btn btn-sm" onclick="deleteInquiry('<?= htmlspecialchars($inq['date'] ?? '', ENT_QUOTES) ?>', this)"
                style="background:rgba(231,76,60,0.1);color:#e74c3c;border:1px solid rgba(231,76,60,0.2);">
                <i class="fas fa-trash"></i> Obriši
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <!-- SLIKE KATEGORIJA -->
    <section id="section-cat-images" class="section">
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-images" style="color:var(--primary);margin-right:8px;"></i>Slike Kategorija – Početna stranica</span>
        </div>
        <div style="padding:16px 20px;color:var(--gray);font-size:14px;border-bottom:1px solid #f0ede8;">
          Uploadujte sliku za svaku kategoriju i podesite koji dio slike želite prikazati (povucite sliku unutar okvira). Kliknite <strong>Sačuvaj poziciju</strong> nakon što postavite željeni prikaz.
        </div>
      </div>
      <?php
        $catsFile = __DIR__ . '/../data/categories.json';
        $cats = json_decode(file_get_contents($catsFile), true) ?: [];
      ?>
      <style>
        .crop-arrow-btn {
          width:44px;height:44px;border:1px solid #ddd;border-radius:8px;background:#fff;
          cursor:pointer;display:flex;align-items:center;justify-content:center;
          font-size:14px;color:var(--dark);transition:background 0.15s;user-select:none;
          -webkit-user-select:none;touch-action:none;
        }
        .crop-arrow-btn:active { background:#f0ede8; }
      </style>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;">
        <?php foreach ($cats as $cat):
          $img   = $cat['image'] ?? '';
          $pos   = $cat['imagePosition'] ?? [];
          $posX  = $pos['posX'] ?? 50;
          $posY  = $pos['posY'] ?? 50;
          $zoom  = $pos['zoom'] ?? 1.0;
          $catId = htmlspecialchars($cat['id']);
        ?>
        <div class="card" style="overflow:visible;">
          <div class="card-header" style="padding:14px 16px;">
            <span class="card-title" style="font-size:14px;">
              <i class="<?= htmlspecialchars($cat['icon']) ?>" style="color:<?= htmlspecialchars($cat['color']) ?>;margin-right:6px;"></i>
              <?= htmlspecialchars($cat['name']) ?>
            </span>
          </div>
          <div style="padding:16px;">

            <!-- PREVIEW + DRAG -->
            <div class="crop-container" data-cat="<?= $catId ?>"
                 data-img-src="<?= $img ? '../'.htmlspecialchars($img, ENT_QUOTES) : '' ?>"
                 style="width:100%;height:240px;overflow:hidden;border-radius:8px;background:#1a1a1a;position:relative;cursor:<?= $img ? 'grab' : 'default' ?>;border:2px solid #e8e2da;user-select:none;touch-action:none;<?php if ($img): ?>background-image:url('../<?= htmlspecialchars($img, ENT_QUOTES) ?>');background-repeat:no-repeat;background-position:<?= $posX ?>% <?= $posY ?>%;<?php endif; ?>">
              <?php if (!$img): ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,0.3);flex-direction:column;gap:8px;">
                  <i class="fas fa-image" style="font-size:32px;"></i>
                  <span style="font-size:12px;">Nema slike – odaberi ispod</span>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($img): ?>

            <!-- ZOOM -->
            <div style="margin-top:12px;display:flex;align-items:center;gap:6px;">
              <span style="font-size:12px;color:var(--gray);white-space:nowrap;min-width:42px;">Zoom:</span>
              <button type="button" class="crop-arrow-btn" style="width:36px;height:36px;font-size:18px;"
                      onpointerdown="startZoom('<?= $catId ?>',-0.05)" onpointerup="stopCropAction()" onpointerleave="stopCropAction()">−</button>
              <input type="range" class="zoom-slider" min="100" max="300" step="5" value="<?= round($zoom * 100) ?>"
                     style="flex:1;accent-color:var(--primary);" data-cat="<?= $catId ?>">
              <button type="button" class="crop-arrow-btn" style="width:36px;height:36px;font-size:18px;"
                      onpointerdown="startZoom('<?= $catId ?>',0.05)" onpointerup="stopCropAction()" onpointerleave="stopCropAction()">+</button>
              <span class="zoom-val" style="font-size:12px;color:var(--gray);min-width:40px;text-align:right;"><?= round($zoom * 100) ?>%</span>
            </div>

            <!-- ARROWS + COORDINATES -->
            <div style="margin-top:10px;display:flex;align-items:center;gap:14px;">
              <div style="display:grid;grid-template-columns:repeat(3,44px);grid-template-rows:repeat(3,44px);gap:4px;">
                <div></div>
                <button type="button" class="crop-arrow-btn"
                        onpointerdown="startMove('<?= $catId ?>',0,-4)" onpointerup="stopCropAction()" onpointerleave="stopCropAction()">
                  <i class="fas fa-chevron-up"></i></button>
                <div></div>
                <button type="button" class="crop-arrow-btn"
                        onpointerdown="startMove('<?= $catId ?>',-4,0)" onpointerup="stopCropAction()" onpointerleave="stopCropAction()">
                  <i class="fas fa-chevron-left"></i></button>
                <button type="button" class="crop-arrow-btn" style="background:#f8f6f3;" title="Resetuj poziciju"
                        onclick="resetCropPos('<?= $catId ?>')">
                  <i class="fas fa-crosshairs" style="color:var(--primary);"></i></button>
                <button type="button" class="crop-arrow-btn"
                        onpointerdown="startMove('<?= $catId ?>',4,0)" onpointerup="stopCropAction()" onpointerleave="stopCropAction()">
                  <i class="fas fa-chevron-right"></i></button>
                <div></div>
                <button type="button" class="crop-arrow-btn"
                        onpointerdown="startMove('<?= $catId ?>',0,4)" onpointerup="stopCropAction()" onpointerleave="stopCropAction()">
                  <i class="fas fa-chevron-down"></i></button>
                <div></div>
              </div>
              <div style="font-size:12px;color:var(--gray);line-height:2;">
                <div>X: <strong><span class="pos-x-val" data-cat="<?= $catId ?>"><?= round($posX) ?></span>%</strong></div>
                <div>Y: <strong><span class="pos-y-val" data-cat="<?= $catId ?>"><?= round($posY) ?></span>%</strong></div>
                <div style="font-size:11px;color:#bbb;margin-top:2px;">prevuci / 2 prsta za zoom</div>
              </div>
            </div>

            <?php endif; ?>

            <!-- UPLOAD -->
            <form class="cat-img-form" data-cat="<?= $catId ?>" style="margin-top:14px;">
              <input type="hidden" name="action" value="upload_category_image">
              <input type="hidden" name="cat_id" value="<?= htmlspecialchars($cat['id']) ?>">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:#f8f6f3;border:2px dashed #c9a86c;border-radius:8px;padding:10px 12px;font-size:13px;color:var(--dark);">
                <i class="fas fa-upload" style="color:var(--primary);"></i>
                <span><?= $img ? 'Zamijeni sliku (JPG/PNG/WEBP, max 15MB)' : 'Odaberi sliku (JPG/PNG/WEBP, max 15MB)' ?></span>
                <input type="file" name="cat_image" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none;"
                       onchange="handleCatImageUpload(this,'<?= $catId ?>')">
              </label>
            </form>

            <!-- SAVE POSITION BTN -->
            <?php if ($img): ?>
            <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
              <button class="btn btn-primary btn-sm save-pos-btn" data-cat="<?= $catId ?>"
                      style="flex:1;font-size:13px;" onclick="saveCatPosition('<?= htmlspecialchars($cat['id']) ?>')">
                <i class="fas fa-check"></i> Sačuvaj poziciju
              </button>
              <span class="pos-saved-msg" data-cat="<?= $catId ?>"
                    style="font-size:12px;color:var(--success);display:none;">✓ Sačuvano</span>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ===== SHOWCASE SLIKA ===== -->
    <section id="section-showcase-img" class="section">
      <div style="max-width:700px;">
        <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">Showcase Slika – Početna Stranica</h2>
        <p style="color:var(--gray);margin-bottom:28px;">Ovo je velika slika koja se prikazuje na početnoj stranici ispod "Skrolujte". Uploaduj novu sliku da je zamijeniš.</p>

        <?php
        $showcasePath = __DIR__ . '/../images/showcase-room.jpg';
        $showcaseUrl  = '../images/showcase-room.jpg';
        ?>

        <!-- Trenutna slika -->
        <div style="margin-bottom:28px;background:var(--white);border-radius:12px;padding:20px;border:1px solid #eee;">
          <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--gray);font-weight:600;margin-bottom:12px;">Trenutna slika</div>
          <?php if (file_exists($showcasePath)): ?>
            <img src="<?= $showcaseUrl ?>?v=<?= filemtime($showcasePath) ?>"
                 alt="Showcase slika"
                 style="width:100%;border-radius:8px;display:block;">
          <?php else: ?>
            <div style="background:#f5f0eb;border-radius:8px;height:160px;display:flex;align-items:center;justify-content:center;color:var(--gray);">
              <div style="text-align:center;">
                <i class="fas fa-image" style="font-size:36px;margin-bottom:8px;display:block;opacity:.4;"></i>
                Slika još nije uploadovana
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Upload forma -->
        <div style="background:var(--white);border-radius:12px;padding:24px;border:1px solid #eee;">
          <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--gray);font-weight:600;margin-bottom:16px;">Uploaduj novu sliku</div>
          <form id="showcase-upload-form" enctype="multipart/form-data">
            <div id="showcase-drop-zone" style="
              border:2px dashed #ddd;border-radius:10px;padding:40px 20px;text-align:center;
              cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:16px;"
              onclick="document.getElementById('showcase-file-input').click()"
              ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='#fdf8f0';"
              ondragleave="this.style.borderColor='#ddd';this.style.background='';"
              ondrop="handleShowcaseDrop(event)">
              <i class="fas fa-cloud-upload-alt" style="font-size:32px;color:var(--primary);margin-bottom:10px;display:block;"></i>
              <div style="font-weight:600;margin-bottom:4px;">Prevuci sliku ovdje ili klikni</div>
              <div style="font-size:13px;color:var(--gray);">JPG, PNG, WEBP – preporučeno široka slika (min 1400px)</div>
              <input type="file" id="showcase-file-input" accept="image/*" style="display:none" onchange="previewShowcase(this)">
            </div>

            <div id="showcase-preview-wrap" style="display:none;margin-bottom:16px;">
              <img id="showcase-preview-img" style="width:100%;border-radius:8px;display:block;">
            </div>

            <button type="button" id="showcase-upload-btn" onclick="uploadShowcase()" style="
              display:none;width:100%;padding:14px;background:var(--primary);color:var(--dark);
              border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;">
              <i class="fas fa-upload"></i> Sačuvaj na sajt
            </button>

            <div id="showcase-msg" style="display:none;margin-top:14px;padding:12px 16px;border-radius:8px;font-weight:600;"></div>
          </form>
        </div>
      </div>
    </section>

    <!-- ===== ABOUT IMAGE ===== -->
    <section id="section-about-img" class="section">
      <div style="max-width:700px;">
        <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">Slika Radnje – Sekcija "O Nama"</h2>
        <p style="color:var(--gray);margin-bottom:28px;">Ovo je slika koja se prikazuje u sekciji "Vaš Partner za Ljepši Dom" na početnoj stranici. Uploaduj fotografiju radnje ili showrooma.</p>

        <?php
        $aboutPath = __DIR__ . '/../images/about-showroom.jpg';
        $aboutUrl  = '../images/about-showroom.jpg';
        ?>

        <div style="margin-bottom:28px;background:var(--white);border-radius:12px;padding:20px;border:1px solid #eee;">
          <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--gray);font-weight:600;margin-bottom:12px;">Trenutna slika</div>
          <?php if (file_exists($aboutPath)): ?>
            <img src="<?= $aboutUrl ?>?v=<?= filemtime($aboutPath) ?>"
                 id="about-current-img" alt="Slika radnje"
                 style="width:100%;border-radius:8px;display:block;">
          <?php else: ?>
            <div id="about-current-img" style="background:#f5f0eb;border-radius:8px;height:200px;display:flex;align-items:center;justify-content:center;color:var(--gray);">
              <div style="text-align:center;">
                <i class="fas fa-store" style="font-size:48px;margin-bottom:8px;display:block;opacity:.3;"></i>
                Slika još nije uploadovana
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div style="background:var(--white);border-radius:12px;padding:24px;border:1px solid #eee;">
          <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--gray);font-weight:600;margin-bottom:16px;">Uploaduj novu sliku</div>
          <form id="about-upload-form" enctype="multipart/form-data">
            <div id="about-drop-zone" style="
              border:2px dashed #ddd;border-radius:10px;padding:40px 20px;text-align:center;
              cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:16px;"
              onclick="document.getElementById('about-file-input').click()"
              ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='#fdf8f0';"
              ondragleave="this.style.borderColor='#ddd';this.style.background='';"
              ondrop="handleAboutDrop(event)">
              <i class="fas fa-cloud-upload-alt" style="font-size:32px;color:var(--primary);margin-bottom:10px;display:block;"></i>
              <div style="font-weight:600;margin-bottom:4px;">Prevuci sliku ovdje ili klikni</div>
              <div style="font-size:13px;color:var(--gray);">JPG, PNG, WEBP – preporučeno kvadratna ili portret fotografija</div>
              <input type="file" id="about-file-input" accept="image/*" style="display:none" onchange="previewAbout(this)">
            </div>
            <div id="about-preview-wrap" style="display:none;margin-bottom:16px;">
              <img id="about-preview-img" style="width:100%;border-radius:8px;display:block;">
            </div>
            <button type="button" id="about-upload-btn" onclick="uploadAbout()" style="
              display:none;width:100%;padding:14px;background:var(--primary);color:var(--dark);
              border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;">
              <i class="fas fa-upload"></i> Sačuvaj na sajt
            </button>
            <div id="about-msg" style="display:none;margin-top:14px;padding:12px 16px;border-radius:8px;font-weight:600;"></div>
          </form>
        </div>
      </div>
    </section>

    <!-- ===== HERO SLIDES ===== -->
    <section id="section-hero-slides" class="section">
      <div style="max-width:860px;">
        <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">Hero Slike – Slider na Početnoj</h2>
        <p style="color:var(--gray);margin-bottom:28px;">
          Slike se automatski izmjenjuju svakih 5 sekundi. Možeš uploadovati posebne slike za desktop i mobilne uređaje.<br>
          Ako ne dodaš mobilnu verziju, prikazuje se desktop slika i na telefonu.
        </p>

        <?php
        $slidesJson = __DIR__ . '/../data/hero-slides.json';
        $slideDir   = __DIR__ . '/../images/hero-slides';
        $slides     = file_exists($slidesJson) ? (json_decode(file_get_contents($slidesJson), true) ?: []) : [];
        // Migrate old string format if needed
        if (!empty($slides) && is_string($slides[0])) {
            $old = $slides; $slides = [];
            foreach ($old as $u) {
                if (preg_match('/slide-(\d+)\.jpg$/', $u, $m)) {
                    $s = intval($m[1]) - 1;
                    if ($s >= 0 && $s < 3) { while(count($slides) <= $s) $slides[] = []; $slides[$s]['d'] = $u; }
                }
            }
        }
        while (count($slides) < 3) $slides[] = [];
        ?>

        <!-- DESKTOP -->
        <div style="margin-bottom:36px;">
          <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;">
            <i class="fas fa-desktop" style="color:var(--primary);margin-right:6px;"></i>Desktop verzija
          </h3>
          <p style="color:var(--gray);font-size:13px;margin-bottom:16px;">
            Preporučena razmjera: <strong>16:9 (npr. 1920×1080px)</strong>
          </p>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
          <?php for ($slot = 1; $slot <= 3; $slot++):
            $dUrl    = $slides[$slot-1]['d'] ?? null;
            $imgPath = $dUrl ? (__DIR__ . '/../' . $dUrl) : null;
            $exists  = $imgPath && file_exists($imgPath);
            $preview = $exists ? ('../' . $dUrl . '?v=' . filemtime($imgPath)) : null;
          ?>
            <div id="hs-slot-<?= $slot ?>" style="background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
              <div style="position:relative;background:#f5f0eb;height:140px;display:flex;align-items:center;justify-content:center;">
                <?php if ($exists): ?>
                  <img src="<?= $preview ?>" id="hs-preview-<?= $slot ?>" alt="Slajd <?= $slot ?>"
                       style="width:100%;height:140px;object-fit:cover;display:block;">
                <?php else: ?>
                  <div id="hs-preview-<?= $slot ?>" style="text-align:center;color:#bbb;">
                    <i class="fas fa-image" style="font-size:32px;margin-bottom:6px;display:block;opacity:.4;"></i>
                    <span style="font-size:12px;">Prazno</span>
                  </div>
                <?php endif; ?>
                <div style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,.5);color:#fff;
                  font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;">Slajd <?= $slot ?></div>
              </div>
              <div style="padding:14px;display:flex;gap:8px;">
                <button onclick="hsUpload(<?= $slot ?>, 'desktop')"
                  style="flex:1;background:var(--primary);color:var(--dark);border:none;border-radius:8px;
                    padding:9px 0;font-size:13px;font-weight:700;cursor:pointer;">
                  <i class="fas fa-upload"></i> Uploaduj
                </button>
                <button id="hs-del-<?= $slot ?>" onclick="hsDelete(<?= $slot ?>, 'desktop')"
                  style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;
                    padding:9px 12px;font-size:13px;font-weight:700;cursor:pointer;<?= $exists ? '' : 'display:none;' ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
              <input type="file" id="hs-file-<?= $slot ?>" accept="image/*"
                style="display:none" onchange="hsSubmit(<?= $slot ?>, this, 'desktop')">
            </div>
          <?php endfor; ?>
          </div>
        </div>

        <!-- MOBILE -->
        <div>
          <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;">
            <i class="fas fa-mobile-alt" style="color:var(--primary);margin-right:6px;"></i>Mobilna verzija
          </h3>
          <p style="color:var(--gray);font-size:13px;margin-bottom:16px;">
            Preporučena razmjera: <strong>9:16 (npr. 750×1334px)</strong> – portrait orijentacija.<br>
            Ako ne dodaš mobilnu sliku, prikazuje se desktop verzija.
          </p>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
          <?php for ($slot = 1; $slot <= 3; $slot++):
            $mUrl    = $slides[$slot-1]['m'] ?? null;
            $imgPath = $mUrl ? (__DIR__ . '/../' . $mUrl) : null;
            $exists  = $imgPath && file_exists($imgPath);
            $preview = $exists ? ('../' . $mUrl . '?v=' . filemtime($imgPath)) : null;
          ?>
            <div id="hs-slot-<?= $slot ?>-m" style="background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
              <div style="position:relative;background:#f5f0eb;height:140px;display:flex;align-items:center;justify-content:center;">
                <?php if ($exists): ?>
                  <img src="<?= $preview ?>" id="hs-preview-<?= $slot ?>-m" alt="Mob <?= $slot ?>"
                       style="width:100%;height:140px;object-fit:cover;display:block;">
                <?php else: ?>
                  <div id="hs-preview-<?= $slot ?>-m" style="text-align:center;color:#bbb;">
                    <i class="fas fa-image" style="font-size:32px;margin-bottom:6px;display:block;opacity:.4;"></i>
                    <span style="font-size:12px;">Prazno</span>
                  </div>
                <?php endif; ?>
                <div style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,.5);color:#fff;
                  font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;">Mob <?= $slot ?></div>
              </div>
              <div style="padding:14px;display:flex;gap:8px;">
                <button onclick="hsUpload(<?= $slot ?>, 'mobile')"
                  style="flex:1;background:var(--primary);color:var(--dark);border:none;border-radius:8px;
                    padding:9px 0;font-size:13px;font-weight:700;cursor:pointer;">
                  <i class="fas fa-upload"></i> Uploaduj
                </button>
                <button id="hs-del-<?= $slot ?>-m" onclick="hsDelete(<?= $slot ?>, 'mobile')"
                  style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;
                    padding:9px 12px;font-size:13px;font-weight:700;cursor:pointer;<?= $exists ? '' : 'display:none;' ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
              <input type="file" id="hs-file-<?= $slot ?>-m" accept="image/*"
                style="display:none" onchange="hsSubmit(<?= $slot ?>, this, 'mobile')">
            </div>
          <?php endfor; ?>
          </div>
        </div>

      </div>
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
            </optgroup>
            <optgroup label="── Aluminijumske Lajsne ──">
              <option value="aluminijum-lajsne">Aluminijumske Lajsne</option>
            </optgroup>
            <optgroup label="── Bambus Paneli ──">
              <option value="bambus-drveni">Bambus › Drveni</option>
              <option value="bambus-tekstilni">Bambus › Tekstilni</option>
              <option value="bambus-mermerni">Bambus › Mermerni</option>
              <option value="bambus-metalni">Bambus › Metalni</option>
              <option value="bambus-kozni">Bambus › Kožni</option>
              <option value="classic">Bambus › Classic</option>
            </optgroup>
            <optgroup label="── Ostalo ──">
              <option value="akusticni-paneli">Akustični Paneli</option>
            </optgroup>
            <optgroup label="── SPC Pod ──">
              <option value="spc-pod">SPC Pod</option>
            </optgroup>
            <optgroup label="── PU Kamen ──">
              <option value="pu-kamen">PU Kamen</option>
            </optgroup>
          </select>
        </div>
        <div class="form-group">
          <label>Cijena (€)</label>
          <input type="text" name="price" id="edit-price" oninput="updateEditDiscount()">
        </div>
        <div class="form-group">
          <label>Popust (%)</label>
          <input type="number" name="discount" id="edit-discount" min="0" max="99" placeholder="0 = bez popusta" style="border:2px solid #e74c3c20;" oninput="updateEditDiscount()">
          <div id="edit-discount-preview" style="font-size:12px;color:#e74c3c;margin-top:4px;"></div>
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
        <!-- Galerija -->
        <div class="form-group full">
          <label>Galerija slika <span style="font-weight:400;color:#888;font-size:12px;">(prostorije, detalji…)</span></label>
          <div id="gallery-grid" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;min-height:60px;"></div>
          <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:9px 16px;background:#f8f6f3;border:2px dashed #c9a86c;border-radius:8px;font-size:13px;color:#c9a86c;font-weight:600;transition:background .2s;"
            onmouseenter="this.style.background='#f0e8d8'" onmouseleave="this.style.background='#f8f6f3'">
            <i class="fas fa-plus-circle"></i> Dodaj sliku
            <input type="file" accept="image/*" multiple style="display:none;" onchange="uploadGalleryImages(this)">
          </label>
          <div id="gallery-upload-status" style="font-size:12px;margin-top:6px;color:#888;"></div>
        </div>

        <div class="form-group">
          <label>Šifra proizvoda (SKU)</label>
          <input type="text" name="sku" id="edit-sku" placeholder="npr. I3D160CQ006" style="font-family:monospace;">
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
    'add-product': 'Dodaj Proizvod', 'inquiries': 'Upiti',
    'cat-images': 'Slike Kategorija'
  };
  document.getElementById('page-title').textContent = titles[name] || '';
  event?.target?.classList.add('active');
  // Zatvori sidebar na mobilnom nakon klika
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (sidebar) sidebar.classList.remove('open');
  if (overlay) overlay.style.display = 'none';
}

// Otvori sekciju iz URL parametra (npr. nakon dodavanja proizvoda)
(function() {
  const urlParams = new URLSearchParams(window.location.search);
  const section = urlParams.get('section');
  if (section) showSection(section);
})();

let _editingProductId = null;

function editProduct(id) {
  const p = products.find(x => x.id === id);
  if (!p) return;
  _editingProductId = id;
  document.getElementById('edit-id').value = p.id;
  document.getElementById('edit-name').value = p.name;
  document.getElementById('edit-category').value = p.category;
  document.getElementById('edit-price').value = p.price;
  document.getElementById('edit-discount').value = p.discount || 0;
  document.getElementById('edit-unit').value = p.unit;
  document.getElementById('edit-description').value = p.description || '';
  document.getElementById('edit-features').value = (p.features || []).join(', ');
  document.getElementById('edit-image').value = p.image || '';
  document.getElementById('edit-sku').value = p.sku || '';
  document.getElementById('edit-badge').value = p.badge || '';
  document.getElementById('edit-inStock').checked = p.inStock;
  document.getElementById('edit-featured').checked = p.featured;
  // Prikaži galeriju
  const gallery = p.gallery || [];
  renderGalleryGrid(gallery);
  document.getElementById('gallery-upload-status').textContent = '';
  // Prikaži preview postojeće slike
  const prev = document.getElementById('edit-img-preview');
  if (p.image) { prev.src = '../' + p.image; prev.style.display = 'block'; }
  else prev.style.display = 'none';
  updateEditDiscount();
  document.getElementById('edit-modal').classList.add('open');
}

function renderGalleryGrid(gallery) {
  const grid = document.getElementById('gallery-grid');
  if (!gallery || !gallery.length) {
    grid.innerHTML = '<span style="color:#bbb;font-size:12px;align-self:center;">Nema dodatnih slika</span>';
    return;
  }
  grid.innerHTML = gallery.map(img => `
    <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
      <img src="../${img}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #e8e2da;display:block;">
      <button type="button" onclick="removeGalleryImage('${img}')"
        style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#e74c3c;color:#fff;border:none;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;line-height:1;">✕</button>
    </div>
  `).join('');
}

async function uploadGalleryImages(input) {
  const files = Array.from(input.files);
  if (!files.length || !_editingProductId) return;
  const status = document.getElementById('gallery-upload-status');
  status.textContent = 'Uploading…';
  let uploaded = 0;
  // Find current product and its gallery
  const p = products.find(x => x.id === _editingProductId);
  if (!p) return;
  if (!p.gallery) p.gallery = [];
  for (const file of files) {
    const fd = new FormData();
    fd.append('action', 'gallery_add');
    fd.append('id', _editingProductId);
    fd.append('gallery_image', file);
    try {
      const r = await fetch('actions.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        p.gallery.push(j.path);
        uploaded++;
      } else {
        status.textContent = 'Greška: ' + (j.error || 'Upload nije uspio');
      }
    } catch(e) {
      status.textContent = 'Greška pri uploadu.';
    }
  }
  renderGalleryGrid(p.gallery);
  if (uploaded) status.textContent = uploaded + ' slika dodato ✓';
  input.value = '';
}

async function removeGalleryImage(imgPath) {
  if (!_editingProductId) return;
  if (!confirm('Obriši ovu sliku iz galerije?')) return;
  const fd = new FormData();
  fd.append('action', 'gallery_remove');
  fd.append('id', _editingProductId);
  fd.append('img', imgPath);
  const r = await fetch('actions.php', { method: 'POST', body: fd });
  const j = await r.json();
  if (j.ok) {
    const p = products.find(x => x.id === _editingProductId);
    if (p) p.gallery = (p.gallery || []).filter(g => g !== imgPath);
    renderGalleryGrid(p ? (p.gallery || []) : []);
  }
}

function updateEditDiscount() {
  const price = parseFloat(document.getElementById('edit-price').value) || 0;
  const disc = parseInt(document.getElementById('edit-discount').value) || 0;
  const preview = document.getElementById('edit-discount-preview');
  if (disc > 0 && price > 0) {
    const sale = (price * (1 - disc / 100)).toFixed(2);
    preview.innerHTML = `<s>${price.toFixed(2)} €</s> → <strong>${sale} €</strong> (ušteda ${disc}%)`;
  } else {
    preview.innerHTML = '';
  }
}

function updateAddDiscount() {
  const price = parseFloat(document.getElementById('add-price').value) || 0;
  const disc = parseInt(document.getElementById('add-discount').value) || 0;
  const preview = document.getElementById('add-discount-preview');
  if (disc > 0 && price > 0) {
    const sale = (price * (1 - disc / 100)).toFixed(2);
    preview.innerHTML = `<s>${price.toFixed(2)} €</s> → <strong>${sale} €</strong> (ušteda ${disc}%)`;
  } else {
    preview.innerHTML = '';
  }
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

async function toggleFeatured(btn, id) {
  btn.style.opacity = '0.4';
  const fd = new FormData();
  fd.append('action', 'toggle_featured');
  fd.append('id', id);
  try {
    const res = await fetch('actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) {
      alert(data.error || 'Greška');
      btn.style.opacity = '1';
      return;
    }
    // Update star
    btn.style.color = data.featured ? '#c9a86c' : '#ccc';
    btn.dataset.featured = data.featured ? '1' : '0';
    btn.title = data.featured ? 'Ukloni iz istaknuti' : 'Dodaj u istaknuti';
    // Update row background
    btn.closest('tr').style.background = data.featured ? 'rgba(201,168,108,0.06)' : '';
    // Update counter
    const counter = document.getElementById('featured-counter');
    if (counter) {
      counter.textContent = data.count + '/6';
      counter.style.color = data.count >= 6 ? 'var(--danger)' : 'var(--dark)';
    }
    const maxMsg = document.getElementById('featured-max-msg');
    if (maxMsg) maxMsg.style.display = data.count >= 6 ? 'inline' : 'none';
  } catch(e) {
    alert('Greška pri čuvanju');
  }
  btn.style.opacity = '1';
}

async function toggleStock(id) {
  const btn = document.getElementById('stock-btn-' + id);
  btn.style.opacity = '0.4';
  const fd = new FormData();
  fd.append('action', 'toggle_stock');
  fd.append('id', id);
  try {
    const res = await fetch('actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert(data.error || 'Greška'); btn.style.opacity = '1'; return; }
    const inStock = data.inStock;
    btn.style.background = inStock ? 'rgba(39,174,96,0.15)' : 'rgba(231,76,60,0.15)';
    btn.style.color = inStock ? '#27ae60' : '#e74c3c';
    btn.style.border = '1px solid ' + (inStock ? '#27ae60' : '#e74c3c');
    btn.title = inStock ? 'Označi kao nema na stanju' : 'Označi kao ima na stanju';
    btn.querySelector('i').className = 'fas ' + (inStock ? 'fa-check-circle' : 'fa-times-circle');
    // Update badge in the stock column
    const row = btn.closest('tr');
    const stockCell = row.querySelector('.stock-badge');
    if (stockCell) {
      stockCell.innerHTML = inStock
        ? '<span class="badge badge-success">Na lageru</span>'
        : '<span class="badge" style="background:rgba(231,76,60,0.1);color:var(--danger);">Nema</span>';
    }
  } catch(e) { alert('Greška pri čuvanju'); }
  btn.style.opacity = '1';
}

async function setBadge(select, id) {
  const badge = select.value;
  const fd = new FormData();
  fd.append('action', 'set_badge');
  fd.append('id', id);
  fd.append('badge', badge);
  select.style.opacity = '0.4';
  try {
    const res = await fetch('actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      select.style.borderColor = badge ? '#c9a86c' : '#ddd';
      select.style.color = badge ? '#c9a86c' : '#999';
      select.style.fontWeight = badge ? '600' : '400';
    }
  } catch(e) {
    alert('Greška pri čuvanju');
  }
  select.style.opacity = '1';
}

// ── SLIKE KATEGORIJA ──────────────────────────────────────────
const cropState = {};
let _cropActionInterval = null;

function applyTransform(catId) {
  const s = cropState[catId];
  const container = document.querySelector(`.crop-container[data-cat="${catId}"]`);
  if (!container || !s) return;

  // background-size: coverPct * zoom % → gives "cover-at-zoom-1" behaviour
  const sizePct = (s.coverPct || 100) * s.zoom;
  container.style.backgroundPosition = `${s.posX}% ${s.posY}%`;
  container.style.backgroundSize     = `${sizePct.toFixed(1)}%`;

  // Update coordinate display
  const xEl = document.querySelector(`.pos-x-val[data-cat="${catId}"]`);
  const yEl = document.querySelector(`.pos-y-val[data-cat="${catId}"]`);
  if (xEl) xEl.textContent = Math.round(s.posX);
  if (yEl) yEl.textContent = Math.round(s.posY);

  // Update zoom slider and label
  const slider = document.querySelector(`.zoom-slider[data-cat="${catId}"]`);
  if (slider) slider.value = Math.round(s.zoom * 100);
  const valEl = container.closest('.card')?.querySelector('.zoom-val');
  if (valEl) valEl.textContent = Math.round(s.zoom * 100) + '%';
}

// Arrow buttons: hold for continuous movement
function startMove(catId, dx, dy) {
  stopCropAction();
  const step = () => {
    const s = cropState[catId];
    if (!s) return;
    s.posX = Math.max(0, Math.min(100, s.posX + dx));
    s.posY = Math.max(0, Math.min(100, s.posY + dy));
    applyTransform(catId);
  };
  step();
  _cropActionInterval = setInterval(step, 80);
}

// Zoom buttons: hold for continuous zoom
function startZoom(catId, delta) {
  stopCropAction();
  const step = () => {
    const s = cropState[catId];
    if (!s) return;
    s.zoom = Math.max(1.0, Math.min(3.0, s.zoom + delta));
    applyTransform(catId);
  };
  step();
  _cropActionInterval = setInterval(step, 80);
}

function stopCropAction() {
  if (_cropActionInterval) { clearInterval(_cropActionInterval); _cropActionInterval = null; }
}

function resetCropPos(catId) {
  const s = cropState[catId];
  if (!s) return;
  s.posX = 50; s.posY = 50; s.zoom = 1.0;
  applyTransform(catId);
}

// Init each crop container
document.querySelectorAll('.crop-container').forEach(container => {
  const catId  = container.dataset.cat;
  const imgSrc = container.dataset.imgSrc;
  if (!imgSrc) return; // no image yet

  const slider    = container.closest('.card').querySelector('.zoom-slider');
  const sliderVal = slider ? parseInt(slider.value) : 100;
  const bgPos     = container.style.backgroundPosition || '50% 50%';
  const parts     = bgPos.match(/([\d.]+)%\s+([\d.]+)%/);

  cropState[catId] = {
    posX:     parts ? parseFloat(parts[1]) : 50,
    posY:     parts ? parseFloat(parts[2]) : 50,
    zoom:     sliderVal / 100,
    coverPct: null,   // set once image loads
    naturalW: 1,
    naturalH: 1
  };

  // Load image to compute "cover" base size and natural dimensions
  const tmpImg = new Image();
  tmpImg.onload = function() {
    const cw = container.offsetWidth  || 340;
    const ch = container.offsetHeight || 240;
    const scale = Math.max(cw / this.naturalWidth, ch / this.naturalHeight);
    cropState[catId].coverPct = (this.naturalWidth * scale / cw) * 100;
    cropState[catId].naturalW = this.naturalWidth;
    cropState[catId].naturalH = this.naturalHeight;
    applyTransform(catId);
  };
  tmpImg.src = imgSrc;

  // Helper: overflow in px at current state
  function overflow(s) {
    const cw   = container.offsetWidth  || 340;
    const ch   = container.offsetHeight || 240;
    const imgW = cw * (s.coverPct || 100) * s.zoom / 100;
    const imgH = imgW * s.naturalH / s.naturalW;
    return { ox: Math.max(1, imgW - cw), oy: Math.max(1, imgH - ch) };
  }

  // ── Mouse drag ──────────────────────────────────────────────
  let dragging = false, mStartX, mStartY, mStartPosX, mStartPosY;

  container.addEventListener('mousedown', e => {
    dragging  = true;
    mStartX   = e.clientX; mStartY   = e.clientY;
    mStartPosX = cropState[catId].posX;
    mStartPosY = cropState[catId].posY;
    container.style.cursor = 'grabbing';
    e.preventDefault();
  });
  document.addEventListener('mousemove', e => {
    if (!dragging) return;
    const s = cropState[catId];
    const { ox, oy } = overflow(s);
    s.posX = Math.max(0, Math.min(100, mStartPosX - ((e.clientX - mStartX) / ox) * 100));
    s.posY = Math.max(0, Math.min(100, mStartPosY - ((e.clientY - mStartY) / oy) * 100));
    applyTransform(catId);
  });
  document.addEventListener('mouseup', () => {
    if (dragging) { dragging = false; container.style.cursor = 'grab'; }
  });

  // ── Touch drag + pinch-to-zoom ───────────────────────────────
  let tStartX, tStartY, tStartPosX, tStartPosY;
  let pinchStartDist, pinchStartZoom, activeTouches = 0;

  container.addEventListener('touchstart', e => {
    activeTouches = e.touches.length;
    if (activeTouches === 1) {
      tStartX    = e.touches[0].clientX;
      tStartY    = e.touches[0].clientY;
      tStartPosX = cropState[catId].posX;
      tStartPosY = cropState[catId].posY;
    } else if (activeTouches === 2) {
      pinchStartDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      pinchStartZoom = cropState[catId].zoom;
    }
    e.preventDefault();
  }, { passive: false });

  container.addEventListener('touchmove', e => {
    const s = cropState[catId];
    if (e.touches.length === 1 && activeTouches === 1) {
      const { ox, oy } = overflow(s);
      s.posX = Math.max(0, Math.min(100, tStartPosX - ((e.touches[0].clientX - tStartX) / ox) * 100));
      s.posY = Math.max(0, Math.min(100, tStartPosY - ((e.touches[0].clientY - tStartY) / oy) * 100));
      applyTransform(catId);
    } else if (e.touches.length === 2) {
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      s.zoom = Math.max(1.0, Math.min(3.0, pinchStartZoom * (dist / pinchStartDist)));
      applyTransform(catId);
    }
    e.preventDefault();
  }, { passive: false });

  container.addEventListener('touchend', e => { activeTouches = e.touches.length; });

  // ── Zoom slider ──────────────────────────────────────────────
  if (slider) {
    slider.addEventListener('input', function() {
      const zoom = parseInt(this.value) / 100;
      cropState[catId].zoom = zoom;
      applyTransform(catId);
    });
  }
});

async function handleCatImageUpload(input, catId) {
  if (!input.files || !input.files[0]) return;
  const label = input.closest('label');
  const span  = label.querySelector('span');
  span.textContent = 'Uploadujem...';
  const fd = new FormData();
  fd.append('action', 'upload_category_image');
  fd.append('cat_id', catId);
  fd.append('cat_image', input.files[0]);
  // Reset input so the same file can be re-selected if needed
  input.value = '';
  try {
    const res  = await fetch('actions.php', { method: 'POST', body: fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) {
      span.textContent = 'Greška servera – pokušaj ponovo';
      console.error('Server response (not JSON):', text.slice(0, 500));
      return;
    }
    if (data.ok) {
      span.textContent = 'Slika uploadovana!';
      setTimeout(() => location.href = 'dashboard.php?section=cat-images', 800);
    } else {
      span.textContent = data.error || 'Greška';
    }
  } catch(e) {
    span.textContent = 'Greška pri uploadu – provjeri vezu';
  }
}

async function saveCatPosition(catId) {
  const s = cropState[catId];
  if (!s) return;
  const btn = document.querySelector(`.save-pos-btn[data-cat="${catId}"]`);
  btn.style.opacity = '0.5';
  const fd = new FormData();
  fd.append('action', 'save_category_position');
  fd.append('cat_id', catId);
  fd.append('posX', s.posX.toFixed(2));
  fd.append('posY', s.posY.toFixed(2));
  fd.append('zoom', s.zoom.toFixed(4));
  try {
    const res  = await fetch('actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      const msg = document.querySelector(`.pos-saved-msg[data-cat="${catId}"]`);
      msg.style.display = 'inline';
      setTimeout(() => msg.style.display = 'none', 2500);
    }
  } catch(e) { alert('Greška'); }
  btn.style.opacity = '1';
}

// ===== SHOWCASE UPLOAD =====
function previewShowcase(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('showcase-preview-img').src = e.target.result;
    document.getElementById('showcase-preview-wrap').style.display = 'block';
    document.getElementById('showcase-upload-btn').style.display = 'block';
  };
  reader.readAsDataURL(input.files[0]);
}

function handleShowcaseDrop(e) {
  e.preventDefault();
  document.getElementById('showcase-drop-zone').style.borderColor = '#ddd';
  document.getElementById('showcase-drop-zone').style.background = '';
  const file = e.dataTransfer.files[0];
  if (!file) return;
  const dt = new DataTransfer();
  dt.items.add(file);
  const input = document.getElementById('showcase-file-input');
  input.files = dt.files;
  previewShowcase(input);
}

async function uploadShowcase() {
  const input = document.getElementById('showcase-file-input');
  const btn   = document.getElementById('showcase-upload-btn');
  const msg   = document.getElementById('showcase-msg');
  if (!input.files || !input.files[0]) return;

  btn.textContent = 'Uploaduje se...';
  btn.disabled = true;
  msg.style.display = 'none';

  const fd = new FormData();
  fd.append('action', 'upload_showcase');
  fd.append('showcase_image', input.files[0]);

  try {
    const res  = await fetch('actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    msg.style.display = 'block';
    if (data.ok) {
      msg.style.background = '#e8f5e9';
      msg.style.color = '#2e7d32';
      msg.textContent = 'Slika je uspješno sačuvana na sajtu!';
      // Refresh preview
      const ts = Date.now();
      const cur = document.querySelector('#section-showcase-img img[alt="Showcase slika"]');
      if (cur) cur.src = '../images/showcase-room.jpg?v=' + ts;
    } else {
      msg.style.background = '#fdecea';
      msg.style.color = '#c62828';
      msg.textContent = data.error || 'Greška pri uploadu.';
    }
  } catch(e) {
    msg.style.display = 'block';
    msg.style.background = '#fdecea';
    msg.style.color = '#c62828';
    msg.textContent = 'Greška pri uploadu. Pokušaj ponovo.';
  }
  btn.textContent = 'Sačuvaj na sajt';
  btn.disabled = false;
}

// ===== ABOUT IMAGE =====
function previewAbout(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('about-preview-img').src = e.target.result;
    document.getElementById('about-preview-wrap').style.display = 'block';
    document.getElementById('about-upload-btn').style.display = 'block';
  };
  reader.readAsDataURL(input.files[0]);
}

function handleAboutDrop(e) {
  e.preventDefault();
  document.getElementById('about-drop-zone').style.borderColor = '#ddd';
  document.getElementById('about-drop-zone').style.background = '';
  const file = e.dataTransfer.files[0];
  if (!file) return;
  const dt = new DataTransfer();
  dt.items.add(file);
  const input = document.getElementById('about-file-input');
  input.files = dt.files;
  previewAbout(input);
}

async function uploadAbout() {
  const input = document.getElementById('about-file-input');
  const btn   = document.getElementById('about-upload-btn');
  const msg   = document.getElementById('about-msg');
  if (!input.files || !input.files[0]) return;
  btn.textContent = 'Uploaduje se...';
  btn.disabled = true;
  msg.style.display = 'none';
  const fd = new FormData();
  fd.append('action', 'upload_about');
  fd.append('about_image', input.files[0]);
  try {
    const res  = await fetch('actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    msg.style.display = 'block';
    if (data.ok) {
      msg.style.background = '#d1fae5'; msg.style.color = '#065f46';
      msg.textContent = 'Slika je uspješno sačuvana!';
      const cur = document.getElementById('about-current-img');
      if (cur.tagName === 'IMG') {
        cur.src = '../images/about-showroom.jpg?v=' + Date.now();
      } else {
        const img = document.createElement('img');
        img.src = '../images/about-showroom.jpg?v=' + Date.now();
        img.id = 'about-current-img';
        img.style.cssText = 'width:100%;border-radius:8px;display:block;';
        cur.replaceWith(img);
      }
      document.getElementById('about-preview-wrap').style.display = 'none';
      btn.style.display = 'none';
      input.value = '';
    } else {
      msg.style.background = '#fee2e2'; msg.style.color = '#991b1b';
      msg.textContent = data.error || 'Greška pri uploadu.';
    }
  } catch(e) {
    msg.style.display = 'block';
    msg.style.background = '#fee2e2'; msg.style.color = '#991b1b';
    msg.textContent = 'Greška pri uploadu.';
  }
  btn.textContent = 'Sačuvaj na sajt';
  btn.disabled = false;
}

// ===== HERO SLIDES =====
function hsUpload(slot, type) {
  const suffix = (type === 'mobile') ? '-m' : '';
  document.getElementById('hs-file-' + slot + suffix).click();
}

async function hsSubmit(slot, input, type) {
  if (!input.files[0]) return;
  const suffix = (type === 'mobile') ? '-m' : '';
  const fd = new FormData();
  fd.append('action', 'upload_hero_slide');
  fd.append('slot', slot);
  fd.append('type', type || 'desktop');
  fd.append('slide_image', input.files[0]);
  try {
    const r = await fetch('actions.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      const preview = document.getElementById('hs-preview-' + slot + suffix);
      if (preview.tagName === 'IMG') {
        preview.src = d.url;
      } else {
        const img = document.createElement('img');
        img.src = d.url;
        img.id = 'hs-preview-' + slot + suffix;
        img.alt = (type === 'mobile' ? 'Mob ' : 'Slajd ') + slot;
        img.style.cssText = 'width:100%;height:140px;object-fit:cover;display:block;';
        preview.replaceWith(img);
      }
      const delBtn = document.getElementById('hs-del-' + slot + suffix);
      if (delBtn) delBtn.style.display = '';
      showToast('Slajd ' + slot + (type === 'mobile' ? ' (mobilna)' : '') + ' je sačuvan!', 'success');
    } else {
      showToast(d.error || 'Greška pri uploadu.', 'error');
    }
  } catch(e) { showToast('Greška pri uploadu.', 'error'); }
  input.value = '';
}

async function hsDelete(slot, type) {
  const suffix = (type === 'mobile') ? '-m' : '';
  const label  = (type === 'mobile') ? 'mobilni slajd ' : 'slajd ';
  if (!confirm('Obriši ' + label + slot + '?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_hero_slide');
  fd.append('slot', slot);
  fd.append('type', type || 'desktop');
  try {
    const r = await fetch('actions.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      const preview = document.getElementById('hs-preview-' + slot + suffix);
      const placeholder = document.createElement('div');
      placeholder.id = 'hs-preview-' + slot + suffix;
      placeholder.style.cssText = 'text-align:center;color:#bbb;';
      placeholder.innerHTML = '<i class="fas fa-image" style="font-size:32px;margin-bottom:6px;display:block;opacity:.4;"></i><span style="font-size:12px;">Prazno</span>';
      preview.replaceWith(placeholder);
      const delBtn = document.getElementById('hs-del-' + slot + suffix);
      if (delBtn) delBtn.style.display = 'none';
      showToast('Slajd ' + slot + (type === 'mobile' ? ' (mobilna)' : '') + ' je obrisan.', 'success');
    }
  } catch(e) { showToast('Greška.', 'error'); }
}

async function deleteInquiry(id, btn) {
  if (!confirm('Obriši ovaj upit? Ova radnja se ne može poništiti.')) return;
  const fd = new FormData();
  fd.append('action', 'delete_inquiry');
  fd.append('id', id);
  try {
    const r = await fetch('actions.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
      btn.closest('.inquiry-card').remove();
      showToast('Upit je obrisan.', 'success');
    } else {
      showToast(j.error || 'Greška pri brisanju.', 'error');
    }
  } catch(e) { showToast('Greška.', 'error'); }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
(function() {
  const tbody = document.querySelector('#section-products table tbody');
  if (!tbody) return;

  let saveTimer = null;
  let saveIndicator = null;

  function getIndicator() {
    if (!saveIndicator) {
      saveIndicator = document.createElement('div');
      saveIndicator.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#333;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;z-index:9999;display:none;transition:opacity 0.3s;';
      document.body.appendChild(saveIndicator);
    }
    return saveIndicator;
  }

  function showSaveMsg(msg, ok) {
    const el = getIndicator();
    el.textContent = msg;
    el.style.background = ok ? '#27ae60' : '#e74c3c';
    el.style.display = 'block';
    el.style.opacity = '1';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => { el.style.opacity = '0'; setTimeout(() => { el.style.display = 'none'; }, 300); }, 2000);
  }

  async function saveOrder() {
    const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(r => parseInt(r.dataset.id));
    try {
      const fd = new FormData();
      fd.append('action', 'reorder_products');
      fd.append('ids', JSON.stringify(ids));
      const res = await fetch('actions.php', { method: 'POST', body: fd });
      const data = await res.json();
      showSaveMsg(data.ok ? '✓ Redosljed sačuvan' : '✗ Greška pri snimanju', data.ok);
    } catch(e) {
      showSaveMsg('✗ Greška mreže', false);
    }
  }

  Sortable.create(tbody, {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'sortable-ghost',
    onEnd: saveOrder
  });

  const style = document.createElement('style');
  style.textContent = '.sortable-ghost { opacity:0.4; background:#f8f4ec !important; } .drag-handle:active { cursor: grabbing; }';
  document.head.appendChild(style);
})();
</script>

</body>
</html>
