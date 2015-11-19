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

                $varName = "user";
                if( $userAge === -1 ) {
                    $varName = "anon";
                }

                // Get probability of it being spam
                $prob = AthenaHooks::loadProbabilities("spam", $varName);
                if( $prob ) {
                    if( $prob > $wgAthenaSpamThreshold ) {
                        $error =
                            "<div class='errorbox'>" .
                            "Your edit has been triggered as spam. If you think this is a mistake, please let an admin know" .
                            "</div>\n" .
                            "<br clear='all' />\n";
                    }
                }
                // Log here
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
        $updater->addExtensionUpdate( array( 'addTable', 'athena_probability', __DIR__ . '/sql/athena_probability.sql', true ) );
        $updater->addExtensionUpdate( array( 'addTable', 'athena_success_log', __DIR__ . '/sql/athena_logs.sql', true ) );
        // don't really need these two
        $updater->addExtensionUpdate( array( 'addTable', 'athena_fail_log', __DIR__ . '/sql/athena_logs.sql', true ) );
        $updater->addExtensionUpdate( array( 'addTable', 'athena_fail_page', __DIR__ . '/sql/athena_logs.sql', true ) );
        return true;
    }

    /**
     * Load the probabilities related to the given variable
     *
     * @param $var string
     * @param $given string
     * @return double|bool
     */
    static function loadProbabilities( $var, $given ) {
        $db = wfGetDB( DB_MASTER );

        $whereStatement = " ap_variable='{$var}'";
        if( $given ) {
            $whereStatement .= " AND ap_given='{$given}'";
        }

        $sql = "SELECT ap_value FROM {$db->tableName( 'athena_probability' )} WHERE {$whereStatement};";

        $res = $db->query( $sql, __METHOD__ );
        $row = $db->fetchObject( $res );

        if( $row ) {

            return $row->ap_value;
        }

        return false;
    }
}