<?php

require_once(__DIR__.'/db-oo.php');

$db_oo_transform_tests = [
	//simple select
	"SELECT 1, '2', '3''4', 5, 6 as X, NULL FROM Persons " =>
		"SELECT 1, '2', '3''4', 5, 6 as X, NULL FROM Persons a1 LIMIT 1000",
	//select with expr
	"SELECT 1+2, SIGN(3), ABS(4+5) FROM Persons LIMIT ALL" =>
		"SELECT 1+2, SIGN(3), ABS(4+5) FROM Persons a1",
	//select with string expr
	"SELECT LENGTH('6'), SUBSTRING(a.fio, 1, 2), UPPER(a.fio), LOWER(a.fio), POSITION(a.fio, '.') AS pos FROM Persons " =>
		"SELECT LENGTH('6'), SUBSTRING(a1.fio, 1, 2), UPPER(a1.fio), LOWER(a1.fio), POSITION(a1.fio, '.') AS pos FROM Persons a1 LIMIT 1000",
	//select with string expr #2
	"SELECT LEFT('6', 1), RIGHT(a.fio, 1), TRIM(a.fio), LTRIM(a.fio), RTRIM(a.fio) FROM Persons " =>
		"SELECT LEFT('6', 1), RIGHT(a1.fio, 1), TRIM(a1.fio), LTRIM(a1.fio), RTRIM(a1.fio) FROM Persons a1 LIMIT 1000",
	//select with grouping
	"SELECT MIN(a.fio), MAX(a.fio), SUM(1), COUNT(2), AVG(2) FROM Persons GROUP BY a.fio" =>
		"SELECT MIN(a1.fio), MAX(a1.fio), SUM(1), COUNT(2), AVG(2) FROM Persons a1 GROUP BY a1.fio LIMIT 1000",
	//select hard expr
	"SELECT DISTINCT COALESCE(a.fio, '-'), CASE WHEN a.fio = '-' THEN '!' ELSE '#' END FROM Persons " =>
		"SELECT DISTINCT COALESCE(a1.fio, '-'), CASE WHEN a1.fio = '-' THEN '!' ELSE '#' END FROM Persons a1 LIMIT 1000", 
	//select conditions
	"SELECT NULL FROM Persons WHERE a.fio = '-' AND a.fi = '--' OR 1=0 AND ( a.fio LIKE '%x' OR a.fio IS NULL )" =>
		"SELECT NULL FROM Persons a1 WHERE a1.fio = '-' AND a1.fi = '--' OR 1=0 AND (a1.fio LIKE '%x' OR a1.fio IS NULL) LIMIT 1000",
	//select conditions with subselect 
	"SELECT NULL FROM Persons WHERE a.fio IS NOT NULL AND NOT EXISTS (SELECT NULL FROM Docs WHERE a.autor = ext.fio)" =>
		"SELECT NULL FROM Persons a1 WHERE a1.fio IS NOT NULL AND NOT EXISTS (SELECT NULL FROM Docs a2 WHERE a2.autor = a1.fio) LIMIT 1000",
	//select conditions with subselect rec
	"SELECT NULL FROM Persons WHERE a.fio IN (SELECT a.fio FROM Persons WHERE a.fio = ext.fio AND EXISTS(SELECT NULL FROM Docs WHERE a.autor = ext.fio)) LIMIT 1000" =>
	"SELECT NULL FROM Persons a1 WHERE a1.fio IN (SELECT a2.fio FROM Persons a2 WHERE a2.fio = a1.fio AND EXISTS(SELECT NULL FROM Docs a3 WHERE a3.autor = a2.fio)) LIMIT 1000", 
	//select conditions with subselect and join quasifield
	"SELECT NULL FROM Persons WHERE EXISTS (SELECT NULL FROM Docs WHERE a.autor.join)" =>
		"SELECT NULL FROM Persons a1 WHERE EXISTS (SELECT NULL FROM Docs a2 WHERE a2.autor = a1.fio) LIMIT 1000",
	//select with join
	"SELECT a.autor.fio FROM Docs" =>
		"SELECT a2.fio FROM Docs a1 JOIN Persons a2 ON a1.autor = a2.fio LIMIT 1000",
	//select with long join
	"SELECT a.autor.type.code FROM Docs" =>
		"SELECT a3.code FROM Docs a1 JOIN (Persons a2 JOIN Types a3 ON a2.type = a3.code) ON a1.autor = a2.fio LIMIT 1000",
	//select with order
	"SELECT a.fio FROM Persons WHERE a.type BETWEEN 1 AND 3 ORDER BY a.fio ASC, a.type DESC, a.fio" =>
		"SELECT a1.fio FROM Persons a1 WHERE a1.type BETWEEN 1 AND 3 ORDER BY a1.fio ASC, a1.type DESC, a1.fio LIMIT 1000",
	//select to array
	"SELECT '1', (SELECT a.name FROM Docs WHERE a.autor = ext.fio) AS ARRAY a FROM Persons" =>
		"SELECT '1', a1.fio AS a FROM Persons a1 LIMIT 1000",
	//select from many tables and default aliasing
	"SELECT fio, b.type.code FROM Persons a JOIN Persons b ON a.fio = b.fio" =>
		"SELECT a1.fio, a3.code FROM Persons a1 JOIN (Persons a2 JOIN Types a3 ON a2.type = a3.code) ON a1.fio = a2.fio LIMIT 1000",
	//select with typecasting
	"SELECT CAST(1 AS DECIMAL(10,1)), CAST(1 AS INTEGER), CAST(1 AS CHAR(1)), CAST(1 AS VARCHAR(10)) FROM Persons" =>
		"SELECT CAST(1 AS DECIMAL(10,1)), CAST(1 AS INTEGER), CAST(1 AS CHAR(1)), CAST(1 AS VARCHAR(10)) FROM Persons a1 LIMIT 1000",
	//select with typecasting #2
	"SELECT CAST(1 AS DATE), CAST(1 AS TIME), CAST(1 AS TIMESTAMP) FROM Persons" =>
		"SELECT CAST(1 AS DATE), CAST(1 AS TIME), CAST(1 AS TIMESTAMP) FROM Persons a1 LIMIT 1000",
		
	//select from client view
	"SELECT a.fio FROM UnnamedPersons" =>
		"SELECT a1.fio FROM (SELECT * FROM Persons WHERE fio = '-') a1 LIMIT 1000",

	//select from multyple tables
	"SELECT a.fio FROM Persons a, Persons b WHERE a.fio = b.fio" =>
		"SELECT a1.fio FROM Persons a1, Persons a2 WHERE a1.fio = a2.fio LIMIT 1000",
		
	//select from join with inplace select
	"SELECT a.fio FROM Persons, (SELECT * FROM Persons WHERE a.fio = ext.fio)" =>
		"SELECT a1.fio FROM Persons a1, (SELECT * FROM Persons a2 WHERE a2.fio = a1.fio) LIMIT 1000",

	//delete: simple
	"DELETE FROM Persons WHERE a.fio IS NULL" =>
		"DELETE FROM Persons a1 WHERE a1.fio IS NULL",
	//delete: joins
	"DELETE FROM Persons WHERE a.type.code = 1" =>
		[
		'pgsql' => 
			"DELETE Persons xx FROM Persons a1 JOIN Types a2 ON a1.type = a2.code WHERE xx.* = a1.* AND (a2.code = 1)"
		],

	//update: simple
	"UPDATE Persons SET a.fio = '1', a.type = a.type WHERE a.fio IS NULL" =>
		"UPDATE Persons a1 SET fio = '1', type = a1.type WHERE a1.fio IS NULL",
	//update: joins
	"UPDATE Persons SET a.fio = '1', a.type = a.type.code WHERE a.fio IS NULL" =>
		[ 'pgsql' => 
			"UPDATE Persons xx SET fio = '1', type = a2.code FROM Persons a1 JOIN Types a2 ON a1.type = a2.code WHERE xx.* = a1.* AND (a1.fio IS NULL)"
		],

	//insert values
	"INSERT INTO Persons (fio, type) VALUES('-',1)" =>
		"INSERT INTO Persons (fio, type) VALUES ('-',1)",
	//insert simple select
	"INSERT INTO Persons (fio, type) SELECT fio, 2 FROM Persons WHERE a.type = 1" =>
		"INSERT INTO Persons (fio, type) SELECT a1.fio, 2 FROM Persons a1 WHERE a1.type = 1",
	//insert select with join
	"INSERT INTO Persons (fio, type) SELECT fio, a.type.code FROM Persons WHERE a.type = 1" =>
		"INSERT INTO Persons (fio, type) SELECT a1.fio, a2.code FROM Persons a1 JOIN Types a2 ON a1.type = a2.code WHERE a1.type = 1",
	
	//function transform: date
	"SELECT YEAR(d), MONTH(d), DAY(d) FROM Persons"=>
		[
			'pgsql' =>
				"SELECT DATE_PART('year', a1.d), DATE_PART('month', a1.d), DATE_PART('day', a1.d) FROM Persons a1 LIMIT 1000"
		],
	//function transform: complicated date
	"SELECT DATE_TO_MONTHS(d), MONTHS_BETWEEN(d_to, d_from) FROM Persons"=>
		[
			'pgsql' =>
				"SELECT TO_CHAR(a1.d,'yyyy-mm'), (SELECT DATE_PART('year', mbw.d1)*12 + DATE_PART('month', mbw.d1) - DATE_PART('year', mbw.d2)*12 - DATE_PART('month', mbw.d2) FROM (SELECT a1.d_to AS d1, a1.d_from AS d2) mbw) FROM Persons a1 LIMIT 1000"
		],
];

