<?php

/**
 * @brief		Converter Woltlab Suite Core Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		26 Mar 2020
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Woltlab Suite Core Converter
 */
class _Woltlab extends \IPS\convert\Software
{
	/**
	 * @brief	The WCF table prefix can change depending on the number of installs
	 */
	 public static $installId = 1;

	 /**
	 * Software Name
	 *
	 * @return    string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "WoltLab Suite (3.1)";
	}

	/**
	 * Software Key
	 *
	 * @return    string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "woltlab";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertProfileFieldGroups'		=> array(
				'table'							=> 'wcf' . static::$installId . '_user_option_category',
				'where'							=> array( 'parentCategoryName=?', 'profile' )
			),
			'convertProfileFields'		=> array(
				'table'							=> 'wcf' . static::$installId . '_user_option',
				'where'							=> NULL
			),
			'convertEmoticons'				=> array(
				'table'							=> 'wcf' . static::$installId . '_smiley',
				'where'							=> NULL
			),
			'convertIgnoredUsers'			=> array(
				'table'							=> 'wcf' . static::$installId . '_user_ignore',
				'where'							=> NULL
			),
			'convertGroups'				=> array(
				'table'							=> 'wcf' . static::$installId . '_user_group',
				'where'							=> NULL
			),
			'convertMembers'				=> array(
				'table'							=> 'wcf' . static::$installId . '_user',
				'where'							=> NULL
			),
			'convertPrivateMessages'		=> array(
				'table'							=> 'wcf' . static::$installId . '_conversation',
				'where'							=> NULL
			),
			'convertPrivateMessageReplies'	=> array(
				'table'							=> 'wcf' . static::$installId . '_conversation_message',
				'where'							=> NULL,
			)
		);
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool		$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch( $table )
		{
			case 'wcf' . static::$installId . '_user_option':
				try
				{
					$where = array( "categoryName LIKE 'profile.%' OR " . $this->db->in( 'categoryName', iterator_to_array( $this->db->select( 'categoryName', static::canConvert()['convertProfileFieldGroups']['table'], static::canConvert()['convertProfileFieldGroups']['where'] ) ) ) );
					return $this->db->select( 'count(optionID)', 'wcf' . static::$installId . '_user_option', $where )->first();
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;

			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
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
			'convertProfileFieldGroups',
			'convertProfileFields',
			'convertEmoticons',
			'convertGroups',
			'convertMembers'
		);
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		switch( $method )
		{
			case 'convertProfileFieldGroups':
				$return['convertProfileFieldGroups'] = array();
				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_groups' ), 'IPS\core\ProfileFields\Group' ) AS $group )
				{
					$options[ $group->_id ] = $group->_title;
				}

				foreach( $this->db->select( '*', 'wcf' . static::$installId . '_user_option_category', array( 'parentCategoryName=?', 'profile' ) ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfgroup_{$group['categoryID']}"]	= $this->getLanguage( $group['categoryName'], 'wcf.user.option.category' );
					\IPS\Member::loggedIn()->language()->words["map_pfgroup_{$group['categoryID']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfgroup_desc' );

					$return['convertProfileFieldGroups']["map_pfgroup_{$group['categoryID']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
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

				$where = array( "categoryName LIKE 'profile.%' OR " . $this->db->in( 'categoryName', iterator_to_array( $this->db->select( 'categoryName', static::canConvert()['convertProfileFieldGroups']['table'], static::canConvert()['convertProfileFieldGroups']['where'] ) ) ) );
				foreach( $this->db->select( '*', 'wcf' . static::$installId . '_user_option', $where ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['optionID']}"]	= $this->getLanguage( $field['optionName'], 'wcf.user.option' );
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['optionID']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );

					$return['convertProfileFields']["map_pfield_{$field['optionID']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;

			case 'convertEmoticons':
				\IPS\Member::loggedIn()->language()->words['emoticon_path'] = \IPS\Member::loggedIn()->language()->addToStack( 'source_path', FALSE, array( 'sprintf' => array( 'Woltlab' ) ) );
				$return['convertEmoticons'] = array(
					'emoticon_path'				=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> NULL,
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					),
					'keep_existing_emoticons'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
						'field_default'		=> TRUE,
						'field_required'	=> FALSE,
						'field_extra'		=> array(),
						'field_hint'		=> NULL,
					)
				);
				break;

			case 'convertGroups':
				$return['convertGroups'] = array();

				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[ $group->g_id ] = $group->name;
				}

				foreach( $this->db->select( '*', 'wcf' . static::$installId . '_user_group' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['groupID']}"]			= $this->getLanguage( $group['groupName'] );
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['groupID']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['groupID']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL
					);
				}
				break;

			case 'convertMembers':
				$return['convertMembers'] = array();

				/* Find out where the photos live */
				\IPS\Member::loggedIn()->language()->words['photo_location']		= \IPS\Member::loggedIn()->language()->addToStack( 'convert_woltlab_avatar' );
				\IPS\Member::loggedIn()->language()->words['photo_location_desc']	= \IPS\Member::loggedIn()->language()->addToStack( 'convert_woltlab_avatar_desc' );
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> NULL,
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				\IPS\Member::loggedIn()->language()->words['coverphoto_location']		= \IPS\Member::loggedIn()->language()->addToStack( 'convert_woltlab_cover' );
				\IPS\Member::loggedIn()->language()->words['coverphoto_location_desc']	= \IPS\Member::loggedIn()->language()->addToStack( 'convert_woltlab_cover_desc' );
				$return['convertMembers']['coverphoto_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> NULL,
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				foreach( [ 'usertitle' ] AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, [ 'sprintf' => $field ] );
					\IPS\Member::loggedIn()->language()->words["field_{$field}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field_desc' );
					$return['convertMembers']["field_{$field}"] = [
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'no_convert',
						'field_required'		=> TRUE,
						'field_extra'			=> [
							'options'				=> [
								'no_convert'			=> \IPS\Member::loggedIn()->language()->addToStack( 'no_convert' ),
								'create_field'			=> \IPS\Member::loggedIn()->language()->addToStack( 'create_field' ),
							],
							'userSuppliedInput'		=> 'create_field'
						],
						'field_hint'			=> NULL
					];
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

		/* Non-Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildProfilePhotos', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_announcements', 'extension' => 'core_Announcement' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\core\Messenger\Message' ), 3, array( 'class' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 1, array( 'app' ) );

		return array( "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild" );
	}

	/**
	 * Fix Post Data
	 *
	 * @param	string	$post	Post
	 * @return	string	Fixed Post
	 */
	public static function fixPostData( $post )
	{
		return $post;
	}

	/**
	 * Convert profile field groups
	 *
	 * @return	void
	 */
	public function convertProfileFieldGroups()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'categoryID' );

		foreach( $this->fetch( 'wcf' . static::$installId . '_user_option_category', 'categoryID', array( 'parentCategoryName=?', 'profile' ) ) AS $group )
		{
			$name = $this->getLanguage( $group['categoryName'], 'wcf.user.option.category' );

			$merge = ( $this->app->_session['more_info']['convertProfileFieldGroups']["map_pfgroup_{$group['categoryID']}"] != 'none' ) ? $this->app->_session['more_info']['convertProfileFieldGroups']["map_pfgroup_{$group['categoryID']}"] : NULL;

			$libraryClass->convertProfileFieldGroup( array(
				'pf_group_id'		=> $group['categoryID'],
				'pf_group_name'		=> $name,
				'pf_group_order'	=> $group['showOrder']
			), $merge );

			$libraryClass->setLastKeyValue( $group['categoryID'] );
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
		$libraryClass::setKey( 'optionID' );

		$where = array( "categoryName LIKE 'profile.%' OR " . $this->db->in( 'categoryName', iterator_to_array( $this->db->select( 'categoryName', static::canConvert()['convertProfileFieldGroups']['table'], static::canConvert()['convertProfileFieldGroups']['where'] ) ) ) );
		foreach ( $this->fetch( 'wcf' . static::$installId . '_user_option', 'optionID', $where ) AS $field )
		{
			/* Birthday is special */
			if( \in_array( $field['optionName'], ['birthday', 'birthdayShowYear'] ) )
			{
				$libraryClass->setLastKeyValue( $field['optionID'] );
				continue;
			}

			$content = [];
			switch( $field['optionType'] )
			{
				case 'aboutMe':
					$type = 'Editor';
					break;
				case 'boolean':
					$type = 'YesNo';
					break;
				case 'text':
				case 'URL':
					$type = ucwords( mb_strtolower( $field['optionType'] ) );
				break;
				case 'textarea':
					$type = 'TextArea';
					break;
				case 'select':
					$type = 'Select';

					foreach( explode( "\n", $field['selectOptions'] ) as $opt )
					{
						list( $id, $lang ) = explode( ':', $opt );
						$content[ $id ] = $this->getLanguage( $lang );
					}

					break;
				default:
					$type = 'Text';
					break;
			}

			$category = $this->db->select( 'categoryID', 'wcf' . static::$installId . '_user_option_category', array( 'categoryName=?', $field['categoryName'] ) )->first();
			$info = array(
				'pf_id'				=> $field['optionID'],
				'pf_name'			=> $this->getLanguage( $field['optionName'], 'wcf.user.option' ),
				'pf_desc'			=> $this->getLanguage( $field['optionName'], 'wcf.user.option.' . $field['optionName'] . '.description' ),
				'pf_type'			=> $type,
				'pf_content'		=> json_encode( $content ),
				'pf_not_null'		=> $field['required'],
				'pf_member_hide'	=> $field['visible'] == 15 ? 'all' : 'hide',
				'pf_member_edit'	=> ( $field['editable'] >= 1 ) ? 1 : 0,
				'pf_position'		=> $field['showOrder'],
				'pf_show_on_reg'	=> $field['askDuringRegistration'],
				'pf_input_format'	=> $field['validationPattern'],
				'pf_group_id'		=> $category
			);

			$merge = ( $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$field['optionID']}"] != 'none' ) ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$field['optionID']}"] : NULL;
			$libraryClass->convertProfileField( $info, $merge );
			$libraryClass->setLastKeyValue( $field['optionID'] );
		}
	}

