<?php

$csvLines = file(__DIR__ . '/Stavanger Togtrafikk Planlagt faktisk Pt Ept 20170101 20170930 20171012.csv');
$datasettBeskrivelse = 'Togtrafikk på Stavanger Stasjon i perioden 01.01.2017 til 30.09.2017';

$csvHeadings = explode(',', trim($csvLines[0]));
var_dump($csvHeadings);
for($i = 0; $i < count($csvHeadings); $i++) {
	$csvHeadings[$i] = str_replace(' ', '_', $csvHeadings[$i]);
}

$alleAvganger = array();
$alleAnkomster = array();
$perTognrAvganger = array();
$perTognrAnkomster = array();
$avgangerPerEndestasjon = array();
$avkomsterPerUtgangstasjon= array();
$startPosKlokkeslett = strlen('01.01.2017 ');
for($i = 1; $i < count($csvLines); $i++) {
	$row = explode(',', trim($csvLines[$i]));
	$obj = new stdClass();
	foreach($csvHeadings as $csvHeadingKey => $csvHeadingName) {
		$obj->$csvHeadingName = $row[$csvHeadingKey];
	}

	// Encoding error somewhere. Fuck it.
	$obj->endestasjon_kd = str_replace('NB?', 'NBØ', $obj->endestasjon_kd);
	$obj->utgstasjon_kd = str_replace('NB?', 'NBØ', $obj->utgstasjon_kd);

	if($obj->stasjonsbruk == 'E') {
		// Endestasjon
		$alleAnkomster[] = $obj;
		if ($obj->endestasjon_kd != 'STV') {
			throw new Exception('endestasjon_kd feil: ' . $obj->endestasjon_kd
				. chr(10) . 'Objekt: ' . chr(10) . print_r($obj, true));
		}
		$togNrKey = substr($obj->planlagt_ankomst, $startPosKlokkeslett) . ' - ' . $obj->tog_nr . ' fra ' . $obj->utgstasjon_kd;
		if(!isset($perTognrAnkomster[$togNrKey])) {
			$perTognrAnkomster[$togNrKey] = array();
		}
		$perTognrAnkomster[$togNrKey][] = $obj;

		$stasjonsKey = 'Ankomst fra ' . $obj->utgstasjon_kd;
		if(!isset($avkomsterPerUtgangstasjon[$stasjonsKey])) {
			$avkomsterPerUtgangstasjon[$stasjonsKey] = array();
		}
		$avkomsterPerUtgangstasjon[$stasjonsKey][] = $obj;
	}
	elseif($obj->stasjonsbruk == 'U') {
		// Utgangsstasjon
		$alleAvganger[] = $obj;
		if ($obj->utgstasjon_kd != 'STV') {
			throw new Exception('utgstasjon_kd feil: ' . $obj->utgstasjon_kd
				. chr(10) . 'Objekt: ' . chr(10) . print_r($obj, true));
		}
		$togNrKey = substr($obj->planlagt_avgang, $startPosKlokkeslett) . ' - ' . $obj->tog_nr . ' til ' . $obj->endestasjon_kd;
		if(!isset($perTognrAvganger[$togNrKey])) {
			$perTognrAvganger[$togNrKey] = array();
		}
		$perTognrAvganger[$togNrKey][] = $obj;

		$stasjonsKey = 'Avganger til ' . $obj->endestasjon_kd;
		if(!isset($avgangerPerEndestasjon[$stasjonsKey])) {
			$avgangerPerEndestasjon[$stasjonsKey] = array();
		}
		$avgangerPerEndestasjon[$stasjonsKey][] = $obj;
	}
	else {
		throw new Exception('Uhåndtert stasjonsbruk: ' . $obj->stasjonsbruk
			. chr(10) . 'Objekt: ' . chr(10) . print_r($obj, true));
	}

	// Rydd bort litt data som lager støy i print outs
	unset($obj->motorvogntype);
	unset($obj->virksporfelt_nr);
	unset($obj->plansporfelt_nr);
	unset($obj->asstog_nr);
	unset($obj->stasjonsbruk);
	unset($obj->linjenummer);

	// Parse tid
	$obj->planlagt_ankomst_unixtime = norskTidTilUnixtime($obj->planlagt_ankomst);
	$obj->faktisk_ankomst_unixtime = norskTidTilUnixtime($obj->faktisk_ankomst);
	$obj->planlagt_avgang_unixtime = norskTidTilUnixtime($obj->planlagt_avgang);
	$obj->faktisk_avgang_unixtime = norskTidTilUnixtime($obj->faktisk_avgang);
}

