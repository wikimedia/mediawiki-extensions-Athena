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
        $debugStatement = 'Probability of ';

        if ( $varFlag ) {
            $whereStatement .= ' AND `ap_variable_not`=1';
            $debugStatement .= ' NOT ';
        }
        $debugStatement .= $var;

        if ( $given ) {
            $whereStatement .= " AND `ap_given`='$given'";
            $debugStatement .= ' given ';
        }

        if ( $givenFlag ) {
            $whereStatement .= ' AND `ap_given_not`=1';
            $debugStatement .= ' NOT ';
        }

        if( $given ) {
            $debugStatement .= $given;
        }

        $debugStatement .= ' is ';


        $sql = "SELECT ap_value FROM {$dbr->tableName( 'athena_probability' )} WHERE {$whereStatement};";

        $res = $dbr->query( $sql, __METHOD__ );
        $row = $dbr->fetchObject( $res );

        if ( $row ) {
            wfErrorLog( $debugStatement . $row->ap_value, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

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
        // TODO could do with a MediaWiki method
        $sql = 'select LAST_INSERT_ID() as id;';
        $res = $dbw->query( $sql );
        $row = $dbw->fetchObject( $res );
        $id = $row->id;

        $detailsArray['al_id'] = $id;
        $calcArray['al_id'] = $id;

        //print_r($calcArray);

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
        global $wgAthenaSpamThreshold;

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

        if ( $prob > $wgAthenaSpamThreshold ) {
            $insertArray['al_success'] = 0;
        } else {
            $insertArray['al_success'] = 1;
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
        AthenaHelper::updateStats( $logArray, $titleObj );
        AthenaHelper::updateProbabilities( );

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

        //echo( "problang is " + $probLang);
        //echo( "weightlang is " + $weightLang);

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
            return wfMessage( 'athena-true' );
        }
        return wfMessage( 'athena-false' );
    }

    /**
     * Loads the language classifier
     *
     * @return TextLanguageDetect
     */
   /* static function getClassifier()
    {
        global $IP;

        require_once( $IP . '\extensions\Athena\libs\text-language-detect-master\lib\TextLanguageDetect\TextLanguageDetect.php' );
        $classifier = new \TextLanguageDetect\TextLanguageDetect();

        // Set it to return ISO 639-1 (same format as MediaWiki)
        $classifier->setNameMode(2);
        foreach( $classifier->getLanguages() as $lang ) {
            wfErrorLog( $lang, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug-lang.log');
        }

        return $classifier;
    }*/

    /**
     * Gets the language of a given text
     *
     * @param $text string
     * @return string
     */
    static function getTextLanguage( $text ) {
        $code = system( "franc \"$text\"" );

        return AthenaHelper::convertISOCode($code);
    }

    /**
     * Updates the stats table with information from this edit
     *
     * @param $array array (logArray - contains details of the edit to be inserted into the athena_log table)
     * @param $title Title
     */
    static function updateStats( $array, $title ) {
        global $wgAthenaSpamThreshold;
        $dbw = wfGetDB( DB_SLAVE );

        // TODO not the best way but get me incrementing with the better way and I'll use it
        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'pages'";

        if ( $array['al_value'] > $wgAthenaSpamThreshold ) {
            $spam = true;
            $sql .= " OR `as_name`='spam' ";
        } else {
            $spam = false;
            $sql .= " OR `as_name`='notspam' ";
        }

        if ( $array['al_language'] ) {
            $sql .= " OR `as_name`='difflang' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamanddifflang' ";
            }
        } else {
            $sql .= " OR `as_name`='samelang' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandsamelang' ";
            }
        }

        if ( $array['al_deleted'] ) {
            $sql .= " OR `as_name`='deleted' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamanddeleted' ";
            }
        } else {
            $sql .= " OR `as_name`='notdeleted' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnotdeleted' ";
            }
        }

        if ( $array['al_wanted'] ) {
            $sql .= " OR `as_name`='wanted' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandwanted' ";
            }
        } else {
            $sql .= " OR `as_name`='notwanted' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnotwanted' ";
            }
        }

        $userAge = $array['al_user_age'];
          if ( $userAge >= 0 ) {
              if ($userAge < 1) {
                  $sql .= " OR `as_name`='user1' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser1' ";
                  }
              } else if ($userAge < 5) {
                  $sql .= " OR `as_name`='user5' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser5' ";
                  }
              } else if ( $userAge < 30 ) {
                  $sql .= " OR `as_name`='user30' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser30' ";
                  }
              } else if ( $userAge < 60 ) {
                  $sql .= " OR `as_name`='user60' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser60' ";
                  }
              } else if ( $userAge < 60 * 12 ) {
                $sql .= " OR `as_name`='user12' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser12' ";
                  }
              } else if ( $userAge < 60 * 24 ) {
                   $sql .= " OR `as_name`='user24' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser24' ";
                  }
              }  else {
                  $sql .= " OR `as_name`='userother' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduserother' ";
                  }
              }
          } else if ( $userAge != -1 ) {
              $sql .= " OR `as_name`='userother' ";
              if( $spam ) {
                  $sql .= " OR `as_name`='spamanduserothe' ";
              }
          } else {
              $sql .= " OR `as_name`='anon' ";
              if( $spam ) {
                  $sql .= " OR `as_name`='spamandanon' ";
              }
          }

        if ( strlen( $title->getText() ) > 39 ) {
            $sql .= " OR `as_name`='titlelength' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandtitlelength' ";
            }
        } else {
            $sql .= " OR `as_name`='nottitlelength' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnottitlelength' ";
            }
        }

        $namespace = $title->getNamespace();
        if ( $namespace == 0 ) {
            $sql .= " OR `as_name`='nsmain' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnsmain' ";
            }
        } else if ( $namespace == 1 ) {
            $sql .= " OR `as_name`='nstalk' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandnstalk' ";
            }
        } else if ( $namespace == 2 ) {
            $sql .= " OR `as_name`='nsuser' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandnsuser' ";
            }
        } else if ( $namespace == 3 ) {
            $sql .= " OR `as_name`='nsusertalk' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnsusertalk' ";
            }
        } else {
            $sql .= " OR `as_name`='nsother' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnsother' ";
            }
        }

        $syntax = $array['al_syntax'];
        if ( $syntax === 1 ) {
            $sql .= " OR `as_name`='syntaxbasic' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandsyntaxbasic' ";
            }
        } else if ( $syntax === 2 ) {
            $sql .= " OR `as_name`='syntaxcomplex' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandsyntaxcomplex' ";
            }
        } else if ( $syntax === 3 ) {
            $sql .= " OR `as_name`='brokenspambot' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandbrokenspambot' ";
            }
        } else {
            $sql .= " OR `as_name`='syntaxnone' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandsyntaxnone' ";
            }
        }

        $percentage = $array['al_link_percentage'];
        // TODO why is this named like this...
        if ( $percentage == 0 ) {
            $sql .= " OR `as_name`='links0' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandlinks0' ";
            }
        } else if ( $percentage > 0 && $percentage < 0.1 ) {
            $sql .= " OR `as_name`='links5' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandlinks5' ";
            }
        } else if ( $percentage >= 0.1 && $percentage <= 0.35 ) {
            $sql .= " OR `as_name`='links20' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandlinks20' ";
            }
        } else {
            $sql .= " OR `as_name`='links50' ";
            if ($spam) {
                $sql .= " OR `as_name`='spamandlinks50' ";
            }
        }
        $sql .= ";";

        $dbw->query( $sql );
        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
    }

    /**
     * Updates the probability table based on the new stats
     */
    static function updateProbabilities( ) {
        $dbw = wfGetDB( DB_SLAVE );

        // Have to use ids, which is far from ideal but is efficient
        $sql = "UPDATE `athena_probability` SET `ap_value` = CASE ";

        $stats = AthenaHelper::getStatistics();

        // Probability of spam
        // # of spam / # of pages
        $probSpam = $stats['spam'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=7 THEN $probSpam ";
        wfErrorLog( "New spam probability is " . $probSpam, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $notProb = 1 - $probSpam;
        $sql .= " WHEN `ap_id`=8 THEN $notProb ";

        // Probability of difflang
        // # of difflang / # of pages
        $prob = $stats['difflang'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=9 THEN $prob ";
        wfErrorLog( "Diff lang probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $notProb = 1 - $prob;
        $sql .= " WHEN `ap_id`=10 THEN $notProb ";

        // Probability of spam given difflang
        $opposite = $stats['spamanddifflang'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=11 THEN $condProb ";
        wfErrorLog( "spam given difflang probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given samelang
        $opposite = $stats['spamandsamelang'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$notProb;
        $sql .= " WHEN `ap_id`=12 THEN $condProb ";
        wfErrorLog( "spam given samelang probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of brokenspambot
        // # of brokenspambot / # of pages
        $prob = $stats['brokenspambot'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=13 THEN $prob ";
        wfErrorLog( "Brokenspambot probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $notProb = 1 - $prob;
        $sql .= " WHEN `ap_id`=14 THEN $notProb ";

        // Probability of spam given brokenspambot
        $opposite = $stats['spamandbrokenspambot'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=15 THEN $condProb ";
        wfErrorLog( "spam given brokenspambot probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // TODO skipping 16 (spam and not broken spam bot) as don't have stats and don't think its used

        // Probability of deleted
        // # of deleted / # of pages
        $prob = $stats['deleted'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=17 THEN $prob ";
        wfErrorLog( "New deleted probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $notProb = 1 - $prob;
        $sql .= " WHEN `ap_id`=18 THEN $notProb ";

        // Probability of spam given deleted
        $opposite = $stats['spamanddeleted'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=19 THEN $condProb ";
        wfErrorLog( "spam given deleted probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given not deleted
        $opposite = $stats['spamandnotdeleted'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$notProb;
        $sql .= " WHEN `ap_id`=20 THEN $condProb ";
        wfErrorLog( "spam given notdeleted probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of wanted
        // # of wanted / # of pages
        $prob = $stats['wanted'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=21 THEN $prob ";
        wfErrorLog( "New wanted probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $notProb = 1 - $prob;
        $sql .= " WHEN `ap_id`=22 THEN $notProb ";

        // Probability of spam given wanted
        $opposite = $stats['spamandwanted'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=23 THEN $condProb ";
        wfErrorLog( "spam given wanted probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given not wanted
        $opposite = $stats['spamandnotwanted'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$notProb;
        $sql .= " WHEN `ap_id`=24 THEN $condProb ";
        wfErrorLog( "spam given notwanted probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of anon
        // # of anon / # of pages
        $prob = $stats['anon'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=25 THEN $prob ";
        wfErrorLog( "New anon probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given anon
        $opposite = $stats['spamandanon'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=34 THEN $condProb ";
        wfErrorLog( "spam given anon probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // TODO skipping 44 for now (spam and not anon) as no stats and don't think its used

        // Probability of user1
        // # of user1 / # of pages
        $prob = $stats['user1'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=26 THEN $prob ";
        wfErrorLog( "New spam probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given user1
        $opposite = $stats['spamanduser1'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=35 THEN $condProb ";
        wfErrorLog( "spam given user1 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of user5
        // # of user5 / # of pages
        $prob = $stats['user5'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=27 THEN $prob ";
        wfErrorLog( "New user5 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given user5
        $opposite = $stats['spamanduser5'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=36 THEN $condProb ";
        wfErrorLog( "spam given user5 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of user30
        // # of user30 / # of pages
        $prob = $stats['user30'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=28 THEN $prob ";
        wfErrorLog( "New user30 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given user30
        $opposite = $stats['spamanduser30'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=37 THEN $condProb ";
        wfErrorLog( "spam given user30 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of user60
        // # of user60 / # of pages
        $prob = $stats['user60'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=29 THEN $prob ";
        wfErrorLog( "New user60 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given user60
        $opposite = $stats['spamanduser60'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=38 THEN $condProb ";
        wfErrorLog( "spam given user60 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of user12
        // # of user12 / # of pages
        $prob = $stats['user12'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=30 THEN $prob ";
        wfErrorLog( "New user12 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given user12
        $opposite = $stats['spamanduser12'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=39 THEN $condProb ";
        wfErrorLog( "spam given user12 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of user24
        // # of user24 / # of pages
        $prob = $stats['user24'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=42 THEN $prob ";
        wfErrorLog( "New user24 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given user24
        $opposite = $stats['spamanduser24'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=40 THEN $condProb ";
        wfErrorLog( "spam given user24 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of userother
        // # of userother / # of pages
        $prob = $stats['userother'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=31 THEN $prob ";
        wfErrorLog( "New userother probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given userother
        $opposite = $stats['spamanduserother'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=41 THEN $condProb ";
        wfErrorLog( "spam given userother probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of titlelength
        // # of titlelength / # of pages
        $prob = $stats['titlelength'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=45 THEN $prob ";
        wfErrorLog( "New titlelength probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $notProb = 1 - $prob;
        $sql .= " WHEN `ap_id`=46 THEN $notProb ";

        // Probability of spam given titlelength
        $opposite = $stats['spamandtitlelength'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=47 THEN $condProb ";
        wfErrorLog( "spam given titlelength probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given not titlelength
        $opposite = $stats['spamandnottitlelength'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$notProb;
        $sql .= " WHEN `ap_id`=48 THEN $condProb ";
        wfErrorLog( "spam given nottitlelength probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of nsmain
        // # of nsmain / # of pages
        $prob = $stats['nsmain'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=49 THEN $prob ";
        wfErrorLog( "New nsmain probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given nsmain
        $opposite = $stats['spamandnsmain'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=54 THEN $condProb ";
        wfErrorLog( "spam given nsmain probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of nstalk
        // # of nstalk / # of pages
        $prob = $stats['nstalk'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=50 THEN $prob ";
        wfErrorLog( "New nstalk probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given nstalk
        $opposite = $stats['spamandnstalk'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=55 THEN $condProb ";
        wfErrorLog( "spam given nstalk probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of nsuser
        // # of nsuser / # of pages
        $prob = $stats['nsuser'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=51 THEN $prob ";
        wfErrorLog( "New nsuser probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given nsuser
        $opposite = $stats['spamandnsuser'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=56 THEN $condProb ";
        wfErrorLog( "spam given nsuser probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of nsusertalk
        // # of nsusertalk / # of pages
        $prob = $stats['nsusertalk'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=52 THEN $prob ";
        wfErrorLog( "New nsusertalk probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given nsusertalk
        $opposite = $stats['spamandnsusertalk'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=57 THEN $condProb ";
        wfErrorLog( "spam given nsusertalk probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of nsother
        // # of nsother / # of pages
        $prob = $stats['nsother'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=53 THEN $prob ";
        wfErrorLog( "New nsother probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given nsother
        $opposite = $stats['spamandnsother'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=58 THEN $condProb ";
        wfErrorLog( "spam given nsother probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of syntaxnone
        // # of syntaxnone / # of pages
        $prob = $stats['syntaxnone'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=59 THEN $prob ";
        wfErrorLog( "New syntaxnone probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given syntaxnone
        $opposite = $stats['spamandsyntaxnone'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=60 THEN $condProb ";
        wfErrorLog( "spam given syntaxnone probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of syntaxbasic
        // # of syntaxbasic / # of pages
        $prob = $stats['syntaxbasic'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=61 THEN $prob ";
        wfErrorLog( "New syntaxbasic probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given syntaxbasic
        $opposite = $stats['spamandsyntaxbasic'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=62 THEN $condProb ";
        wfErrorLog( "spam given syntaxbasic probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of syntaxcomplex
        // # of syntaxcomplex / # of pages
        $prob = $stats['syntaxcomplex'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=63 THEN $prob ";
        wfErrorLog( "New syntaxcomplex probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given syntaxcomplex
        $opposite = $stats['spamandsyntaxcomplex'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=64 THEN $condProb ";
        wfErrorLog( "spam given syntaxcomplex probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of links0
        // # of links0 / # of pages
        $prob = $stats['links0'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=65 THEN $prob ";
        wfErrorLog( "New links0 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given links0
        $opposite = $stats['spamandlinks0'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=66 THEN $condProb ";
        wfErrorLog( "spam given links0 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of links5
        // # of links5 / # of pages
        $prob = $stats['links5'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=67 THEN $prob ";
        wfErrorLog( "New links5 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given links5
        $opposite = $stats['spamandlinks5'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=68 THEN $condProb ";
        wfErrorLog( "spam given links5 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of links20
        // # of lins20 / # of pages
        $prob = $stats['links20'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=69 THEN $prob ";
        wfErrorLog( "New links20 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given links20
        $opposite = $stats['spamandlinks20'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=70 THEN $condProb ";
        wfErrorLog( "spam given links20 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of links50
        // # of links50 / # of pages
        $prob = $stats['links50'] /  $stats['pages'];
        $sql .= " WHEN `ap_id`=71 THEN $prob ";
        wfErrorLog( "New links50 probability is " . $prob, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Probability of spam given links50
        $opposite = $stats['spamandlinks50'] / $stats['spam'];
        $condProb = ($probSpam * $opposite)/$prob;
        $sql .= " WHEN `ap_id`=72 THEN $condProb ";
        wfErrorLog( "spam given links50 probability is " . $condProb, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $sql .= "END, `ap_updated`=CURRENT_TIMESTAMP;"; // don't need a where for efficiency as we are updating every row

        // Hacky fix is hacky
        $sql = str_replace( 'NAN', '0', $sql );

        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $dbw->query( $sql );
    }

    /**
     * Retrieves the contents of the stats table
     *
     * @return array() containing all the stats
     */
    static function getStatistics() {
        $dbr = wfGetDB( DB_SLAVE );

        $res = $dbr->select(
            array( 'athena_stats' ),
            array( 'as_name, as_value' ),
            array( ),
            __METHOD__,
            array( )
        );

        $array = array();

        foreach ( $res as $row ) {
            $array[$row->as_name] = $row->as_value;
        }

        foreach( $array as $name=>$val ) {
            wfErrorLog( "Array has key " . $name . " and value " . $val, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        }

        return $array;
    }

    /**
     * Reinforce during page deletion
     *
     * @param $id integer
     */
    static function reinforceDelete( $id ) {
        // Get page details
        $res = AthenaHelper::getAthenaDetails( $id );

        AthenaHelper::updateStatsDeleted( $res );
        AthenaHelper::updateProbabilities();
    }

    /**
     * Prepare the log array without any data already
     *
     * @param $id integer
     * @return StdClass - database query results
     */
    static function getAthenaDetails( $id ) {

        $dbr = wfGetDB( DB_SLAVE );
        // Get data from the database
        $res = $dbr->selectRow(
            array( 'athena_log', 'athena_page_details' ),
            array( 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_timestamp',
                'al_user_age', 'al_link_percentage', 'al_syntax', 'al_language', 'al_wanted', 'al_deleted' ),
            array( 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ),
            __METHOD__,
            array()
        );

        return $res;
    }

    /**
     * Updates the stats table with information from this edit
     *
     * @param $res StdClass
     */
    static function updateStatsDeleted( $res ) {
        $dbw = wfGetDB( DB_SLAVE );

        // Start by reducing the number of not spam
        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`-1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'notspam';";
        $dbw->query( $sql );
        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Now increment spam and all the spamands

        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'spam'";

        if ( $res->al_language ) {
            $sql .= " OR `as_name`='spamanddifflang' ";
        } else {
            $sql .= " OR `as_name`='spamandsamelang' ";
        }

        if ( $res->al_deleted ) {
            $sql .= " OR `as_name`='spamanddeleted' ";
        } else {
            $sql .= " OR `as_name`='spamandnotdeleted' ";
        }

        if ( $res->al_wanted ) {
            $sql .= " OR `as_name`='spamandwanted' ";
        } else {
            $sql .= " OR `as_name`='spamandnotwanted' ";
        }

        $userAge = $res->al_user_age;
        if ( $userAge >= 0 ) {
            if ($userAge < 1) {
                $sql .= " OR `as_name`='spamanduser1' ";
            } else if ($userAge < 5) {
                $sql .= " OR `as_name`='spamanduser5' ";
            } else if ( $userAge < 30 ) {
                $sql .= " OR `as_name`='spamanduser30' ";
            } else if ( $userAge < 60 ) {
                $sql .= " OR `as_name`='spamanduser60' ";
            } else if ( $userAge < 60 * 12 ) {
                $sql .= " OR `as_name`='spamanduser12' ";
            } else if ( $userAge < 60 * 24 ) {
                $sql .= " OR `as_name`='spamanduser24' ";
            }  else {
                $sql .= " OR `as_name`='spamanduserother' ";
            }
        } else if ( $userAge != -1 ) {
            $sql .= " OR `as_name`='spamanduserothe' ";
        } else {
            $sql .= " OR `as_name`='spamandanon' ";
        }

        if ( strlen( $res->apd_title ) > 39 ) {
            $sql .= " OR `as_name`='spamandtitlelength' ";
        } else {
            $sql .= " OR `as_name`='spamandnottitlelength' ";
        }

        $namespace = $res->apd_namespace;
        if ( $namespace == 0 ) {
            $sql .= " OR `as_name`='spamandnsmain' ";
        } else if ( $namespace == 1 ) {
            $sql .= " OR `as_name`='spamandnstalk' ";
        } else if ( $namespace == 2 ) {
            $sql .= " OR `as_name`='spamandnsuser' ";
        } else if ( $namespace == 3 ) {
            $sql .= " OR `as_name`='spamandnsusertalk' ";
        } else {
            $sql .= " OR `as_name`='spamandnsother' ";
        }

        $syntax = $res->al_syntax;
        if ( $syntax === 1 ) {
            $sql .= " OR `as_name`='spamandsyntaxbasic' ";
        } else if ( $syntax === 2 ) {
            $sql .= " OR `as_name`='spamandsyntaxcomplex' ";
        } else if ( $syntax === 3 ) {
            $sql .= " OR `as_name`='spamandbrokenspambot' ";
        } else {
            $sql .= " OR `as_name`='spamandsyntaxnone' ";
        }

        $percentage = $res->al_link_percentage;
        // TODO why is this named like this...
        if ( $percentage == 0 ) {
            $sql .= " OR `as_name`='spamandlinks0' ";
        } else if ( $percentage > 0 && $percentage < 0.1 ) {
            $sql .= " OR `as_name`='spamandlinks5' ";
        } else if ( $percentage >= 0.1 && $percentage <= 0.35 ) {
            $sql .= " OR `as_name`='spamandlinks20' ";
        } else {
            $sql .= " OR `as_name`='spamandlinks50' ";
        }
        $sql .= ";";

        $dbw->query( $sql );
        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
    }

    /**
     * Reinforce during reinforcement page creation
     *
     * @param $id integer
     */
    static function reinforceCreate( $id ) {
        // Get page details
        $res = AthenaHelper::getAthenaDetails( $id );

        AthenaHelper::updateStatsCreated( $res );
        AthenaHelper::updateProbabilities();
    }

    /**
     * Updates the stats table with information from this edit
     *
     * @param $res StdClass
     */
    static function updateStatsCreated( $res ) {
        $dbw = wfGetDB( DB_SLAVE );

        // Start by reducing the number of not spam
        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'notspam';";
        $dbw->query( $sql );
        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        // Now increment spam and all the spamands

        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`-1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'spam'";

        if ( $res->al_language ) {
            $sql .= " OR `as_name`='spamanddifflang' ";
        } else {
            $sql .= " OR `as_name`='spamandsamelang' ";
        }

        if ( $res->al_deleted ) {
            $sql .= " OR `as_name`='spamanddeleted' ";
        } else {
            $sql .= " OR `as_name`='spamandnotdeleted' ";
        }

        if ( $res->al_wanted ) {
            $sql .= " OR `as_name`='spamandwanted' ";
        } else {
            $sql .= " OR `as_name`='spamandnotwanted' ";
        }

        $userAge = $res->al_user_age;
        if ( $userAge >= 0 ) {
            if ($userAge < 1) {
                $sql .= " OR `as_name`='spamanduser1' ";
            } else if ($userAge < 5) {
                $sql .= " OR `as_name`='spamanduser5' ";
            } else if ( $userAge < 30 ) {
                $sql .= " OR `as_name`='spamanduser30' ";
            } else if ( $userAge < 60 ) {
                $sql .= " OR `as_name`='spamanduser60' ";
            } else if ( $userAge < 60 * 12 ) {
                $sql .= " OR `as_name`='spamanduser12' ";
            } else if ( $userAge < 60 * 24 ) {
                $sql .= " OR `as_name`='spamanduser24' ";
            }  else {
                $sql .= " OR `as_name`='spamanduserother' ";
            }
        } else if ( $userAge != -1 ) {
            $sql .= " OR `as_name`='spamanduserothe' ";
        } else {
            $sql .= " OR `as_name`='spamandanon' ";
        }

        if ( strlen( $res->apd_title ) > 39 ) {
            $sql .= " OR `as_name`='spamandtitlelength' ";
        } else {
            $sql .= " OR `as_name`='spamandnottitlelength' ";
        }

        $namespace = $res->apd_namespace;
        if ( $namespace == 0 ) {
            $sql .= " OR `as_name`='spamandnsmain' ";
        } else if ( $namespace == 1 ) {
            $sql .= " OR `as_name`='spamandnstalk' ";
        } else if ( $namespace == 2 ) {
            $sql .= " OR `as_name`='spamandnsuser' ";
        } else if ( $namespace == 3 ) {
            $sql .= " OR `as_name`='spamandnsusertalk' ";
        } else {
            $sql .= " OR `as_name`='spamandnsother' ";
        }

        $syntax = $res->al_syntax;
        if ( $syntax === 1 ) {
            $sql .= " OR `as_name`='spamandsyntaxbasic' ";
        } else if ( $syntax === 2 ) {
            $sql .= " OR `as_name`='spamandsyntaxcomplex' ";
        } else if ( $syntax === 3 ) {
            $sql .= " OR `as_name`='spamandbrokenspambot' ";
        } else {
            $sql .= " OR `as_name`='spamandsyntaxnone' ";
        }

        $percentage = $res->al_link_percentage;
        // TODO why is this named like this...
        if ( $percentage == 0 ) {
            $sql .= " OR `as_name`='spamandlinks0' ";
        } else if ( $percentage > 0 && $percentage < 0.1 ) {
            $sql .= " OR `as_name`='spamandlinks5' ";
        } else if ( $percentage >= 0.1 && $percentage <= 0.35 ) {
            $sql .= " OR `as_name`='spamandlinks20' ";
        } else {
            $sql .= " OR `as_name`='spamandlinks50' ";
        }
        $sql .= ";";

        $dbw->query( $sql );
        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
    }

    static function convertISOCode( $code ) {
        $array = array(
            'aar' => 'aa',
            'abk' => 'ab',
            'ace' => 'ace',
            'ady' => 'ady',
            'aeb' => 'aeb',
            'afr' => 'af',
            'aka' => 'ak',
            'aln' => 'aln',
            'amh' => 'am',
            'arg' => 'an',
            'ang' => 'ang',
            'anp' => 'anp',
            'ara' => 'ar',
            'arc' => 'arc',
            'arn' => 'arn',
            'arq' => 'arq',
            'ary' => 'ary',
            'arz' => 'arz',
            'asm' => 'as',
            'ase' => 'ase',
            'ast' => 'ast',
            'ava' => 'av',
            'avk' => 'avk',
            'awa' => 'awa',
            'aym' => 'ay',
            'aze' => 'az',
            'azb' => 'azb',
            'bak' => 'ba',
            'bar' => 'bar',
            'bbc' => 'bbc',
            'bcc' => 'bcc',
            'bcl' => 'bcl',
            'bel' => 'be',
            'bul' => 'bg',
            'bgn' => 'bgn',
            'bih' => 'bh',
            'bho' => 'bho',
            'bis' => 'bi',
            'bjn' => 'bjn',
            'bam' => 'bm',
            'ben' => 'bn',
            'bod' => 'bo',
            'bpy' => 'bpy',
            'bqi' => 'bqi',
            'bre' => 'br',
            'brh' => 'brh',
            'bos' => 'bs',
            'bto' => 'bto',
            'bug' => 'bug',
            'bxr' => 'bxr',
            'cat' => 'ca',
            'cbk' => 'cbk-zam',
            'cdo' => 'cdo',
            'che' => 'ce',
            'ceb' => 'ceb',
            'cha' => 'ch',
            'cho' => 'cho',
            'chr' => 'chr',
            'chy' => 'chy',
            'ckb' => 'ckb',
            'cos' => 'co',
            'cps' => 'cps',
            'cre' => 'cr',
            'crh' => 'crh',
            'ces' => 'cs',
            'csb' => 'csb',
            'chu' => 'cu',
            'chv' => 'cv',
            'cym' => 'cy',
            'dan' => 'da',
            'deu' => 'de',
            'diq' => 'diq',
            'dsb' => 'dsb',
            'dtp' => 'dtp',
            'dty' => 'dty',
            'div' => 'dv',
            'dzo' => 'dz',
            'ewe' => 'ee',
            'egl' => 'egl',
            'ell' => 'el',
            'eml' => 'eml',
            'eng' => 'en',
            'epo' => 'eo',
            'spa' => 'es',
            'est' => 'et',
            'eus' => 'eu',
            'ext' => 'ext',
            'fas' => 'fa',
            'ful' => 'ff',
            'fin' => 'fi',
            'fit' => 'fit',
            'fij' => 'fj',
            'fao' => 'fo',
            'fra' => 'fr',
            'frc' => 'frc',
            'frp' => 'frp',
            'frr' => 'frr',
            'fur' => 'fur',
            'fry' => 'fy',
            'gle' => 'ga',
            'gag' => 'gag',
            'gan' => 'gan',
            'gla' => 'gd',
            'glg' => 'gl',
            'glk' => 'glk',
            'grn' => 'gn',
            'gom' => 'gom',
            'got' => 'got',
            'grc' => 'grc',
            'gsw' => 'gsw',
            'guj' => 'gu',
            'glv' => 'gv',
            'hau' => 'ha',
            'hak' => 'hak',
            'haw' => 'haw',
            'heb' => 'he',
            'hin' => 'hi',
            'hif' => 'hif',
            'hil' => 'hil',
            'hmo' => 'ho',
            'hrv' => 'hr',
            'hrx' => 'hrx',
            'hsb' => 'hsb',
            'hat' => 'ht',
            'hun' => 'hu',
            'hye' => 'hy',
            'her' => 'hz',
            'ina' => 'ia',
            'ind' => 'id',
            'ile' => 'ie',
            'ibo' => 'ig',
            'iii' => 'ii',
            'ipk' => 'ik',
            'ike' => 'ike-cans',
            'ilo' => 'ilo',
            'inh' => 'inh',
            'ido' => 'io',
            'isl' => 'is',
            'ita' => 'it',
            'iku' => 'iu',
            'jpn' => 'ja',
            'jam' => 'jam',
            'jbo' => 'jbo',
            'jut' => 'jut',
            'jav' => 'jv',
            'kat' => 'ka',
            'kaa' => 'kaa',
            'kab' => 'kab',
            'kbd' => 'kbd',
            'kon' => 'kg',
            'khw' => 'khw',
            'kik' => 'ki',
            'kiu' => 'kiu',
            'kua' => 'kj',
            'kaz' => 'kk',
            'kal' => 'kl',
            'khm' => 'km',
            'kan' => 'kn',
            'kor' => 'ko',
            'koi' => 'koi',
            'kau' => 'kr',
            'krc' => 'krc',
            'kri' => 'kri',
            'krj' => 'krj',
            'kas' => 'ks',
            'ksh' => 'ksh',
            'kur' => 'ku',
            'kom' => 'kv',
            'cor' => 'kw',
            'kir' => 'ky',
            'lat' => 'la',
            'lad' => 'lad',
            'ltz' => 'lb',
            'lbe' => 'lbe',
            'lez' => 'lez',
            'lfn' => 'lfn',
            'lug' => 'lg',
            'lim' => 'li',
            'lij' => 'lij',
            'liv' => 'liv',
            'lmo' => 'lmo',
            'lin' => 'ln',
            'lao' => 'lo',
            'lrc' => 'lrc',
            'loz' => 'loz',
            'lit' => 'lt',
            'ltg' => 'ltg',
            'lus' => 'lus',
            'luz' => 'luz',
            'lav' => 'lv',
            'lzh' => 'lzh',
            'lzz' => 'lzz',
            'mai' => 'mai',
            'mdf' => 'mdf',
            'mlg' => 'mg',
            'mah' => 'mh',
            'mhr' => 'mhr',
            'mri' => 'mi',
            'min' => 'min',
            'mkd' => 'mk',
            'mal' => 'ml',
            'khk' => 'mn',
            'mol' => 'mo',
            'mar' => 'mr',
            'mrj' => 'mrj',
            'msa' => 'ms',
            'mlt' => 'mt',
            'mus' => 'mus',
            'mwl' => 'mwl',
            'mya' => 'my',
            'myv' => 'myv',
            'mzn' => 'mzn',
            'nau' => 'na',
            'naz' => 'nah',
            'nan' => 'nan',
            'nap' => 'nap',
            'nob' => 'nb',
            'nds' => 'nds',
            'nep' => 'ne',
            'new' => 'new',
            'ndo' => 'ng',
            'niu' => 'niu',
            'nld' => 'nl',
            'nno' => 'nn',
            'nor' => 'no',
            'nov' => 'nov',
            'nrm' => 'nrm',
            'nso' => 'nso',
            'nav' => 'nv',
            'nya' => 'ny',
            'oci' => 'oc',
            'olo' => 'olo',
            'orm' => 'om',
            'ori' => 'or',
            'oss' => 'os',
            'pan' => 'pa',
            'pag' => 'pag',
            'pam' => 'pam',
            'pap' => 'pap',
            'pcd' => 'pcd',
            'pdc' => 'pdc',
            'pdt' => 'pdt',
            'plf' => 'pfl',
            'pli' => 'pi',
            'pih' => 'pih',
            'pol' => 'pl',
            'pms' => 'pms',
            'pnb' => 'pnb',
            'pnt' => 'pnt',
            'prg' => 'prg',
            'pus' => 'ps',
            'por' => 'pt',
            'que' => 'qu',
            'quq' => 'qug',
            'rgn' => 'rgn',
            'rif' => 'rif',
            'roh' => 'rm',
            'rmy' => 'rmy',
            'run' => 'rn',
            'ron' => 'ro',
            'rus' => 'ru',
            'rue' => 'rue',
            'rup' => 'rup',
            'ruq' => 'ruq',
            'kin' => 'rw',
            'san' => 'sa',
            'sah' => 'sah',
            'sat' => 'sat',
            'srd' => 'sc',
            'scn' => 'scn',
            'sco' => 'sco',
            'snd' => 'sd',
            'sdc' => 'sdc',
            'sdh' => 'sdh',
            'sme' => 'se',
            'sei' => 'sei',
            'ses' => 'ses',
            'sag' => 'sg',
            'sgs' => 'sgs',
            'hbs' => 'sh',
            'shi' => 'shi',
            'sin' => 'si',
            'slk' => 'sk',
            'slv' => 'sl',
            'sli' => 'sli',
            'smo' => 'sm',
            'sma' => 'sma',
            'sna' => 'sn',
            'som' => 'so',
            'sqi' => 'sq',
            'srp' => 'sr',
            'srn' => 'srn',
            'ssw' => 'ss',
            'sot' => 'st',
            'stq' => 'stq',
            'sun' => 'su',
            'swe' => 'sv',
            'swa' => 'sw',
            'szl' => 'szl',
            'tam' => 'ta',
            'tcy' => 'tcy',
            'tel' => 'te',
            'tet' => 'tet',
            'tgk' => 'tg',
            'tha' => 'th',
            'tir' => 'ti',
            'tuk' => 'tk',
            'tgl' => 'tl',
            'tly' => 'tly',
            'tsn' => 'tn',
            'ton' => 'to',
            'mis' => 'tokipona',
            'tpi' => 'tpi',
            'tur' => 'tr',
            'tru' => 'tru',
            'tso' => 'ts',
            'tat' => 'tt',
            'tum' => 'tum',
            'twi' => 'tw',
            'tah' => 'ty',
            'tyv' => 'tyv',
            'tzm' => 'tzm',
            'udm' => 'udm',
            'uig' => 'ug',
            'ukr' => 'uk',
            'urd' => 'ur',
            'uzb' => 'uz',
            'ven' => 've',
            'vec' => 'vec',
            'vep' => 'vep',
            'vie' => 'vi',
            'vls' => 'vls',
            'vmf' => 'vmf',
            'vol' => 'vo',
            'vot' => 'vot',
            'vro' => 'vro',
            'wln' => 'wa',
            'war' => 'war',
            'wol' => 'wo',
            'wuu' => 'wuu',
            'xal' => 'xal',
            'xho' => 'xh',
            'xmf' => 'xmf',
            'yid' => 'yi',
            'yor' => 'yo',
            'yue' => 'yue',
            'zha' => 'za',
            'zea' => 'zea',
            'zho' => 'zh',
            'cmn' => 'zh-hans',
            'zul' => 'zu'
        );

        return $array[$code];
    }
}