	/**
	 * Convert emoticons
	 *
	 * @return	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'smileyID' );

		foreach( $this->fetch( 'wcf' . static::$installId . '_smiley', 'smileyID' ) AS $row )
		{
			/* We need to figure out where our file lives - if it's remote, then we need to use file_get_contents() and pass the raw data. */
			$filepathx2 = NULL;
			$filenamex2 = NULL;

			$filename	= basename( $row['smileyPath'] );
			$filepath	= rtrim( $this->app->_session['more_info']['convertEmoticons']['emoticon_path'], '/' ) . '/' . $row['smileyPath'];

			if( !empty( $row['smileyPath2x'] ) )
			{
				$filenamex2	= basename( $row['smileyPath2x'] );
				$filepathx2	= rtrim( $this->app->_session['more_info']['convertEmoticons']['emoticon_path'], '/' ) . '/' . $row['smileyPath2x'];
			}

			$category = array(
				'display_order'	=> 1,
			);

			$title = "Converted";
			$set = array(
				'set'		=> md5( $title ),
				'title'		=> $title,
				'position'	=> $category['display_order']
			);

			$info = array(
				'id'			=> $row['smileyID'],
				'typed'			=> $row['smileyCode'],
				'filename'		=> $filename,
				'filenamex2'	=> $filenamex2,
				'emo_position'	=> $row['showOrder'],
			);

