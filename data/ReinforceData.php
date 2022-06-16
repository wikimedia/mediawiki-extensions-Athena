<?php
ini_set( 'include_path', __DIR__ . '/../../../maintenance' );

require_once 'Maintenance.php';

class ReinforceData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Athena' );
		$this->addDescription( 'Reinforces based on existing data' );
	}

	public function execute() {
		$dbr = wfGetDB( DB_REPLICA );

		$array = [];

		$script = "<html><head></head><body><script>window.onload = function() {";

		// Get data from file
		$file = fopen( 'data.out', 'r' );
		if ( !$file ) {
			exit( 'Unable to open file!' );
		}
		// Output a line of the file until the end is reached
		while ( !feof( $file ) ) {
			$data = ( explode( ", ", fgets( $file ) ) );
			if ( count( $data ) == 2 ) {
				$array[$data[0]] = $data[1];
				// echo("$data[0], $data[1]");
			}
		}
		fclose( $file );

		$res = $dbr->select(
			[ 'athena_log' ],
			[ 'athena_log.al_id', 'al_success' ],
			[],
			__METHOD__,
			[],
			[]
		);

		foreach ( $res as $row ) {
		   // echo( "\n----------------------------------------------\n" );
		   // echo( "al_id is $row->al_id \n" );

			if ( $row->al_success ) {
				$strA = "spam";
			} else {
				$strA = "notspam";
			}
		  // echo( "Result is $strA\n" );

			if ( $array[$row->al_id] == 1 ) {
				$strB = "spam";
			} else {
				$strB = "notspam";
			}
		   // echo( "Desired is $strB\n" );

			if ( $strA != $strB ) {
				$script .= "window.open('http://188.166.153.22/b/index.php/Special:Athena/";
				if ( $strB == "spam" ) {
					// Then this is not spam at the moment
					// So delete
				   // echo ("We need to delete this");
					$script .= "create";
				} else {
				   // echo( "We need to create this");
					$script .= "delete";
				}
				$script .= "/$row->al_id/confirm');\n";
			} else {
				// echo ("We good");
			}

		}

		$script .= "}</script></body></html>";

		echo( $script );
	}
}

$maintClass = ReinforceData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
