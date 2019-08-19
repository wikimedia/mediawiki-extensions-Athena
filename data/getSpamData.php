<?php
ini_set( 'include_path', dirname( __FILE__ ) . '/../../../maintenance' );

require_once( 'Maintenance.php' );

class getSpamData extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->requireExtension( 'Athena' );
        $this->addDescription( 'Gets final spam declarations for different values' );
    }

    public function execute() {
        $dbw = wfGetDB( DB_REPLICA );

        $res = $dbw->select(
            array( 'athena_log' ),
            array( 'al_id', 'al_success', 'al_overridden' ),
            array( ),
            __METHOD__,
            array(),
            array( )
        );

        foreach ( $res as $row ) {
            
            $spam = $row->al_success;
            if( $row->al_overridden ) {
                $spam = abs($spam-=1);
            }

	 if( $row->al_id > 7333 ) {
		$id = ($row->al_id)-1;
            } else {
		$id = $row->al_id;
	}
            echo( "$id, $spam\n" );
        }

    }
}

$maintClass = 'getSpamData';
require_once( RUN_MAINTENANCE_IF_MAIN );

