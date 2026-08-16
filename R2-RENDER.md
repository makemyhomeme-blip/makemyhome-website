# R2 — sta ostane poslije JavaScripta

Napravljeno: 2026-08-16 19:35 UTC

149 adresa iz sitemapa. Svaka ucitana dva puta u istom Chromiumu:
jednom sa **iskljucenim** JavaScriptom, jednom sa **ukljucenim** (`networkidle` + 2 s).
Broji se u zivom DOM-u, isto u oba slucaja — zato su brojevi uporedivi.

> Mjereno na lokalnoj kopiji (`php -S` + `alat/posrednik.mjs`) jer pregledac iz
> ovog okruzenja ne moze do zivog sajta. Kolona `izvor` je povucena curl-om sa
> **zivog** sajta, kao kontrola.

**[!!]** iscrtano manje nego bez JS-a · **[!]** iscrtano znatno vise · **[i]** uredu

## Zakljucak

- stranica sa **[!!]** (JS gasi sadrzaj): **1**
- stranica sa **[!]** (JS dodaje sadrzaj): **0**
- stranica koje se nisu ucitale: **0**

### [!!] JavaScript gasi sadrzaj koji server ispise

**/kategorija/bambus-paneli**
- [!!] JS ukloni 2 h2


## Sve stranice

`kart` = kartice proizvoda · `bez` = bez JavaScripta · `sa` = poslije JavaScripta

