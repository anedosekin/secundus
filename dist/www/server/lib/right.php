<?php
/*
	права проверяем по базе
	object verb role params
	
	verb - имя процедуры
			read/write для полей и таблиц
	
	role - rolename
	object - path (with dots)
	
	db:
	     role => path => verb => filter
		
	каждый параметр имеет приориет (явно или по роли)
	объекты сканируем "вверх" для каждой роли
	нашли - выходим (возвращаем приоритет и параметр)
*/
class SelectData
{
	public $stmt=null;
	public $links=null;
}

define('SQL_FORBIDDEN',
       "\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F");
define('SQL_FORBIDDEN2', ';');

define('SQL_WHITE_FUNCS',
'/ (NULL|IS|SUM|MIN|MAX|AVG|AND|OR|NOT|IN|EXISTS|BETWEEN|LIKE|ESCAPE|CASE|WHEN|THEN|ELSE|END|LOWER|UPPER|POSITION|SUBSTRING|CHAR_LENGTH|CHARACTER_LENGTH|OCTET_LENGTH|LENGTH|TRIN|RTRIM|LTRIM|LEFT|RIGHT|ASC|DESC) /'
       );

define('RE_ID','[a-zA-Z_][a-zA-Z_0-9]*');
$RE_ID = RE_ID;
$RE_TABLE = "/^\\s*($RE_ID)\\s+($RE_ID)(\\s|$)/";
$RE_ONE_TABLE = "/^\\s*($RE_ID)\\s+($RE_ID)\\s*$/";

define('RE_STR', "/'[^']*(?:''[^']*)*'/");

define('JS_CMDTYPE', 'TYPE');
define('JS_SELECT', 'SELECT');
define('JS_INSERT', 'INSERT');
define('JS_UPDATE', 'UPDATE');
define('JS_DELETE', 'DELETE');
define('JS_GENSID','GENSID');
define('JS_RESULTSET','RESULTSET');

define('JS_FIELDS', 'FIELDS');

define('JS_TABLES', 'FROM');
define('JS_WHERE', 'WHERE');
define('JS_ORDER', 'ORDER');
define('JS_GROUP', 'GROUP');

define('JS_LINK', 'LINK');
define('JS_LINK_DATA','DATA');
define ('JS_LINK_INC','INSEL');
define ('JS_LINK_FILE','ISFILE');
define ('JS_LINK_ADDSID','ADDSID');

define('DEFAULT_OBJECT_PARAMS', '$all');
define('DEFAULT_ROLE_PRI', '$prioritet');
define('RIGHTS_READ', '$r');
define('RIGHTS_WRITE', '$w');
define('RIGHTS_ALLOW', '');
define('RIGHTS_DENY', '-');

$default_pri = 10;

require_once('cache.php');
require_once('cfg.php');

function get_right_param1($verb, $role_assigments, &$param, $alias) {
	global $default_pri;
	$pri = null;
	if(array_key_exists($verb, $role_assigments)) {
	  $pri = $role_assigments[$verb]->pri
	    ?: @$role_assigments[DEFAULT_ROLE_PRI] 
	    ?: $default_pri;
	  $param =  $role_assigments[$verb]->param;
	}
	$param = str_replace('$A', $alias, $param);
	return $pri;
}

function get_rights_param($aobj, $verb, $role, &$param, $alias) {
	$pri = null;
	$param = RIGHTS_DENY; //deny all by default

	$role_assigments =  object_right_params($role, DEFAULT_OBJECT_PARAMS); //default for role
	if(!$role_assigments) return $pri; // role not found
	$pri = get_right_param1($verb, $role_assigments, $param, $alias);

	if(!aobj) return $pri;

	$role_assigments = object_right_params($role, $aobj[0]); //table or another container
	if(!$role_assigments) return $pri; // table not found
	$pri = get_right_param1($verb, $role_assigments, $param, $alias);

	if(count($aobj)<2) return $pri; // no field part

	if(!array_key_exists($aobj[1], $role_assigments)) 
	    return $pri;
        $role_assigments = $role_assigments[$aobj[1]];
	$pri = get_right_param1($verb, $role_assigments, $param, $alias);
	return $pri;
}

