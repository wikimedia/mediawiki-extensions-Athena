<?php

class AthenaHelper
{

    /**
     * Load the probabilities related to the given variable
     *
     * @param $var string
     * @param $varFalg 0|1
     * @param $given string
     * @param $givenFlag 0|1
     * @return double|bool
     */
    static function loadProbabilities($var, $varFlag, $given, $givenFlag)
    {
        $db = wfGetDB(DB_MASTER);

        $whereStatement = " ap_variable='{$var}'";

        if ($varFlag) {
            $whereStatement .= " AND ap_variable_not=1";
        }

        if ($given) {
            $whereStatement .= " AND ap_given='{$given}'";
        }

        if ($givenFlag) {
            $whereStatement .= " AND ap_given_not=1";
        }

        $sql = "SELECT ap_value FROM {$db->tableName( 'athena_probability' )} WHERE {$whereStatement};";

        //echo($sql);

        $res = $db->query($sql, __METHOD__);
        $row = $db->fetchObject($res);

        if ($row) {
            return $row->ap_value;
        }

        // else we are bork and so let's say false
        echo('Something went wrong :(');
        return false;
    }

    /**
     * Log information about the attempted page creation
     *
     * @param $prob double
     * @param $userAge int
     * @param $links double
     * @param $syntax double
     * @param $language boolean
     * @param $broken boolean
     * @param $deleted boolean
     * @param $wanted boolean
     * @param $namespace int
     * @param $title string
     * @param $content string
     * @param $comment string
     * @param $user int
     */
    static function logAttempt($prob, $userAge, $links, $syntax, $language, $broken, $deleted, $wanted,
                                $namespace, $title, $content, $comment, $user) {
        $db = wfGetDB(DB_MASTER);

        $links = AthenaHelper::makeSQLNull($links);
        $syntax = AthenaHelper::makeSQLNull($syntax);

        if( $language === false ) {
            $language = 0;
        }
        $language = AthenaHelper::makeSQLNull($language);

        if( $broken === false ) {
            $broken = 0;
        }

        $broken = AthenaHelper::makeSQLNull($broken);

        if( $deleted === false ) {
            $deleted = 0;
        }

        $deleted = AthenaHelper::makeSQLNull($deleted);

        if( $wanted === false ) {
            $wanted = 0;
        }

        $wanted = AthenaHelper::makeSQLNull($wanted);
        $comment = AthenaHelper::makeSQLNull($comment);

        //$insertStatement = " (NULL, {$prob}, 0, {$userAge}, {$links}, {$syntax}, {$language}, {$broken}, ${wanted}, ${deleted})";

        //$sql = "INSERT INTO {$db->tableName( 'athena_log' )} VALUES {$insertStatement};";
        $insertArray = array( "al_id"=>NULL, "al_value"=>$prob, "al_success"=>0, "al_user_age"=>$userAge,
            "al_links"=>$links, "al_syntax"=>$syntax, "al_language"=>$language, "al_broken_spambot"=>$broken,
            "al_wanted"=>$wanted, "al_deleted"=>$deleted);
        $db->insert('athena_log', $insertArray, __METHOD__, null);

       // $db->query($sql, __METHOD__);

        // Get last inserted ID
        $sql = 'select LAST_INSERT_ID() as id;';
        $res = $db->query($sql);
        $row = $db->fetchObject( $res );
        $id = $row->id;

        $title = mysql_real_escape_string ($title);
        $content = mysql_real_escape_string ($content);
        $comment = mysql_real_escape_string ($comment);
        // TODO security
        //$insertStatement = " ({$id}, {$namespace}, '{$title}', '{$content}', {$comment}, {$user}, CURRENT_TIMESTAMP, NULL, NULL)";
        $insertArray = array( "al_id"=>$id, "apd_namespace"=>$namespace, "apd_title"=>$title,
            "apd_content"=>$content, "apd_comment"=>$comment, "apd_user"=>$user,
            "page_id"=>"NULL", "rev_id"=>"NULL");
       // $sql = "INSERT INTO {$db->tableName( 'athena_page_details' )} VALUES {$insertStatement};";
        //print_r($insertArray);

        try {
            $db->insert('athena_page_details', $insertArray, __METHOD__, null);
        } catch (Exception $e) {
            print_r($e);
        }
        //$db->query($sql, __METHOD__);

    }


    /**
     * Log the page creation attempt
     *
     * @param $var string
     * @return double|bool
     */
    static function loadWeightings($var)
    {
        $db = wfGetDB(DB_MASTER);

        $whereStatement = " aw_variable='{$var}'";

        $sql = "SELECT aw_value FROM {$db->tableName( 'athena_weighting' )} WHERE {$whereStatement};";

        //echo($sql);

        $res = $db->query($sql, __METHOD__);
        $row = $db->fetchObject($res);

        if ($row) {
            return $row->aw_value;
        }

        // else we are bork and so let's say false
        echo('Something went wrong :(');
        return false;
    }

