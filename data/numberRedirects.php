<?php
		// TODO provide file instead of hardcode
		$file = file_get_contents( 'page_data/spam-6.json' );

		$json = json_decode( $file, true );

		$count = 0;
		foreach ( $json as $page ) {

			echo( $page['title'] . "\n" );
		}
		echo( $count );
