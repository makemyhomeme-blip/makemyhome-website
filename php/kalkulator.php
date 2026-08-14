<?php
/**
 * Racunica za kalkulator kolicine — ISTA ona koja je bila u js/products.js.
 *
 * Zasto je prenesena u PHP:
 * Desna kolona na stranici proizvoda ispisivala se dva puta. Server je prvo
 * ispisao jednostavniju verziju (naslov, cijena, opis, stanje), a onda bi
 * JavaScript, tek posto skine data/products.json, cijelu tu kolonu zamijenio
 * drugom — sa sifrom, ocjenom, drugacije slozenom cijenom i kalkulatorom.
 * Kupac je pri osvjezavanju vidio prvo jedan raspored pa drugi, a kalkulator
 * bi se pojavio zadnji. Na svakoj stranici proizvoda, ne samo na 3D letvicama.
 *
 * Sada server ispisuje ODMAH konacan izgled, a JavaScript ga vise ne crta —
 * samo ozivi dugmad. Zato ove funkcije moraju davati isti rezultat kao ranije
 * JS, inace bi se kalkulator racunao drugacije nego prije.
 *
 * Vazno: nista se ne izmislja. Kad podatka nema, vraca se null i taj dio se
 * ne prikazuje — kao i u JS-u. Ranije se za letvice bez upisane sirine
 * vracalo 16 cm, pa je kalkulator racunao sa izmisljenom mjerom.
 */

/** Sirina letvice u centimetrima, iz "Širina: 170mm". Bez podatka: null. */
function mmhSirinaLetviceCm(array $p): ?float
{
    foreach (($p['features'] ?? []) as $f) {
        if (preg_match('/Širina:\s*(\d+)\s*mm/iu', $f, $m)) {
            return ((int) $m[1]) / 10;
        }
    }
    return null;
}

/**
 * Koliko m² pokriva jedan komad.
 * Redoslijed je isti kao u JS-u: roba po m² -> 1; letvice -> 2,80 × sirina;
 * inace prvo "(3,42 m²", pa "275×60cm". Bez podatka: null.
 */
function mmhPokrivenostPoKomadu(array $p): ?float
{
    if (($p['unit'] ?? '') === 'm²') return 1.0;

    if (($p['category'] ?? '') === '3d-letvice') {
        $w = mmhSirinaLetviceCm($p);
        return $w ? 2.80 * ($w / 100) : null;
    }

    foreach (($p['features'] ?? []) as $f) {
        if (preg_match('/\((\d+\s*[.,]\s*\d+)\s*m²/u', $f, $m)) {
            $v = (float) str_replace([' ', ','], ['', '.'], $m[1]);
            if ($v > 0.05) return $v;
        }
        if (preg_match('/(\d{2,3})\s*[×x]\s*(\d{2,3})\s*cm/iu', $f, $m)) {
            $v = ((int) $m[1] / 100) * ((int) $m[2] / 100);
            if ($v > 0.05) return $v;
        }
    }
    return null;
}

/**
 * Dimenzije komada za kategorije koje ih prikazuju iznad kalkulatora.
 * Vraca ['w' => ..., 'h' => ...] u centimetrima ili null.
 *
 * Flex Stone ima zadatu vrijednost 120×60 kad je u podacima nema — tako je
 * bilo i u JS-u, pa se ne mijenja da se prikaz ne bi promijenio.
 */
function mmhDimenzijeKomada(array $p): ?array
{
    $kat = $p['category'] ?? '';
    $f   = $p['features'] ?? [];

    if ($kat === 'pu-kamen' || $kat === 'mdf') {
        foreach ($f as $x) {
            if (preg_match('/(\d+)\s*[×x]\s*(\d+)\s*cm/iu', $x, $m)) {
                return ['w' => (int) $m[1], 'h' => (int) $m[2]];
            }
        }
        return null;
    }

    if ($kat === 'flex-stone') {
        foreach ($f as $x) {
            if (preg_match('/(\d+)\s*[×x]\s*(\d+)\s*cm/iu', $x, $m)) {
                return ['w' => (int) $m[1], 'h' => (int) $m[2]];
            }
        }
        return ['w' => 120, 'h' => 60];
    }

    if ($kat === 'spc-pod') {
        foreach ($f as $x) {
            if (preg_match('/(\d+[.,]?\d*)\s*[×x]\s*(\d+[.,]?\d*)\s*cm/iu', $x, $m)) {
                return ['w' => (float) str_replace(',', '.', $m[1]),
                        'h' => (float) str_replace(',', '.', $m[2])];
            }
        }
        return null;
    }

    return null;
}

