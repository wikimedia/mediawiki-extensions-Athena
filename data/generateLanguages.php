<?php
/**
 * A script to run franc when franc has not been run
 * Run in conjunction with fixLanguage to update the stats table
 */

/**
 * Set the correct include path for PHP so that we can run this script from
 * $IP/extensions/Athena and we don't need to move this file to
 * $IP/maintenance/.
 */
ini_set( 'include_path', dirname( __FILE__ ) . '/../../../maintenance' );

require_once( 'Maintenance.php' );

class generateLanguages extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->requireExtension( 'Athena' );
        $this->mDescription = 'Fixes a bug that caused al_language to be the wrong value';
    }

    public function execute() {
        $dbw = wfGetDB( DB_SLAVE );

        // Get all Athena logs
        $res = $dbw->select(
            array( 'athena_page_details' ),
            array( 'al_id', 'apd_language', 'apd_content' ),
            array(  ),
            __METHOD__,
            array(),
            array( )
        );

        foreach ( $res as $row ) {
            echo( "\n----------------------------------------------\n" );
            echo( "al_id is $row->al_id \n" );
            echo( "apd_language is $row->apd_language \n" );

            $content = $row->apd_content;
            if( strlen( $content ) == 0 ) {
                $code = null;
            } else {
                file_put_contents( "temp", $content );
                $code = system( "franc < temp" );
            }

            $code = AthenaHelper::convertISOCode($code);

            echo( "Language code is $code\n" );
            $str = $dbw->strencode( Language::fetchLanguageName( $code ) );

            echo( "Language name is $str \n");

           $dbw->update( 'athena_page_details',
                array( 'apd_language' => $str ),
                array( 'al_id' => $row->al_id ),
                __METHOD__,
                null );

            echo( "\n----------------------------------------------\n" );
        }
    }
}

$maintClass = 'generateLanguages';
require_once( RUN_MAINTENANCE_IF_MAIN );
