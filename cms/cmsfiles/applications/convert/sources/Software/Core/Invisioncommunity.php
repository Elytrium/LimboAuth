<?php

/**
 * @brief		Invision Community 4.2 Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		12 July 2017
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Core Converter
 */
class _Invisioncommunity extends \IPS\convert\Software
{
	/**
	 * @brief 	Whether the versions of IPS4 match
	 */
	public static $versionMatch = FALSE;

	/**
	 * @brief 	Whether the database has been required
	 */
	public static $dbNeeded = FALSE;

	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\App	$app	The application to reference for database and other information.
	 * @param	bool				$needDB	Establish a DB connection
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( \IPS\convert\App $app, $needDB=TRUE )
	{
		/* Set filename obscuring flag */
		\IPS\convert\Library::$obscureFilenames = FALSE;

		$return = parent::__construct( $app, $needDB );

		if( $needDB )
		{
			static::$dbNeeded = TRUE;

			try
			{
				$version = $this->db->select( 'app_version', 'core_applications', array( 'app_directory=?', 'core' ) )->first();

				/* We're matching against the human version since the long version can change with patches */
				if ( $version == \IPS\Application::load( 'core' )->version )
				{
					static::$versionMatch = TRUE;
				}
			}
			catch( \IPS\Db\Exception $e ) {}
		}

		return $return;
	}

	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return 'Invision Community (' . \IPS\Application::load( 'core' )->version . ')';
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "invisioncommunity";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		if( !static::$versionMatch AND static::$dbNeeded )
		{
			throw new \IPS\convert\Exception( 'convert_invision_mismatch' );
		}
	