// Sjekk at alle med samme tognummer har samme avgangstid, ankomststed, etc
// Gjør at man kan bruke data på item [0] ved utlisting
foreach($perTognrAvganger as $tognr) {
	$tidspunkt = substr($tognr[0]->planlagt_avgang, $startPosKlokkeslett);
	$ankomstSted = $tognr[0]->endestasjon_kd;
	foreach($tognr as $avgang) {
		if (substr($avgang->planlagt_avgang, $startPosKlokkeslett) != $tidspunkt) {
			throw new Exception('Ulikt klokkeslett for planlagt_avgang:' . chr(10)
				. 'Tog 0: ' . $tidspunkt . chr(10)
				. 'Tog X: ' . substr($avgang->planlagt_avgang, $startPosKlokkeslett) . chr(10)
				. 'Objekter: ' . chr(10)
				. print_r($tognr[0], true)
				. print_r($avgang, true)
			);
		}
		if ($avgang->endestasjon_kd != $ankomstSted) {
			throw new Exception('Ulikt endestasjon_kd:' . chr(10)
				. 'Tog 0: ' . $ankomstSted . chr(10)
				. 'Tog X: ' . $avgang->endestasjon_kd . chr(10)
				. 'Objekter: ' . chr(10)
				. print_r($tognr[0], true)
				. print_r($avgang, true)
			);
		}
	}
}

echo count($alleAvganger) . ' avganger (stasjonsbruk U).'.chr(10);
echo count($alleAnkomster) . ' ankomster (stasjonsbruk E).'.chr(10);
var_dump($alleAvganger[0]);
var_dump($alleAnkomster[0]);

ksort($perTognrAvganger);
ksort($perTognrAnkomster);
ksort($avgangerPerEndestasjon);
ksort($avkomsterPerUtgangstasjon);

