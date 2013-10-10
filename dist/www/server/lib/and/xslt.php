<?php
if($argc<2) die("Usage: php -f xslt.php template [fname] xslparam=value ...");

$astart = 2;
if($argc> 2 && strpos($argv[2], '=')===FALSE) { $astart = 3;
	$fname = $argv[2];
} else $fname = 'php://stdin';

$params = array_map( function($x) { return explode('=',$x,2); },
  array_slice($argv, $astart));
$params1 = array_map( function($x) { return $x[0]; }, $params);
$params2 = array_map( function($x) { return isset($x[1])? $x[1] : ""; }, $params);
$params = array_combine( $params1, $params2 );

$template = $argv[1];

$xslt = new XSLTProcessor; 

$tfile = simplexml_load_file($template);

if(!$tfile) die("cannot read template");

$xslt->importStylesheet($tfile);



$xml = new DOMDocument;
$xml->load( $fname );

$xslt->setParameter('', $params);

echo $xslt->transformToXML($xml);
?>
