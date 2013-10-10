<?php
require_once(__DIR__.'/processor.php');

function query_to_html($stmt, $args = [], $fields = [], $captions = []) {
  header('Content-type: text/html');
  
  $first = true;
  foreach(process_query($cmd, $args) as $r) {
    if($first) { $first = false;
      echo '<table><caption></caption>';
      echo "\n<thead><tr>";
        foreach($r as $k=>$v)
          echo "\n<th col='$k' expr=\"".htmlspecialchars(@$fields[$k])."\">"
            .(@$captions[$k]?:$k)."</th>";
      echo '</tr></thead>';
      echo "\n<tbody>";
    }
    echo '<tr>';
    foreach($r as $k=>$v) {
      echo "\n<td col='$k'>";
	if(has_subitems($v))
		query_to_html($v); //call for inner array
	else
		echo htmlspecialchars($v);
      echo "</td>";
    }
    echo '</tr>';
  }
  if(!$first)
    echo "\n</tbody></table>";
  else
    echo '<div class=empty_result></div>';
}

if(__FILE__ != TOPLEVEL_FILE) return;

query_to_html( main_argument(),  main_subarguments() );

?>
