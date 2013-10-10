<?php

class loop_info {
	static $top = null;

	var $outer = null;

	var $was_connector = false;
	var $group_starts = [];
	var $group_level = 0;
	
	var $level = 0;
	
	function at_group_start($value) {
		$idx = count($this->group_starts) - $this->group_level;
		if($idx >= count($this->group_starts))
		{	
			$this->group_starts[$idx] = $value;
			return true;
		} else {
			if($this->group_starts[$idx] === $value)
				return false;
			array_splice($this->group_starts, $idx); //remove all up to idx
			$this->group_starts[$idx] = $value;
			return $this->group_level;
		}
	}
}

class loop_helper extends IteratorIterator {
	var $info = null;

	var $counter = null;

	var $initial = 1;
	var $info = null;

  function __construct($i, &$counter = null, $initial = 1, $info = null) { 
			parent::__construct(
				$i === null ? new EmptyIterator :
				is_array($i)? new ArrayIterator($i) : $i
			); 
	$this->info = new loop_info;
	$this->info->outer = loop_info::$top;
	if($this->info->outer)
		$info->level = $this->info->outer->level+1;
	$this->counter = &$counter;
	$this->initial = $initial;
	$this->info = $info;
	loop_info::$top = $this->info;
  }
  function next (  ) { 
	if($this->counter === 0)
		parent::rewind();
	else    parent::next();
	++$this->counter;
  }
  function rewind (  ) {
	$this->counter = $this->initial;
	if($this->counter!==0)
		parent::rewind();
  }
  function valid() { 
	$ret == $this->counter === 0 || parent::valid();
	if($ret) { //at iteration very beginning!
		if($this->info->was_connector === true) {
			ob_end_flush();
			$this->info->was_connector = false;
		}
		//at_group_start will flush group ends, if any
		$this->info->group_level = 0;
	} else { //at iteration very end!
		while($this->info->group_level--)
			ob_end_flush();
		if($this->info->was_connector === true)
			ob_end_clean();
	}
  }
  function current() { return $this->counter? parent::current() : new everything_you_want($this->info); }

  function __destruct() { loop_info::$top = $this->info->outer; }
}
function with_loop_info($a, &$it = null) { return new loop_helper($a, $it); }
function with_loop_info_and_sample($a, &$it = null, $info = null) { return new loop_helper($a, $it, 0, $info); }

class everything_you_want { 
	private $cmd = null;
	function __construct($cmd = null) { $this->cmd = $cmd; }
	function cmd() { return $this->cmd; }
	function __get($name) { return null; } 
	function subselect_info($name) { return $this->cmd->subselects[$name]; }
}

function current_loop() { return loop_info::$top; }

function iteration_connector() { ob_start(); loop_info::$top->$info->was_connector = true; }

function at_group_start($value) { 
	if($cnt = $this->info->at_group_start($value)) {
		//
		while($cnt--) {
			--$this->info->group_level;
			ob_end_flush();
		}
		$this->info->was_connector = false;
	} else {
		ob_clean(); //clean previous end
		if(--$this->info->group_level === 0 &&
			$this->info->was_connector) {
			echo $this->info->was_connector; //if no groups, output connector!
			$this->info->was_connector = false;
		}
	}
}
function at_group_end() { 
	if($this->info->was_connector === true)
		$this->info->was_connector = ob_get_clean(); //group connector, get connector in variable!
	ob_start(); loop_info::$top->group_level++; 
}

function merge_queries($target, $cmd) {
	if(!$cmd) return $target;
	//take where, order, group from source and add it to target
	//or, if source is not a select, use data as is
	if(!is_string($cmd)) return $cmd;
	if(preg_match('/^\s*</', $cmd))
		if( dom_import_simplexml($xml = simplexml_load_string($cmd))->namespaceURI === 'http://xmlquery/query') {
			make_sql_from_xml($xml, $cmd, $args); //xml-query //FIXME: we should use args and return them!
			// and go forward to query processing
		} else return $cmd;
	else if(preg_match('/^s*CALL:(.*)/', $cmd)) return $cmd;
	else if(preg_match('/^\s*\[/', $cmd)) return $cmd;
	//SQL:
	//FIXME: subselects!
	//TODO: cache!
	global $SELECT_STRUCT, $RE_ID;
	$parsed_src = new parsedCommandSmart($SELECT_STRUCT, $cmd);
	$parsed_target = new parsedCommandSmart($SELECT_STRUCT, $target);
	if(!$parsed_src->ok || !$parsed_target->ok) return $prep_target; //FIXME: throw
	if(!preg_match("/^\s*($RE_ID)(\s|$)/",$parsed_src->FROM, $ms)) 
		throw new Exception("table not specified in incoming command, which is: $cmd");
	if(!preg_match("/^\s*($RE_ID)(\s|$)/",$parsed_target->FROM, $mt)) 
		throw new Exception("table not specified in template base command, which is: $target");
	if($mt[1] !== $ms[1])
		throw new Exception("tables in the template base command ($mt[1]) and in the incomming command($ms[1]) are diffrenet");
	//copy parts
	if(@$parsed_src->WHERE)
		$parsed_target->WHERE = 
			@$parsed_target->WHERE? "( $parsed_target->WHERE ) AND ($parsed_src->WHERE)"
			: $parsed_src->WHERE;
	if(@$parsed_src->{'GROUP BY'})
		$parsed_target->{'GROUP BY'} = 
			@$parsed_target->{'GROUP BY'}? $parsed_target->{'GROUP BY'}.', '.$parsed_src->{'GROUP BY'}
			: $parsed_src->{'GROUP BY'};
	if(@$parsed_src->{'ORDER BY'})
		$parsed_target->{'ORDER BY'} = 
			@$parsed_target->{'ORDER BY'}? $parsed_target->{'ORDER BY'}.', '.$parsed_src->{'ORDER BY'}
			: $parsed_src->{'ORDER BY'};
	if(@$parsed_src->LIMIT)
		$parsed_target->LIMIT = $parsed_src->LIMIT; //TODO: min, max, what?
	
	$target = (string)$parsed_target;
}

