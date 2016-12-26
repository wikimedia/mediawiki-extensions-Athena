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
ini_set( 'include_path', dirname( __FILE__ ) . '/../../../maintenance' );

require_once( 'Maintenance.php' );

class Polymatheia extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Athena' );
		$this->mDescription = 'Gets all the new page info for a given site';
	}

	public function execute() {
		global $wgLanguageCode;

		$dbr = wfGetDB( DB_SLAVE );

		// For users
		$res = $dbr->select(
			array( 'revision', 'page', 'text', 'user' ),
			array( 'page_namespace', 'page_title', 'rev_comment', 'old_text', 'rev_timestamp', 'user_registration' ),
			array( 'rev_parent_id=0', 'rev_user != 0' ),
			__METHOD__,
			array(),
			array( 'page' => array( 'INNER JOIN', array(
					'rev_page=page_id' ) ),
					'text' => array( 'INNER JOIN', array(
					'rev_text_id=old_id' ) ),
					'user' => array( 'INNER JOIN', array(
					'rev_user=user_id' ) ) )
		);

		$output = array();
		foreach ( $res as $row ) {
			$output[] = array( 'namespace' => $row->page_namespace,
								'title' => $row->page_title,
								'comment' => $row->rev_comment,
								'content' => $row->old_text,
								'timestamp' => $row->rev_timestamp,
								'user-timestamp' => $row->user_registration,
								'lang' => $wgLanguageCode );
		}

		// For anons
		$res = $dbr->select(
			array( 'revision', 'page', 'text' ),
			array( 'page_namespace', 'page_title', 'rev_comment', 'old_text', 'rev_timestamp' ),
			array( 'rev_parent_id=0', 'rev_user = 0' ),
			__METHOD__,
			array(),
			array( 'page' => array( 'INNER JOIN', array(
					'rev_page=page_id' ) ),
					'text' => array( 'INNER JOIN', array(
					'rev_text_id=old_id' ) ) )
		);

		foreach ( $res as $row ) {
				$output[] = array( 'namespace' => $row->page_namespace,
								'title' => $row->page_title,
								'comment' => $row->rev_comment,
								'content' => $row->old_text,
								'timestamp' => $row->rev_timestamp,
								'user-timestamp' => 0,
								'lang' => $wgLanguageCode );
		}
		$outputStr = json_encode( $output );

		$this->output( $outputStr );


	}
}

$maintClass = 'Polymatheia';
require_once( RUN_MAINTENANCE_IF_MAIN );
