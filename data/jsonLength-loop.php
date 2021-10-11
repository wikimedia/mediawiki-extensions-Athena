<?php
// TODO provide file instead of hardcode
	for ( $i = 1; $i < 9; $i++ ) {
		$string = file_get_contents( "page_data/spam-$i.json" );
		$json_a = json_decode( $string, true );
		echo( count( $json_a ) . "\n" );
	}
