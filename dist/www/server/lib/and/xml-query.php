<?php
require_once(__DIR__.'/db-oo.php');

function XMLField($a) { return (string)$a ?: $a->getName(); }

function query_to_xml($cmd, $args, $pre_transform = '', $post_transform = '') {

$xml = null;
if($cmd[0] == '<') {
	$xml = new DOMDocument;
	$xml->loadXML($cmd);
} else
	{
		//cmd is alredy done
	}
	
$template = $xml ? ($pre_transform ?: $args[1]) : '';
if($template && $template!='-') {
  $xslt = new XSLTProcessor; 
  
  $tfile = simplexml_load_file($template);
  if(!$tfile) die("cannot read template");

  $xslt->importStylesheet($tfile);

  $xml = $xslt->transformToDoc($xml);
}

//echo 'x1',$xml->saveXML();

if($xml) {
	$xml = simplexml_import_dom($xml);

	$make_copy = @$xml['copy'];

	$elems = $xml->children();

	$commands = array();

	foreach($elems as $elem) {
	  $table = $elem->getName();
	  $attrs = $elem->xpath('attrs/*');
	  if(!$attrs) continue;
	  $fields = array_map(function($a) { return $a->getName(); }, $attrs);
	  $fields_csv = implode(', ', $fields);

	  $conds = $elem->xpath('cond/*');
	  $conds_txt = array_map(
				  function($a) { 
				// <cond>
				// <_ op='op'/> --> op
				// <f /> --> f is not null
				// <f val='v'/> --> <f op='=' val='v'/>
				// <f op='op' val='v' /> --> <f op='op' val='v' func=''/>
				// <f op='op' val='v' func='func'/>
				// --> if op has '?' 
				//     and then cond = func(f) + replace ? with func(?) in op
				// --> else con = func(f) op func(?)
				//
				// so
				// most common way: <_ op='op'/>
				// check existance: <f/>
				// common expr with val: <_ op='expr' val='v'/>
				//    we substitute val in expr here
				//    func can be integrated in expr
				// check filed: <f val='v'/> --> f = v
				// compare field: <f op='op' val='v'/> --> f op v
				// caseless check: <f val='v' func='UPPER'/>
				// begins: <f val='v' func="LIKE '?%'" />
				// caseless begins: <f val='v' func="LIKE ? || '%'"/>
				$e = $a->getName();
				if($e == '_') $e = '';
				if(!isset($a['val'])) 
				  return @$a['op']?: "$e IS NOT NULL";
				$op = @$a['op']?:'=';
				//CONCAT!!!
				if($op == '^=') $op = "LIKE ?||'%'";
				if($op == '*=') $op = "LIKE '%'||?||'%'";
				if($op == '$=') $op = "LIKE '%'||?";
				$func = @$a['func']?:'';
				if(strpos($op, '?')===FALSE)
				  $op = $op." $func(?) ";
				else
				  if($func)
					$op = str_replace('?'," $func(?) ",$op);
				  return $func? "$func($e) $op" : "$e $op"; 
				  }, 
				  $conds);
	  $conds_vals = array_map(
				  function($a) { return @$a['val']; }, 
				  $conds);
	  $conds_vals = array_filter($conds_vals);
	  $conds_txt = implode(' AND ', $conds_txt) ?: '1=1';

	  $group = $elem->xpath('group/*');
	  $group = array_map('XMLField', $group);
	  $group = $group? 'GROUP BY '.implode(',', $group): '';

	  $order = $elem->xpath('order/*');
	  $order = array_map(function($a) { 
		  return preg_replace('/-$/', ' DESC', XMLField($a)); }
		  , $order);
	  $order = $order? 'ORDER BY '.implode(',', $order): '';

	  $commands[] = array( 'table' => $table,
				   'cmd' =>  "SELECT $fields_csv FROM $table WHERE $conds_txt $group $order",
				   'params' => $conds_vals,
				   'node' => $make_copy? $elem->children() : null
				);
	}
} else {
	$make_copy = false; //no tags to copy
	global $RE_ID;
	preg_match("/\\s+FROM\\s+($RE_ID)\\s+/i", $cmd, $m);
	$commands[] = array( 'table' => @$m[1]?:'<tr>',
		'cmd' => $cmd,
		'params' => $args,
		'node' => null
	);
}

$out = new DOMDocument;
$root = $out->appendChild(
			  $make_copy?
			  $out->importNode(dom_import_simplexml($xml))
			  :
			  $out->createElement('root')
);

foreach($commands as $c) {
  $dbh = get_connection($c['table']);
  $cc = $dbh->prepare($c['cmd']);
  $res = $cc->execute($c['params']);
//	echo 'cmd ', $c['cmd'];
  $to_add = $c['node'] ? 
    $root->appendChild($out->importNode(dom_import_simplexml($c['node'])))
		       : $root;
  while($res = $cc->fetch(PDO::FETCH_ASSOC)) {
    $elem = $to_add->appendChild($out->createElement($c['table']));
    foreach($res as $fld=>$fval)
      $elem->setAttribute($fld, $fval);
  }
}

//echo $out->saveXML();

$template = $xml ? ($post_transform ?: $args[2]) : '';

if($template && $template!='-') {
  $xslt = new XSLTProcessor; 

  $tfile = simplexml_load_file($template);

  if(!$tfile) die("cannot read template");

  $xslt->importStylesheet($tfile);
  $xslt->setParameter('', 'address', 
	'http://'
	.(@$_SERVER["SERVER_ADDR"]?:'127.0.0.1')
	.':'.(@$_SERVER["SERVER_PORT"]?:'80')
	.'/');

  $out = $xslt->transformToDoc($out);
}

if(@$compress)
  ini_set("zlib.output_compression", 4096);

header('Content-type: application/xml');
$out->formatOutput = true;
$out->encoding = 'UTF-8';
echo $out->saveXML();

}

if(__FILE__ != TOPLEVEL_FILE) return;

query_to_xml( main_argument(),  main_subarguments(), [1] );

?>