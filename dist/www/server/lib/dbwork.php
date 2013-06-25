<?php
ini_set('display_errors', 'On');
//----------------------
define ("LOG_ERR_COMM","ERR_COM");
define ("LOG_ERR_SYS","ERR_SYS");
define ("LOG_COM_OK","COM_OK");
define ("LOG_PRINT","JUST_ECHO");
//----------------------
define ("MSG_TXT","MSGTXT");
define ("MSG_ERR_CODE","SQLSTATE");
define ("MSG_EXEC_OK","SUCCESS");
define ("MSG_ROW_COL","ROWS");
//----------------------
define ("MSG_SID","SETSID");
//----------------------
$jresult=json_decode('{"result":{"commands":[]}}',true);
$requestOk=true;
$curcom=null;
$db=null;
/*
$tyyp='{"x":["str",{"name":"data"}]}';
$FFFFFF=json_decode($tyyp);
if ($FFFFFF )print_r($FFFFFF);
else print_r("ERRRR!!!");
*/
//if ($jresult===false or $jresult==null) echo "\nerrr\n";
//===================================================
//              FUNCTIONS
//===================================================
require_once 'right.php';
//===================================================
function testUTF($tst)
{
	$tstutf='{"txt":"'.$tst.'"}';
	$jd=json_decode($tstutf,true);
	if ($jd['txt']==null) return false;
	return true;
}
//------- logMsg -------
function logMsg($txt,$type="",$data=null,$errcode=-1,$count=0)
{
	if ($txt!==null )$txt=str_replace(array("\n","\r","\t","\\","\'","\"","\n\r")," ",$txt);
	$tstutf=array("ttt"=>$txt);
	if (!testUTF($txt)) $txt=iconv('windows-1251', 'UTF-8', $txt);
	global $jresult;
	if ($type==LOG_ERR_COMM||$type==LOG_COM_OK)
	{
		$comok=true;
		if ($type==LOG_ERR_COMM) $comok=false;
		if ($data!=null)
		{
			if ($txt==null) $txt="";
			$arrmrg=null;
			if (!$comok) $arrmrg=array(MSG_EXEC_OK=>$comok,MSG_TXT=>$txt,MSG_ERR_CODE=>$errcode);
			else $arrmrg=array(MSG_EXEC_OK=>$comok,MSG_ROW_COL=>$count);
			if ($data)
			{
				$ddata=array_merge($data,$arrmrg);
				$jresult['result']['commands'][]=$ddata;
			}
		}
	}
	if ($type==LOG_ERR_SYS) $jresult['errors']['system'][]=array(MSG_TXT=>$txt,MSG_ERR_CODE=>$errcode);
	if ($type==LOG_PRINT) $jresult['echo'][]=$txt;
}
//-------- save and exit ---
function endScript($isbin=false)
{
	global $jresult;
	global $requestOk;
	if (!$isbin) header('Content-Type: application/json; charset=utf-8');
	if ($requestOk)
	{
		header("HTTP/1.1 200 Ok");
	}
	else
	{
		header("HTTP/1.1 400 Bad Request");
	}
	echo json_encode($jresult);
	die;
}
//------ db prepear ------
function prepearDB(&$db)
{
	$dbtype=$db->dialect;
	// for postgre
	if ($dbtype=="pgsql")
	{
		$db->exec ("SET client_encoding to 'UTF8'");
		$db->exec ("SET DateStyle = ISO,YMD");
		$db->exec ("SET timezone = UTC");
		$db->exec ("SET client_min_messages = 'warning'");
	}
	// for mysql
	if ($dbtype=="mysql")
	{
		$db->exec ("SET NAMES 'utf8'");
		$db->exec ("SET SESSION time_zone = '+00:00'");
		$db->exec ("SET SESSION sql_mode='STRICT_ALL_TABLES,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE,NO_KEY_OPTIONS,NO_TABLE_OPTIONS,NO_FIELD_OPTIONS,NO_AUTO_CREATE_USER,ONLY_FULL_GROUP_BY,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_BACKSLASH_ESCAPES'");

	}
	// for ms sql
	if ($dbtype=="sqlsrv"||$dbtype=="odbc")
	{
		$db->exec ("SET CHARSET utf-8");
		$db->exec ("SET LANGUAGE us_english");
		$db->exec ("SET DATEFORMAT YMD");
	}
	// oracle
	if ($dbtype=="oci")
	{
		$db->exec ("ALTER SESSION SET NLS_CALENDAR='Gregorian'");
		// NLS_LANG задается передается через переменные среды 
		//$db->exec ("ALTER SESSION SET NLS_LANG='ENGLISH_UNITED KINGDOM.UTF8'"); // RUSSIAN_CIS // AMERICAN_AMERICA
		$db->exec ("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD'");
		$db->exec ("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS'");
		$db->exec ("ALTER SESSION SET TIME_ZONE='UTC'");
		$db->exec ("ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.,'");
	}
}
// --------- get SID from DB ----------
function getSID(&$db)
{
	$result=null;
	$dbtype=$db->dialect;
	$sqlq="";
	if ($dbtype=="pgsql" ||$dbtype=="mysql") $sqlq="select getSidNum()";
	if ($dbtype=="oci") $sqlq="select getIncForSID.nextval from dual";
	if ($dbtype=="sqlsrv"||$dbtype=="mssql") $sqlq="execute getSidNum;";
	try
	{
		$ttt=null;
		if ($dbtype=="sqlite")
		{
			$sqllq1="update sid_num_generator set getSidNum=getSidNum+1 where unid=1;";
			if ($db->exec($sqllq1)==1) $ttt=$db->query("select getSidNum from sid_num_generator;");
		}
		else $ttt=$db->query($sqlq);
		if ($ttt)
		{
			$result=$ttt->fetchAll();
			if ($result) $result=$result[0][0];
		}
		else logMsg($db->errorInfo()[2],LOG_ERR_SYS,null,$db->errorInfo()[0]);
	}
	catch (PDOException $e)
	{
		logMsg($e->getMessage(),LOG_ERR_SYS,null,$e->getCode());//
	}
	return $result;
}
// ------- get file path from $_FILE
function getFilePath($name)
{
	foreach ($_FILES as $fl)
	{
		if (!isset($fl['name'])) return "";
		if (is_array($fl['name']))
		{
			$i=0;
			$num=-1;
			foreach ($fl['name'] as $tmpn)
			{
				if (strcmp($tmpn,$name)==0) {
					$num=$i;break;
				}
				$i++;
			}
			if (isset($fl['error'][$num])) if ($fl['error'][$num]!=0) return "";
			if (isset($fl['tmp_name'][$num])) return $fl['tmp_name'][$num];
		}
		else
		{
			if (!isset($fl['name'])) return "";
			if (strcmp($fl['name'],$name)==0)
			{
				if (!isset($fl['error'])) return "";
				if ($fl['error']==0) return $fl['tmp_name'];
			}
		}
	}
	return "";
}
//====================================================
//                  END OF FUNCTIONS
//====================================================
$jdata=null;
if (isset($_SERVER['HTTP_CONTENT_TYPE']))
{
	$tyy=$_SERVER['HTTP_CONTENT_TYPE'];
	if (substr_count($tyy,"application/json",0)==0)
	{
		if (substr_count($tyy,"multipart/form-data",0)==0)
		{
			logMsg("Error content type!",LOG_ERR_SYS);
			endScript();
		}
		else
		{
			if (!isset($_POST['sqlboby'])) {
				logMsg("SQL body not found.",LOG_ERR_SYS);endScript();
			}
			$jdata=json_decode($_POST['sqlboby'],true);
		}			
	}
	else
	{
		if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
			logMsg("Error data read!",LOG_ERR_SYS);endScript();
		}
		$jdata=json_decode($GLOBALS['HTTP_RAW_POST_DATA'],true);
	}
}
if ($jdata==NULL){
	logMsg("JSON parse error",LOG_ERR_SYS);endScript();
}
$tmpvar=null;

