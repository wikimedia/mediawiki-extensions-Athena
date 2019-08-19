<?php
/**
 * A script to grab information about new pages from a wiki
 *
 * TODO clean up
 * TODO re-write before future testing
 * TODO merge with normal script
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
		$this->addDescription( 'Gets all the new page info for a given site that has been deleted' );
	}

	public function execute() {
		global $wgLanguageCode;

		$dbr = wfGetDB( DB_REPLICA );

		// For users
		$res = $dbr->select(
			array( 'archive', 'text', 'user' ),
			array( 'ar_namespace', 'ar_title', 'ar_comment', 'old_text', 'ar_timestamp', 'user_registration' ),
			array( 'ar_parent_id=0', 'ar_user != 0' ),
			__METHOD__,
			array(),
			array( 	'text' => array( 'INNER JOIN', array(
					'ar_text_id=old_id' ) ),
					'user' => array( 'INNER JOIN', array(
					'ar_user=user_id' ) ) )
		);

		$output = array();
		foreach ( $res as $row ) {
			$output[] = array( 'namespace' => $row->ar_namespace,
								'title' => $row->ar_title,
								'comment' => $row->ar_comment,
								'content' => $row->old_text,
								'timestamp' => $row->ar_timestamp,
								'user-timestamp' => $row->user_registration,
								'lang' => $wgLanguageCode );
		}

		// For anons
		$res = $dbr->select(
			array( 'archive', 'text' ),
			array( 'ar_namespace', 'ar_title', 'ar_comment', 'old_text', 'ar_timestamp' ),
			array( 'ar_parent_id=0', 'ar_user = 0' ),
			__METHOD__,
			array(),
			array( 	'text' => array( 'INNER JOIN', array(
					'ar_text_id=old_id' ) ) ) );
		foreach ( $res as $row ) {
			$output[] = array( 'namespace' => $row->ar_namespace,
								'title' => $row->ar_title,
								'comment' => $row->ar_comment,
								'content' => $row->old_text,
								'timestamp' => $row->ar_timestamp,
								'user-timestamp' => 0,
								'lang' => $wgLanguageCode );
		}
		$outputStr = json_encode( $output );

		$this->output( $outputStr );


	}
}

$maintClass = 'Polymatheia';
require_once( RUN_MAINTENANCE_IF_MAIN );
