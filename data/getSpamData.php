<?php
ini_set( 'include_path', dirname( __FILE__ ) . '/../../../maintenance' );

require_once( 'Maintenance.php' );

class getSpamData extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->mDescription = 'Gets final spam declarations for different values';
    }

    public function execute() {
        $dbw = wfGetDB( DB_SLAVE );

        $res = $dbw->select(
            array( 'athena_log' ),
            array( 'athena_log.al_id', 'al_success', 'al_overridden' ),
            array(  'al_id' < 5104),
            __METHOD__,
            array(),
            array( )
        );

        foreach ( $res as $row ) {
            
            $spam = $row->al_success;
            if( $row->al_overridden ) {
                $spam = abs($spam-=1);
            }
            
            echo( "$row->al_id, $spam\n" );
        }

    }
}

$maintClass = 'getSpamData';
require_once( RUN_MAINTENANCE_IF_MAIN );

