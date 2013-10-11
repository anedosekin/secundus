<?php
$seqcmd = array(
		'pgsql' => "select nextval('mainseq')"
		);
		
$type_translations_db_to_internal = array(
	'default' => array(
		'VARCHAR' => 'VARCHAR', //eq NVARCHAR
		'CHAR' => 'CHAR',
		'DECIMAL' => 'DECIMAL',
		'INTEGER' => 'INTEGER',
		//FLOAT...
		'DATE' => 'DATE',
		'TIMESTAMP' => 'TIMESTAMP',
		'TIME' => 'TIME',
		'CLOB' => 'CLOB',
		'BLOB' => 'BLOB'
		),
	'pgsql' => array(
		'character varying' => 'VARCHAR', //eq NVARCHAR
		'character' => 'CHAR',
		'numeric' => 'DECIMAL',
		'integer' => 'INTEGER',
		//FLOAT...
		'date' => 'DATE',
		'timestamp without time zone' => 'TIMESTAMP', //better to work with(!) timezone!
		'time with time zone' => 'TIME',
		'text' => 'CLOB',
		'bytea' => 'BLOB'
		)
);

//TODO
// function to get current seq value 1) as char 2) as int
// server function to get seq value, based on client seq and rownum



function replace_dbspecific_funcs($cmd, $dialect) {
	static $repl = [
		'pgsql'=> [[],[]],
		'oci'=> [[],[]],
		'sqlsrv'=> [['||'],["+''+"]],
		'mysql'=> [[],[]],
	];
	static $fdef = [
		'LN' => [ 'sqlsrv' => 'LOG' ],
		'TRUNC' => [ 'pgsql' => 'TRUNC', 'oci' => 'TRUNC', 'sqlsrv' => 'ROUND$1$2,1$3', 'mysql' => 'TRUNCATE' ],
		'YEAR' => [ 'pgsql' => "DATE_PART$1'year',$2$3", 
				'oci' => 'EXTRACT$1year FROM $2$3', 
				'sqlsrv' => 'YEAR', 
				'mysql' => 'YEAR' ],
		'MONTH' => [ 'pgsql' => "DATE_PART$1'month',$2$3", 
				'oci' => 'EXTRACT$1month FROM $2$3', 
				'sqlsrv' => 'MONTH', 
				'mysql' => 'MONTH' ],
		'DAY' => [ 'pgsql' => "DATE_PART$1'day',$2$3", 
				'oci' => 'EXTRACT$1day FROM $2$3', 
				'sqlsrv' => 'DAY', 
				'mysql' => 'DAY' ],
		'DATE_TO_MONTHS' => [ 
				'pgsql' => "TO_CHAR$1$2,'yyyy-mm'$3", 
				'oci' => "TO_CHAR$1$2,'yyyy-mm')$3", 
				'sqlsrv' => "LEFT$1CONVERT<varchar,$2,120),7$3", 
				'mysql' => "DATE_FORMAT$1$2,'%Y-%m'$3" ],
		'MONTHS_BETWEEN' => [
				'pgsql' => 
						"$1 SELECT DATE_PART('year', mbw.d1)*12 + DATE_PART('month', mbw.d1) - DATE_PART('year', mbw.d2)*12 - DATE_PART('month', mbw.d2) FROM ( SELECT $2 AS d1 $3 $4 AS d2 ) mbw $5",
				'oci' => "MONTH_BETWEEN$1 TRUNC($2,'month') $3 TRUNC($4,'month') $5",
				'sqlsrv' => "DATEDIFF$1month, $4 $3 $2 $5",// --chage order
				'mysql' => "PERIOD_DIFF$1 date_format($2, '%Y%m') $3 date_format($4, '%Y%m') $5",
				],
		'DAYS_BETWEEN' => [
				'pgsql' => 
						"DATE_PART$1'day',($2)::TIMESTAMP - ($4)::TIMESTAMP $5",
				'oci' => "$1 TRUNC($2) - (TRUNC($4) $5",
				'sqlsrv' => "DATEDIFF$1day, $4 $3 $2 $5",// --chage order
				'mysql' => "DATEDIFF$1$2$3$4$5",
				],
		'ADD_DAYS' => [
				'pgsql' => "$1 ($2) + INTERVAL $4 DAY$5",
				'oci' => "$1 ($2) + INTERVAL $4 DAY$5",
				'sqlsrv' => "DATEADD$1day, $4 $3 $2 $5",// --chage order
				'mysql' => "$1 ($2) + INTERVAL $4 DAY$5",
				],
		'ADD_MONTHS' => [
				'pgsql' => "$1 ($2) + INTERVAL $4 MONTH$5",
				'oci' => "$1 ($2) + INTERVAL $4 MONTH$5",
				'sqlsrv' => "DATEADD$1month, $4 $3 $2 $5",// --chage order
				'mysql' => "$1 ($2) + INTERVAL $4 MONTH$5",
				],
		'NOW' => [ //with timezone, if possible!
				'pgsql' => "CURRENT_TIMESTAMP",
				'oci' => "CURRENT_TIMESTAMP",
				'sqlsrv' => "CURRENT_TIMESTAMP",
				'mysql' => "CURRENT_TIMESTAMP",
				],
		'TODAY' => [ 
				'pgsql' => "CURRENT_DATE",
				'oci' => "CURRENT_DATE ", //servel local
				'sqlsrv' => "CAST(CURRENT_TIMESTAMP AS DATE)", //servel local
				'mysql' => "CURRENT_DATE", //server local
				],
	];
	static $frepl_from = null;
	static $frepl_to = null;
	if(!$frepl_from) {
		$frepl_from = array_fill_keys(array_keys($repl), []);
		$frepl_to = array_fill_keys(array_keys($repl), []);
		foreach($fdef as $f=>$def)
			foreach($def as $d => $v) {
				if(strstr($v, '$5')) {
					//works at any(!) level, but take max area as argument, need levelization
					$frepl_from[$d][] = "/(?<=^|[^a-zA-Z0-9_])$f\s*(~#<)(.*?)(~#~)(.*?)(>#~)/";
					$frepl_to[$d][] = $v;
				} else
				if(strstr($v, '$3')) {
					//works at any(!) level, but take max area as argument, need levelization
					$frepl_from[$d][] = "/(?<=^|[^a-zA-Z0-9_])$f\s*(~#<)(.*?)(>#~)/";
					$frepl_to[$d][] = $v;
				} else {
					//works at all(!) levels at once
					$frepl_from[$d][] = "/(?<=^|[^a-zA-Z0-9_])$f(?=\s*~)/";
					$frepl_to[$d][] = $v;
				}
			}
	}
	$cmd = levelized_process($cmd,
		function($s, $lvl) use($frepl_from, $frepl_to, $dialect) {
			foreach($frepl_from[$dialect] as $k=>$v)
				$s = preg_replace(
					str_replace('#', $lvl, $v), //can be precalculated for all levels up to MAX
					$frepl_to[$dialect][$k],
					$s);
			return $s;
		}
	);
	return str_replace($repl[$dialect][0], $repl[$dialect][1], (string)$cmd);
}