function collect_rights_param($obj, $verb, $roles, $alias) {
	$res = array();
	$res_pri = 1000;
	foreach($roles as $role) {
		$par = '';
		if(($pri = get_rights_param($obj, $verb, $role, $par, $alias)) <= $res_pri) {
			if($pri < $res_pri) $res = array();
			$res[] = $par;
		}
	}
	return $res;
}

/*
*/
function parse_raw_object($raw_object) {
  global $funs_white_list;

  $raw_object = ' '.$raw_object.' '; //simlufy regexs

  //remove strings
  $raw_object = preg_replace(RE_STR, ' ', $raw_object);
  //remove special symbols
  $raw_object = strtr($raw_object, '(),+-*/|<>=?','                   ');
  //norlaize dot
  $raw_object = preg_replace('/ +\\. +/','.', $raw_object);
  //remove numbers
  $raw_object = preg_replace('/ \\d+(\\.\\d*) /',' ', $raw_object);

  if(preg_match('/[^a-zA-Z0-9. ]/', $raw_object))
    throw new Exception('forbidden symbol in command');

  //remove AS `id`
  //$raw_object = preg_replace("/\\sAS\\s+$RE_ID/", ' ', $raw_object);
  //now we dont include 'as' into select expr, but do it in compose select string

  //remove funcs
  $raw_object = preg_replace(SQL_WHITE_FUNCS, ' ', $raw_object);

  //fields eq alias.field
  $res = array();
  $a_f = '/ ($RE_ID\\.$RE_ID) /';
  preg_match_all($a_f, $raw_object, $res);
  //$res[1] ====> all alias.field matches

  $raw_object = preg_replace($a_f, ' ', $raw_object);

  $raw_object = str_replace(' ', '', $raw_object);
  if($raw_object != '')
      throw new Exception("unexpected char $raw_object");

  return $res[1];
}

function make_context($from, &$conds) {
  //table alias join table alias on expr

  //remove strings
  $from = preg_replace(RE_STR, ' ', $from);
  global $RE_ONE_TABLE;

  //remove special symbols
  $from = strtr($from, '(),+-*/|<>=?','                   ');

  if(preg_match('/[^a-zA-Z0-9. ]/', $from))
    throw new Exception('forbidden symbol in command');

  $tparts = preg_split('/\\s(LEFT)?\\s+(OUTER)?\\s+JOIN\\s/', $from);

  $res = array();
  foreach($tparts as $idx=>$tpart) {
    $tpc = explode(' ON ', $tpart, 2);
    if(isset($tpc[1])) $conds[] = $tpc[1];
    $a = array();
    if(preg_match($RE_ONE_TABLE, $tpc[0], $a))
      $res[$a[2]] = $a[1];
    else
      throw new Exception ('bad table part');
  }
  return $res;
}

/*
	composed_roles - string (used to make cache key and splitted)
	used_objects - map
		verb => array of expressions
	context - map
		alias => container object (map of elements, eq fields)
		used to check field presense
*/
function full_collect_rights_param($roles, $used_objects, $context, $base_res = array()) {
    $res = $base_res;

  if(!array_key_exists(RIGHTS_READ, $used_objects)) $used_objects[RIGHTS_READ] = array();
  if(is_string($context))
    $context = make_context($context, $used_objects[RIGHTS_READ]);

  foreach($used_objects as $verb => $objects) {
    foreach($objects as $raw_object) {
      $parsed_objects = parse_raw_object($raw_object);
      foreach($parsed_objects as $object) {
	$a = explode('.', $object); // [alias , field]
	if(!array_key_exists($a[0], $context))
	  throw new Exception("alias {$a[0]} not found!");
	$res = array_merge($res, collect_rights_param(array($context[$a[0]], $a[1]), $verb, $roles, $a[0]));
      }
    }
  }
  $res = array_filter(array_unique($res));
  if(in_array(RIGHTS_DENY, $res)) return NULL;
  return $res;
}

function process_expr_part(&$expr, &$used, $composed_roles) {
  if(is_string($expr))
    $used[] = $expr;
  else
    if($expr[JS_CMDTYPE] == JS_SELECT) {
      add_role_filter_to_command($expr, $composed_roles);
      $used[] = implode(' ', $expr[JS_LINK]);
    }
}

$command_cache = array(); // key => prepared statament

