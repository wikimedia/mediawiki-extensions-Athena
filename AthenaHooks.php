<?php

class AthenaHooks {

    /**
     * Called when the edit is about to be saved
     *
     * @param $editPage EditPage
     * @param $text string
     * @param $section string
     * @param $error string
     * @param $summary string
     * @return bool
     */
    static function editFilter( $editPage, $text, $section, &$error, $summary ) {
        global $wgAthenaSpamThreshold;
        // Check if it's a new article or not
        if ( $editPage->getTitle()->getArticleID() === 0 ) {
            // Let's skip redirects
            $redirect = preg_match_all("/^#REDIRECT(\s)?\[\[([^\[\]])+\]\]$/", $text);
            if( $redirect !== 1 ) {

                // TODO proper message, i18n and stuff

                $userAge = AthenaFilters::userAge();

                $varName = "anon";
                $notFlag = 1;
                if( $userAge === -1 ) {
                    $notFlag = 0;
                }

                // Get probability of it being spam
                $probAge = AthenaHooks::loadProbabilities("spam", 0, $varName, $notFlag);
                echo( "\n probAge is " . $probAge );

                $sameLang = AthenaFilters::sameLanguage($text);
                $notFlag = 0;
                if( $sameLang ) {
                    $notFlag = 1;
                }

                if( empty($sameLang) ) {
                   $probLang = AthenaHooks::loadProbabilities("spam", 0, "difflang", $notFlag);
                    echo( "\n probLang is " . $probLang );
                }

                $weightAge = AthenaHooks::loadWeightings("userage");
                echo( "\n weightAge is " . $weightAge );
                if( $probLang ) {
                    $weightLang = AthenaHooks::loadWeightings("lang");
                    echo( "\n weightLang is " . $weightLang );

                    $prob = $weightLang * $probLang + $weightAge * $probAge;
                } else {
                    $prob = $probAge;
                }
                echo( "\n prob is " . $prob );

                /*if( $prob > $wgAthenaSpamThreshold ) {
                    $error =
                        "<div class='errorbox'>" .
                        "Your edit has been triggered as spam. If you think this is a mistake, please let an admin know" .
                        "</div>\n" .
                        "<br clear='all' />\n";
                } else {*/
                    $error =
                        "<div class='errorbox'>" .
                        "prob is" . $prob .
                         "</div>\n" .
                         "<br clear='all' />\n";
               // }

                // Log here
                AthenaHooks::logAttempt($prob, $userAge, null, null, $sameLang, null, null, null, null);
            }
        }
        return true;
    }

    /**
     * Updates the database with the new Athena tabled
     * Called when the update.php maintenance script is run.
     *
     * @param $updater DatabaseUpdater
     * @return bool
     */
    static function createTables( $updater ) {
        $updater->addExtensionUpdate( array( 'addTable', 'athena_weighting', __DIR__ . '/sql/athena_probability.sql', true ) );
        $updater->addExtensionUpdate( array( 'addTable', 'athena_log', __DIR__ . '/sql/athena_logs.sql', true ) );
        // don't really need these two
        //$updater->addExtensionUpdate( array( 'addTable', 'athena_fail_log', __DIR__ . '/sql/athena_logs.sql', true ) );
        //$updater->addExtensionUpdate( array( 'addTable', 'athena_fail_page', __DIR__ . '/sql/athena_logs.sql', true ) );
        return true;
    }

    /**
     * Load the probabilities related to the given variable
     *
     * @param $var string
     * @param $varFalg 0|1
     * @param $given string
     * @param $givenFlag 0|1
     * @return double|bool
     */
    static function loadProbabilities( $var, $varFlag, $given, $givenFlag ) {
        $db = wfGetDB( DB_MASTER );

        $whereStatement = " ap_variable='{$var}'";

        if( $varFlag ) {
            $whereStatement .=  " AND ap_variable_not=1";
        }

        if( $given ) {
            $whereStatement .= " AND ap_given='{$given}'";
        }

        if( $givenFlag ) {
            $whereStatement .= " AND ap_given_not=1";
        }

        $sql = "SELECT ap_value FROM {$db->tableName( 'athena_probability' )} WHERE {$whereStatement};";

        echo ($sql);

        $res = $db->query( $sql, __METHOD__ );
        $row = $db->fetchObject( $res );

        if( $row ) {
            return $row->ap_value;
        }

        // else we are bork and so let's say false
        echo ('Something went wrong :(');
        return false;
    }

    /**
     * Load the weighting for a given variable
     *
     * @param $prob double
     * @param $userAge int
     * @param $links double
     * @param $syntax double
     * @param $language boolean
     * @param $broken boolean
     * @param $deleted boolean
     * @param $wanted boolean
     * @return double|bool
     */
    static function logAttempt( $prob, $userAge, $links, $syntax, $language, $broken, $deleted, $wanted ) {
        $db = wfGetDB( DB_MASTER );

        $insertStatement = " (NULL, {$prob}, {$userAge}, {$links}, {$syntax}, {$language}, {$broken}, ${deleted}, ${wanted})";

        $sql = "INSERT INTO {$db->tableName( 'athena_log' )} VALUES {$insertStatement};";

        echo ($sql);

        $res = $db->query( $sql, __METHOD__ );
        $row = $db->fetchObject( $res );

        if( $row ) {
            echo( $row->aw_id );
            return $row->aw_id;
        }

        // else we are bork and so let's say false
        echo ('Something went wrong insert :(');
        return false;
    }


    /**
     * Log the page creation attempt
     *
     * @param $var string
     * @return double|bool
     */
    static function loadWeightings( $var ) {
        $db = wfGetDB( DB_MASTER );

        $whereStatement = " aw_variable='{$var}'";

        $sql = "SELECT aw_value FROM {$db->tableName( 'athena_weighting' )} WHERE {$whereStatement};";

        echo ($sql);

        $res = $db->query( $sql, __METHOD__ );
        $row = $db->fetchObject( $res );

        if( $row ) {
            return $row->aw_value;
        }

        // else we are bork and so let's say false
        echo ('Something went wrong :(');
        return false;
    }
}