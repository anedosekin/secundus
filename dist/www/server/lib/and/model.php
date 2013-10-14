<?php
require_once(__DIR__.'/cfg.php');
require_once(__DIR__.'/rights.php');
require_once(__DIR__.'/dialects.php');
require_once __DIR__.'/parser-common.php';

class Table {
  var $___name = '';
  var $fields = [];
  function __construct( $name ) { $this->___name = $name; }
  function Fields($f) { 
	  foreach($f as $k => $fld)
	    {
			if(array_key_exists($k, $this->fields)) {
				foreach($fld as $n=>$v)
					$this->fields->$n = $v;
			} else $this->fields[$k] = $fld;
		}
  }
  function PK($many = false) {
	$ret = [];
	foreach($this->fields as $k => $v)
		if($v->pk) if($many) $ret[] = $k; else return $k;
	ksort($ret);
	return $ret;
  }
  function default_order() {
	//TODO: order by relation
	$ret = [];
	foreach($this->fields as $k => $v)
		if($v->order !== null)
			$ret[abs($v->order)] = $v->order < 0 ? $k.' DESC ' : $k;
	ksort($ret);
	return array_values($ret);
  }
}

class _Field {
  var $type = null;
  var $size = 0;
  var $precision = null;
  var $caption = '';
  var $pk = false;
  var $target = null;
  var $condition = null;
  var $sources = [];
  var $targets = [];
  var $order = null;

  function __construct( $table = null) { 
    global $Tables;
	$field = '';
	if(preg_match('/^(.*)\.(.*)/',$table, $m)) { $table = $m[1];  $field = $m[2]; }
	if($table) {
		$this->target = $Tables->$table; 
		$a = $field ? [ $field ] : $this->target->PK(true);
	}
	if($table && count($a) === 1) {
		$a = $a[0];
		$a = $this->target->fields[$a];
		$this->type = $a->type;
		$this->size = $a->size;
		$this->precision = $a->precision; //and so on...
	}
  }
  function getCondition($src_alias, $target_alias) {
	  if($this->condition) return str_replace(['src.', 'target.'], ["$src_alias.", "$target_alias."], $this->condition);
	  if($this->sources)
		  return implode(' AND ',
				array_map(function($a, $b) use($src_alias, $target_alias) {
						return "$src_alias.$a = $target_alias.$b";
					}
					, $this->sources, $this->targets)
			);
  }
}

function FK($target, $condition = null) {  
	$ret = new _Field($target);
	$ret->condition = $condition;
	return $ret;
}

$Tables = new stdClass;
function Table($name) {
  global $Tables;
  if(@$Tables->$name) return $Tables->$name;
  return  $Tables->$name = new Table($name);  
}
function SemiView($name, $select) {
  global $Tables;
  $Tables->$name = new Table($name);
  $Tables->$name->select = $select;
  return  $Tables->$name;
}

//// parser (SQL-like)
/*
	TABLE name [props] (
		name VARCHAR(10) PK [props]
		name @table # rel to table pk (or virtual rel to table, if multifield pk)
		name @table.field # rel to table and field
		name @table. # virtual rel (eq @table if @table has pk with multiple fields)
		name @table on 'condition' #to table with custom condition
		name local_rel.target_field # part of rel to table and field
	)
caption is a first string in definition table or field
*/

/*
TODO: 
	extended FK
		name @table ON 'expression' with src.field = target.field
	multifield FK
		for each field we know target!
	expression for fields (subst them in query)
*/

