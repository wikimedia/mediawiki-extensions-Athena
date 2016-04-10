<?php
// TODO provide file instead of hardcode
    for( $i = 1; $i < 10; $i++ ) {
        $string = file_get_contents( "page_data/wikipedia-$i.json" );
        $json_a = json_decode( $string, true );
        echo( count( $json_a ) . "\n");
    }
