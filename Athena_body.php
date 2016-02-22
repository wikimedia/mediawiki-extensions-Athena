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

class SpecialAthena extends SpecialPage {

	/**
	 * Class constants for types of log viewing
	 */
	const ALL = 0;
	const SPAM = 1;
	const NOTSPAM = 2;
	const TRAINING = 3;

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
	function execute( $par )
	{
		// Check user has the rights to access this page
		$user = $this->getUser();

		if (!$this->userCanExecute($user)) {
			$this->displayRestrictionError();
		}

		// See if they have a parameter, and if so show the relevant logs
		$parts = explode('/', $par);

		if (count($parts) === 1) {
			if ( $parts[0] == wfMessage('athena-type-0') ) {
				$this->showAthenaLogs($this::ALL);
			} else if ( $parts[0] == wfMessage('athena-type-1') ) {
				$this->showAthenaLogs($this::SPAM);
			} else if ( $parts[0] == wfMessage('athena-type-2') ) {
				$this->showAthenaLogs($this::NOTSPAM);
			} else if ( $parts[0] == wfMessage('athena-type-3') ) {
				$this->showAthenaLogs($this::TRAINING);
			} else {
				$this->showAthenaHome();
			}
		} else if ( count($parts) === 2 && $parts[0] == wfMessage('athena-id') ) {
			$this->showAthenaPage($parts[1]);
		} else if ( count($parts) === 2 && $parts[0] == wfMessage('athena-create-url') ) {
			$this->createAthenaPage($parts[1], false);
		} else if ( count($parts) === 3 && $parts[0] == wfMessage('athena-create-url') && $parts[2] == wfMessage('athena-create-confirm-url') ) {
			$this->createAthenaPage($parts[1], true);
		} else if ( count($parts) === 2 && $parts[0] == wfMessage('athena-delete-url') ) {
			$this->deleteAthenaPage($parts[1], false);
		} else if ( count($parts) === 3 && $parts[0] == wfMessage('athena-delete-url') && $parts[2] == wfMessage('athena-create-confirm-url') ) {
			$this->deleteAthenaPage($parts[1], true);
		} else if ( count($parts) === 3 && $parts[0] == wfMessage('athena-reinforce-url') && $parts[1] == wfMessage('athena-reinforce-spam-url') ) {
			$this->reinforceAthenaPage($parts[2], true);
		} else if ( count($parts) === 3 && $parts[0] == wfMessage('athena-reinforce-url') && $parts[1] == wfMessage('athena-reinforce-not-spam-url') ) {
			$this->reinforceAthenaPage($parts[2], false);
		} else {
			$this->showAthenaHome();
		}
	}

