<?php
/**
 * A script to grab information about new pages from a wiki
 *
 * TODO clean up
 * TODO re-write before future testing
 * TODO merge with Deleted script
 *
 * @file
 * @ingroup Maintenance
 * @author Richard Cook <cook879@shoutwiki.com>
 * @date 28 November 2015
 */

/**
 * Set the correct include path for PHP so that we can run this script from
 * $IP/extensions/Athena and we don't need to move this file to
 * $IP/maintenance/.
 */
ini_set( 'include_path', __DIR__ . '/../../../maintenance' );

require_once 'Maintenance.php';

class Polymatheia extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Athena' );
		$this->addDescription( 'Gets all the new page info for a given site' );
	}

	public function execute() {
		global $wgLanguageCode;

		$dbr = wfGetDB( DB_REPLICA );

		// For users
		$res = $dbr->select(
			[ 'revision', 'page', 'text', 'user' ],
			[ 'page_namespace', 'page_title', 'rev_comment', 'old_text', 'rev_timestamp', 'user_registration' ],
			[ 'rev_parent_id=0', 'rev_user != 0' ],
			__METHOD__,
			[],
			[ 'page' => [ 'INNER JOIN', [
					'rev_page=page_id' ] ],
					'text' => [ 'INNER JOIN', [
					'rev_text_id=old_id' ] ],
					'user' => [ 'INNER JOIN', [
					'rev_user=user_id' ] ] ]
		);

		$output = [];
		foreach ( $res as $row ) {
			$output[] = [ 'namespace' => $row->page_namespace,
								'title' => $row->page_title,
								'comment' => $row->rev_comment,
								'content' => $row->old_text,
								'timestamp' => $row->rev_timestamp,
								'user-timestamp' => $row->user_registration,
								'lang' => $wgLanguageCode ];
		}

		// For anons
		$res = $dbr->select(
			[ 'revision', 'page', 'text' ],
			[ 'page_namespace', 'page_title', 'rev_comment', 'old_text', 'rev_timestamp' ],
			[ 'rev_parent_id=0', 'rev_user = 0' ],
			__METHOD__,
			[],
			[ 'page' => [ 'INNER JOIN', [
					'rev_page=page_id' ] ],
					'text' => [ 'INNER JOIN', [
					'rev_text_id=old_id' ] ] ]
		);

		foreach ( $res as $row ) {
				$output[] = [ 'namespace' => $row->page_namespace,
								'title' => $row->page_title,
								'comment' => $row->rev_comment,
								'content' => $row->old_text,
								'timestamp' => $row->rev_timestamp,
								'user-timestamp' => 0,
								'lang' => $wgLanguageCode ];
		}
		$outputStr = json_encode( $output );

		$this->output( $outputStr );
	}
}

$maintClass = Polymatheia::class;
require_once RUN_MAINTENANCE_IF_MAIN;