/*
functions

substr --> substr
left(n) === substr(0,n)
right(n) === substr(-n)
trim -->trim
ltrim, rtrim ---> ltrim, rtrim

replace ---> ???
lpad, rpad
nvl
around
round, trunc, rel_round
ru_date, ru_number

file reference with stamp!

*/

function NVL($v, $def) { return $v === null || $v === ''? $def: $v; }
function lpad($v, $cnt, $symb = ' ') { return str_pad($v, $cnt, $symb, STR_PAD_LEFT); }
function rpad($v, $cnt, $symb = ' ') { return str_pad($v, $cnt, $symb, STR_PAD_RIGHT); }
function replace($v, $from, $to) { return preg_replace($from, $to, $v); }
function around($v, $patt) { return $v === '' || $v===null ? $v : str_replace('?', $v, $patt); }

//function round
//function rel_round($v, $decs) {}
function ru_date($v) { preg_replace('/^(\s*)(\d\d\d\d)-(\d\d)-(\d\d)/', '$1$3.$2.$1', $v); }

require_once __DIR__."ru_number.php";

$included_templates = [];
function load_template($file) {
	global $included_templates;
	if(array_key_exists($file, $included_templates)) return $included_templates[$file];
	$included_templates[$file] = require_once($file);
	return $included_templates[$file];
}

function call_template($name, $file, $cmd, &$args, &$call_parameters) {
	if(!$file) $file = TOPLEVEL_FILE;
	
	$funcs = load_template($file);

	if(!$args) $args = [];
	if(!$call_parameters) $call_parameters = [];

	$func = $funcs[$name?:'_main_'];
	$func($cmd, $args, $call_parameters);

	$args = [];
	$call_parameters = []; //clear paramters after call
}
function template_reference($name, $file, $cmd, &$args, &$call_parameters) {
	if(!$file) $file = TOPLEVEL_FILE;
	if($name) { 
		array_unshift($args, $cmd);
		$cmd = 'T:'.$name;
	}
	$params = $call_parameters;
	$params['cmd'] = $cmd;
	$params['args'] = $args;
	$ret = $file.'?'.http_build_query($params);

	$args = [];
	$call_parameters = []; //clear paramters after call
	
	return $ret;
}

function dispatch_template($cmd, $args) {

	if(is_string($cmd) && preg_match('/^T:(.*)/', $cmd, $m)) {
		$func_name = $m[1];
		$cmd = array_shift($args);
	} else {
		$func_name = '_main_';
	}

	global $functions;
	
	if(!@$functions[$func_name])
		throw new Exception("cannot find template function '$func_name' in ".TOPLEVEL_FILE);
	
	$func = $functions[$func_name];
	$func($cmd, $args, $_REQUEST);
}


//helpers
function x_str_putcsv($a) {
	$f = fopen('php://memory', 'r+');
	fputcsv($v, $a, ',', "'");
	rewind($f);
	return stream_get_contents($f);
}

function make_manipulation_command($data, $counter) {
	if($counter) {
		//make core of update/delete
		$table = $data->getName();
		global $Tables;
		$table = $Tables->{$table};
		$pk = $table->PK(true);
		if(!$pk) return '';
		foreach($pk as $e)
			$d[] = $data->{$e};
		return $table->___name .' WHERE '
			. implode(' AND ', array_map(function($x){return "$x=?"}, $pk))
			. ';' . x_str_putcsv($d);
	} else {
		//if data is toplevel select, we can give its definition from toplevel map
		//if data in subselect, we has it's definition in map of subselects in its parent
		//make insert command
	
		$info = $data->cmd();
		if(!$info) return '';
		$args = @$info->args ?: []; 

		if(isset($info->parsed->{'GROUP BY'})) return ''; //maybe we can handle this as an insert into group?
		//FIXME: we assume, that all parameters reside in 'where', but it is not a case in general
		// so we should take all, replace '?' with param number, take where and replace back
		// collecting actualy used parameters
		return "INSERT INTO $info->table WHERE "
			.$info->cmd->doToString($info->parsed->WHERE)
			."\n" . x_str_putcsv($args);
		/*
			on client side (js) we take command and it it's a insert
			we: 
				take where (it's simple) and params (it's simple too)
				js doesn't have recursive regexp
					so, we need parse, but we do it on client and on demand
				how can we parse?
					just building a tree of nodes
				walk left to right
					take tokens with regexp (/...../g)
				process operands
		*/
	}
}

?>
