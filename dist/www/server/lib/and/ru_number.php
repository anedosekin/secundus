<?php

/*
spell out russian number

ru_number($n, $lcmd, $rcmd = null, $left = '', $right = '')

$left - command before dot
$left - command after dot

command:
	1) N - split by N digits
	2) 0 - output as simple number
	3) null - skip
	4) 'str' - m/f/n - gender
	left/right: 
		str or
		array [ 'рубль', 'рубля', 'рублей' ]
*/

function ru_number_take_word($n, $wlist) {
	if(!is_array($wlist)) return $wlist;
	switch((int)$n) {
		case 1: return $wlist[0];
		case 2: case 3: case 4: return $wlist[1];
		default: return $wlist[2];
	}
}

function ru_number($n, $lcmd = 3, $rcmd = null, $left = '', $right = '') {
	$r = '';
	$n = (string)$n;
	if($n === '0') {
		if($lcmd !== null) {
			if(is_string($lcmd))
				$r = 'ноль '.ru_number_take_word(0, $left);
			else if(is_numeric($lcmd))
				$r = '0 '.ru_number_take_word(0, $left);
		}
		if($rcmd !== null) {
			if($r !== '') $r .= ' ';
			if(is_string($rcmd))
				$r = 'ноль '.ru_number_take_word(0, $right);
			else if(is_numeric($rcmd))
				if($rcmd===0)
					$r = '0 '.ru_number_take_word(0, $right);
				else
					$r =  str_repeat('0', $rcmd).' '.ru_number_take_word(0, $right);
		}
		return $r;
	}
	$n = number_format( $n, is_string($rcmd)? 2 : $rcmd, '.', ' ');
	$n = explode('.', $n);
	$nl = explode(' ', $n[0]); $nr = $n[1];
}

?>