function add_role_filter_to_command(&$cmd, $composed_roles) {
  global $RE_TABLE;
  global $RE_ID;
  
  //TODO: check it!
  //if(strcspn($composed_command,SQL_FORBIDDEN) != strlen($composed_command))
  //throw new Exception('forbidden symbol in command');
  
  $roles = explode(' ', $composed_roles);

  $used = array( RIGHTS_READ => array(), RIGHTS_WRITE => array() );

  $context = make_context($cmd[JS_TABLES], $used[RIGHTS_READ]);

  $is_select = $cmd[JS_CMDTYPE] == JS_SELECT;
  
  $aliastmp=array();  
  preg_match($RE_TABLE, $cmd[JS_TABLES],$aliastmp); //table format checked before!
  $main_alias=$aliastmp[2];
  foreach($cmd[JS_FIELDS] as $fld=>$expr){
    if(is_string($fld) && !preg_match("/^$RE_ID$/", $fld))
      throw new Exception("Bad field alias $fld");
    if(!$is_select)
      $used[RIGHTS_WRITE][] = "$main_alias.$fld";      
    process_expr_part($expr, $used[RIGHTS_READ], $composed_roles);
  } 
  if(isset($cmd['DEFAULTS'])) {
    foreach(array_diff_key($cmd['DEFAULTS'], $cmd[JS_FIELDS]) as $fld=>$expr) {
      if(!is_string($fld) || !preg_match("/^$RE_ID$/", $fld))
	throw new Exception("Bad field alias $fld");
      if(!preg_match("/^'[^']*(?:''[^']*)*'$/", $expr) &&
	 !preg_match('/^\\d+(\\.\\d*)$/', $expr)) throw new Exception("Bad field default $expr");
    }
  }
  if(isset($cmd[JS_WHERE]))
    foreach($cmd[JS_WHERE] as $expr)
      process_expr_part($expr, $used[RIGHTS_READ], $composed_roles);
  if(isset($cmd[JS_ORDER]))
    foreach($cmd[JS_ORDER] as $expr)
      $used[RIGHTS_READ][] = $expr;
  if(isset($cmd[JS_GROUP]))
    foreach($cmd[JS_GROUP] as $expr)
      $used[RIGHTS_READ][] = $expr;
  //no need to process link at toplevel

  $filter = full_collect_rights_param( $roles, $used, $context);
  if($filter !== null && $is_select && isset($cmd['INTO'])) {
    $used = array( RIGHTS_WRITE => array() );
    foreach($cmd[JS_FIELDS] as $fld=>$expr)
	$used[RIGHTS_WRITE][] = "a.$fld";
    $filter = full_collect_rights_param($roles, $used, array('a'=>$cmd['INTO']), $filter);
  }
  if($filter === null) $filter = array('1=0');
  if($filter) {
    //$A -> alias (does not has `$`, do it's safe to replace first
    //$ROLES_LIST - replace with `( 'role', 'role', ...)`, due to there is no '$' in our roles, it's safe to replace here
    //$USER - replace with current user (global var, taken from request)
    $filter = str_replace(array('$ROLES_LIST', '$USER'), 
			  array( 'for now, replace roles with dummy value', 
			  		'for now, replace users with dummy value'/*$current_user*/)
			  , $filter);
    $cmd['ROLE_FLT'] = implode(' AND ', $filter);
    
  }
}

function main_table($cmd) {
	global $RE_TABLE;
	$rzlt=array();
	preg_match($RE_TABLE, $cmd[JS_TABLES],$rzlt);
    // in sometimes not work (alias in delete)
    // fix it, @ - it is KOSTYL =)
  	return @$rzlt[1]; //table format checked before!
  	
}
// return - array {prepared statment & LINK}
function make_command(&$cmd, $composed_roles,$dbh) {
	$composed_comand = make_string_command($cmd,$dbh);
	$cache_key = strpos( $composed_comand, '$USER' ) === false ?
	"$composed_comand; ROLES $composed_roles" : // independent from user
	"$composed_comand; ROLES $composed_roles; USER $current_user" //depends from user
	;
	
	static $command_cache = array();
	if(array_key_exists($cache_key, $command_cache)) return $command_cache[$cache_key];
	
	//$dbh = get_connection(main_table($cmd));// не понял, нафиг это
	//$composed_comand = make_string_command($cmd, $dbh);// это тоже
	/*
	$composed_command =
	cached('sql-commands', $cache_key,
			function($key) use(&$cmd, $dbh, $composed_roles) {
			add_role_filter_to_command($cmd, $composed_roles);
			return make_string_command($cmd, $dbh);
	
			});
	*/
	$command_cache[$cache_key] = $dbh->prepare($composed_comand);
	return $command_cache[$cache_key] = $dbh->prepare($composed_comand);
}

