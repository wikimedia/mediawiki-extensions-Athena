<?php

/**
 * Filters used by Athena
 */
class AthenaFilters {

    /**
     * Checks type of user and their age
     * Returns -1 if anon, -2 if info not available, or otherwise returns the age of the account in minutes
     *
     * @return int user age / type
     */
    public static function userAge() {
        global $wgUser;

        // check anon
        $registration = $wgUser->getRegistration();

        if ( $registration === false ) {
            // if false, user is anon
            return -1;
        } elseif ( $registration === null ) {
        } elseif ( $registration === null ) {
            // if null, user is registered but info not available
            return -2;
        } else {
            // we have a timestamp
            // get current time
            $now = wfTimestamp();
            // convert registration from MediaWiki timestamp to Unix timestamp
            $registration = wfTimestamp( TS_UNIX, $registration );

            // Get difference (in seconds)
            $diff = $now - $registration;

            wfErrorLog( "+++++++++++++++++++++++++++++++++++++++", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
            wfErrorLog( "Now is $now", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
            wfErrorLog( "Registration is $registration", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
            wfErrorLog( "Difference is $diff", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

            // Convert to minutes, rounding down
            //$diff = floor( $diff / 60 );

            wfErrorLog( "Difference post ronding is $diff", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
            wfErrorLog( "+++++++++++++++++++++++++++++++++++++++", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

            return $diff;
        }
    }

    /**
     * Gets the number of external links in an article
     *
     * @param $text string page content
     * @return int number of links
     */
    public static function numberOfLinks( $text ) {
        // Three ways to make a link
        // A: [http(s)://..... ]
        $count = preg_match_all( "/\[http(s?):\/\/([^\[\]])+\]/", $text );
        // B: http(s)://...
        $count += preg_match_all( "/[^\[]http(s?):\/\/[^\s^\]^\[]+[^\[]\s/", $text );
        // C: [//.... ]
        $count += preg_match_all( "/\[\/\/([^\[\]])+\]/", $text );

        // Plus alternate protocols
        // D: [mailto:.... ]
        $count += preg_match_all( "/\[mailto:([^\[\]])+\]/", $text );
        $count += preg_match_all( "/[^\[]mailto:[^\s^\]^\[]+[^\[]\s/", $text );
        // E: [gopher:.... ]
        $count += preg_match_all( "/\[gopher:([^\[\]])+\]/", $text );
        $count += preg_match_all( "/[^\[]gopher:\/\/[^\s^\]^\[]+[^\[]\s/", $text );
        // F: [news:.... ]
        $count += preg_match_all( "/\[news:([^\[\]])+\]/", $text );
        $count += preg_match_all( "/[^\[]news:\/\/[^\s^\]^\[]+[^\[]\s/", $text );
        // G: [ftp:.... ]
        $count += preg_match_all( "/\[ftp:([^\[\]])+\]/", $text );
        $count += preg_match_all( "/[^\[]ftp:\/\/[^\s^\]^\[]+[^\[]\s/", $text );
        // H: [irc:.... ]
        $count += preg_match_all( "/\[irc:([^\[\]])+\]/", $text );
        $count += preg_match_all( "/[^\[]irc:\/\/[^\s^\]^\[]+[^\[]\s/", $text );

        return $count;
    }

    /**
     * Gets the percentage of the page that is links
     *
     * @param $text string content of the page
     * @return double percentage of page that are links
     */
    public static function linkPercentage( $text ) {
        // Get character count
        $charCount = strlen( $text );

        // Three ways to make a link
        // A: [http(s)://..... ]
        $textNoLinks = preg_replace( "/\[http(s?):\/\/([^\[\]])+\]/", "", $text );
        // B: http(s)://...
        $textNoLinks = preg_replace( "/[^\[]http(s?):\/\/[^\s^\]^\[]+[^\[]\s/", "", $textNoLinks );
        // C: [//.... ]
        $textNoLinks = preg_replace( "/\[\/\/([^\[\]])+\]/", "", $textNoLinks );

        // Plus alternate protocols
        // D: [mailto:.... ]
        $textNoLinks = preg_replace( "/\[mailto:([^\[\]])+\]/", "", $textNoLinks );
        $textNoLinks = preg_replace( "/[^\[]mailto:[^\s^\]^\[]+[^\[]\s/", "", $textNoLinks );
        // E: [gopher:.... ]
        $textNoLinks = preg_replace( "/\[gopher:([^\[\]])+\]/", "", $textNoLinks );
        $textNoLinks = preg_replace( "/[^\[]gopher:\/\/[^\s^\]^\[]+[^\[]\s/", "", $textNoLinks );
        // F: [news:.... ]
        $textNoLinks = preg_replace( "/\[news:([^\[\]])+\]/", "", $textNoLinks );
        $textNoLinks = preg_replace( "/[^\[]news:\/\/[^\s^\]^\[]+[^\[]\s/", "", $textNoLinks );
        // G: [ftp:.... ]
        $textNoLinks = preg_replace( "/\[ftp:([^\[\]])+\]/", "", $textNoLinks );
        $textNoLinks = preg_replace( "/[^\[]ftp:\/\/[^\s^\]^\[]+[^\[]\s/", "", $textNoLinks );
        // H: [irc:.... ]
        $textNoLinks = preg_replace( "/\[irc:([^\[\]])+\]/", "", $textNoLinks );
        $textNoLinks = preg_replace( "/[^\[]irc:\/\/[^\s^\]^\[]+[^\[]\s/", "", $textNoLinks );

        $charCountNoLinks = strlen( $textNoLinks );

        return 1 - ($charCountNoLinks / $charCount);
    }

    /**
     * Gets the number of (certain) syntax uses in an article
     * 2 is advanced, 1 is basic, 0 is none
     * 3 is broken spam bot
     *
     * @param $text string
     * @return integer 0|1|2|3
     */
    public static function syntaxType( $text ) {

        if ( AthenaFilters::brokenSpamBot( $text ) ) {
            return 3;
        } else {
            // Start with headings
            $count = preg_match_all( "/==([^=]+)==(\s)*(\n|$)/", $text );
            $count += preg_match_all( "/===([^=]+)===(\s)*(\n|$)/", $text );
            $count += preg_match_all( "/====([^=]+)====(\s)*(\n|$)/", $text );
            $count += preg_match_all( "/=====([^=]+)=====(\s)*(\n|$)/", $text );
            $count += preg_match_all( "/======([^=]+)======(\s)*(\n|$)/", $text );
            // nowiki tags are very wiki specific
            $count += preg_match_all( "/<nowiki>(.*)<\/nowiki>/", $text );
            $count += preg_match_all( "/<nowiki\/>/", $text );
            // Internal links
            $count += preg_match_all( "/\[\[([^\[\]])+\]\]/", $text );
            // Tables
            $count += preg_match_all( "/\{\|([^\{\|\}])+\|\}/", $text );
            // Templates
            // TODO Fix
            // $count += preg_match_all("/\{\{([^\{\}])+\}\}/", $text);

            if ( $count > 1 ) {
                return 2;
            } else {
                // Basic wiki syntax (bold, brs, links)
                $count = 0;
                // Links
                $count += AthenaFilters::numberOfLinks( $text );
                // Line breaks
                $count += preg_match_all( "/<br\/>|<br>/", $text );
                // Bold
                $count += preg_match_all( "/'''([^(''')]+)'''/", $text );
                // Italics
                $count += preg_match_all( "/''([^('')]+)''/", $text );

                // Check for alternative syntax
                $count += preg_match_all( "/<strong>(.*)<\/strong>/", $text );
                $count += preg_match_all( "/<a(.*)>(.*)<\/a>/", $text );
                $count += preg_match_all( "/[url]/", $text );
                if ( $count > 1 ) {
                    return 1;
                }
            }
        }

        // Else no syntax
        return 0;
    }

    /**
     * Compares the language of the site with the language of the edit
     * Returns true if different, and false if the same or null if error
     *
     * @param $text string
     * @return bool|null
     */
    public static function differentLanguage( $text ) {
        global $wgLanguageCode;

        $language = AthenaHelper::getTextLanguage( $text );

        // Remove any region specialities from wiki's language code (e.g. en-gb becomes en)
        $arr = preg_split( "/-/", $wgLanguageCode );

       // echo( "\n\n language code is " .  $arr[0] );
       // echo( "\n\n language is " .  $language );

        if ( $language !== null ) {
            if ( $arr[0] === $language ) {
                return false;
            }
            return true;
        }

        return null;
    }

    /**
     * Checks for broken spambot code
     * Determined based off of {blah|blah|blah} syntax and occurrences of #file_links<>
     *
     * @param $text string
     * @return bool
     */
    public static function brokenSpamBot( $text ) {
        // Word choices
        // TODO Fix
       // $count = preg_match_all("/\{([^\{\}]|)+\}/", $text);
        // Link count
        $count = preg_match_all( "/#file_links<>/", $text );

        // Let's be reasonable, for now
        if ( $count > 1 )
            return true;
        else
            return false;
    }

    /**
     * Returns the length of the page title
     *
     * @param $title Title
     * @return int
     */
    public static function titleLength( $title ) {
        return strlen( $title->getText() );
    }

    /**
     * Returns the namespace of the article
     *
     * @param $title Title
     * @return int
     */
    public static function getNamespace( $title ) {
        return $title->getNamespace();
    }

    /**
     * Checks if a page is wanted
     *
     * @param $title Title
     * @return bool
     */
    public static function isWanted( $title ) {
        $dbr = wfGetDB( DB_SLAVE );
        $res = $dbr->select(
            'pagelinks',                                  // $table
            array( 'count' => 'COUNT(*)' ),            // $vars (columns of the table)
            array( 'pl_title' => $title->getDBkey(),
                  'pl_namespace' => $title->getNamespace() ),         // $conds
            __METHOD__,                                   // $fname = 'Database::select',
            null        // $options = array()
        );

        // hacky approach is hacky
        $count = 0;
        foreach ( $res as $row ) {
            $count = $row->count;
            break;
        }

        if ( $count > 0 )
            return true;
        return false;
    }

    /**
     * Checks if a page has been deleted and if so, how many times
     *
     * @param $title Title
     * @return bool
     */
    public static function wasDeleted( $title )
    {
        $dbr = wfGetDB( DB_SLAVE );
        $res = $dbr->select(
            'archive',                                  // $table
            array( 'ar_namespace', 'ar_title', 'count' => 'COUNT(*)' ),            // $vars (columns of the table)
            array( 'ar_title' => $title->getDBkey(),
                'ar_namespace' => $title->getNamespace() ),
            null,      // $conds
            __METHOD__,                                   // $fname = 'Database::select',
            null        // $options = array()
        );

        // hacky approach is hacky
        $count = 0;
        foreach ( $res as $row ) {
            $count = $row->count;
            break;
        }

        if ( $count > 0 )
            return true;

        return false;
    }
}