    /**
     * Loads the language classifier
     * @return Text_LanguageDetect
     */
    static function getClassifier() {
        global $IP;

        // Code for Text-LanguageDetect
        require_once $IP . '\extensions\Athena\libs\Text_LanguageDetect-0.3.0\Text\LanguageDetect.php';
        $classifier = new Text_LanguageDetect;
        // Set it to return ISO 639-1 (same format as MediaWiki)
        $classifier->setNameMode(2);
        return $classifier;
    }

    /**
     * Converts empty variables to be a NULL string for SQL purposes
     *
     * @param $var type
     * @return string|type
     */
    static function makeSQLNull( $var ) {
        // Can't use empty() as 0 and false are empty
        if( $var === null || $var === '' ) {
            return "NULL";
        }
        return $var;
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

        $titleObj = $editPage->getTitle();
        $namespace = $titleObj->getNamespace();
        $title = $titleObj->getTitleValue()->getText();

        /* start different language */
        $diffLang = AthenaFilters::differentLanguage($text);

        $probDiffLang = AthenaHelper::calculateAthenaValue_Language( $diffLang );
        echo( "<br/>  difflang is " . $probDiffLang);
        /* end different language */

        /* start broken spam bot */
        //$brokenSpamBot = AthenaFilters::brokenSpamBot( $text );
        //echo( "<br/>  brokenspambot is " . $brokenSpamBot);

       // $probBrokenSpamBot = AthenaHelper::calculateAthenaValue_BrokenSpamBot( $brokenSpamBot );
        /* end broken spam bot */

        /* start deleted */
        $deleted = AthenaFilters::wasDeleted( $titleObj );

        $probDeleted = AthenaHelper::calculateAthenaValue_Deleted( $deleted );
        echo( "<br/>  deleted is " . $probDeleted);
        /* end deleted */

        /* start wanted */
        $wanted = AthenaFilters::isWanted( $titleObj );

        $probWanted = AthenaHelper::calculateAthenaValue_Wanted( $wanted );
        echo( "<br/>  wanted is " . $probWanted);
        /* end wanted */

        /* start user type */
        $userAge = AthenaFilters::userAge();

        $probUser = AthenaHelper::calculateAthenaValue_User( $userAge );
        echo( "<br/>  user age is " . $probUser);
        /* end user type */

        /* start title length */
        $titleLength = AthenaFilters::titleLength( $titleObj );

        $probLength = AthenaHelper::calculateAthenaValue_Length( $titleLength );
        echo( "<br/>  title length is " . $probLength);
        /* end title length */

        /* start title length */
        $namespace = AthenaFilters::getNamespace( $titleObj );

        $probNamespace = AthenaHelper::calculateAthenaValue_Namespace( $namespace );
        echo( "<br/>  namespace is " . $probNamespace);
        /* end title length */

        /* start syntax */
        //$brokenSpamBot = AthenaFilters::brokenSpamBot( $text );
        $syntaxType = AthenaFilters::syntaxType( $text );

        $probSyntax = AthenaHelper::calculateAthenaValue_Syntax( $syntaxType );
        echo( "<br/>  syntax is " . $probSyntax);
        /* end syntax */

        /* start syntax */
        $links = AthenaFilters::linkPercentage( $text );

        $probLinks = AthenaHelper::calculateAthenaValue_Links( $links );
        echo( "<br/>  links is " . $probLinks);
        /* end syntax */

        $prob = $probDiffLang + $probDeleted + $probWanted + $probUser + $probLength + $probNamespace + $probSyntax + $probLinks;


        echo( "<br/> total probability is " . $prob );

        // Log here
        if( $syntaxType === 3 ) {
            $brokenSpamBot = true;
        } else {
            $brokenSpamBot = false;
        }
        AthenaHelper::logAttempt($prob, $userAge, $links, $syntaxType, $diffLang, $brokenSpamBot, $deleted, $wanted, $namespace, $title,
            $text, $summary, $wgUser->getId());
        return $prob;
    }

    /**
     * Calculates the probability related to the different language filter
     *
     * @param $diffLang bool
     * @return double
     */
    static function calculateAthenaValue_Language( $diffLang ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if( !$diffLang ) {
            $notFlag = 1;
        }

        $probLang = AthenaHelper::loadProbabilities("spam", 0, "difflang", $notFlag);
        //echo( "<br/> probLang is " . $probLang );

        $weightLang = AthenaHelper::loadWeightings("difflang");
        //echo( "<br/>  weightLang is " . $weightLang );

        return $weightLang * $probLang;
    }

    /**
     * Calculates the probability related to the broken spam bot filter
     *
     * @param $brokenSpamBot bool
     * @return double
     */
    static function calculateAthenaValue_BrokenSpamBot( $brokenSpamBot ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if( !$brokenSpamBot ) {
            $notFlag = 1;
        }

        $probLang = AthenaHelper::loadProbabilities("spam", 0, "brokenspambot", $notFlag);
        //echo( "<br/> probSpamBot is " . $probLang );

        $weightLang = AthenaHelper::loadWeightings("brokenspambot");
        //echo( "<br/>  weightSpamBot is " . $weightLang );

        return $weightLang * $probLang;
    }

