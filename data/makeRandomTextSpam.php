<?php
    $count = 0;
    $output = array();
    for( $count = 0; $count < 100; $count++) {
        $title = substr(substr( str_shuffle( "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 1 ) . substr( md5( time() ), 1 ), rand(5, 20));
        echo("Count is $count\n");
        $title = urlencode($title);
        
        $title = urlencode("User:" . $title);
        date_default_timezone_set('UTC');
        
       $content = file_get_contents("http://www.randomtext.me/api/gibberish/p7/20-100");
        $content_json = json_decode($content, true);
        $content = $content_json["text_out"];
        
        $curTime = date('YmdHis');
        $pagetimestamp = date_create($curTime);
        
        if( $count % 100 < 73 ){
            if( $count % 100 > 63 ) {
                $account = date_sub($pagetimestamp, new DateInterval("P1Y"));
                $userTimestamp = date_format($account, 'YmdHis');
            } else {
                if( $count % 100 < 31 ) {
                    $age = 60;
                } else if ( $count % 100 < 41 ) { // 5
                    $age = 180;
                }  else if ( $count % 100 < 47 ) { // 30
                    $age = 60 * 15;
                } else if ( $count % 100 < 51 ) {
                    $age = 60 * 45;
                } else if ( $count % 100 < 62 ) {
                    $age = 60 * 60 * 5;
                } else if ( $count % 100 < 63 ) {
                    $age = 60 * 60 * 15;
                }
                $account = date_sub($pagetimestamp, new DateInterval("PT" . $age . "S"));
                $userTimestamp = date_format($account, 'YmdHis');
            }
            
        } else {
            $userTimestamp = 0;
        }
        
       $content = str_replace("</p>\r<p>", "<br/>", $content);
        $content = str_replace("<p>", "", $content);
        $content = str_replace("</p>", "", $content);
        $content = str_replace("/r", "", $content);

        $output[] = array( 'title' => $title, 'content' => $content, 'timestamp'=>$curTime, 'user-timestamp'=>$userTimestamp );
        
        sleep(2);
    }
    file_put_contents( 'page_data/spam2.json', json_encode( $output ) );