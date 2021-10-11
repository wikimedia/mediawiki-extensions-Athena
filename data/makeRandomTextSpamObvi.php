<?php
	$count = 0;
	$output = [];

	$names = [ 'Shari', 'Bree', 'Bette', 'Catalina', 'Timmy', 'Oscar', 'Steve Fischer', 'Gloria', 'Francesca', 'Glen Taverner', 'Brady Wharton', 'Tamie Turriff', 'Richard', 'Ellie', 'Frank Sinatra', 'Muhammad', 'Polly Pearson', 'Uscar', 'Mckenzie', 'Angus' ];
	$date = [ '10 December 1984', '20 November 1999', '12 January 1987', '1 March 1977', '6 April 1976',
			'15 October 2000', '4 April 1965', '28 Feburary 1968', 'May 30 1986', 'June 7, 1937',
			'33 July 2000', '8 August 1999', '19 September 1924', 'June 17, 1995', '5 April 1957' ];
	$hobbies = [ 'porn', 'viagra', 'zoofilla', 'jerseys', 'cheap nfl jerseys','paris-hilton', 'huojia', 'casino-online', 'spycam', 'livegirl', 'camgirl', 'cheapairfares', 'online-casino', 'adultporn','fullcellmarket', 'teensex', 'hardcoreporn', 'teenporn', 's-e-x' ];
	$urls = [ 'http://s-e-x.com/what-s-the-role-of-an-audiologist activair hearing aid batteries', 'https://casino-online.com lap dog', 'https://lesbiansex.org/ https://facebook.loginreminder.org/', 'http://www.page.to/ visit', 'http://cheap-airfares.co.uk/item.php?id=348139&mode=1 powerball result',
			'http://www.putinbay.com/seo-services-usa/ visit the following site', 'https://putlocker.ml/ aol.loginreminder.org', 'https://teenporn.com/ my aol mail login', 'https://spycam.org/watch?v=JYBj2iD35mY ketosis weight loss', 'http://reebok-jerseys.com/api/query?datasource=uploads&search_type=all&sort=rank&search=wrinkles%20aren%27t&lic=by,sa,s,splus,pd,zero wrinkles aren',
			'http://fans-jerseys.us/tag/twarzy kolagen na oczy', 'http://xxxporn.com/videos-porno-de-cumlouder-gratis homepage', 'http://camgirl.org.uk/tagculo/cumlouder-com webpage', 'http://adultporn.com/ Leesa Shapiro', 'http://adultweb.com/search?q=misspell&s_it=header_form_v1 misspell',
			'http://www.zoofilia.com/watch?v=eHQgcDNbymc http://www.pacific-pictures.com/watch?v=eHQgcDNbymc','http://www.kpop.fr/ mebel jepara antik', 'http://buyanessay.org injustice gods among us cheats', 'http://www.freesexshows.com/search?q=cellulite&btnI=lucky cellulite', 'http://xxxbloggers.com yuan penting' ];

	for ( $count = 0; $count < 50; $count++ ) {
		$title = substr( substr( str_shuffle( "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 1 ) . substr( md5( time() ), 1 ), rand( 10, 30 ) );
		echo( "Count is $count\n" );

		$title = urlencode( "User:" . $title );
		date_default_timezone_set( 'UTC' );

		$name = $names[$count % 20];
		$date = $dates[$count % 15];
		$hobby = $hobbies[$count % 20];
		$url = $urls[$count % 20];
		if ( $count % 5 == 0 ) {
			$content = "Im $name and was born on $date My hobbies are $hobby.<br><br>Also visit my homepage [$url]";
		} elseif ( $count % 5 == 1 ) {
			$age = rand( 18, 89 );
			$url2 = $urls[rand( 0, 19 )];
			$url3 = $urls[rand( 0, 19 )];
			$hobby2 = $hobbies[rand( 0, 19 )];
			$hobby3 = $hobbies[rand( 0, 19 )];
			$content = "I'm a $age years old and [$url] at the university ($hobby).<br>In my spare time I teach myself $hobby2. I love to read, preferably on my ebook reader. I like to watch $hobby3 as well as docus about anything [$url2]. I love $hobby.<br><br>My homepage: [$url3]";
		} elseif ( $count % 5 == 2 ) {
			$url2 = $urls[rand( 0, 19 )];
			$hobby2 = $hobbies[rand( 0, 19 )];
			$hobby3 = $hobbies[rand( 0, 19 )];
			$content = "$name is exactly what his partner loves to call him but individuals constantly [$url] it.  I utilized to be out of work now I am a dentist.  One of his preferred hobbies is $hobby but he can not make it his career.  Years ago we moved to $hobby2 and I have everything that I require right here.  I'm bad at $hobby3 however you may desire to check my internet site: [$url2]";
		} elseif ( $count % 5 == 3 ) {
			$hobby2 = $hobbies[rand( 0, 19 )];
			 $content = "Hello from Australia. I'm glad to be here. My first name is $name. <br>I live in a city called $hobby.<br>I was also born in Palmyra 20 years ago. Married in $date. I'm working at the $hobby2.<br><br>Feel free to surf to my web blog: [$url]";
		} else {
			$age = rand( 18, 89 );
			$hobby2 = $hobbies[rand( 0, 19 )];
			$hobby3 = $hobbies[rand( 0, 19 )];
			$content = "$age year-old $hobby $name from Inuvik, enjoys to spend time $hobby3. Has enrolled in a world contiki trip. Is extremely thrilled particularly about going to $hobby2.<br><br>Feel free to surf to my webpage [$url]";
		}

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
		$account = date_sub( $pagetimestamp, new DateInterval( "PT" . $age . "S" ) );
		$userTimestamp = date_format( $account, 'YmdHis' );

		$output[] = [ 'title' => $title, 'content' => $content, 'timestamp' => $curTime, 'user-timestamp' => $userTimestamp ];
	}
	file_put_contents( 'page_data/spam-8.json', json_encode( $output ) );
