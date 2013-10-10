<?php
/*
process cmd and args

we make external call from here
1) to sql db
2) to http or so url
	(using cfg setting for request and result translation)
	
any template, include universal tamplates like query2xxx can use it

*/

require_once __DIR__.'/db-oo.php';

function take_XMLField($a) { return (string)$a ?: $a->getName(); }

function make_sql_from_xml($xml, &$cmd, &$args) {
	$elem = simplexml_import_dom($xml);
	
	  $table = $elem->getName();
	  $attrs = $elem->xpath('select/*');
	  $fields = array_map('take_XMLField', $attrs);
	  $fields_csv = implode(', ', $fields);

	  $where = [];
	  $conds_vals = [];
	  foreach( $elem->xpath('where') as $cond_group) {
		$conds = $cond_group->xpath('*');
		$conds_txt = array_map(
			function($a) { 
					// <where>
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
				if(!isset($a['val'])) return @$a['op'] ?: "$e IS NOT NULL"; //val is not set!
				switch($op = @$a['op'] ?: '=') {
				case '^=': $op = "LIKE ?||'%'"; break;
				case '*=': $op = "LIKE '%'||?||'%'"; break;
				case '$=': $op = "LIKE '%'||?"; break;
				}
				if(strpos($op, '?')===FALSE)
					$op = $op.' ?'; //add placeholder
				if($func = @$a['func'] ?: '')
					$op = str_replace('?'," $func(?) ",$op);
				return $func? "$func($e) $op" : "$e $op"; 
			}, 
			$conds);
		$conds_vals[] = array_map(function($a) { return @$a['val'] ?: ''; }, $conds);
		$conds_txt = implode(' AND ', $conds_txt);
		if($conds_txt)
			$where[] = $conds_txt;
	  }
	  $where = implode(' OR ', array_map(function($e) { return "( $e )"; }, $where));

	  $group = array_map('take_XMLField', $elem->xpath('group/*'));
	  $group = $group? 'GROUP BY '.implode(', ', $group): '';

	  $order = 
		preg_replace('/-$/', ' DESC',array_map('take_XMLField', $elem->xpath('order/*')));
	  $order = $order? 'ORDER BY '.implode(', ', $order): '';

	$cmd = "SELECT $fields_csv FROM $table WHERE $conds_txt $group $order";
	//FIXME: merge the new args with original ones, but keep order... for now it's overcomlicated
	$args = $conds_vals;
}

function process_query($cmd, $args = []) {
	if(!is_string($cmd)) {
		//not a string, return as is s
		//but...
		if($cmd instanceof DOMNode) $cmd = simplexml_import_dom($cmd);
		return $cmd;
	}
	if(preg_match('/^\s*</', $cmd))
	{ //xml
		$xml = simplexml_load_string($cmd);
		if( dom_import_simplexml($xml)->namespaceURI === 'http://xmlquery/query') {
			//xml-query
			make_sql_from_xml($xml, $cmd, $args);
			// and go forward to query processing
		} else {
			// xml data
			$table = $xml->getName();
			return $xml;
		}
	} else if(preg_match('/^s*CALL:(.*)/', $cmd, $m) ) {
		$point = $m[1];
		// make external call:
		// take cfg settings
		// preporocess args
		// make call
		// get results
		// parse xml/json...
		// postrocess according cfg settings
		return []; //TODO: implement it!
	} else if(preg_match('/^\s*\[/', $cmd)) {
		//JSON here!
		return  json_decode($cmd);
	}
	//SQL:
	$stmt = Select($cmd, $args);
	return $stmt; //implements foreach
}


?>
