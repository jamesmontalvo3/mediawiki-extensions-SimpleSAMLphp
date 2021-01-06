<?php

namespace MediaWiki\Extension\SimpleSAMLphp\AttributeProcessor;

use MediaWiki\MediaWikiServices;

class MapGroups extends Base {

	/**
	 *
	 * @var array
	 */
	protected $groupMap = [];

	/**
	 * Reads out the attribute that holds the user groups and applies them to the local user object
	 */
	public function run() {
		$this->initGroupMap();

		$groupListDelimiter = $this->config->get( 'GroupAttributeDelimiter' );

		foreach ( $this->groupMap as $group => $rules ) {
			$group = trim( $group );
			$groupAdded = false;

			foreach ( $rules as $attrName => $needles ) {
				if ( $groupAdded == true ) {
					break;
				} elseif ( !isset( $this->attributes[$attrName] ) ) {
					if ( method_exists( MediaWikiServices::class, 'getUserGroupManager' ) ) {
						// MW 1.35+
						MediaWikiServices::getInstance()->getUserGroupManager()
							->removeUserFromGroup( $this->user, $group );
					} else {
						$this->user->removeGroup( $group );
					}
					continue;
				}
				$samlProvidedGroups = $this->attributes[$attrName];
				if ( $groupListDelimiter !== null ) {
					$samlProvidedGroups = explode( $groupListDelimiter, $samlProvidedGroups[0] );
				}
				foreach ( $needles as $needle ) {
					// Needle looks like a regex. This is an imperfect check, and will not validate
					// the regex. Admin configuring permissions is expected to supply valid regex.
					$useRegex = preg_match( "/^\/.+\/[a-z]*$/i", $needle );

					if ( $useRegex ) {
						$foundMatch = count( preg_grep( $needle, $samlProvidedGroups ) ) > 0;
					} else {
						$foundMatch = in_array( $needle, $samlProvidedGroups );
					}

					if ( $foundMatch ) {
						if ( method_exists( MediaWikiServices::class, 'getUserGroupManager' ) ) {
							// MW 1.35+
							MediaWikiServices::getInstance()->getUserGroupManager()
								->addUserToGroup( $this->user, $group );
						} else {
							$this->user->addGroup( $group );
						}

						// This differs from the original implementation: Otherwise the _last_ group
						// in the list would always determine whether a group should be added or not
						$groupAdded = true;
						break;
					} else {
						if ( method_exists( MediaWikiServices::class, 'getUserGroupManager' ) ) {
							// MW 1.35+
							MediaWikiServices::getInstance()->getUserGroupManager()
								->removeUserFromGroup( $this->user, $group );
						} else {
							$this->user->removeGroup( $group );
						}
					}
				}
			}
		}
	}

	private function initGroupMap() {
		$this->groupMap = [];
		if ( $this->config->has( 'GroupMap' ) ) {
			$this->groupMap = $this->config->get( 'GroupMap' );
		}

		# group map: [mediawiki group][saml attribute][saml attribute value]
		if ( !is_array( $this->groupMap ) ) {
			wfDebugLog( 'SimpleSAMLphp', '$wgSimpleSAMLphp_GroupMap is not an array' );
			$this->groupMap = [];
		}
	}

}
