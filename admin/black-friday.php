<?php
/**
 * Black Friday popust — postavlja 20% na SVE proizvode koji imaju cijenu,
 * a one koji VEC imaju veci popust (>=20%) ne dira.
 *
 * Radi na SERVERU nad ZIVIM products.json. Prije upisa pravi backup i pamti
 * stare popuste u data/bf-popusti.json, pa se akcija moze tacno vratiti.
 *
 *   ?token=...            dry-run pregled (postavljanje)
 *   ?token=...&apply=1    stvarni upis 20%
 *   ?token=...&revert=1   dry-run pregled vracanja
 *   ?token=...&revert=1&apply=1   vrati stare popuste
 *
 * Preskace proizvode bez cijene (npr. Flex Stone 0 EUR) — nema smisla "−20%" na 0.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('58a51c2e80e100fa2f0b022c29b87f10d2e3c8ad', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');

$BF      = 20;
$apply   = ($_GET['apply'] ?? '') === '1';
$revert  = ($_GET['revert'] ?? '') === '1';
$root    = dirname(__DIR__);
$put     = $root . '/data/products.json';
$marker  = $root . '/data/bf-popusti.json';

$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$promjene = 0;

if ($revert) {
    $stari = json_decode(@file_get_contents($marker), true);
    if (!is_array($stari) || !$stari) { die("Nema zapisa (data/bf-popusti.json) — nema sta da se vrati.\n"); }
    echo $apply ? "=== VRACANJE popusta (kraj Black Friday-a) ===\n" : "=== PREGLED vracanja (dry-run) ===\n";
    foreach ($flat as $i => $p) {
        $id = $p['id'] ?? null;
        if ($id === null || !array_key_exists((string)$id, $stari)) continue;
        // vrati samo ako je jos na 20% (ako je vlasnik u medjuvremenu dao drugi popust, ne diraj)
        if ((int)($p['discount'] ?? 0) === $BF) {
            printf("%s #%-4d %-34s %d%% -> %d%%\n", $apply ? '[OK]' : '[BI]', $id, mb_substr((string)$p['name'],0,34), $BF, (int)$stari[(string)$id]);
            if ($apply) $flat[$i]['discount'] = (int)$stari[(string)$id];
            $promjene++;
        }
    }
    echo "\nVraceno: $promjene\n";
    if ($apply && $promjene) {
        @file_put_contents($root . '/data/products.backup-bf-' . date('Ymd-His') . '.json', $sirovo);
        $novi = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $put . '.tmp';
        if (@file_put_contents($tmp, $novi, LOCK_EX) !== false && @rename($tmp, $put)) {
            @unlink($marker);
            echo "UPISANO. Zapis obrisan.\n";
        } else { echo "GRESKA pri upisu!\n"; }
    } elseif (!$apply) {
        echo "(dry-run — dodaj &apply=1 da vratis)\n";
    }
    exit;
}

// ---- POSTAVLJANJE 20% ----
echo $apply ? "=== POSTAVLJANJE Black Friday {$BF}% ===\n" : "=== PREGLED (dry-run) ===\n";
$stari = [];
$preskoceno_bezcijene = 0;
foreach ($flat as $i => $p) {
    $id  = $p['id'] ?? null;
    $cur = (int)($p['discount'] ?? 0);
    $cij = (float)($p['price'] ?? 0);
    if ($cij <= 0) { $preskoceno_bezcijene++; continue; }       // bez cijene — preskoci
    if ($cur >= $BF) {                                          // vec veci/jednak popust — zadrzi
        printf("DRZI #%-4d %-34s %d%%\n", $id, mb_substr((string)$p['name'],0,34), $cur);
        continue;
    }
    $stari[(string)$id] = $cur;
    printf("%s #%-4d %-34s %d%% -> %d%%\n", $apply ? '[OK]' : '[BI]', $id, mb_substr((string)$p['name'],0,34), $cur, $BF);
    if ($apply) $flat[$i]['discount'] = $BF;
    $promjene++;
}
echo "\nNa {$BF}%: $promjene | Zadrzano (veci popust): " . (count(array_filter($flat, fn($p)=> (float)($p['price']??0)>0 && (int)($p['discount']??0)>$BF))) . " | Bez cijene preskoceno: $preskoceno_bezcijene\n";

if ($apply && $promjene) {
    @file_put_contents($root . '/data/products.backup-bf-' . date('Ymd-His') . '.json', $sirovo);
    @file_put_contents($marker, json_encode($stari, JSON_PRETTY_PRINT));
    $novi = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = $put . '.tmp';
    if (@file_put_contents($tmp, $novi, LOCK_EX) !== false && @rename($tmp, $put)) {
        echo "UPISANO. Zapis starih popusta: data/bf-popusti.json (za vracanje: ?revert=1&apply=1)\n";
    } else { echo "GRESKA pri upisu!\n"; }
} elseif (!$apply) {
    echo "(dry-run — dodaj &apply=1 da upises)\n";
}
