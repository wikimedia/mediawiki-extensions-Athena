<?php
        $list = file_get_contents("https://en.wikipedia.org/w/api.php?action=query&list=random&rnlimit=500&format=json");
    $json = json_decode($list, true);
    $json = $json["query"]["random"];
    
    $count = 0;
    $output = array();
    for( $i = 0; $i < 18; $i++) {
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
    }
    file_put_contents( 'page_data/wikipedia.json', json_encode( $output ) );