		return array(
			'convertAchievementBadges'	=> array(
				'table'		=> 'core_badges',
				'where'		=> NULL
			),
			'convertAchievementRanks'	=> array(
				'table'		=> 'core_member_ranks',
				'where'		=> NULL
			),
			'convertAcronyms'			=> array(
				'table'		=> 'core_acronyms',
				'where'		=> NULL
			),
			'convertEmoticons'		=> array(
				'table'		=> 'core_emoticons',
				'where'		=> NULL
			),
			'convertReactions'		=> array(
				'table'		=> 'core_reactions',
				'where'		=> NULL
			),
			'convertBanfilters'		=> array(
				'table'		=> 'core_banfilters',
				'where'		=> NULL
			),
			'convertGroups'				=> array(
				'table'		=> 'core_groups',
				'where'		=> NULL
			),
			'convertProfileFieldGroups'		=> array(
				'table'		=> 'core_pfields_groups',
				'where'		=> NULL
			),
			'convertProfileFields'		=> array(
				'table'		=> 'core_pfields_data',
				'where'		=> NULL
			),
			'convertProfanityFilters'		=> array(
				'table'		=> 'core_profanity_filters',
				'where'		=> NULL
			),
			'convertQuestionAndAnswers'		=> array(
				'table'		=> 'core_question_and_answer',
				'where'		=> NULL
			),
			'convertReputationLevels'		=> array(
				'table'		=> 'core_reputation_levels',
				'where'		=> NULL
			),
			'convertWarnActions'		=> array(
				'table'		=> 'core_members_warn_actions',
				'where'		=> NULL
			),
			'convertWarnReasons'		=> array(
				'table'		=> 'core_members_warn_reasons',
				'where'		=> NULL
			),
			'convertMembers'			=> array(
				'table'		=> 'core_members',
				'where'		=> NULL
			),
			'convertAnnouncements'		=> array(
				'table'		=> 'core_announcements',
				'where'		=> NULL
			),
			'convertDnameChanges'		=> array(
				'table'		=> 'core_member_history',
				'where'		=> array( 'log_app=? AND log_type=?', 'core', 'display_name' )
			),
			'convertStatuses'		=> array(
				'table'		=> 'core_member_status_updates',
				'where'		=> NULL
			),
			'convertStatusReplies'		=> array(
				'table'		=> 'core_member_status_replies',
				'where'		=> NULL
			),
			'convertMemberHistory'		=> array(
				'table'		=> 'core_member_history',
				'where'		=> array( 'log_app=? AND log_type!=?', 'core', 'display_name' )
			),
			'convertIgnoredUsers'		=> array(
				'table'		=> 'core_ignored_users',
				'where'		=> NULL
			),
			'convertPrivateMessages'		=> array(
				'table'		=> 'core_message_topics',
				'where'		=> NULL
			),
			'convertPrivateMessageReplies'		=> array(
				'table'		=> 'core_message_posts',
				'where'		=> NULL
			),
			'convertModerators'		=> array(
				'table'		=> 'core_moderators',
				'where'		=> NULL
			),
			'convertClubs'		=> array(
				'table'		=> 'core_clubs',
				'where'		=> NULL
			),
			'convertClubMembers'		=> array(
				'table'		=> 'core_clubs_memberships',
				'where'		=> NULL
			),
			'convertClubPages'		=> array(
				'table'		=> 'core_club_pages',
				'where'		=> NULL
			),
			'convertAttachments'		=> array(
				'table'		=> 'core_attachments',
				'where'		=> NULL
			)
		);
	}
	
	/**
	 * Can we convert passwords from this software.
	 *
	 * @return 	boolean
	 */
	public static function loginEnabled()
	{
		return TRUE;
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertEmoticons',
			'convertGroups',
			'convertMembers',
			'convertReputationLevels',
			'convertClubs',
			'convertAttachments',
			'convertReactions',
			'convertProfileFields'
		);
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Method name
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();

		switch( $method )
		{
			case 'convertEmoticons':
			case 'convertMembers':
			case 'convertReputationLevels':
			case 'convertClubs':
			case 'convertAttachments':
			case 'convertReactions':
				\IPS\Member::loggedIn()->language()->words["upload_path"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_invision_upload_input' );
				\IPS\Member::loggedIn()->language()->words["upload_path_desc"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_invision_upload_input_desc' );
				$return[ $method ] = array(
					'upload_path'				=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> isset( $this->app->_session['more_info']['convertEmoticons']['upload_path'] ) ? $this->app->_session['more_info']['convertEmoticons']['upload_path'] : NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_invision_upload_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
				);

				if( $method == 'convertEmoticons' )
				{
					$return[ $method ]['keep_existing_emoticons'] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
						'field_default'		=> TRUE,
						'field_required'	=> FALSE,
						'field_extra'		=> array(),
						'field_hint'		=> NULL,
					);
				}
				break;

			case 'convertProfileFields':
				$return['convertProfileFields'] = array();

				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_data' ), 'IPS\core\ProfileFields\Field' ) AS $field )
				{
					$options[ $field->_id ] = $field->_title;
				}

				foreach( $this->db->select( '*', 'core_pfields_data' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['pf_id']}"]		= $this->getWord( 'core_pfield_' . $field['pf_id'] );
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['pf_id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );

					$return['convertProfileFields']["map_pfield_{$field['pf_id']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;

			case 'convertGroups':
				$return['convertGroups'] = array();

				$options = array();
				$options['none'] = 'None';
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[$group->g_id] = $group->name;
				}

				foreach( $this->db->select( '*', 'core_groups' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['g_id']}"]		= $this->getWord( 'core_group_' . $group['g_id'] );
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['g_id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['g_id']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
			break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
	
	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Search Index Rebuild */
		\IPS\Content\Search\Index::i()->rebuild();
		
		/* Clear Cache and Store */
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Clubs */
		\IPS\Task::queue( 'convert', 'RecountClubMembers', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildProfilePhotos', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );
		
		return array( "f_search_index_rebuild", "f_clear_caches" );
	}

	/**
	 * Convert Achievement Badges
	 *
	 * @return	void
	 */
	public function convertAchievementBadges(): void
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'id' );

		foreach( $this->fetch( 'core_badges', 'id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_badges' );

			/* Add name after clearing other columns */
			$row['title'] = $this->getWord( 'core_badges_' . $row['id'] );

			/* Path for custom icon */
			$imagePath = $row['image'] ? $this->app->_session['more_info']['convertEmoticons']['upload_path'] . '/' . $row['image'] : NULL;
			$row['image'] = $row['image'] ? pathinfo( $imagePath, PATHINFO_BASENAME ) : NULL;

			$libraryClass->convertAchievementBadge( $row, $imagePath );
			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}

	/**
	 * Convert Achievement Ranks
	 *
	 * @return	void
	 */
	public function convertAchievementRanks(): void
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'id' );

		foreach( $this->fetch( 'core_member_ranks', 'id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_member_ranks' );

			/* Add name after clearing other columns */
			$row['title'] = $this->getWord( 'core_member_rank_' . $row['id'] );

			/* Path for custom icon */
			$imagePath = $row['icon'] ? $this->app->_session['more_info']['convertEmoticons']['upload_path'] . '/' . $row['icon'] : NULL;

			$libraryClass->convertAchievementRank( $row, $imagePath );
			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}

	/**
	 * Convert acronym
	 *
	 * @return 	void
	 */
	public function convertAcronyms()
	{
		$libraryClass = $this->getLibrary();	
		$libraryClass::setKey( 'a_id' );

		foreach( $this->fetch( 'core_acronym', 'a_id' ) as $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_acronym' );

			$libraryClass->convertAcronym( $row );
		}

		$libraryClass->setLastKeyValue( $row['a_id'] );
	}

	/**
	 * Convert emoticons
	 *
	 * @return	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'core_emoticons', 'id' ) AS $row )
		{
			$setTitle = $this->getWord( 'core_emoticon_group_' . $row['emo_set'] );
			
			$set = array(
				'set'		=> md5( $setTitle ),
				'title'		=> $setTitle,
				'position'	=> $row['emo_set_position']
			);

			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_emoticons' );

			/* Remap rows */
			$name = explode( '/', $row['image'] );
			$row['filename'] = isset( $name[1] ) ? $name[1] : $name[0];
			$path = ( isset( $name[1] ) ) ? $this->app->_session['more_info']['convertEmoticons']['upload_path'] . "/{$name[0]}" : $this->app->_session['more_info']['convertEmoticons']['upload_path'];
			$path2 = NULL;

			if( $row['image_2x'] )
			{
				$name2 = explode( '/', $row['image_2x'] );
				$row['filenamex2'] = isset( $name2[1] ) ? $name2[1] : $name2[0];
				$path2 = ( isset( $name2[1] ) ) ? $this->app->_session['more_info']['convertEmoticons']['upload_path'] . "/{$name2[0]}" : $this->app->_session['more_info']['convertEmoticons']['upload_path'];
			}

			unset( $row['image'], $row['image_2x'] );

			/* We'll figure this out later */
			unset( $row['emo_position'] );
			
			$result = $libraryClass->convertEmoticon( $row, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], $path, NULL, $path2 );

			/* We need to manually copy any files that don't get created (duplicates) so that existing posts aren't broken */
			if( $result !== FALSE )
			{
				try
				{
					\IPS\File::get( 'core_Emoticons', $row['filename'] );
				}
				catch( \Exception $ex )
				{
					\IPS\File::create( 'core_Emoticons', $row['filename'], file_get_contents( $path . '/' . $row['filename'] ), 'emoticons', FALSE, NULL, FALSE );
				}

				if( isset( $path2 ) )
				{
					try
					{
						\IPS\File::get( 'core_Emoticons', $row['filenamex2'] );
					}
					catch( \Exception $ex )
					{
						\IPS\File::create( 'core_Emoticons', $row['filenamex2'], file_get_contents( $path2 . '/' . $row['filenamex2'] ), 'emoticons', FALSE, NULL, FALSE );
					}
				}
			}

			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}

	/**
	 * Convert ban filters
	 *
	 * @return	void
	 */
	public function convertBanFilters()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'ban_id' );
		
		foreach( $this->fetch( 'core_banfilters', 'ban_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_banfilters' );

			$libraryClass->convertBanfilter( $row );		
			$libraryClass->setLastKeyValue( $row['ban_id'] );
		}
	}
	
	/**
	 * Convert groups
	 *
	 * @return 	void
	 */
	public function convertGroups()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'g_id' );
		
		foreach( $this->fetch( 'core_groups', 'g_id' ) as $row )
		{
			$merge = ( $this->app->_session['more_info']['convertGroups']["map_group_{$row['g_id']}"] != 'none' ) ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['g_id']}"] : NULL;

			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_groups' );

			/* Add name after clearing other columns */
			$row['g_name'] = $this->getWord( 'core_group_' . $row['g_id'] );

			$libraryClass->convertGroup( $row, $merge );
			
			$libraryClass->setLastKeyValue( $row['g_id'] );
		}

		/* Now check for group promotions */
		if( \count( $libraryClass->groupPromotions ) )
		{
			foreach( $libraryClass->groupPromotions as $groupPromotion )
			{
				$libraryClass->convertGroupPromotion( $groupPromotion );
			}
		}
	}

	/**
	 * Convert profile field groups
	 *
	 * @return	void
	 */
	public function convertProfileFieldGroups()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'pf_group_id' );
		
		foreach( $this->fetch( 'core_pfields_groups', 'pf_group_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_pfields_groups' );

			/* Add name after clearing other columns */
			$row['pf_group_name'] = $this->getWord( 'core_pfieldgroups_' . $row['pf_group_id'] );

			$libraryClass->convertProfileFieldGroup( $row );		
			$libraryClass->setLastKeyValue( $row['pf_group_id'] );
		}
	}

	/**
	 * Convert profile fields
	 *
	 * @return	void
	 */
	public function convertProfileFields()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'pf_id' );
		
		foreach( $this->fetch( 'core_pfields_data', 'pf_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_pfields_data' );

			/* Add name after clearing other columns */
			$row['pf_name'] = $this->getWord( 'core_pfield_' . $row['pf_id'] );
			$row['pf_desc'] = $this->getWord( 'core_pfield_' . $row['pf_id'] . '_desc' );

			/* Merge with existing field */
			$merge = ( $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['pf_id']}"] != 'none' ) ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['pf_id']}"] : NULL;

			$libraryClass->convertProfileField( $row, $merge );
			$libraryClass->setLastKeyValue( $row['pf_id'] );
		}
	}

	/**
	 * Convert members
	 *
	 * @return 	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'member_id' );
		
		foreach( $this->fetch( 'core_members', 'member_id' ) as $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_members' );

			$profilePhotoName = NULL;
			if( !\in_array( $row['pp_photo_type'], array( 'letter', 'none', 'twitter', 'facebook', 'microsoft', '' ) ) )
			{
				$bits = explode( '/', $row['pp_main_photo'] );
				$profilePhotoName = array_pop( $bits );
			}

			$coverPhotoName = NULL;
			if( $row['pp_cover_photo'] )
			{
				$coverBits = explode( '/', $row['pp_cover_photo'] );
				$coverPhotoName = array_pop( $coverBits );
			}
			
			/* Profile Fields */
			try
			{
				$profileFields = $this->db->select( '*', 'core_pfields_content', array( "member_id=?", $row['member_id'] ) )->first();
				
				unset( $profileFields['member_id'] );
				
				/* Basic fields - we only need ID => Value, the library will handle the rest */
				foreach( $profileFields AS $key => $value )
				{
					$profileFields[ str_replace( 'field_', '', $key ) ] = $value;
				}
			}
			catch( \UnderflowException $e )
			{
				$profileFields = array();
			}

			/* Load TZ into an object and validate */
			try
			{
				$row['timezone'] = new \DateTimeZone( $row['timezone'] );
			}
			catch( \Exception $e )
			{
				unset( $row['timezone'] );
			}

			/* Rename bit options so we can pass them through the conversion method */
			$row['ips_members_bitoptions'] = $row['members_bitoptions'];
			$row['ips_members_bitoptions2'] = $row['members_bitoptions2'];
			unset( $row['members_bitoptions'], $row['members_bitoptions2'] );
	
			$path = $this->app->_session['more_info']['convertMembers']['upload_path'] . '/';
			$libraryClass->convertMember( $row, $profileFields, $profilePhotoName, $path . ( isset( $bits[0] ) ? $bits[0] : '' ), NULL, $coverPhotoName, $path . ( isset( $coverBits[0] ) ? $coverBits[0] : '' ) );

			/* Any friends need converting to followers? */
			foreach( $this->db->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'core', 'member', $row['member_id'] ) ) as $follow )
			{
				/* Remove non-standard columns */
				$this->unsetNonStandardColumns( $follow, 'core_follow' );

				$follow['follow_rel_id_type'] = 'core_members';

				$libraryClass->convertFollow( $follow );
			}

			/* And warn logs made on the profile - we'll do content specific later */
			foreach( $this->db->select( '*', 'core_members_warn_logs', array( 'wl_member=? AND ( wl_content_app=? OR wl_content_app=? )', $row['member_id'], 'core', '' ) ) AS $warn )
			{
				/* Remove non-standard columns */
				$this->unsetNonStandardColumns( $warn, 'core_members_warn_logs' );

				$warnId = $libraryClass->convertWarnLog( $warn );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warn['wl_id'],
						'log_member'	=> $warn['wl_member'],
						'log_by'		=> $warn['wl_moderator'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warn['wl_date']
					)
				);
			}

			$libraryClass->setLastKeyValue( $row['member_id'] );
		}
	}

	/**
	 * Convert profanity filters
	 *
	 * @return	void
	 */
	public function convertProfanityFilters()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'wid' );
		
		foreach( $this->fetch( 'core_profanity_filters', 'wid' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_profanity_filters' );

			$libraryClass->convertProfanityFilter( $row );		
			$libraryClass->setLastKeyValue( $row['wid'] );
		}
	}

	/**
	 * Convert Q&A
	 *
	 * @return	void
	 */
	public function convertQuestionAndAnswers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'qa_id' );
		
		foreach( $this->fetch( 'core_question_and_answer', 'qa_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_question_and_answer' );

			/* Add question after clearing other columns */
			$row['qa_question'] = $this->getWord( 'core_question_and_answer_' . $row['qa_id'] );
			$answers = json_decode( $row['qa_answers'], TRUE );
			unset( $row['qa_answers'] );

			$libraryClass->convertQuestionAndAnswer( $row, $answers );		
			$libraryClass->setLastKeyValue( $row['qa_id'] );
		}
	}

	/**
	 * Convert reputation levels
	 *
	 * @return	void
	 */
	public function convertReputationLevels()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'level_id' );
		
		foreach( $this->fetch( 'core_reputation_levels', 'level_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_reputation_levels' );

			/* Add name after clearing other columns */
			$row['level_title'] = $this->getWord( 'core_reputation_level_' . $row['level_id'] );

			/* Path for custom icon */
			$badgePath = $row['level_image'] ? $this->app->_session['more_info']['convertReputationLevels']['badge_path'] . '/' . $row['level_image'] : NULL;

			$libraryClass->convertReputationLevel( $row, $badgePath );		
			$libraryClass->setLastKeyValue( $row['level_id'] );
		}
	}

	/**
	 * Convert reactions
	 *
	 * @return	void
	 */
	public function convertReactions()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'reaction_id' );

		foreach( $this->fetch( 'core_reactions', 'reaction_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_reactions' );
			
			/* Add name after clearing other columns */
			$row['reaction_title'] = $this->getWord( 'reaction_title_' . $row['reaction_id'] );

			/* Remap rows */
			$name = explode( '/', $row['reaction_icon'] );
			$row['filename'] = isset( $name[1] ) ? $name[1] : $name[0];
			$path = ( isset( $name[1] ) ) ? $this->app->_session['more_info']['convertReactions']['upload_path'] . "/{$name[0]}" : $this->app->_session['more_info']['convertReactions']['upload_path'];

			/* We'll figure this out later */
			unset( $row['reaction_position'], $row['reaction_icon'] );

			$libraryClass->convertReaction( $row, $path );

			$libraryClass->setLastKeyValue( $row['reaction_id'] );
		}
	}

	/**
	 * Convert warn reasons
	 *
	 * @return	void
	 */
	public function convertWarnReasons()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'wr_id' );
		
		foreach( $this->fetch( 'core_members_warn_reasons', 'wr_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_members_warn_reasons' );

			/* Add name after clearing other columns */
			$row['wr_name'] = $this->getWord( 'core_warn_reason_' . $row['wr_id'] );

			$libraryClass->convertWarnReason( $row  );		
			$libraryClass->setLastKeyValue( $row['wr_id'] );
		}
	}

	/**
	 * Convert announcements
	 *
	 * @return	void
	 */
	public function convertAnnouncements()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'announce_id' );
		
		foreach( $this->fetch( 'core_announcements', 'announce_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_announcements' );

			$libraryClass->convertAnnouncement( $row  );		
			$libraryClass->setLastKeyValue( $row['announce_id'] );
		}
	}

	/**
	 * Convert display name changes
	 *
	 * @return	void
	 */
	public function convertDnameChanges()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'log_id' );
		
		foreach( $this->fetch( 'core_member_history', 'log_id', array( 'log_app=? AND log_type=?', 'core', 'display_name' ) ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_member_history' );

			/* our content is already formatted for member history, so we'll use that method instead */
			$libraryClass->convertMemberHistory( $row  );		
			$libraryClass->setLastKeyValue( $row['log_id'] );
		}
	}

	/**
	 * Convert status updates
	 *
	 * @return	void
	 */
	public function convertStatuses()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'status_id' );
		
		foreach( $this->fetch( 'core_member_status_updates', 'status_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_member_status_updates' );

			$libraryClass->convertStatus( $row  );		
			$libraryClass->setLastKeyValue( $row['status_id'] );
		}
	}

	/**
	 * Convert status updates
	 *
	 * @return	void
	 */
	public function convertStatusReplies()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'reply_id' );
		
		foreach( $this->fetch( 'core_member_status_replies', 'status_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_member_status_replies' );

			$libraryClass->convertStatusReply( $row  );		
			$libraryClass->setLastKeyValue( $row['reply_id'] );
		}
	}

	/**
	 * Convert ignored users
	 *
	 * @return	void
	 */
	public function convertIgnoredUsers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'ignore_id' );
		
		foreach( $this->fetch( 'core_ignored_users', 'ignore_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_ignored_users' );

			$libraryClass->convertIgnoredUser( $row  );		
			$libraryClass->setLastKeyValue( $row['ignore_id'] );
		}
	}

	/**
	 * Convert private messages
	 *
	 * @return	void
	 */
	public function convertPrivateMessages()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'mt_id' );
		
		foreach( $this->fetch( 'core_message_topics', 'mt_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_message_topics' );

			$maps = iterator_to_array( $this->db->select( '*', 'core_message_topic_user_map', array( 'map_topic_id=?', $row['mt_id'] ) ) );

			$libraryClass->convertPrivateMessage( $row, $maps );		
			$libraryClass->setLastKeyValue( $row['mt_id'] );
		}
	}

	/**
	 * Convert private message replies
	 *
	 * @return	void
	 */
	public function convertPrivateMessageReplies()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'msg_id' );
		
		foreach( $this->fetch( 'core_message_posts', 'msg_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_message_posts' );

			$libraryClass->convertPrivateMessageReply( $row );		
			$libraryClass->setLastKeyValue( $row['msg_id'] );
		}
	}

	/**
	 * Convert moderators
	 *
	 * @return	void
	 */
	public function convertModerators()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'core_moderators', 'id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_moderators' );

			$libraryClass->convertModerator( $row );		
			$libraryClass->setLastKeyValue( $row['id'] );
		}
	}

	/**
	 * Convert clubs
	 *
	 * @return	void
	 */
	public function convertClubs()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'id' );
		
		foreach( $this->fetch( 'core_clubs', 'id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_clubs' );

			/* Remap club id */
			$row['club_id'] = $row['id'];
			unset( $row['id'] );

			/* Path for custom icon */
			$iconFile = $row['profile_photo'] ? $this->app->_session['more_info']['convertClubs']['upload_path'] . '/' . $row['profile_photo'] : NULL;
			$coverFile = $row['cover_photo'] ? $this->app->_session['more_info']['convertClubs']['upload_path'] . '/' . $row['cover_photo'] : NULL;

			$libraryClass->convertClub( $row, $iconFile, NULL, $coverFile );		
			$libraryClass->setLastKeyValue( $row['club_id'] );
		}
	}

	/**
	 * Convert club members
	 *
	 * @return	void
	 */
	public function convertClubMembers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'club_id' );
		
		foreach( $this->fetch( 'core_clubs_memberships', 'club_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_clubs_memberships' );

			$libraryClass->convertClubMember( $row );		
			$libraryClass->setLastKeyValue( $row['club_id'] );
		}
	}

	/**
	 * Convert club pages
	 *
	 * @return	void
	 */
	public function convertClubPages()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'page_id' );

		foreach( $this->fetch( 'core_club_pages', 'page_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_club_pages' );

			$libraryClass->convertClubPage( $row );
			$libraryClass->setLastKeyValue( $row['page_id'] );
		}
	}

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'attach_id' );

		$mapTypes = array_keys( \IPS\Application::load('core')->extensions( 'core', 'EditorLocations', FALSE, FALSE ) );
		array_walk( $mapTypes, function( &$value )
		{
			$value = 'core_' . $value;
		});

		foreach( $this->fetch( 'core_attachments', 'attach_id' ) AS $row )
		{
			try
			{
				$attachmentMap = $this->db->select( '*', 'core_attachments_map', array( 'attachment_id=? AND ' . \IPS\Db::i()->in( 'location_key', $mapTypes ), $row['attach_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['attach_id'] );
				continue;
			}

			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_attachments' );
			$this->unsetNonStandardColumns( $attachmentMap, 'core_attachments_map' );

			/* Attach Types */
			switch( $attachmentMap['location_key'] )
			{
				case 'core_Messaging':
						$attachmentMap['id1_type'] = 'core_message_topics';
						$attachmentMap['id2_type'] = 'core_message_posts';
						$attachmentMap['id2_from_parent'] = FALSE;
					break;
				case 'core_Signatures':
						$attachmentMap['id1_type'] = 'core_members';
					break;
				case 'core_CustomField':
						$attachmentMap['id1_type'] = 'core_members';
						$attachmentMap['id2_type'] = 'core_pfields_data';
						$attachmentMap['id2_from_parent'] = FALSE;
					break;
				case 'core_ClubPage':
						$attachmentMap['id1_type'] = 'core_club_page';
					break;
				case 'core_Announcement':
						$attachmentMap['id1_type'] = 'core_announcement';
					break;
				default:
						// Can't convert this type
						$libraryClass->setLastKeyValue( $row['attach_id'] );
						continue 2;
					break;
			}

			/* This applies to all options */
			$attachmentMap['id1_from_parent'] = FALSE;
			$attachmentMap['id3_skip_link'] = TRUE;

			/* Remap rows */
			$name = explode( '/', $row['attach_location'] );
			$row['attach_container'] = isset( $name[1] ) ? $name[0] : NULL;
			$thumbName = explode( '/', $row['attach_thumb_location'] );
			$row['attach_thumb_container'] = isset( $thumbName[1] ) ? $thumbName[0] : NULL;

			$filePath = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['attach_location'];
			$thumbnailPath = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['attach_thumb_location'];

			unset( $row['attach_file'] );

			$libraryClass->convertAttachment( $row, $attachmentMap, $filePath, NULL, $thumbnailPath );
			$libraryClass->setLastKeyValue( $row['attach_id'] );
		}
	}

	/**
	 * Convert display name changes
	 *
	 * @return	void
	 */
	public function convertMemberHistory()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'log_id' );
		
		foreach( $this->fetch( 'core_member_history', 'log_id', array( 'log_app=? AND log_type!=?', 'core', 'display_name' ) ) AS $row )
		{
			/* Remove non-standard columns */
			$this->unsetNonStandardColumns( $row, 'core_member_history' );

			/* our content is already formatted for member history, so we'll use that method instead */
			$libraryClass->convertMemberHistory( $row );
			$libraryClass->setLastKeyValue( $row['log_id'] );
		}
	}

	/**
	 * @brief 	Default language
	 */
	protected $_defaultLanguage = NULL;

	/**
	 * Get default language id
	 *
	 * @return 	int
	 */
	protected function _defaultLanguage()
	{
		if( $this->_defaultLanguage !== NULL )
		{
			return $this->_defaultLanguage;
		}

		return $this->_defaultLanguage = $this->db->select( 'lang_id', 'core_sys_lang', array( 'lang_default=?', 1 ) )->first();
	}

	/**
	 * @brief 	Word storage
	 */
	protected $_words = [];

	/**
	 * Get word from language system
	 *
	 * @param 	string 		$key 		Word key 
	 * @return 	string
	 */
	public function getWord( $key )
	{
		if( isset( $this->_words[ $key ] ) )
		{
			return $this->_words[ $key ];
		}

		try
		{
			$result = $this->_words[ $key ] = $this->db->select( 'word_custom, word_default', 'core_sys_lang_words', array( 'word_key=? AND lang_id=?', $key, $this->_defaultLanguage() ) )->first();
		}
		/* Can't find the word, so return the original string */
		catch( \UnderflowException $e )
		{
			return $this->_words[ $key ] = $key;
		}

		$return = $result['word_default'];
		if( !empty( $result['word_custom'] ) )
		{
			$return = $result['word_custom'];
		}
	
		return $this->_words[ $key ] = $return;
	}

	/**
	 * @brief 	Table schema storage
	 */
	protected $_schemas = array();

	/**
	 * Remove non-standard columns from row data
	 *
	 * @param 	array 		$row 		Database result row
	 * @param 	string 		$table 		Table name
	 * @param 	string 		$app 		App to check
	 * @return 	void
	 */
	public function unsetNonStandardColumns( &$row, $table, $app=NULL )
	{
		if( $app === NULL )
		{
			$app = $this->app->sw;
		}

		if( !isset( $this->_schemas[ $app ] ) )
		{
			$this->_schemas[ $app ] = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/schema.json" ), TRUE );
		}

		if( !isset( $this->_schemas[ $app ][ $table ] ) )
		{
			return;
		}

		foreach( array_keys( $row ) as $key )
		{
			if( !isset( $this->_schemas[ $app ][ $table ]['columns'][ $key ] ) )
			{
				unset( $row[ $key ] );
			}
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		if( !\stristr( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'ic-merge-' . $this->app->app_id ) )
		{
			return NULL;
		}

		/* account for non-mod_rewrite links */
		$searchOn = \stristr( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'index.php' ) ? $url->data[ \IPS\Http\Url::COMPONENT_QUERY ] : $url->data[ \IPS\Http\Url::COMPONENT_PATH ];

		if( preg_match( '#/profile/([0-9]+)-(.+?)#i', $searchOn, $matches ) )
		{
			/* If we can't access profiles, don't bother trying to redirect */
			if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
			{
				return NULL;
			}

			try
			{
				$data = (string) $this->app->getLink( (int) $matches[1], array( 'members', 'core_members' ) );
				return \IPS\Member::load( $data )->url();
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}