class modelParser extends _PreCmd {
	function __construct($str) { parent::__construct($str);
		$str = $this->cmd;
		global $RE_ID;
		$split = preg_split('/(?:^|\s)(TABLE|QUERY)\s+/i', $str, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		$split = array_filter(array_map('trim', $split));
		$mode = '';
		foreach($split as $tdef) {
			if(preg_match('/^(TABLE|QUERY)$/i',$tdef)) {
				$mode = $tdef;
			} else
			if($mode === 'TABLE' && preg_match("/($RE_ID)\s*\((.*)\)/s", $tdef, $m) ||
				$mode === 'QUERY' && preg_match("/($RE_ID)\s+'(\d+)'\s+\((.*)\)/s", $tdef, $m)
			) {
				$table = $m[1];
				$fields = $mode === 'TABLE' ? $m[2] : $m[3];
				$query = $mode === 'QUERY'? $m[2] : '';
				global $Tables;
				$fres = [];
				if(substr($fields,0,2) === '= ') { 
					$fres = $Tables->{substr($fields,2)}->fields;
				}
				else {
					$fn = levelized_process($fields, 
						function($fld, $lvl) {
							if($lvl === 1)
								return str_replace(',', ';', $fld);
							return $fld;
						}
					);
					$fields = $fn === $fields? str_replace(',', ';', $fn) : $fn;
					$fields = explode(';', $fields);
					$fields = array_map('trim', $fields);
					$fields = array_filter($fields);
					//var_dump($fields);
					foreach($fields as $f) {
						if(!preg_match("/\s*(?<name>$RE_ID)\s+
									(?<rel>@\s*)?(?<type>(?<local>$RE_ID)(?<haspart>\.(?<part>$RE_ID))?)
									(?:\(\s*(?<size>\d+)(?:\s*,\s*(?<prec>\d+))?\s*\))?
									(?<other>.*)/x", $f, $m))
								throw new Exception("stange field definition <<$f>>");
						$fname = $m['name'];
						$frel = @$m['rel'] ? true : false;
						$ftype = $m['type'];
						$flocal = $m['local'];
						$fpart = @$m['part']?:'';
						$fhaspart = @$m['haspart']? true : false;
						$fsize = @$m['size']?:0;
						$fprec = @$m['prec']?:'';
						$fpop = @$m['other']?: '';
						$fld = null;
						if($frel) { //relation here!
							$fld = FK($ftype);
							if($fpart) {
								$fld->sources[] = $fname;
								$fld->targets[] = $fpart;
							} else {
								$pk = $fhaspart ? [] : $fld->target->PK(true);
								if(count($pk)===1) {
									$fld->sources[] = $fname;
									$fld->targets[] = $pk[0];
								}
							}
						} else { //field or part of relation here
							$fld = new _Field;
							if($fpart) { //relation's part => copy info
								$src = $fres[$flocal]->target[$fpart];
								$fld->type = $src->type;
								$fld->size = $src->size;
								$fld->precision = $src->precision;
								//add condition to rel
								$fres[$local]->sources[] = $fname;
								$fres[$local]->targets[] = $fpart;
							} else {
								$fld->type = $ftype;
								$fld->size = $fsize;
								if($fprec !== '') $fld->precision = $fprec;
							}
						}
						//parse props here
						$props = array_map('trim', explode(' ', $fpop));
						foreach($props as $p) 
							if($p === 'PK') $fld->pk = true;
							else if(preg_match('/^PK\((\d+)\)/i', $p, $m)) $fld->pk = $m[1];
							else if(preg_match('/^ORDER\((\d+)\)/i', $p, $m)) $fld->order = $m[1];
						$fres[$fname] = $fld;
					}
				}
				if($mode === 'TABLE')
					Table($table)->Fields($fres);
				else if($mode === 'QUERY') {
					SemiView($table, $this->unescape($query))->Fields($fres);
				}
			}
		}
	}
}

////


function print_actual_model($model = null) {
	global $Tables;
	$model = $model ?: $Tables;
	foreach($model as $mod) {
		if(@$mod->select)
			echo "\nQUERY $mod->___name "._PreCmd::escape($mod->select)." (";
		else
			echo "\nTABLE $mod->___name (";
		$first = '';
		$rels = [];
		foreach($mod->fields as $k=>$f) {
			echo "\n\t$first$k "; $first = ',';
			if($f->target) { 
				echo '@',$f->target->___name;
				//3 cases: 1- rel to pk, 2 -rel to fld, 3-multifield rel
				//1: $f->sources empty
				//2: $f->sources has 1 elem
				//3: $f->sources has many elems
				if(count($f->sources)===0) {} //@target ===>PK
				else if(count($f->sources)===1) { //@target.fld
				    $a = $f->target->PK(true); 
					if(count($a) !== 1 || $a[0] !== $f->targets[0])
						echo '.', $f->targets[0]; 
				} else { //@target.
					echo '.';
					foreach($f->sources as $i => $r)
						$rels[$i] = $f->targets[$i];
				}
			} else { echo $f->type;
				// maybe, this is a case: name rel.field?
				if(array_key_exists($k, $rels)) {
					echo '.',$rels[$k];
				}else
				if($f->size) { echo '(',$f->size;
					if($f->precision) echo ',', $f->precision;
					echo ')';
				}
			}
			if($f->caption) echo ' '._PreCmd::escape($f->caption);
			if($f->pk)
				if($f->pk === true) echo " PK ";
				else echo "PK($f->pk)";
		}
		echo "\n\t)";
	}
}

function append_information_schema_to_model($schema) {
	$dbh = get_connection('');
		//TABLE_CATALOG
		//,TABLE_SCHEMA
	
	$cols = $dbh->query(<<<QQ
	SELECT 
		TABLE_NAME
		,COLUMN_NAME
		,COLUMN_DEFAULT
		,IS_NULLABLE
		,DATA_TYPE
		,CHARACTER_MAXIMUM_LENGTH
		,CHARACTER_OCTET_LENGTH
		,NUMERIC_PRECISION
		,NUMERIC_SCALE
		,DATETIME_PRECISION	
		FROM INFORMATION_SCHEMA.COLUMNS 
		WHERE TABLE_SCHEMA = '$schema'
		ORDER BY TABLE_NAME, ORDINAL_POSITION
QQ
)->fetchAll(PDO::FETCH_OBJ | PDO::FETCH_GROUP);

	$fks = $dbh->query(<<<QQ
	SELECT 
		a.table_name ||'.'|| a.column_name,
		d.table_name
	FROM INFORMATION_SCHEMA.key_column_usage a
	JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS b
	ON
		a.CONSTRAINT_CATALOG = b.CONSTRAINT_CATALOG
		AND a.CONSTRAINT_SCHEMA = b.CONSTRAINT_SCHEMA
		AND a.CONSTRAINT_NAME = b.CONSTRAINT_NAME
	JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS c
	ON
		a.CONSTRAINT_CATALOG = c.CONSTRAINT_CATALOG
		AND a.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA
		AND a.CONSTRAINT_NAME = c.CONSTRAINT_NAME
	JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS d
	ON
		c.UNIQUE_CONSTRAINT_CATALOG = d.CONSTRAINT_CATALOG
		AND c.UNIQUE_CONSTRAINT_SCHEMA = d.CONSTRAINT_SCHEMA
		AND c.UNIQUE_CONSTRAINT_NAME = d.CONSTRAINT_NAME
	WHERE 
		a.TABLE_SCHEMA = '$schema' 
		AND b.CONSTRAINT_TYPE = 'FOREIGN KEY'
QQ
)->fetchAll(PDO::FETCH_KEY_PAIR);

	$pks = $dbh->query(<<<QQ
	SELECT 
		a.table_name,
		a.column_name
	FROM INFORMATION_SCHEMA.key_column_usage a
	JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS b
	ON
		a.CONSTRAINT_CATALOG = b.CONSTRAINT_CATALOG
		AND a.CONSTRAINT_SCHEMA = b.CONSTRAINT_SCHEMA
		AND a.CONSTRAINT_NAME = b.CONSTRAINT_NAME
	WHERE 
		a.TABLE_SCHEMA = '$schema' 
		AND b.CONSTRAINT_TYPE = 'PRIMARY KEY'
QQ
)->fetchAll(PDO::FETCH_KEY_PAIR);

	//FIXME: multicolumn PK, FK!
	
	//var_dump($cols);
	global $type_translations_db_to_internal;
	$tr = $type_translations_db_to_internal[$dbh->dialect];
	foreach($cols as $t)
		foreach($t as $f)
			if(@$tr[$f->data_type]){
				$f->data_type = 'db'.$tr[$f->data_type];
			} else
				throw new Exception("unknown type: <$f->data_type>");
	//var_dump($cols['encountries']);
	foreach($cols as $t=>$fields) {
		$tbl = Table($t);
		$ft = [];
		foreach($fields as $f) {
			$fp = [$f->character_maximum_length];
			if(@$pks[$t] === $f->column_name) $fp[] = PK;
			$ft[$f->column_name] = 
				call_user_func_array($f->data_type, $fp);
		}
		$tbl->Fields($ft);
	}
	global $Tables;
	foreach($fks as $tf=>$tg) {
		list ($t,$f) = explode('.', $tf);
		$Tables->$t->fields[$f] = FK($tg);
	}
}

$tst = new modelParser(<<<MP
	TABLE dual ( f VARCHAR(1) PK)

	TABLE Types ( code VARCHAR PK )
	TABLE Persons ( fio VARCHAR PK, type @Types )
	TABLE Docs ( name VARCHAR PK, autor @Persons )
	TABLE encountries ( syrecordidw VARCHAR PK, enf_namew VARCHAR)

	TABLE dbootest ( rid DECIMAL PK, value VARCHAR(20) )
	TABLE dbootest_details ( link @dbootest PK, id DECIMAL PK, value VARCHAR(20) )

	QUERY UnnamedPersons 'SELECT * FROM Persons WHERE fio = ''-'' ' (=Persons)
	
	TABLE rmn_exp ( id DECIMAL PK )
		
	TABLE rmn_insel ( id DECIMAL PK )

	TABLE streets ( city_id VARCHAR PK, street_name VARCHAR PK, street_population DECIMAL )
	TABLE cities ( id VARCHAR PK, city_name VARCHAR, country VARCHAR)
	TABLE countries ( country_name VARCHAR PK, country VARCHAR)
	TABLE buildings (id VARCHAR PK, street_name VARCHAR, city_id VARCHAR, building_number DECIMAL)
	TABLE mailoffices ( office_name VARCHAR PK, building VARCHAR)
MP
);

if(__FILE__ != TOPLEVEL_FILE) return;

//append_information_schema_to_model('public');

print_actual_model();

?>
