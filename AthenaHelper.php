<?php

/**
 * Various helper functions for Athena
 *
 * @file
 * @author Richard Cook
 * @copyright ©2016 Richard Cook
 * @license GPL-3.0-only
 */

class AthenaHelper {

	/**
	 * Log information about the attempted page creation
	 *
	 * @param array $logArray
	 * @param array $detailsArray
	 * @param array $calcArray
	 */
	static function logAttempt( $logArray, $detailsArray, $calcArray ) {
		global $wgAthenaTraining;
		$dbw = wfGetDB( DB_PRIMARY );

		$dbw->insert( 'athena_log', $logArray );

		// Get last inserted ID
		$id = $dbw->insertId();

		$detailsArray['al_id'] = $id;

		$dbw->insert( 'athena_page_details', $detailsArray );
		if ( !$wgAthenaTraining ) {
			$calcArray['al_id'] = $id;
			$dbw->insert( 'athena_calculations', $calcArray );
		}
	}

	/**
	 * Prepare an array with the details we want to insert into the athena_log table
	 *
	 * @param double $prob
	 * @param int $userAge
	 * @param int $links
	 * @param double $linkPercentage
	 * @param double $syntax
	 * @param bool $language
	 * @param bool $deleted
	 * @param bool $wanted
	 * @return array
	 */
	static function prepareLogArray( $prob, $userAge, $links, $linkPercentage, $syntax, $language, $deleted, $wanted ) {
		global $wgAthenaTraining;

		if ( $deleted === false ) {
			$deleted = 0;
		}

		if ( $wanted === false ) {
			$wanted = 0;
		}

		$insertArray = [ 'al_id' => null, 'al_value' => $prob, 'al_user_age' => $userAge,
			'al_links' => $links, 'al_link_percentage' => $linkPercentage, 'al_syntax' => $syntax,
			'al_wanted' => $wanted, 'al_deleted' => $deleted ];

		// Language could be null
		if ( $language != '' ) {
			if ( $language === false ) {
				$language = 0;
			}
			$insertArray['al_language'] = $language;
		} else {
			$insertArray['al_language'] = 0;
		}

		if ( !$wgAthenaTraining ) {
			// This version of Bayes is based around it being greater than 0 or not
			if ( $prob > 0 ) {
			// if ( $prob > $wgAthenaSpamThreshold ) {
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
	 * @param int $namespace
	 * @param string $title
	 * @param string $content
	 * @param string $comment
	 * @param int $user
	 * @return array
	 */
	static function preparePageDetailsArray( $namespace, $title, $content, $comment, $user ) {
		$insertArray = [ 'apd_namespace' => $namespace, 'apd_title' => $title,
			'apd_content' => $content, 'apd_user' => $user,
			'page_id' => 0, 'rev_id' => 0, 'apd_comment' => $comment ];

		$language = self::getTextLanguage( $content );

		$insertArray['apd_language'] = Language::fetchLanguageName( $language );

		return $insertArray;
	}

	/**
	 * Calculates the Athena value
	 * Calls all the filters, works out the probability of each contributing to the spam level, and combines them
	 *
	 * @param EditPage $editPage
	 * @param string $text
	 * @param string $summary
	 * @return double
	 */
	static function calculateAthenaValue( $editPage, $text, $summary ) {
		global $wgAthenaTraining;

		// Get title
		$titleObj = $editPage->getTitle();
		$title = $titleObj->getTitleValue()->getText();
		$user = $editPage->getContext()->getUser();

		// Get filter results
		$diffLang = AthenaFilters::differentLanguage( $text );
		$deleted = AthenaFilters::wasDeleted( $titleObj );
		$wanted = AthenaFilters::isWanted( $titleObj );
		$userAge = AthenaFilters::userAge( $user );
		$titleLength = AthenaFilters::titleLength( $titleObj );
		$namespace = AthenaFilters::getNamespace( $titleObj );
		$syntaxType = AthenaFilters::syntaxType( $text );
		$linksPercentage = AthenaFilters::linkPercentage( $text );

		// If not training, work out probabilities
		if ( !$wgAthenaTraining ) {
			// Array to store probability info
			$probabilityArray = [];

			$spam = null;
			$notspam = null;

			// Get the statistics table's contents
			$stats = self::getStatistics();

			// Calculate probability of spam
			self::calculateProbability_Spam( $stats, $probabilityArray );

			$lnProbSpamNotSpam = 0;
			$sigma = 0;

			if ( $probabilityArray['ac_p_not_spam'] ) {
				$lnProbSpamNotSpam = log( $probabilityArray['ac_p_spam'] / $probabilityArray['ac_p_not_spam'] );
			}

			/* start different language */
			self::calculateProbability_Language( $diffLang, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_langgivennotspam'] ) {
				$sigma = log( $probabilityArray['ac_p_langgivenspam'] / $probabilityArray['ac_p_langgivennotspam'] );
			}
			/* end different language */

			/* start deleted */
			self::calculateProbability_Deleted( $deleted, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_deletedgivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_deletedgivenspam'] / $probabilityArray['ac_p_deletedgivennotspam'] );
			}
			/* end deleted */

			/* start wanted */
			self::calculateProbability_Wanted( $wanted, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_wantedgivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_wantedgivenspam'] / $probabilityArray['ac_p_wantedgivennotspam'] );
			}
			/* end wanted */

			/* start user type */
			self::calculateProbability_User( $userAge, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_usergivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_usergivenspam'] / $probabilityArray['ac_p_usergivennotspam'] );
			}
			/* end user type */

			/* start title length */
			self::calculateProbability_Length( $titleLength, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_titlelengthgivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_titlelengthgivenspam'] / $probabilityArray['ac_p_titlelengthgivennotspam'] );
			}
			/* end title length */

			/* start namespace */
			self::calculateProbability_Namespace( $namespace, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_namespacegivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_namespacegivenspam'] / $probabilityArray['ac_p_namespacegivennotspam'] );
			}
			/* end namespace */

			/* start syntax */
			self::calculateProbability_Syntax( $syntaxType, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_syntaxgivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_syntaxgivenspam'] / $probabilityArray['ac_p_syntaxgivennotspam'] );
			}
			/* end syntax */

			/* start links */
			self::calculateProbability_Links( $linksPercentage, $stats, $probabilityArray );

			if ( $probabilityArray['ac_p_linksgivennotspam'] ) {
				$sigma += log( $probabilityArray['ac_p_linksgivenspam'] / $probabilityArray['ac_p_linksgivennotspam'] );
			}
			/* end links */

			$prob = $lnProbSpamNotSpam + $sigma;

			// wfErrorLog("------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log');
			//wfErrorLog("Probability is $prob", '/var/www/html/a/extensions/Athena/data/debug.log');
		} else {
			// al_value is double unsigned not null, so let's just set to 0 and let the code ignore it later on
			$prob = 0;
			$probabilityArray = null;
		}
		$links = AthenaFilters::numberOfLinks( $text );

		$logArray = self::prepareLogArray( $prob, $userAge, $links, $linksPercentage, $syntaxType, $diffLang, $deleted, $wanted );
		$detailsArray = self::preparePageDetailsArray( $namespace, $title, $text, $summary, $user->getId() );

		self::logAttempt( $logArray, $detailsArray, $probabilityArray );
		self::updateStats( $logArray, $titleObj );

		return $prob;
	}

	/**
	 * Calculates the probability of an article being spam
	 *
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Spam( array $stats, array &$probabilityArray ) {
		$spam = $stats['spam'] + 2;
		$pages = $stats['pages'] + 2;

		$probSpam = $spam / $pages;

		$probNotSpam = 1 - $probSpam;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of spam is $spam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of spam is $probSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of not spam is $probNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_spam'] = $probSpam;
		$probabilityArray['ac_p_not_spam'] = $probNotSpam;
	}

	/**
	 * Calculates the probability related to the different language filter
	 *
	 * @param bool $diffLang
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Language( $diffLang, array $stats, array &$probabilityArray ) {
		$var = 'difflang';
		// Let's treat null as false for simplicity
		if ( !$diffLang ) {
			$var = 'samelang';
		}

		$lang = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$langAndSpam = $stats['spamand' . $var] + 1;
		$langAndNotSpam = $lang - $langAndSpam;

		$probLang = $lang / $pages;
		$probLangAndSpam = $langAndSpam / $pages;
		$probLangGivenSpam = $probLangAndSpam / $probabilityArray['ac_p_spam'];
		$probLangAndNotSpam = $langAndNotSpam / $pages;
		$probLangGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probLangAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Lang type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of lang is $lang", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of lang and spam is $langAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of lang and not spam is $langAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of lang is $probLang", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of lang and spam is $probLangAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of lang given spam is $probLangGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of lang and not spam is $probLangAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of lang given not spam is $probLangGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_lang'] = $probLang;
		$probabilityArray['ac_p_langandspam'] = $probLangAndSpam;
		$probabilityArray['ac_p_langgivenspam'] = $probLangGivenSpam;
		$probabilityArray['ac_p_langandnotspam'] = $probLangAndNotSpam;
		$probabilityArray['ac_p_langgivennotspam'] = $probLangGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the deleted filter
	 *
	 * @param bool $wasDeleted
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Deleted( $wasDeleted, array $stats, array &$probabilityArray ) {
		$var = 'deleted';
		// Let's treat null as false for simplicity
		if ( !$wasDeleted ) {
			$var = 'notdeleted';
		}

		$deleted = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$deletedAndSpam = $stats['spamand' . $var] + 1;
		$deletedAndNotSpam = $deleted - $deletedAndSpam;

		$probDeleted = $deleted / $pages;
		$probDeletedAndSpam = $deletedAndSpam / $pages;
		$probDeletedGivenSpam = $probDeletedAndSpam / $probabilityArray['ac_p_spam'];
		$probDeletedAndNotSpam = $deletedAndNotSpam / $pages;
		$probDeletedGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probDeletedAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Delete type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of deleted is $deleted", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of deleted and spam is $deletedAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of deleted and Not spam is $deletedAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of deleted is $probDeleted", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of deleted and spam is $probDeletedAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of deleted given spam is $probDeletedGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of deleted and Not spam is $probDeletedAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of deleted given Not spam is $probDeletedGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_deleted'] = $probDeleted;
		$probabilityArray['ac_p_deletedandspam'] = $probDeletedAndSpam;
		$probabilityArray['ac_p_deletedgivenspam'] = $probDeletedGivenSpam;
		$probabilityArray['ac_p_deletedandnotspam'] = $probDeletedAndNotSpam;
		$probabilityArray['ac_p_deletedgivennotspam'] = $probDeletedGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the wanted filter
	 *
	 * @param bool $isWanted
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Wanted( $isWanted, array $stats, array &$probabilityArray ) {
		$var = 'wanted';
		// Let's treat null as false for simplicity
		if ( !$isWanted ) {
			$var = 'notwanted';
		}

		$wanted = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$wantedAndSpam = $stats['spamand' . $var] + 1;
		$wantedAndNotSpam = $wanted - $wantedAndSpam;

		$probWanted = $wanted / $pages;
		$probWantedAndSpam = $wantedAndSpam / $pages;
		$probWantedGivenSpam = $probWantedAndSpam / $probabilityArray['ac_p_spam'];
		$probWantedAndNotSpam = $wantedAndNotSpam / $pages;
		$probWantedGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probWantedAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Wanted type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of wanted is $wanted", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of wanted and spam is $wantedAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of wanted and Not spam is $wantedAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of wanted is $probWanted", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of wanted and spam is $probWantedAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of wanted given spam is $probWantedGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of wanted and Not spam is $probWantedAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of wanted given Not spam is $probWantedGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_wanted'] = $probWanted;
		$probabilityArray['ac_p_wantedandspam'] = $probWantedAndSpam;
		$probabilityArray['ac_p_wantedgivenspam'] = $probWantedGivenSpam;
		$probabilityArray['ac_p_wantedandnotspam'] = $probWantedAndNotSpam;
		$probabilityArray['ac_p_wantedgivennotspam'] = $probWantedGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the user type filter
	 *
	 * @param int $userAge
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_User( $userAge, array $stats, array &$probabilityArray ) {
		$var = 'anon';
		if ( $userAge >= 0 ) {
			if ( $userAge < 60 ) {
				$var = 'user1';
			} elseif ( $userAge < 5 * 60 ) {
				$var = 'user5';
			} elseif ( $userAge < 30 * 60 ) {
				$var = 'user30';
			} elseif ( $userAge < 60 * 60 ) {
				$var = 'user60';
			} elseif ( $userAge < 60 * 60 * 12 ) {
				$var = 'user12';
			} elseif ( $userAge < 60 * 60 * 24 ) {
				$var = 'user24';

			} else { $var = 'userother';
			}
		} else {
			if ( $userAge != -1 ) {
				// -2 is no registration details - we shouldn't have that problem though
				// anything bigger will be imported content, so let's just assume they were greater than a day and do other
				$var = 'userother';
			}
		}

		$user = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$userAndSpam = $stats['spamand' . $var] + 1;
		$userAndNotSpam = $user - $userAndSpam;

		$probUser = $user / $pages;
		$probUserAndSpam = $userAndSpam / $pages;
		$probUserGivenSpam = $probUserAndSpam / $probabilityArray['ac_p_spam'];
		$probUserAndNotSpam = $userAndNotSpam / $pages;
		$probUserGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probUserAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "User type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of user is $user", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of user and spam is $userAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of user and Not spam is $userAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of user is $probUser", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of user and spam is $probUserAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of user given spam is $probUserGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of user and Not spam is $probUserAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of user given Not spam is $probUserGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_user'] = $probUser;
		$probabilityArray['ac_p_userandspam'] = $probUserAndSpam;
		$probabilityArray['ac_p_usergivenspam'] = $probUserGivenSpam;
		$probabilityArray['ac_p_userandnotspam'] = $probUserAndNotSpam;
		$probabilityArray['ac_p_usergivennotspam'] = $probUserGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the title length filter
	 *
	 * @param int $length
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Length( $length, array $stats, array &$probabilityArray ) {
		$var = 'nottitlelength';
		// Let's treat null as false for simplicity
		if ( $length > 39 ) {
			$var = 'titlelength';
		}

		$titleLength = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$titleLengthAndSpam = $stats['spamand' . $var] + 1;
		$titleLengthAndNotSpam = $titleLength - $titleLengthAndSpam;

		$probTitleLength = $titleLength / $pages;
		$probTitleLengthAndSpam = $titleLengthAndSpam / $pages;
		$probTitleLengthGivenSpam = $probTitleLengthAndSpam / $probabilityArray['ac_p_spam'];
		$probTitleLengthAndNotSpam = $titleLengthAndNotSpam / $pages;
		$probTitleLengthGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probTitleLengthAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Title length type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of title length is $titleLength", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of title length and spam is $titleLengthAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of title length and Not spam is $titleLengthAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of title length is $probTitleLength", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of title length and spam is $probTitleLengthAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of title length given spam is $probTitleLengthGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of title length and Not spam is $probTitleLengthAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of title length given Not spam is $probTitleLengthGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_titlelength'] = $probTitleLength;
		$probabilityArray['ac_p_titlelengthandspam'] = $probTitleLengthAndSpam;
		$probabilityArray['ac_p_titlelengthgivenspam'] = $probTitleLengthGivenSpam;
		$probabilityArray['ac_p_titlelengthandnotspam'] = $probTitleLengthAndNotSpam;
		$probabilityArray['ac_p_titlelengthgivennotspam'] = $probTitleLengthGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the namespace filter
	 *
	 * @param int $namespace
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Namespace( $namespace, array $stats, array &$probabilityArray ) {
		$var = 'nsother';
		if ( $namespace == 0 ) {
			$var = 'nsmain';
		} elseif ( $namespace == 1 ) {
			$var = 'nstalk';
		} elseif ( $namespace == 2 ) {
			$var = 'nsuser';
		} elseif ( $namespace == 3 ) {
			$var = 'nsusertalk';
		} elseif ( $namespace == 6 ) {
			$var = 'nsfile';
		}

		$namespace = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$namespaceAndSpam = $stats['spamand' . $var] + 1;
		$namespaceAndNotSpam = $namespace - $namespaceAndSpam;

		$probNamespace = $namespace / $pages;
		$probNamespaceAndSpam = $namespaceAndSpam / $pages;
		$probNamespaceGivenSpam = $probNamespaceAndSpam / $probabilityArray['ac_p_spam'];
		$probNamespaceAndNotSpam = $namespaceAndNotSpam / $pages;
		$probNamespaceGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probNamespaceAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Namespace type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of namespace is $namespace", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of namespace and spam is $namespaceAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of namespace and Not spam is $namespaceAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of namespace is $probNamespace", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of namespace and spam is $probNamespaceAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of namespace given spam is $probNamespaceGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of namespace and Not spam is $probNamespaceAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of namespace given Not spam is $probNamespaceGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_namespace'] = $probNamespace;
		$probabilityArray['ac_p_namespaceandspam'] = $probNamespaceAndSpam;
		$probabilityArray['ac_p_namespacegivenspam'] = $probNamespaceGivenSpam;
		$probabilityArray['ac_p_namespaceandnotspam'] = $probNamespaceAndNotSpam;
		$probabilityArray['ac_p_namespacegivennotspam'] = $probNamespaceGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the syntax filter
	 *
	 * @param int $type
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Syntax( $type, array $stats, array &$probabilityArray ) {
		$var = 'syntaxnone';
		if ( $type == 1 ) {
			$var = 'syntaxbasic';
		} elseif ( $type == 2 ) {
			$var = 'syntaxcomplex';
		} elseif ( $type == 3 ) {
			$var = 'brokenspambot';
		}

		$syntax = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$syntaxAndSpam = $stats['spamand' . $var] + 1;
		$syntaxAndNotSpam = $syntax - $syntaxAndSpam;

		$probSyntax = $syntax / $pages;
		$probSyntaxAndSpam = $syntaxAndSpam / $pages;
		$probSyntaxGivenSpam = $probSyntaxAndSpam / $probabilityArray['ac_p_spam'];
		$probSyntaxAndNotSpam = $syntaxAndNotSpam / $pages;
		$probSyntaxGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probSyntaxAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Syntax type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of syntax is $syntax", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of syntax and spam is $syntaxAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of syntax and Not spam is $syntaxAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of syntax is $probSyntax", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of syntax and spam is $probSyntaxAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of syntax given spam is $probSyntaxGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of syntax and Not spam is $probSyntaxAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of syntax given Not spam is $probSyntaxGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_syntax'] = $probSyntax;
		$probabilityArray['ac_p_syntaxandspam'] = $probSyntaxAndSpam;
		$probabilityArray['ac_p_syntaxgivenspam'] = $probSyntaxGivenSpam;
		$probabilityArray['ac_p_syntaxandnotspam'] = $probSyntaxAndNotSpam;
		$probabilityArray['ac_p_syntaxgivennotspam'] = $probSyntaxGivenNotSpam;
	}

	/**
	 * Calculates the probability related to the link filter
	 *
	 * @param double $percentage
	 * @param array $stats contents of the athena_stats table
	 * @param array &$probabilityArray stores details about probabilities calculated
	 */
	static function calculateProbability_Links( $percentage, array $stats, array &$probabilityArray ) {
		$var = 'links0';
		if ( $percentage > 0 && $percentage < 0.1 ) {
			$var = 'links5';
		} elseif ( $percentage >= 0.1 && $percentage <= 0.35 ) {
			$var = 'links20';
		} elseif ( $percentage > 0.35 ) {
			$var = 'links50';
		}

		$links = $stats[$var] + 2;
		$pages = $stats['pages'] + 2;
		$linksAndSpam = $stats['spamand' . $var] + 1;
		$linksAndNotSpam = $links - $linksAndSpam;

		$probLinks = $links / $pages;
		$probLinksAndSpam = $linksAndSpam / $pages;
		$probLinksGivenSpam = $probLinksAndSpam / $probabilityArray['ac_p_spam'];
		$probLinksAndNotSpam = $linksAndNotSpam / $pages;
		$probLinksGivenNotSpam = $probabilityArray['ac_p_not_spam'] ? $probLinksAndNotSpam / $probabilityArray['ac_p_not_spam'] : 0;

		// wfErrorLog( "------------------------------------------------", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Links type is $var ", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of links is $links", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of pages is $pages", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of links and spam is $linksAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Number of links and Not spam is $linksAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of links is $probLinks", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of links and spam is $probLinksAndSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of links given spam is $probLinksGivenSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of links and Not spam is $probLinksAndNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );
		//wfErrorLog( "Probability of links given Not spam is $probLinksGivenNotSpam", '/var/www/html/a/extensions/Athena/data/debug.log' );

		$probabilityArray['ac_p_links'] = $probLinks;
		$probabilityArray['ac_p_linksandspam'] = $probLinksAndSpam;
		$probabilityArray['ac_p_linksgivenspam'] = $probLinksGivenSpam;
		$probabilityArray['ac_p_linksandnotspam'] = $probLinksAndNotSpam;
		$probabilityArray['ac_p_linksgivennotspam'] = $probLinksGivenNotSpam;
	}

	/**
	 * Makes a number of seconds look nice and pretty
	 *
	 * @param int $seconds
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
	 * @param int $type
	 * @return string
	 */
	static function syntaxTypeToString( $type ) {
		switch ( $type ) {
			case 0:
				return wfMessage( 'athena-syntax-none' );
			case 1:
				return wfMessage( 'athena-syntax-basic' );
			case 2:
				return wfMessage( 'athena-syntax-complex' );
			case 3:
				return wfMessage( 'athena-syntax-spambot' );
			default:
				return wfMessage( 'athena-syntax-invalid' );
		}
	}

	/**
	 * Takes a boolean (be it of type boolean or integer) and returns the equivalent string
	 *
	 * @param bool|int $val
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
	 * @param string $text
	 * @return string
	 */
	static function getTextLanguage( $text ) {
		// wfErrorLog( "getTextLanguage called", '/var/www/html/a/extensions/Athena/data/debug.log' );

		if ( strlen( $text ) == 0 ) {
			$code = null;
		} else {
			$temp = __DIR__ . '/data/temp';
			// wfErrorLog( "BEFORE TEMP", '/var/www/html/a/extensions/Athena/data/debug.log' );
			file_put_contents( $temp, $text );
			// wfErrorLog( "AFTER TEMP", '/var/www/html/a/extensions/Athena/data/debug.log' );

			$code = system( 'franc < ' . $temp );
			// wfErrorLog( "Language code is $code", '/var/www/html/a/extensions/Athena/data/debug.log' );
		}
		return self::convertISOCode( $code );
	}

	/**
	 * Updates the stats table with information from this edit
	 *
	 * @param array $array (logArray - contains details of the edit to be inserted into the athena_log table)
	 * @param Title $title
	 */
	static function updateStats( $array, $title ) {
		global $wgAthenaTraining;
		$dbw = wfGetDB( DB_PRIMARY );

		// TODO not the best way but get me incrementing with the better way and I'll use it
		$sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'pages'";

		if ( !$wgAthenaTraining ) {
			// This version of Bayes is based around it being greater than 0 or not
			if ( $array['al_value'] > 0 ) {
			// if ($array['al_value'] > $wgAthenaSpamThreshold) {
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
			if ( $spam ) {
				$sql .= " OR `as_name`='spamanddifflang' ";
			}
		} else {
			$sql .= " OR `as_name`='samelang' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandsamelang' ";
			}
		}

		if ( $array['al_deleted'] ) {
			$sql .= " OR `as_name`='deleted' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamanddeleted' ";
			}
		} else {
			$sql .= " OR `as_name`='notdeleted' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnotdeleted' ";
			}
		}

		if ( $array['al_wanted'] ) {
			$sql .= " OR `as_name`='wanted' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandwanted' ";
			}
		} else {
			$sql .= " OR `as_name`='notwanted' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnotwanted' ";
			}
		}

