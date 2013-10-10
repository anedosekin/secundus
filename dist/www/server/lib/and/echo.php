<?php
header('Content-type: text/plain');
stream_copy_to_stream(fopen('php://input','r'), fopen('php://output','w'));
?>