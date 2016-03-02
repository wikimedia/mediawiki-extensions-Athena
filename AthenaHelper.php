<?php


/**
 * Class AthenaHelper
 *
 * Various helper functions for Athena
 */
class AthenaHelper
{

    /**
     * Log information about the attempted page creation
     *
     * @param $logArray array
     * @param $detailsArray array
     * @param $calcArray array
     */
    static function logAttempt( $logArray, $detailsArray, $calcArray ) {
        global $wgAthenaTraining;
        $dbw = wfGetDB( DB_MASTER );

        $dbw->insert( 'athena_log', $logArray, __METHOD__, null );

        // Get last inserted ID
        // TODO could do with a MediaWiki method
        $sql = 'select LAST_INSERT_ID() as id;';
        $res = $dbw->query( $sql );
        $row = $dbw->fetchObject( $res );
        $id = $row->id;

        $detailsArray['al_id'] = $id;

        try {
            $dbw->insert( 'athena_page_details', $detailsArray, __METHOD__, null );
            if( !$wgAthenaTraining ) {
                $calcArray['al_id'] = $id;
                $dbw->insert( 'athena_calculations', $calcArray, __METHOD__, null );
            }
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
        global $wgAthenaSpamThreshold, $wgAthenaTraining;

        if ( $deleted === false ) {
            $deleted = 0;
        }

        if ( $wanted === false ) {
            $wanted = 0;
        }

        $insertArray = array( 'al_id' => NULL, 'al_value' => $prob, 'al_user_age' => $userAge,
            'al_links' => $links, 'al_link_percentage' => $linkPercentage, 'al_syntax' => $syntax,
            'al_wanted' => $wanted, 'al_deleted' => $deleted );

        // Language could be null
        if ( $language != '' ) {
            if( $language === false ) {
                $language = 0;
            }
            $insertArray['al_language'] = $language;
        } else {
            $insertArray['al_language'] = 0;
        }

		if( !$wgAthenaTraining ) {
			if ( $prob > $wgAthenaSpamThreshold ) {
				$insertArray['al_success'] = 0;
			} else {
				$insertArray['al_success'] = 1;
			}
		} else {
			$insertArray['al_success'] = 2;
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

        $insertArray['apd_language'] = $dbw->strencode(Language::fetchLanguageName($language));

        return $insertArray;
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
        global $wgUser, $wgAthenaTraining;

        // Get title
        $titleObj = $editPage->getTitle();
        $title = $titleObj->getTitleValue()->getText();

        // Get filter results
        $diffLang = AthenaFilters::differentLanguage( $text );
        $deleted = AthenaFilters::wasDeleted( $titleObj );
        $wanted = AthenaFilters::isWanted( $titleObj );
        $userAge = AthenaFilters::userAge();
        $titleLength = AthenaFilters::titleLength( $titleObj );
        $namespace = AthenaFilters::getNamespace( $titleObj );
        $syntaxType = AthenaFilters::syntaxType( $text );
        $linksPercentage = AthenaFilters::linkPercentage( $text );

        // If not training, work out probabilities
        if( !$wgAthenaTraining ) {
            // Array to store probability info
            $probabilityArray = array();

            // Parts of our final calculation
            // P(S|A,B,...,F) = P(A|S)*P(B|S)*...*P(F|S)*P(S)
            //                  -----------------------------
            //                      P(A)*P(B)*...*P(F)
            $numerator = null;
            $denominator = null;

            // Get the statistics table's contents
            $stats = AthenaHelper::getStatistics();

            // Calculate probability of spam
            AthenaHelper::calculateProbability_Spam($stats, $probabilityArray);

            $numerator = $probabilityArray['ac_p_spam'];

            /* start different language */
            AthenaHelper::calculateProbability_Language($diffLang, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_langgivenspam'];
            $denominator = $probabilityArray['ac_p_lang'];
            /* end different language */

            /* start deleted */

            AthenaHelper::calculateProbability_Deleted($deleted, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_deletedgivenspam'];
            $denominator *= $probabilityArray['ac_p_deleted'];
            /* end deleted */

            /* start wanted */
            AthenaHelper::calculateProbability_Wanted($wanted, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_wantedgivenspam'];
            $denominator *= $probabilityArray['ac_p_wanted'];
            /* end wanted */

            /* start user type */
            AthenaHelper::calculateProbability_User($userAge, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_usergivenspam'];
            $denominator *= $probabilityArray['ac_p_user'];
            /* end user type */

            /* start title length */
            AthenaHelper::calculateProbability_Length($titleLength, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_titlelengthgivenspam'];
            $denominator *= $probabilityArray['ac_p_titlelength'];
            /* end title length */

            /* start namespace */
            AthenaHelper::calculateProbability_Namespace($namespace, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_namespacegivenspam'];
            $denominator *= $probabilityArray['ac_p_namespace'];
            /* end namespace */

            /* start syntax */
            AthenaHelper::calculateProbability_Syntax($syntaxType, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_syntaxgivenspam'];
            $denominator *= $probabilityArray['ac_p_syntax'];
            /* end syntax */

            /* start links */
            AthenaHelper::calculateProbability_Links($linksPercentage, $stats, $probabilityArray);

            $numerator *= $probabilityArray['ac_p_linksgivenspam'];
            $denominator *= $probabilityArray['ac_p_links'];
            /* end links */

            $prob = $numerator / $denominator;

            wfErrorLog("------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log');
            wfErrorLog("Numerator is $numerator", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log');
            wfErrorLog("Denominator is $denominator", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log');
            wfErrorLog("Probability is $prob", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log');
        } else {
            // al_value is double unsigned not null, so let's just set to 0 and let the code ignore it later on
            $prob = 0;
            $probabilityArray = null;
        }
        $links = AthenaFilters::numberOfLinks( $text );

        $logArray = AthenaHelper::prepareLogArray( $prob, $userAge, $links, $linksPercentage, $syntaxType, $diffLang, $deleted, $wanted );
        $detailsArray = AthenaHelper::preparePageDetailsArray( $namespace, $title, $text, $summary, $wgUser->getId() );

        AthenaHelper::logAttempt( $logArray, $detailsArray, $probabilityArray );
        AthenaHelper::updateStats( $logArray, $titleObj );

        return $prob;
    }

    /**
     * Calculates the probability of an article being spam
     *
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Spam( &$stats, &$probabilityArray  ) {
        $spam = $stats['spam'];
        $pages = $stats['pages'];

        $probSpam = $spam/$pages;

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of spam is $spam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of spam is $probSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_spam'] = $probSpam;
    }

    /**
     * Calculates the probability related to the different language filter
     *
     * @param $diffLang bool
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     * @param &$calcArray array stores details about calculations
     */
    static function calculateProbability_Language( $diffLang, &$stats, &$probabilityArray ) {
        $var = 'difflang';
        // Let's treat null as false for simplicity
        if ( !$diffLang ) {
            $var = 'samelang';
        }

        $lang = $stats[$var];
        $pages = $stats['pages'];
        $langAndSpam = $stats['spamand' . $var];

        $probLang = $lang/$pages;
        $probLangAndSpam = $langAndSpam/$pages;
        $probLangGivenSpam = $probLangAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Lang type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of lang is $lang", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of lang and spam is $langAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of lang is $probLang", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of lang and spam is $probLangAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of lang given spam is $probLangGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_lang'] = $probLang;
        $probabilityArray['ac_p_langandspam'] = $probLangAndSpam;
        $probabilityArray['ac_p_langgivenspam'] = $probLangGivenSpam;
    }

    /**
     * Calculates the probability related to the deleted filter
     *
     * @param $wasDeleted bool
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Deleted( $wasDeleted, &$stats, &$probabilityArray ) {
        $var = 'deleted';
        // Let's treat null as false for simplicity
        if ( !$wasDeleted ) {
            $var = 'notdeleted';
        }

        $deleted = $stats[$var];
        $pages = $stats['pages'];
        $deletedAndSpam = $stats['spamand' . $var];

        $probDeleted = $deleted/$pages;
        $probDeletedAndSpam = $deletedAndSpam/$pages;
        $probDeletedGivenSpam = $probDeletedAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Delete type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of deleted is $deleted", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of deleted and spam is $deletedAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of deleted is $probDeleted", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of deleted and spam is $probDeletedAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of deleted given spam is $probDeletedGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_deleted'] = $probDeleted;
        $probabilityArray['ac_p_deletedandspam'] = $probDeletedAndSpam;
        $probabilityArray['ac_p_deletedgivenspam'] = $probDeletedGivenSpam;
    }

    /**
     * Calculates the probability related to the wanted filter
     *
     * @param $isWanted bool
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Wanted( $isWanted, &$stats, &$probabilityArray ) {
        $var = 'wanted';
        // Let's treat null as false for simplicity
        if ( !$isWanted ) {
            $var = 'notwanted';
        }

        $wanted = $stats[$var];
        $pages = $stats['pages'];
        $wantedAndSpam = $stats['spamand' . $var];

        $probWanted = $wanted/$pages;
        $probWantedAndSpam = $wantedAndSpam/$pages;
        $probWantedGivenSpam = $probWantedAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Wanted type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of wanted is $wanted", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of wanted and spam is $wantedAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of wanted is $probWanted", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of wanted and spam is $probWantedAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of wanted given spam is $probWantedGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_wanted'] = $probWanted;
        $probabilityArray['ac_p_wantedandspam'] = $probWantedAndSpam;
        $probabilityArray['ac_p_wantedgivenspam'] = $probWantedGivenSpam;
    }

    /**
     * Calculates the probability related to the user type filter
     *
     * @param $userAge int
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_User( $userAge, &$stats, &$probabilityArray ) {

        $var = 'anon';
        if ( $userAge >= 0 ) {
            if ( $userAge < 60 )
                $var = 'user1';
            else if ( $userAge < 5 * 60 )
                $var = 'user5';
            else if ( $userAge < 30 * 60 )
                $var = 'user30';
            else if ( $userAge < 60 * 60 )
                $var = 'user60';
            else if ( $userAge < 60 * 60 * 12 )
                $var = 'user12';
            else if ( $userAge < 60 * 60 * 24 )
                $var = 'user24';
            else
                $var = 'userother';
        } else {
            if ( $userAge != -1 ) {
                // -2 is no registration details - we shouldn't have that problem though
                // anything bigger will be imported content, so let's just assume they were greater than a day and do other
                $var = 'userother';
            }
        }

        $user = $stats[$var];
        $pages = $stats['pages'];
        $userAndSpam = $stats['spamand' . $var];

        $probUser = $user/$pages;
        $probUserAndSpam = $userAndSpam/$pages;
        $probUserGivenSpam = $probUserAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "User type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of user is $user", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of user and spam is $userAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of user is $probUser", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of user and spam is $probUserAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of user given spam is $probUserGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_user'] = $probUser;
        $probabilityArray['ac_p_userandspam'] = $probUserAndSpam;
        $probabilityArray['ac_p_usergivenspam'] = $probUserGivenSpam;
    }

    /**
     * Calculates the probability related to the title length filter
     *
     * @param $length int
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Length( $length, &$stats, &$probabilityArray ) {
        $var = 'nottitlelength';
        // Let's treat null as false for simplicity
        if ( $length > 39 ) {
            $var = 'titlelength';
        }

        $titleLength = $stats[$var];
        $pages = $stats['pages'];
        $titleLengthAndSpam = $stats['spamand' . $var];

        $probTitleLength = $titleLength/$pages;
        $probTitleLengthAndSpam = $titleLengthAndSpam/$pages;
        $probTitleLengthGivenSpam = $probTitleLengthAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Title length type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of title length is $titleLength", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of title length and spam is $titleLengthAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of title length is $probTitleLength", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of title length and spam is $probTitleLengthAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of title length given spam is $probTitleLengthGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_titlelength'] = $probTitleLength;
        $probabilityArray['ac_p_titlelengthandspam'] = $probTitleLengthAndSpam;
        $probabilityArray['ac_p_titlelengthgivenspam'] = $probTitleLengthGivenSpam;
    }

    /**
     * Calculates the probability related to the namespace filter
     *
     * @param $namespace int
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Namespace( $namespace, &$stats, &$probabilityArray ) {
        $var = 'nsother';
        if ($namespace === 0)
            $var = 'nsmain';
        else if ($namespace === 1)
            $var = 'nstalk';
        else if ($namespace === 2)
            $var = 'nsuser';
        else if ($namespace === 3)
            $var = 'nsusertalk';
        else if ( $namespace == 6 )
            $var = 'nsfile';

        $namespace = $stats[$var];
        $pages = $stats['pages'];
        $namespaceAndSpam = $stats['spamand' . $var];

        $probNamespace = $namespace/$pages;
        $probNamespaceAndSpam = $namespaceAndSpam/$pages;
        $probNamespaceGivenSpam = $probNamespaceAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Namespace type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of namespace is $namespace", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of namespace and spam is $namespaceAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of namespace is $probNamespace", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of namespace and spam is $probNamespaceAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of namespace given spam is $probNamespaceGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_namespace'] = $probNamespace;
        $probabilityArray['ac_p_namespaceandspam'] = $probNamespaceAndSpam;
        $probabilityArray['ac_p_namespacegivenspam'] = $probNamespaceGivenSpam;
    }

    /**
     * Calculates the probability related to the syntax filter
     *
     * @param $type int
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Syntax( $type, &$stats, &$probabilityArray ) {

        $var = 'syntaxnone';
        if ( $type === 1 )
            $var = 'syntaxbasic';
        else if ( $type === 2 )
            $var = 'syntaxcomplex';
        else if ( $type === 3 )
            $var = 'brokenspambot';

        $syntax = $stats[$var];
        $pages = $stats['pages'];
        $syntaxAndSpam = $stats['spamand' . $var];

        $probSyntax = $syntax/$pages;
        $probSyntaxAndSpam = $syntaxAndSpam/$pages;
        $probSyntaxGivenSpam = $probSyntaxAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Syntax type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of syntax is $syntax", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of syntax and spam is $syntaxAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of syntax is $probSyntax", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of syntax and spam is $probSyntaxAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of syntax given spam is $probSyntaxGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_syntax'] = $probSyntax;
        $probabilityArray['ac_p_syntaxandspam'] = $probSyntaxAndSpam;
        $probabilityArray['ac_p_syntaxgivenspam'] = $probSyntaxGivenSpam;
    }

    /**
     * Calculates the probability related to the link filter
     *
     * @param $percentage double
     * @param &$stats array contents of the athena_stats table
     * @param &$probabilityArray array stores details about probabilities calculated
     */
    static function calculateProbability_Links( $percentage, &$stats, &$probabilityArray ) {
        $var = 'links0';
        if ( $percentage > 0 && $percentage < 0.1 )
            $var = 'links5';
        else if ( $percentage >= 0.1 && $percentage <= 0.35 )
            $var = 'links20';
        else if ( $percentage > 0.35 )
            $var = 'links50';

        $links = $stats[$var];
        $pages = $stats['pages'];
        $linksAndSpam = $stats['spamand' . $var];

        $probLinks = $links/$pages;
        $probLinksAndSpam = $linksAndSpam/$pages;
        $probLinksGivenSpam = $probLinksAndSpam/$probabilityArray['ac_p_spam'];

        wfErrorLog( "------------------------------------------------", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Links type is $var ", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of links is $links", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of pages is $pages", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Number of links and spam is $linksAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of links is $probLinks", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of links and spam is $probLinksAndSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );
        wfErrorLog( "Probability of links given spam is $probLinksGivenSpam", 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        $probabilityArray['ac_p_links'] = $probLinks;
        $probabilityArray['ac_p_linksandspam'] = $probLinksAndSpam;
        $probabilityArray['ac_p_linksgivenspam'] = $probLinksGivenSpam;
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
        global $wgAthenaSpamThreshold, $wgAthenaTraining;
        $dbw = wfGetDB( DB_SLAVE );

        // TODO not the best way but get me incrementing with the better way and I'll use it
        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'pages'";

        if( !$wgAthenaTraining ) {
            if ($array['al_value'] > $wgAthenaSpamThreshold) {
                $spam = true;
                $sql .= " OR `as_name`='spam' ";
            } else {
                $spam = false;
                $sql .= " OR `as_name`='notspam' ";
            }
        } else {
            $spam = false;
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
              if ($userAge < 1 * 60 ) {
                  $sql .= " OR `as_name`='user1' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser1' ";
                  }
              } else if ($userAge < 5 * 60 ) {
                  $sql .= " OR `as_name`='user5' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser5' ";
                  }
              } else if ( $userAge < 30 * 60 ) {
                  $sql .= " OR `as_name`='user30' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser30' ";
                  }
              } else if ( $userAge < 60 * 60 ) {
                  $sql .= " OR `as_name`='user60' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser60' ";
                  }
              } else if ( $userAge < 60 * 12 * 60 ) {
                $sql .= " OR `as_name`='user12' ";
                  if( $spam ) {
                      $sql .= " OR `as_name`='spamanduser12' ";
                  }
              } else if ( $userAge < 60 * 24 * 60 ) {
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
            if ($spam) {
                $sql .= " OR `as_name`='spamandnsusertalk' ";
            }
        } else if ( $namespace == 6 ) {
            $sql .= " OR `as_name`='nsfile' ";
            if( $spam ) {
                $sql .= " OR `as_name`='spamandnsfile' ";
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

       // foreach( $array as $name=>$val ) {
       //     wfErrorLog( "Array has key " . $name . " and value " . $val, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

       // }

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

        AthenaHelper::updateStatsDeleted( $res, false );
    }

    /**
     * Reinforce during page deletion
     *
     * @param $id integer
     */
    static function reinforceDeleteTraining( $id ) {
        // Get page details
        $res = AthenaHelper::getAthenaDetails( $id );

        AthenaHelper::updateStatsDeleted( $res, true );
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
     * @param $training bool are we in training mode?
     */
    static function updateStatsDeleted( $res, $training ) {
        $dbw = wfGetDB( DB_SLAVE );

        // Start by reducing the number of not spam
        if( !$training ) {
            $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`-1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'notspam';";
            $dbw->query($sql);
            wfErrorLog($sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log');
        }

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
            if ($userAge < 1 * 60 ) {
                $sql .= " OR `as_name`='spamanduser1' ";
            } else if ($userAge < 5 * 60 ) {
                $sql .= " OR `as_name`='spamanduser5' ";
            } else if ( $userAge < 30 * 60 ) {
                $sql .= " OR `as_name`='spamanduser30' ";
            } else if ( $userAge < 60 * 60 ) {
                $sql .= " OR `as_name`='spamanduser60' ";
            } else if ( $userAge < 60 * 12 * 60 ) {
                $sql .= " OR `as_name`='spamanduser12' ";
            } else if ( $userAge < 60 * 24 * 60 ) {
                $sql .= " OR `as_name`='spamanduser24' ";
            }  else {
                $sql .= " OR `as_name`='spamanduserother' ";
            }
        } else if ( $userAge != -1 ) {
            $sql .= " OR `as_name`='spamanduserother' ";
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
        } else if ( $namespace == 6 ) {
            $sql .= " OR `as_name`='spamandnsfile' ";
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

        AthenaHelper::updateStatsCreated( $res, false );
    }

    /**
     * Reinforce during reinforcement page creation
     *
     * @param $id integer
     */
    static function reinforceCreateTraining( $id ) {
        // Get page details
        $res = AthenaHelper::getAthenaDetails( $id );

        AthenaHelper::updateStatsCreated( $res, true );
    }
    /**
     * Updates the stats table with information from this edit
     *
     * @param $res StdClass
     * @param $training bool are we in training mode?
     */
    static function updateStatsCreated( $res, $training ) {
        $dbw = wfGetDB( DB_SLAVE );

        // Start by increasing the number of not spam
        $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'notspam';";
        $dbw->query( $sql );
        wfErrorLog( $sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log' );

        if ( !$training ) {
            // Now decrement spam and all the spamands
            $sql = "UPDATE `athena_stats` SET `as_value`=`as_value`-1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'spam'";

            if ($res->al_language) {
                $sql .= " OR `as_name`='spamanddifflang' ";
            } else {
                $sql .= " OR `as_name`='spamandsamelang' ";
            }

            if ($res->al_deleted) {
                $sql .= " OR `as_name`='spamanddeleted' ";
            } else {
                $sql .= " OR `as_name`='spamandnotdeleted' ";
            }

            if ($res->al_wanted) {
                $sql .= " OR `as_name`='spamandwanted' ";
            } else {
                $sql .= " OR `as_name`='spamandnotwanted' ";
            }

            $userAge = $res->al_user_age;
            if ($userAge >= 0) {
                if ($userAge < 1 * 60 ) {
                    $sql .= " OR `as_name`='spamanduser1' ";
                } else if ($userAge < 5 * 60 ) {
                    $sql .= " OR `as_name`='spamanduser5' ";
                } else if ($userAge < 30 * 60 ) {
                    $sql .= " OR `as_name`='spamanduser30' ";
                } else if ($userAge < 60 * 60 ) {
                    $sql .= " OR `as_name`='spamanduser60' ";
                } else if ($userAge < 60 * 12 * 60 ) {
                    $sql .= " OR `as_name`='spamanduser12' ";
                } else if ($userAge < 60 * 24 * 60 ) {
                    $sql .= " OR `as_name`='spamanduser24' ";
                } else {
                    $sql .= " OR `as_name`='spamanduserother' ";
                }
            } else if ($userAge != -1) {
                $sql .= " OR `as_name`='spamanduserothe' ";
            } else {
                $sql .= " OR `as_name`='spamandanon' ";
            }

            if (strlen($res->apd_title) > 39) {
                $sql .= " OR `as_name`='spamandtitlelength' ";
            } else {
                $sql .= " OR `as_name`='spamandnottitlelength' ";
            }

            $namespace = $res->apd_namespace;
            if ($namespace == 0) {
                $sql .= " OR `as_name`='spamandnsmain' ";
            } else if ($namespace == 1) {
                $sql .= " OR `as_name`='spamandnstalk' ";
            } else if ($namespace == 2) {
                $sql .= " OR `as_name`='spamandnsuser' ";
            } else if ($namespace == 3) {
                $sql .= " OR `as_name`='spamandnsusertalk' ";
            } else if ($namespace == 6) {
                $sql .= " OR `as_name`='spamandnsfile' ";
            } else {
                $sql .= " OR `as_name`='spamandnsother' ";
            }

            $syntax = $res->al_syntax;
            if ($syntax === 1) {
                $sql .= " OR `as_name`='spamandsyntaxbasic' ";
            } else if ($syntax === 2) {
                $sql .= " OR `as_name`='spamandsyntaxcomplex' ";
            } else if ($syntax === 3) {
                $sql .= " OR `as_name`='spamandbrokenspambot' ";
            } else {
                $sql .= " OR `as_name`='spamandsyntaxnone' ";
            }

            $percentage = $res->al_link_percentage;
            // TODO why is this named like this...
            if ($percentage == 0) {
                $sql .= " OR `as_name`='spamandlinks0' ";
            } else if ($percentage > 0 && $percentage < 0.1) {
                $sql .= " OR `as_name`='spamandlinks5' ";
            } else if ($percentage >= 0.1 && $percentage <= 0.35) {
                $sql .= " OR `as_name`='spamandlinks20' ";
            } else {
                $sql .= " OR `as_name`='spamandlinks50' ";
            }
            $sql .= ";";

            $dbw->query($sql);
            wfErrorLog($sql, 'D:/xampp2/htdocs/spam2/extensions/Athena/data/debug.log');
        }
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

        if( isset( $array[$code] ) ) {
           return $array[$code];
        }
        return null;
    }
}
