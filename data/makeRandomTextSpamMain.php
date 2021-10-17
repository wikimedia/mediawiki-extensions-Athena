<?php
	$count = 0;

	// phpcs:disable Generic.Files.LineLength
	$urls = [ 'http://ashtonwojcik.bligoo.com/what-s-the-role-of-an-audiologist activair hearing aid batteries', 'https://penzu.com/p/67c8d890 lap dog', 'https://facebook.loginreminder.org/ https://facebook.loginreminder.org/', 'http://www.dailydot.com/ visit', 'http://triglav.altervista.org/item.php?id=348139&mode=1 powerball result',
			'http://www.ukjayseo.com/seo-services-usa/ visit the following site', 'https://aol.loginreminder.org/ aol.loginreminder.org', 'https://aol.loginreminder.org/ my aol mail login', 'https://www.youtube.com/watch?v=JYBj2iD35mY ketosis weight loss', 'http://Ccmixter.org/api/query?datasource=uploads&search_type=all&sort=rank&search=wrinkles%20aren%27t&lic=by,sa,s,splus,pd,zero wrinkles aren',
			'http://clammyhangover703.soup.io/tag/twarzy kolagen na oczy', 'http://culossalidos.com/tagculo/videos-porno-de-cumlouder-gratis homepage', 'http://Culossalidos.com/tagculo/cumlouder-com webpage', 'http://leesa-shapiro.webnode.com/ Leesa Shapiro', 'http://search.huffingtonpost.com/search?q=misspell&s_it=header_form_v1 misspell',
			'http://www.youtube.com/watch?v=eHQgcDNbymc http://www.youtube.com/watch?v=eHQgcDNbymc','http://www.ashifajati.com/ mebel jepara antik', 'http://injusticegodsamongushack14.wordpress.com injustice gods among us cheats', 'http://www.google.com/search?q=cellulite&btnI=lucky cellulite', 'http://indonesianeconomicnews.blogspot.com yuan penting' ];
	// phpcs:enable

	$output = [];
	for ( $count = 0; $count < 125; $count++ ) {
		$title = substr( substr( str_shuffle( "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 1 ) . substr( md5( time() ), 1 ), 0, rand( 10, 50 ) );
		echo( "Count is $count\n" );
		// $title = urlencode($title);

		//$title = urlencode("User:" . $title);
		date_default_timezone_set( 'UTC' );

		$content = file_get_contents( "http://www.randomtext.me/api/gibberish/p7/20-100" );
		$content_json = json_decode( $content, true );
		$content = $content_json["text_out"];

		$curTime = date( 'YmdHis' );
		$pagetimestamp = date_create( $curTime );

		if ( $count % 25 < 19 ) {
			if ( $count % 25 > 16 ) {
				$account = date_sub( $pagetimestamp, new DateInterval( "P1Y" ) );
				$userTimestamp = date_format( $account, 'YmdHis' );
				echo( "user 1 year\n" );
			} else {
				if ( $count % 25 < 8 ) {
					echo( "user 30 secs\n" );
   $age = 30;
				} elseif ( $count % 25 < 10 ) { // 5
					echo( "user 3 mins\n" );
  $age = 180;
				} elseif ( $count % 25 < 12 ) { // 30
					echo( "user 15 mins\n" );
   $age = 60 * 15;
				} elseif ( $count % 25 < 13 ) {
					echo( "user 45 mins\n" );
   $age = 60 * 45;
				} elseif ( $count % 25 < 16 ) {
					echo( "user 5 hours\n" );
   $age = 60 * 60 * 5;
				} else {
					echo( "user 15 hours\n" );

					$age = 60 * 60 * 15;
				}
				$account = date_sub( $pagetimestamp, new DateInterval( "PT" . $age . "S" ) );
				$userTimestamp = date_format( $account, 'YmdHis' );
			}

		} else {
			echo( "user anon\n" );
			$userTimestamp = 0;
		}

	   $content = str_replace( "</p>\r<p>", "<br/>", $content );
		$content = str_replace( "<p>", "", $content );
		$content = str_replace( "</p>", "", $content );
		$content = str_replace( "/r", "", $content );
		for ( $i = 0; $i < rand( 1, 3 ); $i++ ) {
			$content = $content . " [" . $urls[rand( 1, 19 )] . "] <br/>";
		}

		// echo($content);

		$output[] = [ 'title' => $title, 'content' => $content, 'timestamp' => $curTime, 'user-timestamp' => $userTimestamp ];

		sleep( 2 );
	}
	file_put_contents( 'page_data/spam-6.json', json_encode( $output ) );
