<?php

$csvLines = file(__DIR__ . '/Stavanger Togtrafikk Planlagt faktisk Pt Ept 20170101 20170930 20171012.csv');
$datasettBeskrivelse = 'Togtrafikk på Stavanger Stasjon i perioden 01.01.2017 til 30.09.2017';

$csvHeadings = explode(',', trim($csvLines[0]));
var_dump($csvHeadings);

$alleAvganger = array();
$alleAnkomster = array();
$perTognrAvganger = array();
$perTognrAnkomster = array();
$startPosKlokkeslett = strlen('01.01.2017 ');
for($i = 1; $i < count($csvLines); $i++) {
	$row = explode(',', trim($csvLines[$i]));
	$obj = new stdClass();
	foreach($csvHeadings as $csvHeadingKey => $csvHeadingName) {
		$obj->$csvHeadingName = $row[$csvHeadingKey];
	}
	if($obj->stasjonsbruk == 'E') {
		// Endestasjon
		$alleAnkomster[] = $obj;
		if ($obj->endestasjon_kd != 'STV') {
			throw new Exception('endestasjon_kd feil: ' . $obj->endestasjon_kd
				. chr(10) . 'Objekt: ' . chr(10) . print_r($obj, true));
		}
		$togNrKey = $obj->tog_nr . ' - ' . substr($obj->planlagt_ankomst, $startPosKlokkeslett);
		if(!isset($perTognrAnkomster[$togNrKey])) {
			$perTognrAnkomster[$togNrKey] = array();
		}
		$perTognrAnkomster[$togNrKey][] = $obj;
	}
	elseif($obj->stasjonsbruk == 'U') {
		// Utgangsstasjon
		$alleAvganger[] = $obj;
		if ($obj->utgstasjon_kd != 'STV') {
			throw new Exception('utgstasjon_kd feil: ' . $obj->utgstasjon_kd
				. chr(10) . 'Objekt: ' . chr(10) . print_r($obj, true));
		}
		$togNrKey = substr($obj->planlagt_avgang, $startPosKlokkeslett) . ' - ' . $obj->tog_nr;
		if(!isset($perTognrAvganger[$togNrKey])) {
			$perTognrAvganger[$togNrKey] = array();
		}
		$perTognrAvganger[$togNrKey][] = $obj;
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

}

// Sjekk at alle med samme tognummer har samme avgangstid
foreach($perTognrAvganger as $tognr) {
	$tidspunkt = substr($tognr[0]->planlagt_avgang, $startPosKlokkeslett);
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
	}
}

echo count($alleAvganger) . ' avganger (stasjonsbruk U).'.chr(10);
echo count($alleAnkomster) . ' ankomster (stasjonsbruk E).'.chr(10);
var_dump($alleAvganger[0]);
var_dump($alleAnkomster[0]);

function tognrLink ($tognrOgAvgang) {
	return 'tognr-'.str_replace(' ', '-', str_replace(':', '', $tognrOgAvgang)) . '.html';
}
function writeAvgangsliste($fil, $tittel, $avganger) {
	$content = '<h1>' . $tittel . '</h1>' . chr(10);
	$content .= 'Antall avganger: ' . count($avganger) . chr(10);
	file_put_contents($fil, $content);
}
function writeAnkomstliste($fil, $tittel, $avganger) {
	$content = '<h1>' . $tittel . '</h1>' . chr(10);
	$content .= 'Antall ankomster: ' . count($avganger) . chr(10);
	file_put_contents($fil, $content);
}
$content = '<h1>' . $datasettBeskrivelse . '</h1>' . chr(10);
$content .= '<table class="table" style="width: 100%;"><tr><td>' . chr(10) . '<h2>Avganger</h2>' . chr(10);
foreach($perTognrAvganger as $tognrOgAvgang => $avganger) {
	$content .= '<li><a href="' . tognrLink($tognrOgAvgang) . '">' . $tognrOgAvgang . '</a> - ' . count($avganger) . ' avganger' . chr(10);
	writeAvgangsliste(__DIR__ . '/docs/' . tognrLink($tognrOgAvgang), 'Avgang ' . $tognrOgAvgang, $avganger);
}
$content .= '</td><td><h2>Ankomster</h2>' . chr(10);
foreach($perTognrAnkomster as $tognrOgAvgang => $ankomster) {
	$content .= '<li><a href="' . tognrLink($tognrOgAvgang) . '">' . $tognrOgAvgang . '</a> - ' . count($ankomster) . ' ankomster' . chr(10);
	writeAnkomstliste(__DIR__ . '/docs/' . tognrLink($tognrOgAvgang), 'Ankomst ' . $tognrOgAvgang, $ankomster);
}
$content .= '</td></tr></table>';

file_put_contents(__DIR__ . '/docs/index.html', $content);
