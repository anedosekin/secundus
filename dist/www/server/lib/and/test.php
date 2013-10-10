<?php
class aaa {
  function __get($name) {
    $this->$name = '1';
    return $name;
  }
  static $c = null;
  static function I() {
    echo 'I';
  }
  static $x = '1';
  const yy = '3';
}

$xx = '2';

// $dbh = new PDO('pgsql:dbname=p1 host=localhost port=5433', 'puser', 'puser');
// $stmt = $dbh->prepare('SELECT name, value FROM test');
// $stmt->setFetchMode(PDO::FETCH_CLASS, 'aaa', []);
// $stmt->execute();
// while($r = $stmt->fetch()) {
//   print_r($r);
//   echo $r->aaa, $r->aaa, "\n";
// }

echo $xx, aaa::$x;

$aaa  = 'aaa';

var_dump(preg_split('/(\( SELECT|\(|\))/i', 'xxx, ( SELECT ff ), (2)',
		    null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY));

$aa = [ 2,3,4];
foreach($aa as $a) {
  echo ' ', key($a), ' ';
}

$arr = [];

$arr['a'] = 'a';

array_unshift($arr, '0');
array_unshift($arr, '1');
array_unshift($arr, '2');

var_dump($arr);

echo 'in main:', __FILE__, ' ', 
  $_SERVER['DOCUMENT_ROOT'],'--',$_SERVER['PHP_SELF'],
  get_included_files()[0], '//', $_SERVER["SCRIPT_FILENAME"];

include(__DIR__.'/test2.php');


var_dump('main file!!!', debug_backtrace());

//require(__DIR__.'/rights.php');
//$c = get_connection('');


$cnt = 300000;

$str = "SELECT * FROM aaa WHERE f = 10 AND g = 20 AND g = 20AND g = 20AND g = 20AND g = 20AND g = 20";
    $r = explode(' FROM ', $str, 2);
var_dump($r);
    $r2 = explode(' WHERE ', $r[1], 2);


$t = microtime(true);
for($i = 0; $i < $cnt; ++$i)
  {
    $r = preg_split('/ ('.
		    implode('|',['FROM','WHERE','GROUP BY','ORDER BY']).') /i', $str,
		    null, PREG_SPLIT_DELIM_CAPTURE);
  }

echo "\npreg", microtime(true)-$t, "\n";

$t = microtime(true);
for($i = 0; $i < $cnt; ++$i)
  {
    $r = explode(' FROM ', $str, 2);
    $r2 = explode(' WHERE ', $r[1], 2);
    $r3 = explode(' GROUP BY ', @$r2[1]?:'', 2);
    $r4 = explode(' ORDER BY ', @$r3[1]?:'', 2);
  }

echo "\nsplit", microtime(true)-$t, "\n";

$t = microtime(true);
for($i = 0; $i < $cnt; ++$i)
  {
    preg_match('/^\s*(\()?\s*SELECT (.*) FROM ((?:(?! WHERE).)*)( WHERE .*)?( GROUP BY .*)?( ORDER BY .*)?/i', $str, $m);
   }
echo "\nmatch", microtime(true)-$t, "\n";

//parts
$SELECT_STRUCT = [ 'SELECT', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY' ];

function parse_ex_command($parts, $cmd) {
  $ret = (object)$parts;
  
  $re = '/ ('.implode('|', $parts).') /i';

  $split = preg_split($re, $cmd, null, PREG_SPLIT_DELIM_CAPTURE);
  if(array_shift($split) !== '') return null;
  foreach($parts as $part) {
    if(current($split) === $part) {
      array_shift($split);
      $ret->$part = array_shift($split);
    }
  }
  if(count($split)) return null;
  return ret;
}

$t = microtime(true);
for($i = 0; $i < $cnt; ++$i)
  { parse_ex_command($SELECT_STRUCT, $str);
   }
echo "\nmatch", microtime(true)-$t, "\n";


var_dump( preg_split('/(a|b)/', 'a v b c', null, 
		     PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY));

$aaa = (object)['a', 'b'];

$aaap = 'x x';
$aaa->$aaap = '2';

var_dump($aaa, $aaa->a, $aaa->{a}, $aaa->{'x x'});

?>