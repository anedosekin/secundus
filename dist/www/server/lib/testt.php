<?php
ini_set('display_errors', 'On');
$cfg = array(
		'cache' => array( 'timeout' => 1, 'local' => true ),
		'default_db' => array(
				'dialect' => 'pgsql',
				'server'=>'pgsql:host=katia;port=5433;dbname=yoda',
				'user' => 'serious',
				'pass' => '1',
		)
);

//header("HTTP/1.1 200 Ok");
//header('Content-Type: image/jpg');
$params=$cfg['default_db'];
$result=null;
$addparams=array(PDO::ATTR_PERSISTENT => true);
$conn= new PDO("{$params['server']}",$params['user'],$params['pass'],$addparams);
$conn->dialect = $params['dialect'];
$conn->setAttribute (PDO::ATTR_ORACLE_NULLS,PDO::NULL_TO_STRING);
$conn->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);// exceptions for all errors

$sqlstr="SELECT blobdata FROM rmn_exp ex  WHERE intdata=?";
$stmt=$conn->prepare($sqlstr);
//blobdata AS blb, 
$bval=5;
$stmt->bindValue(1,$bval);
if ($stmt->execute())
{
	//$result=$stmt->fetchAll(PDO::FETCH_NUM);
	$blbrez=null;
	//for ($i=1;$i<=$stmt->columnCount();$i++) $stmt->bindColumn($i,$blbrez, PDO::PARAM_LOB);
	//while ($stmt->fetch(PDO::FETCH_BOUND)) fpassthru($blbrez);
	$rez=$stmt->fetchAll(PDO::FETCH_NUM);
	//print_r($rez[0][0]);
	fpassthru($rez[0][0]);
}


?>
