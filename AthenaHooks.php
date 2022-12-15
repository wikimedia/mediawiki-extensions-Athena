<?php
/**
 * Hooks Athena uses to do it's thing
 *
 * @file
 * @author Richard Cook
 * @copyright ©2016 Richard Cook
 * @license GPL-3.0-only
 */
class AthenaHooks {

	/**
	 * Register hooks depending on version
	 */
	public static function registerExtension() {
		global $wgHooks;
		if ( class_exists( MediaWiki\HookContainer\HookContainer::class ) ) {
			// MW 1.35+
			$wgHooks['PageSaveComplete'][] = 'AthenaHooks::successfulEdit';
		} else {
			$wgHooks['PageContentSaveComplete'][] = 'AthenaHooks::successfulEdit';
		}
	}

	/**
	 * Called when the edit is about to be saved
	 *
	 * @param EditPage $editPage
	 * @param string $text
	 * @param string $section
	 * @param string &$error
	 * @param string $summary
	 * @return bool
	 */
	static function editFilter( $editPage, $text, $section, &$error, $summary ) {
		global $wgAthenaTraining;

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
	 * Updates the database with the new Athena table
	 * Called when the update.php maintenance script is run.
	 *
	 * @param DatabaseUpdater $updater
	 */
	static function createTables( DatabaseUpdater $updater ) {
		$updater->addExtensionUpdate( [ 'addTable', 'athena_log', __DIR__ . '/sql/athena_log.sql', true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'athena_calculations', __DIR__ . '/sql/athena_calculations.sql', true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'athena_page_details', __DIR__ . '/sql/athena_page_details.sql', true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'athena_stats', __DIR__ . '/sql/athena_stats.sql', true ] );
	}

	/**
	 * If an article successfully saves, we want to take the page_id and rev_id and update our
	 * athena_page_details table
	 *
	 * PageContentSaveComplete hook handler
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @return bool
	 */
	static function successfulEdit(
		WikiPage $wikiPage, $user ) {
		$dbw = wfGetDB( DB_PRIMARY );

		$page_id = $wikiPage->getId();
		$rev_id = $wikiPage->getRevisionRecord()->getId();

		// TODO check multiple instances of the same title - maybe check user_id as well
		$row = $dbw->selectRow(
			'athena_page_details',
			'al_id',
			[ 'apd_title' => $wikiPage->getTitle()->getText(), 'apd_namespace' => $wikiPage->getTitle()->getNamespace() ],
			__METHOD__,
			[ 'ORDER BY' => 'al_id DESC' ]
		);

		if ( $row ) {

			$id = $row->al_id;

			$dbw->update( 'athena_page_details',
				[ 'page_id' => $page_id, 'rev_id' => $rev_id ],
				[ 'al_id' => $id ],
				__METHOD__
			);

			return true;
		}

		return false;
	}

	/**
	 * BUGGY - Temporarily disabled
	 *
	 * Hooks into the delete action, so we can track if Athena logged pages have been deleted
	 *
	 * @param Article &$article
	 * @param User &$user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param LogEntry $logEntry
	 */
	static function pageDeleted( &$article, &$user, $reason, $id, $content, $logEntry ) {
		/*$pos = strpos( $reason, wfMessage( 'athena-spam' )->toString() );
		//echo($pos);
		if ( $pos !== false ) {
			$dbw = wfGetDB( DB_PRIMARY );

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
					__METHOD__
				);

				AthenaHelper::reinforceDelete( $res->al_id );
			}
		}*/
	}
}
