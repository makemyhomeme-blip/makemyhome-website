<?php
// Redirect stari WordPress /product/slug/ URL-ovi
if (!empty($_GET['slug'])) {
    require_once __DIR__ . '/php/slug-match.php';
    $products = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
    header('Location: ' . mmhSlugTarget($_GET['slug'], $products), true, 301);
    exit;
}

require_once __DIR__ . '/php/slug.php';

// Kategorija se otvara preko /kategorija/<kljuc>. Stari oblici ?category= i ?cat=
// i dalje rade, ali odmah salju 301 na novu adresu.
$catPretty = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['k'] ?? ''));
$catStari  = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['category'] ?? $_GET['cat'] ?? ''));

if ($catPretty === '' && $catStari !== '') {
    header('Location: ' . mmhUrlKategorije($catStari), true, 301);
    exit;
}
if ($catPretty === '' && ($_SERVER['QUERY_STRING'] ?? '') !== '' && isset($_GET['cat'])) {
    header('Location: https://makemyhome.me/products.html', true, 301);
    exit;
}

$cat = $catPretty;

$catNames = [
  'bambus-tekstilni' => 'Tekstilni Paneli',
  'bambus-drveni'    => 'Drveni Paneli',
  'bambus-mermerni'  => 'Mermerni Paneli',
  'bambus-metalni'   => 'Metalni Paneli',
  'bambus-kozni'     => 'Kožni Paneli',
  'bambus-paneli'    => 'Bambus Paneli',
  '3d-letvice'       => '3D Letvice',
  'akusticni-paneli' => 'Akustični Paneli',
  'aluminijum-lajsne'=> 'Aluminijum Lajsne',
  'spc-pod'          => 'SPC Pod',
  'pu-kamen'         => 'PU Kamen',
  'classic'          => 'Classic Paneli',
  'mdf'              => 'MDF Paneli',
  'flex-stone'       => 'Flex Stone',
];

$catImages = [
  'bambus-tekstilni' => 'images/products/product-1774006203-686.jpg',
  'bambus-drveni'    => 'images/products/cq006.jpg',
  'bambus-mermerni'  => 'images/products/product-1780505348-753.jpg',
  'bambus-metalni'   => 'images/products/product-1774009566-250.jpg',
  'bambus-kozni'     => 'images/products/product-1774454850-237.jpg',
  'bambus-paneli'    => 'images/products/cq006.jpg',
  '3d-letvice'       => 'images/products/product-1785262175-466.jpg',
  'akusticni-paneli' => 'images/products/product-1774011747-477.jpg',
  'aluminijum-lajsne'=> 'images/products/product-1774013021-738.png',
  'spc-pod'          => 'images/products/product-1774536767-824.jpg',
  'pu-kamen'         => 'images/products/product-1775121365-536.jpg',
  'classic'          => 'images/products/product-1784309442-679.jpg',
  'mdf'              => 'images/products/product-1775489604-493.jpg',
  'flex-stone'       => 'images/products/product-1775307391-584.jpg',
];

// H1 po kategoriji — svaka stranica mora imati SVOJ glavni naslov sa svojim ključnim riječima
$catH1 = [
  'bambus-paneli'    => 'Bambus Zidni Paneli u Podgorici',
  'bambus-drveni'    => 'Drveni Zidni Paneli – Bambus Obloge Podgorica',
  'bambus-tekstilni' => 'Tekstilni Zidni Paneli – Podgorica',
  'bambus-mermerni'  => 'Mermerni Zidni Paneli – Imitacija Mermera',
  'bambus-metalni'   => 'Metalni Zidni Paneli – Moderan Enterijer',
  'bambus-kozni'     => 'Kožni Zidni Paneli – Luksuzne Obloge',
  'classic'          => 'Classic Zidni Paneli – Klasične Obloge za Zid',
  '3d-letvice'       => '3D Dekorativne Letvice za Zid – Podgorica',
  'akusticni-paneli' => 'Akustični Paneli za Zid – Zvučna Izolacija',
  'aluminijum-lajsne'=> 'Aluminijum Lajsne za Zidne Panele',
  'spc-pod'          => 'SPC Vodootporni Pod – Podgorica, Crna Gora',
  'pu-kamen'         => 'PU Dekorativni Kamen za Zid – Imitacija Kamena',
  'mdf'              => 'MDF Kanelirani Zidni Paneli',
  'flex-stone'       => 'Flex Stone – Savitljivi Kameni Furnir',
];

// Nepoznata kategorija (?category=nesto-cega-nema) MORA vratiti 404, ne 200 —
// inace je to "soft 404" i Google moze indeksirati beskonacno varijanti.
if ($cat !== '' && !isset($catNames[$cat])) {
    http_response_code(404);
    header('X-Robots-Tag: noindex', true);
    $catNotFound = true;
}
$catName  = isset($catNames[$cat]) ? $catNames[$cat] : 'Katalog Proizvoda';
$imgPath  = isset($catImages[$cat]) ? $catImages[$cat] : 'images/products/cq006.jpg';
$ogImage  = 'https://makemyhome.me/' . $imgPath;
$ogTitle  = $catName . ' | Make My Home Decor';
$catDescs = [
  'bambus-tekstilni' => 'Tekstilni zidni paneli 280x122cm – premium tkanina na bambusovoj podlozi. Topao i elegantan dekor za dnevne sobe i spavaće sobe.',
  'bambus-drveni'    => 'Drveni zidni paneli 280x122cm – prirodna drvenasta tekstura za moderan i klasičan enterijer. Visoka trajnost i jednostavna ugradnja.',
  'bambus-mermerni'  => 'Mermerni zidni paneli 280x122cm – luksuzni izgled mermera bez cijene prirodnog kamena. Idealni za kupaonice i hodnici.',
  'bambus-metalni'   => 'Metalni zidni paneli 280x122cm – industrijski šik za moderan enterijer. Čelik i aluminijum izgled bez hladnoće pravog metala.',
  'bambus-kozni'     => 'Kožni zidni paneli 280x122cm – luksuzna koža tekstura za ekskluzivne enterijere. Toplo i sofisticirano.',
  '3d-letvice'       => '3D dekorativne letvice 280x16cm – kreativne kombinacije, beskonačne mogućnosti dizajna. Idealne za akcente zidova.',
  'akusticni-paneli' => 'Akustični zidni paneli – smanje buku i uljepšaju prostor istovremeno. Idealni za home office, studije i dnevne sobe.',
  'aluminijum-lajsne'=> 'Aluminijum lajsne – završni detalji koji spajaju panele profesionalno i elegantno.',
  'spc-pod'          => 'SPC podovi – vodootporni, trajni i estetski laminat za svaki prostor. Laka ugradnja i dugotrajnost.',
  'pu-kamen'         => 'Poliuretanski kamen – realistična imitacija kamena bez težine i troškova pravog kamena. Set od 4 komada pokriva 1.74m².',
  'classic'          => 'Classic zidni paneli – timeless dizajn koji odgovara svakom enterijeru. Bijela i neutralne boje.',
  'mdf'              => 'MDF zidni paneli – precizno obrađeni paneli od MDF materijala za moderan i elegantan zid.',
  'flex-stone'       => 'Flex Stone – fleksibilni kameni furnir koji se savija i lijepi na bilo koju površinu.',
];
$ogDesc   = $cat
  ? ($catDescs[$cat] ?? "Pregledajte {$catName} – Make My Home Decor Podgorica, Crna Gora. Zidni paneli i dekorativne obloge.")
  : 'Kompletan katalog zidnih panela, 3D letvica, akustičnih panela, SPC podova i PU kamena. Make My Home Decor Podgorica.';

