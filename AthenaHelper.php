<?php


/**
 * Class AthenaHelper
 *
 * Various helper functions for Athena
 */
class AthenaHelper
{

    /**
     * Load the probabilities related to the given variable
     *
     * @param $var string
     * @param $varFlag 0|1
     * @param $given string
     * @param $givenFlag 0|1
     * @return double|bool
     */
    static function loadProbabilities( $var, $varFlag, $given, $givenFlag )
    {
        $dbr = wfGetDB( DB_MASTER );

        $whereStatement = " `ap_variable`='$var'";

        if ( $varFlag ) {
            $whereStatement .= ' AND `ap_variable_not`=1';
        }

        if ( $given ) {
            $whereStatement .= " AND `ap_given`='$given'";
        }

        if ( $givenFlag ) {
            $whereStatement .= ' AND `ap_given_not`=1';
        }

        $sql = "SELECT ap_value FROM {$dbr->tableName( 'athena_probability' )} WHERE {$whereStatement};";

        $res = $dbr->query( $sql, __METHOD__ );
        $row = $dbr->fetchObject( $res );

        if ( $row ) {
            return $row->ap_value;
        }

        return false;
    }

    /**
     * Log information about the attempted page creation
     *
     * @param $logArray array
     * @param $detailsArray array
     * @param $calcArray array
     */
    static function logAttempt( $logArray, $detailsArray, $calcArray ) {
        $dbw = wfGetDB( DB_MASTER );

        $dbw->insert( 'athena_log', $logArray, __METHOD__, null );

        // Get last inserted ID
        $sql = 'select LAST_INSERT_ID() as id;';
        $res = $dbw->query( $sql );
        $row = $dbw->fetchObject( $res );
        $id = $row->id;

        $detailsArray['al_id'] = $id;
        $calcArray['al_id'] = $id;

        print_r($calcArray);

        try {
            $dbw->insert( 'athena_page_details', $detailsArray, __METHOD__, null );
            $dbw->insert( 'athena_calculations', $calcArray, __METHOD__, null );
        } catch ( Exception $e ) {
            print_r( $e );
        }
    }

    /**
     * Prepare an array with the details we want to insert into the athena_log table
     *
     * @param $prob double
     * @param $userAge integer
     * @param $links integer
     * @param $linkPercentage double
     * @param $syntax double
     * @param $language boolean
     * @param $deleted boolean
     * @param $wanted boolean
     * @return array
     */
    static function prepareLogArray( $prob, $userAge, $links, $linkPercentage, $syntax, $language, $deleted, $wanted ) {

        if ( $deleted === false ) {
            $deleted = 0;
        }

        if ( $wanted === false ) {
            $wanted = 0;
        }

        $insertArray = array( 'al_id' => NULL, 'al_value' => $prob, 'al_success' => 0, 'al_user_age' => $userAge,
            'al_links' => $links, 'al_link_percentage' => $linkPercentage, 'al_syntax' => $syntax,
            'al_wanted' => $wanted, 'al_deleted' => $deleted );

        // Language could be null
        // TODO check this works
        if ( $language != '' ) {
            if( $language === false ) {
                $language = 0;
            }
            $insertArray['al_language'] = $language;
        }



        return $insertArray;
    }

    /**
     * Prepare an array with the details we want to insert into the athena_page_details table
     *
     * @param $prob double
     * @param $namespace int
     * @param $title string
     * @param $content string
     * @param $comment string
     * @param $user int
     * @return array
     */
    static function preparePageDetailsArray( $namespace, $title, $content, $comment, $user ) {
        $dbw = wfGetDB( DB_MASTER );

        $title = $dbw->strencode( $title );
        $content = $dbw->strencode( $content );

        $insertArray = array( 'apd_namespace' => $namespace, 'apd_title' => $title,
            'apd_content' => $content, 'apd_user' => $user,
            'page_id' => 'NULL', 'rev_id' => 'NULL' );

        if ( $comment != '' ) {
            $comment = $dbw->strencode( $comment );
            $insertArray['apd_comment'] = $comment;
        }

        $language = AthenaHelper::getTextLanguage( $content );

        if( $language != '') {
            $language = $dbw->strencode($language);
            $insertArray['apd_language'] = $language;
        }

        return $insertArray;
    }

