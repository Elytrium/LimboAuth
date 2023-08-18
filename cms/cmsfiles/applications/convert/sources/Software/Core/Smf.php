<?php
/**
 * @brief		Converter Smf Class
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
 * SMF Core Converter
 */
class _Smf extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "Simple Machines Forum (2.0.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "smf";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertBanfilters'			=> array(
				'table'						=> 'ban_items',
				'where'						=> NULL,
			),
			'convertEmoticons'			=> array(
				'table'						=> 'smileys',
				'where'						=> NULL,
			),
			'convertProfileFields'		=> array(
				'table'						=> 'custom_fields',
				'where'						=> NULL,
			),
			'convertGroups'				=> array(
				'table'						=> 'membergroups',
				'where'						=> NULL,
			),
			'convertMembers'			=> array(
				'table'						=> 'members',
				'where'						=> NULL,
			),
			'convertPrivateMessages'	=> array(
				'table'						=> 'personal_messages',
				'where'						=> NULL
			),
			'convertPrivateMessageReplies'	=> array(
				'table'						=> 'personal_messages',
				'where'						=> NULL
			),
			'convertIgnoredUsers'		=> array(
				'table'						=> 'members',
				'where'						=> array( 'LENGTH( pm_ignore_list ) > 0' ),
			),
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
			'convertProfileFields',
			'convertGroups',
			'convertMembers'
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
			case 'ignored_users':
				try
				{
					return $this->db->select( 'SUM(CHAR_LENGTH(buddy_list) - CHAR_LENGTH(REPLACE(buddy_list, ",", "") + 1))', 'members', array( "pm_ignore_list!=?", '' ) )->first();
				}
				catch( \UnderflowException $e )
				{
					return 0;
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
				$return['convertEmoticons']['emoticon_path'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL,
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				$return['convertEmoticons']['keep_existing_emoticons']	= array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
					'field_default'		=> TRUE,
					'field_required'	=> FALSE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL,
				);
				break;
			
			case 'convertProfileFields':
				$return['convertProfileFields'] = array();
				
				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_data' ), 'IPS\core\ProfileFields\Field' ) AS $field )
				{
					$options[$field->_id] = $field->_title;
				}
				
				foreach( $this->db->select( '*', 'custom_fields' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['id_field']}"]		= $field['field_name'];
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['id_field']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );
					
					$return['convertProfileFields']["map_pfield_{$field['id_field']}"] = array(
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
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[$group->g_id] = $group->name;
				}
				
				foreach( $this->db->select( '*', 'membergroups' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['id_group']}"]			= $group['group_name'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['id_group']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );
					
					$return['convertGroups']["map_group_{$group['id_group']}"] = array(
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

				$return['convertMembers']['username_or_display_name'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'user_name',
					'field_required'		=> TRUE,
					'field_extra'			=> array( 'options' => array( 'user_name' => 'user_name', 'display_name' => 'display_name' ) ),
					'field_hint'			=> NULL,
				);

				\IPS\Member::loggedIn()->language()->words['photo_location_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'photo_location_nodb_desc' );
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> "This is typically: /path/to/smf/attachments/",
					'field_validation'		=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				
				$return['convertMembers']['gallery_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> "This is typically: /path/to/smf/uploads/avatars/",
					'field_validation'		=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				
				foreach( array( 'gender', 'website_url', 'location', 'icq', 'aim', 'yim', 'msn', 'personal_text', 'usertitle' ) AS $field )
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
		
		return $return[ $method ];
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
		// Replace BBCode - The table HTML is removed by our parser, but it leaves the posts cleaner than having unparsed bbcode
		$search = array( '[li]', '[/li]', '[table]', '[/table]', '[tr]', '[/tr]', '[td]', '[/td]', '[tt]', '[/tt]' );
		$replace = array( '[*]', '', '<table>', '</table>', '<tr>', '</tr>', '<td>', '</td>', '[code]', '[/code]' );

		$post = str_replace( $search, $replace, $post );

		// And img tags
		$post = preg_replace("#\[img width=(\d+)(?: height=(\d+))?\]([a-zA-Z0-9.:\-/%\?&_=~\#\(\);:]+)\[\/img\]#i", "[img]$3[/img]", $post);

		//Quotes
		$post = preg_replace("#\[quote author=(.+?) link=(.+?)=(\d+)\.msg(\d+)(.+?)date=(\d+)\](.+)\[\/quote\]#i", "[quote name=\"$1\" timestamp=\"$6\"]$7[/quote]", $post);

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
		
		$libraryClass::setKey( 'id_ban' );
		
		foreach( $this->fetch( 'ban_items', 'id_ban' ) AS $row )
		{
			/* Hostnames aren't supported, and Members are done later */
			if ( $row['hostname'] OR $row['id_member'] )
			{
				$libraryClass->setLastKeyValue( $row['id_ban'] );
				continue;
			}
			
			if ( $row['ip_low1'] AND $row['ip_low2'] AND $row['ip_low3'] AND $row['ip_low4'] )
			{
				$libraryClass->convertBanfilter( array(
					'ban_id'		=> $row['id_ban'],
					'ban_type'		=> 'ip',
					'ban_content'	=> $row['ip_low1'] . '.' . $row['ip_low2'] . '.' . $row['ip_low3'] . '.' . $row['ip_low4']
				) );
			}
			
			if ( $row['email_address'] )
			{
				$libraryClass->convertBanfilter( array(
					'ban_id'		=> $row['id_ban'],
					'ban_type'		=> 'email',
					'ban_content'	=> str_replace( '%' ,'*', $row['email_address'] ),
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['id_ban'] );
		}
	}

	/**
	 * Convert emoticons
	 *
	 * @return 	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_smiley' );
		
		foreach( $this->fetch( 'smileys', 'id_smiley' ) AS $row )
		{
			$libraryClass->convertEmoticon( array(
				'id'			=> $row['id_smiley'],
				'typed'			=> $row['code'],
				'filename'		=> $row['filename'],
				'clickable'		=> !$row['hidden'],
				'emo_position'	=> $row['smiley_order']
			), array(
				'set'		=> md5( 'Converted' ),
				'title'		=> 'Converted',
				'position'	=> 1
			), $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], rtrim( $this->app->_session['more_info']['convertEmoticons']['emoticon_path'], '/' ) );
			
			$libraryClass->setLastKeyValue( $row['id_smiley'] );
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

		$libraryClass::setKey( 'id_pm' );

		foreach( $this->fetch( 'personal_messages', 'id_pm' ) AS $row )
		{
			$topic = array(
				'mt_id'				=> $row['id_pm'],
				'mt_date'			=> $row['msgtime'],
				'mt_title'			=> $row['subject'],
				'mt_starter_id'		=> $row['id_member_from'],
				'mt_start_time'		=> $row['msgtime'],
				'mt_last_post_time'	=> $row['msgtime'],
				'mt_to_count'		=> 0,
				'mt_replies'		=> 0,
			);

			$maps = array(
				$row['id_member_from'] = array(
					'map_user_id'			=> $row['id_member_from'],
					'map_read_time'			=> time(),
					'map_user_active'		=> ( $row['deleted_by_sender'] ) ? 0 : 1,
					'map_user_banned'		=> 0,
					'map_has_unread'		=> 0,
					'map_is_starter'		=> 1,
					'map_last_topic_reply'	=> $row['msgtime']
				)
			);

			foreach( $this->db->select( '*', 'pm_recipients', array( "id_pm=?", $row['id_pm'] ) ) AS $map )
			{
				if( !isset( $maps[ $map['id_member'] ] ) )
				{
					$maps[ $map['id_member'] ] = array(
						'map_user_id'			=> $map['id_member'],
						'map_read_time'			=> $map['is_new'] ? time() : 0,
						'map_user_active'		=> $map['deleted'] ? 0 : 1,
						'map_user_banned'		=> 0,
						'map_has_unread'		=> $map['is_new'],
						'map_is_starter'		=> 0,
						'map_last_topic_reply'	=> $row['msgtime']
					);
				}
			}

			$libraryClass->convertPrivateMessage( $topic, $maps );

			$libraryClass->setLastKeyValue( $row['id_pm'] );
		}
	}

	/**
	 * Convert PM replies
	 *
	 * @return 	void
	 */
	public function convertPrivateMessageReplies()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'id_pm' );

		foreach( $this->fetch( 'personal_messages', 'id_pm' ) AS $row )
		{
			$libraryClass->convertPrivateMessageReply( array(
					'msg_id'			=> $row['id_pm'],
					'msg_topic_id'		=> $row['id_pm'],
					'msg_date'			=> $row['msgtime'],
					'msg_post'			=> $row['body'],
					'msg_author_id'		=> $row['id_member_from'],
			) );

			$libraryClass->setLastKeyValue( $row['id_pm'] );
		}
	}

	/**
	 * Convert custom fields
	 *
	 * @return 	void
	 */
	public function convertProfileFields()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_field' );
		
		foreach( $this->fetch( 'custom_fields', 'id_field' ) AS $row )
		{
			$type = NULL;
			switch( $row['field_type'] )
			{
				case 'textarea':
					$type = 'TextArea';
					break;
				
				case 'check':
					$type = 'Checkbox';
					break;
				
				default:
					$type = ucwords( $row['field_type'] );
					break;
			}
			
			if ( $row['mask'] == 'email' )
			{
				$type = 'Email';
			}
			
			if ( $row['mask'] == 'number' )
			{
				$type = 'Number';
			}
			
			$info = array(
				'pf_id'				=> $row['col_name'],
				'pf_type'			=> $type,
				'pf_name'			=> $row['field_name'],
				'pf_desc'			=> $row['field_desc'],
				'pf_content'		=> ( \in_array( $row['field_type'], array( 'select', 'radio' ) ) ) ? json_encode( explode( ',', $row['field_options'] ) ) : NULL,
				'pf_not_null'		=> ( $row['show_reg'] == 2 ) ? 1 : 0,
				'pf_member_hide'	=> ( $row['private'] >= 3 ) ? 'hide' : 'all',
				'pf_max_input'		=> ( !\in_array( $row['field_type'], array( 'select', 'radio', 'check' ) ) ) ? $row['field_length'] : NULL,
				'pf_member_edit'	=> ( $row['private'] < 4 ) ? 1 : 0,
				'pf_position'		=> $row['placement'],
				'pf_show_on_reg'	=> ( $row['show_reg'] >= 1 ) ? 1 : 0,
				'pf_input_format'	=> ( mb_substr( $row['mask'], 0, 5 ) == 'regex' ) ? str_replace( 'regex', '', $row['mask'] ) : NULL,
			);
			
			$merge = ( $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['id_field']}"] != 'none' ) ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['id_field']}"] : NULL;
			
			$libraryClass->convertProfileField( $info, $merge );
			
			$libraryClass->setLastKeyValue( $row['id_field'] );
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
		
		$libraryClass::setKey( 'id_group' );
		
		foreach( $this->fetch( 'membergroups', 'id_group' ) AS $row )
		{
			$prefix = NULL;
			$suffix = NULL;
			
			if ( $row['online_color'] )
			{
				$prefix = "<span style='color:{$row['online_color']}'>";
				$suffix = "</span>";
			}
			
			$info = array(
				'g_id'				=> $row['id_group'],
				'g_name'			=> $row['group_name'],
				'prefix'			=> $prefix,
				'suffix'			=> $suffix,
				'g_max_messages'	=> $row['max_messages'],
			);
			
			$merge = $this->app->_session['more_info']['convertGroups']["map_group_{$row['id_group']}"] != 'none' ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['id_group']}"] : NULL;
			
			$libraryClass->convertGroup( $info, $merge );
			
			$libraryClass->setLastKeyValue( $row['id_group'] );
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
	 * @return 	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_member' );
		
		foreach( $this->fetch( 'members', 'id_member' ) AS $row )
		{
			$name = $row['real_name'];
			
			if ( $this->app->_session['more_info']['convertMembers']['username_or_display_name'] == 'username' )
			{
				$name = $row['member_name'];
			}
			
			/* Restrictions */
			$ban	= 0;
			$rp		= 0;
			try
			{
				$ban_data = $this->db->select( '*', 'ban_groups', array( "id_ban_group=?", $this->db->select( 'id_ban_group', 'ban_items', array( "id_member=?", $row['id_member'] ) )->first() ) )->first();
				
				$time = -1;
				if ( $ban_data['expire_time'] )
				{
					$time = $ban_data['expire_time'];
				}
				
				if ( $ban_data['cannot_access'] )
				{
					$ban = $time;
				}
				
				if ( $ban_data['cannot_post'] )
				{
					$rp = $time;
				}
			}
			catch( \UnderflowException $e ) {}

			/* Catch out of range issues */
			if( $ban > 2147483647 )
			{
				$ban = -1;
			}
			
			$bday_year = $bday_month = $bday_day = NULL;

			/* Make sure it isn't the SMF placeholder */
			if( $row['birthdate'] != '0001-01-01' )
			{
				list( $bday_year, $bday_month, $bday_day ) = explode( '-', $row['birthdate'] );
			}
			
			$info = array(
				'member_id'				=> $row['id_member'],
				'email'					=> $row['email_address'],
				'name'					=> $name,
				'password'				=> $row['passwd'],
				'password_extra'		=> $row['member_name'], // We need to keep the original member name for the SMF password hash
				'member_group_id'		=> $row['id_group'],
				'joined'				=> $row['date_registered'],
				'ip_address'			=> $row['member_ip'],
				'msg_count_new'			=> $row['unread_messages'],
				'msg_count_total'		=> $row['instant_messages'],
				'msg_show_notification'	=> $row['new_pm'],
				'last_visit'			=> $row['last_login'],
				'last_activity'			=> $row['last_login'],
				'restrict_post'			=> $rp,
				'temp_ban'				=> $ban,
				'bday_day'				=> $bday_day,
				'bday_month'			=> $bday_month,
				'bday_year'				=> $bday_year,
				'mgroup_others'			=> $row['additional_groups'],
				'signature'				=> static::fixPostData( $row['signature'] ),
				'pp_reputation_points'	=> $row['karma_good'] - $row['karma_bad'],
				'timezone'				=> $row['time_offset'],
				'allow_admin_mails'		=> $row['notify_announcements'],
				'member_posts'			=> $row['posts'],
			);
			
			$pfields = array();
			
			/* Pseudo Profile Fields */
			foreach( array( 'gender', 'website_url', 'location', 'icq', 'aim', 'yim', 'msn', 'personal_text', 'usertitle' ) AS $pseudo )
			{
				/* Are we retaining? */
				if ( $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"] == 'no_convert' )
				{
					/* No, skip */
					continue;
				}
				
				try
				{
					$fieldId = $this->app->getLink( $pseudo, 'core_pfields_data' );
				}
				catch( \OutOfRangeException $e )
				{
					$type		= 'Text';
					$content	= '[]';
					switch( $pseudo )
					{
						case 'gender':
							$type		= 'Select';
							$content	= json_encode( [ 'Male', 'Female', 'Undisclosed' ] );
							break;
						
						case 'personal_text':
							$type		= 'TextArea';
							break;
					}
					
					$libraryClass->convertProfileField( array(
						'pf_id'				=> $pseudo,
						'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"],
						'pf_desc'			=> '',
						'pf_type'			=> $type,
						'pf_content'		=> $content,
						'pf_member_hide'	=> 0,
						'pf_max_input'		=> 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
						'pf_admin_only'		=> 0,
					) );
				}
				
				switch( $pseudo )
				{
					case 'gender':
						switch( $row[$pseudo] )
						{
							case 1:
								$pfields[$pseudo] = 'Male';
								break;
							
							case 2:
								$pfields[$pseudo] = 'Female';
								break;
							
							default:
								$pfields[$pseudo] = 'Undisclosed';
								break;
						}
						break;
					default:
						$pfields[$pseudo] = $row[$pseudo];
						break;
				}
			}
			
			/* Real Profile Fields */
			foreach( $this->db->select( 'variable, value', 'themes', array( "id_member=?", $row['id_member'] ) )->setKeyField( 'variable' )->setValueField( 'value' ) AS $key => $value )
			{
				$pfields[$key] = $value;
			}
			
			/* Photos */
			$filedata = NULL;
			$filename = NULL;
			$filepath = NULL;
			if ( $row['avatar'] )
			{
				if ( mb_substr( $row['avatar'], 0, 4 ) == 'http' )
				{
					/* Remote */
					try
					{
						$file = \IPS\Http\Url::external( $row['avatar'] )->request()->get();
						
						$filename = explode( '/', $row['avatar'] );
						$filename = array_pop( $filename );
						$filedata = (string) $file;
					}
					catch( \Exception $e ) {}
				}
				else
				{
					/* Gallery */
					$file = rtrim( $this->app->_session['more_info']['convertMembers']['gallery_location'], '/' );
					if ( @file_exists( $file . '/' . $row['avatar'] ) )
					{
						$filename = $row['avatar'];
						$filepath = $file;
					}
				}
			}

			/* Nothing yet? try attachments */
			if( empty( $filename ) )
			{
				/* Maybe Uploaded */
				try
				{
					$legacyName = null;
					$attach = $this->db->select( '*', 'attachments', array( "id_member=? AND filename LIKE CONCAT( ?, '%' )", $row['id_member'], 'avatar_' ) )->first();
					
					$path = rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' );

					/* We need to figure out where it is */
					if ( $attach['file_hash'] )
					{
						$location = $attach['id_attach'] . '_' . $attach['file_hash'];
					}
					else
					{
						/* Clean filename per legacy SMF requirements */
						$cleanName = strtr( $attach['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
						$cleanName = strtr( $cleanName, array( 'Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u' ) );
						$cleanName = preg_replace( array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $cleanName );
						$location = $attach['id_attach'] . '_' . str_replace( '.', '_', $cleanName ) . md5( $cleanName );
						$legacyName = preg_replace( '~\.[\.]+~', '.', $cleanName );
					}
					
					if ( \file_exists( $path . '/' . $location ) )
					{
						$filename = $attach['filename'];
						$filepath = $path . '/' . $location;
					}
					elseif( !empty( $legacyName ) AND \file_exists( $path . '/' . $legacyName ) )
					{
						$filename = $attach['filename'];
						$filepath = $path;
					}
				}
				catch( \UnderflowException $e ) {}
			}
			
			$libraryClass->convertMember( $info, $pfields, $filename, $filepath, $filedata );
			
			$libraryClass->setLastKeyValue( $row['id_member'] );
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
		
		$libraryClass::setKey( 'id_member' );
		
		foreach( $this->fetch( 'members', 'id_member', array( 'LENGTH( pm_ignore_list ) > 0' ) ) AS $row )
		{
			if ( !$row['pm_ignore_list'] )
			{
				continue;
			}
			
			foreach( explode( ',', $row['pm_ignore_list'] ) AS $id )
			{
				$info = array();
				$info['ignore_id']			= $row['id_member'] . '_' . $id;
				$info['ignore_owner_id']	= $row['id_member'];
				$info['ignore_ignore_id']	= $id;
				
				foreach( \IPS\core\Ignore::types() AS $type )
				{
					$info['ignore_' . $type] = 1;
				}
				$libraryClass->convertIgnoredUser( $info );
			}
			
			$libraryClass->setLastKeyValue( $row['id_member'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 * @note	Current profile URL format: /index.php?action=profile;u=123
	 */
	public function checkRedirects()
	{
		/* If we can't access profiles, don't bother trying to redirect */
		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
		{
			return NULL;
		}

		if( isset( \IPS\Request::i()->action ) AND mb_strpos( \IPS\Request::i()->action, 'profile' ) !== FALSE )
		{
			if( isset( \IPS\Request::i()->u ) )
			{
				$oldId	= \IPS\Request::i()->u;
			}
			else
			{
				$pieces	= explode( ';', \IPS\Request::i()->action );

				foreach( $pieces as $piece )
				{
					$_pieces	= explode( '=', $piece );

					if( $_pieces[0] === 'u' )
					{
						$oldId	= (int) $_pieces[1];
						break;
					}
				}
			}

			try
			{
				$data = (string) $this->app->getLink( $oldId, array( 'members', 'core_members' ) );
				return \IPS\Member::load( $data )->url();
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
		if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->name ) . html_entity_decode( $password ) ) ) )
		{
			return TRUE;
		}
		else if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->name ) . $password ) ) )
		{
			return TRUE;
		}
		/* In 4.2.6 we save the original members name as the salt so that we don't have one that has been modified in the conversion process */
		else if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->conv_password_extra ) . $password ) ) )
		{
			return TRUE;
		}
		else
		{
			require_once \IPS\ROOT_PATH . "/applications/convert/sources/Login/PasswordHash.php";
			$ph = new \PasswordHash( 8, TRUE );

			if( $ph->CheckPassword( mb_strtolower( $member->name ) . $password, $member->conv_password ) )
			{
				return TRUE;
			}
			/* In 4.2.6 we save the original members name as the salt so that we don't have one that has been modified in the conversion process */
			else if ( $ph->CheckPassword( mb_strtolower( $member->conv_password_extra ) . $password, $member->conv_password ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}
}