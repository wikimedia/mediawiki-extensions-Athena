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
        global $wgAthenaSpamThreshold, $wgUser;
        // Check if it's a new article or not
        if ( $editPage->getTitle()->getArticleID() === 0 ) {
            $namespace = $editPage->getTitle()->getNamespace();
            $title = $editPage->getTitle()->getTitleValue()->getText();

            // Let's skip redirects
            $redirect = preg_match_all("/^#REDIRECT(\s)?\[\[([^\[\]])+\]\]$/", $text);
            if( $redirect !== 1 ) {

                // TODO proper message, i18n and stuff

               /* $userAge = AthenaFilters::userAge();

                $varName = "anon";
                $notFlag = 1;
                if( $userAge === -1 ) {
                    $notFlag = 0;
                }

                // Get probability of it being spam
                $probAge = AthenaHelper::loadProbabilities("spam", 0, $varName, $notFlag);
                echo( "\n probAge is " . $probAge );

                $diffLang = AthenaFilters::differentLanguage($text);

                $notFlag = 0;


                echo( "\n\n Samelanguage is " . $diffLang);
                if( $diffLang ) {
                    $notFlag = 1;
                }

                if( !empty($sameLang) ) {
                    $probLang = AthenaHelper::loadProbabilities("spam", 0, "difflang", $notFlag);
                    echo( "\n probLang is " . $probLang );
                }

                $weightAge = AthenaHelper::loadWeightings("userage");
                echo( "\n weightAge is " . $weightAge );
                if( !empty($probLang) ) {
                    $weightLang = AthenaHelper::loadWeightings("lang");
                    echo( "\n weightLang is " . $weightLang );

                    $prob = $weightLang * $probLang + $weightAge * $probAge;
                } else {
                    $prob = $probAge;
                }
                echo( "\n prob is " . $prob );
*/
                $prob = AthenaHelper::calculateAthenaValue( $editPage, $text, $summary );
               // if( $prob > $wgAthenaSpamThreshold ) {
                    $error =
                        "<div class='errorbox'>" .
                        "Your edit has been triggered as spam. If you think this is a mistake, please let an admin know" .
                        "</div>\n" .
                        "<br clear='all' />\n";
              //  }


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
     * If an article successfully saves, we want to take the page_id and rev_id and update our
     * athena_page_details table
     *
     * @param $article WikiPage
     * @param $user User
     * @param $content Content
     * @param $summary string
     * @param $isMinor boolean
     * @param $isWatch boolean
     * @param $section Deprecated
     * @param $flags integer
     * @param $revision {Revision|null}
     * @param $status Status
     * @param $baseRevId integer
     *
     * @return boolean
     */
    static function successfulEdit( $article, $user, $content, $summary, $isMinor, $isWatch, $section,
                                    $flags, $revision, $status, $baseRevId ) {
        $db = wfGetDB(DB_MASTER);

        $page_id = $article->getId();
        $rev_id = $article->getRevision()->getId();

        $title = mysql_real_escape_string ($article->getTitle()->getText());

        $whereStatement = " apd_title='{$title}' AND apd_namespace={$article->getTitle()->getNamespace()}";

        // TODO check multiple instances of the same title - maybe check user_id as well
        $sql = "SELECT al_id FROM {$db->tableName( 'athena_page_details' )} WHERE {$whereStatement} ORDER BY al_id DESC;";

        echo($sql);

        $res = $db->query($sql, __METHOD__);
        $row = $db->fetchObject($res);

        if ($row) {
            $id = $row->al_id;
            $updateStatement = " page_id={$page_id}, rev_id={$rev_id}";
            $whereStatement = " al_id = {$id}";

            $sql = "UPDATE {$db->tableName( 'athena_page_details' )} SET {$updateStatement} WHERE {$whereStatement};";

            echo($sql);

            $db->query($sql, __METHOD__);


            //return true;
        }
        return false;
    }
}