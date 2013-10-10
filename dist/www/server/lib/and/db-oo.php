<?php

require_once(__DIR__.'/db-oo-transformer.php');


class axROW {
  private $cmd = null;
  function getName() { return $this->cmd->table; }
  function hasInfo() { return true; }
  function __construct($cmd, $subselect_stmts) {
    $this->cmd = $cmd;
    foreach($cmd->subselects as $p=>$s) {
      $args = [];
      foreach($s->args as $i=>$a) {
        $args[] = $this->$a;
        if($i) unset($this->$a); //remove all links but first
      }
      $subselect_stmts[$p]->execute($args);
      $n = $s->args[0];
      $this->$n = $subselect_stmts[$p]->fetchAll(); //replace first with fetched recordset
        //TODO: maybe, we can fetch later (by condition: AS ARRAY / AS TABLE (AS ROWS, AS SELECT) !)
        // or instantly join to string: AS JOIN WITH ', ' INTO a
    }
  }
  function subselect_info($name) { return $this->cmd->subselects[$name]; }
}

function has_subitems($e) {
  return is_array($e) || $e instanceof SimpleXMLElement && $e->children();
}

function get_subitems($e) {
  return is_array($e) ? $e : 
        $e instanceof SimpleXMLElement ? $e->children() 
        : null;
}


function __recPrepare($dbh, $cmd) {
  $stmt = $dbh->prepare($cmd);
  $substmts = [];
  foreach($cmd->subselects as $i=>$subselect)
      $substmts[$i] = __recPrepare($dbh, $subselect);
  $stmt->setFetchMode(PDO::FETCH_CLASS, 'axRow',
    			[$cmd, $substmts]); //we should set direct childs here
  return $stmt;
}

function Select($cmd, $args = null) {
  if(!preg_match('/^\s*SELECT\s/i',$cmd)) $cmd = 'SELECT '.$cmd;
  $cmd = get_cached_cmd_object($cmd);
  $stmt = __recPrepare(get_connection($cmd->root()), $cmd);
  if($args !== null)
    $stmt->execute((array)$args);
  return $stmt;
}

function get_cached_cmd_object_int($key, $cmd) { 
  global $CURRENT_USER, $DELETE_CACHE_ENTRY;
  return array( 'user' => $CURRENT_USER, 
       'cmd' => serialize(new _Cmd($cmd)) );
}

function get_cached_cmd_object($cmd) {
  global $CURRENT_ROLES, $CURRENT_USER;
 
  $ckey = "$cmd ROLSES $CURRENT_ROLES";

  $ctext = '';

  $cc = cached('sql-commands-all', $ckey);
  if($cc && 
     ($cc['user'] == $CURRENT_USER || $cc['user'] == '$ALL'))
    $ctext = $cc['cmd']; //entry exists for all users or only one user was rememberd
  else {
    if(!$cc) { // no command => make new cache entry for all users
      $ctext = cached('sql-commands-for-all', $ckey, 
                'get_cached_cmd_object_int', null, $cmd)['cmd'];
    } else {
      // current user is differnt from stored in cache for all users
      $ctext = cached('sql-commands-for-user', "$ckey USER $CURRENT_USER", 
                'get_cached_cmd_object_int', null, $cmd)['cmd'];
      if($ctext == $cc['cmd']) {
        //commands are the same for two different users
        // so, mark it as 'for all'
        cached('sql-commands-for-all', $ckey, SET_CACHE_ENTRY([ 'user' =>'$ALL', 'cmd' => $ctext ]));
      } else {
        if($cc['user'] !== '') {
          // command preliminary cached 'for all' => clear it
          cached('sql-commands-for-all', $ckey, SET_CACHE_ENTRY([ 'user' =>'', 'cmd' => '' ]));
          //and store in user cache too
          cached('sql-commands-for-user', "$ckey USER {$cc['user']}", SET_CACHE_ENTRY($cc));
        }
      }
    }
  }
  
  return unserialize($ctext);
}

function prepare_or_exec_command($cmd, $args) {
  $cmd = get_cached_cmd_object($cmd);
  $dbh = get_connection($cmd->root());
  $stmt = $dbh->prepare($cmd);
  if($args !== null)
    $stmt->execute($args);
  return $stmt;
}

function Insert($cmd, $args = null) {
  if(!preg_match('/^\s*INSERT\s+INTO\s/i',$cmd)) $cmd = 'INSERT INTO '.$cmd;
  if($args !== null) {
    $cmd .= '( '.strlist(array_keys($args)).' ) '
	 . ' VALUES ('.strlist(array_fill(0, count($args), '?')).')';
    $args = array_values($args);
  }
  return prepare_or_exec_command($cmd, $args);
}