    /**
     * Calculates the probability related to the deleted filter
     *
     * @param $wasDeleted bool
     * @return double
     */
    static function calculateAthenaValue_Deleted( $wasDeleted ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if( !$wasDeleted ) {
            $notFlag = 1;
        }

        $probDeleted = AthenaHelper::loadProbabilities("spam", 0, "deleted", $notFlag);
        //echo( "<br/> probdeleted is " . $probDeleted );

        $weightDeleted = AthenaHelper::loadWeightings("deleted");
        //echo( "<br/>  weightdeleted is " . $weightDeleted );

        return $weightDeleted * $probDeleted;
    }

    /**
     * Calculates the probability related to the wanted filter
     *
     * @param $isWanted bool
     * @return double
     */
    static function calculateAthenaValue_Wanted( $isWanted ) {
        $notFlag = 0;
        // Let's treat null as false for simplicity
        if( !$isWanted ) {
            $notFlag = 1;
        }

        $probWanted = AthenaHelper::loadProbabilities("spam", 0, "wanted", $notFlag);
        //echo( "<br/> probwanted is " . $probWanted );

        $weightWanted = AthenaHelper::loadWeightings("wanted");
        //echo( "<br/>  weightwanted is " . $weightWanted );

        return $weightWanted * $probWanted;
    }

    /**
     * Calculates the probability related to the user type filter
     *
     * @param $userAge int
     * @return double
     */
    static function calculateAthenaValue_User( $userAge ) {

        $varName = "anon";
        if( $userAge >= 0 ) {
            if( $userAge < 1 )
                $varName = "user1";
            else if ( $userAge < 5 )
                $varName = "user5";
            else if ( $userAge < 30 )
                $varName = "user30";
            else if ( $userAge < 60 )
                $varName = "user60";
            else if ( $userAge < 60*12 )
                $varName = "user12";
            else if ( $userAge < 60*24 )
                $varName = "user24";
            else
                $varName = "userother";
        }


        $probUser = AthenaHelper::loadProbabilities("spam", 0, $varName, 0);
        //echo( "<br/> probUser is " . $probUser );

        $weightUser = AthenaHelper::loadWeightings("userage");
        //echo( "<br/>  weightwanted is " . $weightUser );

        return $weightUser * $probUser;
    }

    /**
     * Calculates the probability related to the title length filter
     *
     * @param $length int
     * @return double
     */
    static function calculateAthenaValue_Length( $length ) {
        $notFlag = 1;
        // Let's treat null as false for simplicity
        if( $length > 39 ) {
            $notFlag = 0;
        }

        $probLength = AthenaHelper::loadProbabilities( "spam", 0, "titlelength", $notFlag );
        //echo( "<br/> probLength is " . $probLength );

        $weightLength = AthenaHelper::loadWeightings( "titlelength" );
        //echo( "<br/>  weightLength is " . $weightLength );

        return $weightLength * $probLength;
    }

    /**
     * Calculates the probability related to the namespace filter
     *
     * @param $namespace int
     * @return double
     */
    static function calculateAthenaValue_Namespace( $namespace ) {

        $varName = "nsother";
        if( $namespace === 0 )
            $varName = "nsmain";
        else if ( $namespace === 1 )
            $varName = "nstalk";
        else if ( $namespace === 2 )
            $varName = "nsuser";
        else if ( $namespace === 3 )
            $varName = "nsusertalk";

        $probNamespace = AthenaHelper::loadProbabilities("spam", 0, $varName, 0);
        //echo( "<br/> probnamespace is " . $probNamespace );

        $weightNamespace = AthenaHelper::loadWeightings("namespace");
        //echo( "<br/>  weightwanted is " . $weightNamespace );

        return $weightNamespace * $probNamespace;
    }


    /**
     * Calculates the probability related to the syntax filter
     *
     * @param $type int
     * @return double
     */
    static function calculateAthenaValue_Syntax( $type ) {

        $varName = "syntaxnone";
        if( $type === 1 )
            $varName = "syntaxbasic";
        else if ( $type === 2 )
            $varName = "syntaxcomplex";
        else if ( $type === 3 )
            $varName = "brokenspambot";

        $probNamespace = AthenaHelper::loadProbabilities("spam", 0, $varName, 0);
        //echo( "<br/> probnamespace is " . $probNamespace );

        $weightNamespace = AthenaHelper::loadWeightings("syntax");
       //echo( "<br/>  weightwanted is " . $weightNamespace );

        return $weightNamespace * $probNamespace;
    }

    /**
     * Calculates the probability related to the link filter
     *
     * @param $percentage double
     * @return double
     */
    static function calculateAthenaValue_Links( $percentage ) {

        $varName = "links0";
        if( $percentage > 0 && $percentage < 0.1 )
            $varName = "links5";
        else if ( $percentage >= 0.1 && $percentage <= 0.35 )
            $varName = "links20";
        else if ( $percentage > 0.35 )
            $varName = "links50";

        $probLinks = AthenaHelper::loadProbabilities("spam", 0, $varName, 0);
        //echo( "<br/> probLinks is " . $probLinks );

        $weightLinks = AthenaHelper::loadWeightings("links");
        //echo( "<br/>  $weightLinks is " . $weightLinks );

        return $weightLinks * $probLinks;
    }
}