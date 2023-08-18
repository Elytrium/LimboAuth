<?php

/**
 * @brief		Converter wpForo Core Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		22 Jan 2021
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * wpForo Core Converter
 */
class _Wpforo extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "wpForo (2.1.x)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "wpforo";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return [
			'convertGroups'     => [
				'table'		=> 'wpforo_usergroups',
				'where'		=> NULL
			],
			'convertMembers'    => [
				'table'		=> 'users',
				'where'		=> NULL
			],
			'convertPrivateMessages'	=> [
				'table'		=> 'wpforo_pmfolders',
				'where'		=> NULL
			],
			'convertPrivateMessageReplies'	=> [
				'table'		=> 'wpforo_pms',
				'where'		=> NULL,
			],
			'convertAttachments'	=> [
				'table'		=> 'wpforo_pms',
				'where'		=> NULL,
			],
		];
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
	 * Returns a block of text, or a language string, that explains what the admin must do to start this conversion
	 *
	 * @return	string
	 */
	public static function getPreConversionInformation()
	{
		return 'convert_wpforo_preconvert';
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return [
			'convertMembers',
			'convertGroups',
		];
	}

	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		/* Mentions */
		$matches = [];
		preg_match_all( '/@([^@ ]+)/i', $post, $matches );

		if( \count( $matches ) )
		{
			foreach( $matches[0] as $key => $tag )
			{
				$member = \IPS\Member::load( $matches[1][ $key ], 'name' );
				if( !$member->member_id )
				{
					continue;
				}

				$post = str_replace( $tag, "[mention={$member->member_id}]{$member->name}[/mention]", $post );
			}
		}

		/* Make quotes look a little nicer */
		$post = str_replace( '<blockquote', '<blockquote class="ipsQuote"', $post );

		return $post;
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = [];

		switch( $method )
		{
			case 'convertMembers':
				$return['convertMembers'] = [
					'wpuploads' => [
						'field_class'			=> 'IPS\\Helpers\\Form\\Text',
						'field_default'			=> NULL,
						'field_required'		=> TRUE,
						'field_extra'			=> [],
						'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_wp_typical_path'),
						'field_validation'	    => function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					]
				];

				/* Pseudo Fields */
				foreach( $this->_profileFields AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, [ 'sprintf' => ucwords( str_replace( '_', ' ', $field ) ) ] );
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

			case 'convertGroups':
				$return['convertGroups'] = [];

				$options = [];
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[ $group->g_id ] = $group->name;
				}

				foreach( $this->db->select( '*', 'wpforo_usergroups' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['groupid']}"]			= $group['name'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['groupid']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['groupid']}"] = [
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> [ 'options' => $options ],
						'field_hint'		=> NULL
					];
				}
			break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : [];
	}

	/**
	 * @brief   temporarily store post content
	 */
	protected $_postContent = [];

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'pmid' );

		foreach( $this->fetch( 'wpforo_pms', 'pmid' ) AS $row )
		{
			if( !\stristr( $row['message'], '[attach]' ) AND !\stristr( $row['message'], 'wpforo-attached-file' ) )
			{
				$libraryClass->setLastKeyValue( $row['pmid'] );
				continue;
			}

			$map = [
				'id1'				=> $row['folderid'],
				'id2'				=> $row['pmid'],
				'id1_type'			=> 'core_message_topics',
				'id1_from_parent'	=> FALSE,
				'id2_type'			=> 'core_message_posts',
				'id2_from_parent'	=> FALSE,
				'location_key'		=> 'core_Messaging'
			];

			/* Advanced Attachments */
			$matches = [];
			preg_match_all( '/\[attach\](\d+)\[\/attach\]/i', $row['message'], $matches );

			if( \count( $matches ) )
			{
				foreach( $matches[1] as $key => $id )
				{
					$sourceAttachment = $this->db->select( '*', 'wpforo_attachments', [ 'attachid=?', $id ] )->first();
					$url = explode( '/', $sourceAttachment['fileurl'] );
					$filename = array_pop( $url );

					$info = [
						'attach_id'			=> $row['pmid'],
						'attach_file'		=> $sourceAttachment['filename'],
						'attach_date'		=> \strtotime( $row['date'] ),
						'attach_member_id'	=> $sourceAttachment['userid'],
						'attach_filesize'	=> $sourceAttachment['size'],
					];

					$realFilePath = '/wpforo/attachments/' . $sourceAttachment['userid'] . '/' . $filename;
					$path = rtrim( $this->app->_session['more_info']['convertMembers']['wpuploads'], '/' ) . $realFilePath;

					$attachId = $libraryClass->convertAttachment( $info, $map, $path );

					/* Update Message Post if we can */
					try
					{
						if ( $attachId !== FALSE )
						{
							$messagePostId = $this->app->getLink( $row['pmid'], 'core_message_posts' );

							if( !isset( $this->_postContent[ $messagePostId ] ) )
							{
								$this->_postContent[ $messagePostId ] = \IPS\Db::i()->select( 'msg_post', 'core_message_posts', [ "msg_id=?", $messagePostId ] )->first();
							}

							$this->_postContent[ $messagePostId ] = str_replace( $matches[0][ $key ], '[attachment=' . $attachId . ':name]', $this->_postContent[ $messagePostId ] );
						}
					}
					catch( \UnderflowException $e ) {}
					catch( \OutOfRangeException $e ) {}
				}
			}

			/* Default Attachments */
			$matches = [];
			preg_match_all( '/\<div id\="wpfa\-[\d]+"(.+?)?>\<a class\="wpforo\-default\-attachment" href\="(.+?)"(.+?)?>\<i class\="(.+?)">\<\/i>(.+?)<\/a><\/div>/i', $row['message'], $matches );

			if( \count( $matches ) )
			{
				foreach( $matches[2] as $key => $url )
				{
					$url = explode( '/', $url );
					$filename = array_pop( $url );
					$info = [
						'attach_id'			=> $row['pmid'],
						'attach_file'		=> $filename,
						'attach_date'		=> \strtotime( $row['date'] ),
						'attach_member_id'	=> $row['fromuserid'],
					];

					$realFilePath = '/wpforo/default_attachments/' . $filename;
					$path = rtrim( $this->app->_session['more_info']['convertMembers']['wpuploads'], '/' ) . $realFilePath;

					$attachId = $libraryClass->convertAttachment( $info, $map, $path );

					/* Update post if we can */
					try
					{
						if ( $attachId !== FALSE )
						{
							$messagePostId = $this->app->getLink( $row['pmid'], 'core_message_posts' );

							if( !isset( $this->_postContent[ $messagePostId ] ) )
							{
								$this->_postContent[ $messagePostId ] = \IPS\Db::i()->select( 'msg_post', 'core_message_posts', [ "msg_id=?", $messagePostId ] )->first();
							}

							$this->_postContent[ $messagePostId ] = str_replace( $matches[0][ $key ], '[attachment=' . $attachId . ':name]', $this->_postContent[ $messagePostId ] );
						}
					}
					catch( \UnderflowException $e ) {}
					catch( \OutOfRangeException $e ) {}
				}
			}

			$libraryClass->setLastKeyValue( $row['pmid'] );
		}

		/* Do the updates */
		foreach( $this->_postContent as $id => $content )
		{
			\IPS\Db::i()->update( 'core_message_posts', [ 'msg_post' => $content ], [ "msg_id=?", $id ] );
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
		$libraryClass::setKey( 'groupid' );

		foreach( $this->fetch( 'wpforo_usergroups', 'groupid' ) AS $row )
		{
			$prefix = NULL;
			$suffix = NULL;

			if ( $row['color'] )
			{
				$prefix = "<span style='color:{$row['color']}'>";
				$suffix = "</span>";
			}

			$info = [
				'g_id'				=> $row['groupid'],
				'g_name'			=> $row['name'],
				'prefix'			=> $prefix,
				'suffix'			=> $suffix,
			];

			$merge = $this->app->_session['more_info']['convertGroups']["map_group_{$row['groupid']}"] != 'none' ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['groupid']}"] : NULL;

			$libraryClass->convertGroup( $info, $merge );
			$libraryClass->setLastKeyValue( $row['groupid'] );
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
	 * @brief   Hardcoded wpForo profile fields
	 */
	protected $_profileFields = [ 'site', 'icq', 'aim', 'yahoo', 'msn', 'facebook', 'twitter', 'gtalk', 'skype', 'about', 'occupation', 'location' ];

	/**
	 * Convert members
	 *
	 * @return	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'users.ID' );

		foreach( $this->fetch( 'users', 'users.ID' )->join( 'wpforo_profiles', "users.ID=wpforo_profiles.userid" ) AS $user )
		{
			try
			{
				$joined = ( new \IPS\DateTime( $user['user_registered'] ) )->getTimestamp();
			}
			catch( \ValueError $e )
			{
				$joined = time();
			}
			/* Main Members Table */
			$info = [
				'member_id'					=> $user['ID'],
				'member_group_id'			=> $user['groupid'],
				'mgroup_others'				=> $user['secondary_groupids'],
				'name'						=> $user['display_name'],
				'email'						=> $user['user_email'],
				'joined'					=> $joined,
				'conv_password'				=> $user['user_pass'],
				'temp_ban'                  => $user['status'] == 'blocked' ? -1 : 0,
				'signature'					=> static::fixPostData( $user['signature'] ?: '' ),
				'last_visit'			    => $user['online_time'],
				'last_activity'			    => $user['online_time']
			];

			$fields = [];
			foreach( $this->_profileFields AS $field )
			{
				if ( $this->app->_session['more_info']['convertMembers']["field_{$field}"] != 'no_convert' )
				{
					try
					{
						$fieldId = $this->app->getLink( $field, 'core_pfields_data' );
					}
					catch( \OutOfRangeException $e )
					{
						$libraryClass->convertProfileField( [
							'pf_id'				=> $field,
							'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$field}"],
							'pf_desc'			=> '',
							'pf_type'			=> 'Text',
							'pf_content'		=> '[]',
							'pf_member_hide'	=> 'all',
							'pf_max_input'		=> 255,
							'pf_member_edit'	=> 1,
							'pf_show_on_reg'	=> 0,
						] );
					}

					$user[ $field ] = $user[ $field ] ?? '';
				}
			}

			/* Avatars */
			$avatarFilename = NULL;
			$avatarPath = rtrim( $this->app->_session['more_info']['convertMembers']['wpuploads'], '/' ) . '/wpforo/avatars/';
			if( !empty( $user['avatar'] ) )
			{
				$path = explode( '/', $user['avatar'] );
				$avatarFilename = array_pop( $path );
			}
			else
			{
				/* Take a guess */
				foreach( [ 'jpg', 'png', 'jpeg', 'gif' ] as $ext )
				{
					$filename = \strtolower( \str_replace( '_', '', \preg_replace( '/\s|\.(?=.*\.)/i', '-',$user['display_name'] ) ) ) . '_' . $user['ID'] . '.' . $ext;

					if( file_exists( $avatarPath . '/' . $filename ) )
					{
						$avatarFilename = $filename;
					}
				}
			}

			$memberId = $libraryClass->convertMember( $info, $fields, $avatarFilename, $avatarPath );

			if( $memberId !== FALSE )
			{
				$this->app->addLink( $memberId, $info['name'], 'member_furl' );
			}

			$libraryClass->setLastKeyValue( $user['ID'] );
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
		$libraryClass::setKey( 'folderid' );

		foreach( $this->fetch( 'wpforo_pmfolders', 'folderid' ) AS $row )
		{
			try
			{
				$firstMessage = $this->db->select( '*', 'wpforo_pms', [ 'folderid=?', $row['folderid'] ], 'pmid ASC', 1 )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['folderid'] );
				continue;
			}

			try
			{
				$lastMessage = $this->db->select( '*', 'wpforo_pms', [ 'folderid=?', $row['folderid'] ], 'pmid DESC', 1 )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['folderid'] );
				continue;
			}

			$topic = [
				'mt_id'				=> $row['folderid'],
				'mt_date'			=> \strtotime( $firstMessage['date'] ),
				'mt_title'			=> $row['name'] ?: 'Message',
				'mt_starter_id'		=> $firstMessage['fromuserid'],
				'mt_start_time'		=> \strtotime( $firstMessage['date'] ),
				'mt_last_post_time'	=> \strtotime( $lastMessage['date'] ),
				'mt_to_count'		=> $row['user_count'],
			];

			$maps = [];
			$maps[ $firstMessage['fromuserid'] ] = [
				'map_user_id'		=> $firstMessage['fromuserid'],
				'map_read_time'		=> \strtotime( $firstMessage['date'] ),
				'map_is_starter'	=> true
			];

			$readData = explode( ',', $lastMessage['read'] );

			foreach( explode( ',', $row['userids'] ) as $participant )
			{
				if( $participant == $firstMessage['fromuserid'] )
				{
					continue;
				}

				$maps[ $participant ] = [
					'map_user_id'		=> $participant,
					'map_read_time'		=> \in_array( $participant, $readData ) ? time() : 0,
					'map_user_active'	=> 1,
				];
			}

			$libraryClass->convertPrivateMessage( $topic, $maps );
			$libraryClass->setLastKeyValue( $row['folderid'] );
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
		$libraryClass::setKey( 'pmid' );

		foreach( $this->fetch( 'wpforo_pms', 'pmid' ) AS $row )
		{
			$libraryClass->convertPrivateMessageReply( [
				'msg_id'			=> $row['pmid'],
				'msg_topic_id'		=> $row['folderid'],
				'msg_date'			=> \strtotime( $row['date'] ),
				'msg_post'			=> $row['message'],
				'msg_author_id'		=> $row['fromuserid'],
			] );

			$libraryClass->setLastKeyValue( $row['pmid'] );
		}
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
		\IPS\Task::queue( 'convert', 'RebuildNonContent', [ 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ], 2, [ 'app', 'link', 'extension' ] );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', [ 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ], 2, [ 'app', 'link', 'extension' ] );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', [ 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ], 2, [ 'app', 'link', 'extension' ] );

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', [ 'app' => $this->app->app_id ], 4, [ 'app' ] );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', [ 'class' => 'IPS\core\Messenger\Message' ], 3, [ 'class' ] );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', [ 'app' => $this->app->app_id ], 2, [ 'app' ] );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', [ 'app' => $this->app->app_id ], 1, [ 'app' ] );

		return [ "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild" ];
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
		return \IPS\convert\Software\Core\Wordpress::login( $member, $password );
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();
		$wpForoSlug = \defined('WPFORO_SLUG') ? WPFOROSLUG : 'community';

		$matches = [];
		if( preg_match( '#/' . $wpForoSlug . '/profile/([a-z0-9-]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			/* If we can't access profiles, don't bother trying to redirect */
			if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
			{
				return NULL;
			}

			try
			{
				$data = (string) $this->app->getLink( $matches[1], [ 'member_furl' ] );
				return \IPS\Member::load( $data )->url();
			}
			catch( \Exception $e ) { }
		}

		return NULL;
	}
}