			$libraryClass->convertEmoticon( $info, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], $filepath, NULL, $filepathx2, NULL );
			$libraryClass->setLastKeyValue( $row['smileyID'] );
		}
	}

	/**
	 * Convert groups
	 *
	 * @return	void
	 */
	public function convertGroups()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'groupID' );

		foreach( $this->fetch( 'wcf' . static::$installId . '_user_group', 'groupID' ) AS $row )
		{
			/* Username Styles */
			$style = explode( '%s', $row['userOnlineMarking'] );
			$prefix = isset( $style[0] ) ? $style[0] : '';
			$suffix = isset( $style[1] ) ? $style[1] : '';

			$info = array(
				'g_id'					=> $row['groupID'],
				'g_name'				=> $this->getLanguage( $row['groupName'] ),
				'prefix'				=> $prefix,
				'suffix'				=> $suffix,
			);

			$merge = $this->app->_session['more_info']['convertGroups']["map_group_{$row['groupID']}"] != 'none' ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['groupID']}"] : NULL;

			$libraryClass->convertGroup( $info, $merge );
			$libraryClass->setLastKeyValue( $row['groupID'] );
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
	 * Convert members
	 *
	 * @return	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'userID' );

		$options = iterator_to_array( $this->db->select( 'optionID, optionName', 'wcf' . static::$installId . '_user_option' )->setKeyField( 'optionName' )->setValueField( 'optionID' ) );

		/* Profile Fields */
		$where = array( "categoryName LIKE 'profile.%' OR " . $this->db->in( 'categoryName', iterator_to_array( $this->db->select( 'categoryName', static::canConvert()['convertProfileFieldGroups']['table'], static::canConvert()['convertProfileFieldGroups']['where'] ) ) ) );
		$userProfileOptions = iterator_to_array( $this->db->select( 'optionID, optionName', 'wcf' . static::$installId . '_user_option', $where )->setKeyField('optionID')->setValueField('optionName') );

		/* User titles */
		$userTitles = [];
		foreach( $this->db->select( 'rankTitle', 'wcf' . static::$installId . '_user_rank' ) as $rank )
		{
			$userTitle[] = $this->getLanguage( $rank );
		}

		foreach ( $this->fetch( 'wcf' . static::$installId . '_user', 'userID' ) AS $row )
		{
			$fields = $this->db->select( '*', 'wcf' . static::$installId . '_user_option_value', array( 'userID=?', $row['userID'] ) )->first();
			$userFields = array();
			foreach( $options as $name => $opt )
			{
				$userFields[ $name ] = $fields[ 'userOption' . $opt ];
			}

			/* Is there a birthdate? */
			$birthdayYear = $birthdayMonth = $birthdayDay = NULL;
			if( !empty( $userFields['birthday'] ) )
			{
				list( $birthdayYear, $birthdayMonth, $birthdayDay ) = explode( '-', $userFields['birthday'] );
			}

			/* Member Groups */
			$memberGroups = iterator_to_array( $this->db->select( 'groupID', 'wcf' . static::$installId . '_user_to_group', array( 'userID=?', $row['userID'] ) )->setKeyField('groupID') );
			unset( $memberGroups[ $row['userOnlineGroupID'] ] );

			$info = array(
				'member_id'				=> $row['userID'],
				'name'					=> html_entity_decode( $row['username'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ),
				'email'					=> $row['email'],
				'password'				=> $row['password'],
				'member_group_id'		=> $row['userOnlineGroupID'],
				'joined'				=> $row['registrationDate'],
				'bday_day'				=> $birthdayDay,
				'bday_month'			=> $birthdayMonth,
				'bday_year'				=> $birthdayYear,
				'last_visit'			=> $row['lastActivityTime'],
				'last_activity'			=> $row['lastActivityTime'],
				'temp_ban'				=> $row['banned'] ? ( $row['banExpires'] ?: -1 ) : NULL,
				'mgroup_others'			=> $memberGroups,
				'signature'				=> $row['signature'],
				'allow_admin_mails'		=> $userFields['adminCanMail'],
				'member_posts'			=> $row['wbbPosts'],
				'auto_track'			=> $userFields['watchThreadOnReply'] ?  array( 'content' => 1, 'comments' => 1, 'method' => 'immediate' ) : NULL,
				'members_profile_views'	=> $row['profileHits']
			);

			/* Avatars */
			$filename = $filepath = NULL;
			if( !empty( $row['avatarID'] ) )
			{
				try
				{
					$avatar = $this->db->select( '*', 'wcf' . static::$installId . '_user_avatar', array( 'avatarID=?', $row['avatarID'] ) )->first();
					$filename = $row['avatarID'] . '-' . $avatar['fileHash'] . '.' . $avatar['avatarExtension'];
					$filepath = rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' ) . '/' . mb_substr( $avatar['fileHash'], 0, 2 );
				}
				catch( \UnderflowException $e ) {}
			}

			/* Cover Photo */
			$coverFileName = $coverFilePath = NULL;
			if( !empty( $row['coverPhotoHash'] ) )
			{
				$coverFileName = $row['userID'] . '-' . $row['coverPhotoHash'] . '.' . $row['coverPhotoExtension'];
				$coverFilePath = rtrim( $this->app->_session['more_info']['convertMembers']['coverphoto_location'], '/' ) . '/' . mb_substr( $row['coverPhotoHash'], 0, 2 );
			}

			/* Profile Fields */
			$profileFields = array();
			foreach( $userProfileOptions as $k => $v )
			{
				$profileFields[ $k ] = $userFields[ $v ];
			}

			/* Pseudo field(s) */
			foreach( [ 'usertitle' ] AS $pseudo )
			{
				/* Are we retaining? */
				if ( $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"] == 'no_convert' )
				{
					/* No, skip */
					continue;
				}

				try
				{
					/* We don't actually need this, but we need to make sure the field was created */
					$fieldId = $this->app->getLink( $pseudo, 'core_pfields_data' );
				}
				catch( \OutOfRangeException $e )
				{
					$libraryClass->convertProfileField( [
						'pf_id'				=> $pseudo,
						'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"],
						'pf_desc'			=> '',
						'pf_type'			=> 'Text',
						'pf_content'		=> '[]',
						'pf_member_hide'	=> 0,
						'pf_max_input'		=> 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
						'pf_admin_only'		=> 0,
					] );
				}

				if( $pseudo == 'usertitle' )
				{
					$profileFields[ $pseudo ] = \in_array( $row['userTitle'], $userTitles ) OR empty( $row['userTitle'] ) ? NULL : $row['userTitle'];
				}
				else
				{
					$profileFields[ $pseudo ] = $row[ $pseudo ];
				}
			}

			$libraryClass->convertMember( $info, $profileFields, $filename, $filepath, NULL, $coverFileName, $coverFilePath );

			/* Any friends need converting to followers? */
			foreach( $this->db->select( '*', 'wcf' . static::$installId . '_user_follow', array( "userID=?", $row['userID'] ) ) AS $follower )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'core',
					'follow_area'			=> 'member',
					'follow_rel_id'			=> $follower['followUserID'],
					'follow_rel_id_type'	=> 'core_members',
					'follow_member_id'		=> $follower['userID'],
					'follow_added'			=> $follower['time']
				) );
			}

			$libraryClass->setLastKeyValue( $row['userID'] );
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
		$libraryClass::setKey( 'ignoreID' );

		foreach( $this->fetch( 'wcf' . static::$installId . '_user_ignore', 'ignoreID' ) AS $ignore )
		{
			$info = array(
				'ignore_id'			=> $ignore['ignoreID'],
				'ignore_owner_id'	=> $ignore['userID'],
				'ignore_ignore_id'	=> $ignore['ignoreUserID'],
				'ignore_messages'	=> 1,
				'ignore_topics'		=> 1
			);

			$libraryClass->convertIgnoredUser( $info );
			$libraryClass->setLastKeyValue( $ignore['ignoreID'] );
		}
	}

	/**
	 * Convert PMs
	 *
	 * @return	void
	 */
	public function convertPrivateMessages()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'conversationID' );

		foreach( $this->fetch( 'wcf' . static::$installId . '_conversation', 'conversationID' ) AS $row )
		{
			$participants = iterator_to_array( $this->db->select( '*', 'wcf' . static::$installId . '_conversation_to_user', array( 'conversationID=?', $row['conversationID'] ) )->setKeyField('participantID') );

			$topic = array(
				'mt_id'				=> $row['conversationID'],
				'mt_date'			=> $row['time'],
				'mt_title'			=> $row['subject'],
				'mt_starter_id'		=> $row['userID'],
				'mt_start_time'		=> $row['time'],
				'mt_last_post_time'	=> $row['lastPostTime'],
				'mt_to_count'		=> $row['participants'],
			);

			$maps = array();
			$maps[ $row['userID'] ] = array(
				'map_user_id'		=> $row['userID'],
				'map_read_time'		=> $row['time'],
				'map_is_starter'	=> true
			);

			unset( $participants[ $row['userID'] ] );

			foreach( $participants as $participant )
			{
				$maps[ $participant['participantID'] ] = array(
					'map_user_id'		=> $participant['participantID'],
					'map_read_time'		=> $participant['lastVisitTime'],
					'map_user_active'	=> $participant['hideConversation'] ? 0 : 1,
				);
			}

			$libraryClass->convertPrivateMessage( $topic, $maps );
			$libraryClass->setLastKeyValue( $row['conversationID'] );
		}
	}

	/**
	 * Convert PM replies
	 *
	 * @return	void
	 */
	public function convertPrivateMessageReplies()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'messageID' );

		foreach( $this->fetch( 'wcf' . static::$installId . '_conversation_message', 'messageID' ) AS $row )
		{
			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $row['messageID'],
				'msg_topic_id'		=> $row['conversationID'],
				'msg_date'			=> $row['time'],
				'msg_post'			=> $row['message'],
				'msg_author_id'		=> $row['userID'],
				'msg_ip_address'	=> $row['ipAddress'],
			) );

			$libraryClass->setLastKeyValue( $row['messageID'] );
		}
	}

	/**
	 * @brief		Cache default language
	 */
	static $_defaultLanguage = NULL;

	/**
	 * Helper to fetch a WCF lang string
	 *
	 * @param	string			$itemName		WCF Language Name
	 * @param 	string			$itemPrefix		WCF Prefix for end-user input
	 * @return	string							Translated string, or original if no customisation exists
	 * @throws	\UnderflowException
	 */
	protected function getLanguage( string $itemName, string $itemPrefix=NULL ): string
	{
		if( static::$_defaultLanguage === NULL )
		{
			static::$_defaultLanguage = $this->db->select( 'languageID', 'wcf' . static::$installId . '_language', array( 'isDefault=?', 1 ) )->first();
		}

		try
		{
			return $this->db->select( 'languageItemValue', 'wcf' . static::$installId . '_language_item', array( "languageID=? AND languageItem=?", static::$_defaultLanguage, $itemName ) )->first();
		}
		catch( \UnderflowException $e )
		{
			try
			{
				return $this->db->select( 'languageItemValue', 'wcf' . static::$installId . '_language_item', array( "languageID=? AND languageItem=?", static::$_defaultLanguage, $itemPrefix . '.' . $itemName ) )->first();
			}
			catch( \UnderflowException $e ) { }

			return $itemName;
		}
	}

	/**
	 * Process a login
	 *
	 * @param	\IPS\Member		$member			The member
	 * @param	string			$password		Password from form
	 * @return	bool
	 */
	public static function login( $member, $password )
	{
		/* If it's not blowfish, then we don't have a salt for it. fail. */
		if( preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $member->conv_password ) )
		{
			$salt = mb_substr( $member->conv_password, 0, 29 );
			$test = crypt( crypt( $password, $salt ), $salt );

			return \IPS\Login::compareHashes( $member->conv_password, $test );
		}

		return FALSE;
	}
}
