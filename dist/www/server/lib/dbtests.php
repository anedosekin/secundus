<?php
	define ("LINK","http://localhost:8080/lib_link/dbwork.php");
	$stopExecOnError=false;
	
	define('JS_RESULTSET','RESULTSET');
	define ("MSG_EXEC_OK","SUCCESS");
	define ("MSG_ROW_COL","ROWS");	
	
	class ErrException extends Exception
	{
		public $header;
		public function __construct($message="",$head="", $code = 0, Exception $previous = null) 
		{
			$this->header=$head;
	        parent::__construct($message, $code, $previous);
		}
    } 
	
	ini_set('display_errors', 'On');	

	$conn = curl_init();
	$idc=0;

	//$data = array('name' => 'Foo', 'file' => '@/home/user/test.png');
	$comlist=array();
	echo '<html>
	<script type="text/javascript">
		function see_hide(iddiv) 
		{
			var contents = document.getElementById(iddiv);
			if (contents.style.display == "none") contents.style.display = "block";
			else contents.style.display = "none";
		}
	</script>
	<table>';
	function echoRow($head,$infotext="",$stat='RED')
	{
		global $idc;
		$idc++;		
		$colst="<FONT COLOR=$stat>";
		echo <<<EEE
		<tr onClick="see_hide('cont$idc');">
				<td>$colst $head </FONT></td>
		</tr>
		<tr><td><div id='cont$idc' style='display: none'>$infotext</div></td></tr>
EEE
;
	}
	function endScript()
	{
		global $conn;
		curl_close($conn);
		echo "</table></html>";
		die;
	}	
	curl_setopt($conn, CURLOPT_URL, LINK);
	curl_setopt($conn, CURLOPT_POST, 1);	
	curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
	
	include 'testcases.php';// add $comlist[], array of command string arrays
	
	foreach($comlist as $num=>$cc)
	{
		// dont panic, some magic =)))		
		try 
		{		
			$jscom=json_decode($cc,true);	
			if (!$jscom) throw new ErrException($cc,"#".$num. " Task not parsed. ");
			$data=array('sqlboby'=>$cc);
			if (isset($jscom['blobs'])) $data=array_merge($data,$jscom['blobs']);

			curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
			$rezt=curl_exec($conn);
			//echo "<br>";var_dump($rezt);
			$jsrez=json_decode($rezt,true);	
			if (!$jsrez) throw new ErrException("Comm:<br>".json_encode($cc)."<br>Resp:<br>".$rezt,"#".$num. " response not parsed. ");
			
			$com=$jscom['commands'];
			$tn=$jscom['taskname'];			
			// idem po rezultatam comandi 
			$nrez=0;// template result counter			
			echoRow("#### Task $num: $tn","Task full resp:<br>".json_encode($jsrez),'BLUE');
			$subrez=$jsrez['result']['commands'];
			foreach ($subrez as $snum=>$crez)
			{
				try {
				$nnum=$num;
				if (count($subrez)>1) $nnum="$num.$snum";
				if ($crez[MSG_EXEC_OK]!==true) throw new ErrException("<br>Resp:<br>".json_encode($crez),"#$nnum  ".$crez['TYPE']." Not pass. Execute error. ");
				$ok=true;
				if (isset($jscom['result'])) 
				{									
					if (isset($crez[JS_RESULTSET]))
					{
						// ololo compare objects %)
						$obj1=json_encode($jscom['result'][$nrez]);
						$obj2=json_encode($crez[JS_RESULTSET]);
						$nrez++;
						//echo "<br> obj1:";var_dump($obj1);echo "<br>obj2:";var_dump($obj2);
						if ($obj1!=$obj2) throw new ErrException ("Resp:<br>".json_encode($crez),"#$nnum  ".$crez['TYPE']." Not pass. Wrong result. Must be: $obj1");
					}
				}
				}
				catch(ErrException $err)
				{
					//echo "<pre>";var_dump($jscom);
					echoRow($err->header,$err->getMessage());
					if ($stopExecOnError) endScript();
					$ok=false;					
				}				
				if ($ok) echoRow("	#$nnum  ".$crez['TYPE']." Passed. ","Resp:<br>".json_encode($crez),'GREEN');
			}
		}
		catch(ErrException $err)
		{
		//echo "<pre>";var_dump($jscom);
			echoRow($err->header,$err->getMessage());
			if ($stopExecOnError) endScript();
		}
	}	
	
	endScript();
?>