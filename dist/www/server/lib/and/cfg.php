<?php
$CURRENT_USER = 
  @$_SERVER['HTTP_X_AUTH_USER'] ?:
  @$_COOKIE['AUTH_USER'] ?:
  @$_SERVER['PHP_AUTH_USER'] ?:
  function_exists('posix_geuid') ?
   posix_getpwuid(posix_geuid())['name'] :
   getenv('USERNAME').'@'.getenv('USERDOMAIN');

$CURRENT_ROLES =
  @$_SERVER['HTTP_AUTH_ROLES'] ?:
  @$_COOKIE['AUTH_ROLES'] ?:
  '';

require_once(__DIR__.'/cache.php');
$main_cfg = array(
		  'default_db' => array(
					'dialect' => '',
					'server' => '',
					'user' => '',
					'pass' => ''
					)
		  );

function db_dialect($a) {
  return @$a['dialect'] ?: explode(':', $a['server'], 2 )[0];
}

if(getenv('MAIN_CFG'))
  $main_cfg = array_replace_recursive( $main_cfg,
				     cached_ini(getenv('MAIN_CFG'), true)
				     );
else //FIXME:rethink
  $main_cfg = array_replace_recursive( $main_cfg,
				     cached_ini(__DIR__.'/test-db.ini', true)
				     );
	
$default_db = $main_cfg['default_db'];

$a_table_db = array();
if(getenv('TABLE_DB')) {
  /*
    table_name = db_name
   */
  $a_table_db = cached_ini(getenv('TABLE_DB'));
  }

function table_db($table){
  global $main_cfg;
  global $a_table_db;
  return $main_cfg[@$a_table_db[$table] ?: 'default_db'];
}

if(getenv('LOCAL_USERS'))
  /*
    [user]
    role = D | role = S
   */
  $local_users = cached_ini(getenv('LOCAL_USERS'));
else
  $local_users = array( $CURRENT_USER => [ 'LOCAL' =>'D' ] );

function local_user($user) { global $local_users; return @$local_users[$user]; }

function cfg_parse_roles($a) {
	$r = array();
	$role =& $r['.default'];
	$table =& $role['.default'];
	$fld =& $table['.default'];
	$fld = array( '.level' => 10 ); //FIXME: constant (and .default too)
	foreach(array_map('trim',$a) as $l) { if(!$l || $l[0] == '#') continue;
	     if(preg_match('/^\[\s*([^!]+)(\s+!([0-9]+)!)?\s*\]$/i', $l, $m)) //[role]
	       { 	@$role =& $r[$m[1]] ?: array();
				$table =& $role['.default'];
				$fld =& $table['.default'];
				$fld = array( '.level' => @$m[3] ?: 10 ); //FIXME
				continue; 
	       }
	     if(preg_match('/^table\s+(.+)/i', $l, $m)) //table
	       { 	@$table =& $role[$m[1]] ?: 
					$role[$m[1]] = array('.default' => array()); 
				$fld =& $table['.default'];
				continue; 
	       }
	     if(preg_match('/^([.a-z$0-9_]+)\s*:\s*(.*)/i', $l, $m)) { //field or right
				if($m[2] == '') { //field
					@$fld =& $table[$m[1]] ?: $table[$m[1]] = array(); 
					continue; 
				}
	     }
		 //right definition
	     $pri = null;
	     if(preg_match('/^!([0-9]+)!\s+(.*)/', $m[2], $mm)) { $m[2] = $mm[2]; $pri = $mm[1]; }
	     if(preg_match('/^(all|allow|\+)$/i', $m[2])) $m[2] = '';
	     else if(preg_match('/^(deny|none|-)$/i', $m[2])) $m[2] = '-';
		 //var_dump($l, $m);
	     $fld[$m[1]] = $m[2];
		 if($pri) $fld[$m[1].'.level'] = $pri;
	}
	return $r;
}