function Update($cmd, $key = null, $args = null) {
  if(!preg_match('/^\s*UPDATE\s/i',$cmd)) $cmd = 'UPDATE '.$cmd;
  if($key !== null) { //we have arguments: we add SET and push keys at the end of paramaters
    if($args) { // if we have args, we make SET cause
      $c = explode(' WHERE ', $cmd, 2);
      $cmd = $c[0].' SET '.
        strlist(function($k) { return "$k = ?"; }, array_keys($args))
        .(@$c[1]? " WHERE $c[1]":'');
    }
    $args = array_merge(array_values((array)$args), (array)$key);
  }
  return prepare_or_exec_command($cmd, $args);
}
function Delete($cmd, $key = null) {
  if(!preg_match('/^\s*DELETE\s+FROM\s/i',$cmd)) $cmd = 'DELETE FROM '.$cmd;
  return prepare_or_exec_command($cmd, $key);
}


///CID

function GetCIDBase($dbh) {
  global $seqcmd;
  $stmt = $seqcmd[$dbh->dialect];
  return $dbh->query($stmt)->fetchColumn();
}

function GenCID($cid = null) { //if null = gen new
  static $dbh = null;
  if($dbh == null) $dbh = get_connection('');
  static $base = null;
  if($base == null) $base = GetCIDBase($dbh);
  static $cur = 0;
  if(!$cid)
    $cid = ++$cur;
  //return "$base.$cid";
  //0 reserverd for server side
  //1-5 as is
  //6-8 1,2,3 digit cid
  //9- size+cid
  if($cid <= 5) return "$base.$cid";
  if($cid <= 9) return "$base.6$cid";
  if($cid <= 99) return "$base.7$cid";
  if($cid <= 999) return "$base.8$cid";
  return $base.'.9'.strlen($cid).$cid;
}
function GenStringCID($size,$cid = null) { //if null = gen new
  return str_pad(
    str_replace('.','-',GenCID($cid)).'_'
    , $size, '0', STR_PAD_LEFT);
}

function SaveCollection($items) {
	/* by desing, should save sequence to db
		like xml-dataloader does
		but it can't generalize xml-dataloader nor make them simple
		due to:
		1) xml-dataloder deals with many tables at once and it's useful
			so, we should reorder file
			we can do it with xslt or xpath
			but if we use xslt, we need special pass
			and if we use xpath, it bind us to use xml only
			so, it's much siple do directly code in xml-dataloader,
			using plain insert/update
		2) we better know key fields in input sequence than in a model 
			and often we should not use PK, but other keys
		3) we can't do relation key calculation in manner like xml-dataloder do
			(using subselect)
		maybe, for 3, it generally useful
		to code it in a general model?
		we know target and when we make
			insert into t(f, rel.g) values(Vf, Vg)
		we can translate it as
			insert into t(f, rel) values( Vf,
				(select pk from T2 where g = Vg) )
		this translation can be done universally
		based on know left side of assigment (i.e. rel.g)
		we may think, that an actual value can be used as key filter
		this VERY useful! espcecially for unnatural(genrated) keys which is almat always has one field
		in case of natural keys, we know values and can use them as is
	*/
}


/*
  Select('a, b, c from t where id = ?')->execute([1])->fetch()/fetchAll();
  Select('a, b, c from t where id = ?', [x])
  Select('a, b, c from t where id = ?', [x])->fetch()/fetchAll()/fetchColumn()/fetchAll(PDO::FETCH_COLUMN)
*/

if(__FILE__ != TOPLEVEL_FILE) return;

static $RE_ARGS1 = '/(?<=^|[^a-zA-Z0-9])(?>\s*)
  (?<args>
    \(
      (
        (?:
          (?>[^()]*)
          (?&args)?
        )*
      )
    \)
  )
/sxS';
$RE_ARGS1 = '/
    \(
      (
        (?:
          (?>[^()]*)
          (?R)?
        )*
      )
    \)
/sxS';
//echo preg_replace($RE_ARGS1, 'ln[$2]', 'ln(10), cos(), ln(cos(10)), ln(ln(30))');

function arg_replaceL0($s) {
  global $RE_ARGS1;
  $cnt = 0;
  $lvl = 1;
  do {
    $s = preg_replace($RE_ARGS1, "<$lvl%$1%$lvl>", $s, -1, $cnt);
    ++$lvl;
  } while($cnt);
  --$lvl;
  while(--$lvl) {
    $s = preg_replace("/(?<![a-zA-Z0-9_])ln\s*<$lvl%(.*?)%$lvl>/", "log<$lvl%$1%$lvl>", $s);
  }
  $s = preg_replace("/<[0-9]%/", '(', $s);
  $s = preg_replace("/%[0-9]>/", ')', $s);
  return $s;
}

