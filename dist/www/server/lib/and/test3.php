<?php

echo 'in include:', __FILE__, ' ', 
  $_SERVER['DOCUMENT_ROOT'],'--',$_SERVER['PHP_SELF'],
  get_included_files()[0], '//', $_SERVER["SCRIPT_FILENAME"];

var_dump('included file 3!!!', debug_backtrace());

?>