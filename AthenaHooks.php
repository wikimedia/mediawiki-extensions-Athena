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

        // Check if it's a new article or not
        if ( $editPage->getTitle()->getArticleID() === 0 ) {
            // Let's skip redirects
            $redirect = preg_match_all("/^#REDIRECT(\s)?\[\[([^\[\]])+\]\]$/", $text);
            if( $redirect !== 1 ) {

                // TODO proper message, i18n and stuff
                /*$error =
                    "<div class='errorbox'>" .
                    "Your edit has been triggered as spam. If you think this is a mistake, please let an admin know" .
                    "</div>\n" .
                    "<br clear='all' />\n";*/
                $var1 = AthenaFilters::isDeleted($editPage->getTitle());
                /*if( $var1 === null ) {
                    $var1 = "null";
                } else if( $var1 === true) {
                    $var1 = "same";
                } else
                    $var1 = "different";*/

                $error =
                    "<div class='errorbox'>" .
                     $var1 .
                    "</div>\n" .
                    "<br clear='all' />\n";
            }
        }
        return true;
    }
}