<?php
if($argc<2) die("Usage: php -f createdb.php platform");

require_once(__DIR__.'/cfg.php');
require_once(__DIR__.'/rights.php');

$platform = 1000+intval($argv[1]);


$dbh = get_connection('');

function startsWith($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

switch( $dbh->dialect ) {
  case 'pgsql':  echo 'init pg';
$dbh->exec( <<<DOOOO
    do $$ begin 
      if not exists( select 0 from pg_class where relname = 'mainseq') then
          create sequence mainseq increment by 1000 start with $platform; 
      end if; 
    end $$
DOOOO
);
  break;
}

?>
