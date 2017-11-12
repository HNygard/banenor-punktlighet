<?php

require __DIR__ . '/vendor/autoload.php';



use dawood\phpChrome\Chrome;

$lines = file(__DIR__ . '/path.txt');
$chrome = new Chrome(null, '/usr/bin/google-chrome');
$chrome->setWindowSize($width = 600, $height = 300);

// La Highcharts rendre
$chrome->setArgument('--virtual-time-budget', 25000);
foreach($lines as $i => $line) {
	$url = __DIR__ . '/..'.trim($line);
	$url = str_replace('screenshot-maker/../', '', $url);
	echo 'Opening [' . ($i + 1) .' av ' . count($lines) . '] - current memory usage: ' . memory_get_usage() . chr(10) . '  ' . $url . chr(10);
	$chrome->useHtmlFile($url);
	echo '  '.$chrome->getScreenShot(str_replace('.html', '.png', $url)).PHP_EOL;
}