function norskTidTilUnixtime($norskTid) {
	if ($norskTid == '_') {
		return -1;
	}
	// 01.01.2017 00:00
	$unixtime = mktime(
		// Hour
		substr($norskTid, 11, 2),
		// Minute
		substr($norskTid, 14, 2),
		// Second
		0,
		// Month
		substr($norskTid, 3, 2),
		// Day of month
		substr($norskTid, 0, 2),
		// Year
		substr($norskTid, 6, 4)
	);
	if (date('d.m.Y H:i', $unixtime) != $norskTid
		// Noe spesielt med denne dagen og tidspunktet. Stilte vi klokka?
		&& $norskTid != '26.03.2017 02:17'
		&& $norskTid != '26.03.2017 02:18') {
		throw new Exception('Error parsing date: ' . chr(10) . $norskTid . chr(10) . date('d.m.Y H:i', $unixtime));
	}
	return $unixtime;
}
function tognrLink ($tognrOgAvgang) {
	return 'tognr-'.str_replace(' ', '-', str_replace(':', '', str_replace('Ø', 'OE', $tognrOgAvgang))) . '.html';
}
function avgangTilFraLink ($tognrOgAvgang) {
	return strtolower(str_replace(' ', '-', str_replace(':', '', str_replace('Ø', 'OE', $tognrOgAvgang)))) . '.html';
}
function getDiffTekst($planlagt, $faktisk, $innstiltTog, $delinnstiltStv) {
	if ($innstiltTog == 'Y') {
		$innstiltTekst = 'Helinnstilt tog';
	}
	else if ($innstiltTog == 'P') {
		$innstiltTekst = 'Delinnstilt tog';
	}
	else if ($innstiltTog == 'B') {
		$innstiltTekst = 'Buss for tog';
	}
	else if ($innstiltTog == 'N') {
		$innstiltTekst = '';
	}
	else {
		throw new Exception('Ukjent innstilt tog verdi: ' . $innstiltTog);
	}
	return ($faktisk == -1 ? '?' : (($faktisk - $planlagt) / 60)) . ' minutter'
		. '. ' . $innstiltTekst;
}
function getDiffKategori($planlagt, $faktisk, $innstiltTog, $delinnstiltStv) {
	if ($innstiltTog == 'Y') {
		return '<span class="sort-8 diff-innstilt">Helinnstilt tog</span>';
	}
	else if ($innstiltTog == 'P') {
		return '<span class="sort-6 diff-innstilt">Delinnstilt tog</span>';
	}
	else if ($innstiltTog == 'B') {
		return '<span class="sort-7 diff-innstilt">Buss for tog</span>';
	}
	else if ($innstiltTog == 'N') {
		$innstiltTekst = '';
	}
	else {
		throw new Exception('Ukjent innstilt tog verdi: ' . $innstiltTog);
	}

	if ($faktisk == -1) {
		return '<span class="sort-3 diff-medium">? min</span>';
	}

	$diffMinutter = (($faktisk - $planlagt) / 60);
	if ($diffMinutter < 0) {
		return '<span class="sort-0 diff-good">' . $diffMinutter . ' min</span>';
	}

	if ($diffMinutter <= 2) {
		return '<span class="sort-1 diff-good">0-2 min</span>';
	}
	if ($diffMinutter <= 10) {
		return '<span class="sort-2 diff-medium">3-10 min</span>';
	}
	if ($diffMinutter <= 30) {
		return '<span class="sort-4 diff-bad">10-30 min</span>';
	}
	return '<span class="sort-5 diff-bad">Over 30 min</span>';
}
function getDiffKategoriSummary($tog, $erDetteAvganger) {
	$kategorier = array();
	foreach($tog as $avgang) {
		if ($erDetteAvganger) {
			$kat = getDiffKategori(
					$avgang->planlagt_avgang_unixtime,
					$avgang->faktisk_avgang_unixtime,
					$avgang->innstilt_tog,
					$avgang->delinnstilt_STV
				);
		}
		else {
			$kat = getDiffKategori(
					$avgang->planlagt_ankomst_unixtime,
					$avgang->faktisk_ankomst_unixtime,
					$avgang->innstilt_tog,
					$avgang->delinnstilt_STV
				);
		}
		if (!isset($kategorier[$kat])) {
			$kategorier[$kat] = 0;
		}
		$kategorier[$kat] ++;
	}
	ksort($kategorier);

	$sumKategorierKeys = array(
		0 => '<span class="sort-10 diff-good">OK</span>',
		1 => '<span class="sort-11 diff-medium">Litt forsinket</span>',
		2 => '<span class="sort-12 diff-bad">Forsinket</span>',
		3 => '<span class="sort-13 diff-innstilt">Innstilt/Delinnstilt</span>'
	);
	$sumKategorier = array(
		$sumKategorierKeys[0] => array('main' => 0),
		$sumKategorierKeys[1] => array('main' => 0),
		$sumKategorierKeys[2] => array('main' => 0),
		$sumKategorierKeys[3] => array('main' => 0),
	);
	foreach($kategorier as $kat => $antall) {
		$antallMedProsent = $antall . ' (' . str_replace('.', ',', number_format($antall / count($tog) * 100, 2)) . ' %)';
		if(strpos($kat, 'diff-good') !== false) {
			$sumKategorier[$sumKategorierKeys[0]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[0]][$kat] = $antallMedProsent;
		}
		elseif(strpos($kat, 'diff-medium') !== false) {
			$sumKategorier[$sumKategorierKeys[1]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[1]][$kat] = $antallMedProsent;
		}
		elseif(strpos($kat, 'diff-bad') !== false) {
			$sumKategorier[$sumKategorierKeys[2]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[2]][$kat] = $antallMedProsent;
		}
		elseif(strpos($kat, 'diff-innstilt') !== false) {
			$sumKategorier[$sumKategorierKeys[3]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[3]][$kat] = $antallMedProsent;
		}
		else {
			throw new Exception('Ukjent kategori: ' . $kat);
		}
	}

	foreach($sumKategorier as $kat => $antall) {
		$sumKategorier[$kat]['main'] = $antall['main'] . ' (' . str_replace('.', ',', number_format($antall['main'] / count($tog) * 100, 2)) . ' %)';
	}
	return $sumKategorier;
}

