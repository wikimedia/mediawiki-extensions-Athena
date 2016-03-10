<?php

    /**
     * Gets the language of a given text
     *
     * @param $text string
     * @return string
     */
     $text= "test string ok cool thanks";
        if( strlen( $text ) == 0 ) {
            $code = null;
        } else {
            file_put_contents( "temp", $text );
            $code = exec( "franc < temp" );
        }
        echo ($code);
