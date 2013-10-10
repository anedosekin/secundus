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
$cache_local_unset = function($key) {};
if(getenv('CACHE') == 'local'){
  if(function_exists('xcache_get')) $cache_local_get = 'xcache_get';
  if(function_exists('xcache_set')) $cache_local_set = 'xcache_set';
  if(function_exists('xcache_unset')) $cache_local_unset = 'xcache_unset';
}

define('CACHE_TTL', getenv('CACHE_TTL') ?: 10);

$DELETE_CACHE_ENTRY = new stdClass;

class __WRAP_SET_CACHE_ENTRY { function __construct($val) { $this->val = $val; }}
function SET_CACHE_ENTRY($val) { return new __WRAP_SET_CACHE_ENTRY($val); }

/*
  cached(zone, key) check, if entry exists
  cached(zone, key, function) add cached entry, if none
  cached(zone, key, SET_CACHE_ENTRY(value)) add cached entry, if none
  cached(zone, key, DELETE_CACHE_ENTRY) delete cached entry
*/

function cached($zone, $key, $fval = null, $fkey = null) {
  global $cache_local_get, $cache_local_set, $DELETE_CACHE_ENTRY;
  static $x_cache_info = array();
  $isfunc = is_callable($fval);
  if(!array_key_exists($zone, $x_cache_info))
    $x_cache_info[$zone] = array();
  //1. get memorized
  if($isfunc && array_key_exists($key, $x_cache_info[$zone]))
      return $x_cache_info[$zone][$key];
  //2. get APC/XCACHE
  $val = $isfunc ? $cache_local_get("$zone:$key") : null;
  if(!$val) 
  {
  //3. ...
    if($fval === null)
      return $val;
    //recalculate
    $nargs = func_num_args();
    $args = $nargs > 4 ? array_slice(func_get_args(), 4) : array();
    array_unshift($args, $key);
    if($fkey) $key = call_user_func_array($fkey, $args);
    $val = $isfunc? call_user_func_array($fval, $args) : fval;
  }
  if($val !== $DELETE_CACHE_ENTRY) {
    if(is_a($fval, '__WRAP_SET_CACHE_ENTRY')) $val = $fval->val;
    $x_cache_info[$zone][$key] = $val;
    $cache_local_set("$zone:$key", $val, CACHE_TTL);
  } else {
    unset( $x_cache_info[$zone][$key] );
    $cache_local_unset("$zone:$key");
  }
  return $val;
}

function cached_ini($f, $sections = false) {
  return cached('ini', $f, 'parse_ini_file', null, $sections);
}

?>