// SEO uvodni tekst po kategoriji – prikazuje se na stranici (vidi ga kupac i Google)
// Vodic koji odgovara kategoriji — da posjetilac dodje do njega odakle i gleda proizvode
$catVodic = [
  'bambus-mermerni'   => ['paneli-za-kupatilo.html','Paneli za kupatilo','Mermerni paneli su najcesci izbor za kupatilo. Evo koji smiju, kako se lijepe preko plocica i koliko to kosta.'],
  'bambus-drveni'     => ['paneli-ili-lamperija.html','Paneli ili lamperija','Poredjenje sa lamperijom — montaza, vlaga, cijena i gdje je lamperija zaista bolja.'],
  'bambus-tekstilni'  => ['akusticni-paneli-kancelarija.html','Akustika u prostoriji','Tekstilna tekstura smiruje zvuk. Ako vam smeta eho, evo koliko panela treba i gdje idu.'],
  'bambus-kozni'      => ['tv-zid.html','Zid iza televizora','Kozni panel iza TV-a — mat ili sjaj, koliko sirok i gdje ide LED traka.'],
  'bambus-metalni'    => ['tv-zid.html','Zid iza televizora','Kako urediti zid iza televizora bez odsjaja.'],
  'bambus-paneli'     => ['paneli-ili-lamperija.html','Paneli ili lamperija','Poredjenje sa lamperijom — montaza, vlaga, cijena i gdje je lamperija bolja.'],
  'classic'           => ['tv-zid.html','Zid iza televizora','Jednobojni panel iza TV-a — kako da ne bude previse ravno.'],
  '3d-letvice'        => ['tv-zid.html','Zid iza televizora','Letvice najbolje rade iza TV-a. Koliko sirok zid, gdje ide LED i koliko komada treba.'],
  'akusticni-paneli'  => ['akusticni-paneli-kancelarija.html','Akustika u kancelariji','Koliko panela treba za vasu prostoriju, na koji zid idu i sta znaci NRC 0,65-0,85.'],
  'spc-pod'           => ['spc-ili-laminat.html','SPC ili laminat','Sta izdrzava vodu, sta ide na podno grijanje i gdje je laminat zaista bolji.'],
  'pu-kamen'          => ['paneli-za-kupatilo.html','Paneli za kupatilo','PU kamen je vodootporan. Evo sta jos smije u kupatilo i kako se lijepi.'],
  'flex-stone'        => ['paneli-za-kupatilo.html','Paneli za kupatilo','Flex Stone ide i oko zaobljenih uglova. Evo kako se postavlja u kupatilu.'],
  'mdf'               => ['paneli-ili-lamperija.html','Paneli ili lamperija','Kanelirani MDF naspram klasicne lamperije — sta je brze i sta traje.'],
  'aluminijum-lajsne' => ['montaza.html','Montaza korak po korak','Gdje tacno ide koja lajsna, kako se rezu uglovi i sta treba od alata.'],
];