$simpleStyling = '<style>
table {
    border-collapse: collapse;
}
table td, table th {
	border: 1px solid black;
	vertical-align: top;
}
.diff-good {
	color: green;
}
.diff-bad, .diff-innstilt {
	color: red;
}
.diff-medium {
	color: #b77621;
}
.diff-sub-kat {
	margin: 0;
}
</style>
<a href="index.html">Til hovedside</a><br><br>
<span style="font-size: 0.8em;">Tograpport generert av <a href="https://twitter.com/hallny">@hallny</a> (Hallvard Nygård)
 basert på <a href="https://www.mimesbronn.no/request/togavganger_og_ankomst_pa_stavan">data fra Bane NOR</a> (innsynshenvendelse via Mimes Brønn)
 - <a href="https://github.com/HNygard/banenor-punktlighet">Kildekode på Github.</a><br><br></span>
';

function getDiffKategorySummaryHtml($avganger, $erDenneAvgang) {
	$content = '';
	$kategorier = getDiffKategoriSummary($avganger, $erDenneAvgang);
	foreach($kategorier as $kategori => $subKat) {
		$content .= '<li>' . $kategori . ': ' . $subKat['main'] . chr(10);
		$content .= '<ul class="diff-sub-kat">';
		foreach($subKat as $subKatKey => $antall) {
			if($subKatKey != 'main') {
				$content .= '<li>' . $subKatKey . ': '.$antall . '</li>' . chr(10);
			}
		}
		$content .= '</ul>';
		$content .= '</li>'. chr(10);
	}
	return $content;
}

