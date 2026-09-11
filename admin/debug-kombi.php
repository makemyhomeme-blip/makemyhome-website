<?php
// Debug: sta mmhKombiInspiracija stvarno vrati na serveru + pozicija SW215 combo.
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/php/slug.php';
require_once $root . '/php/kombinacije.php';
$P = json_decode(@file_get_contents($root . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];

$r = mmhKombiInspiracija($P);
echo "SKIP (" . count($r['skip']) . "):\n";
foreach ($r['skip'] as $k => $v) echo "  $k\n";
echo "\nCOMBO (" . count($r['combo']) . "):\n";
foreach ($r['combo'] as $k => $v) {
    echo "  $k  ->  " . implode(', ', array_map(fn($x) => $x['name'], $v)) . "\n";
}

$target = 'images/products/gallery-46-1789058882-300.jpg';
echo "\nCilj: $target\n";
echo "  u skip? " . (isset($r['skip'][$target]) ? 'DA (preskace se!)' : 'ne') . "\n";
echo "  u combo? " . (isset($r['combo'][$target]) ? 'DA' : 'ne') . "\n";

// replikuj redosljed
$iv = fn($put) => preg_match('/gallery-\d+-(\d{9,11})-/', $put, $m) ? (int)$m[1] : 0;
$insPo = [];
foreach ($P as $p) { if (empty($p['gallery'])) continue;
  $g = array_values($p['gallery']); usort($g, fn($a,$b)=>$iv($b)<=>$iv($a));
  $insPo[$p['id']] = ['g'=>$g, 't'=>$iv($g[0])]; }
uasort($insPo, fn($a,$b)=>$b['t']<=>$a['t']);
$poKat=[]; foreach($insPo as $id=>$v)$poKat[($P[array_search($id,array_column($P,'id'))]['category']??'')][]=$id;
// jednostavnije: kategorija preko mape
$catOf=[]; foreach($P as $p)$catOf[$p['id']]=$p['category']??'';
$poKat=[]; foreach($insPo as $id=>$v)$poKat[$catOf[$id]][]=$id;
uasort($poKat, fn($a,$b)=>$insPo[$b[0]]['t']<=>$insPo[$a[0]]['t']);
$redom=[]; while($poKat){foreach(array_keys($poKat) as $k){$redom[]=array_shift($poKat[$k]);if(!$poKat[$k])unset($poKat[$k]);}}
$sl=[]; $krug=0;
while(true){$d=false;foreach($redom as $id){if(isset($insPo[$id]['g'][$krug])){$put=$insPo[$id]['g'][$krug];$d=true;if(isset($r['skip'][$put]))continue;$sl[]=$put;}}if(!$d)break;$krug++;}
foreach($sl as $i=>$s) if($s===$target) echo "\nPozicija u galeriji: ".($i+1)." / ".count($sl)."\n";
