<?php
require_once(__DIR__.'/processor.php');

function query_to_xml($cmd, $args = [], $into = null) {
  $out = $into instanceof SimpleXMLElement ? $into 
		: simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?><data/>');
  foreach(process_query($cmd, $args) as $r) {
    $elem = $out->appendChild(@$r->getName() ?: 'r');
	foreach($r as $k=>$v)
		if(has_subitems($v)) {
			$elem->$k = '';
			query_to_xml($v, [], $elem->$k);
		} else
		  	$elem->$k = $v;
  }
  if($into) return $out;
  header('Content-type: application/xml');
  echo $out->saveXML();
}

if(__FILE__ != TOPLEVEL_FILE) return;

query_to_xml( main_argument(),  main_subarguments() );
  
?>