$catSeoText = [
  'bambus-paneli'    => '<p>Bambus zidni paneli su jedno od najtraženijih rješenja za dekoraciju zidova u Crnoj Gori. Spajaju prirodnu drvenu teksturu sa modernim dizajnom i daju toplinu svakom prostoru – dnevnoj sobi, spavaćoj sobi, hodniku ili poslovnom prostoru. Standardne dimenzije su 280x122cm, što omogućava brzo prekrivanje velikih površina uz minimalan broj spojeva. Kao moderna zamjena za klasičnu lamperiju, ovi vodootporni paneli su pogodni i za kupatilo i kuhinju, a posebno su popularni za TV zid – akcentni zid iza televizora ili iza kreveta.</p><p>U ponudi Make My Home Decor showrooma u Podgorici imate bambus panele u više varijanti – drveni, tekstilni, mermerni, metalni i kožni. Svi paneli su otporni na vlagu, jednostavni za montažu (montaža panela lijepljenjem – bez majstora) i lako se održavaju. Dostavljamo širom Crne Gore – Podgorica, Nikšić, Bar, Budva, Herceg Novi i ostali gradovi.</p><p>Ako tražite dekorativni panel za dnevnu sobu, panele za spavaću sobu ili zidne obloge za hodnik, bambus je najuniverzalniji izbor: jedan panel pokriva 3,42 m², pa se veliki zid prekrije sa svega nekoliko komada i bez vidljivih spojeva. Za akcentni zid iza TV-a ili iza kreveta najčešće se biraju tamniji dezeni, a za manje prostorije svijetli, jer vizuelno šire prostor. Uzorke možete vidjeti i opipati u showroomu prije nego se odlučite.</p>',
  'bambus-drveni'    => '<p>Drveni bambus paneli unose toplu, prirodnu drvenu teksturu u svaki enterijer – moderna zamjena za klasičnu drvenu lamperiju. Idealni su za moderne i klasične prostore – dnevne sobe, spavaće sobe, TV zidove i poslovne prostore. Dimenzije 280x122cm, visoka trajnost i jednostavna ugradnja na svaku ravnu površinu.</p><p>Drvo izgleda bez održavanja pravog drveta – paneli su vodootporni i ne deformišu se, pa su pogodni i za kupatilo. Dostupni u Make My Home Decor showroomu u Podgorici, sa dostavom širom Crne Gore.</p><p>Drveni dezeni su najtraženiji jer se uklapaju i u moderan i u klasičan prostor. Panel je 280×122 cm i pokriva 3,42 m², pa se zid od 8 m² prekrije sa tri komada i dva spoja. Debljina je 5 mm, tako da panel ne oduzima prostor i ne traži potkonstrukciju.</p><p>Za dnevnu sobu se najčešće biraju topliji tonovi hrasta i oraha, za spavaću sobu mirniji svijetli, a za hodnik tamniji jer manje pokazuju tragove. Panel se lijepi montažnim silikonom direktno na malter, gips, staru keramiku ili tapetu i siječe se skalpelom. Rezovi i uglovi se pokrivaju aluminijum lajsnama.</p>',
  'bambus-tekstilni' => '<p>Tekstilni bambus paneli imaju mekanu tkaninu na bambusovoj podlozi, što daje sofisticiran i elegantan izgled zidu. Savršeni za spavaće sobe i dnevne boravke gdje želite topao, prijatan ambijent. Dimenzije 280x122cm.</p><p>Premium tekstilna površina je otporna i lako se održava. Pogledajte uzorke u našem showroomu u Podgorici – dostava dostupna širom Crne Gore.</p><p>Tekstilni paneli imaju tkaninu na bambusovoj podlozi, pa zid dobija mekoću koju ravna boja ne može dati. Dobro rade tamo gdje se traži tišina i toplina — iza kreveta, u spavaćoj sobi, u kancelariji ili čitalačkom kutku. Tekstura upija dio odbijenog zvuka, pa prostor zvuči mirnije.</p><p>Dimenzija je 280×122 cm, 3,42 m² po komadu, debljina 5 mm. Površina je zaštićena, ne upija vlagu i čisti se vlažnom krpom. Iako podnosi vlagu, za kupatilo prije preporučujemo mermerne ili drvene dezene, a tekstil za suve prostorije gdje dolazi do izražaja.</p>',
  'bambus-mermerni'  => '<p>Mermerni bambus paneli su savršena imitacija mermera – luksuzni izgled prirodnog kamena bez velike cijene i težine. Ovi vodootporni paneli za kupatilo, hodnik i akcentne zidove u dnevnom boravku prekrivaju velike površine brzo i čisto. Dimenzije 280x122cm, otporni na vlagu.</p><p>Mermerni uzorak izgleda izuzetno realistično, a montaža je jednostavna. Dostupno u Make My Home Decor Podgorica, sa dostavom širom Crne Gore.</p><p>Mermerni paneli daju izgled kamena bez težine, cijene i fugni pravog mermera. Uzorak je UV štampan na bambusovu podlogu i završen visokosjajnim lakom, pa svjetlo pada po zidu slično kao na poliranoj ploči. Jedan komad je 280×122 cm i pokriva 3,42 m², što na velikom zidu znači malo spojeva i neprekinutu šaru.</p><p>Najčešće se koriste u kupatilu, iza kuhinjskog radnog dijela, oko kamina i iza televizora. Panel je vodootporan, ne bubri i ne deformiše se, otporan je na buđ i spada u vatrootpornu klasu B1, pa je dozvoljen i u kafićima, restoranima i hotelima. Lijepi se silikonom, bez bušenja i bez majstora.</p>',
  'bambus-metalni'   => '<p>Metalni bambus paneli daju industrijski šik i moderan izgled bez hladnoće pravog metala. Čelik i aluminijum efekat na toploj bambus osnovi – idealno za moderne enterijere i poslovne prostore. Dimenzije 280x122cm.</p><p>Za razliku od pravih metalnih obloga, ovi paneli su lagani (nekoliko kilograma po ploči), ne hlade prostoriju i ne stvaraju odjek. Površina je otporna na vlagu i lako se čisti vlažnom krpom, pa su pogodni i za kuhinju, kupatilo i ulazne hodnike. Metalni dekor najbolje funkcioniše kao akcentni zid – iza televizora, u kancelariji, u kafiću ili frizerskom salonu – gdje treba dati karakter jednom zidu, a ostatak ostaviti u neutralnom tonu.</p><p>Montaža je ista kao kod ostalih bambus panela: panel se lijepi silikonom direktno na ravan zid, siječe se skalpelom, a spojevi i ivice se zatvaraju aluminijum lajsnama za profesionalan završetak. Nije potreban majstor ni potkonstrukcija, pa je cijeli zid gotov za nekoliko sati.</p><p>Pogledajte metalne panele uživo u našem showroomu u Podgorici (Vojvode Maša Đurovića 41, City Kvart). Dostava kurirskom službom širom Crne Gore – Podgorica, Nikšić, Bar, Budva, Tivat, Herceg Novi, Kotor, Bijelo Polje, Berane i Cetinje. Za savjet o količini i kombinaciji sa lajsnama pozovite 069 105 222.</p>',
  'bambus-kozni'     => '<p>Kožni bambus paneli imaju luksuznu kožnu teksturu za ekskluzivne enterijere. Toplo, sofisticirano i taktilno bogato rješenje za zid. Dimenzije 280x122cm, jednostavna ugradnja.</p><p>Kožni dekor je najčešći izbor za zid iza kreveta u spavaćoj sobi – daje efekat tapacirane table bez stolarskih radova i bez dodatnog troška. Jednako dobro izgleda u dnevnoj sobi iza televizora, u garderobama, hotelskim sobama i reprezentativnim poslovnim prostorima. Tekstura je mekana na dodir, ali podloga je čvrsta i vodootporna, tako da se panel ne krivi i ne upija vlagu.</p><p>Održavanje je jednostavno – dovoljna je vlažna krpa, bez posebnih sredstava. Panel se lijepi silikonom na ravan zid, siječe skalpelom, a ivice se zatvaraju aluminijum lajsnama u zlatnoj, crnoj ili srebrnoj boji, zavisno od tona koji ste izabrali. Jedan panel pokriva 3.4m², pa se prosječan zid završava sa dva do tri panela.</p><p>Uzorke kožnih panela možete vidjeti i opipati u Make My Home Decor showroomu u Podgorici. Dostava kurirskom službom širom Crne Gore, okvirno 20 €. Pozovite 069 105 222 za provjeru dostupnosti boja.</p>',
  '3d-letvice'       => '<p>3D dekorativne letvice (PVC paneli) transformišu svaki ravni zid kroz igru svjetla i sjene. Vertikalni rebrasti dizajn daje prostoru dubinu i moderan karakter – zato su 3D paneli prvi izbor za TV zid i akcentni zid iza kreveta. Dimenzije 280x16cm, kombinacijom letvica kreirate beskonačne mogućnosti dizajna, a pogodne su i za plafon.</p><p>Idealne za akcente iza televizora, u hodnicima, spavaćim sobama i poslovnim prostorima. Montaža je jednostavna – letvice se lijepe i sijeku skalpelom. Pogledajte ih uživo u Make My Home Decor showroomu u Podgorici – dostava širom Crne Gore.</p><p>3D letvica je uska rebrasta ploča dužine 280 cm koja se ređa jedna do druge. Ritam rebara hvata svjetlo i pravi sjenku, pa ravan zid dobija dubinu koju boja ne može postići. U ponudi su tri širine profila — 140, 160 i 170 mm — i što je profil uži, ritam je gušći i finiji.</p><p>Jedna letvica od 160 mm pokriva 0,45 m², pa za zid od 8 m² treba oko 18 komada. Materijal je PVC, ne upija vlagu i ne radi na promjenu temperature. Siječe se skalpelom i lijepi montažnim silikonom, pa se cijeli zid odradi za jedno popodne. Najčešće se stavljaju iza televizora, iza kreveta, u hodniku i kao pregrada između trpezarije i dnevne sobe. Uz LED traku u alu lajsni sjenka postaje još izraženija.</p>',
  'akusticni-paneli' => '<p>Akustični zidni paneli smanjuju buku i odjek u prostoru, a istovremeno izgledaju kao pravi dekorativni element. Idealni za home office, studije, dnevne sobe i poslovne prostore gdje je važna dobra akustika.</p><p>Spajaju funkcionalnost i estetiku – poboljšavaju zvuk i uljepšavaju zid. Dostupni u našem showroomu u Podgorici, sa dostavom širom Crne Gore.</p><p>Akustični paneli su letvice na filcanoj podlozi koja upija odbijeni zvuk. Ne rade zvučnu izolaciju prema komšiji — rade na eho unutar prostorije. U sobi sa mnogo stakla, pločica i malo tekstila govor odzvanja i televizor zvuči mutno; panel na jednom zidu to primjetno smiruje. Koeficijent apsorpcije je NRC 0,65 do 0,85.</p><p>Postoje dvije veličine. Veliki panel je 275×60 cm i pokriva 1,65 m², pa se zid brzo prekrije. Mali format 60×60 cm pokriva 0,36 m² i koristi se kad se pravi uzorak ili se oblaže manja površina. Za zid od 8 m² treba oko pet velikih panela. Montira se ljepilom ili na sistem kuka, a najbolje radi iza televizora, u kancelariji, u sobi za sastanke i u prostoriji gdje se snima ili radi online.</p>',
  'aluminijum-lajsne'=> '<p>Aluminijum lajsne su završni profili koji spajaju panele profesionalno i elegantno. Pokrivaju ivice, prelaze i spojeve i daju instalaciji čist, dovršen izgled.</p><p>Dostupne u više boja i profila u Make My Home Decor showroomu u Podgorici. Dostava širom Crne Gore.</p><p>Lajsne su ono što razdvaja uredno postavljen zid od amaterskog. Panel se siječe skalpelom i rez nikad nije savršen — lajsna ga pokrije i da ravnu liniju bez gletovanja i farbanja.</p><p>Svaka lajsna je duga 3 m i dolazi u crnoj i bronzanoj boji. Četiri namjene: srednja spaja dva panela, početna i završna zatvaraju ivicu prema plafonu ili podu, ugaona pokriva spoljni ugao, a LED lajsna prima traku pa se svjetlo ugrađuje u sam spoj. Za zid širine 3 m obično trebaju dvije srednje i dvije završne. Računajte lajsne odmah uz panele — naknadno se teško uklapaju.</p>',
  'spc-pod'          => '<p>SPC podovi su vodootporni podovi nove generacije – bolja alternativa klasičnom laminatu i vinil podu. Izdrže kupatilo, kuhinju i svakodnevnu upotrebu, izuzetno su trajni, otporni na ogrebotine i jednostavni za ugradnju click sistemom, bez ljepila.</p><p>Idealni za stambene i poslovne prostore gdje je potreban izdržljiv i lijep pod. Pogledajte uzorke u našem showroomu u Podgorici – dostava širom Crne Gore.</p><p>SPC je jedini proizvod iz naše ponude koji se prodaje po kvadratu. Jezgro je mješavina kamenog praha i PVC-a, pa pod ne bubri od vode i ne skuplja se od temperature — može u kupatilo, kuhinju i hodnik gdje klasičan laminat propada.</p><p>Dolazi u dva formata: daska 122×18 cm koja pokriva 0,22 m² i pločica 61,5×31 cm koja pokriva 0,19 m² i daje manje otpada pri rezanju u malim prostorijama. Postavlja se click-lock sistemom, bez ljepila, preko ravne podloge, i može se hodati isti dan. Računajte 10 odsto više od površine sobe zbog rezova.</p>',
  'pu-kamen'         => '<p>PU kamen (poliuretanski kamen) je dekorativni kamen za zid – realistična imitacija prirodnog kamena bez težine i troškova pravog kamena. Set od 4 komada pokriva 1.74m². Idealan za akcentni zid iza TV-a, kamine, špalete i fasadne detalje – svuda gdje želite izgled kamena ili cigle.</p><p>Lagani paneli se jednostavno lijepe na zid i izgledaju kao pravi kamen. Vodootporni su i pogodni za unutrašnju i spoljnu primjenu. Dostupno u Make My Home Decor Podgorica, sa dostavom širom Crne Gore.</p><p>Poliuretanski kamen je lagana imitacija kamena i cigle. Prava kamena obloga traži nosivi zid i majstora, ova se lijepi kao panel i teži nekoliko puta manje, pa može i na gips i na staru oblogu.</p><p>U ponudi su tri formata: 240×60 cm (1,44 m² po komadu), 120×60 cm (0,72 m²) i set 290×60 cm (1,74 m²). Računajte pet odsto rezerve zbog rezanja i uklapanja šare. Najčešće se koristi na jednom akcentnom zidu, oko kamina, u hodniku, na stubovima i u ugostiteljskim prostorima gdje se traži rustičan izgled bez težine pravog kamena.</p>',
  'mdf'              => '<p>MDF zidni paneli sa kaneliranom (rebrastom) površinom su moderna lamperija koja zidovima daje arhitektonski karakter i trodimenzionalnu dubinu. Precizno obrađeni, idealni za moderne i elegantne enterijere – dnevne sobe, spavaće sobe, recepcije i TV zidove.</p><p>Pogledajte MDF panele uživo u našem showroomu u Podgorici. Dostava dostupna širom Crne Gore.</p><p>MDF kanelirani panel je puna ploča sa izrezanim rebrima. Za razliku od PVC letvica, ovdje je rebro dio same ploče, pa je ivica oštrija a sjenka tvrđa — izgled je bliži stolarskoj izradi nego oblozi.</p><p>Dva formata: veliki 290×120 cm (3,48 m² po komadu, debljina 12 mm) i uži 280×60 cm (1,68 m², u varijantama 12 i 18 mm). Zbog debljine i težine MDF traži ravan zid i ozbiljnije ljepilo nego bambus panel. Nije za kupatilo ni druge vlažne prostorije — tu idu bambus ili SPC. Idealan je za dnevnu sobu, kancelariju, recepciju i zid iza televizora.</p>',
  'flex-stone'       => '<p>Flex Stone je savitljivi kameni furnir koji se primjenjuje na ravne, zakrivljene i neravne površine. Pravi kamen u tankom, fleksibilnom obliku – idealno za stubove, lukove i nestandardne površine.</p><p>Jedinstveno rješenje dostupno u Make My Home Decor showroomu u Podgorici, sa dostavom širom Crne Gore.</p><p>Flex Stone je tanak sloj pravog kamena na savitljivoj podlozi. Za razliku od pločica, može da se savije — ide oko stuba, po zaobljenom zidu, preko ivice i na neravnu podlogu na kojoj kruta obloga ne bi legla.</p><p>Ploča je 120×60 cm i pokriva 0,72 m². Površina je prirodan kamen, pa nema dva ista komada i zid izgleda kao zidan, a ne kao štampan. Lijepi se ljepilom za kamen na ravnu i čvrstu podlogu. Koristi se na akcentnim zidovima, u hodnicima, na fasadnim detaljima, oko kamina i u ugostiteljskim prostorima.</p>',
  'classic'          => '<p>Classic zidni paneli imaju bezvremenski dizajn koji odgovara svakom enterijeru. Bijele i neutralne boje, jednostavna ugradnja i dugotrajan izgled. Ovo je najuniverzalnija kategorija u ponudi – ako ne želite izražen dekor, nego čist i miran zid koji neće izaći iz mode, Classic paneli su pravi izbor.</p><p>Površina je glatka ili blago strukturirana, u bijeloj i neutralnim nijansama koje vizuelno šire prostor. Zbog toga se najčešće koriste u manjim stanovima, hodnicima, kuhinjama i kupatilima, ali i u kancelarijama i čekaonicama gdje se traži čist, uredan izgled. Paneli su vodootporni, ne upijaju vlagu i lako se čiste, pa su praktična zamjena za krečenje zidova koji se često prljaju.</p><p>Classic paneli se odlično kombinuju sa 3D letvicama – ravna bijela podloga na jednom dijelu zida i vertikalne letvice na akcentnom dijelu daju moderan, slojevit izgled. Za završnu obradu ivica i spojeva preporučujemo aluminijum lajsne, koje pokrivaju rezove i daju profesionalan izgled bez gletovanja i farbanja.</p><p>Montaža je jednostavna: panel se lijepi silikonom direktno na ravan zid i siječe skalpelom, bez potkonstrukcije i bez majstora. Dostupno u Make My Home Decor showroomu u Podgorici (Vojvode Maša Đurovića 41, City Kvart), sa dostavom kurirskom službom širom Crne Gore. Za savjet i izračun količine pozovite 069 105 222.</p>',
];
$ogUrl    = $cat ? mmhUrlKategorije($cat) : 'https://makemyhome.me/products.html';
// Naslov po kategoriji. Ranije je svaka nosila "... – Zidni Paneli", pa je
// dvadeset stranica trazilo istu rijec od Googla i medjusobno se gusile.
// Sada svaka kategorija ima svoj izraz, a "zidni paneli" ostaje samo katalogu.
$catTitles = [
  'bambus-paneli'    => 'Bambus Paneli za Zid – Cijene i Modeli',
  'bambus-drveni'    => 'Drveni Paneli za Zid – Bambus Obloge',
  'bambus-tekstilni' => 'Tekstilni Paneli za Zid – Modeli i Cijene',
  'bambus-mermerni'  => 'Mermerni Paneli – Imitacija Mermera za Zid',
  'bambus-metalni'   => 'Metalni Paneli za Zid – Moderan Enterijer',
  'bambus-kozni'     => 'Kožni Paneli za Zid – Luksuzne Obloge',
  'classic'          => 'Classic Paneli – Jednobojne Obloge za Zid',
  '3d-letvice'       => '3D Letvice za Zid – Cijene i Dezeni',
  'akusticni-paneli' => 'Akustični Paneli – Zvučna Izolacija Zida',
  'aluminijum-lajsne'=> 'Aluminijum Lajsne za Panele – Profili',
  'spc-pod'          => 'SPC Vodootporni Pod – Cijena po m²',
  'pu-kamen'         => 'PU Dekorativni Kamen za Zid – Imitacija',
  'mdf'              => 'MDF Kanelirani Paneli – Rebrasti Zid',
  'flex-stone'       => 'Flex Stone – Savitljivi Kameni Furnir',
];
$pageTitle = $cat
  ? (($catTitles[$cat] ?? $catName) . ' | Make My Home Decor')
  : 'Zidni Paneli i Bambus Obloge | Make My Home Decor';