$local_objects_rights =
  cfg_parse_roles(explode("\n", <<<ROLES
  [LOCAL]
	.r: all
	.d: all
	.u: all
	.c: all
ROLES
));

$db = table_db('');
$dialect = db_dialect($db);

foreach($db_oo_transform_tests as $t => $e) {
	echo "\ntest: $t";
	//ob_start();

	$d = [];
	if(!is_array($e)) {
		$d[$dialect] = $e;
	} else {
		$d = $e;
	}

	$ok = true;
	foreach($d as $dia=>$ee) {
		$db['dialect'] = $dia;
		$c = new _Cmd($t);
		$r = $c;
		//$errs = ob_end_clean();
		//if($errs){
			//echo "Was unxpected output: $errs";
			//continue;
		//}
		$r = trim(preg_replace('/\s+/', ' ', $r));
		$r = str_replace(['( ', ' )', ' ,'], ['(', ')', ','], $r);
		if($ee !== $r)
		{
			for($i = 0; $i < MIN(strlen ($r), strlen ($ee)); ++$i)
				if($ee[$i] != $r[$i])
					break;
			$fill = str_repeat(' ',8).str_repeat('.',$i).'^';
			echo "\nError $dia\nExpect  $ee\nBut got $r\n$fill";
			$ok = false;
			break;
		}
	}
	if($ok)
		echo "\n\t...pass...";
	else return; //break;
}


