<?php
require_once(__DIR__.'/cfg.php');
require_once(__DIR__.'/parser-common.php');

/* example
	<html>
	<head>
	...
	</head>
	<body>
		<table>
			<tr><td>[[@tr]][[LOOP]][[~fio]]<td>[[~type]]</tr>
		</table>
	</body>
	</html>
---
[-[ ----> [[
[[BEGIN:id]]...[[END:id]] ---> zone(function)
[[CALL:name:file]] --->call a named zone: TODO: pass params
[[LOOP]] ---> main command loop <---> foreach(with_loop_info($db) as $row){ ..... }
[[@tag:....]] ---> reposition next item before the tag and next-next item after the tag
[[@tag@attribute:....]] --->reposition as an attribute value
[[@tag@:......]] ---> reposition next item as a last attribute of the tag

loop
	[[$data : SELECT ....]]
	[[$data of $master : SELECT ....]]

field access
[[$data.field]] [[$data.path]]
field expression
[[$data.{expr}]]
operators
[[db part~default~op seq]]
if no default
[[db part~~op seq]]
if default start with ':'
[[db part~'~...'~...]]

*/

function templater_take_zones($text, $file) {
	echo '<',"?php\n";
	echo "require_once(getenv('P_LIB_PATH').'/template-runtime.php');";
	$zones = [];
	preg_replace_callback('/\[\[BEGIN:([a-zA-Z_][a-zA-Z_0-9])\]\](.*)\[\[END:\1\]\]/s', 
		function($m) use(&$zones){
			$zones[$m[1]] = $m[2];
			return '[[ZONE:$m[1]]]';
		}
	,$text);
	$zones['_main_'] = $text;
	foreach($zones as $k=>$v)
		templater_take_one_zone($k, $v, $file);
		
	echo "\n\nif(__FILE__ != TOPLEVEL_FILE) return \$functions;";
	echo "\n\ndispatch_template(\$cmd, \$args);";

	echo "\n?",'>';
}
$error_count = 0;

function uescape_template_command($cmd) {
	return preg_replace(
		['/\]-(-*+)\]/', '/-:/','/=:/'
			, '/(?<=\s)gt(?=\s)/'
			, '/(?<=\s)lt(?=\s)/'
			, '/(?<=\s)ge(?=\s)/'
			, '/(?<=\s)le(?=\s)/'
			, '/(?<=\s)ne(?=\s)/'
			, '/&gt;/', '/&lt;/', '/&amp;/', '/&quot;/'
		],
		[']$1]', '->', '=>'
			, '>', '<', '>=', '<=', '<>'
			, '>', '<', '&', '"',
		],
	$cmd);
}

