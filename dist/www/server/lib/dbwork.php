<?php
	error_log("Oracle database not available!");
	ini_set('display_errors', 'On');
	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	// dbtype: mysql, postgres, mssql, oci, sqlite
	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	// for mssql\odbc, not comment if not use odbc
	$odbc_use=false;
	$odbcdrv="SQL Server";
	$odbcsrv="SHUMSKY-XC-O3\SQLEXPRESS";
	//-----------------
	$dbhost="127.0.0.1";
	//----mssql-odbc----
	/*
	$dbtype="mssql";
	$dbport="1433";
	$dbuser="puser";
	$dbpass="1";
	$dbname="tst";	
	*/
	//----mysql----
	
	$dbtype="mysql";
	$dbport="3306";
	$dbuser="puser";
	$dbpass="1";
	$dbname="test";
	
	//---postgres---	
	/*
	$dbtype="pgsql";
	$dbport="5432";
	$dbuser="user";
	$dbpass="1";
	$dbname="postgres";	
	*/
	//---orecle-----
	/*
	$dbtype="oci";
	$dbport="1521";
	$dbuser="puser";
	$dbpass="1";
	$dbname="XE";	
	*/
	//---sqlite3----
	/*
	$dbtype="sqlite";
	$dbuser=null;
	$dbpass=null;
	$dbhost="D:/soft/BD/sql_lite/test.db";
	*/
	//----------------------
	define ("LOG_ERR_COMM","err_com");
	define ("LOG_ERR_SYS","err_sys");
	define ("LOG_COM_OK","com_ok");
	define ("LOG_PRINT","just_echo");
	//----------------------
	define ("MSG_TXT","msgtxt");
	define ("MSG_ERR_CODE","sqlstate");
	define ("MSG_EXEC_OK","success");
	define ("MSG_ROW_COL","rows");
	define ("MSG_SID","setsid");
	//----------------------
	$jresult=json_decode('{"result":{"commands":[]}}',true);
	$requestOk=true;
	$curcom=null;
	$selectstack=array();
	$errorstack="";
	header('Content-Type: application/json; charset=utf-8');
	//if ($jresult===false or $jresult==null) echo "\nerrr\n";
	//----------------------
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
		$tstutf=array("ttt"=>$txt);
		if (!testUTF($txt)) $txt=iconv('windows-1251', 'UTF-8', $txt);
		global $jresult;
		if ($txt!==null )$txt=str_replace(array("\n","\r","\t","\\","\'","\"","\n\r")," ",$txt);
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
		if ($type==LOG_ERR_SYS) 
		{
			//$txt=iconv('windows-1251', 'UTF-8', $txt);
			//$findex=fopen("tttt.txt","w");
			//fwrite($findex,$txt);
			$jresult['errors']['system'][]=array(MSG_TXT=>$txt,MSG_ERR_CODE=>$errcode);
		}
		if ($type==LOG_PRINT)
		{
			$jresult['echo'][]=$txt;
		}

	}
	//-------- save and exit ---
	function endScript()
	{
		global $jresult;
		global $requestOk;
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
	//- check exists params -
	function checkExists($val,$params)
	{
		$result=true;		
		foreach ($params as $tmp)
		{
			if (!isset($val[$tmp])) $result=false;
		}		
		return $result;
	}
	//------ test names ------
	function testName($name)
	{
		$rez=true;
		$sym=" :;\\\|\/";
		if (strpbrk($name,$sym)!==false) $rez=false;
		return $rez;
	}
	//------ db prepear ------
	function prepearDB($db)
	{
	
		global $dbtype;
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
			$db->exec ("SET SESSION sql_mode='STRICT_ALL_TABLES,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE,NO_KEY_OPTIONS,NO_TABLE_OPTIONS,
		NO_FIELD_OPTIONS,NO_AUTO_CREATE_USER,ONLY_FULL_GROUP_BY,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_BACKSLASH_ESCAPES'");

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
			$db->exec ("ALTER SESSION SET NLS_LANG='ENGLISH_UNITED KINGDOM.UTF8'"); // RUSSIAN_CIS // AMERICAN_AMERICA 
			$db->exec ("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD'");					
			$db->exec ("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS'");			
			$db->exec ("ALTER SESSION SET TIME_ZONE='UTC'");
			$db->exec ("ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.,'");
		}
	}	
	// -----------------------
	function getSID($db,$sid)
	{
		$result=null;
		global $dbtype;
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
				//print_r($db->errorInfo());
			}
			else logMsg($db->errorInfo()[2],LOG_ERR_SYS,null,$db->errorInfo()[0]);
		}
		catch (PDOException $e) 
		{	
			logMsg($e->getMessage(),LOG_ERR_SYS,null,$e->getCode());//
		}
		return $result;
	}
	// ------ create select ---
	function doSelect($body,&$stack,$inc=0)
	{
		$sqlrez="";
//		if ($body!="join"){
		foreach($body as $dat=>$dt)
		{	
			if ($dat=="expr")
			{	
				$fst=true;
				foreach ($dt as $tmp=>$tmpdat)
				{	
					foreach($tmpdat as $T_T=>$o_O)
					{
						if ($T_T=="field") 
						{	
							if (!$fst) $sqlrez.=",";
							if ($fst) $fst=false;
							if (testName($o_O))$sqlrez.=$o_O;
						}
						if ($T_T=="sel_body") 
						{
							$zzz=array("insql"=>array(),"SQL"=>array(),"args"=>array(),"result"=>array());
							if (isset($o_O['inctname'])) $zzz['inctname']=$o_O['inctname'];
							$zzz['SQL']="SELECT ".doSelect($o_O,$zzz);
							$stack['insql'][]=$zzz;
						}
					}
				}
			}	
			if ($dat=="from")
			{	
				$sqlrez=$sqlrez." FROM ";
				$fst=true;
				foreach ($dt as $tmp=>$tmpdat)
				{				
					foreach($tmpdat as $T_T=>$o_O)
					{
						if ($T_T=="sel_body") 
						{	
							if (!$fst) $sqlrez.=",";
							$sqlrez=$sqlrez." (SELECT ".doSelect($o_O,$stack).") ";
						}
						if ($T_T=="table") 
						{								
							if (!$fst) $sqlrez.=",";
							if ($fst) $fst=false;
							if (testName($o_O)) $sqlrez.=$o_O;
						}
						if ($T_T=="join") 
						{
							$sqlrez=$sqlrez."".doSelect($tmpdat,$stack)." ";
						}
					}
				}
			}
			if ($dat=="join" || $dat=="table_join") 
			{
				$jt=null;
				$tbl1="";
				$tbl2=null;
				$oa1=null;
				$oa2=null;
				$orb="";
				$incjoin="";
				foreach ($dt as $tmp=>$T_T)
				{
					foreach($T_T as $tmpdat=>$d) 
					{
						if ($tmpdat=="join_type") if (testName($d)) $jt=$d; 
						if ($tmpdat=="table") 
						{
							if (!is_array($d))	if (testName($d)) $tbl1=$d;
							else if (isset($d['sel_body'])) $tbl1=" (SELECT ".doSelect($d,$stack).")";
						}
						if ($tmpdat=="table_join") 
						{
							if (!is_array($d)) if (testName($d)) $tbl2=$d;
							else 
							{
								if (isset($d['sel_body'])) $tbl2=" (SELECT ".doSelect($d['sel_body'],$stack).")";
								//$sqlrez=$sqlrez."".doSelect($T_T,$stack,2)." ";
							}
						}
						if ($tmpdat=="on_arg1") if (testName($d)) $oa1=$d;
						if ($tmpdat=="on_arg2") if (testName($d)) $oa2=$d;
						if ($tmpdat=="order_by") if (testName($d)) $orb=" ORDER BY ".$d;
						if ($tmpdat=="join") $incjoin=doSelect($T_T,$stack,2);
						//if ($tmpdat=="sel_body") $sqlrez=$sqlrez." (SELECT ".doSelect($o_O,$stack).") ";
					}
				}
				/*
				if ($jt==null || ($tbl1==null && $tbl2==null) ||$oa1==null || $oa2==null) 
				{
					global $curcom;
					logMsg("Error join params!",LOG_ERR_COMM,$curcom);
				}
				*/
					$joincond=" ON ".$oa1."=".$oa2;
					if ($incjoin!="") $tbl2="";
					if (strtoupper($jt)=="CROSS") $joincond=" "; 
					if ($inc==2) $sqlrez.="(".$tbl1." ".$jt." JOIN ".$incjoin.$tbl2.$joincond.$orb.")";
					else $sqlrez.=$tbl1." ".$jt." JOIN ".$incjoin.$tbl2.$joincond.$orb;
			}
			if ($dat=="where")
			{	
				$sqlrez.=" WHERE ";
				$col="";
				$op="";
				$val="";
				$valsel="";
				foreach ($dt as $tmp=>$tmpdat)
				{	
					$add="";
					foreach($tmpdat as $T_T=>$o_O)
					{
						if ($T_T=="column") if (testName($o_O)) $col=$o_O;
						if ($T_T=="operation") $op=$o_O;
						if ($T_T=="value") 
						{	
							if (!is_array($o_O))
							{								
								if (isset($tmpdat['valkey'])) 
								{
									if ($tmpdat['valkey']==true) $stack['args'][]=array("val"=>$o_O,"key"=>true);
									$val="?";
								}
								else 
								{
									if (isset($tmpdat['valcolumn'])) 
									{
										if (($tmpdat['valcolumn'])==true) if (testName($o_O)) $val=$o_O;										
									}
									else
									{
										$stack['args'][]=array("val"=>$o_O);
										$val="?";//$o_O;
									}
								}
							}
							else 
							{
								if (isset($o_O['sel_body'])) $val=" (SELECT ".doSelect($o_O['sel_body'],$stack).")";
								else 
								{
									$fst=true;
									$val.=" (";
									foreach ($o_O as $x_X)
									{
										if (!$fst) $val.=",";
										if ($fst) $fst=false;
										$val.="?";//$x_X;
										if (isset($tmpdat['valkey'])) if ($tmpdat['valkey']==true) $stack['args'][]=array("val"=>$x_X,"key"=>true);
										else $stack['args'][]=array("val"=>$x_X);
										
									}
									$val.=") ";																				
								}
							}
						}
						if ($T_T=="add") $add=" ".$o_O." "; 
					}
					if ($col!="" && $op!="" && $val!="") $sqlrez.=" ".$add.$col." ".$op." ".$val;
				}
			}
		}		
		return $sqlrez;
	}
	//-------- exec select -------
	function execSelect(&$db,&$cursl,&$upperdata)
	{
		global $errorstack;
		$rezerr=0;
		$countins=0;
		$lastname=null;
		$selOK=false;
		$selin=false;
		$selinEnded=true;
		$upperargs=array();						
		if ($upperdata) $selin=true;
		$curindexin=0;
		if (isset($cursl['SQL'])) 
		{
			$countins=0;
			$sm=$db->prepare($cursl['SQL']);															
			for($narg=0;;)
			{
				$counter=1;
				foreach ($cursl['args'] as $aval)
				{
					$taval=null;
					if (!isset($aval['key'])) $taval=$aval['val'];
					else
					{
						if ($selin&&count($upperargs)==0) 
						{
							foreach($upperdata['result'] as $ty) $upperargs[]=$ty[$aval['val']];
							$selinEnded=false;
						}
						if (isset($upperargs[$narg])) $taval=$upperargs[$narg];
					}
					if (!($sm->bindValue($counter,$taval))) 
					{
						//logMsg("Bind err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$curcom,$sm->errorInfo()[0]);
						$errorstack.="\nBind err. SQL:".$cursl['SQL'].$sm->errorInfo()[2]."\nSQLSTATE:".$sm->errorInfo()[0];
						$rezerr++;
					}
					$counter++;
				}
				if (($sm->execute())===false) 
				{
					//logMsg("Exec err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$curcom,$sm->errorInfo()[0]);
					$errorstack.="\nExec err. SQL:".$cursl['SQL'].$sm->errorInfo()[2]."\nSQLSTATE:".$sm->errorInfo()[0];
					$rezerr++;
				}
				else 
				{
					if ($upperdata!=null) 
					{
						$cursl['result'][] = $sm->fetchAll(PDO::FETCH_ASSOC);
					}
					else $cursl['result'] = $sm->fetchAll(PDO::FETCH_ASSOC);
					
					if (!$selin) 
					{
						$selOK=true;
						$selinEnded=true;										
					}
					
				}
				if ($selinEnded)	break;
				if ($narg==count($upperargs)-1) 
				{	
					$selinEnded=true;
					break;
				}
				$narg++;
			}
		}		
		if (isset($cursl['insql']))
		{	
			$countins++;
			for($i=0;$i<count($cursl['insql']);$i++)
			{
				$rezerr+=execSelect($db,$cursl['insql'][$i],$cursl);//,$nxt);
				$cc=0;				
				for ($tt=0;$tt<count($cursl['result']);$tt++)//($cursl['result'] as $rzt)
				{					
					$nnn=$cursl['insql'][$i]['inctname'];
					if (isset ($cursl['insql'][$i]['result'][$cc])) $cursl['result'][$tt][$nnn]=$cursl['insql'][$i]['result'][$cc];
					$cc++;
				}
			}
		}
		return $rezerr;
	}
		
	if (isset($_SERVER['HTTP_CONTENT_TYPE']))
	{
		$tyy=$_SERVER['HTTP_CONTENT_TYPE'];
		if ($tyy!="application/json") 
		{
			logMsg("Error content type!",LOG_ERR_SYS);
			endScript();
		}
	}
	if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])) {logMsg("Error data read!",LOG_ERR_SYS);endScript();}
	$jdata=json_decode($GLOBALS['HTTP_RAW_POST_DATA'],true);
	if ($jdata==NULL){logMsg("JSON parse error",LOG_ERR_SYS);endScript();}
	$tmpvar=null;
	
	try 
	{	
		$db=null;
		//PDO::ATTR_PERSISTENT => true - кэширование сессии DB
		//PDO::ATTR_ORACLE_NULLS=>PDO::NULL_TO_STRING - null -> ""
		if ($dbtype=="mssql" || $odbc_use==true) 
		{
			$dsn="odbc:Driver={".$odbcdrv."};Server=".$odbcsrv.";Database=".$dbname.";Uid=".$dbuser.";Pwd=".$dbpass;
			//$dsn="odbc:Driver={SQL Server};Server=SHUMSKY-XC-O3\SQLEXPRESS;Database=tst;Uid=puser;Pwd=1";
			$db=new PDO($dsn);
			$db->setAttribute (PDO::ATTR_ORACLE_NULLS,PDO::NULL_TO_STRING);
		}
		else 
		{	
			if ($dbtype!="sqlite") $dsn=$dbtype.':host='.$dbhost.';port='.$dbport.';dbname='.$dbname;
			else $dsn=$dbtype.':'.$dbhost;
			$db = new PDO($dsn,$dbuser,$dbpass,array(PDO::ATTR_PERSISTENT => true,PDO::ATTR_ORACLE_NULLS=>PDO::NULL_TO_STRING));			
		}			
		if ( $db) 
		{	
			prepearDB($db);			
			//testSIDS($jdata['commands'],$db);
			$sid="";
			foreach($jdata['commands'] as $dat)
			{
				if ($dat['type']==MSG_SID)
				{
					$sid=getSID($db,$dat['data']);
					if ($sid)
					{
						$dat['data']=$sid;
						logMsg("",LOG_COM_OK,$dat);
						break;
					}
					else 
					{
						$dat['data']="";
						logMsg("Get sid inc err.",LOG_ERR_COMM,$dat);
						$sid="";
					}
				}
			}			
			foreach($jdata['commands'] as $dat)
			{
				$resultsql="";
				$curcom=$dat;			
				if (!checkExists($dat,array("type"))) 
				{
					logMsg("Error! Not defined type.",LOG_ERR_COMM,$dat);
					continue;
				}		
				//---------------------- INSERT ---------
				if ($dat['type']=="insert")
				{
					$haserr=false;
					$sets="";
					$vsets="";
					$fst=true;
					if (!checkExists($dat,array("data","table"))) 
					{
						logMsg("Error! Not defined data or table for update.",LOG_ERR_COMM,$dat);
						continue;
					}
					if (testName($dat['table'])==false) 
					{
						$haserr=true;
						logMsg("Error table name: ".$dat['table'],LOG_ERR_COMM,$dat);
					}					
					foreach ($dat['data'] as $ttt=>$tv)
					{
						//if (!testName($ttt))
						if ($fst==false) 
						{
							$sets.=",";
							$vsets.=",";
						}
						if (testName($ttt)==false) 
						{
							$haserr=true;
							logMsg("Error field name: ".$ttt,LOG_ERR_COMM,$dat);
						}
						$sets.=$ttt;
						$vsets.="?";
						$fst=false;
					}
					if (!$haserr)
					{
						$resultsql.="INSERT INTO ".$dat['table']." (".$sets.")  VALUES (".$vsets.")";
						$sm=$db->prepare($resultsql);
						$counter=1;
						foreach ($dat['data'] as $ttt=>$tttval)
						{
							$varval=$tttval;
							if (is_array($tttval)) 
							{
								$varval=$tttval[0].$sid;
							}
							if (!$varval) $varval="";
							if (!($sm->bindValue($counter,$varval))) logMsg("Bind err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
							$counter++;
						}
						if (!($sm->execute()))	
						{
							logMsg("Exec err.".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
							//print_r($sm->queryString);
						}
						else logMsg("",LOG_COM_OK,$dat,0,$sm->rowCount());
					}
				}					
				
				//---------------------- DELETE ---------
				if ($dat['type']=="delete")
				{
					$sets="";
					$vsets="";
					$haserr=false;
					$fst=true;
					if (!checkExists($dat,array("where","table"))) 
					{
						logMsg("Error! Not defined delete key or table.",LOG_ERR_COMM,$dat);
						continue;
					}
					if (testName($dat['table'])==false) 
					{
						$haserr=true;
						logMsg("Error field name: ".$dat['table'],LOG_ERR_COMM,$dat);
					}
					
					foreach ($dat['where'] as $ttt=>$tttval)
					{
						if ($fst==false) 
						{
							$sets.=" AND ";
						}
						if (testName($ttt)==false) 
						{
							$haserr=true;
							logMsg("Error field name: ".$ttt,LOG_ERR_COMM,$dat);
						}
						$sets.=" ".$ttt."=?";
						$fst=false;
					}
					if (!$haserr)
					{
						$resultsql.="DELETE FROM ".$dat['table']." WHERE ".$sets;	
						$sm=$db->prepare($resultsql);
						$counter=1;
						foreach ($dat['where'] as $ttt=>$tttval)
						{
							$varval=$tttval;
							if (is_array($tttval)) 
							{
								$varval=$tttval[0].$sid;
							}
							if (!($sm->bindValue($counter,$varval))) logMsg("Bind err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
							$counter++;
						}
						if (($sm->execute())===false)	logMsg("Exec err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
						else logMsg("",LOG_COM_OK,$dat,0,$sm->rowCount ());
					}
				}
				
				//---------------------- UPDATE ---------
				if ($dat['type']=="update")
				{
					$sets="";
					$wsets="";
					$haserr=false;
					if (!checkExists($dat,array("where","data","table"))) 
					{
						logMsg("Error! Not defined key or data for update.",LOG_ERR_COMM,$dat);
						continue;
					}
					$fst=true;
					if (testName($dat['table'])==false) 
					{
						$haserr=true;
						logMsg("Error field name: ".$dat['table'],LOG_ERR_COMM,$dat);
					}
					foreach ($dat['data'] as $ttt=>$tttval)
					{
						if ($fst==false) $sets.=",";
						$sets.=" ".$ttt."=?";
						$fst=false;
						if (testName($ttt)==false) 
						{
							$haserr=true;
							logMsg("Error field name: ".$ttt,LOG_ERR_COMM,$dat);
						}
						
					}
					$fst=true;
					foreach ($dat['where'] as $ttt=>$tttval)
					{
						if ($fst==false) 
						{
							$wsets.=" AND ";
						}
						$wsets.=" ".$ttt."=?";
						if (testName($ttt)==false) 
						{
							$haserr=true;
							logMsg("Error field name: ".$ttt,LOG_ERR_COMM,$dat);
						}
						$fst=false;
					}
					if (!$haserr)
					{
						$resultsql.="UPDATE ".$dat['table']." SET".$sets." WHERE ".$wsets;								
						$sm=$db->prepare($resultsql);
						$counter=1;
						foreach ($dat['data'] as $ttt=>$tttval)
						{
							$varval=$tttval;
							if (is_array($tttval)) 
							{
								$varval=$tttval[0].$sid;
							}
							if (!($sm->bindValue($counter,$varval))) logMsg("Bind err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
							$counter++;
						}
					
						foreach ($dat['where'] as $ttt=>$tttval)
						{
							$varval=$tttval;
							if (is_array($tttval)) 
							{
								$varval=$tttval[0].$sid;
							}
							if (!($sm->bindValue($counter,$varval))) logMsg("Bind err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
							$counter++;
						}
						if (($sm->execute())===false) logMsg("Exec err. ".$sm->errorInfo()[2],LOG_ERR_COMM,$dat,$sm->errorInfo()[0]);
						else logMsg("",LOG_COM_OK,$dat,0,$sm->rowCount());
					}
				}
				//--------------------- SELECT ------------
				if ($dat['type']=="select")
				{
					if (isset($dat['sel_body']))
					{
						$selectstack=json_decode('{"SQL":"","args":[],"insql":[],"result":{}}',true);
						$selsql="SELECT ".doSelect($dat['sel_body'],$selectstack);
						//logMsg($selsql,LOG_PRINT);
						$selectstack['SQL']= $selsql;
						$prevcom=null;
						$errorstack="";
						$result=execSelect($db,$selectstack,$prevcom);
						$dat['ret_data']=$selectstack['result'];
						if ($result==0) logMsg("",LOG_COM_OK,$dat,0,count($selectstack['result']));
						else logMsg($errorstack,LOG_ERR_COMM,$dat,$db->errorInfo()[0]);
					}					
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