function arg_replaceL($s){
  return levelized_process($s,
    function($s, $lvl) {
      $s = preg_replace("/(?<![a-zA-Z0-9_])ln\s*~$lvl<(.*?)>$lvl~/", "log~$lvl<$1>$lvl~", $s);
      $s = preg_replace("/(?<![a-zA-Z0-9_])cos\s*~$lvl<(.*?)>$lvl~/", "sin~$lvl<$1>$lvl~", $s);
	return $s;
    }
  );
}

function arg_replace($s) {
  global $RE_ARGS1;
  //return preg_replace($RE_ARGS1, "arg_replace('$2')", $s);
  //preg_replace_callback($RE_ARGS1, function($a) { return 'ln['.arg_replace($a[2]).']'; } , $s);
  preg_match_all($RE_ARGS1, $s, $m);
  foreach($m[2] as &$e)
    $e = arg_replace($e);
  return implode($m[2]);
}

function rec_down_repl($s, &$p = 0) {
  $r = '';
  $length = strlen($s);
  $lvl = 0;
  for ($i = $p; $i < $length; ++$i) {
    switch($s[$i]) {
      case '(':
        ++$i;
        //++$lvl;
        $r .= '[';
        //$r .= '['.rec_down_repl($s, $i).']';
        continue;
      case ')':
        $r .= ']';
        $p = $i;
        //return $r;
        continue;
    }
    $r .= $s[$i];
  }
  return $r;
}

function rec_down_replX($s) {
  //$d = preg_split('/\(|\)/S', $s, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
  //$d = explode('(', $s);
  $r = '';
  for($p = $i = 0; $i = strpos($s, '(', $p); $p = $i+1) {
    $r .= substr($s, $p, $i - 1);
    $r .= '(';
  }
  //foreach($d as &$x)
    //$x = $x ==  '(' ? '[' : $x == ')' ? ']' : $x;
  //return implode($d);
}

$xsl_out = new DOMDocument();
$xsl_out->loadXML(
<<<XSL
	<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
		<xsl:output method="text" omit-xml-declaration="yes" indent="no"/>
		<xsl:template match="*"><xsl:value-of select="name()"/><xsl:apply-templates/></xsl:template>
		<xsl:template match="/root"><xsl:apply-templates/></xsl:template>
		<xsl:template match="a">(<xsl:apply-templates/>)</xsl:template>
		<xsl:template match="ln">log<xsl:apply-templates/></xsl:template>
		<xsl:template match="cos">sin<xsl:apply-templates/></xsl:template>
	</xsl:stylesheet>
XSL
);
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl_out);

function repl_with_xml($s) {
	global $proc;
	$s = htmlspecialchars($s);
	$s = preg_replace('/[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)*/i', '<$0/>', $s);
	$s = str_replace(['(',')'], ['<a>', '</a>'], $s);
	//$x = simplexml_load_string("<root>$s</root>");
	$x = new DOMDocument();
	$x->loadXML("<root>$s</root>");
	//return $proc->transformToXML($x);
	return $x->saveXML();
	return $s;
}

echo 'ln(10), cos(), ln(cos(10)), ln(ln(30))',"\n";
echo rec_down_repl('ln(10), cos(), ln(cos(10)), ln(ln(30))'),"\n";
echo arg_replaceL('ln(10), cos(), ln(cos(10)), ln(ln(30)), substr(s,1,2)'),"\n";
echo repl_with_xml('ln(10), cos(), ln(cos(10)), ln(ln(30)), substr(s,1,2)'),"\n";


$xml = simplexml_load_string("<query><aaa/><bbb/></query>");
foreach($xml as $k=>$v)
echo $k;



return;



$test = 'ln(10), cos(), ln(cos(10)), ln(ln(30)) ';
//$test = 'lxn(10), cos(), lxn(cos(10)), lxn(lxn(30)) ';
$test.= $test;
$test.= $test;
$test.= $test;
$test.= $test;
$test.= "ln($test)";
echo str_replace('ln','log', $test) == arg_replaceL($test) ? '!' : ':(',"\n";
//echo str_replace('ln','log', $test),"\n";
//echo arg_replaceL($test),"\n";

echo "\n";

$n = 10000;
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  repl_with_xml($test);
  ;
echo 'xml: ', microtime(true) - $t, "\n";
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  str_replace('ln','log', $test);
echo 'str: ', microtime(true) - $t, "\n";
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  arg_replaceL('ln','log', $test);
echo 'lvl: ', microtime(true) - $t, "\n";
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  //arg_replace($test);
  ;
echo 'rec: ', microtime(true) - $t, "\n";
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  //rec_down_repl($test);
  ;
echo 'own: ', microtime(true) - $t, "\n";
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  rec_down_replX($test);
echo 'owX: ', microtime(true) - $t, "\n";

$sx = simplexml_load_string('<a><b><a>1</a></b><b><a>2</a></b></a>');
foreach($sx as $k=>$v)
  echo $k,'->',$v->a;

