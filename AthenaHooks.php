<?php
/**
 * Hooks Athena uses to do it's thing
 *
 * @file
 * @author Richard Cook
 * @copyright ©2016 Richard Cook
 * @license GNU General Public License v3.0
 */
class AthenaHooks
{

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
		global $wgAthenaSpamThreshold, $wgAthenaTraining;

		// Check if it's a new article or not
		if ( $editPage->getTitle()->getArticleID() === 0 ) {

			// Let's skip redirects
			$redirect = preg_match_all( "/^#REDIRECT(\s)?\[\[([^\[\]])+\]\]$/", $text );
			if ( $redirect !== 1 ) {
				$prob = AthenaHelper::calculateAthenaValue( $editPage, $text, $summary );
				
				// This version of Bayes is based around it being greater than 0 or not
				//if ( !$wgAthenaTraining && $prob > $wgAthenaSpamThreshold ) {
				if ( !$wgAthenaTraining && $prob > 0 ) {
					$error =
						'<div class="errorbox">' .
						wfMessage( 'athena-blocked-error' ) .
						'</div>' .
						'<br clear="all" />';
				}
			}
		}
		return true;
	}

	/**
	 * Updates the database with the new Athena tabled
	 * Called when the update.php maintenance script is run.
	 *
	 * TODO Auto-fill data
	 * @param $updater DatabaseUpdater
	 * @return bool
	 */
	static function createTables( $updater ) {
		$updater->addExtensionUpdate( array( 'addTable', 'athena_log', __DIR__ . '/sql/athena_logs.sql', true ) );

		return true;
	}

	/**
	 * If an article successfully saves, we want to take the page_id and rev_id and update our
	 * athena_page_details table
	 *
	 * PageContentSaveComplete hook handler
	 *
	 * @param WikiPage $wikiPage
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
	static function successfulEdit(
		WikiPage $wikiPage, $user, $content, $summary, $isMinor, $isWatch, $section,
								   $flags, $revision, $status, $baseRevId ) {
		$dbw = wfGetDB( DB_MASTER );

		$page_id = $wikiPage->getId();
		$rev_id = $wikiPage->getRevision()->getId();

		$title = $dbw->strencode( $wikiPage->getTitle()->getText() );

		$whereStatement = " apd_title='{$title}' AND apd_namespace={$wikiPage->getTitle()->getNamespace()}";

		// TODO check multiple instances of the same title - maybe check user_id as well
		$sql = "SELECT al_id FROM {$dbw->tableName( 'athena_page_details' )} WHERE {$whereStatement} ORDER BY al_id DESC;";

		$res = $dbw->query( $sql, __METHOD__ );
		$row = $dbw->fetchObject( $res );

		if ( $row ) {

			$id = $row->al_id;

			$dbw->update( 'athena_page_details',
				array( 'page_id' => $page_id, 'rev_id' => $rev_id ),
				array( 'al_id' => $id ),
				__METHOD__,
				null );

			return true;
		}

		return false;
	}

	/**
	 * BUGGY - Temporarily disabled
	 *
	 * Hooks into the delete action, so we can track if Athena logged pages have been deleted
	 *
	 * @param $article Article
	 * @param $user User
	 * @param $reason string
	 * @param $id int
	 * @param null $content Content
	 * @param $logEntry LogEntry
	 */
	static function pageDeleted( &$article, &$user, $reason, $id, $content = null, $logEntry ) {
		/*$pos = strpos( $reason, wfMessage( 'athena-spam' )->toString() );
		//echo($pos);
		if ( $pos !== false ) {
			$dbw = wfGetDB( DB_REPLICA );

			// Search Athena logs for the page id
			$res = $dbw->selectRow(
				array( 'athena_page_details' ),
				array( 'al_id' ),
				array( 'page_id' => $id ),
				__METHOD__,
				array()
			);

			if ( $res ) {
				$dbw->update( 'athena_log',
					array( 'al_overridden' => 1 ),
					array( 'al_id' => $res->al_id ),
					__METHOD__,
					null );

				AthenaHelper::reinforceDelete( $res->al_id );
			}
		}*/
	}
}