


    <?php
        // TODO provide file instead of hardcode
        $file = file_get_contents( 'page_data/wikipedia-1.json' );
        
        $json = json_decode( $file, true );
        
        $count = 0;
        foreach ( $json as $page ) {
            
            if(preg_match_all( "/^#REDIRECT(\s)?\[\[([^\[\]])+\]\]$/", $json['content'] ) ) {
                $count++;
            }
        }
        echo($count);
    
