<?php
	$count = 0;

	$urls = [ 'http://s-e-x.com/what-s-the-role-of-an-audiologist activair hearing aid batteries', 'https://casino-online.com lap dog', 'https://lesbiansex.org/ https://facebook.loginreminder.org/', 'http://www.page.to/ visit', 'http://cheap-airfares.co.uk/item.php?id=348139&mode=1 powerball result',
			'http://www.putinbay.com/seo-services-usa/ visit the following site', 'https://putlocker.ml/ aol.loginreminder.org', 'https://teenporn.com/ my aol mail login', 'https://spycam.org/watch?v=JYBj2iD35mY ketosis weight loss', 'http://reebok-jerseys.com/api/query?datasource=uploads&search_type=all&sort=rank&search=wrinkles%20aren%27t&lic=by,sa,s,splus,pd,zero wrinkles aren',
			'http://fans-jerseys.us/tag/twarzy kolagen na oczy', 'http://xxxporn.com/videos-porno-de-cumlouder-gratis homepage', 'http://camgirl.org.uk/tagculo/cumlouder-com webpage', 'http://adultporn.com/ Leesa Shapiro', 'http://adultweb.com/search?q=misspell&s_it=header_form_v1 misspell',
			'http://www.zoofilia.com/watch?v=eHQgcDNbymc http://www.pacific-pictures.com/watch?v=eHQgcDNbymc','http://www.kpop.fr/ mebel jepara antik', 'http://buyanessay.org injustice gods among us cheats', 'http://www.freesexshows.com/search?q=cellulite&btnI=lucky cellulite', 'http://xxxbloggers.com yuan penting' ];

	$output = [];
	for ( $count = 0; $count < 50; $count++ ) {
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

		if ( $count % 50 < 37 ) {
			if ( $count % 50 > 32 ) {
				$account = date_sub( $pagetimestamp, new DateInterval( "P1Y" ) );
				$userTimestamp = date_format( $account, 'YmdHis' );
				echo( "user year\n" );
			} else {
				if ( $count % 50 < 15 ) {
					$age = 30;
					echo( "user minute\n" );
				} elseif ( $count % 50 < 20 ) { // 5
					$age = 180;
					echo( "user 3 mins\n" );
				} elseif ( $count % 50 < 23 ) { // 30
					$age = 60 * 15;
					echo( "user 15 mins\n" );
				} elseif ( $count % 50 < 26 ) {
					$age = 60 * 45;
					echo( "user 45 mins\n" );
				} elseif ( $count % 50 < 31 ) {
					$age = 60 * 60 * 5;
					echo( "user 5 hours\n" );
				} else {
					$age = 60 * 60 * 15;
					echo( "user 15 hours\n" );
				}
				$account = date_sub( $pagetimestamp, new DateInterval( "PT" . $age . "S" ) );
				$userTimestamp = date_format( $account, 'YmdHis' );
			}

		} else {
			echo( "user ANON\n" );
			$userTimestamp = 0;
		}

		$content = str_replace( "</p>\r<p>", "<br/>", $content );
		$content = str_replace( "<p>", "", $content );
		$content = str_replace( "</p>", "", $content );
		$content = str_replace( "/r", "", $content );
		$n = rand( 1, 3 );
		for ( $i = 0; $i < $n; $i++ ) {

			$content = $content . " [" . $urls[rand( 0, 19 )] . "] <br/>";
		}

		// echo($content);

		$output[] = [ 'title' => $title, 'content' => $content, 'timestamp' => $curTime, 'user-timestamp' => $userTimestamp ];

		sleep( 2 );
	}
	file_put_contents( 'page_data/spam-5.json', json_encode( $output ) );