		$userAge = $array['al_user_age'];
		  if ( $userAge >= 0 ) {
			  if ( $userAge < 1 * 60 ) {
				  $sql .= " OR `as_name`='user1' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduser1' ";
				  }
			  } elseif ( $userAge < 5 * 60 ) {
				  $sql .= " OR `as_name`='user5' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduser5' ";
				  }
			  } elseif ( $userAge < 30 * 60 ) {
				  $sql .= " OR `as_name`='user30' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduser30' ";
				  }
			  } elseif ( $userAge < 60 * 60 ) {
				  $sql .= " OR `as_name`='user60' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduser60' ";
				  }
			  } elseif ( $userAge < 60 * 12 * 60 ) {
				$sql .= " OR `as_name`='user12' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduser12' ";
				  }
			  } elseif ( $userAge < 60 * 24 * 60 ) {
				  $sql .= " OR `as_name`='user24' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduser24' ";
				  }
			  } else {
				  $sql .= " OR `as_name`='userother' ";
				  if ( $spam ) {
					  $sql .= " OR `as_name`='spamanduserother' ";
				  }
			  }
		  } elseif ( $userAge != -1 ) {
			  $sql .= " OR `as_name`='userother' ";
			  if ( $spam ) {
				  $sql .= " OR `as_name`='spamanduserother' ";
			  }
		  } else {
			  $sql .= " OR `as_name`='anon' ";
			  if ( $spam ) {
				  $sql .= " OR `as_name`='spamandanon' ";
			  }
		  }

		if ( strlen( $title->getText() ) > 39 ) {
			$sql .= " OR `as_name`='titlelength' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandtitlelength' ";
			}
		} else {
			$sql .= " OR `as_name`='nottitlelength' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnottitlelength' ";
			}
		}

		$namespace = $title->getNamespace();
		if ( $namespace == 0 ) {
			$sql .= " OR `as_name`='nsmain' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnsmain' ";
			}
		} elseif ( $namespace == 1 ) {
			$sql .= " OR `as_name`='nstalk' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnstalk' ";
			}
		} elseif ( $namespace == 2 ) {
			$sql .= " OR `as_name`='nsuser' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnsuser' ";
			}
		} elseif ( $namespace == 3 ) {
			$sql .= " OR `as_name`='nsusertalk' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnsusertalk' ";
			}
		} elseif ( $namespace == 6 ) {
			$sql .= " OR `as_name`='nsfile' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnsfile' ";
			}
		} else {
			$sql .= " OR `as_name`='nsother' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandnsother' ";
			}
		}

		$syntax = $array['al_syntax'];
		if ( $syntax == 1 ) {
			$sql .= " OR `as_name`='syntaxbasic' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandsyntaxbasic' ";
			}
		} elseif ( $syntax == 2 ) {
			$sql .= " OR `as_name`='syntaxcomplex' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandsyntaxcomplex' ";
			}
		} elseif ( $syntax == 3 ) {
			$sql .= " OR `as_name`='brokenspambot' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandbrokenspambot' ";
			}
		} else {
			$sql .= " OR `as_name`='syntaxnone' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandsyntaxnone' ";
			}
		}

		$percentage = $array['al_link_percentage'];
		// TODO why is this named like this...
		if ( $percentage == 0 ) {
			$sql .= " OR `as_name`='links0' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandlinks0' ";
			}
		} elseif ( $percentage > 0 && $percentage < 0.1 ) {
			$sql .= " OR `as_name`='links5' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandlinks5' ";
			}
		} elseif ( $percentage >= 0.1 && $percentage <= 0.35 ) {
			$sql .= " OR `as_name`='links20' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandlinks20' ";
			}
		} else {
			$sql .= " OR `as_name`='links50' ";
			if ( $spam ) {
				$sql .= " OR `as_name`='spamandlinks50' ";
			}
		}
		$sql .= ";";

		$dbw->query( $sql );
		// wfErrorLog( $sql, '/var/www/html/a/extensions/Athena/data/debug.log' );
	}

	/**
	 * Retrieves the contents of the stats table
	 *
	 * @return array() containing all the stats
	 */
	static function getStatistics() {
		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			[ 'athena_stats' ],
			[ 'as_name, as_value' ],
			[],
			__METHOD__,
			[]
		);

		$array = [];

		foreach ( $res as $row ) {
			$array[$row->as_name] = $row->as_value;
		}

	   // foreach( $array as $name=>$val ) {
	   //     //wfErrorLog( "Array has key " . $name . " and value " . $val, '/var/www/html/a/extensions/Athena/data/debug.log' );

	   // }

		return $array;
	}

	/**
	 * Reinforce during page deletion
	 *
	 * @param int $id
	 */
	static function reinforceDelete( $id ) {
		// Get page details
		$res = self::getAthenaDetails( $id );

		self::updateStatsDeleted( $res, false );
	}

	/**
	 * Reinforce during page deletion
	 *
	 * @param int $id
	 */
	static function reinforceDeleteTraining( $id ) {
		// Get page details
		$res = self::getAthenaDetails( $id );

		self::updateStatsDeleted( $res, true );
	}

	/**
	 * Prepare the log array without any data already
	 *
	 * @param int $id
	 * @return stdClass - database query results
	 */
	static function getAthenaDetails( $id ) {
		$dbr = wfGetDB( DB_REPLICA );
		// Get data from the database
		$res = $dbr->selectRow(
			[ 'athena_log', 'athena_page_details' ],
			[ 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_timestamp',
				'al_user_age', 'al_link_percentage', 'al_syntax', 'al_language', 'al_wanted', 'al_deleted' ],
			[ 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ],
			__METHOD__,
			[]
		);

		return $res;
	}

	/**
	 * Updates the stats table with information from this edit
	 *
	 * @param stdClass $res
	 * @param bool $training are we in training mode?
	 */
	static function updateStatsDeleted( $res, $training ) {
		$dbw = wfGetDB( DB_PRIMARY );

		// Start by reducing the number of not spam
		if ( !$training ) {
			$sql = "UPDATE `athena_stats` SET `as_value`=`as_value`-1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'notspam';";
			$dbw->query( $sql );
			// wfErrorLog($sql, '/var/www/html/a/extensions/Athena/data/debug.log');
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
			if ( $userAge < 1 * 60 ) {
				$sql .= " OR `as_name`='spamanduser1' ";
			} elseif ( $userAge < 5 * 60 ) {
				$sql .= " OR `as_name`='spamanduser5' ";
			} elseif ( $userAge < 30 * 60 ) {
				$sql .= " OR `as_name`='spamanduser30' ";
			} elseif ( $userAge < 60 * 60 ) {
				$sql .= " OR `as_name`='spamanduser60' ";
			} elseif ( $userAge < 60 * 12 * 60 ) {
				$sql .= " OR `as_name`='spamanduser12' ";
			} elseif ( $userAge < 60 * 24 * 60 ) {
				$sql .= " OR `as_name`='spamanduser24' ";
			} else {
				$sql .= " OR `as_name`='spamanduserother' ";
			}
		} elseif ( $userAge != -1 ) {
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
		} elseif ( $namespace == 1 ) {
			$sql .= " OR `as_name`='spamandnstalk' ";
		} elseif ( $namespace == 2 ) {
			$sql .= " OR `as_name`='spamandnsuser' ";
		} elseif ( $namespace == 3 ) {
			$sql .= " OR `as_name`='spamandnsusertalk' ";
		} elseif ( $namespace == 6 ) {
			$sql .= " OR `as_name`='spamandnsfile' ";
		} else {
			$sql .= " OR `as_name`='spamandnsother' ";
		}

		$syntax = $res->al_syntax;
		if ( $syntax == 1 ) {
			$sql .= " OR `as_name`='spamandsyntaxbasic' ";
		} elseif ( $syntax == 2 ) {
			$sql .= " OR `as_name`='spamandsyntaxcomplex' ";
		} elseif ( $syntax == 3 ) {
			$sql .= " OR `as_name`='spamandbrokenspambot' ";
		} else {
			$sql .= " OR `as_name`='spamandsyntaxnone' ";
		}

		$percentage = $res->al_link_percentage;
		// TODO why is this named like this...
		if ( $percentage == 0 ) {
			$sql .= " OR `as_name`='spamandlinks0' ";
		} elseif ( $percentage > 0 && $percentage < 0.1 ) {
			$sql .= " OR `as_name`='spamandlinks5' ";
		} elseif ( $percentage >= 0.1 && $percentage <= 0.35 ) {
			$sql .= " OR `as_name`='spamandlinks20' ";
		} else {
			$sql .= " OR `as_name`='spamandlinks50' ";
		}
		$sql .= ";";

		$dbw->query( $sql );
		// wfErrorLog( $sql, '/var/www/html/a/extensions/Athena/data/debug.log' );
	}

	/**
	 * Reinforce during reinforcement page creation
	 *
	 * @param int $id
	 */
	static function reinforceCreate( $id ) {
		// Get page details
		$res = self::getAthenaDetails( $id );

		self::updateStatsCreated( $res, false );
	}

	/**
	 * Reinforce during reinforcement page creation
	 *
	 * @param int $id
	 */
	static function reinforceCreateTraining( $id ) {
		// Get page details
		$res = self::getAthenaDetails( $id );

		self::updateStatsCreated( $res, true );
	}

	/**
	 * Updates the stats table with information from this edit
	 *
	 * @param stdClass $res
	 * @param bool $training are we in training mode?
	 */
	static function updateStatsCreated( $res, $training ) {
		$dbw = wfGetDB( DB_PRIMARY );

		// Start by increasing the number of not spam
		$sql = "UPDATE `athena_stats` SET `as_value`=`as_value`+1, `as_updated`=CURRENT_TIMESTAMP WHERE `as_name` = 'notspam';";
		$dbw->query( $sql );
		// wfErrorLog( $sql, '/var/www/html/a/extensions/Athena/data/debug.log' );

		if ( !$training ) {
			// Now decrement spam and all the spamands
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
				if ( $userAge < 1 * 60 ) {
					$sql .= " OR `as_name`='spamanduser1' ";
				} elseif ( $userAge < 5 * 60 ) {
					$sql .= " OR `as_name`='spamanduser5' ";
				} elseif ( $userAge < 30 * 60 ) {
					$sql .= " OR `as_name`='spamanduser30' ";
				} elseif ( $userAge < 60 * 60 ) {
					$sql .= " OR `as_name`='spamanduser60' ";
				} elseif ( $userAge < 60 * 12 * 60 ) {
					$sql .= " OR `as_name`='spamanduser12' ";
				} elseif ( $userAge < 60 * 24 * 60 ) {
					$sql .= " OR `as_name`='spamanduser24' ";
				} else {
					$sql .= " OR `as_name`='spamanduserother' ";
				}
			} elseif ( $userAge != -1 ) {
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
			} elseif ( $namespace == 1 ) {
				$sql .= " OR `as_name`='spamandnstalk' ";
			} elseif ( $namespace == 2 ) {
				$sql .= " OR `as_name`='spamandnsuser' ";
			} elseif ( $namespace == 3 ) {
				$sql .= " OR `as_name`='spamandnsusertalk' ";
			} elseif ( $namespace == 6 ) {
				$sql .= " OR `as_name`='spamandnsfile' ";
			} else {
				$sql .= " OR `as_name`='spamandnsother' ";
			}

			$syntax = $res->al_syntax;
			if ( $syntax == 1 ) {
				$sql .= " OR `as_name`='spamandsyntaxbasic' ";
			} elseif ( $syntax == 2 ) {
				$sql .= " OR `as_name`='spamandsyntaxcomplex' ";
			} elseif ( $syntax == 3 ) {
				$sql .= " OR `as_name`='spamandbrokenspambot' ";
			} else {
				$sql .= " OR `as_name`='spamandsyntaxnone' ";
			}

			$percentage = $res->al_link_percentage;
			// TODO why is this named like this...
			if ( $percentage == 0 ) {
				$sql .= " OR `as_name`='spamandlinks0' ";
			} elseif ( $percentage > 0 && $percentage < 0.1 ) {
				$sql .= " OR `as_name`='spamandlinks5' ";
			} elseif ( $percentage >= 0.1 && $percentage <= 0.35 ) {
				$sql .= " OR `as_name`='spamandlinks20' ";
			} else {
				$sql .= " OR `as_name`='spamandlinks50' ";
			}
			$sql .= ";";

			$dbw->query( $sql );
			// wfErrorLog($sql, '/var/www/html/a/extensions/Athena/data/debug.log');
		}
	}

	/**
	 * Concerts a language code returned by franc into a language code used by MediaWiki
	 *
	 * @param string $code
	 * @return string
	 */
	static function convertISOCode( $code ) {
		$array = [
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
		];

		if ( isset( $array[$code] ) ) {
		   return $array[$code];
		}
		return null;
	}
}
