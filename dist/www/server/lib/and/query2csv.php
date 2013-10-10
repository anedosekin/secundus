<?php
require_once(__DIR__.'/processor.php');

function query_to_csv($stmt, $args = [], $captions = []) {
  header('Content-type: text/csv');
  $outstream = fopen("php://output",'w');
  
  $first = true;
  foreach(process_query($cmd, $args) as $r) {
    if($first) { $first = false;
        foreach($r as $k=>$v)
          $fn[] = @$captions[$k]?:$k;
        fputcsv($outstream, $fn, ';');
    }
    $v = [];
    foreach($r as $e) $v[] = $e;
    fputcsv($outstream, $v, ';');
  }
}

if(__FILE__ != TOPLEVEL_FILE) return;

query_to_csv( main_argument(),  main_subarguments());

?>