	/**
	 * Displays links to the various log views
	 */
	public function showAthenaHome() {
		$output = $this->getOutput();
		$this->setHeaders();
		$output->setPagetitle( wfMessage( 'athena-title' ) );

		$output->addWikiMsg( 'athena-pagetext' );

		$output->addWikiText( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-0' ) . '|' . wfMessage( 'athena-type-desc-0' ) . ']]' );
		$output->addWikiText( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-1' ) . '|' . wfMessage( 'athena-type-desc-1' ) . ']]' );
		$output->addWikiText( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-2' ) . '|' . wfMessage( 'athena-type-desc-2' ) . ']]' );
		$output->addWikiText( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-3' ) . '|' . wfMessage( 'athena-type-desc-3' ) . ']]' );
	}

	/**
	 * Shows the Athena logs of the given type
	 *
	 * @param $type integer the log type the user wants to see
	 */
	public function showAthenaLogs( $type ) {
		$output = $this->getOutput();
		$this->setHeaders();
		$output->setPagetitle( wfMessage( 'athena-title' ) );

		$conds = '';
		$showStatus = false;

		if ( $type === $this::ALL ) {
			$output->addWikiMsg( 'athena-pagetext-0' );
			$showStatus = true;
		} else if ( $type === $this::SPAM ) {
			$output->addWikiMsg( 'athena-pagetext-1' );
			$conds = 'al_success = 0';
		} else if ( $type === $this::NOTSPAM ) {
			$output->addWikiMsg( 'athena-pagetext-2' );
			$conds = 'al_success = 1';
		} else {
			$output->addWikiMsg( 'athena-pagetext-3' );
			$conds = 'al_success = 2';
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'athena_log', 'athena_page_details' ),
			array( 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_user', 'apd_timestamp', 'al_success', 'al_overridden' ),
			$conds,
			__METHOD__,
			array( 'ORDER BY' => 'al_id' ),
			array( 'athena_page_details' => array( 'INNER JOIN', array( 'athena_log.al_id=athena_page_details.al_id' ) ) )
		);

		$tableStr = '<table class="wikitable"><thead><th>' . wfMessage( 'athena-log-id' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-view-title' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-view-user' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-log-date' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-view-athena-value' ) . '</th>';

		if ( $showStatus ) {
			$tableStr .= '<th>' . wfMessage( 'athena-view-result' ) . '</th>';
		}

		if ( $type === $this::TRAINING ) {
			$tableStr .= '<th>' . wfMessage( 'athena-view-overridden' ) . '</th>';
		}

		$tableStr .= '<th>' . wfMessage( 'athena-log-view' ) . '</th></thead><tbody>';

		foreach ( $res as $row ) {
			$tableStr .= '<tr>';
			$tableStr .= '<td>' . $row->al_id . '</td>';

			// Make a pretty title
			$title = Title::newFromText( stripslashes( $row->apd_title ), $row->apd_namespace );
			$link = $output->parse( '[[:' . $title->getFullText() . ']]' );
			$tableStr .= '<td>' . $link . '</td>';

			// Get the user
			if ( $row->apd_user != 0 ) {
				$user = User::newFromId( $row->apd_user );
				$link = $output->parse( '[[' . $user->getUserPage() . '|' . $user->getName() . ']]' );
				$tableStr .= '<td>' . $link . '</td>';
			} else {
				$tableStr .= '<td>' . wfMessage( 'athena-anon' ) . '</td>';
			}
			$tableStr .= '<td>' . $row->apd_timestamp . '</td>';
			$tableStr .= '<td>' . $row->al_value . '</td>';

			if ( $showStatus ) {
				if ( $row->al_success == 1 ) {
					$tableStr .= '<td>' . wfMessage( 'athena-not-blocked' ) . '</td>';
				} else if ( $row->al_success == 0 ) {
					$tableStr .= '<td>' . wfMessage( 'athena-blocked' ) . '</td>';
				} else {
					$tableStr .= '<td>' . wfMessage( 'athena-training' ) . '</td>';
				}
			}

			if ( $type === $this::TRAINING ) {
				if ( $row->al_overridden ) {
					$tableStr .= '<td>' . wfMessage('athena-yes') . '</td>';
				} else {
					$tableStr .= '<td>' . wfMessage('athena-no') . '</td>';
				}

			}

			$link = $output->parse( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' .  wfMessage( 'athena-id' ) . '/' . $row->al_id . '|' . wfMessage( 'athena-log-view' ) . ']]' );
			$tableStr .= '<td>' . $link . '</td>';

			$tableStr .= '</tr>';
		}

		$tableStr .= '</tbody></table>';
		$output->addHTML( $tableStr );
	}

	/**
	 * Shows the details for a given Athena ID
	 * TODO add back buttons
	 *
	 * @param $id integer the id of the page they want to see
	 */
	public function showAthenaPage( $id ) {
		global $wgAthenaTraining;
		
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPagetitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-viewing', $id ) );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
			array( 'athena_log', 'athena_page_details', 'athena_calculations' ),
			array( 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_user', 'apd_timestamp', 'al_success',
				'apd_comment', 'apd_content', 'al_user_age', 'al_links', 'al_link_percentage', 'al_syntax', 'apd_language',
					'al_language', 'al_wanted', 'al_deleted', 'al_overridden', 'page_id',
					'ac_p_spam', 'ac_p_lang', 'ac_p_langandspam', 'ac_p_langgivenspam',	'ac_p_deleted',
					'ac_p_deletedandspam', 'ac_p_deletedgivenspam', 'ac_p_wanted',	'ac_p_wantedandspam',
					'ac_p_wantedgivenspam', 'ac_p_user', 'ac_p_userandspam', 'ac_p_usergivenspam', 'ac_p_titlelength',
					'ac_p_titlelengthandspam', 'ac_p_titlelengthgivenspam',  'ac_p_namespace', 'ac_p_namespaceandspam',
  					'ac_p_namespacegivenspam', 'ac_p_syntax', 'ac_p_syntaxandspam', 'ac_p_syntaxgivenspam',
					'ac_p_links', 'ac_p_linksandspam', 'ac_p_linksgivenspam' ),
			array( 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id, 'athena_calculations.al_id' => $id ),
			__METHOD__,
			array()
		);

		if ( $res ) {
			// Start info box at the top
			$tableStr = '<table class="wikitable"><tbody>';

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-title' ) . '</td>';
			// Make a pretty title
			$title = Title::newFromText( stripslashes( $res->apd_title ), $res->apd_namespace );
			$tableStr .= '<td>' . $output->parse( '[[:' . $title->getFullText() . ']]' ) . '</td></tr>';

			// Get the user
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-user' ) . '</td>';
			if ( $res->apd_user != 0 ) {
				$user = User::newFromId( $res->apd_user );
				$link = $output->parse( '[[' . $user->getUserPage() . '|' . $user->getName() . ']]' );
				$tableStr .= '<td>' . $link . '</td>';
			} else {
				$tableStr .= '<td>' . wfMessage( 'athena-anon' ) . '</td>';
			}
			$tableStr .= '</tr><tr><td>' . wfMessage( 'athena-view-timestamp' ) . '</td>';
			$tableStr .= '<td>' . $res->apd_timestamp . '</td></tr>';

			$tableStr .= '</tr><tr><td>' . wfMessage( 'athena-view-language' ) . '</td>';
			$lang = $res->apd_language;
			$tableStr .= '<td>' . $lang . '</td></tr>';

			if ( $res->apd_comment ) {
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-comment' ) . '</td>';
				$tableStr .= '<td>' . stripslashes ( $res->apd_comment ) . '</td></tr>';
			}

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-athena-value' ) . '</td><td>' . $res->al_value . '</td></tr>';
			$tableStr .= '<tr><td colspan="2"><b>';
			if ( $res->al_success == 1 ) {
				$tableStr .= wfMessage( 'athena-view-not-blocked' );
			} else if ( $res->al_success == 0 ) {
				$tableStr .= wfMessage( 'athena-view-blocked' );
			} else {
				$tableStr .= wfMessage( 'athena-view-training' );
			}
			$tableStr .= '</b></td></tr>';
			$tableStr .= '</tbody></table>';
			$output->addHTML( $tableStr );

			// Reinforcement button
			if ( $res->al_success == 1 ) {
				if ( $res->al_overridden ) {
					$output->addWikiText( wfMessage( 'athena-view-not-blocked-reinforce-done' ) );
				} else {
					// Page has been deleted, but not within Athena's remit
					if ( $title->getArticleID() != $res->page_id ) {
						$output->addWikiText( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-delete-url' ) . '/' . $res->al_id .
								'|' . wfMessage( 'athena-view-not-blocked-reinforce' ) . ']]' );
					} else {
						$output->addHTML('<a href=' . $title->getFullURL(array('action' => 'delete')) . '>' . wfMessage( 'athena-view-not-blocked-reinforce' ) . '</a>');
					}
				}
			} else if ( $res->al_success == 0 ) {
				if ( $res->al_overridden ) {
					$output->addWikiText( wfMessage( 'athena-view-blocked-reinforce-done' ) );
				} else {
					$output->addWikiText( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-create-url' ) . '/' . $res->al_id .
							 '|' . wfMessage( 'athena-view-blocked-reinforce' ) . ']]' );
				}
			} else {
				if ( $wgAthenaTraining ) {
					if ( $res->al_overridden ) {
						$output->addWikiText( wfMessage( 'athena-view-training-reinforce-done' ) );
					} else {
						$output->addWikiText( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-reinforce-url' ) . '/' . wfMessage( 'athena-reinforce-spam-url' ) . '/' . $res->al_id .
								'|<span style="color:red;">' . wfMessage( 'athena-view-training-reinforce-spam' ) . '</span>]] [[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-reinforce-url' ) . '/' . wfMessage( 'athena-reinforce-not-spam-url' ) . '/' . $res->al_id .
								'|<span style="color:green;">' . wfMessage( 'athena-view-training-reinforce-not-spam' ) . '</span>]]' );
					}
				} else {
					$output->addWikiMsg('athena-training-off');
				}
			}

			// Replace \n with new line, remove slashes
			$content = stripslashes( str_replace( '\\n', PHP_EOL, $res->apd_content ) );

			$output->addWikiText( '== ' . wfMessage( 'athena-view-content' ) . ' ==' );
			$output->addWikiText( '<h3>' . wfMessage( 'athena-view-wikitext' ) . '</h3>' );
			$output->addHTML( '<div class="toccolours mw-collapsible mw-collapsed">' );
			$output->addHTML( '<pre>' . $content . '</pre>' );
			$output->addHTML( '</div>' );

			// For the preview, display it replacing {{PAGENAME}} with the correct title
			// TODO magic words are language dependant
			$content = str_replace( '{{PAGENAME}}', $title, $content );

			$output->addWikiText( '<h3>' . wfMessage( 'athena-view-preview' ) . '</h3>' );
			$output->addHTML( '<div class="toccolours mw-collapsible mw-collapsed">' );
			$output->addWikiText( $content );
			$output->addHTML( '</div>' );

			// Display Athena scores
			$output->addWikiText( '== ' . wfMessage( 'athena-view-results' ) . ' ==' );
			$tableStr = '<table class="wikitable"><thead><th>' . wfMessage( 'athena-view-metric' ) . '</th>';
			$tableStr .= '<th>' . wfMessage( 'athena-view-result' ) . '</th>';
			$tableStr .= '<th>' . wfMessage( 'athena-view-probability' ) . '</th>';
			$tableStr .= '<th>' . wfMessage( 'athena-view-probability-and' ) . '</th>';
			$tableStr .= '<th>' . wfMessage( 'athena-view-probability-given' ) . '</th>';
			$tableStr .= '<th>' . wfMessage( 'athena-view-probability-given-result' ) . '</th>';
			$tableStr .= '</thead><tbody>';

			$probSpam = $res->ac_p_spam;
			$numerator = '';
			$denominator = '';

			$ageStr = AthenaHelper::secondsToString( $res->al_user_age );
			if ( $ageStr === '' ) {
				if ( $res->al_user_age == -1 ) {
					$ageStr = wfMessage('athena-anon');
				} else if ( $res->al_user_age == -2 ) {
					$ageStr = wfMessage( 'athena-view-not-available' );
				} else {
					$ageStr = wfMessage( 'athena-view-imported' );
				}
			}
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-user-age' ) . '</td><td>' . $ageStr . '</td>';

			$p = $res->ac_p_user;
			$a = $res->ac_p_userandspam;
			$g = $res->ac_p_usergivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$linkPercentage = $res->al_link_percentage * 100;
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-link-percentage' ) . '</td><td>' . $linkPercentage . '% (' . $res->al_links . ' ' . wfMessage( 'athena-view-links' ) . ')</td>';

			$p = $res->ac_p_links;
			$a = $res->ac_p_linksandspam;
			$g = $res->ac_p_linksgivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-syntax' ) . '</td><td>' . AthenaHelper::syntaxTypeToString( $res->al_syntax ) . '</td>';

			$p = $res->ac_p_syntax;
			$a = $res->ac_p_syntaxandspam;
			$g = $res->ac_p_syntaxgivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-lang' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_language ) . '</td>';

			$p = $res->ac_p_lang;
			$a = $res->ac_p_langandspam;
			$g = $res->ac_p_langgivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-wanted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_wanted ) . '</td>';

			$p = $res->ac_p_wanted;
			$a = $res->ac_p_wantedandspam;
			$g = $res->ac_p_wantedgivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-deleted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_deleted ) . '</td>';

			$p = $res->ac_p_deleted;
			$a = $res->ac_p_deletedandspam;
			$g = $res->ac_p_deletedgivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$titleLength = strlen( $title->getText() );
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-title-length' ) . '</td><td>' . $titleLength . '</td>';

			$p = $res->ac_p_titlelength;
			$a = $res->ac_p_titlelengthandspam;
			$g = $res->ac_p_titlelengthgivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

			$numerator .= "$g * ";
			$denominator .= "$p * ";

			$namespace = MWNamespace::getCanonicalName( $title->getNamespace() );
			// Main will return an empty string
			if ( $namespace === '' ) {
				$namespace = wfMessage( 'athena-view-namespace-0' );
			}
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-namespace' ) . '</td><td>' . $namespace . '</td>';

			$p = $res->ac_p_namespace;
			$a = $res->ac_p_namespaceandspam;
			$g = $res->ac_p_namespacegivenspam;
			$gr = ($g*$probSpam)/$p;

			$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr></tbody></table>";

			$numerator .= "$g * ";
			$denominator .= $p;

			$tableStr .= '<table class="wikitable"><tbody>';
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-probability-spam' ) . "</td><td>$probSpam</td></tr>";
			$numerator .= $probSpam;
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-probability-calc' ) . "</td><td>$numerator<br/><hr/>$denominator</td></tr>";
			$tableStr .= '<tr><td><b>' . wfMessage( 'athena-view-athena-value' ) . '</b></td><td><b>' . $res->al_value . '</b></td></tr>';
			$tableStr .= '</tbody></table>';

			$output->addHTML( $tableStr );
		} else {
			$output->addWikiMsgArray( 'athena-viewing-error', $id );
		}
	}

	/**
	 * Creates the page with the given Athena ID
	 *
	 * @param $id integer the id of the page they want to create
	 * @param $confirmed boolean whether they have clicked confirm of not
	 */
	public function createAthenaPage( $id, $confirmed ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPagetitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-create-title', $id ) );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
				array( 'athena_log', 'athena_page_details' ),
				array( 'athena_log.al_id', 'apd_content', 'apd_comment', 'apd_namespace', 'apd_title', 'al_success', 'al_overridden', 'apd_user' ),
				array( 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ),
				__METHOD__,
				array()
		);

		// Check the Athena id exists
		if ( $res ) {
			// Check it was blocked by Athena
			if ( $res->al_success == 0 ) {
				// Check it hasn't been overridden already
				if ( $res->al_overridden == 0 ) {
					$title = Title::newFromText( stripslashes( $res->apd_title ), $res->apd_namespace );

					// At this point, we want to reinforce Athena if we've confirmed it.
					if ( $confirmed ) {
						// Let's reinforce and depending on the scenario, create the page
						if ( !$title->exists() ) {
							$wikiPage = WikiPage::factory( $title );

							// Replace \n with new line, remove slashes
							$textContent = stripslashes( str_replace( '\\n', PHP_EOL, $res->apd_content ) );

							$content = new WikitextContent( $textContent );

							$comment = stripslashes ( $res->apd_comment );

							if ( $res->apd_user != 0 ) {
								$user = User::newFromId( $res->apd_user );
								$wikiPage->doEditContent( $content, $comment, 0, false, $user, null );
							} else {
								$wikiPage->doEditContent($content, $comment);
							}

							$output->addWikiText( '[[' . $title->getFullText() . '|'. wfMessage( 'athena-create-go-page' ) . ']]' );

						}
						// Reinforce the system
						AthenaHelper::reinforceCreate( $id );
						$output->addWikiMsg( 'athena-create-reinforced' );

						$dbr->update( 'athena_log',
								array( 'al_overridden' => 1 ),
								array( 'al_id' => $id ),
								__METHOD__,
								null );
					} else {
						// Check a page with this title doesn't already exist
						if ( $title->exists() ) {
							$output->addWikiMsg( 'athena-create-already-text', $id  );
						} else {
							$output->addWikiMsg( 'athena-create-text', $id  );
						}
						$output->addWikiText( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-create-url' ) . '/' . $res->al_id .
								'/' . wfMessage( 'athena-create-confirm-url' ) . '|' . wfMessage( 'athena-create-confirm' ) . ']]' );
					}
				} else {
					$output->addWikiMsgArray( 'athena-create-error-overridden', $id );
				}
			} else {
				$output->addWikiMsgArray( 'athena-create-error-not-blocked', $id );
			}
		} else {
			$output->addWikiMsgArray( 'athena-create-error-not-exists', $id );
		}
	}

	/**
	 * Reinforces an Athena training page
	 *
	 * @param $id integer
	 * @param $spam boolean - whether its been marked for spam or not
	 */
	public function reinforceAthenaPage( $id, $spam ) {
		global $wgAthenaSpamThreshold;

		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPagetitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-reinforce-title', $id ) );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
				array( 'athena_log', 'athena_page_details' ),
				array( 'athena_log.al_id', 'al_value', 'apd_content', 'apd_comment', 'apd_namespace', 'apd_title', 'al_success', 'al_overridden', 'apd_user' ),
				array( 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ),
				__METHOD__,
				array()
		);

		// Check the Athena id exists
		if ( $res ) {
			// Check it is training data
			if ( $res->al_success == 2 ) {
				// Check it hasn't been overridden already
				if ( $res->al_overridden == 0 ) {
					// Let's figure out what we want to do now
					if ( $res->al_value > $wgAthenaSpamThreshold ) {
						// Article originally tagged as spam
						if ( $spam ) {
							// Just need to mark as overridden, which will be done later
						} else {
							AthenaHelper::reinforceCreate( $id );
						}
					} else {
						// Article originally tagged as not spam
						if ( $spam ) {
							AthenaHelper::reinforceDelete( $id );
						} else {
							// Just need to mark as overridden, which will be done later
						}
					}
					// Reinforce the system
					$output->addWikiMsg( 'athena-reinforce-reinforced' );

					$dbr->update( 'athena_log',
							array( 'al_overridden' => 1 ),
							array( 'al_id' => $id ),
							__METHOD__,
							null );

				} else {
					$output->addWikiMsgArray( 'athena-reinforce-error-overridden', $id );
				}
			} else {
				$output->addWikiMsgArray( 'athena-reinforce-error-not-blocked', $id );
			}
		} else {
			$output->addWikiMsgArray( 'athena-reinforce-error-not-exists', $id );
		}
	}

	/**
	 * Deletes the page with the given Athena ID
	 *
	 * Well that's technically a lie - really this is only used on pages that have already been deleted but not Athena reinforced
	 *
	 * @param $id integer the id of the page they want to delete
	 * @param $confirmed boolean whether they have clicked confirm of not
	 */
	public function deleteAthenaPage( $id, $confirmed ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPagetitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-delete-title', $id ) );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
				array( 'athena_log', 'athena_page_details' ),
				array( 'athena_log.al_id', 'apd_content', 'apd_comment', 'apd_namespace', 'apd_title', 'al_success', 'al_overridden', 'apd_user' ),
				array( 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ),
				__METHOD__,
				array()
		);

		// Check the Athena id exists
		if ( $res ) {
			// Check it was not blocked by Athena
			if ( $res->al_success == 1 ) {
				// Check it hasn't been overridden already
				if ( $res->al_overridden == 0 ) {
					$title = Title::newFromText( stripslashes( $res->apd_title ), $res->apd_namespace );

					if ( $title->exists() ) {
						// Page exists - point them to delete instead
						$output->addWikiMsg( 'athena-delete-still-exists' );
						$output->addHTML('<a href=' . $title->getFullURL(array('action' => 'delete')) . '>' . wfMessage( 'athena-view-not-blocked-reinforce' ) . '</a>');
					} else {
						// At this point, we want to reinforce Athena if we've confirmed it.
						if ($confirmed) {
							// Reinforce the system
							AthenaHelper::reinforceDelete($id);
							$output->addWikiMsg('athena-create-reinforced');

							$dbr->update('athena_log',
									array('al_overridden' => 1),
									array('al_id' => $id),
									__METHOD__,
									null);
						} else {
							$output->addWikiMsg('athena-delete-text', $id);

							$output->addWikiText('[[{{NAMESPACE}}:' . wfMessage('athena-title') . '/' . wfMessage('athena-delete-url') . '/' . $res->al_id .
									'/' . wfMessage('athena-create-confirm-url') . '|' . wfMessage('athena-create-confirm') . ']]');
						}
					}

				} else {
					$output->addWikiMsgArray( 'athena-delete-error-overridden', $id );
				}
			} else {
				$output->addWikiMsgArray( 'athena-delete-error-not-blocked', $id );
			}
		} else {
			$output->addWikiMsgArray( 'athena-delete-error-not-exists', $id );
		}
	}

}
