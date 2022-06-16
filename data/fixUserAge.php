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

class fixUserAge extends Maintenance {
	public function __construct() {
		parent::__construct();
	$this->requireExtension( 'Athena' );
		$this->addDescription( 'Fixes a bug that caused user and spamanduser stats to classify the same thing differently' );
	}

	public function execute() {
		$dbw = wfGetDB( DB_PRIMARY );

		// Get all Athena logs
		$res = $dbw->select(
				[ 'athena_log', 'athena_page_details' ],
				[ 'athena_log.al_id', 'al_user_age', 'apd_user', 'apd_timestamp', 'al_success' ],
				[],
				__METHOD__,
				[],
				[ 'athena_page_details' => [ 'INNER JOIN', [
						'athena_log.al_id=athena_page_details.al_id' ] ] ]
		);

		$output = [];
		$anon = 0;
		$user1 = 0;
		$user5 = 0;
		$user30 = 0;
		$user60 = 0;
		$user12 = 0;
		$user24 = 0;
		$userother = 0;

		$spamandanon = 0;
		$spamanduser1 = 0;
		$spamanduser5 = 0;
		$spamanduser30 = 0;
		$spamanduser60 = 0;
		$spamanduser12 = 0;
		$spamanduser24 = 0;
		$spamanduserother = 0;

		foreach ( $res as $row ) {
			echo( "\n----------------------------------------------\n" );
			echo( "al_id is $row->al_id \n" );
			echo( "apd_user is $row->apd_user \n" );

			if ( $row->apd_user != 0 ) {
				$user_res = $dbw->selectRow(
						[ 'user' ],
						[ 'user_registration' ],
						[ 'user_id' => $row->apd_user ],
						__METHOD__,
						[],
						[]
				);

				echo( "registration timestamp is $user_res->user_registration \n" );
				echo( "creation timestamp is $row->apd_timestamp \n" );

				$registration = wfTimestamp( TS_UNIX, $user_res->user_registration );
				$creation = wfTimestamp( TS_UNIX, $row->apd_timestamp );

				echo( "registration timestamp is $registration \n" );
				echo( "creation timestamp is $creation \n" );

				$diff = $creation - $registration;
				echo( "difference is $diff \n" );

				$value = $diff;

				if ( $value < 1 * 60 ) {
					if ( $row->al_success == 3 ) {
						$spamanduser1++;
					}
					$user1++;
				} elseif ( $value < 5 * 60 ) {
					if ( $row->al_success == 3 ) {
						$spamanduser5++;
					}
					$user5++;
				} elseif ( $value < 30 * 60 ) {
					if ( $row->al_success == 3 ) {
						$spamanduser30++;
					}
					$user30++;
				} elseif ( $value < 60 * 60 ) {
					if ( $row->al_success == 3 ) {
						$spamanduser60++;
					}
					$user60++;
				} elseif ( $value < 60 * 12 * 60 ) {
					if ( $row->al_success == 3 ) {
						$spamanduser12++;
					}
					$user12++;
				} elseif ( $value < 60 * 24 * 60 ) {
					if ( $row->al_success == 3 ) {
						$spamanduser24++;
					}
					$user24++;
				} else {
					if ( $row->al_success == 3 ) {
						$spamanduserother++;
					}
					$userother++;
				}
			} else {
				$anon++;
				$value = -1;
				$category = 'anon';
				if ( $row->al_success == 3 ) {
					$spamandanon++;
				}
			}

			$dbw->update( 'athena_log',
				[ 'al_user_age' => $value ],
				[ 'al_id' => $row->al_id ],
				__METHOD__,
				null );

			echo( "\n----------------------------------------------\n" );

		}

		echo( "\n\n\n----------------------------------------------\n\n\n" );
		echo( "Anon: $anon \n" );
		echo( "User1 $user1\n" );
		echo( "User5 $user5\n" );
		echo( "User30 $user30\n" );
		echo( "User60 $user60\n" );
		echo( "User12 $user12\n" );
		echo( "User24 $user24\n" );
		echo( "UserOther $userother\n" );
		$total = $anon + $user1 + $user5 + $user30 + $user60 + $user12 + $user24 + $userother;
		echo( "Total page: $total \n" );

		echo( "\n\n\n----------------------------------------------\n\n\n" );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $anon, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "anon"' ],
			__METHOD__,
			null );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $user1, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "user1"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $user5, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "user5"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $user30, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "user30"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $user60, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "user60"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $user12, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "user12"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $user24, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "user24"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $userother, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "userother"' ],
			__METHOD__,
			null );

		echo( "\n\n\n----------------------------------------------\n\n\n" );
		echo( "Spam and Anon: $spamandanon \n" );
		echo( "Spam and User1 $spamanduser1\n" );
		echo( "Spam and User5 $spamanduser5\n" );
		echo( "Spam and User30 $spamanduser30\n" );
		echo( "Spam and User60 $spamanduser60\n" );
		echo( "Spam and User12 $spamanduser12\n" );
		echo( "Spam and User24 $spamanduser24\n" );
		echo( "Spam and UserOther $spamanduserother\n" );
		$total = $spamandanon + $spamanduser1 + $spamanduser5 + $spamanduser30 + $spamanduser60 + $spamanduser12 + $spamanduser24 + $spamanduserother;
		echo( "Spam total: $total \n" );

		echo( "\n\n\n----------------------------------------------\n\n\n" );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamandanon, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamandanon"' ],
			__METHOD__,
			null );

		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduser1, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduser1"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduser5, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduser5"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduser30, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduser30"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduser60, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduser60"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduser12, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduser12"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduser24, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduser24"' ],
			__METHOD__,
			null );
		$dbw->update( 'athena_stats',
			[ 'as_value' => $spamanduserother, 'as_updated' => 'CURRENT_TIMESTAMP' ],
			[ 'as_name = "spamanduserother"' ],
			__METHOD__,
			null );
	}
}

$maintClass = fixUserAge::class;
require_once RUN_MAINTENANCE_IF_MAIN;