    /**
     * Loading a given weight
     *
     * @param $var string
     * @return double|bool
     */
    static function loadWeightings( $var )
    {
        $dbr = wfGetDB( DB_MASTER );

        $whereStatement = " `aw_variable`='$var'";

        $sql = "SELECT aw_value FROM {$dbr->tableName( 'athena_weighting' )} WHERE {$whereStatement};";

        $res = $dbr->query( $sql, __METHOD__ );
        $row = $dbr->fetchObject( $res );


        if ( $row ) {
            return $row->aw_value;
        }

        // else we are bork and so let's say false
        return false;
    }

    /**
     * Loads the language classifier
     *
     * @return Text_LanguageDetect
     */
    static function getClassifier()
    {
        global $IP;

        // Code for Text-LanguageDetect
        require_once($IP . '\extensions\Athena\libs\Text_LanguageDetect-0.3.0\Text\LanguageDetect.php');
        $classifier = new Text_LanguageDetect;

        // Set it to return ISO 639-1 (same format as MediaWiki)
        $classifier->setNameMode(2);

        return $classifier;
    }

    /**
     * Calculates the Athena value
     * Calls all the filters, works out the probability of each contributing to the spam level, and combines them
     *
     * @param $editPage EditPage
     * @param $text string
     * @param $summary string
     * @return double
     */
    static function calculateAthenaValue( $editPage, $text, $summary ) {
        global $wgUser;

        // Array to store calculation info
        $calcArray = array();

        $titleObj = $editPage->getTitle();
        $title = $titleObj->getTitleValue()->getText();

        /* start different language */
        $diffLang = AthenaFilters::differentLanguage( $text );

        $probDiffLang = AthenaHelper::calculateAthenaValue_Language( $diffLang, $calcArray );
        /* end different language */

        /* start deleted */
        $deleted = AthenaFilters::wasDeleted( $titleObj );

        $probDeleted = AthenaHelper::calculateAthenaValue_Deleted( $deleted, $calcArray );
        /* end deleted */

        /* start wanted */
        $wanted = AthenaFilters::isWanted( $titleObj );

        $probWanted = AthenaHelper::calculateAthenaValue_Wanted( $wanted, $calcArray );
        /* end wanted */

        /* start user type */
        $userAge = AthenaFilters::userAge();

        $probUser = AthenaHelper::calculateAthenaValue_User( $userAge, $calcArray );
        /* end user type */

        /* start title length */
        $titleLength = AthenaFilters::titleLength( $titleObj );

        $probLength = AthenaHelper::calculateAthenaValue_Length( $titleLength, $calcArray );
        /* end title length */

        /* start title length */
        $namespace = AthenaFilters::getNamespace( $titleObj );

        $probNamespace = AthenaHelper::calculateAthenaValue_Namespace( $namespace, $calcArray );
        /* end title length */

        /* start syntax */
        $syntaxType = AthenaFilters::syntaxType( $text );

        $probSyntax = AthenaHelper::calculateAthenaValue_Syntax( $syntaxType, $calcArray );
        /* end syntax */

        /* start syntax */
        $linksPercentage = AthenaFilters::linkPercentage( $text );

        $probLinks = AthenaHelper::calculateAthenaValue_Links( $linksPercentage, $calcArray );
        /* end syntax */

        $prob = $probDiffLang + $probDeleted + $probWanted + $probUser + $probLength + $probNamespace + $probSyntax + $probLinks;

        $links = AthenaFilters::numberOfLinks( $text );

        $logArray = AthenaHelper::prepareLogArray( $prob, $userAge, $links, $linksPercentage, $syntaxType, $diffLang, $deleted, $wanted );
        $detailsArray = AthenaHelper::preparePageDetailsArray( $namespace, $title, $text, $summary, $wgUser->getId() );

        AthenaHelper::logAttempt( $logArray, $detailsArray, $calcArray );

        return $prob;
    }

