# Water body image inventory

Reviewed 2026-10-18 against baseline `dc8366a`: **28 profiles, eight original images preserved, ten new images, ten placeholders**. No database import, migration, coordinate or scientific-data change. Existing metadata takes precedence; `database/data/explorer-images.json` supplies only previously empty images, matching exact canonical name and municipality.

## Original eight preserved

Original files and attribution records are unchanged. The original catalogues remain the source of these records; [research sources](RESEARCH_SOURCES.md) retains their earlier permission review. Laguna de Bay uses the existing false-color satellite image, explicitly not a water-quality visualization.

| Profile | Local file | Photographer / credit | Source | Permission |
| --- | --- | --- | --- | --- |
| Sampaloc Lake | `/images/water-bodies/sampaloc.jpg` | Judgefloro | [Original](https://commons.wikimedia.org/wiki/File:7260San_Pablo_City_Barangays_Landmarks_20.jpg) | [CC0; 960 px display thumbnail](http://creativecommons.org/publicdomain/zero/1.0/deed.en) |
| Yambo Lake | `/images/water-bodies/yambo.jpg` | Barrera marquez | [Original](https://commons.wikimedia.org/wiki/File:Lake_Yambo.jpg) | [CC BY 3.0; 960 px display thumbnail](https://creativecommons.org/licenses/by/3.0) |
| Pandin Lake | `/images/water-bodies/pandin.jpg` | Barrera marquez | [Original](https://commons.wikimedia.org/wiki/File:Lake_Pandin.jpg) | [CC BY 3.0; 960 px display thumbnail](https://creativecommons.org/licenses/by/3.0) |
| Bunot Lake | `/images/water-bodies/bunot-20240512.jpg` | Ian Lopez (Ianlopez1115) | [Original](https://commons.wikimedia.org/wiki/File:Bunot_Lake_20240512.jpg) | [Attribution only; unchanged photograph](https://commons.wikimedia.org/wiki/User:Ianlopez1115/License_AttributionOnly) |
| Calibato Lake | `/images/water-bodies/calibato.jpg` | Barrera marquez | [Original](https://commons.wikimedia.org/wiki/File:Lake_Calibato.jpg) | [CC BY 3.0; 960 px display thumbnail](https://creativecommons.org/licenses/by/3.0) |
| Caliraya Lake | `/images/water-bodies/caliraya.jpg` | Sonnyboylopez | [Original](https://commons.wikimedia.org/wiki/File:Caliraya_Lake_Spillway.JPG) | [CC BY-SA 3.0; 960 px display thumbnail](https://creativecommons.org/licenses/by-sa/3.0) |
| Laguna de Bay | `/images/water-bodies/laguna-de-bay.jpg` | Individual creator not specified by source | [Original](https://commons.wikimedia.org/wiki/File:Laguna_de_Bay_zoom.jpg) | [Public domain; 960 px display thumbnail](https://commons.wikimedia.org/wiki/File:Laguna_de_Bay_zoom.jpg) |
| Buruwisan Falls | `/images/water-bodies/buruwisan.jpg` | Dionisia.besas | [Original](https://commons.wikimedia.org/wiki/File:Buruwisan_Falls.jpg) | [CC BY-SA 4.0; 960 px display thumbnail](https://creativecommons.org/licenses/by-sa/4.0/) |

Original SHA-256 checksums (rechecked before commit):

| Filename | SHA-256 |
| --- | --- |
| bunot-20240512.jpg | `ddd83d873fe5dd5a042c2e0d86dfcba98dc9f07e7ff1b73f76cba5be14cec1d7` |
| buruwisan.jpg | `17fa17737d021941fae7704ddb0a52f3b68b2a0ae5b4255512a4009ad06e01a8` |
| calibato.jpg | `a639ba4c91080ca9fe840c4a80f1ea2deca3adf6fba7fa5dfe1301fb9356b858` |
| caliraya.jpg | `02f6e6948852ab1b312b0276d8cd4e69ea127429af871f76828c82f5d5a1f670` |
| laguna-de-bay.jpg | `fa2fa77521d22447b067fd32a05bb07fbb0b6010957c6c7d108f6b1e4afa536a` |
| pandin.jpg | `ffe6afacc9b007a3747ebf7ef648a4d41010bc2edf8b61c122dbddb64414d6aa` |
| sampaloc.jpg | `a56468721dd460e8766138becb2c64fcc5c5be12c345c2eef031aa07f2e49a7c` |
| yambo.jpg | `d0cce4e6ff49e63b632a09ae2ab93c482df32b0af85a02f5327cfe9af0999814` |

## Ten new local photographs

All are from Wikimedia Commons file pages with explicit licenses; author, original-page and license links appear in both Overview and Research & Sources. Required credit is the named author plus linked license/source and the recorded modification notice. ShareAlike photographs retain their source license. Photograph dates are historical capture dates, not present-condition assessments. Review date is not substituted for capture date.

| Profile | Photographer | Photograph date | Source | License | Local filename; dimensions; bytes |
| --- | --- | --- | --- | --- | --- |
| Mohicap Lake | Johnpaulo5860 | 2019-07-21 | [Original](https://commons.wikimedia.org/wiki/File:Mohicap_lake_environs.jpg) | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0) | `mohicap.jpg`; 960 x 720; 127841 |
| Pagsanjan Falls | Andrew Martin | 2008-02-18 | [Original](https://commons.wikimedia.org/wiki/File:Pagsanjan_Falls,_Laguna,_Philippines_-_panoramio.jpg) | [CC BY 3.0](https://creativecommons.org/licenses/by/3.0) | `pagsanjan-falls.jpg`; 960 x 640; 159868 |
| Hulugan Falls | Michael Angelo Luna | 2023-08-31 | [Original](https://commons.wikimedia.org/wiki/File:Hulugan_Falls.jpg) | [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0) | `hulugan-falls.jpg`; 686 x 960; 127093 |
| Taytay Falls | mollyboy | 2009-05-29 | [Original](https://commons.wikimedia.org/wiki/File:Taytay_falls_Majayjay.jpg) | [CC BY 2.0](https://creativecommons.org/licenses/by/2.0) | `taytay-falls.jpg`; 960 x 720; 238645 |
| Bukal Falls | Calbon69 | 2019-10-06 | [Original](https://commons.wikimedia.org/wiki/File:Bukal_falls_majayjay_laguna.jpg) | [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0) | `bukal-falls.jpg`; 720 x 960; 211944 |
| Lumot Lake | Judgefloro | 2019-06-18 | [Original](https://commons.wikimedia.org/wiki/File:188Lumot,_Cavinti,_Laguna_11.jpg) | [CC0](http://creativecommons.org/publicdomain/zero/1.0/deed.en) | `lumot.jpg`; 960 x 720; 165364 |
| Balanac River | Ramon FVelasquez | 2013-08-29 | [Original](https://commons.wikimedia.org/wiki/File:Pagsanjan,LagunaRiverjf4388_05.JPG) | [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0) | `balanac-river.jpg`; 960 x 720; 112463 |
| Tunasan River | Ramon FVelasquez | 2013-07-27 | [Original](https://commons.wikimedia.org/wiki/File:TunasanRiverjf2203_01.JPG) | [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0) | `tunasan-river.jpg`; 960 x 720; 226123 |
| Bumbungan River | Sheila mae Pelausa | 2021-05-26 | [Original](https://commons.wikimedia.org/wiki/File:Breathtaking_view_of_Pagsanjan_River.jpg) | [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0) | `bumbungan-river.jpg`; 960 x 498; 102468 |
| Santa Cruz River | Judgefloro | 2019-06-05 | [Original](https://commons.wikimedia.org/wiki/File:159Santa_Cruz,_Laguna_National_Highway_35.jpg) | [CC0](http://creativecommons.org/publicdomain/zero/1.0/deed.en) | `santa-cruz-river.jpg`; 960 x 720; 189883 |

Optimization: downloaded source thumbnails, resampled only when needed to a maximum 960-pixel long edge, re-encoded with PHP GD as progressive JPEG at quality 82, metadata stripped. No subject replacement, generated content or semantic editing. Ten files total 1,661,692 bytes. Hulugan and Bukal use `object-contain` to preserve the full portrait; other images retain the existing cover presentation with a crop notice. No original file was overwritten.

| Profile | Identity checks / limitations |
| --- | --- |
| Mohicap Lake | File title and description identify Mohicap Lake; visual lake/hill view checked. |
| Pagsanjan Falls | File title identifies Pagsanjan Falls; visible cascade and plunge pool checked. Falls are in Cavinti; preserve existing profile locality. |
| Hulugan Falls | File title and metadata identify Hulugan Falls, Luisiana; full portrait cascade checked. |
| Taytay Falls | File title identifies Taytay Falls, Majayjay; forest cascade checked; source records the CC BY 2.0 Flickr license. |
| Bukal Falls | File title and category identify Bukal Falls, Majayjay; image shows its rocky watercourse rather than a prominent cascade. Thumbnail orientation checked visually. |
| Lumot Lake | File title/locality and explicit Lumot Lake category corroborate the lake-and-boats view. General batch descriptions also mention Caliraya; the explicit file/category identity was checked. |
| Balanac River | Description explicitly identifies Balanac River under Balanac Bridge beside Pagsanjan town hall; river view checked. |
| Tunasan River | File title and description identify Tunasan/Cuyab River. Historical construction/debris in the 2013 image is not a current pollution assessment. |
| Bumbungan River | Description identifies Pagsanjan River continuing below the falls; Bumbungan category and existing profile alias corroborate identity. |
| Santa Cruz River | File description identifies the river beside Pagsawitan Bridge in Santa Cruz, Laguna. Do not rely on a generic river category whose infobox refers to Marinduque. |

## Ten missing photographs

These profiles retain "No authorized photograph available." No randomly similar, private report or unlicensed operator photograph is substituted. Missing permission means authorization was not established in the reviewed sources, not that no photograph exists anywhere.

| Profile | Result / next evidence needed |
| --- | --- |
| Palakpakin Lake | No correctly identified reusable Commons image found. General San Pablo/Seven Lakes descriptions do not establish the pictured lake; city/social-media photographs lack established reuse permission. |
| San Pedro River | A San Antonio bridge candidate mentions both San Pedro and Tunasan in a general description. The depicted reach could not be established, so it was excluded. |
| Talay Falls | A specifically named Flickr photograph was found, but a compatible reuse license could not be established. |
| Bunga Twin Falls | The [municipal tourism page](https://nagcarlan.gov.ph/places-of-interest/) identifies the site but retrieval was forbidden (403). No photo-specific permission established. A licensed research article alone does not establish permission for embedded third-party photos. |
| Buntot Palos Falls | No correctly identified image with documented compatible reuse permission found in the reviewed sources. |
| Aliw Falls | Travel images lack established permission. Similarly named Aliwagwag Falls is in Davao and was rejected. |
| Lanzones Falls | No correctly identified reusable photograph found. Commons fruit photographs are unrelated. |
| Dampalit Falls | A [2011 Philippine Journal of Science cover](https://philjournalsci.dost.gov.ph/philippine-journal-of-science-vol-140-no-2-december-2011/) names photographer Bonifacio V. Labatos, but photo-specific permission was not established and the page returned 403. Government hosting alone does not establish reuse rights. Malabon subjects were excluded. |
| Al Fresco Springs Resort | [Official operator photographs](https://alfrescosprings.com/) are marked All Rights Reserved; no reuse grant found. |
| Hidden Valley Springs | [Official resort photographs](https://www.hiddenvalleyspringsresort.com/) have no documented reuse grant. Unrelated overseas springs were excluded. |

## Research and recovery evidence

Pre-research checkpoint is in [EXPANSION_PROGRESS.md](EXPANSION_PROGRESS.md). Ignored `storage/app/photo-completion/` retains the original 28-profile inventory, original-file hashes, source metadata/page captures, downloaded thumbnails and optimization manifest. These local captures are recovery aids; durable credit/source/license records are committed in the supplement and this inventory.

The Commons search API returned a rate limit (429); API research stopped immediately. Public source pages and static files were reviewed separately at a measured pace. Forbidden government pages were not retried or bypassed. No paid services, contact requests, database imports or migrations were used. Official-site copyright is not inferred from a government or tourism domain.

Browser and automated verification results are recorded in [CURRENT_STATE.md](CURRENT_STATE.md); browser artifacts use ignored `storage/app/photo-completion/browser/`.