if(getenv('LOCAL_ROLES'))
  /*
    [role]
    $r : filter
    $w : filter
    table table_name
      $r : filter
      $w : filter
    $all | object.verb : 
--
    filter::= sql | all | allow | none | deny | -
   */
  $local_objects_rights = 
    cached('ini', getenv('LOCAL_ROLES'),
       function($file) { return cfg_parse_roles(file($file)); });
else
  $local_objects_rights = array( );
  
function user_roles($user, $request_roles) {
  return cached('user-roles', "$user:$request_roles", 
		function($key, $user, $request_roles) {
		  $lu = local_user($user);
		  if(!$lu) return '';
		  $rr = array();
		  preg_match('/[a-zA-Z0-9_]+/', $request_roles, $rr);
		  if(!$rr)
		    return implode(' ', array_keys($lu, 'D', true));
		  return implode(' ', array_intersect($rr, array_keys($lu)));
		}
		, null, $user, $request_roles);
}

/*
  можно спросить глобальные натройки роли
  или настройки таблицы
*/
function object_right_params($role, $object = '.default', $field = '.default') {
	return cached('rights', "$role:$object:$field",
		function($key, $role, $object, $field) {
		  global $local_objects_rights;
		  return
		    @$local_objects_rights[$role][ $object ][ $field ] 
		    ?: array();
		}, null, $role, $object, $field);
}


$CURRENT_ROLES = user_roles($CURRENT_USER, $CURRENT_ROLES); 
$CURRENT_ROLES_CSV = str_replace(' ', ',', $CURRENT_ROLES);
$CURRENT_ROLES_ARRAY = explode(' ', $CURRENT_ROLES);


define('TOPLEVEL_FILE', @end(debug_backtrace())['file']?:__FILE__);

$CFG_STDIN_CONTENT = null;
function main_argument($str = true) {
  if(PHP_SAPI == 'cli') {
    $arg = $_SERVER['argv'][1] ?: '-';
    if($arg === '-')
      if($str) {
		global $CFG_STDIN_CONTENT;
		if($CFG_STDIN_CONTENT === null)
			$CFG_STDIN_CONTENT = explode("\0", file_get_contents('php://stdin'));
		return $CFG_STDIN_CONTENT[0];
	  }
      else return 'php://stdin';
    return $arg;
  }
  if($_SERVER['REQUEST_METHOD'] === 'GET') {
    return $_GET['cmd'];
  }
  if($_POST)
    return $_POST['cmd'];
  if($str) {
	global $CFG_STDIN_CONTENT;
	if($CFG_STDIN_CONTENT === null)
		$CFG_STDIN_CONTENT = explode("\0", file_get_contents('php://input'));
	return $CFG_STDIN_CONTENT[0];
  }
  return 'php://input';
}

function main_subarguments($str = true) {
  if(PHP_SAPI == 'cli') {
    $arg = $_SERVER['argv'][1] ?: '-';
	$args = [];
    if($arg === '-' && $str) {
		global $CFG_STDIN_CONTENT;
		if($CFG_STDIN_CONTENT === null)
			$CFG_STDIN_CONTENT = explode("\0", file_get_contents('php://stdin'));
		$args = array_slice($CFG_STDIN_CONTENT,1);
	  }
    return array_merge($args,array_slice($_SERVER['argv'], 2));
  }
  if($_SERVER['REQUEST_METHOD'] === 'GET') {
    return $_GET['args'];
  }
  if($_POST)
    return $_POST['args'];
  if($str) {
	global $CFG_STDIN_CONTENT;
	if($CFG_STDIN_CONTENT === null)
		$CFG_STDIN_CONTENT = explode("\0", file_get_contents('php://stdin'));
	return array_slice($CFG_STDIN_CONTENT,1);
  }
  return [];
}


if(__FILE__ != TOPLEVEL_FILE) return;

var_dump($main_cfg);
?>