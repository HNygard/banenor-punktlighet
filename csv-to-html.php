<?php

$csvLines = file(__DIR__ . '/Stavanger Togtrafikk Planlagt faktisk Pt Ept 20170101 20170930 20171012.csv');

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
		$togNrKey = $obj->tog_nr . ' - ' . substr($obj->planlagt_avgang, $startPosKlokkeslett);
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