function make_where($cmd, $dbh) {
  $w1 = array();
  if (isset($cmd[JS_WHERE]))
  foreach($cmd[JS_WHERE] as $part)
    if(is_string($part)) $w1[] = $part;
    else compose_select_or_insert($part, $dbh, $part[JS_LINKS]);
  $where = implode('', $w1);
  $w2 = isset($cmd['ROLE_FLT']) ? $cmd['ROLE_FLT'] : '';
  return $where && $w2? "$w2 AND ( $where )" : $where? $where : $w2;
}

function compose_update($cmd, $dbh) {
  global $RE_ONE_TABLE;
  $one_table = preg_match($RE_ONE_TABLE,$cmd[JS_TABLES]);
  $set = array();
  foreach($cmd[JS_FIELDS] as $fldpair) {
    foreach($fldpair as $fld => $expr)
      $set[] = "$fld = $expr";
  }
   
  $set = implode(', ', $set);
  $from = $cmd[JS_TABLES];
  $where = make_where($cmd, $dbh);
  if($one_table)
    return "UPDATE $from SET $set WHERE $where";
  $main_table = main_table($cmd);
  if($dbh->dialect == 'pgsql')
    return "UPDATE $main_table xx SET $set FROM $from WHERE xx.* = a.*".($where? " AND $where":'');
  if($dbh->dialect == 'oracle') {
    $set1 = array();
    $set2 = array();
    foreach($cmd[JS_FIELDS] as $fld=>$expr) {
      $set1[] = "$fld = a__$fld";
      $set2[] = "$expr AS a__$fld";
    }
    $set1 = implode(', ', $set1);
    $set2 = implode(', ', $set2);
    return "UPDATE (SELECT a.*, $set2 FROM $from WHERE $where ) SET $set1";
  }
  if($dbh->dialect == 'mssql')
    return "UPDATE a SET $set FROM $from WHERE $where";
  if($dbh->dialect == 'mysql')
    return "UPDATE $from SET $set WHERE $where";
}

/*
  if insert t(f) select g as f from ... where w1;
  1) where for select (read)
  2) where for insert (write)

  with ttt as select g as f, defaults for t from ... where w1 and role1
  insert into t (f) select ttt a from t where role2

  мы не добавляем join для ролевых фильтров (по соглашению!)
  т.о. там свои подселекты, базирующиеся на текущей записи, 
  т.е. на именах полей под главной таблицей
  вложенные мы добавляем тоже - со своими алиасами 
  (это надо учесть в формировании фильтра - далеать постановку алиаса)
*/

function compose_insert($cmd, $dbh) {
  $flds = array();
  foreach($cmd[JS_FIELDS] as $fldpair) {
    foreach($fldpair as $fld => $expr)
      $flds[] = $fld;
  }
  $vals = array_pad(array(), count($flds), "?");
  $flds = implode(', ', $flds);
  $vals = implode(', ', $vals);
  $main_table = main_table($cmd);
  if(!isset($cmd[JS_WHERE])) //no where here === hack!
    return "INSERT INTO $main_table ($flds) VALUES ($vals)";
  $from = $cmd[JS_TABLES];
  $where = make_where($cmd, $dbh);
  return "INSERT INTO $main_table ($flds) SELECT $vals FROM $from WHERE $where";
}

function compose_delete($cmd, $dbh) {
  global $RE_ONE_TABLE;
  $one_table = preg_match($RE_ONE_TABLE,$cmd[JS_TABLES]);
  $main_table = main_table($cmd);
  $where =  make_where($cmd, $dbh);
  // added by Рома
  // не уверен но предположу что $from получается как-то так:
  $from = $cmd[JS_TABLES];
  if($one_table)
    return "DELETE FROM $from WHERE $where";
  if($dbh->dialect == 'pgsql')
    return "DELETE $main_table xx FROM $from WHERE xx.* = a.*".($where? " AND $where":'');
  if($dbh->dialect == 'oracle')
    return "DELETE FROM (SELECT a.* FROM $from WHERE $where)";
  if($dbh->dialect == 'mssql')
    return "DELETE a FROM $from WHERE $where";
  if($dbh->dialect == 'mysql')
    return "DELETE $main_table FROM $from WHERE $where";
}

