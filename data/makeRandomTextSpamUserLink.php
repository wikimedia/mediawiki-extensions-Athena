<?php
	$count = 0;
	$output = [];

	// phpcs:disable Generic.Files.LineLength
	$names = [ 'Shari', 'Bree', 'Bette', 'Catalina', 'Timmy', 'Oscar', 'Steve Fischer', 'Gloria', 'Francesca', 'Glen Taverner', 'Brady Wharton', 'Tamie Turriff', 'Richard', 'Ellie', 'Frank Sinatra', 'Muhammad', 'Polly Pearson', 'Uscar', 'Mckenzie', 'Angus' ];
	$date = [ '10 December 1984', '20 November 1999', '12 January 1987', '1 March 1977', '6 April 1976',
			'15 October 2000', '4 April 1965', '28 Feburary 1968', 'May 30 1986', 'June 7, 1937',
			'33 July 2000', '8 August 1999', '19 September 1924', 'June 17, 1995', '5 April 1957' ];
	$hobbies = [ 'Computing', 'Javelin', 'Long jump', 'Veronica Mars', 'Anthropology and Sociology and Environmental Management','Rock collecting and Figure skating', 'enjoys to spend time theatre, intercambios de parejas and fossils', 'really likes classic cars, intercambios de parejas and educational courses', 'I\'m working at the backery', 'include beach tanning','travel and reading fantasy', 'Hindi', 'aerobics', 'Driving and Jukskei', 'lieing','crying', 'flying', 'buying sports cars', 'who cares baby?', 'Lego' ];
	$urls = [ 'http://ashtonwojcik.bligoo.com/what-s-the-role-of-an-audiologist activair hearing aid batteries', 'https://penzu.com/p/67c8d890 lap dog', 'https://facebook.loginreminder.org/ https://facebook.loginreminder.org/', 'http://www.dailydot.com/ visit', 'http://triglav.altervista.org/item.php?id=348139&mode=1 powerball result',
			'http://www.ukjayseo.com/seo-services-usa/ visit the following site', 'https://aol.loginreminder.org/ aol.loginreminder.org', 'https://aol.loginreminder.org/ my aol mail login', 'https://www.youtube.com/watch?v=JYBj2iD35mY ketosis weight loss', 'http://Ccmixter.org/api/query?datasource=uploads&search_type=all&sort=rank&search=wrinkles%20aren%27t&lic=by,sa,s,splus,pd,zero wrinkles aren',
			'http://clammyhangover703.soup.io/tag/twarzy kolagen na oczy', 'http://culossalidos.com/tagculo/videos-porno-de-cumlouder-gratis homepage', 'http://Culossalidos.com/tagculo/cumlouder-com webpage', 'http://leesa-shapiro.webnode.com/ Leesa Shapiro', 'http://search.huffingtonpost.com/search?q=misspell&s_it=header_form_v1 misspell',
			'http://www.youtube.com/watch?v=eHQgcDNbymc http://www.youtube.com/watch?v=eHQgcDNbymc','http://www.ashifajati.com/ mebel jepara antik', 'http://injusticegodsamongushack14.wordpress.com injustice gods among us cheats', 'http://www.google.com/search?q=cellulite&btnI=lucky cellulite', 'http://indonesianeconomicnews.blogspot.com yuan penting' ];
	// phpcs:enable

	for ( $count = 0; $count < 125; $count++ ) {
		$title = substr( substr( str_shuffle( "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 1 ) . substr( md5( time() ), 1 ), rand( 10, 20 ) );
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

		if ( $count % 25 < 18 ) {
			$age = 30;
			echo( "User 30 seconds\n" );
		} elseif ( $count % 25 < 20 ) { // 5
			$age = 180;
			echo( "User 3 minutes\n" );
		} elseif ( $count % 25 < 22 ) { // 30
			$age = 60 * 15;
			echo( "User 15 minutes\n" );
		} elseif ( $count % 25 < 23 ) {
			echo( "User 45 minutes\n" );
			$age = 60 * 45;
		} elseif ( $count % 25 < 24 ) {
			echo( "User 5 hours\n" );
			$age = 60 * 60 * 5;
		} else {
			echo( "User 30 hours\n" );
			$age = 60 * 60 * 30;
		}
		$account = date_sub( $pagetimestamp, new DateInterval( "PT" . $age . "S" ) );
		$userTimestamp = date_format( $account, 'YmdHis' );

		$output[] = [ 'title' => $title, 'content' => $content, 'timestamp' => $curTime, 'user-timestamp' => $userTimestamp ];
	}
	file_put_contents( 'page_data/spam-2.json', json_encode( $output ) );