return;

//var_dump($Tables);
echo "\n";

$c = new _Cmd("SELECT '1', 232, a.fio, fio as b FROM Persons ".
	      " WHERE a.fio LIKE 'aaa' AND EXISTS (SELECT 1 FROM Docs WHERE a.autor.join)");

echo $c;

//preg_match_all($RE_PATH, " a a.autor a.autor.join" , $m);
//preg_match(_SQL_FUNC_KWD, 'a.autor.join', $m);
//var_dump($m);

//preg_match($RE_FULL_ID, 'dual', $m);
//var_dump($m);

//return;

print "timing\n";

$nc = 1;//10000;

$t = microtime(true);

for($i = 0; $i < $nc; ++$i)
  $c = (string) new _Cmd("SELECT a.fio FROM Persons ".
	      " WHERE EXISTS (SELECT 1 FROM Docs WHERE a.autor.join)");

echo 'str: ', $t2 = microtime(true) - $t, "\n";


//////

$local_objects_rights =
  cfg_parse_roles(explode("\n", <<<ROLES
  [LOCAL]
    table encountries
      .r: null is null
ROLES
));

//var_dump($local_objects_rights);
//var_dump($CURRENT_USER, $CURRENT_ROLES);

$dbh = get_connection('');
$stmt = $dbh->prepare('SELECT a.enf_namew, b.enf_namew as uu,1 as bbb FROM encountries a, encountries b WHERE a.syrecordidw = b.syrecordidw');
$u = new stdClass;
$stmt->setFetchMode(PDO::FETCH_CLASS, 'axROW',
  [
    (object)['stmt' => $dbh->prepare('SELECT cast(? as varchar) as a'), 'args' => ['uu']]
  ]
);
//$stmt->setFetchMode(PDO::FETCH_INTO, $u );
//$stmt->setFetchMode(PDO::FETCH_BOTH);

function x__recPrepare($dbh, $cmd, $subselects) {
  //$stmt = $dbh->prepare($cmd);
  if($subselects) {
    //$stmt->setFetchMode(PDO::FETCH_CLASS, 'axRow', $subselects); //we should set direct childs here
    foreach($subselects as $subselect)
      $subselect->stmt = x__recPrepare($dbh, $subselect->stmt, $subselect->subselects);
  } //else
    //$stmt->setFetchMode(PDO::FETCH_CLASS, 'stdClass');
  return @$stmt;
}
function xSelect($cmd, $args = null) {
  if(!preg_match('/^\s*SELECT\s/i',$cmd)) $cmd = 'SELECT '.$cmd;
  $cmd = new _Cmd($cmd);
  $stmt = x__recPrepare(get_connection($cmd->root()), $cmd, $cmd->subselects);
  if($args !== null)
    $stmt->execute((array)$args);
  return @$stmt;
}

$n = 1;//0000;
$t = microtime(true);
for($i = 0; $i < $n; ++$i)
  $stmt = xSelect('a.enf_namew, 1 as bbb FROM encountries LIMIT 1');
echo 'no db: ', microtime(true) - $t, "\n";
$t = microtime(true);
for($i = 0; $i < $n; ++$i) {
  $stmt = Select('a.enf_namew, 1 as bbb FROM encountries LIMIT 1');
  $stmt->execute([]);
  $stmt->fetch();
}
echo '   db: ', microtime(true) - $t, "\n";
$t = microtime(true);
$dbh = get_connection('');
for($i = 0; $i < $n; ++$i) {
  $stmt = $dbh->prepare('SELECT a.enf_namew, 1 as bbb FROM encountries a LIMIT 1');
  $stmt->setFetchMode(PDO::FETCH_CLASS, 'stdClass');
  $stmt->execute([]);
  $stmt->fetch();
}
echo '  dbd: ', microtime(true) - $t, "\n";
$t = microtime(true);
$dbh = get_connection('');
  $stmt = $dbh->prepare('SELECT a.enf_namew, 1 as bbb FROM encountries a LIMIT 1');
  $stmt->setFetchMode(PDO::FETCH_CLASS, 'stdClass');
for($i = 0; $i < $n; ++$i) {
  $stmt->execute([]);
  $stmt->fetch();
}
echo '  dbp: ', microtime(true) - $t, "\n";

$stmt = Select('a.enf_namew, (SELECT cast(ext.enf_namew as varchar) as x FROM dual) AS ARRAY uu,1 as bbb FROM encountries');
var_dump($stmt);
$stmt->execute([]);
$r = $stmt->fetch();
$r = $stmt->fetch();
var_dump($r);
foreach($r as $k=>$v)
  echo " key: $k";


//js type inference
//http://www.ccs.neu.edu/home/dimvar/jstypes.html

?>