function writeAvgangsliste($fil, $tittel, $avganger) {
	global $simpleStyling;
	$content = '<h1>' . $tittel . '</h1>' . chr(10);
	$content .= $simpleStyling;
	$content .= 'Antall avganger: ' . count($avganger) . chr(10);
	$content .= getDiffKategorySummaryHtml($avganger, true);

	$content .= '<table>' . chr(10);
	$content .= '<thead><tr>' . chr(10);
	$content .= '<th>Togtype</th>' . chr(10);
	$content .= '<th>Planlagt avgang</th>' . chr(10);
	$content .= '<th>Faktisk avgang</th>' . chr(10);
	$content .= '<th>Differanse</th>' . chr(10);
	$content .= '<th>Kategori</th>' . chr(10);
	$content .= '</tr></thead>' . chr(10);

	$content .= '<tbody>' . chr(10);
	foreach($avganger as $avgang) {
		$content .= '<tr>' . chr(10);
		$content .= '<td>' . $avgang->togtype_nv . '</td>' . chr(10);
		$content .= '<td>' . $avgang->planlagt_avgang . '</td>' . chr(10);
		$content .= '<td>' . $avgang->faktisk_avgang . '</td>' . chr(10);
		$content .= '<td>' . getDiffTekst(
				$avgang->planlagt_avgang_unixtime,
				$avgang->faktisk_avgang_unixtime,
				$avgang->innstilt_tog,
				$avgang->delinnstilt_STV
			) . '</td>' . chr(10);
		$content .= '<td>' . getDiffKategori(
				$avgang->planlagt_avgang_unixtime,
				$avgang->faktisk_avgang_unixtime,
				$avgang->innstilt_tog,
				$avgang->delinnstilt_STV
			) . '</td>' . chr(10);
		$content .= '</tr>' . chr(10);
	}
	$content .= '</tbody>' . chr(10);

	$content .= '</table>' . chr(10);

	file_put_contents($fil, $content);
}
function writeAnkomstliste($fil, $tittel, $avkomster) {
	global $simpleStyling;
	$content = '<h1>' . $tittel . '</h1>' . chr(10);
	$content .= $simpleStyling;
	$content .= 'Antall ankomster: ' . count($avkomster) . chr(10) . chr(10);
	$content .= getDiffKategorySummaryHtml($avkomster, false);

	$content .= '<table>' . chr(10);
	$content .= '<thead><tr>' . chr(10);
	$content .= '<th>Togtype</th>' . chr(10);
	$content .= '<th>Planlagt ankomst</th>' . chr(10);
	$content .= '<th>Faktisk ankomst</th>' . chr(10);
	$content .= '<th>Differanse</th>' . chr(10);
	$content .= '<th>Kategori</th>' . chr(10);
	$content .= '</tr></thead>' . chr(10);

	$content .= '<tbody>' . chr(10);
	foreach($avkomster as $avgang) {
		$content .= '<tr>' . chr(10);
		$content .= '<td>' . $avgang->togtype_nv . '</td>' . chr(10);
		$content .= '<td>' . $avgang->planlagt_ankomst . '</td>' . chr(10);
		$content .= '<td>' . $avgang->faktisk_ankomst . '</td>' . chr(10);
		$content .= '<td>' . getDiffTekst(
				$avgang->planlagt_ankomst_unixtime,
				$avgang->faktisk_ankomst_unixtime,
				$avgang->innstilt_tog,
				$avgang->delinnstilt_STV
			) . '</td>' . chr(10);
		$content .= '<td>' . getDiffKategori(
				$avgang->planlagt_ankomst_unixtime,
				$avgang->faktisk_ankomst_unixtime,
				$avgang->innstilt_tog,
				$avgang->delinnstilt_STV
			) . '</td>' . chr(10);
		$content .= '</tr>' . chr(10);
	}
	$content .= '</tbody>' . chr(10);

	$content .= '</table>' . chr(10);

	file_put_contents($fil, $content);
}
function getAndWriteAvgangslisteSummaryInner($filename, $title, $avganger, $avgangerBeskrivelse, $erDetteAvgang) {
	$content = '';
	$content .= '<td><a href="' . $filename . '">' . $title . '</a> - ' . count($avganger) . ' ' . $avgangerBeskrivelse;
	$kategorier = getDiffKategoriSummary($avganger, $erDetteAvgang);
	$content .= '<br><span style="font-size: 0.8em;">';
	foreach($kategorier as $kategori => $antall) {
		$content .= ' - ' . $kategori . ': ' . $antall['main'] . '<br>';
	}
	$content .= '</span>';
	$content .= chr(10);
	if($erDetteAvgang) {
		writeAvgangsliste(__DIR__ . '/docs/' . $filename, $title, $avganger);
	}
	else {
		writeAnkomstliste(__DIR__ . '/docs/' . $filename, $title, $avganger);
	}
	$content .= '</td>';
	return $content;
}
function getAndWriteAvgangslisteSummary($filename, $title, $avganger, $avgangerBeskrivelse, $erDetteAvgang) {
	$content = '<tr>';
	$content .= getAndWriteAvgangslisteSummaryInner($filename, $title, $avganger, $avgangerBeskrivelse, $erDetteAvgang);
	$kunRush = array_filter($avganger, function($value, $key){
		if (strpos($value->planlagt_ankomst, ' 07:') != false
			|| strpos($value->planlagt_avgang, ' 07:') != false
			|| strpos($value->planlagt_ankomst, ' 08:') != false
			|| strpos($value->planlagt_avgang, ' 08:') != false
			|| strpos($value->planlagt_ankomst, ' 15:') != false
			|| strpos($value->planlagt_avgang, ' 15:') != false
			|| strpos($value->planlagt_ankomst, ' 16:') != false
			|| strpos($value->planlagt_avgang, ' 16:') != false) {
			return true;
		}
		else {
			return false;
		}
	}, ARRAY_FILTER_USE_BOTH);
	if (count($kunRush) > 0) {
		$content .= getAndWriteAvgangslisteSummaryInner(str_replace('.html', '-kun-rush.html', $filename), $title . ' - Kun rush', $kunRush, 
			$avgangerBeskrivelse, $erDetteAvgang);
	}
	else {
		$content .= '<td>&nbsp;</td>';
	}
	$kunRush = array_filter($avganger, function($value, $key){
		if ((strpos($value->planlagt_ankomst, '.05.2017') != false
			|| strpos($value->planlagt_avgang, '.05.2017') != false)
			&& (strpos($value->planlagt_ankomst, ' 07:') != false
			|| strpos($value->planlagt_avgang, ' 07:') != false
			|| strpos($value->planlagt_ankomst, ' 08:') != false
			|| strpos($value->planlagt_avgang, ' 08:') != false
			|| strpos($value->planlagt_ankomst, ' 15:') != false
			|| strpos($value->planlagt_avgang, ' 15:') != false
			|| strpos($value->planlagt_ankomst, ' 16:') != false
			|| strpos($value->planlagt_avgang, ' 16:') != false)) {
			return true;
		}
		else {
			return false;
		}
	}, ARRAY_FILTER_USE_BOTH);
	if (count($kunRush) > 0) {
		$content .= getAndWriteAvgangslisteSummaryInner(str_replace('.html', '-kun-rush-mai.html', $filename), $title . ' - Kun rush i Mai', $kunRush, 
			$avgangerBeskrivelse, $erDetteAvgang);
	}
	else {
		$content .= '<td>&nbsp;</td>';
	}
	$kunRush = array_filter($avganger, function($value, $key){
		if ((strpos($value->planlagt_ankomst, '.09.2017') != false
			|| strpos($value->planlagt_avgang, '.09.2017') != false)
			&& (strpos($value->planlagt_ankomst, ' 07:') != false
			|| strpos($value->planlagt_avgang, ' 07:') != false
			|| strpos($value->planlagt_ankomst, ' 08:') != false
			|| strpos($value->planlagt_avgang, ' 08:') != false
			|| strpos($value->planlagt_ankomst, ' 15:') != false
			|| strpos($value->planlagt_avgang, ' 15:') != false
			|| strpos($value->planlagt_ankomst, ' 16:') != false
			|| strpos($value->planlagt_avgang, ' 16:') != false)) {
			return true;
		}
		else {
			return false;
		}
	}, ARRAY_FILTER_USE_BOTH);
	if (count($kunRush) > 0) {
		$content .= getAndWriteAvgangslisteSummaryInner(str_replace('.html', '-kun-rush-september.html', $filename), $title . ' - Kun rush i September', $kunRush, 
			$avgangerBeskrivelse, $erDetteAvgang);
	}
	else {
		$content .= '<td>&nbsp;</td>';
	}
	$content .= '</tr>';
	return $content;
}
$content = '<h1>' . $datasettBeskrivelse . '</h1>' . chr(10);
$content .= $simpleStyling;