//TODO: repeat for all dialects

$conn = get_connection('');

try { $conn->exec('DROP TABLE dbootest_details'); } catch(Exception $e) { /*ignore drop error*/ }
try { $conn->exec('DROP TABLE dbootest'); } catch(Exception $e) { /*ignore drop error*/ }
$conn->exec('CREATE TABLE dbootest ( rid decimal(20,10) PRIMARY KEY, value VARCHAR(20) )');
$conn->exec('CREATE TABLE dbootest_details ( 
				link decimal(20,10) REFERENCES dbootest(rid),
				id decimal(20,10),
				value VARCHAR(20) )');


echo "\ndb prepered for tests....";

$db_oo_db_tests = [
	"Insert into dbootest(rid, value) values (1,'1')" => 1,
	"Insert into dbootest(rid, value) values (2,'2')" => 1,
	"Insert into dbootest(rid, value) values (3,'3')" => 1,
	"Update dbootest SET a.value = '22' WHERE a.rid = 2" => 1,
	"Delete from dbootest WHERE a.rid = 1" => 1,
	"Select rid, value FROM dbootest ORDER BY rid" =>
		"rid:2 value:22; rid:3 value:3",
	"Insert into dbootest_details(link, id, value) values (2, 1, '2-1')" => 1,
	"Insert into dbootest_details(link, id, value) values (2, 2, '2-2')" => 1,
	"Insert into dbootest_details(link, id, value) values (3, 1, '3-1')" => 1,
	"Insert into dbootest_details(link, id, value) values (3, 2, '3-2')" => 1,
	"Select rid, (select value FROM dbootest_details WHERE a.link=ext.rid ORDER BY a.id) AS ARRAY arr FROM dbootest ORDER BY rid" =>
		"rid:2 arr: [ value:2-1; value:2-2 ] ; rid:3 arr: [ value:3-1; value:3-2 ]",
];

foreach($db_oo_db_tests as $t => $e) {
	echo "\ntest: $t";
	//ob_start();

	$dia = $dialect;

	preg_match('/^[A-Za-z]+/',$t, $m);
	$cmd = $m[0];

	$stmt = $cmd($t); //prepare command
	if($cmd == 'Select') {
		$cmp = [];
		$stmt->execute();
		foreach($stmt as $r) {
			$cc = [];
			foreach($r as $k=>$v)
				if(has_subitems($v)) {
					$ccc = [];
					foreach($v as $v) {
						$cccc = [];
						foreach($v as $k1=>$v1) {
							$cccc[] = $k1.':'.preg_replace('/\.0*/','',$v1);
						}
						$ccc[] = implode(' ', $cccc);
					}
					$cc[] = $k.': [ '. implode('; ', $ccc) . ' ]';
				} else
					$cc[] = $k.':'.preg_replace('/\.0*/','',$v);
			$cmp[] = implode(' ',$cc);
		}
		$r = implode('; ', $cmp);
		$e = preg_replace('/\s+/',' ', $e);
		$ee = preg_replace('/ ;/',';', $e);
		if($ee !== $r)
		{
			for($i = 0; $i < MIN(strlen ($r), strlen ($ee)); ++$i)
				if($ee[$i] != $r[$i])
					break;
			$fill = str_repeat(' ',8).str_repeat('.',$i).'^';
			echo "\nError $dialect\nExpect  $ee\nBut got $r\n$fill";
			$ok = false;
			break;
		}
		
	} else {
		if(!$stmt->execute()) {
			echo "\nERROR in $dialect";
			break;
		}
		$cnt = $stmt->rowCount();
		if($cnt != $e) {
			echo "\nUNEXPECTED result: want $e but got $cnt";
			break;
		}
	}
	echo "\n\t...pass...";
}


//var_dump($Tables->Persons);

echo "\n\n\tDONE!";

?>
