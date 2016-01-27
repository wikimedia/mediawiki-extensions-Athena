<?php
/**
 * Special:Athena, provides a way to monitor and review Athena activity
 *
 * @file
 * @ingroup SpecialPage
 * @author Richard Cook
 * @copyright © 2016 Richard Cook
 * @license 
 */

define("ALL", 0);
define("SPAM", 1);
define("NOTSPAM", 2);

class SpecialAthena extends SpecialPage {
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'Athena', 'athena', true );
	}

	/**
	 * Main execution function
	 * 
	 * @param $par array Parameters passed to the page
	 */
	function execute( $par ) {
		// Check user has the rights to access this page
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		}
		
		// See if they have a parameter, and if so show the relevant logs
		$parts = explode( '/', $par );
		if ( count( $parts ) == 1 ) {
			// TODO: i18n this stuff
			if ($parts[0] == wfMessage("athena-type-0")) {
				$this->showAthenaLogs(constant("ALL"));
			} else if ($parts[0] == wfMessage("athena-type-1")) {
				$this->showAthenaLogs(constant("SPAM"));
			} else if ($parts[0] == wfMessage("athena-type-2")) {
				$this->showAthenaLogs(constant("NOTSPAM"));
			} else {
				$this->showAthenaHome();
			}
		} else if( count( $parts ) == 2 && $parts[0] == wfMessage("athena-id") ) {
			$this->showAthenaPage($parts[1]);
		} else {
			$this->showAthenaHome();
		}
	}

	/**
	 * Displays links to the various log views
	 */
	public function showAthenaHome() {
		global $wgContLang;

		$output = $this->getOutput();
		$this->setHeaders();
		$output->setPagetitle( wfMessage( 'athena-title' ) );
		
		$output->addWikiMsg( 'athena-pagetext' );
		
		$output->addWikiText('*[[{{NAMESPACE}}:' . wfMessage('athena-title'). '/' . wfMessage( 'athena-type-0' ) . '|' . wfMessage( 'athena-type-desc-0' ) . ']]');
		$output->addWikiText('*[[{{NAMESPACE}}:' . wfMessage('athena-title'). '/' . wfMessage( 'athena-type-1' ) . '|' . wfMessage( 'athena-type-desc-1' ) . ']]');
		$output->addWikiText('*[[{{NAMESPACE}}:' . wfMessage('athena-title'). '/' . wfMessage( 'athena-type-2' ) . '|' . wfMessage( 'athena-type-desc-2' ) . ']]');
	}

	/**
	 * Shows the Athena logs of the given type
	 * 
	 * @param $type int the log type the user wants to see
	 */
	public function showAthenaLogs( $type ) {
		$output = $this->getOutput();
		$this->setHeaders();
		
		$output->setPagetitle( wfMessage( 'athena-title' ) );

		$conds = '';
		$showStatus = false;

		if( $type == constant("ALL") ) {
			$output->addWikiMsg( 'athena-pagetext-0' );
			$showStatus = true;
		} else if( $type == constant("SPAM") ) {
			$output->addWikiMsg( 'athena-pagetext-1' );
			$conds = 'al_success = 0';
		} else {
			$output->addWikiMsg( 'athena-pagetext-2' );
			$conds = 'al_success = 1';
		}


		$db = wfGetDB( DB_SLAVE );
		$res = $db->select(
			array( 'athena_log', 'athena_page_details' ),
			array( 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_user', 'apd_timestamp', 'al_success' ),
			$conds,
			__METHOD__,
			array( 'ORDER BY' => 'al_id' ),
			array( 'athena_page_details' => array( 'INNER JOIN', array( 'athena_log.al_id=athena_page_details.al_id' ) ) )
		);

		$tableStr = '<table class="wikitable"><thead><th>ID</th><th>Title</th><th>User</th><th>Date</th><th>Athena Value</th>';

		if( $showStatus ) {
			$tableStr .= '<th>Result</th>';
		}

		$tableStr .= '<th>View</th></thead><tbody>';


		foreach( $res as $row ) {
			$tableStr .= '<tr>';
			$tableStr .= '<td>' . $row->al_id . '</td>';

			// Make a pretty title
			$title = Title::newFromText(stripslashes($row->apd_title), $row->apd_namespace);
			$tableStr .= '<td>' . $title->getFullText() . '</td>';

			// Get the user
			if( $row->apd_user != 0 ) {
				$user = User::newFromId($row->apd_user);
				$link = $output->parse( '[[User:' . $user->getName() . '|' . $user->getName() . ']]' );
				$tableStr .= '<td>' . $link . '</td>';
			} else {
				$tableStr .= '<td>Anonymous user</td>';
			}
			$tableStr .= '<td>' . $row->apd_timestamp . '</td>';
			$tableStr .= '<td>' . $row->al_value . '</td>';

			if( $showStatus ) {
				if( $row->al_success ) {
					$tableStr .= '<td>Not blocked</td>';
				} else {
					$tableStr .= '<td>Blocked</td>';
				}
			}

			//
			$link = $output->parse( '[[{{NAMESPACE}}:' . wfMessage('athena-title'). '/' .  wfMessage( 'athena-id' ) . '/' . $row->al_id . '|View]]');
			$tableStr .= '<td>' . $link . '</td>';

			$tableStr .= '</tr>';
		}

		$tableStr .= '</tbody></table>';
		$output->addHTML($tableStr);

	}

	/**
	 * Shows the details for a given Athena id
	 *
	 * TODO remove hard-coded strings
	 * TODO clean up code
	 * TODO add back buttons
	 *
	 * @param $id int the id of the page they want to see
	 */
	public function showAthenaPage( $id ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPagetitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-viewing', $id ) );

		$db = wfGetDB( DB_SLAVE );
		$res = $db->selectRow(
			array( 'athena_log', 'athena_page_details' ),
			array( 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_user', 'apd_timestamp', 'al_success',
				'apd_comment', 'apd_content', 'al_user_age', 'al_links', 'al_syntax', 'al_language',
					'al_wanted', 'al_deleted', 'al_overridden'),
			array('athena_log.al_id' => $id, 'athena_page_details.al_id' => $id),
			__METHOD__,
			array()
		);

		if( $res ) {
			// Start info box at the top
			$tableStr = '<table class="wikitable"><tbody>';

			$tableStr .= '<tr><td>Title</td>';
			// Make a pretty title
			$title = Title::newFromText(stripslashes($res->apd_title), $res->apd_namespace);
			$tableStr .= '<td>' . $output->parse('[[' . $title->getFullText() . ']]') . '</td></tr>';

			// Get the user
			$tableStr .= '<tr><td>User</td>';
			if ($res->apd_user != 0) {
				$user = User::newFromId($res->apd_user);
				$link = $output->parse('[[User:' . $user->getName() . '|' . $user->getName() . ']]');
				$tableStr .= '<td>' . $link . '</td>';
			} else {
				$tableStr .= '<td>Anonymous user</td>';
			}
			$tableStr .= '</tr><tr><td>Timestamp</td>';
			$tableStr .= '<td>' . $res->apd_timestamp . '</td></tr>';

			if ($res->apd_comment && $res->apd_comment != "NULL") {
				$tableStr .= '<tr><td>Comment</td>';
				$tableStr .= '<td>' . stripslashes ($res->apd_comment) . '</td></tr>';
			}

			$tableStr .= '<tr><td>Athena Value</td><td>' . $res->al_value . '</td></tr>';
			$tableStr .= '<tr><td colspan="2">';
			if( $res->al_success ) {
				$tableStr .= 'Athena didn\'t block this page';
			} else {
				$tableStr .= 'Athena blocked this page';
			}
			$tableStr .= '</td></tr>';
			$tableStr .= '</tbody></table>';
			$output->addHTML($tableStr);

			// Reinforcement button
			if( $res->al_success ) {
				if( $res->al_overridden ) {
					// TODO i18n
					$output->addWikiText('Page already deleted');
				} else {
					$output->addHTML('<a href=' . $title->getFullURL( array( 'action' => 'delete' ) ) . '>Delete this page</a>');
				}
			} else {
				if( $res->al_overridden ) {
					// TODO i18n
					$output->addWikiText('Page already allowed through the filter');
				} else {
					$output->addWikiText('[[Special:Create/' . $title . '|Allow this page]]');
				}
			}

			// Replace \n with new line, remove slashes
			$content = stripslashes( str_replace('\\n', PHP_EOL, $res->apd_content ) );

			$output->addWikiText("== Content ==");
			$output->addWikiText("<h3>WikiText</h3>");
			$output->addHTML('<div class="toccolours mw-collapsible mw-collapsed">');
			$output->addHTML('<pre>' . $content . '</pre>');
			$output->addHTML('</div>');
			// For the preview, display it replacing {{PAGENAME}} with the correct title
			$content = str_replace( '{{PAGENAME}}', $title, $content );

			$output->addWikiText("<h3>Preview</h3>");
			$output->addHTML('<div class="toccolours mw-collapsible mw-collapsed">');
			$output->addWikiText($content);
			$output->addHTML('</div>');

			// Display Athena scores
			$output->addWikiText( "== Athena Results ==" );
			$tableStr = "<table class=\"wikitable\"><thead><th>Metric</th><th>Score</th></thead><tbody>";

			$ageStr = AthenaHelper::secondsToString($res->al_user_age);
			if( $ageStr == '' ) {
				$ageStr = 'n/a';
			}
			$tableStr .= "<tr><td>User age</td><td>" . $ageStr . "</td></tr>";
			$tableStr .= "<tr><td>Number of links</td><td>" . $res->al_links . " links</td></tr>";
			$tableStr .= "<tr><td>Syntax classification</td><td>" . AthenaHelper::syntaxTypeToString( $res->al_syntax ) . "</td></tr>";
			$tableStr .= "<tr><td>Same language</td><td>" . AthenaHelper::boolToString( $res->al_language ) . "</td></tr>";
			$tableStr .= "<tr><td>Wanted page</td><td>" . AthenaHelper::boolToString( $res->al_wanted ) . "</td></tr>";
			$tableStr .= "<tr><td>Deleted page</td><td>" . AthenaHelper::boolToString( $res->al_deleted ) . "</td></tr>";
			$tableStr .= "<tr><td><b>Total</b></td><td><b>" . $res->al_value . "</b></td></tr>";
			$tableStr .= "</tbody></table>";

			$output->addHTML($tableStr);
		} else {
			$output->addWikiMsgArray('athena-viewing-error', $id);
		}
	}
}