/** Broj u obliku koji sajt koristi: zarez umjesto tacke. */
function mmhBroj(float $v, int $dec = 2): string
{
    return number_format($v, $dec, ',', '.');
}

/**
 * Harmonika ispod slike: Karakteristike, Idealno za & Stil, O Proizvodu.
 *
 * I ovo je prebaceno iz js/products.js iz istog razloga — server je ispisivao
 * obican spisak, a JavaScript ga zamjenjivao harmonikom, pa se pri svakom
 * osvjezavanju vidjela promjena. Sada postoji samo jedan ispis, ovaj.
 */
function mmhHarmonikaHTML(array $p): string
{
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    // Boje su potamnjene: na svijetloj podlozi stare su davale kontrast
    // 3,1-4,7, ispod 4,5 koliko WCAG trazi za obican tekst.
    $zastita = [
        ['Vodootporan',            'fa-droplet',           '#155f95'],
        ['Otporan na buđ',         'fa-shield-halved',     '#116343'],
        ['Vatrootporan',           'fa-fire-flame-curved', '#a34a06'],
        ['Otporan na prljavštinu', 'fa-hand-sparkles',     '#5c4680'],
    ];

    // Red koji pocinje malim slovom je nastavak prethodnog, ne nova stavka.
    $grupe = [];
    foreach (($p['features'] ?? []) as $f) {
        if (str_starts_with($f, 'Šifra:')) continue;
        if (preg_match('/^[a-zšđčćž]/u', $f) && $grupe) {
            $grupe[count($grupe) - 1]['nastavci'][] = $f;
        } else {
            $grupe[] = ['glavni' => $f, 'nastavci' => []];
        }
    }

    $stavke = '';
    foreach ($grupe as $g) {
        $glavni = $g['glavni'];
        if (preg_match('/^Pogodan za/i', $glavni)) continue;   // pokriva "Idealno za"
        $nadjen = null;
        foreach ($zastita as $z) {
            if (str_starts_with($glavni, $z[0])) { $nadjen = $z; break; }
        }
        if ($nadjen) {
            $stavke .= '<li style="background:' . $nadjen[2] . '14;border:1px solid ' . $nadjen[2]
                     . '33;border-radius:8px;padding:8px 12px;margin-bottom:4px;">'
                     . '<i class="fas ' . $nadjen[1] . '" style="color:' . $nadjen[2] . ';"></i>'
                     . '<strong style="color:' . $nadjen[2] . ';">' . $e($glavni) . '</strong></li>';
        } else {
            $pun = $g['nastavci'] ? $glavni . ', ' . implode(', ', $g['nastavci']) : $glavni;
            $stavke .= '<li><i class="fas fa-check"></i>' . $e($pun) . '</li>';
        }
    }

    // Cijena po m² — iz ZIVE cijene. Roba koja se prodaje po m² vec ima cijenu
    // po m²; dijeljenje sa povrsinom jedne daske davalo je 79,51 umjesto 17,49.
    if (($p['unit'] ?? '') !== 'm²') {
        $pov = null;
        foreach (($p['features'] ?? []) as $f) {
            if (preg_match('/\(([\d.,]+)\s*m²\s*po\s+\S+\)/u', $f, $m)) {
                $pov = (float) str_replace(',', '.', $m[1]); break;
            }
            if (preg_match('/Dimenzije[^:]*:\s*(\d+(?:[.,]\d+)?)\s*[×x]\s*(\d+(?:[.,]\d+)?)\s*cm/u', $f, $m)) {
                $pov = ((float) str_replace(',', '.', $m[1]) / 100) * ((float) str_replace(',', '.', $m[2]) / 100);
                break;
            }
        }
        if ($pov && $pov > 0.05 && !empty($p['price'])) {
            $pov   = round($pov, 2);
            $puna  = (float) str_replace(',', '.', (string) $p['price']);
            $placa = $puna * (1 - ((float) ($p['discount'] ?? 0)) / 100);
            $stavke .= '<li><i class="fas fa-check"></i>Cijena po m²: ' . mmhBroj($placa / $pov)
                     . ' €/m² (1 komad pokriva ' . mmhBroj($pov) . ' m²)</li>';
        }
    }

    $ikoneSoba = [
        'Dnevna soba' => 'fas fa-couch', 'Spavaća soba' => 'fas fa-bed',
        'Kuhinja' => 'fas fa-utensils', 'Kupaonica' => 'fas fa-bath',
        'Hodnik' => 'fas fa-door-open', 'Ured' => 'fas fa-briefcase',
        'Restoran' => 'fas fa-concierge-bell', 'Bar/kafić' => 'fas fa-coffee',
        'Kućni bioskop' => 'fas fa-film', 'Hotel' => 'fas fa-hotel',
        'VIP lounge' => 'fas fa-glass-cheers', 'Biblioteka' => 'fas fa-book',
    ];
    $sobe = '';
    foreach (($p['idealFor'] ?? []) as $s) {
        $sobe .= '<div class="ideal-room"><i class="' . ($ikoneSoba[$s] ?? 'fas fa-home') . '"></i><span>'
               . $e($s) . '</span></div>';
    }
    $stilovi = '';
    foreach (($p['styleMatch'] ?? []) as $s) {
        $stilovi .= '<span class="style-badge">' . $e($s) . '</span>';
    }

    $h = '<div class="spec-accordion">'
       . '<div class="spec-item">'
       . '<button class="spec-header" onclick="toggleSpec(this)">'
       . '<span><i class="fas fa-list-check"></i> Karakteristike</span>'
       // Strelica je vec okrenuta jer je ovaj dio otvoren. Ranije ju je
       // okretao JavaScript poslije ucitavanja, pa se vidjelo kako se vrti.
       . '<i class="fas fa-chevron-down spec-arrow" style="transform:rotate(180deg);"></i></button>'
       . '<div class="spec-body open"><ul class="spec-feature-list">' . $stavke . '</ul></div></div>';

    if ($sobe || $stilovi) {
        $h .= '<div class="spec-item">'
            . '<button class="spec-header" onclick="toggleSpec(this)">'
            . '<span><i class="fas fa-heart"></i> Idealno za &amp; Stil</span>'
            . '<i class="fas fa-chevron-down spec-arrow"></i></button><div class="spec-body">'
            . ($sobe ? '<div class="ideal-for-grid" style="margin-bottom:12px;">' . $sobe . '</div>' : '')
            . ($stilovi ? '<div class="style-match-row">' . $stilovi . '</div>' : '')
            . '</div></div>';
    }

    if (!empty($p['highlight'])) {
        $h .= '<div class="spec-item">'
            . '<button class="spec-header" onclick="toggleSpec(this)">'
            . '<span><i class="fas fa-quote-left"></i> O Proizvodu</span>'
            . '<i class="fas fa-chevron-down spec-arrow"></i></button>'
            . '<div class="spec-body"><div class="product-highlight">'
            . $e(strip_tags($p['highlight'])) . '</div></div></div>';
    }

    $h .= '</div>'
        . '<div class="product-trust-row">'
        . '<div class="trust-item"><i class="fas fa-truck"></i><span>Dostava kurirskom službom — okvirno 20 €</span></div>'
        . '<div class="trust-item"><i class="fas fa-tools"></i><a href="montaza.html" style="color:inherit;text-decoration:underline;">Savjeti za montažu</a></div>'
        . '<div class="trust-item"><i class="fas fa-money-bill-wave"></i><span>Plaćanje pouzećem</span></div>'
        . '</div>';

    return $h;
}