| adresa | € bez | € sa | kart bez | kart sa | h2 bez | h2 sa | tekst bez | tekst sa | status |
|---|---|---|---|---|---|---|---|---|---|
| `/` | 8 | 8 | 4 | 4 | 11 | 11 | 7403 | 7586 | [i] |
| `/products.html` | 0 | 0 | 0 | 0 | 11 | 11 | 3685 | 3685 | [i] |
| `/cjenovnik.html` | 50 | 52 | 0 | 0 | 6 | 6 | 5693 | 5830 | [i] |
| `/inspiracija.html` | 0 | 0 | 0 | 0 | 1 | 1 | 4696 | 4696 | [i] |
| `/montaza.html` | 1 | 1 | 0 | 0 | 6 | 6 | 6597 | 6597 | [i] |
| `/faq.html` | 12 | 12 | 0 | 0 | 6 | 6 | 8564 | 8564 | [i] |
| `/about.html` | 0 | 0 | 0 | 0 | 4 | 4 | 2699 | 2699 | [i] |
| `/contact.html` | 1 | 1 | 0 | 0 | 3 | 3 | 3384 | 3384 | [i] |
| `/decor-box.html` | 0 | 0 | 0 | 0 | 5 | 5 | 5401 | 5401 | [i] |
| `/paneli-za-kupatilo.html` | 5 | 5 | 0 | 0 | 8 | 8 | 5421 | 5421 | [i] |
| `/tv-zid.html` | 1 | 1 | 0 | 0 | 8 | 8 | 4677 | 4677 | [i] |
| `/paneli-ili-lamperija.html` | 0 | 0 | 0 | 0 | 6 | 6 | 4590 | 4590 | [i] |
| `/spc-ili-laminat.html` | 0 | 0 | 0 | 0 | 9 | 9 | 4358 | 4358 | [i] |
| `/akusticni-paneli-kancelarija.html` | 0 | 0 | 0 | 0 | 8 | 8 | 4636 | 4636 | [i] |
| `/dostava-crna-gora.html` | 2 | 2 | 0 | 0 | 7 | 7 | 4314 | 4314 | [i] |
| `/uslovi.html` | 1 | 1 | 0 | 0 | 9 | 9 | 4461 | 4461 | [i] |
| `/reklamacije.html` | 0 | 0 | 0 | 0 | 8 | 8 | 3768 | 3768 | [i] |
| `/privatnost.html` | 0 | 0 | 0 | 0 | 9 | 9 | 4866 | 4866 | [i] |
| `/kategorija/bambus-paneli` | 78 | 78 | 39 | 39 | 51 | 49 | 7495 | 7828 | **[!!]** |
| `/kategorija/bambus-drveni` | 18 | 18 | 9 | 9 | 21 | 21 | 3686 | 3667 | [i] |
| `/kategorija/bambus-tekstilni` | 20 | 20 | 9 | 9 | 21 | 21 | 4256 | 4235 | [i] |
| `/kategorija/bambus-mermerni` | 26 | 26 | 13 | 13 | 25 | 25 | 4268 | 4248 | [i] |
| `/kategorija/bambus-metalni` | 8 | 8 | 4 | 4 | 16 | 16 | 3267 | 3244 | [i] |
| `/kategorija/bambus-kozni` | 9 | 9 | 4 | 4 | 16 | 16 | 3227 | 3183 | [i] |
| `/kategorija/3d-letvice` | 56 | 56 | 28 | 28 | 40 | 40 | 6553 | 6544 | [i] |
| `/kategorija/akusticni-paneli` | 21 | 21 | 9 | 9 | 21 | 21 | 4818 | 4825 | [i] |
| `/kategorija/aluminijum-lajsne` | 10 | 10 | 8 | 8 | 20 | 20 | 4165 | 4159 | [i] |
| `/kategorija/spc-pod` | 12 | 12 | 5 | 5 | 17 | 17 | 4547 | 4527 | [i] |
| `/kategorija/pu-kamen` | 18 | 18 | 9 | 9 | 21 | 21 | 4713 | 4693 | [i] |
| `/kategorija/classic` | 8 | 8 | 4 | 4 | 16 | 16 | 3327 | 3302 | [i] |
| `/kategorija/mdf` | 14 | 14 | 5 | 5 | 17 | 17 | 3931 | 3926 | [i] |
| `/kategorija/flex-stone` | 22 | 22 | 10 | 10 | 22 | 22 | 5004 | 4998 | [i] |
| `/paneli/drveni-panel-golden-teak` | 16 | 18 | 6 | 6 | 3 | 3 | 5617 | 5822 | [i] |
| `/paneli/drveni-panel-mocha-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5518 | 5721 | [i] |
| `/paneli/drveni-panel-havana-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5448 | 5652 | [i] |
| `/paneli/drveni-panel-honey-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5446 | 5649 | [i] |
| `/paneli/drveni-panel-espresso-teak` | 16 | 18 | 6 | 6 | 3 | 3 | 5482 | 5689 | [i] |
| `/paneli/drveni-panel-nordic-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5510 | 5714 | [i] |
| `/paneli/drveni-panel-amber-oak` | 16 | 17 | 6 | 6 | 3 | 3 | 5432 | 5486 | [i] |
| `/paneli/drveni-panel-smoke-oak` | 16 | 17 | 6 | 6 | 3 | 3 | 5424 | 5478 | [i] |
| `/paneli/drveni-panel-dark-ash` | 16 | 17 | 6 | 6 | 3 | 3 | 5534 | 5588 | [i] |
| `/paneli/tekstilni-panel-perla` | 16 | 18 | 6 | 6 | 3 | 3 | 5422 | 5621 | [i] |
| `/paneli/tekstilni-panel-calla` | 16 | 17 | 6 | 6 | 3 | 3 | 5400 | 5454 | [i] |
| `/paneli/tekstilni-panel-grigio` | 16 | 18 | 6 | 6 | 3 | 3 | 5387 | 5587 | [i] |
| `/paneli/tekstilni-panel-glacier` | 16 | 18 | 6 | 6 | 3 | 3 | 5409 | 5610 | [i] |
| `/paneli/tekstilni-panel-slate` | 16 | 17 | 6 | 6 | 3 | 3 | 5420 | 5474 | [i] |
| `/paneli/tekstilni-panel-blanc` | 16 | 17 | 6 | 6 | 3 | 3 | 5637 | 5691 | [i] |
| `/paneli/tekstilni-panel-siena` | 16 | 17 | 6 | 6 | 3 | 3 | 5409 | 5463 | [i] |
| `/paneli/tekstilni-panel-pura` | 16 | 17 | 6 | 6 | 3 | 3 | 5429 | 5483 | [i] |
| `/paneli/tekstilni-panel-deva` | 16 | 18 | 6 | 6 | 3 | 3 | 5458 | 5656 | [i] |
| `/paneli/mermerni-panel-mystic-marble` | 16 | 17 | 6 | 6 | 3 | 3 | 5523 | 5577 | [i] |
| `/paneli/mermerni-panel-desert-stone` | 16 | 17 | 6 | 6 | 3 | 3 | 5487 | 5541 | [i] |
| `/paneli/mermerni-panel-travertino` | 16 | 17 | 6 | 6 | 3 | 3 | 5610 | 5664 | [i] |
| `/paneli/mermerni-panel-mercury-marble` | 16 | 17 | 6 | 6 | 3 | 3 | 5655 | 5709 | [i] |
| `/paneli/mermerni-panel-sahara` | 16 | 17 | 6 | 6 | 3 | 3 | 5403 | 5457 | [i] |
| `/paneli/mermerni-panel-beige-marble` | 16 | 17 | 6 | 6 | 3 | 3 | 5456 | 5510 | [i] |
| `/paneli/mermerni-panel-lava-stone` | 16 | 17 | 6 | 6 | 3 | 3 | 5419 | 5473 | [i] |
| `/paneli/mermerni-panel-urban-concrete` | 16 | 17 | 6 | 6 | 3 | 3 | 5493 | 5547 | [i] |
| `/paneli/mermerni-panel-nordic-concrete` | 16 | 17 | 6 | 6 | 3 | 3 | 5538 | 5592 | [i] |
| `/paneli/mermerni-panel-noir-stone` | 16 | 17 | 6 | 6 | 3 | 3 | 5393 | 5447 | [i] |
| `/paneli/mermerni-panel-dark-luxe` | 16 | 17 | 6 | 6 | 3 | 3 | 5434 | 5488 | [i] |
| `/paneli/mermerni-panel-white-marble` | 16 | 17 | 6 | 6 | 3 | 3 | 5428 | 5482 | [i] |
| `/paneli/mermerni-panel-sw002` | 16 | 17 | 6 | 6 | 3 | 3 | 5685 | 5739 | [i] |
| `/paneli/classic-panel-terrazzo` | 16 | 18 | 6 | 6 | 3 | 3 | 5228 | 5430 | [i] |
| `/paneli/classic-panel-midnight-black` | 16 | 18 | 6 | 6 | 3 | 3 | 5241 | 5449 | [i] |
| `/paneli/classic-panel-soft-beige` | 16 | 17 | 6 | 6 | 3 | 3 | 5166 | 5220 | [i] |
| `/paneli/classic-panel-pure-white` | 16 | 18 | 6 | 6 | 3 | 3 | 5212 | 5416 | [i] |
| `/paneli/kozni-panel-pw007-hermes-narandzasta` | 16 | 17 | 6 | 6 | 3 | 3 | 5653 | 5707 | [i] |
| `/paneli/kozni-panel-pw001-bordo-crvena` | 16 | 17 | 6 | 6 | 3 | 3 | 5631 | 5685 | [i] |
| `/paneli/kozni-panel-pw005-ledena-siva` | 16 | 17 | 6 | 6 | 3 | 3 | 5557 | 5611 | [i] |
| `/paneli/kozni-panel-pw003-tamni-antracit` | 16 | 17 | 6 | 6 | 3 | 3 | 5640 | 5694 | [i] |
| `/paneli/metalni-panel-brushed-gold` | 16 | 17 | 6 | 6 | 3 | 3 | 5482 | 5536 | [i] |
| `/paneli/metalni-panel-raw-steel` | 16 | 17 | 6 | 6 | 3 | 3 | 5462 | 5516 | [i] |
| `/paneli/metalni-panel-champagne-metal` | 16 | 17 | 6 | 6 | 3 | 3 | 5412 | 5466 | [i] |
| `/paneli/metalni-panel-js0014` | 16 | 17 | 6 | 6 | 3 | 3 | 5567 | 5621 | [i] |
| `/paneli/3d-letvica-golden-teak` | 17 | 19 | 6 | 6 | 3 | 3 | 5625 | 5816 | [i] |
| `/paneli/3d-letvica-mocha-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5496 | 5700 | [i] |
| `/paneli/3d-letvica-espresso-teak` | 16 | 18 | 6 | 6 | 3 | 3 | 5504 | 5697 | [i] |
| `/paneli/3d-letvica-honey-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5470 | 5659 | [i] |
| `/paneli/3d-letvica-nordic-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5475 | 5665 | [i] |
| `/paneli/3d-letvica-topli-tik-mat` | 16 | 17 | 6 | 6 | 3 | 3 | 5494 | 5551 | [i] |
| `/paneli/3d-letvica-tamni-orah-gloss` | 16 | 17 | 6 | 6 | 3 | 3 | 5509 | 5566 | [i] |
| `/paneli/3d-letvica-havana-oak` | 16 | 18 | 6 | 6 | 3 | 3 | 5454 | 5644 | [i] |
| `/paneli/3d-letvica-topli-orah` | 16 | 17 | 6 | 6 | 3 | 3 | 5515 | 5571 | [i] |
| `/paneli/3d-letvica-obsidian` | 16 | 18 | 6 | 6 | 3 | 3 | 5480 | 5668 | [i] |
| `/paneli/3d-letvica-prirodni-javor` | 16 | 17 | 6 | 6 | 3 | 3 | 5476 | 5532 | [i] |
| `/paneli/3d-letvica-midnight-black` | 16 | 18 | 6 | 6 | 3 | 3 | 5490 | 5684 | [i] |
| `/paneli/3d-letvica-pure-white` | 16 | 18 | 6 | 6 | 3 | 3 | 5407 | 5597 | [i] |
| `/paneli/3d-letvica-hladno-siva-teksturisana` | 16 | 17 | 6 | 6 | 3 | 3 | 5531 | 5588 | [i] |
| `/paneli/3d-letvica-krem-bijela` | 16 | 17 | 6 | 6 | 3 | 3 | 5481 | 5538 | [i] |
| `/paneli/3d-letvica-topla-bez` | 16 | 17 | 6 | 6 | 3 | 3 | 5397 | 5454 | [i] |
| `/paneli/3d-letvica-topla-bez-uzi-profil` | 16 | 17 | 6 | 6 | 3 | 3 | 5447 | 5504 | [i] |
| `/paneli/3d-letvica-terrazzo` | 16 | 18 | 6 | 6 | 3 | 3 | 5480 | 5668 | [i] |
| `/paneli/3d-letvica-hladno-siva` | 16 | 17 | 6 | 6 | 3 | 3 | 5407 | 5464 | [i] |
| `/paneli/3d-letvica-deva` | 16 | 18 | 6 | 6 | 3 | 3 | 5300 | 5484 | [i] |
| `/paneli/3d-letvica-grigio` | 16 | 18 | 6 | 6 | 3 | 3 | 5369 | 5555 | [i] |
| `/paneli/3d-letvica-glacier` | 16 | 18 | 6 | 6 | 3 | 3 | 5376 | 5563 | [i] |
| `/paneli/3d-letvica-perla` | 16 | 18 | 6 | 6 | 3 | 3 | 5405 | 5590 | [i] |
| `/paneli/3d-letvica-zlatna-zuta` | 16 | 17 | 6 | 6 | 3 | 3 | 5443 | 5500 | [i] |
| `/paneli/3d-letvica-prirodni-hrast` | 15 | 15 | 6 | 6 | 3 | 3 | 5310 | 5449 | [i] |
| `/paneli/3d-letvica-tamna-siva` | 15 | 15 | 6 | 6 | 3 | 3 | 5392 | 5531 | [i] |
| `/paneli/3d-letvica-topli-mahagonij` | 15 | 15 | 6 | 6 | 3 | 3 | 5413 | 5552 | [i] |
| `/paneli/3d-letvica-bijela-premium-profil` | 15 | 15 | 6 | 6 | 3 | 3 | 5529 | 5668 | [i] |
| `/paneli/akusticni-panel-aku063-prirodni-hrast` | 16 | 17 | 6 | 6 | 3 | 3 | 5602 | 5658 | [i] |
| `/paneli/akusticni-panel-aku064-orasasti-hrast` | 16 | 17 | 6 | 6 | 3 | 3 | 5491 | 5547 | [i] |
| `/paneli/akusticni-panel-aku060-topli-orah` | 16 | 17 | 6 | 6 | 3 | 3 | 5534 | 5590 | [i] |
| `/paneli/akusticni-panel-aku041-tamni-antracit` | 16 | 17 | 6 | 6 | 3 | 3 | 5560 | 5616 | [i] |
| `/paneli/akusticni-panel-aku005-crni-talas` | 16 | 17 | 6 | 6 | 3 | 3 | 5541 | 5597 | [i] |
| `/paneli/akusticni-panel-aku051-zlatni-hrast-geometrik` | 16 | 17 | 6 | 6 | 3 | 3 | 5656 | 5712 | [i] |
| `/paneli/akusticni-panel-aku050-bijeli-pepeo-geometrik` | 16 | 17 | 6 | 6 | 3 | 3 | 5650 | 5706 | [i] |
| `/paneli/akusticni-panel-aku054-medeni-hrast-linear` | 16 | 17 | 6 | 6 | 3 | 3 | 5691 | 5747 | [i] |
| `/paneli/akusticni-panel-aku053-zlatni-bambus-linear` | 16 | 17 | 6 | 6 | 3 | 3 | 5606 | 5662 | [i] |
| `/paneli/alu-lajsna-l1-crna-srednja-lajsna` | 8 | 8 | 6 | 6 | 3 | 3 | 4851 | 4851 | [i] |
| `/paneli/alu-lajsna-l2-crna-pocetna-zavrsna-lajsna` | 8 | 8 | 6 | 6 | 3 | 3 | 4917 | 4917 | [i] |
| `/paneli/alu-lajsna-l3-crna-ugaona-lajsna-spoljni-ugao` | 8 | 8 | 6 | 6 | 3 | 3 | 4875 | 4875 | [i] |
| `/paneli/alu-lajsna-l4-crna-led-lajsna` | 8 | 8 | 6 | 6 | 3 | 3 | 4920 | 4920 | [i] |
| `/paneli/alu-lajsna-l5-bronzana-srednja-lajsna` | 8 | 8 | 6 | 6 | 3 | 3 | 4861 | 4861 | [i] |
| `/paneli/alu-lajsna-l6-bronzana-pocetna-zavrsna-lajsna` | 8 | 8 | 6 | 6 | 3 | 3 | 4901 | 4901 | [i] |
| `/paneli/alu-lajsna-l7-bronzana-ugaona-lajsna-spoljni-ugao` | 8 | 8 | 6 | 6 | 3 | 3 | 4862 | 4862 | [i] |
| `/paneli/alu-lajsna-l8-bronzana-led-lajsna` | 8 | 8 | 6 | 6 | 3 | 3 | 4889 | 4889 | [i] |
| `/paneli/spc04-spc-laminat` | 15 | 16 | 6 | 6 | 3 | 3 | 5412 | 5502 | [i] |
| `/paneli/spc05-spc-laminat` | 15 | 16 | 6 | 6 | 3 | 3 | 5380 | 5470 | [i] |
| `/paneli/spc06-spc-laminat` | 15 | 16 | 6 | 6 | 3 | 3 | 5385 | 5475 | [i] |
| `/paneli/spc07-spc-laminat-tile-format` | 15 | 16 | 6 | 6 | 3 | 3 | 5475 | 5565 | [i] |
| `/paneli/spc08-spc-laminat-tile-format` | 15 | 16 | 6 | 6 | 3 | 3 | 5476 | 5566 | [i] |
| `/paneli/pu-kamen-poliuretanski-kamen-bijeli` | 16 | 17 | 6 | 6 | 3 | 3 | 5566 | 5657 | [i] |
| `/paneli/pu-stone-talas-beli-xl` | 16 | 17 | 6 | 6 | 3 | 3 | 5312 | 5403 | [i] |
| `/paneli/pu-stone-talas-bez-xl` | 16 | 17 | 6 | 6 | 3 | 3 | 5294 | 5385 | [i] |
| `/paneli/pu-stone-talas-khaki-xl` | 16 | 17 | 6 | 6 | 3 | 3 | 5339 | 5430 | [i] |
| `/paneli/pu-stone-talas-siva-xl` | 16 | 17 | 6 | 6 | 3 | 3 | 5531 | 5622 | [i] |
| `/paneli/pu-stone-mushroom-beli` | 16 | 17 | 6 | 6 | 3 | 3 | 5543 | 5634 | [i] |
| `/paneli/pu-stone-mushroom-bez` | 16 | 17 | 6 | 6 | 3 | 3 | 5542 | 5633 | [i] |
| `/paneli/pu-stone-mushroom-braon` | 16 | 17 | 6 | 6 | 3 | 3 | 5493 | 5584 | [i] |
| `/paneli/pu-stone-mushroom-crni` | 16 | 17 | 6 | 6 | 3 | 3 | 5473 | 5564 | [i] |
| `/paneli/mdf-panel-mdf004-deblji` | 16 | 17 | 6 | 6 | 3 | 3 | 5479 | 5570 | [i] |
| `/paneli/mdf-panel-mdf005-tanji` | 16 | 17 | 6 | 6 | 3 | 3 | 5461 | 5552 | [i] |
| `/paneli/mdf-panel-mdf001` | 16 | 17 | 6 | 6 | 3 | 3 | 5591 | 5682 | [i] |
| `/paneli/mdf-panel-mdf002` | 16 | 17 | 6 | 6 | 3 | 3 | 5495 | 5586 | [i] |
| `/paneli/mdf-panel-mdf003` | 16 | 17 | 6 | 6 | 3 | 3 | 5489 | 5580 | [i] |
| `/paneli/flex-stone-linear-travertine-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6168 | 6259 | [i] |
| `/paneli/flex-stone-weaving-beige-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6183 | 6274 | [i] |
| `/paneli/flex-stone-weaving-khaki-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6282 | 6373 | [i] |
| `/paneli/flex-stone-rouge-granite-beige-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6270 | 6361 | [i] |
| `/paneli/flex-stone-white-linear-travertine-1-2x0-6m-fleksibilni` | 16 | 17 | 6 | 6 | 3 | 3 | 6195 | 6286 | [i] |
| `/paneli/flex-stone-luna-travertine-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6192 | 6283 | [i] |
| `/paneli/flex-stone-dolomitic-travertine-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6276 | 6367 | [i] |
| `/paneli/flex-stone-romantine-white-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6161 | 6252 | [i] |
| `/paneli/flex-stone-romantine-yellow-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6207 | 6298 | [i] |
| `/paneli/flex-stone-white-milan-travertine-1-2x0-6m-fleksibilni-kamen` | 16 | 17 | 6 | 6 | 3 | 3 | 6259 | 6350 | [i] |

## Kategorije posebno

| kategorija | € izvor (zivi) | € bez JS | € sa JS | kart izvor | kart bez | kart sa | status |
|---|---|---|---|---|---|---|---|
| `/kategorija/bambus-paneli` | 81 | 78 | 78 | 39 | 39 | 39 | **[!!]** |
| `/kategorija/bambus-drveni` | 21 | 18 | 18 | 9 | 9 | 9 | [i] |
| `/kategorija/bambus-tekstilni` | 23 | 20 | 20 | 9 | 9 | 9 | [i] |
| `/kategorija/bambus-mermerni` | 29 | 26 | 26 | 13 | 13 | 13 | [i] |
| `/kategorija/bambus-metalni` | 11 | 8 | 8 | 4 | 4 | 4 | [i] |
| `/kategorija/bambus-kozni` | 12 | 9 | 9 | 4 | 4 | 4 | [i] |
| `/kategorija/3d-letvice` | 59 | 56 | 56 | 28 | 28 | 28 | [i] |
| `/kategorija/akusticni-paneli` | 24 | 21 | 21 | 9 | 9 | 9 | [i] |
| `/kategorija/aluminijum-lajsne` | 13 | 10 | 10 | 8 | 8 | 8 | [i] |
| `/kategorija/spc-pod` | 15 | 12 | 12 | 5 | 5 | 5 | [i] |
| `/kategorija/pu-kamen` | 21 | 18 | 18 | 9 | 9 | 9 | [i] |
| `/kategorija/classic` | 11 | 8 | 8 | 4 | 4 | 4 | [i] |
| `/kategorija/mdf` | 17 | 14 | 14 | 5 | 5 | 5 | [i] |
| `/kategorija/flex-stone` | 25 | 22 | 22 | 10 | 10 | 10 | [i] |
