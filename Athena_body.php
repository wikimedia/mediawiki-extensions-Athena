<?php

use MediaWiki\Page\WikiPageFactory;

/**
 * Special:Athena, provides a way to monitor and review Athena activity
 *
 * @file
 * @ingroup SpecialPage
 * @author Richard Cook
 * @copyright ©2016 Richard Cook
 * @license GPL-3.0-only
 */
class SpecialAthena extends SpecialPage {

	/**
	 * Class constants for types of log viewing
	 */
	private const ALL = 0;
	private const SPAM = 1;
	private const NOTSPAM = 2;
	private const TRAINING = 3;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @param NamespaceInfo $namespaceInfo
	 * @param WikiPageFactory $wikiPageFactory
	 */
	function __construct(
		NamespaceInfo $namespaceInfo,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( 'Athena', 'athena', true );
		$this->namespaceInfo = $namespaceInfo;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Main execution function
	 *
	 * @param array $par Parameters passed to the page
	 */
	function execute( $par ) {
		// Check user has the rights to access this page
		$user = $this->getUser();

		if ( !$this->userCanExecute( $user ) ) {
			$this->displayRestrictionError();
		}

		// See if they have a parameter, and if so show the relevant logs
		$parts = explode( '/', $par );

		if ( count( $parts ) === 1 ) {
			if ( $parts[0] == wfMessage( 'athena-type-0' ) ) {
				$this->showAthenaLogs( self::ALL );
			} elseif ( $parts[0] == wfMessage( 'athena-type-1' ) ) {
				$this->showAthenaLogs( self::SPAM );
			} elseif ( $parts[0] == wfMessage( 'athena-type-2' ) ) {
				$this->showAthenaLogs( self::NOTSPAM );
			} elseif ( $parts[0] == wfMessage( 'athena-type-3' ) ) {
				$this->showAthenaLogs( self::TRAINING );
			} else {
				$this->showAthenaHome();
			}
		} elseif ( count( $parts ) === 2 && $parts[0] == wfMessage( 'athena-id' ) ) {
			$this->showAthenaPage( $parts[1] );
		} elseif ( count( $parts ) === 2 && $parts[0] == wfMessage( 'athena-create-url' ) ) {
			$this->createAthenaPage( $parts[1], false );
		} elseif ( count( $parts ) === 3 && $parts[0] == wfMessage( 'athena-create-url' ) && $parts[2] == wfMessage( 'athena-create-confirm-url' ) ) {
			$this->createAthenaPage( $parts[1], true );
		} elseif ( count( $parts ) === 2 && $parts[0] == wfMessage( 'athena-delete-url' ) ) {
			$this->deleteAthenaPage( $parts[1], false );
		} elseif ( count( $parts ) === 3 && $parts[0] == wfMessage( 'athena-delete-url' ) && $parts[2] == wfMessage( 'athena-create-confirm-url' ) ) {
			$this->deleteAthenaPage( $parts[1], true );
		} elseif ( count( $parts ) === 3 && $parts[0] == wfMessage( 'athena-reinforce-url' ) && $parts[1] == wfMessage( 'athena-reinforce-spam-url' ) ) {
			$this->reinforceAthenaPage( $parts[2], true );
		} elseif ( count( $parts ) === 3 && $parts[0] == wfMessage( 'athena-reinforce-url' ) && $parts[1] == wfMessage( 'athena-reinforce-not-spam-url' ) ) {
			$this->reinforceAthenaPage( $parts[2], false );
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
		$output->setPageTitle( wfMessage( 'athena-title' ) );

		$output->addWikiMsg( 'athena-pagetext' );

		$output->addWikiTextAsInterface( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-0' ) . '|' . wfMessage( 'athena-type-desc-0' ) . ']]' );
		$output->addWikiTextAsInterface( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-1' ) . '|' . wfMessage( 'athena-type-desc-1' ) . ']]' );
		$output->addWikiTextAsInterface( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-2' ) . '|' . wfMessage( 'athena-type-desc-2' ) . ']]' );
		$output->addWikiTextAsInterface( '*[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-type-3' ) . '|' . wfMessage( 'athena-type-desc-3' ) . ']]' );
	}

	/**
	 * Shows the Athena logs of the given type
	 *
	 * @param int $type the log type the user wants to see
	 */
	public function showAthenaLogs( $type ) {
		$output = $this->getOutput();
		$this->setHeaders();
		$output->setPageTitle( wfMessage( 'athena-title' ) );

		$conds = '';
		$showStatus = false;

		if ( $type === self::ALL ) {
			$output->addWikiMsg( 'athena-pagetext-0' );
			$showStatus = true;
		} elseif ( $type === self::SPAM ) {
			$output->addWikiMsg( 'athena-pagetext-1' );
			$conds = 'al_success = 0';
		} elseif ( $type === self::NOTSPAM ) {
			$output->addWikiMsg( 'athena-pagetext-2' );
			$conds = 'al_success = 1';
		} else {
			$output->addWikiMsg( 'athena-pagetext-3' );
			$conds = 'al_success = 2 OR al_success = 3 OR al_success = 4';
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'athena_log', 'athena_page_details' ],
			[ 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_user', 'apd_timestamp', 'al_success', 'al_overridden' ],
			$conds,
			__METHOD__,
			[ 'ORDER BY' => 'al_id' ],
			[ 'athena_page_details' => [ 'INNER JOIN', [ 'athena_log.al_id=athena_page_details.al_id' ] ] ]
		 );

		$tableStr = '<table class="wikitable"><thead><th>' . wfMessage( 'athena-log-id' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-view-title' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-view-user' ) . '</th>';
		$tableStr .= '<th>' . wfMessage( 'athena-log-date' ) . '</th>';

		if ( $type !== self::TRAINING ) {
			$tableStr .= '<th>' . wfMessage( 'athena-view-athena-value' ) . '</th>';
		}

		if ( $showStatus ) {
			$tableStr .= '<th>' . wfMessage( 'athena-view-result' ) . '</th>';
		}

		// if ( $type === self::TRAINING ) {
		$tableStr .= '<th>' . wfMessage( 'athena-view-overridden' ) . '</th>';
		// }

		$tableStr .= '<th>' . wfMessage( 'athena-log-view' ) . '</th></thead><tbody>';

		foreach ( $res as $row ) {
			$tableStr .= '<tr>';
			$tableStr .= '<td>' . $row->al_id . '</td>';

			// Make a pretty title
			$title = Title::newFromText( stripslashes( $row->apd_title ), $row->apd_namespace );
			$link = $output->parseAsInterface( '[[:' . $title->getFullText() . ']]' );
			$tableStr .= '<td>' . $link . '</td>';

			// Get the user
			if ( $row->apd_user != 0 ) {
				$user = User::newFromId( $row->apd_user );
				$link = $output->parseAsInterface( '[[' . $user->getUserPage() . '|' . $user->getName() . ']]' );
				$tableStr .= '<td>' . $link . '</td>';
			} else {
				$tableStr .= '<td>' . wfMessage( 'athena-anon' ) . '</td>';
			}
			$tableStr .= '<td>' . $row->apd_timestamp . '</td>';

			if ( $type !== self::TRAINING ) {
				if ( $row->al_success >= 2 ) {
					$tableStr .= '<td>' . wfMessage( 'athena-not-available' ) . '</td>';
				} else {
					$tableStr .= '<td>' . $row->al_value . '</td>';
				}
			}

			if ( $showStatus ) {
				if ( $row->al_success == 1 ) {
					$tableStr .= '<td>' . wfMessage( 'athena-not-blocked' ) . '</td>';
				} elseif ( $row->al_success == 0 ) {
					$tableStr .= '<td>' . wfMessage( 'athena-blocked' ) . '</td>';
				} else {
					$tableStr .= '<td>' . wfMessage( 'athena-training' ) . '</td>';
				}
			}

			if ( $type === self::TRAINING ) {
				if ( $row->al_overridden ) {
					if ( $row->al_success == 4 ) {
						$tableStr .= '<td>' . wfMessage( 'athena-yes-not-spam' ) . '</td>';
					} else {
						$tableStr .= '<td>' . wfMessage( 'athena-yes-spam' ) . '</td>';
					}
				} else {
					$tableStr .= '<td>' . wfMessage( 'athena-no' ) . '</td>';
				}

			} else {
				if ( $row->al_overridden ) {
					$tableStr .= '<td>' . wfMessage( 'athena-true' ) . '</td>';
				} else {
					$tableStr .= '<td>' . wfMessage( 'athena-false' ) . '</td>';
				}
			}

			$link = $output->parseAsInterface( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-id' ) . '/' . $row->al_id . '|' . wfMessage( 'athena-log-view' ) . ']]' );
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
	 * @param int $id the id of the page they want to see
	 */
	public function showAthenaPage( $id ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPageTitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-viewing', $id ) );

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->selectRow(
			[ 'athena_log', 'athena_page_details' ],
			[ 'athena_log.al_id', 'al_value', 'apd_namespace', 'apd_title', 'apd_user', 'apd_timestamp', 'al_success',
				'apd_comment', 'apd_content', 'al_user_age', 'al_links', 'al_link_percentage', 'al_syntax', 'apd_language',
				'al_language', 'al_wanted', 'al_deleted', 'al_overridden', 'page_id' ],
			[ 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ],
			__METHOD__,
			[]
		 );

		if ( $res ) {
			// Start info box at the top
			$tableStr = '<table class="wikitable"><tbody>';

			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-title' ) . '</td>';
			// Make a pretty title
			$title = Title::newFromText( stripslashes( $res->apd_title ), $res->apd_namespace );
			$tableStr .= '<td>' . $output->parseAsInterface( '[[:' . $title->getFullText() . ']]' ) . '</td></tr>';

			// Get the user
			$tableStr .= '<tr><td>' . wfMessage( 'athena-view-user' ) . '</td>';
			if ( $res->apd_user != 0 ) {
				$user = User::newFromId( $res->apd_user );
				$link = $output->parseAsInterface( '[[' . $user->getUserPage() . '|' . $user->getName() . ']]' );
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
				$tableStr .= '<td>' . stripslashes( $res->apd_comment ) . '</td></tr>';
			}

			if ( $res->al_success < 2 ) {
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-athena-value' ) . '</td><td>' . $res->al_value . '</td></tr>';
			}

			$tableStr .= '<tr><td colspan="2"><b>';
			if ( $res->al_success == 1 ) {
				$tableStr .= wfMessage( 'athena-view-not-blocked' );
			} elseif ( $res->al_success == 0 ) {
				$tableStr .= wfMessage( 'athena-view-blocked' );
			} elseif ( $res->al_success == 3 ) {
				$tableStr .= wfMessage( 'athena-view-training-spam' );
			} elseif ( $res->al_success == 4 ) {
				$tableStr .= wfMessage( 'athena-view-training-not-spam' );
			} else {
				$tableStr .= wfMessage( 'athena-view-training' );
			}
			$tableStr .= '</b></td></tr>';
			$tableStr .= '</tbody></table>';
			$output->addHTML( $tableStr );

			// Reinforcement button
			if ( $res->al_success == 1 ) {
				if ( $res->al_overridden ) {
					$output->addWikiTextAsInterface( wfMessage( 'athena-view-not-blocked-reinforce-done' ) );
				} else {
					// Page has been deleted, but not within Athena's remit
					//if ( $title->getArticleID() != $res->page_id ) {
					$output->addWikiTextAsInterface( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-delete-url' ) . '/' . $res->al_id .
						'|' . wfMessage( 'athena-view-not-blocked-reinforce' ) . ']]' );
					/*} else {
						$output->addHTML( '<a href=' . $title->getFullURL( array( 'action' => 'delete' ) ) . '>' . wfMessage( 'athena-view-not-blocked-reinforce' ) . '</a>' );
					}*/
				}
			} elseif ( $res->al_success == 0 ) {
				if ( $res->al_overridden ) {
					$output->addWikiTextAsInterface( wfMessage( 'athena-view-blocked-reinforce-done' ) );
				} else {
					$output->addWikiTextAsInterface( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-create-url' ) . '/' . $res->al_id .
						'|' . wfMessage( 'athena-view-blocked-reinforce' ) . ']]' );
				}
			} else {
				if ( $this->getConfig()->get( 'AthenaTraining' ) ) {
					if ( $res->al_overridden ) {
						if ( $res->al_success == 3 ) {
							$output->addWikiTextAsInterface( wfMessage( 'athena-view-training-reinforce-done-spam' ) );
						} else {
							$output->addWikiTextAsInterface( wfMessage( 'athena-view-training-reinforce-done-not-spam' ) );
						}
					} else {
						$output->addWikiTextAsInterface( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-reinforce-url' ) . '/' . wfMessage( 'athena-reinforce-spam-url' ) . '/' . $res->al_id .
							'|<span style="color:red;">' . wfMessage( 'athena-view-training-reinforce-spam' ) . '</span>]] [[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-reinforce-url' ) . '/' . wfMessage( 'athena-reinforce-not-spam-url' ) . '/' . $res->al_id .
							'|<span style="color:green;">' . wfMessage( 'athena-view-training-reinforce-not-spam' ) . '</span>]]' );
					}
				} else {
					$output->addWikiMsg( 'athena-training-off' );
				}
			}

			// Replace \n with new line, remove slashes
			$content = stripslashes( str_replace( '\\n', PHP_EOL, $res->apd_content ) );

			$output->addWikiTextAsInterface( '== ' . wfMessage( 'athena-view-content' ) . ' ==' );
			$output->addWikiTextAsInterface( '<h3>' . wfMessage( 'athena-view-wikitext' ) . '</h3>' );
			$output->addHTML( '<div class="toccolours mw-collapsible">' );
			$output->addHTML( '<pre>' . $content . '</pre>' );
			$output->addHTML( '</div>' );

			// For the preview, display it replacing {{PAGENAME}} with the correct title
			// TODO magic words are language dependant
			$content = str_replace( '{{PAGENAME}}', $title, $content );

			$output->addWikiTextAsInterface( '<h3>' . wfMessage( 'athena-view-preview' ) . '</h3>' );
			$output->addHTML( '<div class="toccolours mw-collapsible mw-collapsed">' );
			$output->addWikiTextAsInterface( $content );
			$output->addHTML( '</div>' );

			if ( $res->al_success < 2 ) {
				$calc = $dbr->selectRow(
					[ 'athena_calculations' ],
					[ '*' ],
					[ 'al_id' => $id ],
					__METHOD__,
					[]
				 );

				// Display Athena scores
				$output->addWikiTextAsInterface( '== ' . wfMessage( 'athena-view-results' ) . ' ==' );
				$tableStr = '<table class="wikitable"><thead><th>' . wfMessage( 'athena-view-metric' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-result' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability-and' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability-given' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability-given-result' ) . '</th>';
				$tableStr .= '</thead><tbody>';

				$probSpam = $calc->ac_p_spam;
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-prob-spam' ) . '</td><td colspan="5">' . $probSpam . '</td></tr>';

				$numerator = '';
				$denominator = '';

				$ageStr = AthenaHelper::secondsToString( $res->al_user_age );
				if ( $ageStr === '' ) {
					if ( $res->al_user_age == -1 ) {
						$ageStr = wfMessage( 'athena-anon' );
					} elseif ( $res->al_user_age == -2 ) {
						$ageStr = wfMessage( 'athena-view-not-available' );
					} else {
						$ageStr = wfMessage( 'athena-view-imported' );
					}
				}
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-user-age' ) . '</td><td>' . $ageStr . '</td>';

				$p = $calc->ac_p_user;
				$a = $calc->ac_p_userandspam;
				$g = $calc->ac_p_usergivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$linkPercentage = $res->al_link_percentage * 100;
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-link-percentage' ) . '</td><td>' . $linkPercentage . '% ( ' . $res->al_links . ' ' . wfMessage( 'athena-view-links' ) . ' )</td>';

				$p = $calc->ac_p_links;
				$a = $calc->ac_p_linksandspam;
				$g = $calc->ac_p_linksgivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-syntax' ) . '</td><td>' . AthenaHelper::syntaxTypeToString( $res->al_syntax ) . '</td>';

				$p = $calc->ac_p_syntax;
				$a = $calc->ac_p_syntaxandspam;
				$g = $calc->ac_p_syntaxgivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-lang' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_language ) . '</td>';

				$p = $calc->ac_p_lang;
				$a = $calc->ac_p_langandspam;
				$g = $calc->ac_p_langgivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-wanted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_wanted ) . '</td>';

				$p = $calc->ac_p_wanted;
				$a = $calc->ac_p_wantedandspam;
				$g = $calc->ac_p_wantedgivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-deleted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_deleted ) . '</td>';

				$p = $calc->ac_p_deleted;
				$a = $calc->ac_p_deletedandspam;
				$g = $calc->ac_p_deletedgivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$titleLength = strlen( $title->getText() );
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-title-length' ) . '</td><td>' . $titleLength . '</td>';

				$p = $calc->ac_p_titlelength;
				$a = $calc->ac_p_titlelengthandspam;
				$g = $calc->ac_p_titlelengthgivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$namespace = $this->namespaceInfo->getCanonicalName( $title->getNamespace() );
				// Main will return an empty string
				if ( $namespace === '' ) {
					$namespace = wfMessage( 'athena-view-namespace-0' );
				}
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-namespace' ) . '</td><td>' . $namespace . '</td>';

				$p = $calc->ac_p_namespace;
				$a = $calc->ac_p_namespaceandspam;
				$g = $calc->ac_p_namespacegivenspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr></tbody></table>";

				$tableStr .= '<table class="wikitable"><thead><th>' . wfMessage( 'athena-view-metric' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-result' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability-and' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability-given' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-probability-given-result' ) . '</th>';
				$tableStr .= '</thead><tbody>';

				$probSpam = $calc->ac_p_not_spam;
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-prob-not-spam' ) . '</td><td colspan="5">' . $probSpam . '</td></tr>';
				$numerator = '';
				$denominator = '';

				$ageStr = AthenaHelper::secondsToString( $res->al_user_age );
				if ( $ageStr === '' ) {
					if ( $res->al_user_age == -1 ) {
						$ageStr = wfMessage( 'athena-anon' );
					} elseif ( $res->al_user_age == -2 ) {
						$ageStr = wfMessage( 'athena-view-not-available' );
					} else {
						$ageStr = wfMessage( 'athena-view-imported' );
					}
				}
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-user-age' ) . '</td><td>' . $ageStr . '</td>';

				$p = $calc->ac_p_user;
				$a = $calc->ac_p_userandnotspam;
				$g = $calc->ac_p_usergivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";
				// TODO remove these
				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$linkPercentage = $res->al_link_percentage * 100;
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-link-percentage' ) . '</td><td>' . $linkPercentage . '% ( ' . $res->al_links . ' ' . wfMessage( 'athena-view-links' ) . ' )</td>';

				$p = $calc->ac_p_links;
				$a = $calc->ac_p_linksandnotspam;
				$g = $calc->ac_p_linksgivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-syntax' ) . '</td><td>' . AthenaHelper::syntaxTypeToString( $res->al_syntax ) . '</td>';

				$p = $calc->ac_p_syntax;
				$a = $calc->ac_p_syntaxandnotspam;
				$g = $calc->ac_p_syntaxgivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-lang' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_language ) . '</td>';

				$p = $calc->ac_p_lang;
				$a = $calc->ac_p_langandnotspam;
				$g = $calc->ac_p_langgivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$numerator .= "$g * ";
				$denominator .= "$p * ";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-wanted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_wanted ) . '</td>';

				$p = $calc->ac_p_wanted;
				$a = $calc->ac_p_wantedandnotspam;
				$g = $calc->ac_p_wantedgivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-deleted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_deleted ) . '</td>';

				$p = $calc->ac_p_deleted;
				$a = $calc->ac_p_deletedandnotspam;
				$g = $calc->ac_p_deletedgivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$titleLength = strlen( $title->getText() );
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-title-length' ) . '</td><td>' . $titleLength . '</td>';

				$p = $calc->ac_p_titlelength;
				$a = $calc->ac_p_titlelengthandnotspam;
				$g = $calc->ac_p_titlelengthgivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr>";

				$namespace = $this->namespaceInfo->getCanonicalName( $title->getNamespace() );
				// Main will return an empty string
				if ( $namespace === '' ) {
					$namespace = wfMessage( 'athena-view-namespace-0' );
				}
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-namespace' ) . '</td><td>' . $namespace . '</td>';

				$p = $calc->ac_p_namespace;
				$a = $calc->ac_p_namespaceandnotspam;
				$g = $calc->ac_p_namespacegivennotspam;
				$gr = ( $g * $probSpam ) / $p;

				$tableStr .= "<td>$p</td><td>$a</td><td>$g</td><td>$gr</td></tr></tbody></table>";

				$tableStr .= '<table class="wikitable"><tbody>';
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-athena-value' ) . "</td><td>$res->al_value</td></tr>";
				$tableStr .= '</tbody></table>';
				$output->addHTML( $tableStr );
			} else {
				// Display filter values
				$output->addWikiTextAsInterface( '== ' . wfMessage( 'athena-view-results' ) . ' ==' );
				$tableStr = '<table class="wikitable"><thead><th>' . wfMessage( 'athena-view-metric' ) . '</th>';
				$tableStr .= '<th>' . wfMessage( 'athena-view-result' ) . '</th>';
				$tableStr .= '</thead><tbody>';

				$ageStr = AthenaHelper::secondsToString( $res->al_user_age );
				if ( $ageStr === '' ) {
					if ( $res->al_user_age == -1 ) {
						$ageStr = wfMessage( 'athena-anon' );
					} elseif ( $res->al_user_age == -2 ) {
						$ageStr = wfMessage( 'athena-view-not-available' );
					} else {
						$ageStr = wfMessage( 'athena-view-imported' );
					}
				}
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-user-age' ) . '</td><td>' . $ageStr . '</td></tr>';

				$linkPercentage = $res->al_link_percentage * 100;
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-link-percentage' ) . '</td><td>' . $linkPercentage . '% ( ' . $res->al_links . ' ' . wfMessage( 'athena-view-links' ) . ' )</td></tr>';

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-syntax' ) . '</td><td>' . AthenaHelper::syntaxTypeToString( $res->al_syntax ) . '</td></tr>';

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-lang' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_language ) . '</td></tr>';

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-wanted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_wanted ) . '</td></tr>';

				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-deleted' ) . '</td><td>' . AthenaHelper::boolToString( $res->al_deleted ) . '</td></tr>';

				$titleLength = strlen( $title->getText() );
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-title-length' ) . '</td><td>' . $titleLength . '</td></tr>';

				$namespace = $this->namespaceInfo->getCanonicalName( $title->getNamespace() );
				// Main will return an empty string
				if ( $namespace === '' ) {
					$namespace = wfMessage( 'athena-view-namespace-0' );
				}
				$tableStr .= '<tr><td>' . wfMessage( 'athena-view-namespace' ) . '</td><td>' . $namespace . '</td></tr></tbody></table>';

				$output->addHTML( $tableStr );
			}
		} else {
			$output->addWikiMsgArray( 'athena-viewing-error', $id );
		}
	}

	/**
	 * Creates the page with the given Athena ID
	 *
	 * @param int $id the id of the page they want to create
	 * @param bool $confirmed whether they have clicked confirm of not
	 */
	public function createAthenaPage( $id, $confirmed ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPageTitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-create-title', $id ) );

		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->selectRow(
			[ 'athena_log', 'athena_page_details' ],
			[ 'athena_log.al_id', 'apd_content', 'apd_comment', 'apd_namespace', 'apd_title', 'al_success', 'al_overridden', 'apd_user' ],
			[ 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ],
			__METHOD__,
			[]
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
							$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

							// Replace \n with new line, remove slashes
							$textContent = stripslashes( str_replace( '\\n', PHP_EOL, $res->apd_content ) );

							$content = new WikitextContent( $textContent );

							$comment = stripslashes( $res->apd_comment );

							if ( $res->apd_user != 0 ) {
								$user = User::newFromId( $res->apd_user );
								$wikiPage->doUserEditContent( $content, $user, $comment );
							} else {
								$wikiPage->doUserEditContent( $content, $this->getUser(), $comment );
							}

							$output->addWikiTextAsInterface( '[[' . $title->getFullText() . '|' . wfMessage( 'athena-create-go-page' ) . ']]' );

						}
						// Reinforce the system
						AthenaHelper::reinforceCreate( $id );
						$output->addWikiMsg( 'athena-create-reinforced' );

						$dbw->update( 'athena_log',
							[ 'al_overridden' => 1 ],
							[ 'al_id' => $id ],
							__METHOD__
						);
					} else {
						// Check a page with this title doesn't already exist
						if ( $title->exists() ) {
							$output->addWikiMsg( 'athena-create-already-text', $id );
						} else {
							$output->addWikiMsg( 'athena-create-text', $id );
						}
						$output->addWikiTextAsInterface( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-create-url' ) . '/' . $res->al_id .
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
	 * @param int $id
	 * @param bool $spam - whether its been marked for spam or not
	 */
	public function reinforceAthenaPage( $id, $spam ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPageTitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-reinforce-title', $id ) );

		if ( $this->getConfig()->get( 'AthenaTraining' ) ) {
			$dbw = wfGetDB( DB_PRIMARY );
			$res = $dbw->selectRow(
				[ 'athena_log', 'athena_page_details' ],
				[ 'athena_log.al_id', 'al_value', 'apd_content', 'apd_comment', 'apd_namespace', 'apd_title', 'al_success', 'al_overridden', 'apd_user' ],
				[ 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ],
				__METHOD__,
				[]
			 );

			// Check the Athena id exists
			if ( $res ) {
				// Check it is training data
				if ( $res->al_success >= 2 ) {
					// Check it hasn't been overridden already
					if ( $res->al_overridden == 0 ) {
						if ( $spam ) {
							AthenaHelper::reinforceDeleteTraining( $id );
						} else {
							AthenaHelper::reinforceCreateTraining( $id );
						}

						// Reinforce the system
						$output->addWikiMsg( 'athena-reinforce-reinforced' );

						if ( $spam ) {
							$al_success = 3;
						} else {
							$al_success = 4;
						}

						$dbw->update( 'athena_log',
							[ 'al_overridden' => 1, 'al_success' => $al_success ],
							[ 'al_id' => $id ],
							__METHOD__
						);

					} else {
						$output->addWikiMsgArray( 'athena-reinforce-error-overridden', $id );
					}
				} else {
					$output->addWikiMsgArray( 'athena-reinforce-error-not-blocked', $id );
				}
			} else {
				$output->addWikiMsgArray( 'athena-reinforce-error-not-exists', $id );
			}
		} else {
			$output->addWikiMsg( 'athena-training-off' );
		}
	}

	/**
	 * Deletes the page with the given Athena ID
	 *
	 * Well that's technically a lie - really this is only used on pages that have already been deleted but not Athena reinforced
	 *
	 * @param int $id the id of the page they want to delete
	 * @param bool $confirmed whether they have clicked confirm of not
	 */
	public function deleteAthenaPage( $id, $confirmed ) {
		$output = $this->getOutput();
		$this->setHeaders();

		$output->setPageTitle( wfMessage( 'athena-title' ) . ' - ' . wfMessage( 'athena-delete-title', $id ) );

		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->selectRow(
			[ 'athena_log', 'athena_page_details' ],
			[ 'athena_log.al_id', 'apd_content', 'apd_comment', 'apd_namespace', 'apd_title', 'al_success', 'al_overridden', 'apd_user' ],
			[ 'athena_log.al_id' => $id, 'athena_page_details.al_id' => $id ],
			__METHOD__,
			[]
		 );

		// Check the Athena id exists
		if ( $res ) {
			// Check it was not blocked by Athena
			if ( $res->al_success == 1 ) {
				// Check it hasn't been overridden already
				if ( $res->al_overridden == 0 ) {
					$title = Title::newFromText( stripslashes( $res->apd_title ), $res->apd_namespace );

					// Temporary disabling this
					/*if ( $title->exists() ) {
						// Page exists - point them to delete instead
						$output->addWikiMsg( 'athena-delete-still-exists' );
						$output->addHTML( '<a href=' . $title->getFullURL( array( 'action' => 'delete' ) ) . '>' . wfMessage( 'athena-view-not-blocked-reinforce' ) . '</a>' );
					} else {*/
					// At this point, we want to reinforce Athena if we've confirmed it.
					if ( $confirmed ) {
						// Reinforce the system
						AthenaHelper::reinforceDelete( $id );
						$output->addWikiMsg( 'athena-create-reinforced' );

						$dbw->update( 'athena_log',
							[ 'al_overridden' => 1 ],
							[ 'al_id' => $id ],
							__METHOD__
						);
					} else {
						$output->addWikiMsg( 'athena-delete-text', $id );

						$output->addWikiTextAsInterface( '[[{{NAMESPACE}}:' . wfMessage( 'athena-title' ) . '/' . wfMessage( 'athena-delete-url' ) . '/' . $res->al_id .
							'/' . wfMessage( 'athena-create-confirm-url' ) . '|' . wfMessage( 'athena-create-confirm' ) . ']]' );
					}
					// }

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