//use it before subst constants or subselects
//TODO: escape \
class dbspecific_select {
	var $select = '';
	var $cmd = null;
	var $parsed = null;
	var $table = '';
	var $alias = '';
	function __construct($cmd, $select, $parsed) {
		$this->cmd = $cmd;
		$this->select = $select;
		$this->parsed = $parsed;
		main_table_of_many($parsed->FROM, $this->table, $this->alias, false);
	}
	function __toString() { return $this->cmd->doToString($this->select); }
}
function make_dbspecific_select($cmd, $parsed, $dialect) {
	$sel = $parsed;
	switch($dialect) {
		case 'oci': 
		  if(isset($parsed->LIMIT)) {
		    $l = $parsed->LIMIT; $parsed->LIMIT = '';
		    $sel = "SELECT * FROM ( $parsed ) WHERE ROWNUM <= $l";
	 	    $parsed->LIMIT = $l;
		  }
		  break;
		case 'sqlsrv': 
		  if(isset($parsed->LIMIT)) {
		    $l = $parsed->LIMIT; $parsed->LIMIT = '';
		    $sel = (string)$parsed;
			$sel = str_replace('SELECT ', "SELECT TOP $l ", $sel); 
	 	    $parsed->LIMIT = $l;
		  }
		  break;
	}
	return new dbspecific_select($cmd, replace_dbspecific_funcs($sel, $dialect), $parsed);
}

//FIXME: we should take alias from command! not from outside
function main_table_of_many($tables, &$main_table, &$alias, $table_requried = true) {
	global $RE_ID;
	if(preg_match("/^\s*($RE_ID)\s+($RE_ID)?\s*$/", $tables, $m)) { 
		$main_table = $m[1];
		$alias = $m[2];
		return false; //one table
	}
	if(!preg_match("/^\s*($RE_ID)\s+($RE_ID)\s/", $tables, $m))
		if($table_requried)
			throw new Exception("Can't find main table and it's alias in $tables");
		else
			return;
	$main_table = $m[1];
	$alias = $m[2];
	return true;
}

