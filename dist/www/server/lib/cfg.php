<?php
require_once('cache.php');
$main_cfg = array(
		  'cache' => array( 'timeout' => 1, 'local' => true ),
		  'default_db' => array(
					'dialect' => 'pgsql',
					'server'=>'pgsql:host=katia;port=5433;dbname=yoda',
					'user' => 'serious',
					'pass' => '1',
					)
		  );

/* SERVER EXAMPLES
 *
 *odbc
 *'server'=>"odbc:Driver={SQL Server};Server=SHUMSKY-XC-O3\SQLEXPRESS;Database=tst;Uid=puser;Pwd=1";
 *mysql,pgsql
 *'server'=>'pgsql:host=localhost;port=5432;dbname=test'
 *sqllite
 *'server' => 'sqlite:D:/soft/BD/sql_lite/test.db'
 *
 */

// Вырубил пока, чтобы не мешалось из-за ошибок
/*
$main_cfg = array_merge_recursive( $main_cfg,cached_ini($_ENV['MAIN_CFG'], true));

$local_users = cached_ini($_ENV['LOCAL_USERS']);
function local_user($user) { return @$local_users[$user]; }

$local_objects_rights = 
  cached('ini', $_ENV['LOCAL_ROLES'],
	 function() {
	   $r = array();
	   $h = fopen($_ENV['LOCAL_ROLES']);
	   $cc =& $r;
	   while(($l = fgets($h)) !== FALSE) {
	     $l = trim($l); if(!$l || $l[0] == '#') continue;
	     if(preg_match('/^role\s+(.+)/i', $l, $m)) { @$cc =& $r[$m[1]] ?: $r[$m[1]] = array(); continue; }
	     if(preg_match('/^table\s+(.+)/i', $l, $m)) { @$cc =& $cr[$m[1]] ?: $cr[$m[1]] = array(); continue; }
	     if(preg_match('/^([a-z$0-9_]+)\s*:\s*(.*)/i', $l, $m)) {
	       //if($m[2] == '') { @$cc =& $ct[$m[1]] ?: $ct[m[1]] = array(); continue; }
	       if(preg_match('/^(all|allow|\\+)$/i', $m[2])) $m[2] = '';
	       else if(preg_match('/^(deny|none|-)$/i', $m[2])) $m[2] = '-';
	       $cc[$m[1]] = $m[2];
	     }
	   }
	   return $r;
	 });
*/
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
function object_right_params($role, $object) {
  return cached('rights', "$role:$object",
		function($key, $role, $object) {
		  $lo = @$local_object_rights[$role] ?
		    @$local_object_rights[$role][ $object ] : array();
		  return lo;
		}, null, $role, $object);
}
?>