<?php

/*
  без нагрузки - файл можно читать всегда, кэш не нужен
  под нагрузкой
     читаем из кэша?
     маленькие структуры - без разницы
     большие структуры - надо читать по частям
        т.е. десериализация тоже большая и дорогая
	
*/

/*
ini: 
1. timeout
2. zone => provider?
 что дает кэширования
 а. для переменной - можно не перечитывать (и можно не вести таймаутов даже!)
 (и иметь способ очистки)
 б. для того, что в другом месте - берем из другого места
   это место настраивается (как?)
   тут мы берем из быстрого кэша или из медленной ф-и
   это можно настраивать по зонам - какой кэш
   в любом случае там еще сериализация
   т.о. пока можно не использовать большой кэш
   опять же, причин использовать разные кэши особых нет
   разве что для файлов - они и так рядом
  тут, в принципе, можно любого провайдера
  но все равно лучше по зонам
  ini - apc
  user=>roles : apc + redis <= ini+ldap+db
  roles=>tables=>filter : apc + redis <= ini+ldap+db

  может быть все настройки надо уметь читать из ldap?
*/

$cache_local_get = function($key) { return null; };
$cache_local_set = function($key, $val, $ttl) {};

//if($main_cfg['cache']['local']){
//  if(function_exists('xcache_get')) $cache_local_get = 'xcache_get';
//  if(function_exists('xcache_set')) $cache_local_set = 'xcache_set';
//}



$x_cache_info = array();

function cached($zone, $key, $fval = null, $fkey = null) {
	// заглушка 
	$nargs = func_num_args();
	$args = $nargs > 4 ? array_slice(func_get_args(), 5) : array();
	array_unshift($args, $key);
	if($fkey) $key = call_user_func_array($fkey, $args);
	$val = call_user_func_array($fval, $args);
  /*
  global $x_cache_info;
  if(!array_key_exists($zone, $x_cache_info))
    $x_cache_info = array();
  //1. get memorized
  if(array_key_exists($key, $x_cache_info[$zone]))
      return $x_cache_info[$zone][$key];
  //2. get APC/XCACHE
  $val = cache_local_get("$zone:$key");
  if($val) 
    $val = unserialize($val);
  //3. ...
  else {
    //recalculte
    $nargs = func_num_args();
    $args = $nargs > 4 ? array_slice(func_get_args(), 5) : array();
    array_unshift($args, $key);
    if($fkey) $key = call_user_func_array($fkey, $args);
    $val = call_user_func_array($fval, $args);
  }
  */
  $x_cache_info[$zone][$key] = $val;
  $cache_local_set("$zone:$key", serialize($val), $main_cfg['cache']['ttl']);
  
  return $val;
}

function cached_ini($f, $sections = false) {
  return cached('ini', $f, parse_ini_file, null, $sections);
}

?>