function compose_select_or_insert($cmd, $dbh, $links = null) {
  if($links && isset($cmd['INTO']))
    throw new Exception('found subselect with into cause');

  $flds = array_keys($cmd[JS_FIELDS]);
  $select = array();
  // добавлена поддержка FIELDS типа 
  // "FIELDS":["f1",{"f2 alias":"f2"}]
  foreach($cmd[JS_FIELDS] as $fld)
  {
  	//=>$expr
  	//if(is_string($expr)) $select[] = "$expr AS $fld";
  	// если есть вложенный селект, то в нем должны быть филдсы, иначе это филд с алиасом
  	if(is_string($fld)) $select[] = "$fld";
  	else if (!isset($fld[JS_FIELDS])) foreach ($fld as $al=>$val) $select[] = "$val AS $al";
	
  }    
  if(isset($cmd['DEFAULTS']))
    foreach(array_diff_key($cmd['DEFAULTS'], $cmd[JS_FIELDS]) as $fld=>$expr)
      $select[] = "$expr AS $fld";

  $select = implode(', ', $select);
  $from = $cmd[JS_TABLES];
  $where = make_where($cmd, $dbh); 
  if($links) {
    //hack! replace '?' everywhere in where, it's safe anyway and works if we dont use '?' in our string constants
    $rpl = explode('?', $where);
    $rpl = array_map(function($x,$y){ return "$x $y"; }, $rpl, $links);
    $where = implode(' ', $rpl);
  }
  // если WHERE пустой, то не нужно его вставлять
  if ($where!="") $where=" WHERE ".$where;
  if(isset($cmd['INTO']))
    return "INSERT INTO {$cmd['INTO']} ( $flds ) SELECT $flds FROM (SELECT $select FROM $from $where)";
  $gb = isset($cmd[JS_GROUP])? " GROUP BY {$cmd[JS_GROUP]} " : '';
  $ob = isset($cmd[JS_ORDER])? " ORDER BY {$cmd[JS_ORDER]} " : '';
  return "SELECT $select FROM $from $where $gb $ob";
}

function make_string_command($cmd, $dbh){
  if($cmd[JS_CMDTYPE] == JS_SELECT) return compose_select_or_insert($cmd, $dbh);
  if($cmd[JS_CMDTYPE] == JS_INSERT) return compose_insert($cmd, $dbh);
  if($cmd[JS_CMDTYPE] == JS_UPDATE) return compose_update($cmd, $dbh);
  if($cmd[JS_CMDTYPE] == JS_DELETE) return compose_delete($cmd, $dbh);
}

function get_connection($table){
  global $main_cfg;
  static $connections = array();
  $db = array_key_exists($table, $main_cfg) ? $table : 'default_db';
  if(!@$connections[$db]) {
    $params = $main_cfg[$db];    
    //PDO::ATTR_PERSISTENT => true - кэширование сессии DB, работает только при создании объекта!!!
    //PDO::ATTR_ORACLE_NULLS=>PDO::NULL_TO_STRING - null -> ""
    $addparams=array(PDO::ATTR_PERSISTENT => true);
   	$connections[$db] = new PDO("{$params['server']}",$params['user'],$params['pass'],$addparams);
   	$connections[$db]->dialect = $params['dialect'];
   	$connections[$db]->setAttribute (PDO::ATTR_ORACLE_NULLS,PDO::NULL_TO_STRING);
   	$connections[$db]->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);// exceptions for all errors  
	if ($params['dialect']==="sqlsrv") 
	{
		$connections[$db]->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8); 	
		//mb PDO::SQLSRV_ENCODING_BINARY ???
	}
  }
  return $connections[$db];
}

//var_dump(collect_rights_param('table1.field1', RIGHTS_READ, 'sam'));
/*
var_dump(
	 full_collect_rights_param('table1.field2 table1.field3', 'sam', 
				   [ RIGHTS_READ => array('a.field1 + a.field1') ],
				   'table1 a join table2 b ON a.x = \'aa\''
				   ));
*/
?>