$content .= '<h2>Ankomster og avganger per utgangstasjon/endestasjon</h2>' . chr(10);
$content .= '<table class="table" style="width: 100%;"><tr><td>' . chr(10) . '<h2>Avganger</h2>' . chr(10);
$content .= '<table>';
foreach($avgangerPerEndestasjon as $tognrOgAvgang => $avganger) {
	$content .= getAndWriteAvgangslisteSummary(avgangTilFraLink($tognrOgAvgang), $tognrOgAvgang, $avganger, 'avganger', true);
}
$content .= '</table>';
$content .= '</td><td><h2>Ankomster</h2>' . chr(10);
$content .= '<table>';
foreach($avkomsterPerUtgangstasjon as $tognrOgAvgang => $ankomster) {
	$content .= getAndWriteAvgangslisteSummary(avgangTilFraLink($tognrOgAvgang), $tognrOgAvgang, $ankomster, 'ankomster', false);
}
$content .= '</table>';
$content .= '</td></tr></table>';

$content .= '<h2>Alle ankomster og avganger</h2>' . chr(10);
$content .= '<table class="table" style="width: 100%;"><tr><td>' . chr(10) . '<h2>Avganger</h2>' . chr(10);
$content .= '<table>';
foreach($perTognrAvganger as $tognrOgAvgang => $avganger) {
	$content .= getAndWriteAvgangslisteSummary(tognrLink($tognrOgAvgang), 'Avgang ' . $tognrOgAvgang, $avganger, 'avganger', true);
}
$content .= '</table>';
$content .= '</td><td><h2>Ankomster</h2>' . chr(10);
$content .= '<table>';
foreach($perTognrAnkomster as $tognrOgAvgang => $ankomster) {
	$content .= getAndWriteAvgangslisteSummary(tognrLink($tognrOgAvgang), 'Ankomst ' . $tognrOgAvgang, $ankomster, 'ankomster', false);
}
$content .= '</table>';
$content .= '</td></tr></table>';

file_put_contents(__DIR__ . '/docs/index.html', $content);
