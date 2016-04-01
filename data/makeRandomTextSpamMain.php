<?php
    $count = 0;
    
    $urls = array('http://ashtonwojcik.bligoo.com/what-s-the-role-of-an-audiologist activair hearing aid batteries', 'https://penzu.com/p/67c8d890 lap dog', 'https://facebook.loginreminder.org/ https://facebook.loginreminder.org/', 'http://www.dailydot.com/ visit', 'http://triglav.altervista.org/item.php?id=348139&mode=1 powerball result',
                  'http://www.ukjayseo.com/seo-services-usa/ visit the following site', 'https://aol.loginreminder.org/ aol.loginreminder.org', 'https://aol.loginreminder.org/ my aol mail login', 'https://www.youtube.com/watch?v=JYBj2iD35mY ketosis weight loss', 'http://Ccmixter.org/api/query?datasource=uploads&search_type=all&sort=rank&search=wrinkles%20aren%27t&lic=by,sa,s,splus,pd,zero wrinkles aren',
                  'http://clammyhangover703.soup.io/tag/twarzy kolagen na oczy', 'http://culossalidos.com/tagculo/videos-porno-de-cumlouder-gratis homepage', 'http://Culossalidos.com/tagculo/cumlouder-com webpage', 'http://leesa-shapiro.webnode.com/ Leesa Shapiro', 'http://search.huffingtonpost.com/search?q=misspell&s_it=header_form_v1 misspell',
                  'http://www.youtube.com/watch?v=eHQgcDNbymc http://www.youtube.com/watch?v=eHQgcDNbymc','http://www.ashifajati.com/ mebel jepara antik', 'http://injusticegodsamongushack14.wordpress.com injustice gods among us cheats', 'http://www.google.com/search?q=cellulite&btnI=lucky cellulite', 'http://indonesianeconomicnews.blogspot.com yuan penting');

    $output = array();
    for( $count = 0; $count < 250; $count++) {
        $title = substr(substr( str_shuffle( "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 1 ) . substr( md5( time() ), 1 ), rand(10, 50));
        echo("Count is $count\n");
        $title = urlencode($title);
        
        //$title = urlencode("User:" . $title);
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
        for( $i = 0; $i < rand(0,3); $i) {
            $content = $content . " [" . $urls[rand(0,19)] . "] <br/>";
        }
        
        //echo($content);
        
        $output[] = array( 'title' => $title, 'content' => $content, 'timestamp'=>$curTime, 'user-timestamp'=>$userTimestamp );
        
        sleep(2);
    }
    file_put_contents( 'page_data/spam4.json', json_encode( $output ) );