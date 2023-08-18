<?php

/**
 * @brief		Converter ExpressionEngine Master Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		14 June 2016
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ExpressionEngine Core Converter
 */
class _Expressionengine extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		return "Expression Engine";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		return 'expressionengine';
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertProfileFields'		=> array(
				'table'		=> 'exp_member_fields',
				'where'		=> NULL
			),
			'convertGroups'				=> array(
				'table'		=> 'exp_member_groups',
				'where'		=> NULL
			),
			'convertMembers'				=> array(
				'table'			=> 'exp_members',
				'where'			=> NULL
			),
			'convertAttachments'			=> array(
				'table'			=> 'exp_members',
				'where'			=> array( "sig_img_filename<>''" )
			),
			'convertPrivateMessages'		=> array(
				'table'			=> 'exp_message_data',
				'where'			=> NULL
			),
			'convertPrivateMessageReplies'	=> array(
				'table'			=> 'exp_message_data',
				'where'			=> NULL,
				'extra_steps'	=> array( 'convertPmAttachments' )
			),
			'convertPmAttachments'		=> array(
				'table'			=> 'exp_message_attachments',
				'where'			=> NULL,
			)
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
		$rows['convertPmAttachments'] = array(
			'step_method'		=> 'convertPmAttachments',
			'step_title'		=> 'convert_attachments',
			'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments' ),
			'source_rows'		=> array( 'table' => static::canConvert()['convertPmAttachments']['table'], 'where' => static::canConvert()['convertPmAttachments']['where'] ),
			'per_cycle'			=> 10,
			'dependencies'		=> array( 'convertPrivateMessageReplies' ),
			'link_type'			=> 'core_attachments',
		);

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
	 * Can we convert settings?
	 *
	 * @return	boolean
	 */
	public static function canConvertSettings()
	{
		return FALSE;
	}

	/**
	 * List of Conversion Methods that require more information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments',
			'convertProfileFields',
			'convertGroups',
			'convertMembers',
			'convertPrivateMessages'
		);
	}

	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		return nl2br( $post );
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

		switch ( $method )
		{
			case 'convertAttachments':
				$return['convertAttachments'] = array(
					'signature_attach_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Text',
						'field_default'			=> NULL,
						'field_required'		=> TRUE,
						'field_extra'			=> array(),
						'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_ee_sig_attach_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
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

				foreach( $this->db->select( '*', 'exp_member_fields' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['m_field_id']}"]			= $field['m_field_label'];
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['m_field_id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );

					$return['convertProfileFields']["map_pfield_{$field['m_field_id']}"] = array(
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

				foreach( $this->db->select( '*', 'exp_member_groups' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['group_id']}"]		= $group['group_title'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['group_id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['group_id']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;
			case 'convertMembers':
				$return['convertMembers'] = array();

				/* We can only retain one type of photo */
				$return['convertMembers']['photo_type'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'avatars',
					'field_required'		=> TRUE,
					'field_extra'			=> array( 'options' => array( 'avatars' => \IPS\Member::loggedIn()->language()->addToStack( 'avatars' ), 'profile_photos' => \IPS\Member::loggedIn()->language()->addToStack( 'profile_photos' ) ) ),
					'field_hint'			=> NULL,
				);

				/* Find out where the photos live */
				\IPS\Member::loggedIn()->language()->words['photo_location_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'photo_location_nodb_desc' );
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_ee_avatar_path'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				foreach( array( 'url', 'location', 'occupation', 'bio' ) AS $field )
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
			case 'convertPrivateMessages':
				$return['convertPrivateMessages'] = array(
					'pm_attach_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Text',
						'field_default'			=> NULL,
						'field_required'		=> TRUE,
						'field_extra'			=> array(),
						'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_ee_pm_attach_path'),
						'field_validation'		=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}

	/**
	 * Finish
	 *
	 * @return	array	Messages to display
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
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 1, array( 'app' ) );

		return array( "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild", "f_rebuild_attachments" );
	}

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'member_id' );

		$it = $this->fetch( 'exp_members', 'member_id', array( "sig_img_filename<>''" ) );

		foreach( $it AS $row )
		{
			$map = array(
				'id1'				=> $row['member_id'],
				'id1_type'			=> 'core_members',
				'id1_from_parent'	=> FALSE,
				'location_key'		=> 'core_Signatures'
			);

			$ext = explode( '.', $row['sig_img_filename'] );
			$ext = array_pop( $ext );

			$info = array(
				'attach_id'			=> $row['member_id'],
				'attach_file'		=> $row['sig_img_filename'],
				'attach_member_id'	=> $row['member_id'],
				'attach_ext'		=> $ext
			);

			$path		= rtrim( $this->app->_session['more_info']['convertAttachments']['signature_attach_location'], '/' ) . '/' . $row['sig_img_filename'];

			$attachId = $libraryClass->convertAttachment( $info, $map, $path );

			/* Update Post if we can */
			try
			{
				if ( $attachId !== FALSE )
				{
					$memberId = $this->app->getLink( $row['member_id'], 'core_members' );

					$signature = \IPS\Db::i()->select( 'signature', 'core_members', array( "member_id=?", $memberId ) )->first();

					$signature .= '[attachment=' . $attachId . ':name]';

					\IPS\Db::i()->update( 'core_members', array( 'signature' => $signature ), array( "member_id=?", $memberId ) );
				}
			}
			catch( \UnderflowException $e ) {}
			catch( \OutOfRangeException $e ) {}

			$libraryClass->setLastKeyValue( $row['member_id'] );
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

		foreach( $this->fetch( 'exp_member_fields', 'm_field_id' ) AS $row )
		{
			$info						= array();
			$info['pf_id']				= $row['m_field_id'];
			$merge						= $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['m_field_id']}"] != 'none' ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['m_field_id']}"] : NULL;
			$info['pf_type']			= ucwords( $row['m_field_type'] );
			$info['pf_name']			= $row['m_field_label'];
			$info['pf_desc']			= $row['m_field_description'];
			$info['pf_content'] 		= ( !\in_array( $row['m_field_type'], array( 'textbox', 'textarea' ) ) ) ? explode( "\r", $row['field_choices'] ) : NULL;
			$info['pf_not_null']		= $row['m_field_required'] == 'y' ? 1 : 0;
			$info['pf_member_hide']		= $row['m_field_public'] == 'n' ? 'hide' : 'all';
			$info['pf_max_input']		= $row['m_field_maxl'];
			$info['pf_member_edit'] 	= ( \in_array( $row['user_editable'], array( 'yes', 'once' ) ) ) ? 0 : 1;
			$info['pf_position']		= $row['m_field_order'];
			$info['pf_show_on_reg']		= $row['m_field_reg'] == 'y' ? 1 : 0;
			$info['pf_input_format']	= NULL;
			$info['pf_multiple']		= 0;

			$libraryClass->convertProfileField( $info, $merge );
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

		$libraryClass::setKey( 'group_id' );

		foreach( $this->fetch( 'exp_member_groups', 'group_id' ) AS $row )
		{
			/* Basic info */
			$info = array(
				'g_id'				=> $row['group_id'],
				'g_name'			=> $row['group_title'],
				'g_view_board'		=> ( $row['can_view_online_system'] == 'y' ) ? 1 : 0,
				'g_use_pm'			=> ( $row['can_send_private_messages'] == 'y' ) ? 1 : 0,
				'g_max_messages'	=> $row['prv_msg_storage_limit'],
				'g_pm_perday'		=> $row['prv_msg_send_limit'],
				'g_search_flood'	=> $row['search_flood_control']
			);

			$merge = ( $this->app->_session['more_info']['convertGroups']["map_group_{$row['group_id']}"] != 'none' ) ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['group_id']}"] : NULL;

			$libraryClass->convertGroup( $info, $merge );

			$libraryClass->setLastKeyValue( $row['group_id'] );
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

		$libraryClass::setKey( 'exp_members.member_id' );

		$it = $this->fetch( 'exp_members', 'exp_members.member_id' );

		foreach( $it AS $row )
		{
			/* Main Member Information */
			$info = array(
				'member_id'					=> $row['member_id'],
				'name'						=> $row['screen_name'],
				'email'						=> $row['email'],
				'conv_password'				=> $row['password'],
				'member_group_id'			=> $row['group_id'],
				'joined'					=> $row['join_date'],
				'ip_address'				=> $row['ip_address'],
				'bday_day'					=> $row['bday_d'],
				'bday_month'				=> $row['bday_m'],
				'bday_year'					=> $row['bday_y'],
				'msg_count_total'			=> $row['private_messages'],
				'last_visit'				=> $row['last_visit'],
				'last_activity'				=> $row['last_activity'],
				'signature'					=> $row['signature'],
				'members_bitoptions'		=> array(
					'view_sigs'					=> ( $row['display_signatures'] == 'y' ) ? 1 : 0
				),
				'pp_setting_count_comments'	=> 1,
				'allow_admin_mails'			=> ( $row['accept_admin_email'] == 'y' ) ? 1 : 0,
				'member_posts'				=> $row['total_forum_posts'],
			);

			/* Profile Fields */
			try
			{
				$profileFields = $this->db->select( '*', 'exp_member_data', array( "member_id=?", $row['member_id'] ) )->first();

				unset( $profileFields['member_id'] );

				/* Basic fields - we only need ID => Value, the library will handle the rest */
				foreach( $profileFields AS $key => $value )
				{
					$profileFields[ str_replace( 'm_field_id_', '', $key ) ] = $value;
				}
			}
			catch( \UnderflowException $e )
			{
				$profileFields = array();
			}

			/* Pseudo Fields */
			foreach( array( 'url', 'location', 'occupation', 'bio' ) AS $pseudo )
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
						'pf_max_input'		=> 500,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
					) );
				}

				$profileFields[ $pseudo ] = $row[ $pseudo ];
			}

			/* Profile Photos */
			if ( $this->app->_session['more_info']['convertMembers']['photo_type'] == 'profile_photos' )
			{
				$filename = $row['photo_filename'];
			}
			else
			{
				$filename = $row['avatar_filename'];
			}

			$libraryClass->convertMember( $info, $profileFields, $filename, rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' ) );

			$libraryClass->setLastKeyValue( $row['member_id'] );
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

		$libraryClass::setKey( 'message_id' );

		foreach( $this->fetch( 'exp_message_data', 'message_id' ) AS $row )
		{
			/* Reset map array */
			$maps = array();

			$topic = array(
				'mt_id'				=> $row['message_id'],
				'mt_date'			=> $row['message_date'],
				'mt_title'			=> $row['message_subject'],
				'mt_starter_id'		=> $row['sender_id'],
				'mt_start_time'		=> $row['message_date'],
				'mt_last_post_time'	=> $row['message_date'],
				'mt_to_count'		=> $row['total_recipients'],
			);

			/* Author Map */
			$maps[ $row['sender_id'] ] = array(
				'map_user_id'			=> $row['sender_id'],
				'map_read_time'			=> $row['message_date'],
				'map_user_active'		=> 1,
				'map_user_banned'		=> 0,
				'map_has_unread'		=> 0,
				'map_is_starter'		=> 1,
				'map_last_topic_reply'	=> $row['message_date']
			);

			foreach( $this->db->select( '*', 'exp_message_copies', array( 'message_id=?', $row['message_id'] ) ) AS $map )
			{
				$maps[ $map['recipient_id'] ] = array(
					'map_user_id'			=> $map['recipient_id'],
					'map_read_time'			=> $map['message_time_read'],
					'map_user_active'		=> 1,
					'map_user_banned'		=> 0,
					'map_has_unread'		=> ( $map['message_read'] == 'n' ) ? 1 : 0,
					'map_is_starter'		=> ( $map['recipient_id'] == $row['sender_id'] ) ? 1 : 0,
					'map_last_topic_reply'	=> $row['message_date']
				);
			}

			$libraryClass->convertPrivateMessage( $topic, $maps );

			$libraryClass->setLastKeyValue( $row['message_id'] );
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

		$libraryClass::setKey( 'message_id' );

		foreach( $this->fetch( 'exp_message_data', 'message_id' ) AS $row )
		{
			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $row['message_id'],
				'msg_topic_id'		=> $row['message_id'],
				'msg_date'			=> $row['message_date'],
				'msg_post'			=> $row['message_body'],
				'msg_author_id'		=> $row['sender_id'],
				'msg_is_first_post'	=> 1,
				'msg_ip_address'	=> '127.0.0.1',
			) );

			$libraryClass->setLastKeyValue( $row['message_id'] );
		}
	}

	/**
	 * Convert PM attachments
	 *
	 * @return	void
	 */
	public function convertPmAttachments()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'attachment_id' );

		$it = $this->fetch( 'exp_message_attachments', 'attachment_id' );

		foreach( $it AS $row )
		{
			$map = array(
				'id1'				=> $row['message_id'],
				'id1_type'			=> 'core_message_topics',
				'id1_from_parent'	=> FALSE,
				'id2'				=> $row['message_id'],
				'id2_type'			=> 'core_message_posts',
				'id2_from_parent'	=> FALSE
			);

			$info = array(
				'attach_id'			=> $row['attachment_id'],
				'attach_file'		=> $row['attachment_name'],
				'attach_date'		=> $row['attachment_date'],
				'attach_member_id'	=> $row['sender_id'],
				'attach_ext'		=> \strtolower( trim( $row['attachment_extension'], '.' ) ),
				'attach_filesize'	=> ( $row['attachment_size'] * 1000 ), //convert to kb
			);

			$path		= rtrim( $this->app->_session['more_info']['convertAttachments']['pm_attach_location'], '/' ) . '/' . $row['attachment_hash'] . $row['attachment_extension'];

			$attachId = $libraryClass->convertAttachment( $info, $map, $path );

			/* Update Post if we can */
			try
			{
				if ( $attachId !== FALSE )
				{
					$pmId = $this->app->getLink( $row['message_id'], 'core_message_posts' );

					$message = \IPS\Db::i()->select( 'msg_post', 'core_message_posts', array( "msg_id=?", $pmId ) )->first();

					$message .= '[attachment=' . $attachId . ':name]';

					\IPS\Db::i()->update( 'core_message_posts', array( 'msg_post' => $message ), array( "msg_id=?", $pmId ) );
				}
			}
			catch( \UnderflowException $e ) {}
			catch( \OutOfRangeException $e ) {}

			$libraryClass->setLastKeyValue( $row['attachment_id'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		/* If we can't access profiles, don't bother trying to redirect */
		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
		{
			return NULL;
		}

		$url = \IPS\Request::i()->url();

		if( preg_match( '#/member/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
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

	/**
	 * Process a login
	 *
	 * @param	\IPS\Member		$member			The member
	 * @param	string			$password		Password from form
	 * @return	bool
	 */
	public static function login( $member, $password )
	{
		$length = \strlen( $member->conv_password );
		$providedHash = FALSE;

		switch( $length )
		{
			/* MD5 */
			case 32:
				$providedHash = md5( $password );
			break;
			/* SHA1 */
			case 40:
				$providedHash = sha1( $password );
			break;
			/* SHA256 */
			case 64:
				$providedHash = hash( 'sha256', $password );
			break;
			/* SHA512 */
			case 128:
				$providedHash = hash( 'sha512', $password );
			break;
		}

		return ( \IPS\Login::compareHashes( $member->conv_password, $providedHash ) ) ? TRUE : FALSE;
	}
}