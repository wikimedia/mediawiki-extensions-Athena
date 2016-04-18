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

class generateSyntaxes extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->mDescription = 'Generate syntax field for variables';
    }

    public function execute() {
        $dbw = wfGetDB( DB_SLAVE );

        $res = $dbw->select(
            array( 'athena_log', 'athena_page_details' ),
            array( 'athena_log.al_id', 'apd_content', 'al_success' ),
            array(  ),
            __METHOD__,
            array(),
            array( 'athena_page_details' => array( 'INNER JOIN', array(
                'athena_log.al_id=athena_page_details.al_id' ) ) )
        );

        $syntaxNone = 0;
        $syntaxBasic = 0;
        $syntaxComplex = 0;
        $bsb = 0;

        $spamandsyntaxNone = 0;
        $spamandsyntaxBasic = 0;
        $spamandsyntaxComplex = 0;
        $spamandbsb = 0;

        foreach ( $res as $row ) {
            echo( "\n----------------------------------------------\n" );
            echo( "al_id is $row->al_id \n" );

            $content = $row->apd_content;
            $result = AthenaFilters::syntaxType($content);

            if( $result == 0 ) {
                $syntaxNone++;
                if( $row->al_success == 3 ) {
                    $spamandsyntaxNone++;
                }
            } else if ( $result == 1 ) {
                $syntaxBasic++;
                if( $row->al_success == 3 ) {
                    $spamandsyntaxBasic++;
                }
            } else if ( $result == 3 ) {
                $bsb++;
                if( $row->al_success == 3 ) {
                    $spamandbsb++;
                }
            } else {
                $syntaxComplex++;
                if( $row->al_success == 3 ) {
                    $spamandsyntaxComplex++;
                }
            }

           $dbw->update( 'athena_log',
                array( 'al_syntax' => $result ),
                array( 'al_id' => $row->al_id ),
                __METHOD__,
                null );

            echo( "\n----------------------------------------------\n" );
        }

        echo( "\n\n\n----------------------------------------------\n\n\n" );
        echo( "None: $syntaxNone \n" );
        echo( "Basic $syntaxBasic\n" );
        echo( "Complex: $syntaxComplex \n" );
        echo( "BSB $bsb\n" );
        $total = $syntaxBasic + $syntaxComplex + $syntaxNone + $bsb;
        echo( "Total page: $total \n" );

        echo( "\n\n\n----------------------------------------------\n\n\n" );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $syntaxNone, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "syntaxnone"'),
            __METHOD__,
            null );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $syntaxBasic, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "syntaxbasic"'),
            __METHOD__,
            null );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $syntaxComplex, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "syntaxcomplex"'),
            __METHOD__,
            null );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $bsb, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "brokenspambot"'),
            __METHOD__,
            null );
        echo( "\n\n\n----------------------------------------------\n\n\n" );
        echo( "None: $spamandsyntaxNone \n" );
        echo( "Basic $spamandsyntaxBasic\n" );
        echo( "Complex: $spamandsyntaxComplex \n" );
        echo( "BSB $spamandbsb\n" );
        $total = $spamandsyntaxBasic + $spamandsyntaxComplex + $spamandsyntaxNone + $spamandbsb;
        echo( "Total spam: $total \n" );

        echo( "\n\n\n----------------------------------------------\n\n\n" );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $spamandsyntaxNone, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "spamandsyntaxnone"'),
            __METHOD__,
            null );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $spamandsyntaxBasic, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "spamandsyntaxbasic"'),
            __METHOD__,
            null );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $spamandsyntaxComplex, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "spamandsyntaxcomplex"'),
            __METHOD__,
            null );

        $dbw->update( 'athena_stats',
            array( 'as_value' => $spamandbsb, 'as_updated' => 'CURRENT_TIMESTAMP' ),
            array( 'as_name = "spamandbrokenspambot"'),
            __METHOD__,
            null );

    }
}

$maintClass = 'generateSyntaxes';
require_once( RUN_MAINTENANCE_IF_MAIN );
