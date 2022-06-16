<?php
/**
 * A script to fix the al_language bug
 */

/**
 * Set the correct include path for PHP so that we can run this script from
 * $IP/extensions/Athena and we don't need to move this file to
 * $IP/maintenance/.
 */
ini_set( 'include_path', __DIR__ . '/../../../maintenance' );

require_once 'Maintenance.php';

class fixLanguage extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Athena' );
		$this->addDescription( 'Fixes a bug that caused al_language to be the wrong value' );
	}

	public function execute() {
		$dbw = wfGetDB( DB_PRIMARY );

		// Get all Athena logs
		$res = $dbw->select(
			[ 'athena_log', 'athena_page_details' ],
			[ 'athena_log.al_id', 'al_language', 'apd_language', 'al_success' ],
			[],
			__METHOD__,
			[],
			[ 'athena_page_details' => [ 'INNER JOIN', [
				'athena_log.al_id=athena_page_details.al_id' ] ] ]
		);

		$same = 0;
		$diff = 0;
		$spamandsamelang = 0;
		$spamanddifflang = 0;
		foreach ( $res as $row ) {
			echo( "\n----------------------------------------------\n" );
			echo( "al_id is $row->al_id \n" );
			echo( "apd_language is $row->apd_language \n" );
			if ( $row->apd_language == "English" || $row->apd_language === null || $row->apd_language == "" ) {
				echo( "Same as wiki language\n" );
				$same++;

				$dbw->update( 'athena_log',
					[ 'al_language' => 0 ],
					[ 'al_id' => $row->al_id ],
					__METHOD__,
					null );

				if ( $row->al_success == 3 ) {
					$spamandsamelang++;
				}

			} else {
				echo( "Different to wiki language \n" );
				$diff++;
				$dbw->update( 'athena_log',
					[ 'al_language' => 1 ],
					[ 'al_id' => $row->al_id ],
					__METHOD__,
					null );

				if ( $row->al_success == 3 ) {
					$spamanddifflang++;
				}
			}
			echo( "\n----------------------------------------------\n" );
		}

		echo( "\n\n\n----------------------------------------------\n\n\n" );
		echo( "Same Language: $same \n" );
		echo( "Different Language $diff\n" );
		$total = $same + $diff;
		echo( "Total page: $total \n" );

		echo( "\n\n\n----------------------------------------------\n\n\n" );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $same, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "samelang"' ],
			__METHOD__,
			null );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $diff, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "difflang"' ],
			__METHOD__,
			null );

		echo( "\n\n\n----------------------------------------------\n\n\n" );
		echo( "Spam and same lang: $spamandsamelang \n" );
		echo( "Spam and diff lang $spamanddifflang \n" );
		$total = $spamandsamelang + $spamanddifflang;
		echo( "Spam total: $total \n" );

		echo( "\n\n\n----------------------------------------------\n\n\n" );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamandsamelang, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamandsamelang"' ],
			__METHOD__,
			null );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanddifflang, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanddifflang"' ],
			__METHOD__,
			null );
	}
}

$maintClass = fixLanguage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