try
{
	$db=get_connection(null);
	if ( $db)
	{
		prepearDB($db);
		//testSIDS($jdata['commands'],$db);
		$sid="";
		foreach($jdata['commands'] as $dat)
		{
			if ($dat[JS_CMDTYPE]==MSG_SID)
			{
				$sid=getSID($db);
				if (isset($dat[JS_FIELDS])) $sid=$dat[JS_FIELDS].$sid;
				if ($sid)
				{
					$dat[JSQ_DATA]=$sid;
					logMsg("",LOG_COM_OK,$dat);
					break;
				}
				else
				{
					$dat[JSQ_DATA]="";
					logMsg("Get sid inc err.",LOG_ERR_COMM,$dat);
					$sid="";
				}
			}
		}
		foreach($jdata['commands'] as $dat)
		{
			$resultsql="";
			$curcom=$dat;
			if (!isset($dat[JS_CMDTYPE]))
			{
				logMsg("Error! Not defined type.",LOG_ERR_COMM,$dat);
				continue;
			}
			try
			{
				$stmt=make_command($dat,'sam',$db);				
				$num=1;
				foreach ($dat[JS_LINK] as $ldat)
				{
					$stmt->bindValue($num,$ldat);
					$num++;
				}			
				if (!($stmt->execute()))
				{
					logMsg("Exec error.".$stmt->errorInfo()[2],LOG_ERR_COMM,$dat,$stmt->errorInfo()[0]);
					print_r($stmt->queryString);
				}
				else 
				{					
					if ($dat[JS_CMDTYPE]==JS_SELECT) $dat[JS_RESULTSET]=$stmt->fetchAll(PDO::FETCH_NUM);
					logMsg("",LOG_COM_OK,$dat,0,$stmt->rowCount());
					print_r($stmt->queryString);					
				}				
			}
			catch(Exception $ex)
			{
				logMsg("Error. ".$ex->getMessage(),LOG_ERR_COMM,$dat,$ex->getCode());
			}							
		}
	}
	else logMsg("DB error!",LOG_ERR_SYS);
	$db = null;
}
catch (PDOException $e)
{
	logMsg($e->getMessage(),LOG_ERR_SYS,null,$e->getCode());//

}
endScript();
?>