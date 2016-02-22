<?php
// TODO provide file instead of hardcode
$string = file_get_contents( "page_data/mindworks.json" );
$json_a = json_decode( $string, true );
echo( count( $json_a ) );
