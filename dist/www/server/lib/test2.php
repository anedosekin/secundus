<?php
ini_set('display_errors', 'On');
$cfg = array(
		  'cache' => array( 'timeout' => 1, 'local' => true ),
		  'default_db' => array(
					'dialect' => 'oci',
					'server'=>'oci:host=127.0.0.1;port=1521;dbname=XE',
					'user' => 'puser',
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
//$conn->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8); 	

//mb PDO::SQLSRV_ENCODING_BINARY ???
//$conn->exec ("SET bytea_output=escape");

// ============ insert in oci =====================
/*
$sqlstr="insert into rmn_exp (intdata, txtdata, blobdata) VALUES ('100','blob text', EMPTY_BLOB()) RETURNING blobdata INTO ?";
$stmt=$conn->prepare($sqlstr);
$fp = fopen("c:/tmp/111.txt", 'rb');
$stmt->bindValue(1,$fp,PDO::PARAM_LOB);
$conn->beginTransaction();
if ($stmt->execute()) echo "Ok. Insert complite";
$conn->commit();
*/


$sqlstr="SELECT blobdata FROM rmn_exp WHERE intdata='100'";
$stmt=$conn->prepare($sqlstr);
//$fp = fopen("c:/tmp/111.txt", 'rb');
//$stmt->bindValue(1,$fp,PDO::PARAM_LOB);
//echo "Output:\n";
if ($stmt->execute())
{
	$blbrez=null;
	for ($i=1;$i<=$stmt->columnCount();$i++) $stmt->bindColumn($i,$blbrez, PDO::PARAM_LOB);
	while ($stmt->fetch(PDO::FETCH_BOUND)) fpassthru($blbrez);
	//$rez=$stmt->fetchAll(PDO::FETCH_NUM);
	
	//$stmt->bindColumn(1,$aaa, PDO::PARAM_LOB);
	//$rez=$stmt->fetch(PDO::FETCH_NUM);
	//$rez=$stmt->fetch(PDO::FETCH_NUM);
	//$rezt=$rez[0][0];
	//$rezt=pack("H*",$rezt);
	//print_r($rezt);
	//fpassthru($rezt);
}
/*
$tns = "  
 (DESCRIPTION =
     (ADDRESS_LIST =
       (ADDRESS = (PROTOCOL = TCP)(HOST = 127.0.0.1)(PORT = 1521))
     )
     (CONNECT_DATA =
       (SERVICE_NAME = XE)
     )
   )
        ";
$connection = oci_connect ("PUSER", "1", $tns);

$phpCur = oci_new_cursor($connection);
$stmt = oci_parse($connection, $sqlstr);
oci_execute($stmt);
while( $row = oci_fetch_array($stmt) )
    //var_dump($row);
	echo "<br>\n",$row[0]->load();

oci_free_statement($stmt);

oci_close($connection);
*/
?>
