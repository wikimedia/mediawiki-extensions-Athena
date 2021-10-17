<?php
 /*
	$file = file_get_contents("page_data/wikipedia.json");
	$output = json_decode($file);

	$list = file_get_contents("https://en.wikipedia.org/w/api.php?action=query&list=random&rnlimit=10&format=json");
	$json = json_decode($list, true);
	$json = $json["query"]["random"];

	$count = 0;
	foreach( $json as $page ) {
		echo("Page #$count is " . $page["title"] . "\n");
		$title = urlencode($page["title"]);
		$content = file_get_contents("https://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&format=json&titles=" . $title);
		$content_json = json_decode($content, true);
		$content_json = $content_json["query"]["pages"];
		foreach($content_json as $pageid) {
			$content = $pageid["revisions"][0]["*"];

			$curTime = date(YmdHis);
			$pagetimestamp = date_create($curTime);

			if( $count % 100 < 95 ) {
				$account = date_sub($pagetimestamp, new DateInterval("P1Y"));
				$userTimestamp = date_format($account, YmdHis);
			} else if ( $count % 100 < 97 ) {
				$userTimestamp = 0;
			} else {
				$age = rand(1, 86400);
				$account = date_sub($pagetimestamp, new DateInterval("PT" . $age . "S"));
				$userTimestamp = date_format($account, YmdHis);
			}

			$output[] = array( 'title' => $page["title"], 'content' => $content, 'timestamp'=>$curTime, 'user-timestamp'=>$userTimestamp );

			break;
		}

		$count++;
	}
	file_put_contents( 'page_data/wikipedia.json', json_encode( $output ) );
  */
// Below is original - above is one to merge the file with 8990 pages with 10 new ones
		$list = file_get_contents( "https://en.wikipedia.org/w/api.php?action=query&list=random&rnlimit=500&format=json" );
	$json = json_decode( $list, true );
	$json = $json["query"]["random"];

	$count = 0;
	$output = [];
	for ( $i = 0; $i < 1; $i++ ) {
		foreach ( $json as $page ) {
			echo( "Page #$count is " . $page["title"] . "\n" );
			$title = urlencode( $page["title"] );
			$content = file_get_contents( "https://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&format=json&titles=" . $title );
			$content_json = json_decode( $content, true );
			$content_json = $content_json["query"]["pages"];
			foreach ( $content_json as $pageid ) {
				$content = $pageid["revisions"][0]["*"];

				$curTime = date( YmdHis );
				$pagetimestamp = date_create( $curTime );

				if ( $count % 100 < 95 ) {
					$account = date_sub( $pagetimestamp, new DateInterval( "P1Y" ) );
					$userTimestamp = date_format( $account, YmdHis );
				} elseif ( $count % 100 < 97 ) {
					$userTimestamp = 0;
				} else {
					$age = rand( 1, 86400 );
					$account = date_sub( $pagetimestamp, new DateInterval( "PT" . $age . "S" ) );
					$userTimestamp = date_format( $account, YmdHis );
				}

				$output[] = [ 'title' => $page["title"], 'content' => $content, 'timestamp' => $curTime, 'user-timestamp' => $userTimestamp ];

				break;
			}

			$count++;

		}
	}
	file_put_contents( 'page_data/wikipedia-5.json', json_encode( $output ) );
