<?php
	// TODO provide file instead of hardcode
	$wikiname = 'appunti2';
	$normal = file_get_contents( 'page_data/' . $wikiname . 'Normal.json' );
	$deleted = file_get_contents( 'page_data/' . $wikiname . 'Deleted.json' );

	$output = array_merge( json_decode( $normal, true ), json_decode( $deleted, true ) );

	usort( $output, static function ( $a, $b ) {
		return $a['timestamp'] < $b['timestamp'] ? -1 : 1;
	} );

	file_put_contents( 'page_data/' . $wikiname . '.json', json_encode( $output ) );