function make_dbspecific_insert_from_select($parsed, $sel, $dialect) {
	// in select part we have processed everyting before!
	switch($dialect) {
	}
	return $parsed->{'_INSERT INTO'}.' '.$sel; //nothing to do here!
}

function make_dbspecific_select_values($cmd, $dialect) {
  if($dialect == 'oci') return 'SELECT '.$cmd.' FROM DUAL';
  return 'SELECT '.$cmd;
}

//check every database if we have to have aliases in multitable update at left side if '='
// (if field reside in two tables)
function make_dbspecific_update($parsed, $dialect) {
	$ret = $parsed;
	if(main_table_of_many($parsed->UPDATE, $main_table, $alias)) {
		switch($dialect) {
		case 'pgsql':
		  $ret = "UPDATE $main_table xx SET $parsed->SET FROM $parsed->UPDATE WHERE xx.* = $alias.*"
		    .(@$parsed->WHERE? " AND ( $parsed->WHERE )":'');
		  break;
		case 'oci':
		  //UPDATE t SET f = v WHERE c ==> UPDATE (SELECT a1.*, v AS xx__f WHERE c) SET f = xx__f
		  // NOTE: this KEEP order of placeholders (should be SET before WHERE)
		  $lst = preg_split("/(?:^|,)\s*($RE_ID)\s*=/", $parsed->SET, 
				    null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		  do{
		    $f = current($lst);
		    $set[] =  "$f = xx_$f";
		    $exprs[] = next($lst) ." AS xx_$f";
		  }while(next($lst));
		  $set = strlist($set);
		  $exprs = strlist($exprs);
		  $ret = "UPDATE (SELECT $alias.*, $exprs FROM $parsed->UPDATE $parsed->_WHERE) SET $set";
		  break;
		case 'sqlsrv': $ret = "UPDATE $alias $parsed->_SET FROM $parsed->UPDATE$parsed->_WHERE"; break;
		case 'mysql': 
		  // we need return aliases back!
		  $parsed->SET = preg_replace("/(^|,)\s*($RE_ID)\s*=/", "$1 $alias.$2 =", $parsed->SET);
		  $ret = "$parsed->_UPDATE$parsed->_SET$parsed->_WHERE"; 
		  break;
		}
	} else
	if($dialect === 'sqlsrv') {
		//mysql use special syntax even for delete from one table only
		// and don't allow use aliases, but we need
		// so, convert it to multitable case
		$ret = "UPDATE $alias $parsed->_SET FROM $parsed->UPDATE $parsed->_WHERE";
	}
	return replace_dbspecific_funcs($ret, $dialect);
}
function make_dbspecific_delete($parsed, $dialect) {
	$ret = $parsed;
	if(main_table_of_many($from = $parsed->{'DELETE FROM'}, $main_table, $alias)) {
		switch($dialect) {
		case 'pgsql':
			$ret = "DELETE $main_table xx FROM $from WHERE xx.* = $alias.*"
					.(@$parsed->WHERE? " AND ( $parsed->WHERE )":'');
			break;
		case 'oci': 
			$ret = "DELETE FROM (SELECT $alias.* FROM $from $parsed->_WHERE)"; break;
		case 'sqlsrv': $ret = "DELETE $alias FROM $from $parsed->_WHERE"; break;
		case 'mysql': $ret = "DELETE $alias FROM $from $parsed->_WHERE"; break;
		}
	} else
	if($dialect === 'mysql' || $dialect === 'sqlsrv') {
		//mysql use special syntax even for delete from one table only
		// and don't allow use aliases, but we need
		// so, convert it to multitable case
		$ret = "DELETE $alias FROM $from $parsed->_WHERE";
	}
	return replace_dbspecific_funcs($ret, $dialect);
}

/*TODO
database specific functions
DATE:
	WEEK_DAY_OF
pg: date_part('dow',
ms: datepart(weekday,
or: 
my:

	DATE_FROM_YMD		
pg:						
ms:						
or:						
my:						

TIME:
		HOURS_OF			MINUTES_OF			SECONDS_OF		TIME_FROM_HMS
pg:
ms:
or:
my:

TIMESTAMP: DATE+TIME
	DATE_OF			TIME_OF			MAKE_TIMESTAMP	
pg:													
ms:													
or:													
my:													


CLOB:
		FIRST_OF
pg:
ms:
or:
my:

CHAR:
	LPAD RPAD
pg:
ms:
or:
my:

---
add null row
with t as (select 1 as a, '2' as b from dual)
select t.* from t 
union all 
select t.* from (select null as a from dual) a left outer join t on a.a is not null;

*/

?>
