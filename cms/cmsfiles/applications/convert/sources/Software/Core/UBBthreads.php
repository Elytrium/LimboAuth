<?php
/**
 * @brief		Converter UBBthreads Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		IPS Social Suite
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * UBBThreads Core Converter
 */
class _UBBthreads extends \IPS\convert\Software
{
	/**
	 * @brief   Emoticons WHERE statement
	 * @see     convertEmoticons()
	 */
	protected static $emoticonsWhere = 'GRAEMLIN_IS_ACTIVE=1';

	/**
	 * @brief   Groups WHERE statement
	 * @see     convertGroups()
	 */
	protected static $groupsWhere = 'GROUP_IS_DISABLED=0';

	/**
	 * @brief   Ignored users WHERE statement
	 * @see     convertIgnoredUsers()
	 */
	protected static $ignoredUsersWhere = "USER_IGNORE_LIST IS NOT NULL AND USER_IGNORE_LIST NOT IN ( '', '-' )";

	/**
	 * @brief   Members WHERE statement
	 * @see     convertMembers()
	 */
	protected static $membersWhere = array( 'u.USER_LOGIN_NAME<>?', '**DONOTDELETE**' );

	/**
	 * This is.. kind of hacky, but it's used so we can try and support non-exact profanity matches without converting
	 * other regexes we don't support
	 *
	 * @brief   Profanity filters WHERE statement
	 * @see     convertProfanityFilters()
	 */
	protected static $profanityFiltersWhere = '(
		CENSOR_WORD NOT LIKE "%(.*)%" AND (
			CENSOR_WORD NOT LIKE "%(.*?)%" OR (
			    CENSOR_WORD LIKE "%(.*?)" AND CENSOR_WORD NOT LIKE "%(.*?)%(.*?)"
			)
		)
	)';

	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "UBBthreads";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "ubbthreads";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertEmoticons'				=> array(
				'table'							=> 'GRAEMLINS',
				'where'							=> static::$emoticonsWhere,
			),
			'convertBanfilters'				=> array(
				'table'							=> 'pseudo_banfilters',  /** @see countRows() */
				'where'							=> NULL
			),
			'convertGroups'					=> array(
				'table'							=> 'GROUPS',
				'where'							=> static::$groupsWhere
			),
			'convertMembers'				=> array(
				'table'							=> array( 'USERS', 'u' ),
				'where'							=> static::$membersWhere,
				'extra_steps'                   => array( 'convertMembersFollowers' ),
			),
			'convertIgnoredUsers'        	=> array(
				'table'                         => 'USER_PROFILE',
				'where'                         => static::$ignoredUsersWhere
			),
			'convertMembersFollowers'		=> array(
				'table'							=> 'WATCH_LISTS',
				'where'							=> array( 'WATCH_TYPE=?', 'u' )
			),
			'convertPrivateMessages'		=> array(
				'table'							=> 'PRIVATE_MESSAGE_TOPICS',
				'where'							=> NULL
			),
			'convertPrivateMessageReplies'	=> array(
				'table'							=> 'PRIVATE_MESSAGE_POSTS',
				'where'							=> NULL
			),
			'convertProfanityFilters'		=> array(
				'table'							=> 'CENSOR_LIST',
				'where'							=> static::$profanityFiltersWhere
			),
		);
	}

	/**
	 * Allows software to add additional menu row options
	 *
	 * @return	array
	 */
	public function extraMenuRows()
	{
		$rows = array();
		$count = $this->countRows( static::canConvert()['convertMembersFollowers']['table'], static::canConvert()['convertMembersFollowers']['where'] );

		if( $count )
		{
			$rows['convertMembersFollowers'] = array(
				'step_method'		=> 'convertMembersFollowers',
				'step_title'		=> 'convert_follows',
				'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', array( 'follow_app=? and follow_area=?', 'core', 'member' ) ),
				'source_rows'		=> $count,
				'per_cycle'			=> 200,
				'dependencies'		=> array( 'convertMembers' ),
				'link_type'			=> 'core_follow',
			);
		}

		return $rows;
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
		switch ( $table )
		{
			case 'pseudo_banfilters':
				return parent::countRows( 'BANNED_EMAILS' )
				     + parent::countRows( 'BANNED_HOSTS' )
				     + parent::countRows( 'RESERVED_NAMES' );
		}

		return parent::countRows( $table, $where, $recache );
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
			'convertMembers'
		);
	}

	/**
	 * Attempt to convert a textual date(time) representation to a DateTime instance
	 *
	 * @param   string  $date	Date to try to convert
	 * @return  \IPS\DateTime|null
	 */
	protected function stringToDateTime( $date )
	{
		try
		{
			return new \IPS\DateTime( $date );
		}
		catch( \Exception $e )
		{
			return NULL;
		}
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
			case 'convertEmoticons':
				$return['convertEmoticons'] = array();

				\IPS\Member::loggedIn()->language()->words['emoticon_path'] = \IPS\Member::loggedIn()->language()->addToStack( 'source_path', FALSE, array( 'sprintf' => array( 'UBBthreads' ) ) );
				$return['convertEmoticons']['emoticon_path'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL
				);
				$return['convertEmoticons']['keep_existing_emoticons']	= array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
					'field_default'		=> TRUE,
					'field_required'	=> FALSE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL,
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

				foreach( $this->db->select( '*', 'GROUPS' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['GROUP_ID']}"]        = $group['GROUP_NAME'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['GROUP_ID']}_desc"]   = \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['GROUP_ID']}"] = array(
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

				/* Should we use the username or display name property when converting? */
				$return['convertMembers']['username'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'display_name',
					'field_required'		=> TRUE,
					'field_extra'			=> array( 'options' => array( 'username' => \IPS\Member::loggedIn()->language()->addToStack( 'user_name' ), 'display_name' => \IPS\Member::loggedIn()->language()->addToStack( 'display_name' ) ) ),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack( 'username_hint' ),
				);

				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_ubbthreads_photo_path'),
					'field_validation'		=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				foreach( array( 'homepage', 'occupation', 'hobbies', 'location', 'icq', 'yahoo', 'aim', 'msn', 'custom_title' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, array( 'sprintf' => $field ) );
					\IPS\Member::loggedIn()->language()->words["field_{$field}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field_desc' );
					$return['convertMembers']["field_{$field}"] = array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'no_convert',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'no_convert'			=> \IPS\Member::loggedIn()->language()->addToStack( 'no_convert' ),
								'create_field'			=> \IPS\Member::loggedIn()->language()->addToStack( 'create_field' ),
							),
							'userSuppliedInput'		=> 'create_field'
						),
						'field_hint'			=> NULL
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

		/* Non-Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildProfilePhotos', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\core\Messenger\Conversation' ), 3, array( 'class' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 1, array( 'app' ) );

		return array( "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild", "f_rebuild_attachments" );
	}

	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		$post = preg_replace( "#\[quote=(.+?)\]#i", "[quote name=\"$1\"]", $post );
		$post = preg_replace( '#\[img:(left|center|right)\](.+?)\[\/img\]#i', '[$1][img]$2[/img][/$1]', $post );
		$post = str_ireplace( [ "[image]", '[/image]', '[size:', '[color:' ], [ '[img]', '[/img]', '[size=', '[color=' ], $post );

		return $post;
	}

	/**
	 * Convert ban filters
	 *
	 * @return 	void
	 */
	public function convertBanfilters()
	{
		$libraryClass = $this->getLibrary();

		/* Banned e-mails */
		foreach ( $this->db->select( 'BANNED_EMAIL', 'BANNED_EMAILS' ) as $row )
		{
			$libraryClass->convertBanfilter( array(
				'ban_id'        => $row,  // We don't actually have an ID column
				'ban_type'      => 'email',
				'ban_content'   => str_replace( '%', '*', $row )  // Replace UBB's wildcard character
			) );
		}

		/* Banned IP's */
		foreach( $this->db->select( 'BANNED_HOST', 'BANNED_HOSTS' ) as $row )
		{
			$libraryClass->convertBanfilter( array(
				'ban_id'        => $row,  // We don't actually have an ID column
				'ban_type'      => 'ip',
				'ban_content'   => str_replace( '%', '*', $row )  // Replace UBB's wildcard character
			) );
		}

		/* Banned / "reserved" names */
		foreach ( $this->db->select( 'RESERVED_USERNAME', 'RESERVED_NAMES' ) as $row )
		{
			$libraryClass->convertBanfilter( array(
				'ban_id'        => $row,  // We don't actually have an ID column
				'ban_type'      => 'name',
				'ban_content'   => str_replace( '%', '*', $row )  // Replace UBB's wildcard character; TODO: Verify UBB actually supports wildcards here
			) );
		}

		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Convert emoticons
	 *
	 * @return 	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();

		foreach( $this->fetch( 'GRAEMLINS', 'GRAEMLIN_ID', static::$emoticonsWhere ) as $row )
		{
			$info = array(
				'id'            => $row['GRAEMLIN_ID'],
				'typed'         => $row['GRAEMLIN_SMILEY_CODE'] ?: ':'.$row['GRAEMLIN_MARKUP_CODE'].':',
				'width'         => $row['GRAEMLIN_WIDTH'],
				'height'        => $row['GRAEMLIN_HEIGHT'],
				'filename'      => $row['GRAEMLIN_IMAGE'],
				'emo_position'  => $row['GRAEMLIN_ID'],
			);

			$set = array(
				'set'		=> md5( 'Converted' ),
				'title'		=> 'Converted',
				'position'	=> 1,
			);

			$libraryClass->convertEmoticon(
				$info, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'],
				rtrim( $this->app->_session['more_info']['convertEmoticons']['emoticon_path'], '/' ) . "/images/graemlins/default"
			);
			$libraryClass->setLastKeyValue( $row['GRAEMLIN_ID'] );
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
		$libraryClass::setKey( 'GROUP_ID' );

		foreach ( $this->fetch( 'GROUPS', 'GROUP_ID', static::$groupsWhere ) as $row )
		{
			/* @todo: Can we enable custom titles per group? */
			$info = array(
				'g_id'      => $row['GROUP_ID'],
				'g_name'    => $row['GROUP_NAME'],
			);

			$merge = $this->app->_session['more_info']['convertGroups']["map_group_{$row['GROUP_ID']}"] != 'none' ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['GROUP_ID']}"] : NULL;

			$libraryClass->convertGroup( $info, $merge );
			$libraryClass->setLastKeyValue( $row['GROUP_ID'] );
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
	 * Convert ignored users
	 *
	 * @return 	void
	 */
	public function convertIgnoredUsers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'USER_ID' );

		foreach ( $this->fetch( 'USER_PROFILE', 'USER_ID', static::$ignoredUsersWhere ) as $row )
		{
			/* Proper database modeling? CSV's? JSON? What are those things? */
			foreach ( explode( '-', $row['USER_IGNORE_LIST'] ) as $ignoredMemberMaybe )
			{
				if ( ! $ignoredMemberMaybe )
				{
					/* We split an empty string. Fabulous. */
					continue;
				}

				$info = array(
					'ignore_id'         => $row['USER_ID'] . '-' . $ignoredMemberMaybe,
					'ignore_owner_id'   => $row['USER_ID'],
					'ignore_ignore_id'  => $ignoredMemberMaybe
				);

				/* Assume we want to ignore everything by this member */
				foreach ( \IPS\core\Ignore::types() as $type )
				{
					$info[ 'ignore_' . $type ] = 1;
				}

				$libraryClass->convertIgnoredUser( $info );
				$libraryClass->setLastKeyValue( $row['USER_ID'] );
			}
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
		$libraryClass::setKey( 'u.USER_ID' );

		$select = $this->fetch( array( 'USERS', 'u' ), 'u.USER_ID', static::$membersWhere )
		               ->join( array( 'BANNED_USERS', 'b' ), 'u.USER_ID=b.USER_ID', 'LEFT' )
		               ->join( array( 'USER_PROFILE', 'p' ), 'u.USER_ID=p.USER_ID' )
			           ->join( array( 'USER_DATA', 'd' ), 'u.USER_ID=d.USER_ID' );

		foreach ( $select as $row )
		{
			/* Birthday */
			$birthday = array( 'day' => NULL, 'month' => NULL, 'year' => NULL );

			if ( $birthdayDt = $this->stringToDateTime( $row['USER_BIRTHDAY'] ) )
			{
				$birthday['day']    = $birthdayDt->format( 'j' );
				$birthday['month']  = $birthdayDt->format( 'n' );
				$birthday['year']   = $birthdayDt->format( 'Y' );
			}

			/* Member groups */
			$secondaryGroups = iterator_to_array( $this->db->select( 'GROUP_ID', 'USER_GROUPS', array( 'USER_ID=?', $row['USER_ID'] ) ) );
			$primaryGroup    = array_shift( $secondaryGroups );

			$info = array(
				'member_id'                 => $row['USER_ID'],
				'name'                      => ( $this->app->_session['more_info']['convertMembers']['username'] == 'user_name' )
					? $row['USER_LOGIN_NAME']
					: $row['USER_DISPLAY_NAME'],
				'email'                     => $row['USER_REAL_EMAIL'],
				'md5_password'              => $row['USER_PASSWORD'],
				'member_group_id'           => $primaryGroup,
				'mgroup_others'             => $secondaryGroups,
				'joined'                    => \IPS\DateTime::create()->setTimestamp( $row['USER_REGISTERED_ON'] ),
				'ip_address'                => $row['USER_REGISTRATION_IP'],
				'bday_day'                  => $birthday['day'],
				'bday_month'                => $birthday['month'],
				'bday_year'                 => $birthday['year'],
				'msg_count_total'           => $row['USER_TOTAL_PM'],
				'last_visit'                => \IPS\DateTime::create()->setTimestamp( $row['USER_LAST_VISIT_TIME'] ),
				'last_activity'             => \IPS\DateTime::create()->setTimestamp(
					max( (int) $row['USER_LAST_POST_TIME'], (int) $row['USER_LAST_SEARCH_TIME'] )
				),
				'allow_admin_mails'         => ( $row['USER_ACCEPT_ADMIN_EMAILS'] != 'Off' ),
				'member_posts'              => $row['USER_TOTAL_POSTS'],
				'signature'					=> $row['USER_DEFAULT_SIGNATURE'],
				'member_last_post'          => \IPS\DateTime::create()->setTimestamp( $row['USER_LAST_POST_TIME'] ),
				'temp_ban'                  => isset( $row['BAN_EXPIRATION'] )
					? ( ( (string) $row['BAN_EXPIRATION'] === '0' )
						? -1
						: \IPS\DateTime::create()->setTimestamp( $row['BAN_EXPIRATION'] ) )
					: NULL,
			);

			/* Profile fields */
			$pfields = array();
			foreach( array( 'homepage', 'occupation', 'hobbies', 'location', 'icq', 'yahoo', 'aim', 'msn', 'custom_title' ) AS $pseudo )
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
					$libraryClass->convertProfileField( array(
						'pf_id'				=> $pseudo,
						'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"],
						'pf_desc'			=> '',
						'pf_type'			=> 'Text',
						'pf_content'		=> '[]',
						'pf_member_hide'	=> 'all',
						'pf_max_input'		=> 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
					) );
				}

				$fieldColumn = 'USER_' . \strtoupper( $pseudo );
				$pfields[ $pseudo ] = isset( $row[ $fieldColumn ] ) ? $row[ $fieldColumn ] : NULL;
			}

			/* Profile photo */
			$profilePhotoName = NULL;
			$profilePhotoData = NULL;
			$profilePhotoPath = NULL;

			/* Is it a file? */
			if( !empty( $row['USER_AVATAR'] ) AND mb_substr( $row['USER_AVATAR'], 0, 1 ) == '/' )
			{
				$profilePhotoPath = pathinfo( rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' ) . $row['USER_AVATAR'], PATHINFO_DIRNAME );
				$profilePhotoName = pathinfo( rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' ) . $row['USER_AVATAR'], PATHINFO_BASENAME );
			}
			/* Or an URL? */
			elseif ( !empty( $row['USER_AVATAR'] ) AND ( $row['USER_AVATAR'] != 'http://' ) AND mb_substr( $row['USER_AVATAR'], 0, 4 ) )
			{
				try
				{
					$profilePhotoName = pathinfo( parse_url( $row['USER_AVATAR'], PHP_URL_PATH ), PATHINFO_BASENAME );
					$profilePhotoData = \IPS\Http\Url::external( $row['USER_AVATAR'] )->request()->get();
				}
				catch( \IPS\Http\Request\Exception $e ) { }
			}

			$libraryClass->convertMember( $info, $pfields, $profilePhotoName, $profilePhotoPath, $profilePhotoData );
			$libraryClass->setLastKeyValue( $row['USER_ID'] );
		}
	}

	/**
	 * Convert member followers
	 *
	 * @return 	void
	 */
	public function convertMembersFollowers()
	{
		$libraryClass = $this->getLibrary();

		foreach ( $this->fetch( 'WATCH_LISTS', 'WATCH_ID', array( 'WATCH_TYPE=?', 'u' ) ) as $row )
		{
			$libraryClass->convertFollow( array(
				'follow_app'            => 'core',
				'follow_area'           => 'member',
				'follow_rel_id'         => $row['WATCH_ID'],
				'follow_rel_id_type'    => 'core_members',
				'follow_member_id'      => $row['USER_ID'],
				'follow_notify_freq'    => $row['WATCH_NOTIFY_IMMEDIATE'] ? 'immediate' : 'none',
			) );
		}
	}

	/**
	 * Convert private messages
	 *
	 * @return 	void
	 */
	public function convertPrivateMessages()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'TOPIC_ID' );

		foreach ( $this->fetch( 'PRIVATE_MESSAGE_TOPICS', 'TOPIC_ID' ) as $topicRow )
		{
			$topic = array(
				'mt_id'             => $topicRow['TOPIC_ID'],
				'mt_date'           => \IPS\DateTime::create()->setTimestamp( $topicRow['TOPIC_TIME'] ),
				'mt_title'          => $topicRow['TOPIC_SUBJECT'],
				'mt_starter_id'     => $topicRow['USER_ID'],
				'mt_start_time'     => \IPS\DateTime::create()->setTimestamp( $topicRow['TOPIC_TIME'] ),
				'mt_last_post_time' => \IPS\DateTime::create()->setTimestamp( $topicRow['TOPIC_LAST_REPLY_TIME'] ),
				'mt_replies'        => $topicRow['TOPIC_REPLIES'],
			);

			$maps = array();
			
			/* Make sure the topic starter is in the map */
			$maps[ $topicRow['USER_ID'] ] = array(
				'map_user_id'   => $topicRow['USER_ID'],
				'map_read_time' => \IPS\DateTime::create()->setTimestamp( $topicRow['TOPIC_TIME'] )
			);
	
			foreach ( $this->db->select( '*', 'PRIVATE_MESSAGE_USERS',  array( 'TOPIC_ID=?', $topicRow['TOPIC_ID'] ) ) as $userRow )
			{
				$maps[ $userRow['USER_ID'] ] = array(
					'map_user_id'   => $userRow['USER_ID'],
					'map_read_time' => $userRow['MESSAGE_LAST_READ']
				);
			}

			$libraryClass->convertPrivateMessage( $topic, $maps );
			$libraryClass->setLastKeyValue( $topicRow['TOPIC_ID'] );
		}
	}

	/**
	 * Convert private message replies
	 *
	 * @return 	void
	 */
	public function convertPrivateMessageReplies()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'POST_ID' );

		foreach( $this->fetch( 'PRIVATE_MESSAGE_POSTS', 'POST_ID' ) AS $row )
		{
			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $row['POST_ID'],
				'msg_topic_id'		=> $row['TOPIC_ID'],
				'msg_date'			=> \IPS\DateTime::create()->setTimestamp( $row['POST_TIME'] ),
				'msg_post'			=> $row['POST_DEFAULT_BODY'],
				'msg_author_id'		=> $row['USER_ID'],
				'msg_ip_address'	=> '127.0.0.1',
			) );

			$libraryClass->setLastKeyValue( $row['POST_ID'] );
		}
	}

	/**
	 * Convert profanity filters
	 *
	 * @return 	void
	 */
	public function convertProfanityFilters()
	{
		$libraryClass = $this->getLibrary();

		foreach ( $this->db->select( '*', 'CENSOR_LIST', static::$profanityFiltersWhere ) as $row )
		{
			/**
			 * UBB seems to support regex based profanity filters to some extent. We want to avoid converting those,
			 * but if there is a filter that ends with with a wildcard match, we can convert it to a non-exact profanity
			 * filter
			 */
			$parsedWord = str_replace( '(.*?)', '', $row['CENSOR_WORD'] );
			$exact      = ( $parsedWord == $row['CENSOR_WORD'] );

			$libraryClass->convertProfanityFilter( array(
				'wid'       => $row['CENSOR_WORD'],
				'type'      => $parsedWord,
				'swop'      => $row['CENSOR_REPLACE_WITH'],
				'm_exact'   => $exact
			) );
		}

		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		/* Make sure it's a UBBThreads URL */
		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'ubbthreads.php' ) === FALSE )
		{
			return NULL;
		}

		if( preg_match( '#/ubbthreads.php/users/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			try
			{
				$data = $this->app->getLink( (int) $matches[1], array( 'members', 'core_members' ) );
				$item = \IPS\Member::load( $data );

				if( \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
				{
					return $item->url();
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
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
		$hash = $member->members_pass_hash;
		$salt = $member->members_pass_salt;

		if ( \IPS\Login::compareHashes( $hash, md5( $password ) ) )
		{
			return TRUE;
		}

		// Not using md5, UBB salts the password with the password
		// IPB already md5'd it though, *sigh*
		if ( \IPS\Login::compareHashes( $hash, md5( md5( $salt ) . crypt( $password, $password ) ) ) )
		{
			return TRUE;
		}

		// Now standard IPB check.
		if ( \IPS\Login::compareHashes( $hash, md5( md5( $salt ) . md5( $password ) ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}
}