    /**
     * Calculates the probability related to the different language filter
     *
     * @param $diffLang bool
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Language( $diffLang, &$calcArray ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if ( !$diffLang ) {
            $notFlag = 1;
        }

        $probLang = AthenaHelper::loadProbabilities( 'spam', 0, 'difflang', $notFlag );

        $weightLang = AthenaHelper::loadWeightings( 'difflang' );

        echo( "problang is " + $probLang);
        echo( "weightlang is " + $weightLang);

        $calcArray['ac_p_diff_lang'] = $probLang;
        $calcArray['ac_w_diff_lang'] = $weightLang;

        return $weightLang * $probLang;
    }

    /**
     * Calculates the probability related to the deleted filter
     *
     * @param $wasDeleted bool
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Deleted( $wasDeleted, &$calcArray ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if ( !$wasDeleted ) {
            $notFlag = 1;
        }

        $probDeleted = AthenaHelper::loadProbabilities( 'spam', 0, 'deleted', $notFlag );

        $weightDeleted = AthenaHelper::loadWeightings( 'deleted' );

        $calcArray['ac_p_deleted'] = $probDeleted;
        $calcArray['ac_w_deleted'] = $weightDeleted;

        return $weightDeleted * $probDeleted;
    }

    /**
     * Calculates the probability related to the wanted filter
     *
     * @param $isWanted bool
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Wanted( $isWanted, &$calcArray ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if ( !$isWanted ) {
            $notFlag = 1;
        }

        $probWanted = AthenaHelper::loadProbabilities( 'spam', 0, 'wanted', $notFlag );

        $weightWanted = AthenaHelper::loadWeightings( 'wanted' );

        $calcArray['ac_p_wanted'] = $probWanted;
        $calcArray['ac_w_wanted'] = $weightWanted;

        return $weightWanted * $probWanted;
    }

    /**
     * Calculates the probability related to the user type filter
     *
     * @param $userAge int
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_User( $userAge, &$calcArray ) {

        $varName = 'anon';
        if ( $userAge >= 0 ) {
            if ( $userAge < 1 )
                $varName = 'user1';
            else if ( $userAge < 5 )
                $varName = 'user5';
            else if ( $userAge < 30 )
                $varName = 'user30';
            else if ( $userAge < 60 )
                $varName = 'user60';
            else if ( $userAge < 60 * 12 )
                $varName = 'user12';
            else if ( $userAge < 60 * 24 )
                $varName = 'user24';
            else
                $varName = 'userother';
        } else {
            if ( $userAge != -1 ) {
                // -2 is no registration details - we shouldn't have that problem though
                // anything bigger will be imported content, so let's just assume they were greater than a day and do other
                $varName = 'userother';
            }
        }

        $probUser = AthenaHelper::loadProbabilities( 'spam', 0, $varName, 0 );

        $weightUser = AthenaHelper::loadWeightings( 'userage' );

        $calcArray['ac_p_user_age'] = $probUser;
        $calcArray['ac_w_user_age'] = $weightUser;

        return $weightUser * $probUser;
    }

    /**
     * Calculates the probability related to the title length filter
     *
     * @param $length int
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Length( $length, &$calcArray ) {
        $notFlag = 1;
        // Let's treat null as false for simplicity
        if ( $length > 39 ) {
            $notFlag = 0;
        }

        $probLength = AthenaHelper::loadProbabilities( 'spam', 0, 'titlelength', $notFlag );

        $weightLength = AthenaHelper::loadWeightings( 'titlelength' );

        $calcArray['ac_p_title_length'] = $probLength;
        $calcArray['ac_w_title_length'] = $weightLength;

        return $weightLength * $probLength;
    }

    /**
     * Calculates the probability related to the namespace filter
     *
     * @param $namespace int
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Namespace( $namespace, &$calcArray )
    {

        $varName = 'nsother';
        if ($namespace === 0)
            $varName = 'nsmain';
        else if ($namespace === 1)
            $varName = 'nstalk';
        else if ($namespace === 2)
            $varName = 'nsuser';
        else if ($namespace === 3)
            $varName = 'nsusertalk';

        $probNamespace = AthenaHelper::loadProbabilities('spam', 0, $varName, 0);

        $weightNamespace = AthenaHelper::loadWeightings('namespace');

        $calcArray['ac_p_namespace'] = $probNamespace;
        $calcArray['ac_w_namespace'] = $weightNamespace;

        return $weightNamespace * $probNamespace;
    }

    /**
     * Calculates the probability related to the syntax filter
     *
     * @param $type int
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Syntax( $type, &$calcArray ) {

        $varName = 'syntaxnone';
        if ( $type === 1 )
            $varName = 'syntaxbasic';
        else if ( $type === 2 )
            $varName = 'syntaxcomplex';
        else if ( $type === 3 )
            $varName = 'brokenspambot';

        $probSyntax = AthenaHelper::loadProbabilities( 'spam', 0, $varName, 0 );

        $weightSyntax = AthenaHelper::loadWeightings( 'syntax' );

        $calcArray['ac_p_syntax'] = $probSyntax;
        $calcArray['ac_w_syntax'] = $weightSyntax;

        return $weightSyntax * $probSyntax;
    }

    /**
     * Calculates the probability related to the link filter
     *
     * @param $percentage double
     * @param &$calcArray array stores details about calculations
     * @return double
     */
    static function calculateAthenaValue_Links( $percentage, &$calcArray ) {

        $varName = 'links0';
        if ( $percentage > 0 && $percentage < 0.1 )
            $varName = 'links5';
        else if ( $percentage >= 0.1 && $percentage <= 0.35 )
            $varName = 'links20';
        else if ( $percentage > 0.35 )
            $varName = 'links50';

        $probLinks = AthenaHelper::loadProbabilities( 'spam', 0, $varName, 0 );

        $weightLinks = AthenaHelper::loadWeightings( 'links' );

        $calcArray['ac_p_link'] = $probLinks;
        $calcArray['ac_w_link'] = $weightLinks;

        return $weightLinks * $probLinks;
    }

