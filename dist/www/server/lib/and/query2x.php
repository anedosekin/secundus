<?php
require_once(__DIR__.'/db-oo.php');
require_once(__DIR__.'/xml-query.php');
require_once(__DIR__.'/query2html.php');
require_once(__DIR__.'/query2csv.php');
require_once(__DIR__.'/query2xlsxml.php');

/*
	query2x query_to_xml:out.xml -
*/

function query_to_x($cmd, $args) {
	$opt = explode(':',$cmd);
	$cmd = $opt[0];
	if(!preg_match('/^(query_to_html|query_to_xml|query_to_csv|query_to_xlsxml)$/'))
		die "unknown format $cmd";
	if(@$opt[1])
		header('Content-disposition: attachment;filename='.($filename?:@$opt[1]));
	$cmd($args[0], array_splice($args,1) );
}

if(__FILE__ != TOPLEVEL_FILE) return;

query_to_x( main_argument(),  main_subarguments() );

?>