function templater_take_one_zone($name, $text, $file) {
	global $error_count;
	global $RE_ID;

	echo <<<FUNC


\$functions['$name'] = function(\$cmd, \$args = null, \$context = null) {
	\$args_rest = func_get_args(); 
	array_shift(\$args_rest); array_shift(\$args_rest); array_shift(\$args_rest);

FUNC;
	//find root tag for repeat, if none, use body
	if(!preg_match("/\[\[(@[^:]*+:)?\s*+$RE_ID\s*+:.*?\]\]/si", $text)) 
		$text = preg_replace('/<BODY\s(.*?)>/si', '<BODY $1>[[@BODY:$data:]]', $text);

	//process repositions
	$to_process = null; //it's a reference to last processed noncommand part
	$repos = preg_split('/(\[\[@.*?\]\])/', $text, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
	//var_dump($repos);
	foreach($repos as &$part) { 
		if(preg_match('/^\[\[ \s*+ @ ([^:]*+) \s*+: (.*) \]\]$/sx', $part, $m)) {
			//[[@tag:command]] [[@tag@:command]] [[$tag@attribute:command]]
			//TODO: make a way to reposition _inside_ tag
			$tag = $m[1];
			$command = "[[$m[2]]]";
			if($to_process === null) { //nowhere to repos
				$part = $command;
				continue;
			}
			//find position in 'to_process'
			$attribute = null;
			if(preg_match('/(.*)@(.*)/',$tag, $m)) { $tag = $m[1]; $attribute = $m[2]; }
			//var_dump($tag, $attribute, $command, $to_process);
			if(!$tag) { // to line start
				$before = strrchr($to_process, '\n') ?: '';
				$to_process = 
					$before . $commnad . '</>' . substr($to_process, strlen($before));
			} else {
				switch($tag) {
				case '{}': case '()': case '[]':
					$to_find = $tag[0];
					if(preg_match("/(?<pre>.*)(?<tag>$to_fild(?:\s[^>]*)?>)(?<tail>.*)/si", $to_process, $tg_split)) {
						$to_process = $tg_split['pre'].$command."<<$tag>>".
							$tg_split['tag'].$tg_split['tail'];
					}
					else {
						++$error_count;
						echo "<<<<<<ERROR: tag '$tag' not found>>>>>>";
					}
					break;
				default:
					//find back
					if(preg_match("/(?<pre>.*)(?<tag><$tag(?:\s[^>]*)?>)(?<tail>.*)/si", $to_process, $tg_split)) {
						//var_dump($tg_split['pre'], $tg_split['tag'], $tg_split['tail']);
						if($attribute !== null) { //put inside tag
							if($attribute) { //replace attribute value
								if(preg_match("/\s$attribute=/", $tg_split['tag']))
									$tg_split['tag'] = preg_replace("/(?<=\s$attribute=)('[^']*'|\"[^\"]*\"|\S*)/i", 
											'"'.$command.'"'
											,$tg_split['tag']);
								else
									$tg_split['tag'] = str_replace('>', " .$attribute=\"$commnad\"", $tg_split['tag']);
							} else //
								$tg_split['tag'] = str_replace('>', ' '.$commnad, $tg_split['tag']);
							$to_process = $tg_split['pre'].$tg_split['tag'].$tg_split['tail'];
						} else {
							//here, we should find tag and and it's end
							$to_process = $tg_split['pre'].$command.'<<:>>'.
								$tg_split['tag'].$tg_split['tail'];
						}
					} else {
						++$error_count;
						echo "<<<<<<ERROR: tag '$tag' not found>>>>>>";
					}
				}
			}
			$part = ''; //kill part;
		} else {
			if(preg_match('/^\s*$/s', $part)) { $to_process .= $part; $part = ''; }
			else
				$to_process = &$part;
		}
	}
	$text = implode($repos);
	//echo $text;
	//add closing tags
	preg_replace('#</>(.*)#', '[[{]]$1[[}]]', $text); //up to end of line/file
	do {
		$text = preg_replace('#<<:>>
				(<(\S+?)(?:\s|>) [^<]*+
						(?:(?:<(?!\2)|(?-2))[^<]*+)*?
				</\2>)
			#sx', '[[{]]$1[[}]]', $textp = $text);
		//echo '====',$text;
	} while($textp !== $text);
	
	do {
		$text = preg_replace('#<<\{\}>> (\{ [^{]*+(?:(?-1)[^{]*+)*? \}) #sx', 
						'[[{]]$1[[}]]', $textp = $text);
	} while($textp !== $text);
	do {
		$text = preg_replace('#<<\[\]>> (\[ [^[]*+(?:(?-1)[^[]*+)*? \]) #sx', 
						'[[{]]$1[[}]]', $textp = $text);
	} while($textp !== $text);
	do {
		$text = preg_replace('#<<\(\)>> (\( [^(]*+(?:(?-1)[^(]*+)*? \)) #sx', 
						'[[{]]$1[[}]]', $textp = $text);
	} while($textp !== $text);
	
	//convert default call zones to explicit call by name
	preg_replace('/\[\[\s*+(CALL|REF)::(:.*?)?\]\]\s*+\[\[ZONE:($RE_ID)\]\]/si',
			'[[$1:$3::$2]]',
			$text);

	$selects = []; //varname => select definition
	//$select->fields = []; //expression => alias

	preg_match_all("/\[\[\s*+\\$($RE_ID)(?:\s++of\s++\\$($RE_ID))?\s*+:(.+?)\]\]/si", $text, $m);
	foreach($m[1] as $i=>$s) {
		if(array_key_exists($s, $selects))
			throw new Exception("select name '$s' redefinition");
		if(preg_match('/^\s*+SAMPLE\s++AND\s++(.*)/', $m[3], $g))
			$m[3] = $g[1];
		$c = $selects[$s] = new stdClass;
		$c->select = uescape_template_command($m[3][$i]);
		$c->fields = [];
		$c->arrays = [];
		$c->outer = @$m[2][$i];
		if($c->outer) {
			if(!array_key_exists($c->outer, $selects))
				throw new Exception("there is no select definition with name '$c->outer' to set as a master for '$s'");
			$selects[$c->outer]->arrays[$s] = $c;
		}
	}
	//var_dump($selects);

	//generate commands
	$text = preg_replace_callback('/(?<=\[\[).*?(?=\]\])/s', 
		function($m) use(&$selects){
			global $RE_ID;
			$cmd = $m[0];
			//var_dump($cmd);
			if(preg_match("/^\s*+\\$($RE_ID)(?:\s++of\s++\\$($RE_ID))?\s*+:(.*)/si", $cmd, $m)) {
				$sample = '';
				$a_add = '';
				if(preg_match('/^\s*+SAMPLE\s++AND\s/si', $m[3])) {
					$sample = '_and_sample';
					$a_add = "\${$m[2]}->subselect_info('$m[1]')";
					$a_add_top = "\$rowsets['$m[1]']";
				}
				if(@$m[2])
					$res = "foreach(with_loop_info$sample(\${$m[2]}->$m[1], \$counters->{$m[1]} $a_add) as \$$m[1])";
				else
					$res = "foreach(with_loop_info$sample(\$rowsets['$m[1]'], \$counters->{$m[1]} $a_add_top) as \$$m[1])";
			} else if(preg_match("/\s*+(CALL|REF):\s*+(?<id>$RE_ID)\s*+(:\s*+(?<file>[^:]*+))?(:(?<cmd>.*))?/si", $cmd, $m)){
					//to call and pass context we should assign elements to $call_context variable
					//to call and pass arguments we should assign elements to $command_args variable
					if($m[1]==='CALL') $op = 'call_template'; else $op = 'template_reference';
					$res = "$op('".$m['id']."','"
						.addslashes(@$m['file'])."','"
						.addslashes(@$m['cmd'])."', \$command_args,\$call_context)"; 
			} else if(preg_match("/^\s*[{}]\s*$/si", $cmd, $m)){
				$res = $cmd;
			} else {
					//field parsing
					$cmd = uescape_template_command($cmd);
					$strings = [];
					$cmd = preg_replace_callback('/
						\'[^\\\']*+(\.[^\\\']*+)*\'
					|
						\"[^\\\"]*+(\.[^\\\"]*+)*\"
					/sx', function($m) use(&$strings) {
							$c = count($strings); $strings[] = $m[0];
							return "'$c'";
						}
					,$cmd);
					$cmd_part = explode('~', $cmd);
					$cmd_part = array_map('trim', $cmd_part);
					$db_part = array_shift($cmd_part);
					//process field part
					$db_part = preg_replace_callback(
						"/\\$($RE_ID)\s*+\.((?:\s*+$RE_ID\s*+\.)*\s*+$RE_ID|\\{(?<expr>[^}]*+)\\})/s",
						function($m) use(&$selects, $strings) {
							//var_dump($m);
							if(!array_key_exists($m[1], $selects)) return $m[0]; //nothing to do
							$select = $selects[$m[1]];
							if(isset($m['expr'])) {
								$f = preg_replace_callback("/'(\d+)'/", 
									function($m) use($strings) { return $strings[(int)$m[1]];}
									,trim($m['expr'])
									);
								switch($f) {
								case 'NPP': return "\$counters->$m[1]";
								case 'COUNT': return "\$counters->$m[1]";
								case 'FIRST': return "(\$counters->$m[1] === 1)";
								case 'SAMPLE': return "(\$counters->$m[1] === 0)";
								case 'COMMAND': return "'".addslashes($selects->select)."'";
								}
								$alias = 'x_'.count($select->fields);
							} else {
								$f = preg_replace('/\s+/s', '', $m[2]);
								$alias = strpos($f,'.')? 
									//'x_'.count($select->fields) 
									str_replace('.','__',$f)
									: $f;
							}
							if(array_key_exists($f,$select->fields)) {
								$alias = $select->fields[$f];
								return "\$$m[1]->$alias";
							}
							$select->fields[$f] = $alias;
							return "\$$m[1]->$alias";
						}
						,$db_part
					);
					$res = $db_part;
					if($cmd_part && $cmd_part[0] !== '')
						$res = "NVL($res, ".
							( $cmd_part[0][0] === "'" ? array_shift($cmd_part)
								: addslashes(array_shift($cmd_part))
							)
							.")";
					foreach($cmd_part as $c) {
						if(preg_match("/(?<=^)\\$$RE_ID/", $c, $m))
							$ncmd = "\$$m[0] = $res";
						else
							if(preg_match("/^($RE_ID\()(.*)/s", $c, $m))
								$res = $m[1].$res.', '.$m[2];
							else
								$ncmd = "$c($res)";
					}
					if(    end($cmd_part) === 'unescaped_output' 
						|| end($cmd_part) === ''
						|| end($cmd_part) && end($cmd_part)[0] === '$' 
						) { $ncmd .= ';'; }
					else
						$res = "echo $res;";
					
					//restore strings
					$res = preg_replace_callback("/'(\d+)'/", 
						function($m) use(&$strings) { return $strings[(int)$m[1]];}
						,$res);
			}
			return $res;
		}
	, $text );

	//clear special tags
	$text = preg_replace('/\[\[ZONE:.*?\]\]/i', '', $text);

	//join sequential tags
	$text = str_replace(']][[','', $text);

	//phpise
	$text = preg_replace('/\[\[(.*?)\]\]/', '<'.'?php $1 ?'.'>', $text);

	//unescape tags
	$text = preg_replace('/\[-(-*+)\[/', '[$1[', $text);
	
	//repalce '*' with collected fields in all selects 
	// master declared before details
	// so we just go backward
	//var_dump($selects);
	end($selects);
	while($s = current($selects)) {
		//var_dump($s);
		$fields = 
			array_map(function($a,$b) { return $a===$b ? $a : "$a AS $b"; }
			,array_keys($s->fields), array_values($s->fields)
		);
		$fields = array_merge($fields, 
			array_map(function($a,$b) { return "( $b->select ) AS ARRAY $a"; }
			,array_keys($s->arrays), array_values($s->arrays)
		));
		$fields = implode(', ', $fields);
		$s->select = preg_replace('/(?<=^SELECT\s|\sSELECT\s|,|^)\s*\*\s*(?=,|FROM\s|$)/', " $fields ", $s->select);
		prev($selects);
	}

	//take first select as 'MAIN'
	$main_select_alias = array_keys($selects)[0];
	$select = $selects[$main_select_alias];
	unset($selects[$main_select_alias]);
	
	//$select == '' ===> use cmd as it is
	if($select === '-') { //just use one dummy row 
		$select = ['dummy' => null];
	}

	echo "\n\t\$counters = new stdClass;";
	echo "\n\t\$cmd = merge_queries('".addslashes($select->select)."', \$cmd);";
	echo "\n\t\$rowsets['$main_select_alias'] = process_query(\$cmd, \$args);";
	echo "\n\t\$rowsets['$main_select_alias']->args = \$args;";

	foreach($selects as $n=>$sel) {
		if(!$sel->outer) {
			//free standing select => execute it immidiatly
			echo "\n\t\$rowsets['$n'] = process_query('".addslashes($sel->select)."');";
		}
	}

	echo "\n?>";
	echo $text;
	echo "<?php \n";

	echo "\n}\n";
}

if(__FILE__ != TOPLEVEL_FILE) return;

/*
if($argc <= 1) {
	$text = file_get_contents('php://stdin');
	$out = 'php://stdout';
} else {
	$options = getopt("o::c:");
	$text = file_get_contents($options['c']);
	if(!isset($options['o'])) {
		$options['o'] = preg_replace('/(.*)\.[^.]$/','$1', $options['c']);
	}
	$out = $options['o'];
}

return;
*/


$text  = "<a><n> 
<:><aa>1
	<b>
	<:><a>sss</a>
	<c>
	<aa>2<b></aa>
	<d>
</aa>
<a>3</a>";

do { 
	$text = preg_replace('#<:>
				(?<d><(\S+?)(\s|>)
					[^<]*+(?:(?:<(?!\2)|(?&d))[^<]*+)*?
				</\2>)
		#sx', '{$1}', $textp = $text);
} while($text !== $textp);

//echo $text;

//return;

$text = <<<'TEXT'
<html>
	<head>
	...
	</head>
	<body>
		<table>
			<tr><td>[[@tr:$row : * FROM Persons]][[$row.fio]]<td>[[$row.type]]<td>[[$row.{'12'+2}]]
				<table>
				<tr><td>[[@tr:$det of $row :SELECT * FROM Docs WHERE a.autor.join]]
						[[$det.{NPP}]].[[$det.name]] - [[$det.autor.fio]]
				</tr>
				</table>
			</tr>
		</table>
	</body>
</html>
TEXT
;

templater_take_zones($text, 'xfile');

?>
