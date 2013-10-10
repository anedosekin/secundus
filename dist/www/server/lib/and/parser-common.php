<?php
define('RE_ID','[a-zA-Z_][a-zA-Z_0-9]*');
$RE_ID = RE_ID;
$RE_TABLE = "/^\\s*($RE_ID)\\s+($RE_ID)(\\s|$)/";
$RE_ONE_TABLE = "/^\\s*($RE_ID)\\s+($RE_ID)\\s*$/";

define('RE_STR', "/'[^']*(?:''[^']*)*'/");

$RE_FULL_ID = "/^$RE_ID$/";
$RE_ID_DONE = "/$RE_ID/";
$RE_PATH = "/(?<= )(?<! (?i:AS) )$RE_ID(?:\\.$RE_ID)*/";

//const _SQL_FORBIDDEN = 
//"\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";
const _SQL_FORBIDDEN = 
"\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

function strlist($func_or_array, $a = null, $b = null) {
  if($a === null) return implode(', ', $func_or_array);
  if($b === null) return implode(', ', array_map($func_or_array, $a));
  return implode(', ', array_map($func_or_array, $a, $b));
}

function split_by_strings($s) {
  return preg_split("/('[^']*(?:''[^']*)*')/", $s, 
		    null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
}

function levelized_process($s, $f) {
  static $RE_ARGS1 = '/
      \(
        (
          (?:
            (?>[^()]*)
            (?R)?
          )*
        )
      \)
  /xS';
  $cnt = 0;
  $lvl = 1;
  do {
    $s = preg_replace($RE_ARGS1, "~$lvl<$1>$lvl~", $s, -1, $cnt);
    ++$lvl;
  } while($cnt);
  --$lvl;
  while(--$lvl) {
    //assume, that all commas inside contained brackets replaced 
    $s = preg_replace("/(?<!^) # not from beginning, due to \G assert start too
        (?<=~$lvl< #from requested tag begin
          |\G #or from last replace
        )(  #capture it!
          (?>
            (?:(?!>$lvl~)[^,])* #anything, but end
          )
        )
        ,   #we are looking for it!!!
      /x",
      "$1~$lvl~",
      $s);
    $s = $f($s, $lvl);
  }
  //echo "\n?????",$s;
  $s = preg_replace(["/~[0-9]~/", "/~[0-9]</", "/>[0-9]~/" ], [ ',', '(', ')' ], $s);
  //echo "\n?????",$s;
  return $s;
}

class _PreCmd {
  const RE_STR = "/'[^']*(?:''[^']*)*'/";
  
  var $cmd = '';
  var $strings = [];
  var $selects = [];

  function unescape($d) { return str_replace("''","'",substr($this->strings[(int)$d], 1,-1)); }
  static function escape($s, $quote = true) { 
	  return 
		$quote ? "'".str_replace("'","''", $s)."'" : str_replace("'","''", $s);
	}
	
  function __construct($cmd) { 
    if(strcspn($cmd,_SQL_FORBIDDEN) != strlen($cmd))
      throw new Exception('forbidden symbol in command');
    $cmd = trim($cmd);
    $cmd = preg_replace_callback(_PreCmd::RE_STR,
			       function($str) {
				 $c = count($this->strings);
				 $this->strings[] = $str[0];
				 return "'$c'";
			       }, $cmd);
    if(strcspn($cmd,';') != strlen($cmd))
      throw new Exception('multiple commands');
    if(strcspn($cmd,'~') != strlen($cmd))
      throw new Exception('reserved symbol ~ in command outside strings');
    
    global $RE_ID;
    $cmd = preg_replace("/(?<!\\s|[.a-zA-Z0-9_])$RE_ID/"," $0", $cmd); //start all ids from space!
    //$cmd = preg_replace("/$RE_ID(?!\\s|[.a-zA-Z0-9_])/","$0 ", $cmd); //end all ids with space!
    $cmd = preg_replace('/\s+/',' ', $cmd);
    $cmd = str_replace([' .', '. '], '.', $cmd);
    
    $cmd = str_ireplace('( SELECT ','( SELECT ', $cmd);

    $cmd = levelized_process($cmd, function($cmd, $lvl) {
        return preg_replace_callback("/~$lvl<\s+SELECT\s+(.*?)>$lvl~/",
          function($a) {
            $r = '(%'.count($this->selects).')';
            $this->selects[] = "( SELECT $a[1])"; 
            return $r;
          },
          $cmd);
      }
    );
    $this->cmd = $cmd;
  }
  function subst($a) {
    return preg_replace_callback('/\(%([0-9]+)\)/', 
				 function($m) { return $this->subst($this->selects[(int)$m[1]]); },
				 $a
    );
  }
  function doToString($a) {
    if(!is_string($a)) $a = $a->select;
    return preg_replace_callback("/'([0-9]+)'/",
				 function($m) { return $this->strings[(int)$m[1]]; },
				 $this->subst($a));
  }
  //function __toString() { return (string)($this->cmd); }
}

class parsedCommand
{
  var $ok = false;
  function __construct($parts, $cmd) {
    $re = '/ ('.implode('|', $parts).')(?=\s|\(|\))/i';

    $split = preg_split($re, $cmd, null, PREG_SPLIT_DELIM_CAPTURE);
    $this->pfx = array_shift($split);
    $this->post = '';
    if( $this->pfx !== '' && $this->pfx != '(' ) return;
    if($this->pfx && !$split) return;
    $post = '';
    if($this->pfx) {
      $l = array_pop($split);
      $post = substr($l,-1);
      if($post !== ')') return;
      array_push($split, substr($l,0,-1));
    }
    foreach($parts as $part) {
      if(strcasecmp(current($split), $part)===0) {
        array_shift($split);
        if(($e = trim(array_shift($split))) !== '')
          $this->$part = $e;
      }
    }
    if($post)
      $this->post = $post;
    $this->ok = isset($this->{$parts[0]});
  }
  function __get($name)
  {
    if($name[0] === '_') {
      $n = substr($name,1);
      if(isset($this->$n)) return " $n {$this->$n}";
      return '';
    }
  }
  function __toString() {
    $r = $this->pfx;
    foreach($this as $k=>$v) { if(ctype_lower($k)) continue;
      if($v !== '')
        $r .= " $k $v";
    }
    $r .= $this->post;
    return $r;
  }

  function process_ids($part, $processor, &$tree, &$externals = null) {
    if(isset($this->$part))
      $this->$part = $processor->process_ids($this->$part, $tree, $externals);
  }
}

class parsedCommandSmart extends parsedCommand {
  var $pre = null;
  function __construct($parts, $cmd) {
    $this->pre = new _PreCmd($cmd);
    parent::__construct($this->cmd);
  }
  function __toString() { return $this->pre->doToString(parent::__toString()); }
}

?>