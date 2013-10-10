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

define('DEFAULT_ROLE_LEVEL', 10);

define('DEFAULT_FOR_ROLES', '.default');

require_once(__DIR__.'/cache.php');
require_once(__DIR__.'/cfg.php');


//return priority
function get_rights_for_object($verb, $role, $table, $object, &$filter) {
	$a = object_right_params($role, $table, $object);
	if(!$a || !isset($a[$verb])) 
		if($role === DEFAULT_FOR_ROLES ) { $filter = '-'; return DEFAULT_ROLE_LEVEL; } 
		else return null; 
	$filter = $a[$verb];
	return @$a[$verb.'.level'] ?:
			@object_right_params($role)['.level'] ?:
			DEFAULT_ROLE_LEVEL;
}
function collect_rights_for_object($verb, $role, $table, $object, &$filter) {
	return 
		get_rights_for_object($verb, $role, $table, $object, $filter) ?:
		get_rights_for_object($verb, $role, $table, DEFAULT_FOR_ROLES, $filter) ?:
		get_rights_for_object($verb, $role, DEFAULT_FOR_ROLES, DEFAULT_FOR_ROLES, $filter) ?:
		get_rights_for_object($verb, DEFAULT_FOR_ROLES, DEFAULT_FOR_ROLES, DEFAULT_FOR_ROLES, $filter);
}

function collect_all_rights_for_roles($verb, $roles, $table, $object = DEFAULT_FOR_ROLES) {
	global $CURRENT_ROLES_ARRAY;
	$roles = $roles ?: $CURRENT_ROLES_ARRAY;
	$res = array();
	$res_pri = 1000;
	foreach($roles as $role) {
		if(($pri = collect_rights_for_object($verb, $role, $table, $object, $par)) <= $res_pri) {
			if($pri < $res_pri) $res = array();
			$res[] = $par;
			$res_pri = $pri;
		}
	}
	return $res;
}

function merge_rights_for_roles(&$filter, $verb, $roles, $table, $object = DEFAULT_FOR_ROLES) {
  if(!$filter) $filter = [];
  if(is_array($object)) {
    foreach($object as $e)
      merge_rights_for_roles($filter, $verb, $roles, $table, $e);
    return $filter;
  }
  $filter = 
    array_merge($filter,
		array_filter(
			     collect_all_rights_for_roles($verb, 
							  $roles, $table, $object))
		); 
  return $filter;
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

function get_connection($table){
  static $connections = array();
  $db = table_db($table);
  $key = serialize($db);
  if(!@$connections[$key]) {
    if($db['user'] !== '')
      $connections[$key] = new PDO($db['server'],
				   $db['user'],
				   $db['pass']);
    else
      $connections[$key] = new PDO($db['server']);
    $connections[$key]->dialect = db_dialect($db);
    $connections[$key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connections[$key]->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::ATTR_ORACLE_NULLS);
  }
  return $connections[$key];
}


?>