?>
<!DOCTYPE html>
<html lang="sr-ME">
<head><meta charset="utf-8">
  
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($ogDesc) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
  <meta property="og:locale" content="sr_ME">
  <meta property="og:site_name" content="Make My Home Decor">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Početna", "item": "https://makemyhome.me/" },
      { "@type": "ListItem", "position": 2, "name": "<?= htmlspecialchars($catName) ?>", "item": "<?= htmlspecialchars($ogUrl) ?>" }
    ]
  }
  </script>
<?php
$_allProds   = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
$_bambusCats = ['bambus-drveni','bambus-tekstilni','bambus-mermerni','bambus-kozni','bambus-metalni'];
if (!$cat) {
  $_listProds = $_allProds;
} elseif ($cat === 'bambus-paneli') {
  $_listProds = array_values(array_filter($_allProds, fn($p) => in_array($p['category'] ?? '', $_bambusCats)));
} else {
  $_listProds = array_values(array_filter($_allProds, fn($p) => ($p['category'] ?? '') === $cat));
}
$_returnPolicy = [
  '@type'                => 'MerchantReturnPolicy',
  'applicableCountry'    => 'ME',
  'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
  'merchantReturnDays'   => 7,
  'returnMethod'         => 'https://schema.org/ReturnByMail',
  'returnFees'           => 'https://schema.org/FreeReturn',
];
$_shippingDetails = [
  '@type'               => 'OfferShippingDetails',
  'shippingRate'        => ['@type' => 'MonetaryAmount', 'value' => '20', 'currency' => 'EUR'],
  'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'ME'],
  'deliveryTime'        => [
    '@type'        => 'ShippingDeliveryTime',
    'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 2, 'unitCode' => 'DAY'],
    'transitTime'  => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 4, 'unitCode' => 'DAY'],
  ],
];
$_items = [];
foreach (array_slice($_listProds, 0, 20) as $i => $p) {
  $pOrig    = (float)($p['price'] ?? 0);
  $pDisc    = (int)($p['discount'] ?? 0);
  $pFinal   = $pDisc > 0 ? round($pOrig * (1 - $pDisc / 100), 2) : $pOrig;
  $pRevs    = $p['reviews'] ?? [];
  $pRevCnt  = count($pRevs);
  $pDesc    = mb_substr(strip_tags($p['highlight'] ?? $p['description'] ?? ''), 0, 200);
  $pItem = [
    '@type'       => 'Product',
    'name'        => $p['name'] ?? '',
    'description' => $pDesc,
    'url'         => mmhUrlProizvoda($p),
    'image'       => 'https://makemyhome.me/' . ($p['image'] ?? ''),
    'brand'       => ['@type' => 'Brand', 'name' => 'Make My Home Decor'],
    'sku'         => preg_replace('/\s+/', '-', trim($p['sku'] ?? $p['name'] ?? '')),
    'offers'      => [
      '@type'                  => 'Offer',
      'price'                  => (string)$pFinal,
      'priceCurrency'          => 'EUR',
      'itemCondition'          => 'https://schema.org/NewCondition',
      'priceValidUntil'        => date('Y-m-t', strtotime('first day of next month')),
      'availability'           => ($p['inStock'] ?? true) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      'hasMerchantReturnPolicy'=> $_returnPolicy,
      'shippingDetails'        => $_shippingDetails,
    ],
  ];
  // ---- OCJENE U STRUKTURIRANIM PODACIMA — NAMJERNO ISKLJUCENO ----
  // Isto pravilo kao u product.php: dok se ne potvrdi da svaka recenzija dolazi
  // od stvarnog kupca, ocjene se ne salju Google-u (Review snippet guidelines:
  // "Ratings must be sourced directly from users"). Stranica proizvoda ih vec
  // nije slala, a ove stranice jesu — pa je rizik ipak postojao, samo sa druge
  // strane. Recenzije i dalje stoje vidljivo na stranici, to je dozvoljeno.
  // Kada budu iz forme koju popunjavaju kupci, vraca se uklanjanjem "false &&".
  if (false && $pRevCnt > 0) {
    $pItem['aggregateRating'] = [
      '@type'       => 'AggregateRating',
      'ratingValue' => (string)round(array_sum(array_column($pRevs, 'rating')) / $pRevCnt, 1),
      'bestRating'  => '5',
      'worstRating' => '1',
      'reviewCount' => $pRevCnt,
    ];
  }
  $_items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $pItem];
}
echo '<script type="application/ld+json">' . "\n";
echo json_encode([
  '@context'       => 'https://schema.org',
  '@type'          => 'ItemList',
  'name'           => $catName,
  'url'            => $ogUrl,
  'numberOfItems'  => count($_listProds),
  'itemListElement'=> $_items,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n</script>\n";
?>
  <link rel="icon" type="image/x-icon" href="images/favicon.ico">
  <link rel="icon" type="image/png" href="images/favicon-512.png">
  <link rel="apple-touch-icon" sizes="512x512" href="images/favicon-512.png">
  <meta name="theme-color" content="#1a1a1a">
  <link rel="preload" href="fa/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="fa/css/all.min.css?v=1">
  <link rel="preload" href="fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="css/fonts.css?v=1">
  <link rel="stylesheet" href="css/style-v5.css?v=38">
  <style>
    @media(min-width:769px){.nav-menu{gap:0!important;flex-wrap:nowrap!important;}.nav-link{font-size:12px!important;padding:8px 5px!important;white-space:nowrap!important;}.logo{flex-shrink:0!important;}.logo-text .name,.logo-text .tagline{white-space:nowrap!important;}#desk-search-wrap{flex-shrink:0!important;margin-right:4px!important;}}
    /* ===== CATEGORY GRID ===== */
    .cat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 28px;
      padding: 40px 0;
    }
    .cat-card {
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.07);
      text-decoration: none;
      color: inherit;
      transition: transform 0.25s, box-shadow 0.25s;
      display: block;
      cursor: pointer;
    }
    .cat-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.13);
    }
    .cat-card-img {
      height: 200px;
      overflow: hidden;
      background: #f5f0eb;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .cat-card-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s;
    }
    .cat-card:hover .cat-card-img img { transform: scale(1.05); }
    .cat-card-img i { font-size: 48px; color: #c9a86c; opacity: 0.5; }
    .cat-card-body {
      padding: 20px 24px 24px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }
    .cat-card-icon {
      width: 44px; height: 44px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 18px; flex-shrink: 0; margin-top: 2px;
    }
    .cat-card-info h3 { font-size: 18px; font-weight: 700; margin-bottom: 4px; color: #1a1a1a; }
    .cat-card-info p  { font-size: 13px; color: #666e7a; margin-bottom: 8px; line-height: 1.5; }
    .cat-card-count {
      display: inline-block;
      background: #f5f0eb; color: #c9a86c;
      padding: 3px 12px; border-radius: 20px;
      font-size: 12px; font-weight: 700;
    }
    /* ===== BACK BAR ===== */
    .back-bar {
      display: flex; align-items: center; gap: 16px;
      padding: 20px 0 10px;
    }
    .btn-back {
      display: inline-flex; align-items: center; gap: 8px;
      background: #1a1a1a; color: #fff; text-decoration: none;
      padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
      transition: background 0.2s;
    }
    .btn-back:hover { background: #333; }
    .back-bar h2 { font-size: 22px; font-weight: 800; color: #1a1a1a; }
    .back-bar .count-badge {
      background: #f5f0eb; color: #c9a86c;
      padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 700;
    }
    /* TELEFON: bez praznog prostora ispod hero-a i bez stiskanja u jedan red */
    @media (max-width: 768px) {
      .products-section { padding: 22px 0 60px !important; }
      .back-bar {
        flex-wrap: wrap; align-items: center;
        gap: 10px; padding: 0 0 16px;
      }
      .btn-back { order: 1; padding: 9px 15px; font-size: 13px; }
      .back-bar .count-badge {
        order: 2; margin-left: auto;
        font-size: 12px; padding: 4px 11px; white-space: nowrap;
      }
      .back-bar h2 {
        order: 3; width: 100%;
        font-size: 20px; line-height: 1.25; margin: 0;
      }
    }
    /* OUT OF STOCK */
    .out-of-stock .product-img img { }
    .oos-tag {
      position: absolute;
      top: 14px;
      right: 14px;
      background: rgba(180,28,28,0.92);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      padding: 5px 12px;
      border-radius: 20px;
      z-index: 3;
      backdrop-filter: blur(4px);
    }
  @media(max-width:768px){#desk-search-wrap{display:none!important;}}
  </style>
<style id="nav-fix">
@media(min-width:769px){
  .header-inner{flex-wrap:nowrap!important;}
  .logo{flex-shrink:0!important;margin-right:20px!important;}
  .logo-img{height:44px!important;}
  .logo-text .name{white-space:nowrap!important;font-size:16px!important;}
  .logo-text .tagline{white-space:nowrap!important;font-size:9px!important;}
  .nav-menu{gap:0!important;flex-wrap:nowrap!important;flex-shrink:1!important;margin-left:auto!important;}
  .nav-link{white-space:nowrap!important;font-size:12px!important;padding:8px 5px!important;}
  .nav-link.nav-cta{padding:7px 14px!important;margin-left:4px!important;}
  #desk-search-wrap{flex-shrink:0!important;margin-right:4px!important;}
}
</style>
<style>.footer-links-grid{display:block!important;column-count:2!important;column-gap:20px!important;} .footer-links-grid li{break-inside:avoid;margin-bottom:8px;font-size:13px;}</style>
  <!-- Google Analytics u rezimu BEZ KOLACICA (Consent Mode v2, trajno "denied").
       GA4 ne postavlja nijedan kolacic i ne cuva identifikator na uredjaju posjetioca,
       pa traka za saglasnost nije potrebna. Statistika i dalje stize u agregatnom obliku:
       posjete, izvori, najgledanije stranice, uredjaji, drzave. -->

<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('consent','default',{
    ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied',
    analytics_storage:'denied', functionality_storage:'granted',
    security_storage:'granted', wait_for_update:500
  });
  gtag("js", new Date());
  gtag("config", "G-4LLQCZ8CV4");
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-4LLQCZ8CV4"></script>
<style id="nav-wide">
/* Laptopovi 769–1599px: sve stavke MORAJU stati u red (Kontakt je ranije ispadao van ekrana) */
@media(min-width:769px) and (max-width:1599px){
  .header-inner{max-width:100%!important;padding-left:14px!important;padding-right:14px!important;}
  .nav-menu{gap:0!important;flex-wrap:nowrap!important;}
  .nav-link{font-size:11.5px!important;padding:8px 4px!important;letter-spacing:0!important;}
  .nav-link.nav-cta{padding:7px 11px!important;margin-left:3px!important;}
  .logo{margin-right:8px!important;}
  .logo-img{height:36px!important;}
  .logo-text .name{font-size:13.5px!important;}
  .logo-text .tagline{display:none!important;}
  #desk-search-wrap{width:150px!important;margin-right:4px!important;}
}
/* 769–1099px: previše stavki za jedan red — koristi se hamburger meni (kao na telefonu) */
@media(min-width:769px) and (max-width:1149px){
  .nav-menu{display:none!important;position:absolute!important;top:75px!important;left:0!important;right:0!important;
    background:#1a1a1a!important;flex-direction:column!important;padding:20px!important;gap:4px!important;
    border-top:1px solid rgba(201,168,108,0.2)!important;z-index:9999!important;max-height:calc(100vh - 90px)!important;max-height:calc(100dvh - 90px)!important;overflow-y:auto!important;}
  .nav-menu.open{display:flex!important;}
  .hamburger{display:flex!important;}
  .nav-link{width:100%!important;justify-content:center!important;font-size:14px!important;padding:11px 8px!important;}
  .nav-link.nav-cta{width:100%!important;margin-left:0!important;padding:11px 8px!important;}
  #mob-search-box{display:block!important;}
  #desk-search-wrap{display:none!important;}
  .logo-text .tagline{display:none!important;}
}
/* Široki ekrani: header koristi više prostora, stavke razmaknute */
@media(min-width:1600px){
  .header-inner{max-width:1560px!important;}
  .nav-link{font-size:13px!important;padding:8px 10px!important;}
  #desk-search-wrap{width:250px!important;}
}
@media(min-width:1700px){
  .header-inner{max-width:1720px!important;}
  .nav-link{font-size:13.5px!important;padding:8px 13px!important;}
}
</style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header id="header">
  <div class="header-inner">
    <a href="/" class="logo">
      <!-- OLD LOGO (backup):
      <div class="logo-icon"><i class="fas fa-home"></i></div>
      <div class="logo-text">
        <span class="name">Make My Home Decor</span>
        <span class="tagline">Dekorativni Bambus Paneli</span>
      </div>
-->
      <img src="images/logo-transparent.png" alt="Make My Home Decor" class="logo-img" width="567" height="567">
      <div class="logo-text">
        <span class="name">Make My Home Decor</span>
        <span class="tagline">Dekorativni Bambus Paneli</span>
      </div>
    </a>

    <div id="desk-search-wrap" style="position:relative;flex-shrink:0;margin-left:auto;margin-right:8px;width:210px;">
      <div style="position:relative;">
        <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#c9a86c;font-size:12px;pointer-events:none;"></i>
        <input id="desk-search-input" type="text" placeholder="Traži proizvod…" autocomplete="off"
          style="width:100%;box-sizing:border-box;padding:8px 10px 8px 30px;border-radius:20px;border:1.5px solid rgba(201,168,108,0.4);background:rgba(255,255,255,0.06);color:#fff;font-size:12px;font-family:inherit;outline:none;-webkit-appearance:none;transition:border-color .2s,background .2s;"
          onfocus="this.style.borderColor='rgba(201,168,108,0.85)';this.style.background='rgba(255,255,255,0.1)'"
          onblur="this.style.borderColor='rgba(201,168,108,0.4)';this.style.background='rgba(255,255,255,0.06)'">
      </div>
      <div id="desk-search-results" style="display:none;position:absolute;top:calc(100% + 6px);left:0;width:300px;background:#1a1814;border:1px solid rgba(201,168,108,0.25);border-radius:12px;overflow:hidden;max-height:420px;overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,0.5);z-index:99999;"></div>
    </div>

        <nav id="nav-menu" class="nav-menu">
      <div id="mob-search-box" style="padding:4px 0 14px;width:100%;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#c9a86c;font-size:14px;pointer-events:none;z-index:1;"></i>
          <input id="mob-search-input" type="text" placeholder="Traži po imenu ili šifri…" autocomplete="off"
            style="width:100%;box-sizing:border-box;padding:12px 14px 12px 40px;border-radius:10px;border:1.5px solid rgba(201,168,108,0.35);background:rgba(255,255,255,0.07);color:#fff;font-size:15px;font-family:inherit;outline:none;-webkit-appearance:none;">
        </div>
        <div id="mob-search-results" style="display:none;margin-top:6px;border-radius:10px;overflow:hidden;max-height:52vh;overflow-y:auto;background:rgba(20,18,15,0.97);border:1px solid rgba(201,168,108,0.2);"></div>
      </div>
      <a href="/" class="nav-link">Početna</a>
      <a href="inspiracija.html" class="nav-link nav-insp">Inspiracija</a>
      <a href="/kategorija/bambus-paneli" class="nav-link">Bambus Paneli</a>
      <a href="/kategorija/3d-letvice" class="nav-link">3D Letvice</a>
      <a href="/kategorija/akusticni-paneli" class="nav-link">Akustični</a>
      <a href="/kategorija/mdf" class="nav-link">MDF</a>
      <a href="/kategorija/aluminijum-lajsne" class="nav-link">Alu Lajsne</a>
      <a href="/kategorija/pu-kamen" class="nav-link">PU Kamen</a>
      <a href="/kategorija/flex-stone" class="nav-link">Flex Stone</a>
      <a href="/kategorija/spc-pod" class="nav-link">SPC Pod</a>
      <a href="decor-box.html" class="nav-link">Decor Box</a>
      <a href="faq.html" class="nav-link">Pitanja</a>
      <a href="about.html" class="nav-link">O Nama</a>
      <a href="contact.html" class="nav-link nav-cta">Kontakt</a>
    </nav>
    <a href="korpa.html" class="cart-icon-btn" aria-label="Korpa" style="position:relative;display:flex;align-items:center;justify-content:center;width:40px;height:40px;color:#c9a86c;font-size:18px;text-decoration:none;flex-shrink:0;margin-right:4px;">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-badge" style="display:none;position:absolute;top:3px;right:3px;background:#e74c3c;color:#fff;border-radius:50%;width:17px;height:17px;font-size:9px;font-weight:700;align-items:center;justify-content:center;line-height:1;"></span>
    </a>
    <button id="hamburger" class="hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- ===== PAGE HERO ===== -->
<section class="page-hero">
  <div class="container">
    <div class="page-hero-content">
      <div class="breadcrumb" id="breadcrumb">
        <a href="/">Početna</a>
        <i class="fas fa-chevron-right"></i>
        <span id="breadcrumb-label">Proizvodi</span>
      </div>
      <h1 class="section-title" id="page-title"<?= $cat && isset($catNames[$cat]) ? ' data-seo="1"' : '' ?>><?= $cat && isset($catNames[$cat])
        ? htmlspecialchars($catH1[$cat] ?? ($catNames[$cat] . ' – Zidni Paneli Podgorica'), ENT_QUOTES)
        : 'Zidni Paneli, Bambus Obloge i 3D Letvice u Podgorici' ?></h1>
      <p class="section-subtitle" id="page-subtitle" style="margin-left:auto;margin-right:auto;text-align:center;">
        Kompletna kolekcija obloga za zid u Podgorici — bambus paneli, 3D letvice, akustični paneli, MDF, PU kamen, Flex Stone i SPC podovi
      </p>
      <script>(function(){var p=new URLSearchParams(location.search),cat=p.get('cat')||p.get('category');if(!cat)return;var names={'bambus-paneli':'Bambus Paneli','bambus-drveni':'Drveni Paneli','bambus-tekstilni':'Tekstilni Paneli','bambus-mermerni':'Mermerni Paneli','bambus-metalni':'Metalni Paneli','bambus-kozni':'Kožni Paneli','3d-letvice':'3D Letvice','akusticni-paneli':'Akustični Paneli','aluminijum-lajsne':'Aluminijum Lajsne','spc-pod':'SPC Pod','pu-kamen':'PU Kamen','classic':'Classic Paneli','mdf':'MDF Paneli','flex-stone':'Flex Stone'};var subs={'bambus-paneli':'Odaberite tip panela','bambus-drveni':'Topla drvena tekstura bambusa – prirodan izgled koji unosi toplinu u svaki prostor','bambus-tekstilni':'Mekana tekstilna površina na bambus osnovi za sofisticiran i elegantan zid','bambus-mermerni':'Mermerni uzorak na bambus panelu – luksuz bez težine i cijene pravog mermera','bambus-metalni':'Metalni sjaj na bambus osnovi za moderan industrijski ili luksuzni enterijer','bambus-kozni':'Kožna površinska obrada za ekskluzivan i taktilno bogat zid','classic':'Klasični paneli s vremenski provjerenim uzorcima prilagođenim svakom stilu','3d-letvice':'Vertikalni rebrasti paneli koji igrom svjetla i sjene transformišu svaki ravni zid','akusticni-paneli':'Poboljšavaju akustiku i smanjuju buku, a pritom izgledaju kao pravi dekorativni element','aluminijum-lajsne':'Profili za završne detalje, ivice i prelaze – savršena finalna tačka svakog enterijera','spc-pod':'Vodootporni laminatni pod koji izdrži kupatilo, kuhinju i svakodnevnu upotrebu','pu-kamen':'Laki poliuretanski paneli koji izgledaju kao pravi kamen, a teže mnogo manje','mdf':'Kaneliran medijapan koji zidovima daje arhitektonski karakter i trodimenzionalnu dubinu','flex-stone':'Savitljivi kameni furnir koji se primjenjuje na ravne, zakrivljene i neravne površine'};var n=names[cat]||cat;var pt=document.getElementById('page-title');if(pt&&!pt.dataset.seo)pt.textContent=n;var b=document.getElementById('breadcrumb-label');if(b)b.textContent=n;var s=document.getElementById('page-subtitle');if(s)s.textContent=subs[cat]||'Pogledajte našu kolekciju';})();</script>
    </div>
  </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="products-section">
  <div class="container">

    <!-- Back bar (vidljiv samo kad je kategorija otvorena) -->
    <div class="back-bar" id="back-bar" style="display:none;">
      <a href="products.html" class="btn-back"><i class="fas fa-arrow-left"></i> Sve Kategorije</a>
      <h2 id="cat-title"></h2>
      <span class="count-badge" id="cat-count"></span>
    </div>

    <!-- Sadržaj (kategorije ili proizvodi) -->
    <div id="main-content">
      <div class="cat-grid" id="category-grid">
        <!-- Loading placeholders -->
        <div class="loading-placeholder" style="height:300px;border-radius:16px;"></div>
        <div class="loading-placeholder" style="height:300px;border-radius:16px;"></div>
        <div class="loading-placeholder" style="height:300px;border-radius:16px;"></div>
        <div class="loading-placeholder" style="height:300px;border-radius:16px;"></div>
      </div>
      <div class="products-grid" id="products-container" style="display:<?= $cat ? 'grid' : 'none' ?>;padding-top:20px;">
        <?php if ($cat): ?>
        <?php foreach ($_listProds as $p):
          $pO = (float)($p['price'] ?? 0);
          $pD = (int)($p['discount'] ?? 0);
          $pF = $pD > 0 ? round($pO * (1 - $pD / 100), 2) : $pO;
          $pHl = mb_substr(strip_tags($p['highlight'] ?? $p['description'] ?? ''), 0, 140);
        ?>
        <article class="product-card" data-ssr="1">
          <a href="/<?= mmhSlugProizvoda($p) ?>" class="product-link" style="text-decoration:none;color:inherit;display:block;">
            <div class="product-img">
              <?php
              // Opisan alt: Google Images je za dekoraciju stvarni izvor posjeta,
              // a "Mystic Marble" sam po sebi ne govori nista o tome sta je na slici.
              $altBoja = '';
              foreach (($p['features'] ?? []) as $af) {
                  if (str_starts_with($af, 'Boja:')) {
                      $altBoja = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', substr($af, 5)));
                      break;
                  }
              }
              $altTxt = trim(($p['name'] ?? '') . ' – ' . ($catNames[$p['category'] ?? ''] ?? 'zidni panel')
                        . ($altBoja ? ', ' . $altBoja : '') . ' | Make My Home Decor Podgorica');
              ?><img src="<?= htmlspecialchars($p['image'] ?? '') ?>" alt="<?= htmlspecialchars($altTxt) ?>" loading="lazy" width="400" height="300" style="width:100%;height:auto;display:block;">
            </div>
            <div class="product-body" style="padding:16px;">
              <h2 class="product-name" style="font-size:1em;font-weight:700;margin-bottom:8px;color:#1a1a1a;"><?= htmlspecialchars($p['name'] ?? '') ?></h2>
              <p class="product-price" style="margin-bottom:6px;">
                <?php if ($pD > 0): ?>
                <span style="font-weight:700;color:#c9a86c;"><?= number_format($pF, 2, ',', '.') ?> €</span>
                <span style="text-decoration:line-through;color:#767676;font-size:.85em;margin-left:6px;"><?= number_format($pO, 2, ',', '.') ?> €</span>
                <?php else: ?>
                <span style="font-weight:700;color:#c9a86c;"><?= number_format($pF, 2, ',', '.') ?> €</span>
                <?php endif; ?>
              </p>
              <?php if ($pHl): ?><p style="font-size:.82em;color:#666;line-height:1.5;"><?= htmlspecialchars($pHl) ?></p><?php endif; ?>
            </div>
          </a>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php if ($cat && isset($catVodic[$cat])):
        [$vU,$vN,$vO] = $catVodic[$cat]; ?>
      <aside class="kat-vodic">
        <i class="fas fa-circle-info"></i>
        <div>
          <a href="<?= $vU ?>"><?= htmlspecialchars($vN) ?> &rsaquo;</a>
          <p><?= htmlspecialchars($vO) ?></p>
        </div>
      </aside>
      <?php endif; ?>
      <?php if ($cat && isset($catSeoText[$cat])): ?>
      <section class="cat-seo-text" style="max-width:860px;margin:56px auto 0;padding:32px 24px 8px;border-top:1px solid rgba(0,0,0,0.08);color:#555;line-height:1.8;font-size:15px;">
        <h2 style="font-size:1.3em;color:#1a1a1a;margin-bottom:18px;"><?= htmlspecialchars($catName) ?> u Crnoj Gori</h2>
        <?= $catSeoText[$cat] ?>
      </section>
      <?php elseif (!$cat): ?>
      <section class="cat-seo-text" style="max-width:860px;margin:56px auto 0;padding:32px 24px 8px;border-top:1px solid rgba(0,0,0,0.08);color:#555;line-height:1.8;font-size:15px;">
        <h2 style="font-size:1.3em;color:#1a1a1a;margin-bottom:18px;">Zidne obloge i paneli za zid u Crnoj Gori</h2>
        <p>Make My Home Decor je specijalizovana prodavnica dekorativnih zidnih obloga u <strong>Podgorici</strong>. U našem showroomu u City Kvartu na jednom mjestu možete vidjeti i opipati kompletnu ponudu – <strong>bambus zidne panele</strong> (drvene, tekstilne, mermerne, metalne i kožne), <strong>3D dekorativne letvice</strong>, <strong>akustične panele</strong>, <strong>MDF kanelirane panele</strong>, <strong>PU dekorativni kamen</strong>, <strong>Flex Stone</strong> savitljivi kameni furnir, <strong>aluminijum lajsne</strong> i <strong>SPC vodootporne podove</strong>.</p>
        <p>Bambus paneli dimenzija 280x122cm prekrivaju 3,42 m² po komadu, pa se veliki zid obnovi brzo i sa malo spojeva. Vodootporni su, otporni na buđ i vlagu, vatrootporni klase B1 i imaju UV zaštitu. Kao moderna zamjena za klasičnu <strong>lamperiju</strong>, montiraju se lijepljenjem – bez bušenja, letvica i majstora, a mogu se postaviti i preko starog maltera, gipsa ili pločica. Popularni su za <strong>TV zid</strong>, akcentni zid iza kreveta, kupatilo, kuhinju, hodnike, kao i za kafiće, restorane, hotele i poslovne prostore.</p>
        <p>Sve cijene su jasno navedene uz svaki proizvod, a na stranici proizvoda imate <strong>kalkulator</strong> koji na osnovu dimenzija zida izračuna potreban broj komada. Dostava kurirskom službom širom <strong>Crne Gore</strong> – Podgorica, Nikšić, Bar, Budva, Herceg Novi, Kotor, Tivat, Bijelo Polje, Berane i ostali gradovi, uz mogućnost plaćanja pouzećem ili ličnog preuzimanja u showroomu.</p>
        <p>Niste sigurni šta odgovara vašem prostoru? Pogledajte <a href="faq.html" style="color:#c9a86c;font-weight:600;">česta pitanja</a> ili nas pozovite na <a href="tel:+38269105222" style="color:#c9a86c;font-weight:600;">069 105 222</a> – savjetujemo bez obaveze. Arhitektama, dizajnerima i izvođačima nudimo posebne uslove kroz program <a href="decor-box.html" style="color:#c9a86c;font-weight:600;">Decor Box</a>.</p>
      </section>
      <?php endif; ?>
      <script>(function(){
        var p=new URLSearchParams(location.search),cat=p.get('cat')||p.get('category');
        if(!cat)return;
        var names={'bambus-paneli':'Bambus Paneli','bambus-drveni':'Drveni Paneli','bambus-tekstilni':'Tekstilni Paneli','bambus-mermerni':'Mermerni Paneli','bambus-metalni':'Metalni Paneli','bambus-kozni':'Kožni Paneli','3d-letvice':'3D Letvice','akusticni-paneli':'Akustični Paneli','aluminijum-lajsne':'Aluminijum Lajsne','spc-pod':'SPC Pod','pu-kamen':'PU Kamen','classic':'Classic Paneli','mdf':'MDF Paneli','flex-stone':'Flex Stone'};
        var n=names[cat]||cat;
        var isParent=cat==='bambus-paneli';
        // Show back-bar immediately
        var bb=document.getElementById('back-bar');if(bb)bb.style.display='flex';
        var ct=document.getElementById('cat-title');if(ct)ct.textContent=n;
        if(isParent){
          // Keep category-grid visible (skeleton cat-cards match subcategory layout)
        }else{
          // Leaf: hide cat-grid, show product skeletons
          var cg=document.getElementById('category-grid');if(cg)cg.style.display='none';
          var pc=document.getElementById('products-container');
          if(pc){pc.style.display='grid';pc.innerHTML='<div class="loading-placeholder" style="height:360px;border-radius:16px;"></div>'.repeat(6);}
        }
      })();</script>
    </div>

  </div>
</section>

<!-- ===== CTA ===== -->
<section id="contact-cta" style="padding:80px 0;">
  <div class="container">
    <div class="cta-content" style="text-align:center;">
      <div class="gold-line" style="margin:0 auto 20px;"></div>
      <h2 class="section-title" style="color:#fff;">Ne Nalazite Šta Trebate?</h2>
      <p class="section-subtitle" style="color:rgba(255,255,255,0.6);margin-left:auto;margin-right:auto;text-align:center;">
        Kontaktirajte nas – možemo nabaviti specifične materijale ili Vam pomoći u odabiru
      </p>
      <div class="cta-actions">
        <a href="contact.html" class="btn btn-primary btn-lg">
          <i class="fas fa-envelope"></i> Pošalji Upit
        </a>
        <a href="tel:+38269105222" class="btn btn-outline btn-lg">
          <i class="fas fa-phone"></i> 069 105 222
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer id="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/" class="logo">
          <!-- OLD LOGO (backup):
      <div class="logo-icon"><i class="fas fa-home"></i></div>
      <div class="logo-text">
        <span class="name">Make My Home Decor</span>
        <span class="tagline">Dekorativni Bambus Paneli</span>
      </div>
-->
      <img src="images/logo-transparent.png" alt="Make My Home Decor" class="logo-img" width="567" height="567">
      <div class="logo-text">
        <span class="name">Make My Home Decor</span>
        <span class="tagline">Dekorativni Bambus Paneli</span>
      </div>
        </a>
        <p class="footer-desc">Premium zidni paneli i 3D letvice u Podgorici. Transformišite Vaš prostor.
        <span style="display:block;margin-top:16px;font-size:13px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:#c9a86c;">&#9654; Zapratite nas</span>
        </p>
        <div class="footer-social">
          <a href="https://www.instagram.com/makemyhome.decor" target="_blank" rel="noopener" class="social-btn" title="Instagram" style="background:#d62976;color:#fff;"><i class="fab fa-instagram"></i></a>
          <a href="https://www.facebook.com/61571886302133" target="_blank" rel="noopener" class="social-btn" title="Facebook" style="background:#1877f2;color:#fff;"><i class="fab fa-facebook-f"></i></a>
          <a href="https://wa.me/38269105222" target="_blank" rel="noopener" class="social-btn" title="WhatsApp" style="background:#25d366;color:#fff;"><i class="fab fa-whatsapp"></i></a>
          <a href="viber://contact?number=%2B38269105222" class="social-btn" title="Viber" style="background:#665cac;color:#fff;"><i class="fab fa-viber"></i></a>
          <a href="https://www.tiktok.com/@makemyhome.me" target="_blank" rel="noopener" class="social-btn" title="TikTok" style="background:#ee1d52;color:#fff;"><i class="fab fa-tiktok"></i></a>
          <a href="mailto:makemyhome.me@gmail.com" class="social-btn" title="Email" style="background:#c9a86c;color:#fff;"><i class="fas fa-envelope"></i></a>
        </div>
      </div>
      <div>
        <h3 class="footer-title">Navigacija</h3>
        <ul class="footer-links footer-links-grid">
          <li><a href="/"><i class="fas fa-chevron-right"></i> Početna</a></li>
          <li><a href="products.html"><i class="fas fa-chevron-right"></i> Svi Proizvodi</a></li>
          <li><a href="decor-box.html"><i class="fas fa-chevron-right"></i> Decor Box</a></li>
          <li><a href="inspiracija.html"><i class="fas fa-chevron-right"></i> Inspiracija</a></li>
          <li><a href="faq.html"><i class="fas fa-chevron-right"></i> Česta Pitanja</a></li>
          <li><a href="cjenovnik.html"><i class="fas fa-chevron-right"></i> Cijene</a></li>
          <li><a href="montaza.html"><i class="fas fa-chevron-right"></i> Montaža panela</a></li>
          <li><a href="about.html"><i class="fas fa-chevron-right"></i> O Nama</a></li>
          <li><a href="contact.html"><i class="fas fa-chevron-right"></i> Kontakt</a></li>
        </ul>
      </div>
      <div>
        <h3 class="footer-title">Kategorije</h3>
        <ul class="footer-links" id="footer-cats"></ul>
      </div>
      <div>
        <h3 class="footer-title">Kontakt</h3>
        <ul class="footer-contact-list">
          <li><i class="fas fa-map-marker-alt"></i><span>Vojvode Maša Đurovića 41, City Kvart, Podgorica</span></li>
          <li><i class="fas fa-phone"></i><span><a href="tel:+38269105222">069 105 222</a></span></li>
          <li><i class="fas fa-envelope"></i><span><a href="mailto:makemyhome.me@gmail.com">makemyhome.me@gmail.com</a></span></li>
          <li><i class="fas fa-clock"></i><span>Pon–Pet: 09:00–20:00 | Sub: 10:00–17:00</span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Make My Home Decor. Sva prava zadržana.</p>
      <p class="footer-pravne"><a href="uslovi.html">Uslovi kupovine</a><a href="reklamacije.html">Reklamacije i povrat</a><a href="privatnost.html">Politika privatnosti</a></p>
    </div>
  </div>
</footer>

<div id="whatsapp-float">
  <a href="https://wa.me/38269105222?text=Zdravo%2C%20zanima%20me%20vi%C5%A1e%20informacija%20o%20va%C5%A1im%20zidnim%20panelima." target="_blank" rel="noopener" aria-label="Kontaktirajte nas na WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="30" height="30" fill="white"><path d="M16 0C7.164 0 0 7.163 0 16c0 2.822.736 5.469 2.027 7.774L0 32l8.469-2.003A15.93 15.93 0 0 0 16 32c8.836 0 16-7.163 16-16S24.836 0 16 0zm0 29.333a13.27 13.27 0 0 1-6.771-1.856l-.485-.288-5.028 1.188 1.215-4.895-.316-.503A13.247 13.247 0 0 1 2.667 16C2.667 8.636 8.637 2.667 16 2.667S29.333 8.636 29.333 16 23.363 29.333 16 29.333zm7.27-9.907c-.398-.199-2.355-1.162-2.72-1.295-.365-.133-.63-.199-.896.199-.265.398-1.028 1.295-1.26 1.56-.232.265-.464.298-.862.1-.398-.199-1.681-.62-3.203-1.977-1.184-1.056-1.984-2.36-2.216-2.758-.232-.398-.025-.613.174-.811.178-.178.398-.465.597-.697.199-.232.265-.398.398-.663.133-.265.066-.497-.033-.696-.1-.199-.896-2.162-1.228-2.96-.323-.776-.651-.67-.896-.683l-.763-.013c-.265 0-.696.1-.1061.497-.365.398-1.393 1.362-1.393 3.322s1.427 3.854 1.626 4.119c.199.265 2.808 4.286 6.804 6.014.951.41 1.693.655 2.271.838.954.303 1.823.26 2.51.158.765-.114 2.355-.963 2.688-1.893.333-.93.333-1.727.232-1.893-.1-.165-.365-.265-.763-.464z"/></svg>
  </a>
  <span class="wa-tooltip">Pišite nam na WhatsApp</span>
</div>

<button id="scroll-top" aria-label="Nazad na vrh"><i class="fas fa-chevron-up"></i></button>

<script src="js/main-v4.js?v=5"></script>
<script src="js/products.js?v=45"></script>
<script src="js/cart.js?v=3"></script>
<script>
  initProductsPage();
</script>
<script src="js/analytics-events.js?v=3" defer></script>
</body>
</html>
