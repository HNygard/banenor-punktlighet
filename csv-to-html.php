<?php

$datasettBeskrivelse = 'Togtrafikk på Stavanger Stasjon i perioden 01.01.2017 til 31.10.2017';
$csvLines = array_merge(
	readCsvFile(__DIR__ . '/Stavanger Togtrafikk Planlagt faktisk Pt Ept 20170101 20170930 20171012.csv', ','),
	readCsvFile(__DIR__ . '/Stavanger Togtrafikk Planlagt faktisk Pt Ept 20171001 20171031 20171103.csv', ';')
);

function readCsvFile($filename, $delimiter) {
	global $csvHeadings;
	$csvLines = file($filename);

	$csvHeadings = explode($delimiter, trim($csvLines[0]));
	var_dump($csvHeadings);
	for($i = 0; $i < count($csvHeadings); $i++) {
		$csvHeadings[$i] = str_replace(' ', '_', $csvHeadings[$i]);
	}
	$csvLines2 = array();
	for($i = 1; $i < count($csvLines); $i++) {
		$csvLines2[] = str_replace(';', ',', $csvLines[$i]);
	}
	return $csvLines2;
}

$alleAvganger = array();
$alleAnkomster = array();
$perTognrAvganger = array();
$perTognrAnkomster = array();
$avgangerPerEndestasjon = array();
$avkomsterPerUtgangstasjon= array();
$startPosKlokkeslett = strlen('01.01.2017 ');
for($i = 0; $i < count($csvLines); $i++) {
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

		$stasjonsKey = 'Ankomst fra ' . $obj->utgstasjon_kd . ' til STV';
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

		$stasjonsKey = 'Avganger til ' . $obj->endestasjon_kd . ' fra STV';
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
function getDiffKategori($erDetteAvgang, $avgang) {
	if ($erDetteAvgang) {
		$planlagt = $avgang->planlagt_avgang_unixtime;
		$faktisk = $avgang->faktisk_avgang_unixtime;
		$innstiltTog = $avgang->innstilt_tog;
		$delinnstiltStv = $avgang->delinnstilt_STV;
	}
	else {
		$planlagt = $avgang->planlagt_ankomst_unixtime;
		$faktisk = $avgang->faktisk_ankomst_unixtime;
		$innstiltTog = $avgang->innstilt_tog;
		$delinnstiltStv = $avgang->delinnstilt_STV;
	}

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
		return '<span class="sort-3 diff-medium" data-min="0">? min</span>';
	}

	$diffMinutter = (($faktisk - $planlagt) / 60);
	$min = ' data-min="'. $diffMinutter . '"';
	if ($diffMinutter < 0) {
		return '<span class="sort-0 diff-good"' . $min . '>' . $diffMinutter . ' min</span>';
	}

	if ($diffMinutter <= 2) {
		return '<span class="sort-1 diff-good"' . $min . '>0-2 min</span>';
	}
	if ($diffMinutter <= 9) {
		return '<span class="sort-2 diff-medium"' . $min . '>3-9 min</span>';
	}
	if ($diffMinutter <= 30) {
		return '<span class="sort-4 diff-bad"' . $min . '>10-30 min</span>';
	}
	return '<span class="sort-5 diff-bad"' . $min . '>Over 30 min</span>';
}
function getDiffKategoriSummary($tog, $erDetteAvganger) {
	$kategorier = array();
	foreach($tog as $avgang) {
		$kat = getDiffKategori(
				$erDetteAvganger,
				$avgang
			);
		$kat = preg_replace('/ data-min="([\-0-9]*)"/', '', $kat);
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
		if(str_contains($kat, 'diff-good')) {
			$sumKategorier[$sumKategorierKeys[0]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[0]][$kat] = $antallMedProsent;
		}
		elseif(str_contains($kat, 'diff-medium')) {
			$sumKategorier[$sumKategorierKeys[1]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[1]][$kat] = $antallMedProsent;
		}
		elseif(str_contains($kat, 'diff-bad')) {
			$sumKategorier[$sumKategorierKeys[2]]['main'] += $antall;
			$sumKategorier[$sumKategorierKeys[2]][$kat] = $antallMedProsent;
		}
		elseif(str_contains($kat, 'diff-innstilt')) {
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

$screenshotPaths = array();

$diff_color_good = '#008000';
$diff_color_medium = '#b77621';
$diff_color_bad = '#ff0000';
$diff_color_bad2 = '#a20404';

function styling($tittel, $twitterImage) {
	global $diff_color_good, $diff_color_medium, $diff_color_bad, $diff_color_bad2;
	$simpleStyling = '<html>
<head>
<title>'. $tittel . '</title>

<meta name="author" content="Hallvard Nygård, @hallny">
<meta name="description" content="Tograpport generert basert på data fra BaneNOR. Hentet via innsynshenvendelse (Mimes Brønn).">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:creator" content="@hallny">
<meta name="twitter:site" content="@hallny">
<meta name="twitter:title" content="Innsyn i ' . lcfirst($tittel) . '">
<meta name="twitter:image" content="https://hnygard.github.io/banenor-punktlighet/' . $twitterImage . '">
<meta name="og:image" content="https://hnygard.github.io/banenor-punktlighet/' . $twitterImage . '">
<meta itemprop="name" content="Innsyn i ' . lcfirst($tittel) . '">
<meta itemprop="description" content="Tograpport generert basert på data fra BaneNOR. Hentet via innsynshenvendelse (Mimes Brønn).">

</head>';

$simpleStyling .= '<style>
table {
    border-collapse: collapse;
}
table td, table th {
	border: 1px solid black;
	vertical-align: top;
}
.diff-good {
	color: ' . $diff_color_good . ';
}
.diff-medium {
	color: ' . $diff_color_medium . ';
}
.diff-bad {
	color: ' . $diff_color_bad . ';
}
.diff-innstilt {
	color: ' . $diff_color_bad2 . ';
}
.diff-sub-kat {
	margin: 0;
}
</style>
</head>
<body>
<a href="index.html">Til hovedside</a><br><br>
<span style="font-size: 0.8em;">Tograpport generert av <a href="https://twitter.com/hallny">@hallny</a> (Hallvard Nygård)
 basert på data fra BaneNOR (
<a href="https://www.mimesbronn.no/request/togavganger_og_ankomst_pa_stavan">[1]</a>,
<a href="https://www.mimesbronn.no/request/togavganger_og_ankomst_pa_stavan_2">[2]</a>
 - innsynshenvendelser via Mimes Brønn)
 - <a href="https://github.com/HNygard/banenor-punktlighet">Kildekode på Github.</a><br><br></span>
';

$simpleStyling .= '

<script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/6.0.2/highcharts.src.js"></script>

<div id="container" style="min-width: 310px; height: 400px; max-width: 100%; margin: 0 auto; display: none;"></div>

';
	return $simpleStyling;
}

function highcharts($fil, $tittel, $avganger, $erAvganger) {
	global $diff_color_good, $diff_color_medium, $diff_color_bad, $diff_color_bad2;
	$labels = array();
	$good = array();
	$medium = array();
	$bad = array();
	$bad2 = array();
	foreach($avganger as $avgang) {
		$kat = getDiffKategori($erAvganger, $avgang);
		$unixtime = $erAvganger ? $avgang->planlagt_avgang_unixtime : $avgang->planlagt_ankomst_unixtime;
		$unixtimeFaktisk = $erAvganger ? $avgang->faktisk_avgang_unixtime : $avgang->faktisk_ankomst_unixtime;
		$date = 'Date.UTC('
			. date('Y', $unixtime) .','
			. (date('m', $unixtime)-1) . ','
			. date('d,H,i', $unixtime) . ')';
		if (str_contains($kat, 'diff-innstilt')) {
			$name = 'Planlagt: ' . date('H:i d.m.Y', $unixtime);
			$bad2[] = "{x: $date, y: 0, name: '$name'}\n";
		}
		else {
			preg_match('/ data-min="([\-0-9]*)"/', $kat, $matches);

			if (!isset($matches[1])) {
				throw new Exception('Unknown kat 1: ' . $kat);
			}
			$name = 'Planlagt: ' . date('H:i d.m.Y', $unixtime) . '<br>'
				. 'Faktisk: ' . date('H:i d.m.Y', $unixtimeFaktisk) . '<br>'
				. 'Forsinkelse: ' . $matches[1] . ' minutter';
			$korrigertForsinkelse = $matches[1];
			if ($korrigertForsinkelse < -5) {
				// -463 minutt ødelegger grafen...
				$korrigertForsinkelse = -5;
			}
			$item = '{x: '.$date.', y: ' . $korrigertForsinkelse . ', name: \'' . $name . '\'}' . chr(10);
			if (str_contains($kat, 'diff-medium')) {
				$medium[] = $item;
			}
			else if (str_contains($kat, 'diff-bad')) {
				$bad[] = $item;
			} 
			else if (str_contains($kat, 'diff-good')) {
				$good[] = $item;
			}
			else {
				throw new Exception('Unknown kat: ' . $kat);
			}
		}
	}
	$highcharts = "<script>
Highcharts.chart('container', {
    chart: {
        type: 'scatter',
        zoomType: 'xy'
    },
    title: {
        text: '$tittel'
    },
    subtitle: {
        text: 'https://hnygard.github.io/banenor-punktlighet/" . str_replace(__DIR__ . '/docs/', '', $fil) . "'
    },
    xAxis: {
        type: 'datetime',
        title: {
            enabled: true,
            text: 'Tid'
        },
        startOnTick: true,
        endOnTick: true,
        showLastLabel: true
    },
    yAxis: {
        title: {
            text: 'Minutter forsinket'
        }
    },
    legend: {
	x: 60,
	y: 40,
        layout: 'vertical',
        align: 'left',
        verticalAlign: 'top',
        floating: true,
        borderWidth: 1
    },
    plotOptions: {
        scatter: {
            marker: {
                radius: 5,
                states: {
                    hover: {
                        enabled: true,
                        lineColor: 'rgb(100,100,100)'
                    }
                }
            },
            states: {
                hover: {
                    marker: {
                        enabled: false
                    }
                }
            },
            tooltip: {
                headerFormat: '<b>{series.name}</b><br>',
                pointFormat: '{point.name}'
            }
        }
    },
    series: [{
        name: 'På tiden',
        color: '$diff_color_good',
	turboThreshold: 0,
        data: [" . implode($good, ', ')."]

    }, {
        name: 'Litt forsinket',
        color: '$diff_color_medium',
	turboThreshold: 0,
        data: [" . implode($medium, ', ')."]
    }, {
        name: 'Forsinket',
        color: '$diff_color_bad',
	turboThreshold: 0,
        data: [" . implode($bad, ', ')."]
    }, {
        name: 'Innstilt',
        color: '$diff_color_bad2',
	turboThreshold: 0,
        data: [" . implode($bad2, ', ')."]
    }]
});
document.getElementById('container').style.display='block';
document.getElementById('containerlink').style.display='block';
</script>";

	global $screenshotPaths;
	$screenshotPath = str_replace('docs/', 'docs/screenshots/', $fil);
	$screenshotPaths[] = str_replace(__DIR__, '', $screenshotPath);
	file_put_contents(
		$screenshotPath,
		'<script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/6.0.2/highcharts.src.js"></script>
		<div id="container" style="width: 100%; height: 100%; margin: 0 auto; display: none;"></div>
		'.$highcharts);

	return $highcharts;
}

function str_contains($haystack, $needle) {
	return strpos($haystack, $needle) !== false;
}

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

function getScreenshotPath($file) {
	return str_replace(__DIR__ . '/docs/', 'screenshots/',
		  str_replace('.html', '.png', $file));
}

function grafLink($file) {
	return '<a id="containerlink" style="float: right; display: none;" href="'
		. getScreenshotPath($file)
		. '">Last ned graf</a>' . chr(10).chr(10);
}

function writeAvgangsliste($fil, $tittel, $avganger) {
	$content = '<h1>' . $tittel . '</h1>' . chr(10);
	$content .= styling($tittel, getScreenshotPath($fil));
	$content .= grafLink($fil);
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
		$content .= '<td>' . getDiffKategori(true, $avgang) . '</td>' . chr(10);
		$content .= '</tr>' . chr(10);
	}
	$content .= '</tbody>' . chr(10);

	$content .= '</table>' . chr(10);

	$content .= highcharts($fil, $tittel, $avganger, true);

	file_put_contents($fil, $content);
}
function writeAnkomstliste($fil, $tittel, $avkomster) {
	$content = '<h1>' . $tittel . '</h1>' . chr(10);
	$content .= styling($tittel, getScreenshotPath($fil));
	$content .= grafLink($fil);
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
		$content .= '<td>' . getDiffKategori(false, $avgang) . '</td>' . chr(10);
		$content .= '</tr>' . chr(10);
	}
	$content .= '</tbody>' . chr(10);

	$content .= '</table>' . chr(10);

	$content .= highcharts($fil, $tittel, $avkomster, false);

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
		if (str_contains($value->planlagt_ankomst, ' 07:')
			|| str_contains($value->planlagt_avgang, ' 07:')
			|| str_contains($value->planlagt_ankomst, ' 08:')
			|| str_contains($value->planlagt_avgang, ' 08:')
			|| str_contains($value->planlagt_ankomst, ' 15:')
			|| str_contains($value->planlagt_avgang, ' 15:')
			|| str_contains($value->planlagt_ankomst, ' 16:')
			|| str_contains($value->planlagt_avgang, ' 16:')) {
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
		if ((str_contains($value->planlagt_ankomst, '.05.2017')
			|| str_contains($value->planlagt_avgang, '.05.2017'))
			&& (str_contains($value->planlagt_ankomst, ' 07:')
			|| str_contains($value->planlagt_avgang, ' 07:')
			|| str_contains($value->planlagt_ankomst, ' 08:')
			|| str_contains($value->planlagt_avgang, ' 08:')
			|| str_contains($value->planlagt_ankomst, ' 15:')
			|| str_contains($value->planlagt_avgang, ' 15:')
			|| str_contains($value->planlagt_ankomst, ' 16:')
			|| str_contains($value->planlagt_avgang, ' 16:'))) {
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
		if ((str_contains($value->planlagt_ankomst, '.09.2017')
			|| str_contains($value->planlagt_avgang, '.09.2017'))
			&& (str_contains($value->planlagt_ankomst, ' 07:')
			|| str_contains($value->planlagt_avgang, ' 07:')
			|| str_contains($value->planlagt_ankomst, ' 08:')
			|| str_contains($value->planlagt_avgang, ' 08:')
			|| str_contains($value->planlagt_ankomst, ' 15:')
			|| str_contains($value->planlagt_avgang, ' 15:')
			|| str_contains($value->planlagt_ankomst, ' 16:')
			|| str_contains($value->planlagt_avgang, ' 16:'))) {
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
	$kunRush = array_filter($avganger, function($value, $key){
		if ((str_contains($value->planlagt_ankomst, '.10.2017')
			|| str_contains($value->planlagt_avgang, '.10.2017'))
			&& (str_contains($value->planlagt_ankomst, ' 07:')
			|| str_contains($value->planlagt_avgang, ' 07:')
			|| str_contains($value->planlagt_ankomst, ' 08:')
			|| str_contains($value->planlagt_avgang, ' 08:')
			|| str_contains($value->planlagt_ankomst, ' 15:')
			|| str_contains($value->planlagt_avgang, ' 15:')
			|| str_contains($value->planlagt_ankomst, ' 16:')
			|| str_contains($value->planlagt_avgang, ' 16:'))) {
			return true;
		}
		else {
			return false;
		}
	}, ARRAY_FILTER_USE_BOTH);
	if (count($kunRush) > 0) {
		$content .= getAndWriteAvgangslisteSummaryInner(str_replace('.html', '-kun-rush-oktober.html', $filename), $title . ' - Kun rush i Oktober', $kunRush,
			$avgangerBeskrivelse, $erDetteAvgang);
	}
	else {
		$content .= '<td>&nbsp;</td>';
	}
	$content .= '</tr>';
	return $content;
}
$content = '<h1>' . $datasettBeskrivelse . '</h1>' . chr(10);
$content .= styling($datasettBeskrivelse, 'screenshots/ankomst-fra-egs.png');

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

file_put_contents(__DIR__ . '/screenshot-maker/path.txt', implode(chr(10), $screenshotPaths));
