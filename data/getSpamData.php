<?php
ini_set( 'include_path', __DIR__ . '/../../../maintenance' );

require_once 'Maintenance.php';

class getSpamData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Athena' );
		$this->addDescription( 'Gets final spam declarations for different values' );
	}

	public function execute() {
		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			[ 'athena_log' ],
			[ 'al_id', 'al_success', 'al_overridden' ],
			[],
			__METHOD__,
			[],
			[]
		);

		foreach ( $res as $row ) {

			$spam = $row->al_success;
			if ( $row->al_overridden ) {
				$spam = abs( $spam -= 1 );
			}

			if ( $row->al_id > 7333 ) {
				$id = ( $row->al_id ) - 1;
			} else {
				$id = $row->al_id;
			}
			echo( "$id, $spam\n" );
		}
	}
}

$maintClass = getSpamData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
