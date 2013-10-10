<?php
require_once(__DIR__.'/processor.php');

function query_to_xlsxml($stmt, $args = [], $captions = []) {

  $xml = simplexml_load_string( <<<XMLBASE
<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <!--LastAuthor>user</LastAuthor-->
  <!--Created>2013-08-29T15:20:16Z</Created-->
  <!--Version>11.9999</Version-->
 </DocumentProperties>
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <!--WindowHeight>12630</WindowHeight-->
  <!--WindowWidth>18000</WindowWidth-->
  <!--WindowTopX>360</WindowTopX-->
  <!--WindowTopY>105</WindowTopY-->
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <!--Font ss:FontName="Arial Cyr" x:CharSet="204"/-->
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="s21">
   <NumberFormat ss:Format="Short Date"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="export">
  <Table>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <!--PageSetup>
    <PageMargins x:Bottom="0.984251969" x:Left="0.78740157499999996"
     x:Right="0.78740157499999996" x:Top="0.984251969"/>
   </PageSetup-->
   <Selected/>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
XMLBASE
);
/*
   <Row>
    <Cell><Data ss:Type="String">2011-12-21T00:00:00.000</Data></Cell>
    <Cell><Data ss:Type="String">http://www.yandex.ru</Data></Cell>
    <Cell><Data ss:Type="String">192.168.10.22</Data></Cell>
    <Cell ss:Index="5"><Data ss:Type="String">DDoS</Data></Cell>
    <Cell><Data ss:Type="String">Супер Браузера</Data></Cell>
   </Row>
*/

$tbl = $xml->Worksheet->Table;
function put_xlsxml_row($tbl, $row) {
  $skipped = false;
  $r = $tbl->addChild('Row');
  foreach($row as $idx=>$cell) {
    $c = $r->addChild('Cell');
    if($cell!=='') {
      $c->addChild('Data', $cell)['ss:Type'] = 
        preg_match('/^[0-9]+$/', $cell) ? 'Number': 'String';
      if($skipped)
        $c['ss:Index'] = $idx+1;
      $skipped = false;
    } else
        $skipped = true;
  }
}

  $cnt = 0;
  $rsize = [];
  $first = true;
  foreach(process_query($cmd, $args) as $r) {
    if($first) { $first = false;
      $fn = [];
      foreach($r as $k=>$v)
        $fn[] = @$captions[$k]?:$k;
      put_xlsxml_row($tbl, $fn);
      $rsize[] = count($fn);
      ++$cnt;
    }
    $v = [];
    foreach($r as $e) $v[] = $e;
    put_xlsxml_row($tbl, $v);
    $rsize[] = count($v);
    ++$cnt;
  }
  $tbl['ss:ExpandedColumnCount'] = max($rsize);
  $tbl['ss:ExpandedRowCount'] = $cnt;
  
  header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  echo $xml->saveXML();
}

if(__FILE__ != TOPLEVEL_FILE) return;

query_to_xlsxml( main_argument(),  main_subarguments());

?>