    /**
     * Makes a number of seconds look nice and pretty
     *
     * @param $seconds integer
     * @return string
     */
    static function secondsToString( $seconds ) {
        // Look for days
        $str = '';
        if ( $seconds > 60 * 60 * 24 ) {
            $days = floor( $seconds / ( 60 * 60 * 24 ) );
            $str .= $days . ' days';
            $seconds -= $days * ( 60 * 60 * 24 );
        }
        if ( $seconds > 60 * 60 ) {
            $hours = floor( $seconds / ( 60 * 60 ) );
            if ( $str != '' ) {
                $str .= ', ';
            }
            $str .= $hours . ' hours';
            $seconds -= $hours * ( 60 * 60 );
        }
        if ( $seconds > 60 ) {
            $minutes = floor( $seconds / 60 );
            if ( $str != '' ) {
                $str .= ', ';
            }
            $str .= $minutes . ' minutes';
            $seconds -= $minutes * 60;
        }
        if ( $seconds >= 0 ) {
            if ( $str != '' ) {
                $str .= ' and ';
            }
            $str .= $seconds . ' seconds';
        }
        return $str;
    }

    /**
     * Takes a syntax type and returns its string equivalent
     *
     * @param $type integer
     * @return string
     */
    static function syntaxTypeToString( $type ) {
        switch( $type ) {
            case 0:
                return wfMessage( 'athena-syntax-none');
            case 1:
                return wfMessage( 'athena-syntax-basic');
            case 2:
                return wfMessage( 'athena-syntax-complex');
            case 3:
                return wfMessage( 'athena-syntax-spambot');
            default:
                return wfMessage( 'athena-syntax-invalid');
        }
    }

    /**
     * Takes a boolean (be it of type boolean or integer) and returns the equivalent string
     *
     * @param $val boolean|integer
     * @return string
     */
    static function boolToString( $val ) {
        if ( $val ) {
            return wfMessage( 'athena-true');
        }
        return wfMessage( 'athena-false');
    }

    /**
     * Gets the language of a given text
     *
     * @param $text string
     * @return string
     */
    static function getTextLanguage( $text ) {
        $classifier = AthenaHelper::getClassifier();
        try {
            return $classifier->detectSimple( $text );
        } catch ( Text_LanguageDetect_Exception $e ) {
            return null;
        }
    }
}