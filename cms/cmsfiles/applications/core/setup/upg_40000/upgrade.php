<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Mar 2013
 */

namespace IPS\core\setup\upg_40000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/* ! Member */
	/**
	 * Step 1
	 * Merge members and profile_portal
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 350;

		/* Create a temporary table */
		if ( !isset( \IPS\Request::i()->extra ) )
		{
			/* Rename members table */
			try
			{
				\IPS\Db::i()->renameTable( 'members', 'core_members' );
			}
			catch ( \IPS\Db\Exception $e )
			{
				if ( $e->getCode() != 1050 )
				{
					/* 1050: Core members already exists */
					throw $e;
				}
			}

			/* Set profile data table to myisam for faster inserts, if needed */
			$_SESSION['convertPfieldsEngine']	= FALSE;
			$pfieldsContent = \IPS\Db::i()->getTableDefinition( 'core_pfields_content', false, true );

			if( isset( $pfieldsContent['engine'] ) AND mb_strtolower( $pfieldsContent['engine'] ) == 'innodb' )
			{
				$_SESSION['convertPfieldsEngine']	= TRUE;
				\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_pfields_content ENGINE=MyISAM" );
			}
		}

		/* Define temporary table */
		$membersDefinition = \IPS\Db::i()->getTableDefinition( 'core_members', false, true );
		
		try
		{
			$profileDefinition = \IPS\Db::i()->getTableDefinition( 'profile_portal', false, true );
		}
		catch( \OutOfRangeException $ex )
		{
			/* Table does not exist: have we already finished the conversion and this step is being re-run accidetally? */
			if ( isset( $_SESSION['core_40000_step1_finished'] ) )
			{
				/* Return TRUE to increase _upgradeStep */
				return TRUE;
			}
			
			throw $ex;
		}
		
		$membersDefinition['name'] = 'core_members_temp';

		/* Inserts into myisam are much faster typically */
		$_SESSION['convertMembersEngine']	= FALSE;

		if( isset( $membersDefinition['engine'] ) AND mb_strtolower( $membersDefinition['engine'] ) == 'innodb' )
		{
			$_SESSION['convertMembersEngine']	= TRUE;
			$membersDefinition['engine']		= 'MyISAM';
		}

		/* Get the new default schema and adjust table - only add indexes from the 4.0 definition */
		$schema	= json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/core/data/schema.json' ), TRUE );
		$membersDefinition['columns']	= array_merge( $membersDefinition['columns'], $schema['core_members']['columns'] );
		$membersDefinition['indexes']	= $schema['core_members']['indexes'];

		/* Remove some columns */
		foreach( array( 'ignored_users', 'org_perm_id', 'members_l_display_name', 'member_uploader', 'fb_emailhash', 'ips_mobile_token', 'members_editor_choice',
				'view_img', 'dst_in_use', 'login_anonymous', 'members_auto_dst', 'member_banned', 'tc_lastsync', 'fb_session', 'members_l_username', 'members_display_name',
				'view_sigs', 'coppa_user', 'members_created_remote', 'unacknowledged_warnings', 'fb_lastsync', 'time_offset', 'posts', 'title', 'last_post' ) as $column )
		{
			if( isset( $membersDefinition['columns'][ $column ] ) )
			{
				unset( $membersDefinition['columns'][ $column ] );
			}
		}

		/* Add profile portal */
		foreach( $profileDefinition['columns'] as $key => $definition )
		{
			if ( ! \in_array( $key, array( 'pp_member_id', 'pp_setting_moderate_comments', 'pp_setting_count_friends', 'pp_profile_update',
				'pp_setting_moderate_friends', 'avatar_location', 'avatar_size', 'avatar_type', 'pp_about_me', 'pp_rating_hits', 'pp_rating_value', 'pp_rating_real' ) ) )
			{
				$membersDefinition['columns'][ $key ] = ( isset( $schema['core_members']['columns'][ $key ] ) ) ? $schema['core_members']['columns'][ $key ] : $definition;
			}
		}

		/* Do we need this column too? */
		if( \IPS\Db::i()->checkForTable('downloads_categories' ) )
		{
			$membersDefinition['columns']['idm_block_submissions'] = array(
				"name"		=> "idm_block_submissions",
				"type"		=> "TINYINT",
				"length"	=> 1,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "Blocked from submitting Downloads files?",
				"unsigned"	=> true
			);
		}

		/* Create a temporary table */
		if ( !isset( \IPS\Request::i()->extra ) )
		{
			/* Groups */
			foreach( \IPS\Db::i()->select( 'g_id, g_title', 'core_groups' ) as $group )
			{
				\IPS\Lang::saveCustom( 'core', "core_group_{$group['g_id']}", $group['g_title'] );
			}

			\IPS\Db::i()->dropColumn( 'core_groups', 'g_title' );

			/* Set tables */
			\IPS\Db::i()->dropTable( 'core_members_temp', TRUE );
			\IPS\Db::i()->createTable( $membersDefinition );

			/* Create the about me profile field group */
			$group	= \IPS\Db::i()->insert( 'core_pfields_groups', array( 'pf_group_key' => 'profile_40_fields', 'pf_group_name' => "Profile Fields" ) );
			\IPS\Lang::saveCustom( 'core', 'core_pfieldgroups_' . $group, "Profile Fields" );

			/* Create the about me profile field */
			$aboutMe	= new \IPS\core\ProfileFields\Field;
			$aboutMe->group_id		= $group;
			$aboutMe->type			= "Editor";
			$aboutMe->content		= NULL;
			//$aboutMe->multiple		= FALSE;
			$aboutMe->not_null		= FALSE;
			$aboutMe->max_input		= 0;
			$aboutMe->input_format	= NULL;
			$aboutMe->search_type	= "loose";
			$aboutMe->format		= NULL;
			$aboutMe->admin_only	= FALSE;
			$aboutMe->show_on_reg	= FALSE;
			$aboutMe->member_edit	= TRUE;
			$aboutMe->member_hide	= FALSE;
			
			try
			{
				$aboutMe->save();

				/* We have to store the title for now so step6 won't wipe it out */ 
				\IPS\Db::i()->update( 'core_pfields_data', array( 'pf_title' => "About Me" ), "pf_id=" . $aboutMe->id );

				\IPS\Lang::saveCustom( 'core', 'core_pfield_' . $aboutMe->id, "About Me" );
				\IPS\Lang::saveCustom( 'core', 'core_pfield_' . $aboutMe->id . '_desc', "" );
			}
			catch( \Exception $ex )
			{
				\IPS\Log::log( $ex, 'upgrade' );
			}

			$_SESSION['aboutMe_Field']	= $aboutMe->id;

			return '0';
		}

		/* Figure out guest/banned/validating groups */
		require \IPS\ROOT_PATH . '/conf_global.php';

		$bannedGroup		= $INFO['banned_group'];
		$guestGroup			= $INFO['guest_group'];
		$validatingGroup	= $INFO['auth_group'];
		$memberGroup		= $INFO['member_group'];

		/* If the admin for some reason set member_group and auth_group to the same value in conf_global, that's no good */
		if( $validatingGroup == $memberGroup )
		{
			$validatingGroup = NULL;
		}
		
		$url        = \IPS\Request::i()->url();
		
		/* Make sure we haven't changed the admin directory */
		$admindir = 'admin';
		if ( \defined( '\IPS\CP_DIRECTORY' ) AND \IPS\CP_DIRECTORY !== $admindir )
		{
			$admindir = preg_quote( \IPS\CP_DIRECTORY );
		}
		
		$settings   = array( 'upload_dir' => \IPS\ROOT_PATH . '/uploads' , 'upload_url' => preg_replace( "#/{$admindir}/.*$#", '/uploads', $url ) );
		
		foreach( \IPS\Db::i()->select( '*', 'core_sys_conf_settings', array( "conf_key IN('upload_dir', 'upload_url')" ) ) as $row )
		{
			if ( $row['conf_value'] )
			{
				$settings[ $row['conf_key'] ] = $row['conf_value'];
			}
		}

		/* Get a list of validating member IDs to make sure we flag the account as validating if appropriate. We grab this here in one query
			instead of querying each member individually inside the loop later for efficiency. */
		$validatingMemberIds	= array();

		foreach( \IPS\Db::i()->select( 'member_id', 'core_validating', array( 'lost_pass != ?', 1 ) ) as $memberId )
		{
			$validatingMemberIds[ $memberId ]	= $memberId;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();
		$did			= 0;

		/* Import data */
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$_SESSION['upg_40000_member_id'] = isset( $_SESSION['upg_40000_member_id'] ) ? $_SESSION['upg_40000_member_id'] : 0;
		$select = \IPS\Db::i()->select( 'core_members.*, profile_portal.*', 'core_members', array( 'core_members.member_id>'. $_SESSION['upg_40000_member_id'] ), 'core_members.member_id', $perCycle )
			->join( 'profile_portal', 'profile_portal.pp_member_id=core_members.member_id' );
		if ( \count( $select ) )
		{
			$inserts = array();
			foreach ( $select as $row )
			{
				if( $cutOff !== null AND time() >= $cutOff )
				{
					return ( $limit + $did );
				}

				$did++;

				foreach ( array( 'view_sigs' => 65536, 'coppa_user' => 524288, 'members_created_remote' => 8388608, 'unacknowledged_warnings' => 33554432, 'pp_setting_moderate_followers' => 134217728, 'pp_setting_count_visitors' => 268435456 ) as $k => $v )
				{
					if ( $row[ $k ] )
					{
						$row['members_bitoptions'] += $v;
					}
					unset( $row[ $k ] );
				}
				
				/* Fix ban lines */
				foreach( array( 'mod_posts', 'temp_ban', 'restrict_post' ) as $column )
				{
					if( $row[ $column ] )
					{
						if( $row[ $column ] == 1 )
						{
							$row[ $column ]	= -1;
						}
						else
						{
							$arr	= array();
							list( $arr['date_start'], $arr['date_end'], $arr['timespan'], $arr['unit'] ) = explode( ":", $row[ $column ] );


							$row[ $column ]	= $arr['date_end'] > 2147483647 ? -1 : $arr['date_end'];
						}
					}
					else
					{
						$row[ $column ] = 0;
					}
				}
				
				/* Salvage some member cache items */
				if ( isset( $row['members_cache'] ) )
				{
					$membersCache = unserialize( $row['members_cache'] );
					
					if ( \is_array( $membersCache ) and isset( $membersCache['notifications'] ) )
					{
						if ( $membersCache['show_notification_popup'] )
						{
							$row['members_bitoptions2'] += 1;
						}

						/* This is for IP.Downloads but we need it here */
						if( isset( $membersCache['block_file_submissions'] ) AND $membersCache['block_file_submissions'] )
						{
							if( !\IPS\Db::i()->checkForColumn( 'core_members_temp', 'idm_block_submissions' ) )
							{
								\IPS\Db::i()->addColumn( 'core_members_temp', array(
									"name"		=> "idm_block_submissions",
									"type"		=> "TINYINT",
									"length"	=> 1,
									"null"		=> false,
									"default"	=> 0,
									"comment"	=> "Blocked from submitting Downloads files?",
									"unsigned"	=> true
								)	);
							}

							$row['idm_block_submissions']	= 1;
						}
						
						$notifications = array();
						
						foreach( $membersCache['notifications'] as $type => $selected )
						{
							if ( \is_array( $selected ) )
							{
								switch( $type )
								{
									case 'new_comment':
									case 'followed_topics':
										foreach( $selected as $item )
										{
											if( \is_array( $item ) )
											{
												foreach( $item as $method )
												{
													$notifications['new_comment'][ $method ] = $method;
												}
											}
										}
									break;
									case 'new_likes':
									case 'post_quoted':
									case 'new_private_message':
									case 'warning':
										foreach( $selected as $item )
										{
											if( \is_array( $item ) )
											{
												foreach( $item as $method )
												{
													$notifications[ $type ][ $method ] = $method;
												}
											}
										}
									break;
								}
							}
						}
						
						if ( \count( $notifications ) )
						{
							foreach( $notifications as $type => $selected )
							{
								try
								{
									$selected	= array_filter( $selected, function( $val ){
										if( $val == 'email' OR $val == 'inline' )
										{
											return true;
										}

										return false;
									});

									\IPS\Db::i()->insert( 'core_notification_preferences', array(
										'member_id'        => $row['member_id'],
										'notification_key' => $type,
										'preference'       => implode( ',', array_keys( $selected ) )
									) );
								}
								catch( \IPS\Db\Exception $e )
								{
									/* Ignore duplicate index error */
									if ( $e->getCode() != 1062 )
									{
										throw $e;
									}
								}
							}
						}
					}
				}

				/* Fix serialization */
				if( $row['pp_last_visitors'] !== null )
				{
					$row['pp_last_visitors']	= json_encode( unserialize( $row['pp_last_visitors'] ) );
				}

				/* Fix twitter/fb sync options */
				if( $row['tc_bwoptions'] OR $row['fb_bwoptions'] )
				{
					$profileSettings	= array();

					if( $row['tc_bwoptions'] )
					{
						$profileSettings['Twitter'] = array(
							'photo'		=> (bool) ( $row['tc_bwoptions'] & 1 ),
							'cover'		=> (bool) ( $row['tc_bwoptions'] & 8 ),
							'status'	=> (bool) ( $row['tc_bwoptions'] & 16 ),
						);
					}

					if( $row['fb_bwoptions'] )
					{
						$profileSettings['Facebook'] = array(
							'photo'		=> (bool) ( $row['fb_bwoptions'] & 1 ),
							'cover'		=> false,
							'status'	=> (bool) ( $row['fb_bwoptions'] & 8 ),
						);
					}

					$row['profilesync']	= json_encode( $profileSettings );
				}

				/* Fix auto-track */
				if( $row['auto_track'] and $row['auto_track'] != 'none' )
				{
					if( $row['auto_track'] == 'offline' )
					{
						$row['auto_track'] = 'immediate';
					}

					$row['auto_track']	= json_encode( array( 'comments' => 1, 'content' => 1, 'method' => $row['auto_track'] ) );
				}
				else
				{
					$row['auto_track']	= NULL;
				}

				/* Fix profile photo URL if appropriate - we'll address the rest of the file storage later but it would be better to avoid multiple loops on members */
				if( $row['pp_main_photo'] )
				{
					/* Remove double slashes from a previous bad configuration */
					if( preg_match( '/(?<!:)(\/{2,})/', $row['pp_main_photo'] ) )
					{
						$row['pp_main_photo'] = preg_replace( '/(?<!:)(\/{2,})/', '/', $row['pp_main_photo'] );
					}

					$_test = parse_url( $row['pp_main_photo'] );
					$_local = parse_url( \IPS\Settings::i()->base_url );

					if( isset( $_test['host'] ) AND $_test['host'] == $_local['host'] )
					{
						/* We just want dir/file.ext and not the full URL */
						$row['pp_main_photo']	= str_replace( rtrim( $settings['upload_url'], '/' ) . '/', '', $row['pp_main_photo'] );
						$row['pp_main_photo']	= str_replace( \IPS\Settings::i()->base_url, '', $row['pp_main_photo'] );
					}
				}

				if( $row['pp_thumb_photo'] )
				{
					/* Remove double slashes from a previous bad configuration */
					if( preg_match( '/(?<!:)(\/{2,})/', $row['pp_thumb_photo'] ) )
					{
						$row['pp_thumb_photo'] = preg_replace( '/(?<!:)(\/{2,})/', '/', $row['pp_thumb_photo'] );
					}

					$_test = parse_url( $row['pp_thumb_photo'] );
					$_local = parse_url( \IPS\Settings::i()->base_url );

					if( isset( $_test['host'] ) AND $_test['host'] == $_local['host'] )
					{
						/* We just want dir/file.ext and not the full URL */
						$row['pp_thumb_photo']	= str_replace( rtrim( $settings['upload_url'], '/' ) . '/', '', $row['pp_thumb_photo'] );
						$row['pp_thumb_photo']	= str_replace( \IPS\Settings::i()->base_url, '', $row['pp_thumb_photo'] );
					}
				}

				/* Ban User */
				if ( $row['member_banned'] == 1 )
				{
					$row['temp_ban']	= -1;
				}
				else if ( $row['temp_ban'] !== 0 )
				{
					$arr = array();
					list( $arr['date_start'], $arr['date_end'], $arr['timespan'], $arr['unit'] ) = explode( ":", $row['temp_ban'] );

					$factor = $arr['unit'] == 'd' ? 86400 : 3600;

					$date_end = time() + ( $arr['timespan'] * $factor );

					$row['temp_ban'] = $date_end;
				}

				/* Fix groups */
				if( $row['member_group_id'] == $bannedGroup )
				{
					$row['temp_ban']		= -1;
					$row['member_group_id']	= $memberGroup;
				}
				else if( $row['member_group_id'] == $validatingGroup )
				{
					$row['members_bitoptions'] += 1073741824;
					$row['member_group_id']	= $memberGroup;

					/* We need to make sure they have a validating table entry, otherwise they may not be able to access the community */
					if( !\in_array( $row['member_id'], $validatingMemberIds ) )
					{
						\IPS\Db::i()->insert( 'core_validating', array(
							'vid'			=> \IPS\Login::generateRandomString(),
							'member_id'		=> $row['member_id'],
							'entry_date'	=> time(),
							'email_chg'		=> TRUE,
							'ip_address'	=> \IPS\Request::i()->ipAddress(),
							'prev_email'	=> $row['email']
						) );
					}
				}
				else if( $row['member_group_id'] == $guestGroup )
				{
					$row['member_group_id']	= $memberGroup;
				}

				/* Or do we need to set validating flag because they have a row in core_validating? */
				if( \in_array( $row['member_id'], $validatingMemberIds ) )
				{
					$row['members_bitoptions'] += 1073741824;
				}
				
				/* Make sure the account does not have guest, validating or banned groups in secondary groups */
				$newSecondaryGroups = array();
				foreach( explode( ',', $row['mgroup_others'] ) AS $secondaryGroup )
				{
					if ( $secondaryGroup AND ! \in_array( $secondaryGroup, array( $bannedGroup, $validatingGroup, $guestGroup ) ) )
					{
						$newSecondaryGroups[] = $secondaryGroup;
					}
				}
				
				$row['mgroup_others'] = implode( ',', $newSecondaryGroups );

				/* Handle gallery adjustments now, if necessary - we do this to prevent looping through them in the gallery upgrader routine again */
				if( isset( $row['gallery_perms'] ) AND $row['gallery_perms'] != '1:1' AND $row['gallery_perms'] != '1:1:1' )
				{
					$perms	= explode( ':', $row['gallery_perms'] );

					if( !$perms[0] )
					{
						$row['members_bitoptions2'] |= 2;
					}
					else
					{
						$row['members_bitoptions2'] &=~ 0;
					}

					if( !$perms[1] )
					{
						$row['members_bitoptions2'] |= 4;
					}
					else
					{
						$row['members_bitoptions2'] &=~ 0;
					}
				}
				
				foreach( array(
					'restrict_post',
					'mod_posts',
					'pp_reputation_points',
					'pp_main_width',
					'pp_main_height',
					'pp_thumb_width',
					'pp_thumb_height',
					'fb_bwoptions',
					'tc_bwoptions'
				 ) as $notNullIntField )
				{
					if ( $row[ $notNullIntField ] === NULL )
					{
						$row[ $notNullIntField ] = \intval( $row[ $notNullIntField ] );
					}
				}

				foreach( array(
					'pp_main_photo',
					'pp_thumb_photo',
					'signature',
					'pconversation_filters',
					'fb_photo',
					'fb_photo_thumb',
					'tc_photo',
					'pp_customization',
					'pp_cover_photo',
					'pp_gravatar',
					'pp_photo_type'
				) as $notEmptyField )
				{
					if ( empty( $row[ $notEmptyField ] ) )
					{
						$row[ $notEmptyField ] = '';
					}
				}

				/* Handle other columns, such as those from third party addons */
				foreach( $row as $field => $value )
				{
					if( !$membersDefinition['columns'][ $field ]['allow_null'] AND \is_null( $value ) )
					{
						if( \in_array( $membersDefinition['columns'][ $field ]['type'], array( 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT' ) ) )
						{
							$row[ $field ]	= 0;
						}
						else
						{
							$row[ $field ]	= '';
						}
					}
				}
			
				/* Name or display name? */
				if ( $_SESSION['upgrade_options']['core']['40000']['username_or_displayname'] == 'display_name' AND $row['members_display_name'] )
				{
					$row['name'] = $row['members_display_name'];
				}
				
				if ( ! $row['name'] )
				{
					/* As of 4.0 this cannot be blank, but older DBs may have some blank names */
					$row['name'] = $row['member_id'] . '_' . time();
				}

                /* Decode names */
                $row['name'] = \IPS\Text\Parser::utf8mb4SafeDecode( $row['name'] );

                /* Remap some columns */
                $row['member_posts']		= (int) $row['posts'];
                $row['member_title']		= $row['title'];
                $row['member_last_post']	= (int) $row['last_post'];
                $row['marked_site_read']	= time();
                
                /* Convert failed logins */
                if ( $row['failed_logins'] )
                {
	                $failedLogins = array();
	                foreach( explode( ',', $row['failed_logins'] ) AS $failure )
	                {
		                $fail = explode( '-', $failure );
		                
		                /* Prevent PHP Notices */
		                if ( !isset( $failedLogins[$fail[1]] ) )
		                {
			                $failedLogins[$fail[1]] = array();
		                }
		                
		                $failedLogins[$fail[1]][] = $fail[0];
	                }
	                
	                $row['failed_logins'] = json_encode( $failedLogins );
                }

                if ( $row['pconversation_filters'] )
				{
					$_folders = unserialize( $row['pconversation_filters'] );
					$folders  = array();
					
					if ( \is_array( $_folders ) and \count( $_folders ) )
					{
						foreach( $_folders as $k => $folder )
						{
							$folders[ $folder['id'] ] = $folder['real'] ?: $folder['id'];
						}
					}
					
					$row['pconversation_filters'] = json_encode( $folders );
				}
				
				/* Convert timezone from offset to name */
				$timezone	= "UTC";
	
				$row['timezone']	= $timezone;
				if( $row['time_offset'] and preg_match( '#^([0-9\-\+]+?)$#', $row['time_offset'] ) )
				{
					$tz	= timezone_name_from_abbr( null, $row['time_offset'] * 3600, true );
	
					if ( $tz === FALSE )
					{
						$tz	= timezone_name_from_abbr( null, $row['time_offset'] * 3600, false );
					}
	
					if ( $tz !== FALSE )
					{
						$row['timezone'] = $tz;
					}
				}

				/* Make sure any customisations to the table are removed */
				$aboutMe	= $row['pp_about_me'];

				foreach( $row as $k => $v )
				{
					if ( ! \in_array( $k, array_keys( $membersDefinition['columns'] ) ) )
					{
						unset( $row[ $k ] );
					}
				}

				/* Save the last member_id */
				$_SESSION['upg_40000_member_id'] = $row['member_id'];

				/* Build insert */
				try
				{
					\IPS\Db::i()->insert( 'core_members_temp', $row );
				}
				catch( \IPS\Db\Exception $ex )
				{
					/* Ignore duplicate warnings */
					if ( ! \in_array( $ex->getCode(), array( 1062 ) ) )
					{
						throw $ex;
					}
				}

				try
				{
					\IPS\Db::i()->insert( 'core_pfields_content', array( 'member_id' => $row['member_id'], 'field_' . $_SESSION['aboutMe_Field'] => $aboutMe ), TRUE );
				}
				catch( \IPS\Db\Exception $ex )
				{
					/* Ignore duplicate warnings */
					if ( ! \in_array( $ex->getCode(), array( 1062 ) ) )
					{
						throw $ex;
					}
				}
			}

			return ( $limit + $did );
		}
		else
		{
			if ( ! isset( \IPS\Request::i()->run_anyway ) )
			{
				/* Finish */
				\IPS\Db::i()->dropTable( 'core_members' );
				\IPS\Db::i()->dropTable( 'profile_portal' );
				\IPS\Db::i()->renameTable( 'core_members_temp', 'core_members' );
				
				if ( $bannedGroup )
				{
					\IPS\Db::i()->delete( 'core_groups', 'g_id=' . $bannedGroup );
				}
	
				if ( $validatingGroup )
				{
					\IPS\Db::i()->delete( 'core_groups', 'g_id=' . $validatingGroup );
				}
	
				if( isset( $_SESSION['idm_block_submissions'] ) )
				{
					unset( $_SESSION['idm_block_submissions'] );
				}
	
				unset( $_SESSION['aboutMe_Field'], $_SESSION['_step1Count'], $_SESSION['upg_40000_member_id'] );
			}
			
			$_SESSION['core_40000_step1_finished'] = true;
			
			/* Do we need to convert the engine? */
			if( $_SESSION['convertMembersEngine'] == true OR $_SESSION['convertPfieldsEngine'] == true )
			{
				$toRunQueries	= array();

				if( $_SESSION['convertMembersEngine'] == true )
				{
					$toRunQueries[]	= array(
						'table' => 'core_members',
						'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members ENGINE=InnoDB"
					);
				}

				if( $_SESSION['convertPfieldsEngine'] == true )
				{
					$toRunQueries[]	= array(
						'table' => 'core_pfields_content',
						'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_pfields_content ENGINE=InnoDB"
					);
				}

				$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
				
				unset( $_SESSION['convertMembersEngine'], $_SESSION['convertPfieldsEngine'] );

				if ( \count( $toRun ) )
				{
					\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 2 ) ) );

					/* Queries to run manually */
					return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
				}
			}

			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('core_members') )
		{
			if( !isset( $_SESSION['_step1Count'] ) )
			{
				$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members' )->first();
			}

			$message = "Upgrading members (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
		}
		else
		{
			$message = "Upgrading members";
		}
		
		return $message;
	}
	
	/* ! Log in handlers */
	/**
	 * Step 2
	 * Convert login handlers
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if ( isset( $_SESSION['core_40000_step1_finished'] ) )
		{
			unset( $_SESSION['core_40000_step1_finished'] );
		}
		
		/* Convert serialized data to json encoded */
		$did		= array();
		$available	= array( 'External', 'Facebook', 'Twitter', 'Internal', 'Ldap', 'Live', 'Linkedin', 'Google', 'Ipsconnect', 'Convert' );
		
		foreach( \IPS\Db::i()->select( '*', 'login_methods' ) as $login )
		{
			$data	= NULL;

			$login['login_folder_name'] = mb_ucfirst( $login['login_folder_name'] );
			if( $login['login_folder_name'] == 'internal' )
			{
				$login['login_folder_name'] = 'Internal';
			}

			if( $login['login_custom_config'] !== NULL )
			{
				$data	= unserialize( $login['login_custom_config'] );
			}

			/* Retain log in method (username, email or both) */
			if( $login['login_user_id'] )
			{
				$data = ( \is_array( $data ) ) ? $data : array();

				switch( $login['login_user_id'] )
				{
					case 'either':
						$data['auth_types']	= 3;
					break;

					case 'email':
						$data['auth_types']	= 2;
					break;

					case 'username':
						$data['auth_types']	= 1;
					break;
				}
			}

			if( $data !== NULL )
			{
				$data	= json_encode( $data );
			}
			
			/* Make sure we actually have these log in classes still in 4.0 */
			if ( ! file_exists( \IPS\ROOT_PATH . '/system/Login/' . $login['login_folder_name'] . '.php' ) )
			{
				continue;
			}
			
			/* 3.x did not specify a unique key on login_folder_name, so there may be duplicates */
			try
			{
				$exists = \IPS\Db::i()->select( 'COUNT(*)', 'core_login_handlers', array( "login_key=?", $login['login_folder_name'] ) )->first();
				
				if ( $exists )
				{
					continue;
				}
			}
			catch( \UnderflowException $e ) {}

			/* Enable converter login method automatically if needed */
			if( $login['login_folder_name'] == 'Convert' AND \IPS\Db::i()->checkForTable('conv_apps') )
			{
				$login['login_enabled']	= 1;
			}

			$loginAcp = ( $login['login_folder_name'] == 'Internal' ) ? 1 : 0;
			
			\IPS\Db::i()->insert( 'core_login_handlers', array( 'login_settings' => $data, 'login_key' => $login['login_folder_name'], 'login_enabled' => $login['login_enabled'], 'login_order' => $login['login_order'], 'login_acp' => $loginAcp ) );

			$did[]	= $login['login_folder_name'];
		}

		\IPS\Db::i()->dropTable( 'login_methods' );
		
		/* Fetch settings */
		$settings = array();
		foreach( \IPS\Db::i()->select( '*', 'core_sys_conf_settings', array( "conf_key IN('fbc_appid', 'fbc_secret', 'fb_realname', 'tc_token', 'tc_secret' )" ) ) as $row )
		{
			if ( $row['conf_value'] )
			{
				$settings[ $row['conf_key'] ] = $row['conf_value'];
			}
		}

		/* And insert dummy rows for the rest */
		$max	= \IPS\Db::i()->select( 'MAX(login_order)', 'core_login_handlers' )->first();

		foreach( array_diff( $available, $did ) as $method )
		{
			$max++;
			\IPS\Db::i()->insert( 'core_login_handlers', array( 'login_settings' => json_encode( array() ), 'login_key' => $method, 'login_enabled' => 0, 'login_order' => $max ) );
		}

		/* Import FB settings if available */
		$fbSettings = array(
			'app_id'		=> $settings['fbc_appid'],
			'app_secret'	=> $settings['fbc_secret'],
			'real_name'		=> ( \in_array( $settings['fb_realname'], array( 'prefilled', 'enforced' ) ) ) ? true : false,
		);

		$update = array( 'login_settings' => json_encode( $fbSettings ) );

		if( $fbSettings['app_id'] AND $fbSettings['app_secret'] )
		{
			$update['login_enabled'] = 1;
		}

		\IPS\Db::i()->update( 'core_login_handlers', $update, array( 'login_key=?', 'facebook' ) );

		/* Shut off converter handler if appropriate */
		if( !\IPS\Db::i()->checkForTable( 'conv_apps' ) )
		{
			\IPS\Db::i()->update( 'core_login_handlers', array( 'login_enabled' => 0 ), array( 'login_key=?', 'convert' ) );
		}

		/* Import Twitter settings if available */
		$twSettings = array(
			'consumer_key'		=> $settings['tc_token'],
			'consumer_secret'	=> $settings['tc_secret'],
			'name'				=> false,
		);

		$update = array( 'login_settings' => json_encode( $twSettings ) );

		if( $twSettings['consumer_key'] AND $twSettings['consumer_secret'] )
		{
			$update['login_enabled'] = 1;
		}

		\IPS\Db::i()->update( 'core_login_handlers', $update, array( 'login_key=?', 'twitter' ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading login handlers";
	}

	/* ! DB Columns to lang strings */
	/**
	 * Step 3
	 * Convert old DB name columns to language strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* Member ranks */
		foreach( \IPS\Db::i()->select( 'id, title', 'core_member_ranks' ) as $rank )
		{
			\IPS\Lang::saveCustom( 'core', "core_member_rank_{$rank['id']}", $rank['title'] );
		}

		/* Reputation levels */
		foreach( \IPS\Db::i()->select( 'level_id, level_title', 'core_reputation_levels' ) as $reputation )
		{
			\IPS\Lang::saveCustom( 'core', "core_reputation_level_{$reputation['level_id']}", $reputation['level_title'] );
		}

		/* Warn reason */
		foreach( \IPS\Db::i()->select( 'wr_id, wr_name', 'core_members_warn_reasons' ) as $reason )
		{
			\IPS\Lang::saveCustom( 'core', "core_warn_reason_{$reason['wr_id']}", $reason['wr_name'] );
		}

		/* Question and answer challenges */
		foreach( \IPS\Db::i()->select( 'qa_id, qa_question', 'core_question_and_answer' ) as $qa )
		{
			\IPS\Lang::saveCustom( 'core', "core_question_and_answer_{$qa['qa_id']}", $qa['qa_question'] );
		}

		/* Drop the old columns now */
		\IPS\Db::i()->dropColumn( 'core_reputation_levels', 'level_title' );
		\IPS\Db::i()->dropColumn( 'core_members_warn_reasons', 'wr_name' );
		\IPS\Db::i()->dropColumn( 'core_question_and_answer', 'qa_question' );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Converting titles to language strings";
	}

	/* ! DB Columns to lang strings */
	/**
	 * Step 4
	 * Convert old DB name columns to language strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		/* Custom Profile Group Names */
		foreach( \IPS\Db::i()->select( 'pf_group_id, pf_group_name', 'core_pfields_groups' ) as $group )
		{
			\IPS\Lang::saveCustom( 'core', "core_pfieldgroups_{$group['pf_group_id']}", $group['pf_group_name'] );
		}

		/* Custom Emoticon group names */
		$emoticonSets = array();

		foreach( \IPS\Db::i()->select( 'id, emo_set', 'core_emoticons', NULL, NULL, 'emo_set' ) as $emoticons )
		{
			/* We can't have special characters in the language string key or it breaks */
			$thisSet = preg_replace( "/[^a-zA-Z0-9_]/", '_', $emoticons['emo_set'] );

			if( \in_array( $thisSet, $emoticonSets ) )
			{
				continue;
			}

			$emoticonSets[]	= $thisSet;

			\IPS\Lang::saveCustom( 'core', "core_emoticon_group_{$thisSet}", ucfirst( $emoticons['emo_set'] ) );

			/* If we had to adjust the key, update our emoticons */
			if( $thisSet != $emoticons['emo_set'] )
			{
				\IPS\Db::i()->update( 'core_emoticons', array( 'emo_set' => $thisSet ), array( 'emo_set=?', $emoticons['emo_set'] ) );
			}
		}

		/* Drop the old columns now */
		\IPS\Db::i()->dropColumn( 'core_pfields_groups', 'pf_group_name' );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Converting titles to language strings";
	}

	/* ! Group settings to module permissions */
	/**
	 * Step 5
	 * Convert group settings to module permissions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		$search		= array();
		$profiles	= array();
		$messenger	= array();

		foreach( \IPS\Db::i()->select( 'g_id, g_use_search, g_mem_info, g_use_pm', 'core_groups' ) as $group )
		{
			if( $group['g_use_search'] )
			{
				$search[]	= $group['g_id'];
			}

			if( $group['g_mem_info'] )
			{
				$profiles[]	= $group['g_id'];
			}

			if( $group['g_use_pm'] )
			{
				$messenger[]	= $group['g_id'];
			}
		}
		
		try
		{
			$searchModule	= \IPS\Db::i()->select( 'sys_module_id', 'core_modules', array( 'sys_module_application=? AND sys_module_key=? AND sys_module_area=?', 'core', 'search', 'front' ) )->first();
		}
		catch( \UnderflowException $ex )
		{
			$searchModule	= \IPS\Db::i()->insert( 'core_modules', array( 'sys_module_application' => 'core', 'sys_module_key' => 'search', 'sys_module_area' => 'front', 'sys_module_visible' => 1 , 'sys_module_version' => 0 ) );
		}

		\IPS\Db::i()->replace( 'core_permission_index', array( 'app' => 'core', 'perm_type' => 'module', 'perm_type_id' => $searchModule, 'perm_view' => implode( ',', $search ) ) );
		
		try
		{
			$profileModule	= \IPS\Db::i()->select( 'sys_module_id', 'core_modules', array( 'sys_module_application=? AND sys_module_key=? AND sys_module_area=?', 'core', 'members', 'front' ) )->first();
		}
		catch( \UnderflowException $ex )
		{
			$profileModule	= \IPS\Db::i()->insert( 'core_modules', array( 'sys_module_application' => 'core', 'sys_module_key' => 'members', 'sys_module_area' => 'front', 'sys_module_visible' => 1 , 'sys_module_version' => 0 ) );
		}

		\IPS\Db::i()->replace( 'core_permission_index', array( 'app' => 'core', 'perm_type' => 'module', 'perm_type_id' => $profileModule, 'perm_view' => implode( ',', $profiles ) ) );
		
		try
		{
			$messageModule	= \IPS\Db::i()->select( 'sys_module_id', 'core_modules', array( 'sys_module_application=? AND sys_module_key=? AND sys_module_area=?', 'core', 'messaging', 'front' ) )->first();
		}
		catch( \UnderflowException $ex )
		{
			$messageModule	= \IPS\Db::i()->insert( 'core_modules', array( 'sys_module_application' => 'core', 'sys_module_key' => 'messaging', 'sys_module_area' => 'front', 'sys_module_visible' => 1 , 'sys_module_version' => 0 ) );
		}

		\IPS\Db::i()->replace( 'core_permission_index', array( 'app' => 'core', 'perm_type' => 'module', 'perm_type_id' => $messageModule, 'perm_view' => implode( ',', $messenger ) ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Converting group permissions";
	}

	/* ! Serialize to JSON */
	/* ! Custom Profile Fields */
	/**
	 * Step 6
	 * Convert custom profile fields
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		/* If we have the 'extra' parameter, we're fixing fields */
		if( isset( \IPS\Request::i()->extra ) and \IPS\Request::i()->extra !== true )
		{
			$data = json_decode( base64_decode( \IPS\Request::i()->extra ), true );
            $did = 0;

            $cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

			foreach( \IPS\Db::i()->select( '*', 'core_pfields_content', NULL, NULL, array( $data['_count'], 500 ) ) as $row )
			{
				if( $cutOff !== null AND time() >= $cutOff )
				{
					$data['_count']	= $data['_count'] + $did;
					return base64_encode( json_encode( $data ) );
				}

				$did++;
				$update	= array();

				foreach( $_SESSION['pfields'] as $k => $v )
				{
					$options		= explode( '|', $v['content'] );
					$realOptions	= array();
					
					/* If this field isn't required, then we need to add a blank option */
					if ( $v['required'] === FALSE )
					{
						$realOptions[''] = '';
					}

					foreach( $options as $_index => $option )
					{
						/* If you had a checkbox, you may have entered the content as simply "1", or you may have entered it as lines of "1=Something..2=Something Else" */
						list( $fieldKey, $fieldValue )	= ( mb_strpos( $option, '=' ) !== FALSE ) ? explode( '=', $option ) : array( $option, $option );
						$realOptions[ $fieldKey ]	= array( $fieldValue, $_index );
					}

					if( $row['field_' . $k ] )
					{
						if( mb_strpos( $row['field_' . $k ], '|' ) !== FALSE )
						{
							$_options	= explode( '|', trim( $row['field_' . $k ], '|' ) );
							$_values	= array();

							foreach( $_options as $_option )
							{
								if( $_option AND isset( $realOptions[ $_option ] ) )
								{
									$key = 0;
									if ( $v['use_keys'] )
									{
										$key = 1;
									}
									$_values[]	= $realOptions[ $_option ][$key];
								}
							}

							$update['field_' . $k ]	= implode( ',', $_values );
						}
						else
						{
							$key = 0;
							if ( $v['use_keys'] )
							{
								$key = 1;
							}
							$update['field_' . $k ]	= ( isset( $realOptions[ $row['field_' . $k ] ] ) ) ? $realOptions[ $row['field_' . $k ] ][$key] : NULL;
						}
					}
				}

				/* Update as needed */
				if( \count( $update ) )
				{
					\IPS\Db::i()->update( 'core_pfields_content', $update, 'member_id=' . $row['member_id'] );
				}
			}

			/* Did we fix any?  Then return appropriately */
			if( $did )
			{
				$data['_count']	= $data['_count'] + $did;
				return base64_encode( json_encode( $data ) );
			}
			else
			{
				unset( $_SESSION['pfields'], $_SESSION['_step6Count'] );

				\IPS\Db::i()->dropColumn( 'core_pfields_data', array( 'pf_title', 'pf_desc' ) );

				return TRUE;
			}
		}

		/* Loop over all the fields */
		$extra	= array();

		if( !\IPS\Db::i()->checkForColumn( 'core_pfields_data', 'pf_multiple' ) )
		{
			\IPS\Db::i()->addColumn( 'core_pfields_data', array(
				'name'			=> 'pf_multiple',
				'type'			=> 'TINYINT',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
		}

		foreach( \IPS\Db::i()->select( '*', 'core_pfields_data' ) as $field )
		{
			/* Save the language strings */
			\IPS\Lang::saveCustom( 'core', 'core_pfield_' . $field['pf_id'], $field['pf_title'] );
			\IPS\Lang::saveCustom( 'core', 'core_pfield_' . $field['pf_id'] . '_desc', $field['pf_desc'] );

			/* Convert the field 'type' */
			$update	= array();

			switch( $field['pf_type'] )
			{
				case 'input':
					if( $field['pf_filtering'] )
					{
						$update['pf_type']	= 'Url';
					}
					else
					{
						$update['pf_type']	= 'Text';
					}
				break;

				case 'textarea':
					$update['pf_type']	= 'TextArea';
				break;

				case 'drop':
					$update['pf_type']	= 'Select';
				break;

				case 'radio':
					$update['pf_type']	= 'Radio';
				break;

				case 'cbox':
					$options				= explode( '|', $field['pf_content'] );
					$update['pf_type']		= ( \count( $options ) == 1 ) ? 'Checkbox' : 'CheckboxSet';
					$update['pf_multiple']	= ( $update['pf_type'] == 'CheckboxSet' ) ? TRUE : FALSE;
				break;

				case 'Editor':
				break;
				
				default:
					$update['pf_type']	= 'Text';
				break;
			}

			/* Fix the options */
			if( $field['pf_content'] )
			{
				$options	= explode( '|', $field['pf_content'] );
				$newOptions	= array();

				foreach( $options as $option )
				{
					list( $k, $v )	= ( mb_strpos( $option, '=' ) !== FALSE ) ? explode( '=', $option ) : array( $option, $option );
					$newOptions[]	= $v;
				}

				if( !isset( $_SESSION['pfields'] ) )
				{
					$_SESSION['pfields']	= array();
				}

				$_SESSION['pfields'][ $field['pf_id'] ]	= array( 'content' => $field['pf_content'], 'use_keys' => ( $update['pf_type'] == 'CheckboxSet' ) ? TRUE : FALSE, 'required' => (bool) $field['pf_not_null'] );
				$extra[ $field['pf_id'] ]	= $field['pf_id'];
				$update['pf_content']	= json_encode( $newOptions );
			}

			/* Fix the expected input format */
			if( $field['pf_input_format'] )
			{
				$newValue	= preg_quote( $field['pf_input_format'], '/' );
				$newValue	= str_replace( 'n', '\\d', $newValue );
				$newValue	= str_replace( 'a', '\\w', $newValue );

				$update['pf_input_format']	= "/^" . $newValue . "\$/i";
			}

			/* Update as needed */
			if( \count( $update ) )
			{
				\IPS\Db::i()->update( 'core_pfields_data', $update, 'pf_id=' . $field['pf_id'] );
			}
		}

		/* Determine if we have to loop */
		if( \count( $extra ) )
		{
			$extra['_count']	= 0;
			return base64_encode( json_encode( $extra ) );
		}
		else
		{
			unset( $_SESSION['pfields'], $_SESSION['_step6Count'] );
			\IPS\Db::i()->dropColumn( 'core_pfields_data', array( 'pf_title', 'pf_desc' ) );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		$limit = null;
		$count = 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );
			$limit	= $data['_count'];

			if( !isset( $_SESSION['_step6Count'] ) )
			{
				$_SESSION['_step6Count']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_pfields_content' )->first();
			}
		}

		return ( $limit === null ) ? "Updating custom profile field configurations" : "Updating custom profile fields (Updated so far: " . ( ( $limit > $_SESSION['_step6Count'] ) ? $_SESSION['_step6Count'] : $limit ) . ' out of ' . $_SESSION['_step6Count'] . ')';
	}

	/* ! Convert notification defaults */
	/**
	 * Step 7
	 * Convert notification defaults (previously in a cache)
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		try
		{
			$notifications	= \IPS\Db::i()->select( 'cs_value', 'cache_store', array( 'cs_key=?', 'notifications' ) )->first();
			$notifications	= unserialize( $notifications );

			foreach( $notifications as $key => $data )
			{
				/* Some older methods stored differently and do not have a 'selected' key but do have an 'icon' key */
				if( !isset( $data['selected'] ) )
				{
					continue;
				}

				$selected	= '';

				if( $data['selected'] )
				{
					$selected	= implode( ',', array_filter( $data['selected'], function( $val ){
						if( $val == 'email' OR $val == 'inline' )
						{
							return true;
						}

						return false;
					}) );
				}

				$disabled	= '';

				if( $data['disabled'] )
				{
					$disabled	= implode( ',', array_filter( $data['disabled'], function( $val ){
						if( $val == 'email' OR $val == 'inline' )
						{
							return true;
						}

						return false;
					}) );
				}

				\IPS\Db::i()->insert( 'core_notification_defaults', array( 'notification_key' => $key, 'default' => $selected, 'disabled' => $disabled, 'editable' => (int) $data['disable_override'] ), TRUE );
			}
		}
		catch( \UnderflowException $e )
		{
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Converting default notification preferences";
	}

	/* ! Fix follow table */
	/**
	 * Step 8
	 * Update the follow table
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_follow',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_follow CHANGE like_id follow_id VARCHAR(32) NOT NULL DEFAULT '', 
DROP like_lookup_id,
DROP like_lookup_area, 
CHANGE like_app follow_app VARCHAR(150) NOT NULL DEFAULT '', 
CHANGE like_area follow_area VARCHAR(200) NOT NULL DEFAULT '', 
CHANGE like_rel_id follow_rel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0', 
CHANGE like_member_id follow_member_id INT(10) UNSIGNED NOT NULL DEFAULT '0', 
CHANGE like_is_anon follow_is_anon INT(1) NOT NULL DEFAULT '0', 
CHANGE like_notify_do follow_notify_do INT(1) NOT NULL DEFAULT '0', 
CHANGE like_added follow_added INT(10) UNSIGNED NOT NULL DEFAULT '0', 
CHANGE like_notify_meta follow_notify_meta TEXT NULL DEFAULT NULL, 
CHANGE like_notify_freq follow_notify_freq VARCHAR(20) NOT NULL DEFAULT '', 
CHANGE like_notify_sent follow_notify_sent INT(10) UNSIGNED NOT NULL DEFAULT '0', 
CHANGE like_visible follow_visible TINYINT(4) NOT NULL DEFAULT '1', 
DROP INDEX find_rel_likes,
ADD INDEX find_rel_follows (follow_visible, follow_is_anon, follow_added),
DROP INDEX like_member_id,
ADD INDEX follow_member_id (follow_member_id, follow_visible, follow_added),
DROP INDEX like_lookup_area"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 9 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step8CustomTitle()
	{
		return "Upgrading follow system";
	}

	/* ! Settings to group settings */
	/**
	 * Step 9
	 * Convert settings to group settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		/* unblockable_pm_groups */
		$groups	= array_filter( explode( ',', trim( \IPS\Settings::i()->unblockable_pm_groups, ',' ) ) );

		/* Turn this off for all groups */
		\IPS\Db::i()->update( 'core_groups', "g_bitoptions=g_bitoptions & (~ 67108864)" );

		if( \count( $groups ) )
		{
			\IPS\Db::i()->update( 'core_groups', "g_bitoptions=g_bitoptions & 67108864", 'g_id IN(' . implode( ',', $groups ) . ')' );
		}

		/* override_inbox_full */
		$groups	= array_filter( explode( ',', trim( \IPS\Settings::i()->override_inbox_full, ',' ) ) );

		/* Turn this off for all groups */
		\IPS\Db::i()->update( 'core_groups', "g_bitoptions=g_bitoptions & (~ 134217728)" );

		if( \count( $groups ) )
		{
			\IPS\Db::i()->update( 'core_groups', "g_bitoptions=g_bitoptions & 134217728", 'g_id IN(' . implode( ',', $groups ) . ')' );
		}
		
		/* Copy value of cannot ignore to group settings  */
		$groups	= array_filter( explode( ',', trim( str_replace( '{blank}', '', \IPS\Settings::i()->cannot_ignore_groups ), ',' ) ) );
		
		\IPS\Db::i()->update( 'core_groups', "g_bitoptions=g_bitoptions & (~ 536870912)" );
		
		if( \count( $groups ) )
		{
			\IPS\Db::i()->update( 'core_groups', "g_bitoptions=g_bitoptions & 536870912", 'g_id IN(' . implode( ',', $groups ) . ')' );
		}

		\IPS\Db::i()->update( 'core_groups', array( 'g_attach_max' => '-1' ), "g_attach_max=0" );
		\IPS\Db::i()->update( 'core_groups', array( 'g_attach_per_post' => 0 ), "g_attach_per_post=-1" );
		\IPS\Db::i()->update( 'core_groups', array( 'g_max_bgimg_upload' => '-1' ), "g_max_bgimg_upload=0" );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step9CustomTitle()
	{
		return "Converting group settings";
	}

	/* ! Admins to mods */
	/**
	 * Step 10
	 * Convert admins and mods
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		$reportCenter	= explode( ',', trim( \IPS\Settings::i()->report_mod_group_access, ',' ) );

		/* Convert moderators */
		foreach( \IPS\Db::i()->select( '*', 'moderators', null, 'mid ASC' ) as $moderator )
		{
			$supermoderator	= false;

			if( $moderator['is_group'] )
			{
				/* Make sure the group still exists */
				try
				{
					\IPS\Db::i()->select( 'g_id', 'core_groups', array( 'g_id=?', $moderator['group_id'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					continue;
				}

				$supermoderator	= \IPS\Db::i()->select( 'COUNT(*)', 'core_groups', array( 'g_is_supmod=1 AND g_id=?', $moderator['group_id'] ) )->first();
			}
			else
			{
				/* Make sure the member still exists */
				try
				{
					\IPS\Db::i()->select( 'member_id', 'core_members', array( 'member_id=?', $moderator['member_id'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					continue;
				}
			}
			
			if ( $supermoderator )
			{
				$perms = '*';
			}
			else
			{
				$perms = array(
					'can_edit_topic'			=> $moderator['edit_topic'],
					'can_edit_post'				=> $moderator['edit_post'],
					'can_hide_topic'			=> $moderator['mod_bitoptions'] & 2,
					'can_hide_post'				=> $moderator['mod_bitoptions'] & 2,
					'can_unhide_topic'			=> $moderator['mod_bitoptions'] & 4,
					'can_unhide_post'			=> $moderator['mod_bitoptions'] & 4,
					'can_view_hidden_topic'		=> $moderator['mod_bitoptions'] & 8,
					'can_view_hidden_post'		=> $moderator['mod_bitoptions'] & 8,
					'can_pin_topic'			 	=> $moderator['pin_topic'],
					'can_unpin_topic'			=> $moderator['pin_topic'],
					'can_delete_topic'			=> $moderator['delete_topic'],
					'can_delete_post'			=> $moderator['delete_post'],
					'can_view_editlog'			=> false,
					'can_manage_announcements'	=> false,
					'can_modcp_manage_members'	=> false,
					'can_see_emails'			=> false,
					'can_flag_as_spammer'		=> $moderator['mod_bitoptions'] & 1,
					'can_edit_profiles'			=> false,
					'can_view_reports'			=> $moderator['is_group'] ? \in_array( $moderator['group_id'], $reportCenter ) : false,
					'mod_see_warn'				=> false,
					'mod_can_warn'				=> (bool) $moderator['allow_warn'],
					'mod_revoke_warn'			=> false,
					'warning_custom_noaction'	=> (bool) \IPS\Settings::i()->warning_custom_noaction,
					'warnings_enable_other'		=> (bool) \IPS\Settings::i()->warnings_enable_other,
					'warn_mod_day'				=> \IPS\Settings::i()->warn_mod_day,
				);
				
				$perms['forums'] = ( $moderator['forum_id'] === '*' ) ? 0 : array_filter( explode( ',', $moderator['forum_id'] ) );
				
				$perms = json_encode( $perms );
			}
			
			$insert	= array(
				'type'			=> $moderator['is_group'] ? 'g' : 'm',
				'id'			=> $moderator['is_group'] ? $moderator['group_id'] : $moderator['member_id'],
				'updated'		=> time(),
				'perms'			=> $perms
			);
			
			try
			{
				$existing = \IPS\Db::i()->select( '*', 'core_moderators', array( 'id=? and type=?', $insert['id'], $insert['type'] ) )->first();
				
				if ( $existing['perms'] !== '*' )
				{
					$existingPerms = json_decode( $existing['perms'], true );
					
					/* not a * */
					if ( $insert['perms'] !== '*' )
					{
						$newPerms = json_decode( $insert['perms'], true );
						
						foreach( $newPerms as $key => $value )
						{
							if ( \is_array( $value ) )
							{
								if ( isset( $existingPerms[ $key ] ) and \is_array( $existingPerms[ $key ] ) )
								{
									$newPerms[ $key ] = array_merge_recursive( $existingPerms[ $key ], $value );
								}
							}
							else
							{
								$newPerms[ $key ] = ( isset( $existingPerms[ $key ] ) and \intval( $existingPerms[ $key ] ) > \intval($value) ) ? $existingPerms[ $key ] : $value;
							}
						}
						
						$insert['perms'] = json_encode( $newPerms );
					}
					
					\IPS\Db::i()->replace( 'core_moderators', $insert );
				}
				else
				{
					/* Nothing to do here, previous row is a * so leave it be */
				}
			}
			catch( \UnderflowException $e )
			{
				/* Row not found, insert */
				\IPS\Db::i()->insert( 'core_moderators', $insert );
			}
		}

		/* Make sure admins have permission rows and add moderator records as needed */
		\IPS\Db::i()->update( 'core_admin_permission_rows', array( 'row_perm_cache' => '*' ) );

		foreach( \IPS\Db::i()->select( '*', 'core_groups', 'g_access_cp=1 or g_is_supmod=1', 'g_id ASC' ) as $adminGroup )
		{
			if( $adminGroup['g_access_cp'] AND \IPS\Db::i()->select( 'COUNT(*)', 'core_admin_permission_rows', array( 'row_id=? and row_id_type=?', $adminGroup['g_id'], 'group' ) )->first() == 0 )
			{
				\IPS\Db::i()->insert( 'core_admin_permission_rows', array( 'row_id' => $adminGroup['g_id'], 'row_id_type' => 'group', 'row_perm_cache' => '*', 'row_updated' => time() ) );
			}

			if( $adminGroup['g_is_supmod'] AND \IPS\Db::i()->select( 'COUNT(*)', 'core_moderators', array( 'id=? and type=?', $adminGroup['g_id'], 'g' ) )->first() == 0 )
			{
				\IPS\Db::i()->insert( 'core_moderators', array( 'id' => $adminGroup['g_id'], 'type' => 'g', 'perms' => '*', 'updated' => time() ) );
			}
		}

		/* Insert leaders page records */
		$groupId	= \IPS\Db::i()->insert( 'core_leaders_groups', array( 'group_template' => 'layout_blocks', 'group_position' => 1 ) );
		\IPS\Lang::saveCustom( 'core', 'core_staffgroups_' . $groupId, "Moderators" );

		foreach( \IPS\Db::i()->select( '*', 'core_moderators' ) as $moderator )
		{
			\IPS\Db::i()->insert( 'core_leaders', array( 'leader_type' => $moderator['type'], 'leader_type_id' => $moderator['id'], 'leader_group_id' => $groupId ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step10CustomTitle()
	{
		return "Upgrading moderators";
	}

	/* ! Report Center */
	/**
	 * Step 11
	 * Report Center
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step11()
	{
		$perCycle	= 1000;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		$classes	= array();

		foreach( \IPS\Db::i()->select( '*', 'rc_classes' ) as $rcClass )
		{
			$classes[ $rcClass['com_id'] ]	= $rcClass['my_class'];
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'rc_reports_index', null, 'id ASC', array( $limit, $perCycle ) ) as $report )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;
			
			/* Bugs in older versions may have a report with a 'status' outside of 1, 2, or 3, which can fail when using MySQL Strict with a Data truncated error. We set it to 3 here, so reports are not suddenly shown as "new" */
			if ( !\in_array( $report['status'], array( 1, 2, 3 ) ) )
			{
				$report['status'] = 3;
			}

			/* Convert to new format */
			$insert	= array(
				'id'				=> $report['id'],
				'class'				=> null,
				'content_id'		=> 0,
				'perm_id'			=> 0,
				'status'			=> $report['status'],
				'num_reports'		=> $report['num_reports'],
				'num_comments'		=> $report['num_comments'],
				'first_report_by'	=> 0,
				'first_report_date'	=> $report['date_created'],
				'last_updated'		=> $report['date_updated'],
				'author'			=> 0,
			);

			/* Now we need to figure out the class and database table - can only handle first-party applications. We need the table as it may have been renamed */
			$table	= NULL;
			$column	= $report['exdat3'] ? 'exdat2' : 'exdat1';

			switch( $classes[ $report['rc_class'] ] )
			{
				case 'post':
					/* Posts were always reported, not topics. 3=pid, 2=tid, 1=forum id */
					$insert['class']	= "IPS\\forums\\Topic\\Post";
					$table				= "posts";
					$itemTable			= "topics";
					$containerTable		= "forums";
					$column				= 'exdat3';
					break;

				case 'blog':
					/* If 3 is present, a comment was reported. 2=entry id and 1=blog id */
					if( $report['exdat3'] )
					{
						$insert['class']	= "IPS\\blog\\Entry\\Comment";
						$table				= "blog_comments";
						$itemTable			= "blog_entries";
						$column				= 'exdat3';
					}
					else
					{
						$insert['class']	= "IPS\\blog\\Entry";
						$table				= "blog_entries";
						$column				= 'exdat2';
					}

					$containerTable		= "blog_blogs";
					break;

				case 'gallery':
					/* If 2 is present, a comment was reported. 1=image id */
					if( $report['exdat2'] )
					{
						$insert['class']	= "IPS\\gallery\\Image\\Comment";
						$table				= "gallery_comments";
						$itemTable			= "gallery_images";
						$column				= 'exdat2';
					}
					else
					{
						$insert['class']	= "IPS\\gallery\\Image";
						$table				= "gallery_images";
						$column				= 'exdat1';
					}

					$containerTable		= "gallery_categories";
					break;

				case 'downloads':
					/* If 2 is present, a comment was reported. 1=file id, 3=st id (page offset) */
					if( $report['exdat2'] )
					{
						$insert['class']	= "IPS\\downloads\\File\\Comment";
						$table				= "downloads_comments";
						$itemTable			= "downloads_files";
						$column				= 'exdat2';
					}
					else
					{
						$insert['class']	= "IPS\\downloads\\File";
						$table				= "downloads_files";
						$column				= 'exdat1';
					}

					$containerTable		= "downloads_categories";
					break;

				case 'messages':
					/* Messages were always reported, not topics. 1=topic id, 2=message id, 3=st id (page offset) */
					$insert['class']	= "IPS\\core\\Messenger\\Message";
					$table				= "message_posts";
					$column				= 'exdat2';

					/* Message containers don't use \IPS\Content\Permissions, PermID should always be NULL */
					$insert['perm_id'] = NULL;
					
					break;

				case 'calendar':
					/* If 2 is present, a comment was reported. 1=event id, 3=st id (page offset) */
					if( $report['exdat2'] )
					{
						$insert['class']	= "IPS\\calendar\\Event\\Comment";
						$table				= "cal_event_comments";
						$itemTable			= "cal_events";
						$column				= 'exdat2';
					}
					else
					{
						$insert['class']	= "IPS\\calendar\\Event";
						$table				= "cal_events";
						$column				= 'exdat1';
					}

					$containerTable		= "cal_calendars";
					break;

				case 'ccs':
					/* If 3 is present, a comment was reported. 1=database id, 2=record id */
					if( $report['exdat3'] )
					{
						$insert['class']	= "IPS\\cms\\Records\\Comment" . $report['exdat1'];
						$column				= 'exdat3';
						$itemTable			= "ccs_custom_database_" . $report['exdat1'];
						$table				= 'ccs_database_comments';
					}
					else
					{
						$insert['class']	= "IPS\\cms\\Records" . $report['exdat1'];
						$table				= "ccs_custom_database_" . $report['exdat1'];
						$column				= 'exdat2';
					}

					$containerTable		= "ccs_databases";
					break;

				case 'nexus':
					$insert['class']	= "IPS\\nexus\\Package\\Review";
					$column				= 'exdat1';
					$table				= "nexus_reviews";
					break;
			}

			$insert['content_id'] = $report[ $column ];

			if( $insert['class'] === NULL )
			{
				\IPS\Db::i()->delete( 'core_rc_reports', array( 'rid=?', $report['id'] ) );
				\IPS\Db::i()->delete( 'core_rc_comments', array( 'rid=?', $report['id'] ) );
				continue;
			}

			/* Figure out who submitted first report */
			try
			{
				$insert['first_report_by']	= \IPS\Db::i()->select( 'report_by', 'core_rc_reports', array( 'rid=?', $report['id'] ), 'id ASC', array( 0, 1 ) )->first();
			}
			catch( \UnderflowException $e ){}

			$className	= $insert['class'];
			if( class_exists( $className ) )
			{
				try
				{
					/* Some tables haven't been renamed yet so build the data manually */
					if( $table )
					{
						$item		= NULL;
						$row		= \IPS\DB::i()->select( "*", $table, array( "{$className::$databasePrefix}{$className::$databaseColumnId} = ?", $insert['content_id'] ) )->first();
						$content	= $className::constructFromData( $row );

						if( isset( $className::$itemClass ) AND $className::$itemClass AND $itemTable )
						{
							$itemClass	= $className::$itemClass;
							$itemRow	= \IPS\DB::i()->select("*", $itemTable, array( "{$itemClass::$databasePrefix}{$itemClass::$databaseColumnId} = ?", $content->mapped('item')))->first();
							$item		= $itemClass::constructFromData($itemRow);
						}
						elseif( isset( $className::$containerNodeClass ) AND $className::$containerNodeClass )
						{
							$item	= $content;
						}

						if( $containerTable AND $item !== NULL )
						{
							$containerClass	= $item::$containerNodeClass;

							if ( \in_array( 'IPS\Node\Permissions', class_implements( $containerClass ) ) )
							{
								$containerRow	= \IPS\DB::i()->select( "*", $containerTable, array( "{$containerClass::$databasePrefix}{$containerClass::$databaseColumnId} = ?", $item->mapped('container') ) )->first();
								$container		= $containerClass::constructFromData( $containerRow );

								$permissions = $container->permissions();
								$insert['perm_id'] = $permissions['perm_id'];
							}
						}
					}
					else
					{
						$content = $className::load( $insert['content_id'] );
						$insert['perm_id'] = $content->permId();
					}

					$insert['author']		= $content->mapped('author');
				}
				catch( \Exception $e ){}

				/* Insert new report */
				\IPS\Db::i()->replace( 'core_rc_index', $insert );
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			/* We are going to skip dropping the table so that third party addons can convert their data */
			//\IPS\Db::i()->dropTable( 'rc_reports_index' );

			unset( $_SESSION['_step11Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step11CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step11Count'] ) )
		{
			$_SESSION['_step11Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'rc_reports_index' )->first();
		}

		return "Upgrading reported content (Updated so far: " . ( ( $limit > $_SESSION['_step11Count'] ) ? $_SESSION['_step11Count'] : $limit ) . ' out of ' . $_SESSION['_step11Count'] . ')';
	}

	/* ! Warnings */
	/**
	 * Step 12
	 * Warnings
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step12()
	{
		$perCycle	= 1000;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_members_warn_logs', null, 'wl_id ASC', array( $limit, $perCycle ) ) as $log )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* Convert to dateinterval specs */
			$update	= array(
				'wl_mq'				=> ( $log['wl_mq_unit'] AND $log['wl_mq'] AND $log['wl_mq'] != -1 ) ? 'P' . ( ( mb_strtoupper( $log['wl_mq_unit'] ) == 'H' ) ? 'T' : '' ) . $log['wl_mq'] . mb_strtoupper( $log['wl_mq_unit'] ) : null,
				'wl_rpa'			=> ( $log['wl_rpa_unit'] AND $log['wl_rpa'] AND $log['wl_rpa'] != -1 ) ? 'P' . ( ( mb_strtoupper( $log['wl_rpa_unit'] ) == 'H' ) ? 'T' : '' ) . $log['wl_rpa'] . mb_strtoupper( $log['wl_rpa_unit'] ) : null,
				'wl_suspend'		=> ( $log['wl_suspend_unit'] AND $log['wl_suspend'] AND $log['wl_suspend'] != -1 ) ? 'P' . ( ( mb_strtoupper( $log['wl_suspend_unit'] ) == 'H' ) ? 'T' : '' ) . $log['wl_suspend'] . mb_strtoupper( $log['wl_suspend_unit'] ) : null,
				'wl_content_module'	=> $log['wl_content_app'],
			);

			/* Convert messenger */
			if( $log['wl_content_app'] == 'members' )
			{
				$update['wl_content_app']		= 'core';
				$update['wl_content_module']	= 'messenger';
			}

			\IPS\Db::i()->update( 'core_members_warn_logs', $update, "wl_id=" . $log['wl_id'] );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			\IPS\Db::i()->dropColumn( 'core_members_warn_logs', array( 'wl_mq_unit', 'wl_rpa_unit', 'wl_suspend_unit' ) );

			unset( $_SESSION['_step12Count'] );

			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step12CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step12Count'] ) )
		{
			$_SESSION['_step12Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members_warn_logs' )->first();
		}

		return "Upgrading member warnings (Updated so far: " . ( ( $limit > $_SESSION['_step12Count'] ) ? $_SESSION['_step12Count'] : $limit ) . ' out of ' . $_SESSION['_step12Count'] . ')';
	}

	/* ! Attachments */
	/**
	 * Step 13
	 * Attachments
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step13()
	{
		$limit		= 0;
		$did		= 0;
		$perCycle	= 500;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_attachments', null, 'attach_id ASC', array( $limit, $perCycle ) ) as $attach )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* We're just going to do the best we can to convert the attach_rel_module to the location_key - it is near impossible
				to account for third party apps here, but they can run their own updates later */
			$id1 = $attach['attach_parent_id'] ? $attach['attach_parent_id'] : $attach['attach_rel_id'];
			$id2 = $attach['attach_parent_id'] ? $attach['attach_rel_id'] : NULL;
			$id3 = NULL;
			$locationKey	= $attach['attach_rel_module'];

			switch( $attach['attach_rel_module'] )
			{
				case 'post':
					$locationKey	= 'forums_Forums';

					/* If the parent isn't set because this is legacy data, we can't just let id1 be the post id and id2 be null - that won't work */
					if( !$attach['attach_parent_id'] )
					{
						try
						{
							/* This is still 'posts' rather than 'forums_posts' at this point */
							$topic = \IPS\Db::i()->select( 'topic_id', 'posts', array( 'pid=?', $attach['attach_rel_id'] ) )->first();

							$id1 = $topic;
							$id2 = $attach['attach_rel_id'];
						}
						catch( \UnderflowException $e ){}
					}
				break;

				case 'msg':
					$locationKey	= 'core_Messaging';

					/* We need to fetch the topic ID because 3.x did not store attach_parent_id in this case, but we need it */
					try
					{
						$message = \IPS\Db::i()->select( 'msg_topic_id', 'core_message_posts', array( 'msg_id=?', $attach['attach_rel_id'] ) )->first();
						$id1	= $message;
						$id2	= $attach['attach_rel_id'];
					}
					catch( \UnderflowException $e ){}
				break;

				case 'ccs':
					$locationKey	= 'cms_Records';

					try
					{
						$map = \IPS\Db::i()->select( '*', 'ccs_attachments_map', array( 'map_attach_id=?', $attach['attach_id'] ) )->first();
						$id1 = $map['map_record_id'];
						$id2 = $map['map_field_id'];
						$id3 = $map['map_database_id'];
					}
					catch( \UnderflowException $e ){}
				break;

				case 'blogentry':
					$locationKey	= 'blog_Entries';
				break;

				case 'event':
					$locationKey	= 'calendar_Events';
				break;

				case 'custom_pages':
					$locationKey	= 'nexus_Admin';
					$id3			= 'pkg-pg';
				break;

				case 'packages':
					$locationKey	= 'nexus_Store';
				break;

				case 'support':
					$locationKey	= 'nexus_Support';
					try
					{
						$id2 = $id1;
						$id1 = \IPS\Db::i()->select( 'reply_request', 'nexus_support_replies', array( 'reply_id=?', $id2 ) )->first();
					}
					catch ( \UnderflowException $e ) { }
				break;
			}

			$map	= array(
				'attachment_id'		=> $attach['attach_id'],
				'location_key'		=> $locationKey ?: '',
				'id1'				=> $id1,
				'id2'				=> $id2,
				'id3'				=> $id3,
				'temp'				=> NULL,
			);

			\IPS\Db::i()->replace( 'core_attachments_map', $map );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			$columns	= array();

			if ( \IPS\Db::i()->checkForColumn( 'core_attachments', 'attach_rel_module' ) )
			{
				$columns[]	= 'attach_rel_module';
			}
			
			if ( \IPS\Db::i()->checkForColumn( 'core_attachments', 'attach_parent_id' ) )
			{
				$columns[]	= 'attach_parent_id';
			}
			
			if ( \IPS\Db::i()->checkForColumn( 'core_attachments', 'attach_rel_id' ) )
			{
				$columns[]	= 'attach_rel_id';
			}

			if( \count( $columns ) )
			{
				\IPS\Db::i()->dropColumn( 'core_attachments', $columns );
			}
			
			unset( $_SESSION['_step13Count'] );

			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step13CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step13Count'] ) )
		{
			$_SESSION['_step13Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments' )->first();
		}
		
		return "Upgrading attachments (Upgraded so far: " . ( ( $limit > $_SESSION['_step13Count'] ) ? $_SESSION['_step13Count'] : $limit ) . ' out of ' . $_SESSION['_step13Count'] . ')';
	}

	/* ! Polls */
	/**
	 * Step 14
	 * Polls
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step14()
	{
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			\IPS\Db::i()->dropTable( 'core_voters', true );
			\IPS\Db::i()->renameTable( 'voters', 'core_voters' );
		}
		
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_voters',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_voters ADD COLUMN poll MEDIUMINT UNSIGNED NOT NULL COMMENT 'The poll ID', 
					ADD INDEX poll (poll), 
					DROP INDEX tid, 
					ADD INDEX `member`(member_id, poll)"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 15 ) ) );
			
			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr, 'extra' => 0 ) ) ) );
		}
	
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step14CustomTitle()
	{
		return "Updating poll votes table";
	}

	/**
	 * Step 15
	 * Polls
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step15()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 1000;
		$limit		= 0;
		$step		= 'polls';

		if( isset( \IPS\Request::i()->extra ) )
		{
			$data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );

			$limit	= $data['limit'];
			$step	= $data['step'];
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		switch( $step )
		{
			case 'polls':
				foreach( \IPS\Db::i()->select( '*', 'core_polls', NULL, 'pid ASC', array( $limit, $perCycle ) ) as $poll )
				{
					if( $cutOff !== null AND time() >= $cutOff )
					{
						return base64_encode( json_encode( array( 'limit' => ( $limit + $did ), 'step' => 'polls' ) ) );
					}

					$did++;

					/* Sometimes data from 3.4.x isn't always as well formed as we'd like, especially when non-latin chars are involved */
					$data	= unserialize( $poll['choices'] );

					if( !\is_array( $data ) )
					{
						$poll['choices']	= str_replace( '\\"', '"', str_replace( '\\\'', "'", $poll['choices'] ) );
						$data	= unserialize( $poll['choices'] );
					}

					$_decoded	= FALSE;

					if( !\is_array( $data ) )
					{
						$data	= unserialize( utf8_decode( $poll['choices'] ) );

						if( \is_array( $data ) )
						{
							$_decoded	= TRUE;
						}
					}

					if( \is_array( $data ) AND $_decoded )
					{
						$newPoll = array();
						
						foreach( $data as $_key => $_data )
						{
							if( isset( $_data['question'] ) )
							{
								$data[ $_key ]['question']	= utf8_encode( $_data['question'] );

								if( \is_array( $_data['choice'] ) )
								{
									foreach( $_data['choice'] as $_idx => $_choice )
									{
										$data[ $_key ]['choice'][ $_idx ]	= utf8_encode( $_choice );
									}
								}
							}
							else
							{
								$newPoll[0]['question']	= $poll['poll_question'];

								$newPoll[0]['choice'][]	= utf8_encode( $_data[1] );
							}
						}
					}

					$result = @json_encode( $data );

					/* Sometimes the array unserializes fine but there are still "bad" characters in there */
					if( $result === FALSE )
					{
						$data	= unserialize( $poll['choices'] );

						if( !\is_array( $data ) )
						{
							$poll['choices']	= str_replace( '\\"', '"', str_replace( '\\\'', "'", $poll['choices'] ) );
							$data	= unserialize( $poll['choices'] );
						}

						if( !\is_array( $data ) )
						{
							$data	= unserialize( utf8_decode( $poll['choices'] ) );
						}

						if( \is_array( $data ) )
						{
							foreach( $data as $_key => $_data )
							{
								$data[ $_key ]['question']	= utf8_encode( $_data['question'] );

								if( \is_array( $_data['choice'] ) )
								{
									foreach( $_data['choice'] as $_idx => $_choice )
									{
										$data[ $_key ]['choice'][ $_idx ] = utf8_encode( $_choice );
									}
								}
								else
								{
									/* No choices, no poll */
									\IPS\Db::i()->delete( 'core_polls', array( 'pid=?', $poll['pid'] ) );
									continue;
								}
							}
						}

						$result = @json_encode( $data );
					}

					\IPS\Db::i()->update( 'core_polls', array( 'choices' => $result ), 'pid=' . $poll['pid'] );
				}

				if( $did AND $did == $perCycle )
				{
					return base64_encode( json_encode( array( 'limit' => ( $limit + $did ), 'step' => 'polls' ) ) );
				}
				else
				{
					return base64_encode( json_encode( array( 'limit' => 0, 'step' => 'voters' ) ) );
				}
			break;

			case 'voters':
				foreach( \IPS\Db::i()->select( '*', 'core_voters', NULL, 'vid ASC', array( $limit, $perCycle ) ) as $vote )
				{
					if( $cutOff !== null AND time() >= $cutOff )
					{
						return base64_encode( json_encode( array( 'limit' => ( $limit + $did ), 'step' => 'voters' ) ) );
					}

					$did++;

					/* Get the poll ID */
					try
					{
						$poll	= \IPS\Db::i()->select( 'pid', 'core_polls', array( 'tid=?', $vote['tid'] ) )->first();

						\IPS\Db::i()->update( 'core_voters', array( 'member_choices' => json_encode( unserialize( $vote['member_choices'] ) ), 'poll' => $poll ), 'vid=' . $vote['vid'] );
					}
					catch( \UnderflowException $ex ) { }
				}

				if( $did AND $did == $perCycle )
				{
					return base64_encode( json_encode( array( 'limit' => ( $limit + $did ), 'step' => 'voters' ) ) );
				}
				else
				{
					return base64_encode( json_encode( array( 'limit' => 0, 'step' => 'topics' ) ) );
				}
			break;

			case 'topics':
				$table	= \IPS\Db::i()->checkForTable( 'forums_topics' ) ? 'forums_topics' : 'topics';

				foreach( \IPS\Db::i()->select( '*', 'core_polls', NULL, 'pid ASC', array( $limit, $perCycle ) ) as $poll )
				{
					if( $cutOff !== null AND time() >= $cutOff )
					{
						return base64_encode( json_encode( array( 'limit' => ( $limit + $did ), 'step' => 'topics' ) ) );
					}

					\IPS\Db::i()->update( $table, array( 'poll_state' => $poll['pid'] ), 'tid=' . $poll['tid'] );
					$did++;
				}

				if( $did AND $did == $perCycle )
				{
					return base64_encode( json_encode( array( 'limit' => ( $limit + $did ), 'step' => 'topics' ) ) );
				}
				else
				{
					unset( $_SESSION['_step15PollsCount'], $_SESSION['_step15VotersCount'], $_SESSION['_step15TopicsCount'] );
					return TRUE;
				}
			break;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step15CustomTitle()
	{
		if( isset( \IPS\Request::i()->extra ) )
		{
			$data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );

			$limit	= $data['limit'];
			$step	= $data['step'];

			switch( $step )
			{
				case 'polls':
					if( !isset( $_SESSION['_step15PollsCount'] ) )
					{
						$_SESSION['_step15PollsCount'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_polls' )->first();
					}

					$count = $_SESSION['_step15PollsCount'];
				break;

				case 'voters':
					if( !isset( $_SESSION['_step15VotersCount'] ) )
					{
						$_SESSION['_step15VotersCount'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_voters' )->first();
					}

					$count = $_SESSION['_step15VotersCount'];
				break;

				case 'topics':
					if( !isset( $_SESSION['_step15TopicsCount'] ) )
					{
						$_SESSION['_step15TopicsCount'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_polls' )->first();
					}

					$count = $_SESSION['_step15TopicsCount'];
				break;
			}
			
			return "Upgrading topic polls (working on " . $step . ", currently processed " . ( ( $limit > $count ) ? $count : $limit ) . ' out of ' . $count . ')';
		}
		else
		{
			return "Preparing to upgrade topic polls";
		}
	}

	/* ! Moderator Logs */
	/**
	 * Step 16
	 * Moderator Logs
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step16()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 500;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}
		
		if ( ! \IPS\Db::i()->checkForTable( 'core_moderator_logs' ) )
		{
			$schema	= json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/core/data/schema.json' ), TRUE );

			\IPS\Db::i()->createTable( $schema['core_moderator_logs'] );
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		/* Now loop over moderator logs to convert */
		foreach( \IPS\Db::i()->select( '*', 'moderator_logs', NULL, 'id asc', array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$qsBits	= explode( '&', str_replace( '&amp;', '&', $row['query_string'] ) );
			$query	= array();

			foreach( $qsBits as $bit )
			{
				$_bit	= explode( '=', $bit );
				$query[ $_bit[0] ]	= $_bit[1];
			}

			$application	= null;
			$module			= '';
			$controller		= '';
			$class			= NULL;
			$item_id		= NULL;
			
			if( isset( $query['app'] ) )
			{
				if( !isset( $query['module'] ) OR $query['module'] != 'task' )
				{
					$application = $query['app'];
				}
				else if ( $query['module'] )
				{
					$module = $query['module'];
				}
			}
			
			if ( $application === null )
			{
				if ( isset( $row['forum_id'] ) and $row['forum_id'] )
				{
					$application = 'forums';
				}
				else
				{
					$application = 'core';
				}
			}
			
			if ( $application === 'forums' AND isset( $row['topic_id'] ) AND $row['topic_id'] )
			{
				$class		= 'IPS\forums\Topic';
				$item_id	= $row['topic_id'];
			}

			/* In the rare circumstance the topic title contains malformed UTF-8, we forcefully re-encode to salvage what we can and prevent json_encode from failing (#936969) */
			@json_encode( array( 'topic' => false, $row['http_referer'] => false, $row['topic_title'] => false ) );
			if ( json_last_error() === \JSON_ERROR_UTF8 )
			{
				$row['topic_title'] = mb_convert_encoding( $row['topic_title'], 'UTF-8', 'UTF-8' );
			}

			\IPS\Db::i()->insert( 'core_moderator_logs', array( 
				'member_id'			=> $row['member_id'],
				'ctime'				=> $row['ctime'],
				'note'				=> ( $application == 'forums' ) ? json_encode( array( 'topic' => false, $row['http_referer'] => false, $row['topic_title'] => false ) ) : null,
				'ip_address'		=> $row['ip_address'],
				'appcomponent'		=> $application,
				'module'			=> $module,
				'controller'		=> $controller,
				'do'				=> '',
				'lang_key'			=> $row['action'] ?: "na",	// Not a 1-to-1 comparison, but will suffice for upgraded data
				'class'				=> $class,
				'item_id'			=> $item_id,
			)	);
		}

		/* And then continue */
		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			\IPS\Db::i()->dropTable( 'moderator_logs' );

			unset( $_SESSION['_step16Count'] );
			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step16CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('moderator_logs') )
		{
			if( !isset( $_SESSION['_step16Count'] ) )
			{
				$_SESSION['_step16Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'moderator_logs' )->first();
			}

			return "Upgrading moderator logs (Upgraded so far: " . ( ( $limit > $_SESSION['_step16Count'] ) ? $_SESSION['_step16Count'] : $limit ) . ' out of ' . $_SESSION['_step16Count'] . ')';
		}
		else
		{
			return "Upgraded moderator logs";
		}
	}

	/* ! Convert tags */
	/**
	 * Step 17
	 * Convert tags
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step17()
	{
		if( !isset( \IPS\Request::i()->extra ) AND \IPS\Db::i()->checkForTable( 'tags_index' ) )
		{
			\IPS\Db::i()->dropTable( 'tags_index' );
		}
		
		$did		= 0;
		$perCycle	= 500;
		$limit		= 0;
		
		if ( isset( \IPS\Request::i()->extra ) )
		{
			$limit = (int) \IPS\Request::i()->extra;
		}
		
		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();
		
		/* We need to loop through all tags and fix up tag_aai_lookup as the area has changed. Normally we could just use MySQL MD5, but we need to update core_tags_perms too... */
		foreach( \IPS\Db::i()->select( '*', 'core_tags', NULL, 'tag_id ASC', array( $limit, $perCycle ) ) AS $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			
			$did++;
			if ( $row['tag_meta_app'] === 'ccs' )
			{
				$row['tag_meta_app'] = 'cms';
				
				if ( mb_substr( $row['tag_meta_area'], 0, 8 ) === 'records-' )
				{
					$row['tag_meta_area'] = str_replace( 'records-', 'records', $row['tag_meta_area'] );
				}
			}
			else
			{
				switch( $row['tag_meta_area'] )
				{
					case 'topics':
						$row['tag_meta_area']	= 'forums';
					break;
					
					case 'files':
						$row['tag_meta_area']	= 'downloads';
					break;
					
					case 'images':
						$row['tag_meta_area']	= 'gallery';
					break;
					
					case 'entries':
						$row['tag_meta_area']	= 'blogs';
					break;
				}
			}
			
			$oldAaiLookup = $row['tag_aai_lookup'];
			$row['tag_aai_lookup'] = md5( $row['tag_meta_app'] . ';' . $row['tag_meta_area'] . ';' . $row['tag_meta_id'] );
			
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_aai_lookup' => $row['tag_aai_lookup'] ), array( 'tag_perm_aai_lookup=?', $oldAaiLookup ) );
			\IPS\Db::i()->update( 'core_tags', $row, array( 'tag_id=?', $row['tag_id'] ) );
			\IPS\Db::i()->update( 'core_tags_cache', array( 'tag_cache_key' => $row['tag_aai_lookup'] ), array( 'tag_cache_key=?', $oldAaiLookup ) );
		}
		
		if ( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			/* Unset the count, and set a flag in the session so we do not do this again in Beta 6. */
			unset( $_SESSION['_step17Count'] );
			$_SESSION['_tagsUpgraded'] = TRUE;
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step17CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step17Count'] ) )
		{
			$_SESSION['_step17Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_tags' )->first();
		}

		return "Upgrading tags (Upgraded so far: " . ( ( $limit > $_SESSION['_step17Count'] ) ? $_SESSION['_step17Count'] : $limit ) . ' out of ' . $_SESSION['_step17Count'] . ')';
	}

	/* ! File paths to URLs */
	/**
	 * Step 18
	 * Update file paths to URLs
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step18()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 200;
		$limit		= 0;
		$table		= 'core_attachments';
		
		$url        = \IPS\Request::i()->url();
		$settings   = array( 'upload_dir' => '', 'upload_url' => '' );

		foreach( \IPS\Db::i()->select( '*', 'core_sys_conf_settings', array( "conf_key IN('upload_dir', 'upload_url', 'gallery_images_url', 'gallery_images_path', 'idm_screenshot_url', 
			'idm_filestorage', 'idm_localfilepath', 'idm_localsspath', 'idm_remoteport', 'idm_remotessurl', 'idm_remotefileurl', 'idm_filestorage', 'idm_remoteurl', 'idm_remoteuser', 
			'idm_remotepass', 'idm_remotesspath', 'idm_remotefilepath', 'blog_upload_dir', 'blog_upload_url' )" ) ) as $row )
		{
			if ( $row['conf_value'] )
			{
				if ( \in_array( $row['conf_key'], array( 'upload_dir', 'gallery_images_path', 'idm_localsspath', 'idm_localfilepath', 'blog_upload_dir' ) ) and !@is_dir( str_replace( array( '{root_path}', '{root}' ), \IPS\ROOT_PATH, $row['conf_value'] ) ) )
				{
					continue;
				}
				
				if ( \in_array( $row['conf_key'], array( 'upload_url', 'gallery_images_url', 'idm_screenshot_url', 'blog_upload_url' ) ) )
				{
					$row['conf_value'] = str_replace( \IPS\Http\Url::baseUrl(), '', $row['conf_value'] );
				}
				
				$settings[ $row['conf_key'] ] = $row['conf_value'];
			}
			else if( $row['conf_default'] )
			{
				$settings[ $row['conf_key'] ] = $row['conf_default'];
			}
		}

		/* 3.x auto-corrected this, so it is possible for no value to be set */
		if( !isset( $settings['upload_url'] ) OR !$settings['upload_url'] )
		{
			$settings['upload_url']	= \IPS\Settings::i()->base_url . 'uploads';
		}
		
		/* if the old dir doesn't exist, set this now so at least themes work */
		if( ! is_dir( $settings['upload_dir'] ) )
		{
			/* Protocol relative URLs in 3.x confuse this upgrade step */
			if ( mb_substr( \IPS\Settings::i()->base_url, 0, 2 ) === '//' )
			{
				\IPS\Settings::i()->base_url = 'http:' . \IPS\Settings::i()->base_url;
			}
			
			$settings['upload_dir']	= \IPS\ROOT_PATH . '/uploads';
			$settings['upload_url']	= \IPS\Settings::i()->base_url . 'uploads';
		}

		$tables		= array(
			'core_attachments'			=> array( 'columns' => array( 'attach_location', 'attach_thumb_location' ), 'id' => 'attach_id' ),
			'core_emoticons'			=> array( 'columns' => array( 'image' ), 'id' => 'id' ),
			'core_groups'				=> array( 'columns' => array( 'g_icon' ), 'id' => 'g_id' ),
			'core_member_ranks'			=> array( 'columns' => array( 'pips' ), 'id' => 'id' ),
			'core_reputation_levels'	=> array( 'columns' => array( 'level_image' ), 'id' => 'level_id' ),
			'downloads_files_records'	=> array( 'columns' => array( 'record_location', 'record_thumb', 'record_no_watermark' ), 'id' => 'record_id' ),
		);

		if( isset( \IPS\Request::i()->extra ) )
		{
			$_data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );
			$limit	= $_data['limit'];
			$table	= $_data['table'];
		}
		else
		{
			/* Try and account for windows and legacy data */
			foreach( array( 'upload_dir', 'gallery_images_path', 'blog_upload_dir' ) as $field )
			{
				if ( isset( $settings[ $field ] ) and mb_strstr( $settings[ $field ], '/' ) and mb_strstr( \IPS\ROOT_PATH, '\\' ) )
				{
					$settings[ $field ] = str_replace( '/', '\\', $settings[ $field ] );
				}
			}
			
			/* First we need to make sure a file storage method has been inserted/set */
			if( \IPS\Db::i()->select( 'COUNT(*)', 'core_file_storage' )->first() == 0 )
			{
				$uploadUrl		= str_replace( array( 'http://' . str_replace( array( 'http://', 'https://' ), '', rtrim( \IPS\Settings::i()->base_url, '/' ) . '/' ), 'https://' . str_replace( array( 'http://', 'https://' ), '', rtrim( \IPS\Settings::i()->base_url, '/' ) . '/' ) ), '', $settings['upload_url'] );

				/* If our URL still starts with http:// or https:// it must not match the board url, so we need to store this as a 'custom_url' instead */
				if( mb_stripos( $uploadUrl, 'http://' ) === 0 OR mb_stripos( $uploadUrl, 'https://' ) === 0 )
				{
					$attachments	= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'FileSystem', 'configuration' => json_encode( array( 'dir' => str_replace( \IPS\ROOT_PATH, '{root}', $settings['upload_dir'] ), 'url' => 'uploads', 'toggle' => 1, 'custom_url' => $uploadUrl ) ) ) );
				}
				else
				{
					$attachments	= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'FileSystem', 'configuration' => json_encode( array( 'dir' => str_replace( \IPS\ROOT_PATH, '{root}', $settings['upload_dir'] ), 'url' => $uploadUrl ) ) ) );
				}

				/* Gallery */
				if( isset( $settings['gallery_images_path'] ) AND $settings['gallery_images_path'] AND $settings['gallery_images_path'] != $settings['upload_dir'] )
				{
					$url		= $settings['gallery_images_url'] ?: rtrim( \IPS\Settings::i()->base_url, '/' ) . '/applications/gallery/interface/legacy/image.php?path=';
					$gallery	= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'FileSystem', 'configuration' => json_encode( array( 'dir' => str_replace( \IPS\ROOT_PATH, '{root}', $settings['gallery_images_path'] ), 'url' => $url, 'toggle' => 1, 'custom_url' => $url ) ) ) );
				}
				else
				{
					$gallery	= $attachments;
				}

				/* Blog */
				if( isset( $settings['blog_upload_dir'] ) AND $settings['blog_upload_dir'] AND $settings['blog_upload_dir'] != $settings['upload_dir'] )
				{
					$blog	= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'FileSystem', 'configuration' => json_encode( array( 'dir' => str_replace( \IPS\ROOT_PATH, '{root}', $settings['blog_upload_dir'] ), 'url' => $settings['blog_upload_url'], 'toggle' => 1, 'custom_url' => $settings['blog_upload_url'] ) ) ) );
				}
				else
				{
					$blog	= $attachments;
				}

				/* Downloads */
				if( isset( $settings['idm_filestorage'] ) )
				{
					$_SESSION['downloads_url']		= NULL;
					$_SESSION['screenshots_url']	= NULL;

					if( $settings['idm_filestorage'] == 'ftp' )
					{
						$downloads		= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'Ftp', 'configuration' => json_encode( array( 
							'host'		=> str_replace( "ssl://", "", $settings['idm_remoteurl'] ),
							'port'		=> $settings['idm_remoteport'] ?: 21,
							'ssl'		=> ( mb_strpos( $settings['idm_remoteurl'], "ssl://" ) === 0 ) ? true : false,
							'username'	=> $settings['idm_remoteuser'],
							'password'	=> $settings['idm_remotepass'],
							'path'		=> $settings['idm_remotefilepath'],
							'url'		=> $settings['idm_remotefileurl'],
						) ) ) );

						$screenshots	= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'Ftp', 'configuration' => json_encode( array( 
							'host'		=> str_replace( "ssl://", "", $settings['idm_remoteurl'] ),
							'port'		=> $settings['idm_remoteport'] ?: 21,
							'ssl'		=> ( mb_strpos( $settings['idm_remoteurl'], "ssl://" ) === 0 ) ? true : false,
							'username'	=> $settings['idm_remoteuser'],
							'password'	=> $settings['idm_remotepass'],
							'path'		=> $settings['idm_remotesspath'],
							'url'		=> $settings['idm_remotessurl'],
						) ) ) );

						$_SESSION['downloads_url']		= trim( $settings['idm_remotefileurl'], '/' );
						$_SESSION['screenshots_url']	= trim( $settings['idm_remotessurl'], '/' );
					}
					else if( $settings['idm_filestorage'] == 'db' )
					{
						$downloads		= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'Database', 'configuration' => json_encode( array() ) ) );
						$screenshots	= $downloads;

						if( \IPS\Db::i()->checkForTable( 'downloads_files_records' ) )
						{
							\IPS\Db::i()->insert( 'core_files',
								\IPS\Db::i()->select( "NULL, downloads_files_records.record_location, MD5( RAND() ), downloads_filestorage.storage_file, ''", 'downloads_filestorage', "downloads_files_records.record_type='upload' AND downloads_files_records.record_storagetype='db' AND downloads_filestorage.storage_file IS NOT NULL AND downloads_filestorage.storage_file != ''" )->join( 'downloads_files_records', "downloads_filestorage.storage_id=downloads_files_records.record_db_id" )
							);

							\IPS\Db::i()->insert( 'core_files',
								\IPS\Db::i()->select( "NULL, downloads_files_records.record_location, MD5( RAND() ), downloads_filestorage.storage_ss, ''", 'downloads_filestorage', "downloads_files_records.record_type='ssupload' AND downloads_files_records.record_storagetype='db' AND downloads_filestorage.storage_ss IS NOT NULL AND downloads_filestorage.storage_ss != ''" )->join( 'downloads_files_records', "downloads_filestorage.storage_id=downloads_files_records.record_db_id" )
							);

							\IPS\Db::i()->insert( 'core_files',
								\IPS\Db::i()->select( "NULL, downloads_files_records.record_location, MD5( RAND() ), downloads_filestorage.storage_thumb, ''", 'downloads_filestorage', "downloads_files_records.record_type='ssupload' AND downloads_files_records.record_storagetype='db' AND downloads_filestorage.storage_thumb IS NOT NULL AND downloads_filestorage.storage_thumb != ''" )->join( 'downloads_files_records', "downloads_filestorage.storage_id=downloads_files_records.record_db_id" )
							);
						}

						$_SESSION['downloads_url']		= '';
						$_SESSION['screenshots_url']	= 'applications/downloads/interface/legacy/screenshot.php?path=';
					}
					else if( $settings['idm_filestorage'] == 'disk' )
					{
						$_SESSION['downloads_url']		= trim( str_replace( '{root_path}', '', str_replace( \IPS\ROOT_PATH, '', $settings['idm_localfilepath'] ) ), '/' );
						$_SESSION['screenshots_url']	= $settings['idm_screenshot_url'] ? rtrim( $settings['idm_screenshot_url'], '/' ) : '';
						$useCustom						= FALSE;

						if( !$_SESSION['screenshots_url'] )
						{
							if( mb_strpos( str_replace( array( '{root_path}', '{root}' ), \IPS\ROOT_PATH, $settings['idm_localsspath'] ), \IPS\ROOT_PATH ) === 0 )
							{
								$_SESSION['screenshots_url'] = trim( str_replace( array( '{root_path}', '{root}' ), '', $settings['idm_localsspath'] ), '/' );
							}
							else
							{
								$_SESSION['screenshots_url']	= 'applications/downloads/interface/legacy/screenshot.php?path=';
								$useCustom = TRUE;
							}
						}

						$downloads		= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'FileSystem', 'configuration' => json_encode( array( 
							'dir'			=> str_replace( '{root_path}', '{root}', $settings['idm_localfilepath'] ),
							'url'			=> $_SESSION['downloads_url'],
							'toggle'		=> 1, 
							'custom_url'	=> rtrim( \IPS\Settings::i()->base_url, '/' ) . '/' . $_SESSION['downloads_url']
						) ) ) );

						if( $useCustom )
						{
							$screenshotConfig = array( 
								'dir'			=> str_replace( '{root_path}', '{root}', $settings['idm_localsspath'] ),
								'url'			=> '',
								'toggle'		=> 1,
								'custom_url'	=> rtrim( \IPS\Settings::i()->base_url, '/' ) . '/' . $_SESSION['screenshots_url'],
							);
						}
						else
						{
							$screenshotConfig = array( 
								'dir'			=> str_replace( '{root_path}', '{root}', $settings['idm_localsspath'] ),
								'url'			=> $_SESSION['screenshots_url']
							);
						}

						$screenshots	= \IPS\Db::i()->insert( 'core_file_storage', array( 'method' => 'FileSystem', 'configuration' => json_encode( $screenshotConfig ) ) );
					}
				}
				else
				{
					$downloads		= $attachments;
					$screenshots	= $attachments;
				}

				$configs		= array(
					'filestorage__core_Advertisements'		=> $attachments,
					'filestorage__core_Attachment'			=> $attachments,
					'filestorage__core_Emoticons'			=> $attachments,
					'filestorage__core_Profile'				=> $attachments,
					'filestorage__core_Theme'				=> $attachments,
					'filestorage__downloads_Files'			=> $downloads,
					'filestorage__downloads_Screenshots'	=> $screenshots,
					'filestorage__calendar_Events'			=> $attachments,
					'filestorage__forums_Icons'				=> $attachments,
					'filestorage__gallery_Images'			=> $gallery,
					'filestorage__blog_Blogs'				=> $blog,
					'filestorage__blog_Entries'				=> $blog,
				);

				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_value' => 'FileSystem', 'conf_app' => 'core', 'conf_key' => 'upload_type' ) );
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_value' => json_encode( $configs ), 'conf_app' => 'core', 'conf_key' => 'upload_settings' ) );
			}
		}

		unset( \IPS\Data\Store::i()->storageConfigurations );
		\IPS\Settings::i()->clearCache();

		/* This constant isn't used in 4.0 but may be leftover from 3.x */
		$publicPath = ( \defined( 'PUBLIC_DIRECTORY' ) ? PUBLIC_DIRECTORY : 'public' );

		/* Loop over records to fix the paths to urls */
		if( \IPS\Db::i()->checkForTable( $table ) )
		{
			/* Try to prevent timeouts to the extent possible */
			$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

			foreach( \IPS\Db::i()->select( '*', $table, NULL, $tables[ $table ]['id'] . ' ASC', array( $limit, $perCycle ) ) as $row )
			{
				if( $cutOff !== null AND time() >= $cutOff )
				{
					return base64_encode( json_encode( array( 'table' => $table, 'limit' => ( $limit + $did ) ) ) );
				}

				$did++;
				
				$update	= array();

				foreach( $tables[ $table ]['columns'] as $column )
				{
					if( $table === 'core_attachments' )
					{
						if( mb_strpos( $row[ $column ], '.ipb' ) !== FALSE AND file_exists( $settings['upload_dir'] . '/' . $row[ $column ] ) )
						{
							$pathDetails = explode( '/', $row[ $column ] );
							$fileName	 = array_pop( $pathDetails );
							$newPathname = ltrim( implode( '/', $pathDetails ) . '/' . $row['attach_file'] . '.' . md5( mt_rand() ), '/' );
							
							if( @rename( $settings['upload_dir'] . '/' . $row[ $column ], $settings['upload_dir'] . '/' . $newPathname ) )
							{
								$update[ $column ] = $newPathname;
							}
						}
					}

					if ( $table === 'core_member_ranks' )
					{
						/* The "pips" column is not required to be filled in */
						if ( $row[ $column ] AND !\is_numeric( $row[ $column ] ) )
						{
							if ( file_exists( \IPS\ROOT_PATH . '/' . $publicPath . '/style_extra/team_icons/' . $row[ $column ] ) )
							{
								@copy( \IPS\ROOT_PATH . '/' . $publicPath . '/style_extra/team_icons/' . $row[ $column ], $settings['upload_dir'] . '/pip_' . $row[ $column ] );
								
								$update[ $column ]	= 'pip_' . $row[ $column ];
							}
						}
					}
					else if ( $table === 'core_groups' )
					{
						/* Do we have an image? */
						if( $row[ $column ] )
						{
							$_test	= parse_url( $row[ $column ] );
							$path	= explode( '/', $row[ $column ] );
							$file	= array_pop( $path );

							/* If this is a relative path, we should copy the image and use that path */
							if( ( !isset( $_test['host'] ) OR !$_test['host'] ) AND file_exists( \IPS\ROOT_PATH . '/' . $row[ $column ] ) )
							{
								@copy( \IPS\ROOT_PATH . '/' . $row[ $column ], $settings['upload_dir'] . '/team_' . $file );

								$update[ $column ]	= 'team_' . $file;
							}
							/* Otherwise if this is a URL, we should copy the file locally */
							else
							{
								try
								{
									$contents = \IPS\Http\Url::external( $row[ $column ] )->request()->get();

									@file_put_contents( $settings['upload_dir'] . '/team_' . $file, $contents );

									$update[ $column ]	= 'team_' . $file;
								}
								catch( \Exception $e ){}
							}
						}
					}
					else if ( $table === 'core_emoticons' )
					{
						$path = $settings['upload_dir'];
						$url  = '';
						
						if ( is_dir( $path . '/emoticons' ) )
						{
							$path .= '/emoticons';
							$url = 'emoticons/';
						}
						else if ( @mkdir( $path . '/emoticons' ) )
						{
							@chmod( $path . '/emoticons', \IPS\IPS_FOLDER_PERMISSION );
							$path .= '/emoticons';
							$url  = 'emoticons/';
						}
						
						if ( file_exists( \IPS\ROOT_PATH . '/' . $publicPath . '/style_emoticons/' . $row['emo_set'] . '/' . $row['image'] ) )
						{
							/* The emoticon may have existed in a sub-folder */
							if ( mb_stripos( $row['image'], '/' ) !== FALSE )
							{
								/* Work out the path */
								$emo_path	= explode( '/', $row['image'] );
								$emo_name	= array_pop( $emo_path );
								
								@copy( \IPS\ROOT_PATH . '/' . $publicPath . '/style_emoticons/' . $row['emo_set'] . '/' . implode( '/', $emo_path ) . '/' . $emo_name, $path . '/' . $row['emo_set'] . '_' . implode( '_', $emo_path ) . '_' . $emo_name );
								
								$update[ $column ]	= $url . $row['emo_set'] . '_' . implode( '_', $emo_path ) . '_' . $emo_name;
							}
							else
							{
								@copy( \IPS\ROOT_PATH . '/' . $publicPath . '/style_emoticons/' . $row['emo_set'] . '/' . $row['image'], $path . '/' . $row['emo_set'] . '_' . $row['image'] );
								
								$update[ $column ]	= $url . $row['emo_set'] . '_' . $row['image'];
							}
						}
						else
						{
							\IPS\Db::i()->delete( $table, $tables[ $table ]['id'] . '=' . $row[ $tables[ $table ]['id'] ] );
							continue;
						}
					}
				}
				
				if ( \count( $update ) )
				{
					foreach( $update as $col => $url )
					{
						$update[ $col ] = rtrim( str_replace( rtrim( \IPS\Settings::i()->base_url, '/' ) . '/uploads/', '', $url ), '/' );
						$update[ $col ] = preg_replace( '#^(/)?uploads/#', '', $update[ $col ] );
					}
					
					\IPS\Db::i()->update( $table, $update, $tables[ $table ]['id'] . '=' . $row[ $tables[ $table ]['id'] ] );
				}
			}
		}

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			return base64_encode( json_encode( array( 'table' => $table, 'limit' => ( $limit + $did ) ) ) );
		}
		else
		{
			$nextTable		= NULL;
			$seenCurrent	= FALSE;

			foreach( $tables as $_table => $cols )
			{
				if( $_table == $table )
				{
					$seenCurrent	= TRUE;
					continue;
				}
				else if( $seenCurrent )
				{
					$nextTable	= $_table;
					break;
				}
			}

			if( $nextTable )
			{
				return base64_encode( json_encode( array( 'table' => $nextTable, 'limit' => 0 ) ) );
			}
			else
			{
				foreach( $tables as $k => $v )
				{
					if( isset( $_SESSION['_step18' . $k . 'Count'] ) )
					{
						unset( $_SESSION['_step18' . $k . 'Count'] );
					}
				}
				return TRUE;
			}
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step18CustomTitle()
	{
		$limit = 0;
		$table = null;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$_data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );
			$limit	= $_data['limit'];
			$table	= $_data['table'];

			if( !isset( $_SESSION['_step18' . $table . 'Count'] ) )
			{
				$_SESSION['_step18' . $table . 'Count'] = ( \IPS\Db::i()->checkForTable( $table ) ) ? \IPS\Db::i()->select( 'COUNT(*)', $table )->first() : 0;
			}
		}

		return $table ? "Updating stored file URLs (Updated so far: " . ( ( $limit > $_SESSION['_step18' . $table . 'Count'] ) ? $_SESSION['_step18' . $table . 'Count'] : $limit ) . ' out of ' . $_SESSION['_step18' . $table . 'Count'] . ' in database table ' . $table . ')' : "Updating stored file URLs";
	}

	/* ! Bulk mails */
	/**
	 * Step 19
	 * Update bulk mails to parse
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step19()
	{
		$perCycle	= 100;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_bulk_mail', null, 'mail_id ASC', array( $limit, $perCycle ) ) as $mail )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* Update */
			try
			{
				$mail['mail_content']	= \IPS\Text\LegacyParser::parseStatic( $mail['mail_content'], NULL, TRUE );
			}
			catch( \InvalidArgumentException $e )
			{
				if( $e->getcode() == 103014 )
				{
					$mail['mail_content']	= preg_replace( "#\[/?([^\]]+?)\]#", '', $mail['mail_content'] );
				}
				else
				{
					throw $e;
				}
			}

			\IPS\Db::i()->update( 'core_bulk_mail', array( 'mail_content' => $mail['mail_content'] ), array( 'mail_id=?', $mail['mail_id'] ) );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step19Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step19CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step19Count'] ) )
		{
			$_SESSION['_step19Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_bulk_mail' )->first();
		}

		return "Upgrading bulk mails (Upgraded so far: " . ( ( $limit > $_SESSION['_step19Count'] ) ? $_SESSION['_step19Count'] : $limit ) . ' out of ' . $_SESSION['_step19Count'] . ')';
	}


	/**
	 * Step 20
	 * Update last notify times for follows
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step20()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_follow',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_follow SET follow_notify_sent=" . time()
		) ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 21 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step20CustomTitle()
	{
		return "Upgrading last notify times for follows";
	}

	/* ! Other stuff */
	/**
	 * Step 21
	 * Other
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step21()
	{
		/* Delete any item markers */
		\IPS\Db::i()->delete( 'core_item_markers' );

		/* Delete any existing hooks */
		\IPS\Db::i()->delete( 'core_hooks' );

		/* Clear out any existing module records */
		\IPS\Db::i()->delete( 'core_modules' );
		\IPS\Db::i()->delete( 'core_permission_index', array( 'perm_type=?', 'module' ) );
		
		/* Remove members app */
		\IPS\Db::i()->delete( 'core_applications', array( 'app_directory=?', 'members' ) );
		
		/* "If setting post_titlechange is -1, make 0, and vice-versa." */
		if( \IPS\Settings::i()->post_titlechange == '-1' )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => '0' ), "conf_key='post_titlechange'" );
		}
		elseif( \IPS\Settings::i()->post_titlechange == '0' )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => '-1' ), "conf_key='post_titlechange'" );
		}

		/* If validate_day_prune (remove members who haven't validated after x days) has not been adjusted, set conf_value to conf_default or the setting suddenly becomes enabled - 3.4.x default was off */
		if( !\IPS\Settings::i()->validate_day_prune )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', "conf_value=conf_default", "conf_key='validate_day_prune'" );
		}

		/* Fix sitemap URL back to the default */
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( "conf_value" => \IPS\Settings::i()->base_url . 'sitemap.php' ), "conf_key='sitemap_url'" );

		/* "Copy value of nexus_ads_circmode setting (if available) to ads_circulation" */
		if( isset( \IPS\Settings::i()->nexus_ads_circmode ) )
		{
			$newValue	= NULL;

			if( \IPS\Settings::i()->nexus_ads_circmode == 'rand' )
			{
				$newValue	= 'random';
			}
			else if( \IPS\Settings::i()->nexus_ads_circmode == 'last' )
			{
				$newValue	= 'newest';
			}

			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $newValue ), "conf_key='nexus_ads_circmode'" );
		}

		if( \IPS\Settings::i()->spm_option == 'ban' )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => 'ban,delete' ), array( 'conf_key=?', 'spm_option' ) );
		}

		/* Make sure license key does not have extra spaces at end */
		\IPS\Settings::i()->changeValues( array( 'ipb_reg_number' => trim( \IPS\Settings::i()->ipb_reg_number ) ) );

		/* Settings */
		\IPS\Lang::saveCustom( 'core', "copyright_line_value", \IPS\Settings::i()->ipb_reg_name );

		try
		{
			$guidelines	= \IPS\Text\LegacyParser::parseStatic( \IPS\Settings::i()->gl_guidelines, NULL, TRUE );
		}
		catch( \Exception $e )
		{
			if( $e->getcode() == 103014 )
			{
				$guidelines	= preg_replace( "#\[/?([^\]]+?)\]#", '', \IPS\Settings::i()->gl_guidelines );
			}
			else
			{
				throw $e;
			}
		}

		\IPS\Lang::saveCustom( 'core', "guidelines_value", $guidelines );

		try
		{
			$reg_rules	= \IPS\Text\LegacyParser::parseStatic( \IPS\Settings::i()->reg_rules, NULL, TRUE );
		}
		catch( \Exception $e )
		{
			if( $e->getcode() == 103014 )
			{
				$reg_rules	= preg_replace( "#\[/?([^\]]+?)\]#", '', \IPS\Settings::i()->reg_rules );
			}
			else
			{
				throw $e;
			}
		}

		\IPS\Lang::saveCustom( 'core', "reg_rules_value", $reg_rules );

		try
		{
			$priv_body	= \IPS\Text\LegacyParser::parseStatic( \IPS\Settings::i()->priv_body, NULL, TRUE );
		}
		catch( \Exception $e )
		{
			if( $e->getcode() == 103014 )
			{
				$priv_body	= preg_replace( "#\[/?([^\]]+?)\]#", '', \IPS\Settings::i()->priv_body );
			}
			else
			{
				throw $e;
			}
		}

		\IPS\Lang::saveCustom( 'core', "privacy_text_value", $priv_body );

		/* Convert offline status/message */
		if( \IPS\Settings::i()->board_offline )
		{
			\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => 'site_online', 'conf_default' => 1, 'conf_value' => 0 ) );
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_key' => 'site_offline_message' ), array( 'conf_key=?', 'offline_msg' ) );
		}

		if( isset( \IPS\Settings::i()->blog_offline_text ) )
		{
			$update = array( 'app_disabled_message' => \IPS\Settings::i()->blog_offline_text );

			if( !\IPS\Settings::i()->blog_online )
			{
				$update['app_enabled']	= 0;
			}

			\IPS\Db::i()->update( 'core_applications', $update, array( 'app_directory=?', 'blog' ) );
		}

		if( isset( \IPS\Settings::i()->ipschat_offline_msg ) )
		{
			$update = array( 'app_disabled_message' => \IPS\Settings::i()->ipschat_offline_msg );

			if( !\IPS\Settings::i()->ipschat_online )
			{
				$update['app_enabled']	= 0;
			}

			\IPS\Db::i()->update( 'core_applications', $update, array( 'app_directory=?', 'chat' ) );
		}

		if( isset( \IPS\Settings::i()->ccs_offline_message ) )
		{
			$update = array( 'app_disabled_message' => \IPS\Settings::i()->ccs_offline_message );

			if( !\IPS\Settings::i()->ccs_online )
			{
				$update['app_enabled']	= 0;
			}

			\IPS\Db::i()->update( 'core_applications', $update, array( 'app_directory=?', 'cms' ) );
		}

		if( isset( \IPS\Settings::i()->idm_offline_msg ) )
		{
			$update = array( 'app_disabled_message' => \IPS\Settings::i()->idm_offline_msg );

			if( !\IPS\Settings::i()->idm_online )
			{
				$update['app_enabled']	= 0;
			}

			\IPS\Db::i()->update( 'core_applications', $update, array( 'app_directory=?', 'downloads' ) );
		}

		if( isset( \IPS\Settings::i()->gallery_offline_text ) )
		{
			$update = array( 'app_disabled_message' => \IPS\Settings::i()->gallery_offline_text );

			/* Yes, this was reversed from most of the other settings */
			if( \IPS\Settings::i()->gallery_offline )
			{
				$update['app_enabled']	= 0;
			}

			\IPS\Db::i()->update( 'core_applications', $update, array( 'app_directory=?', 'gallery' ) );
		}

		/* Update guest group access to site if required */
		if( \IPS\Settings::i()->force_login )
		{
			$group = \IPS\Member\Group::load( \IPS\Settings::i()->guest_group );
			$group->g_view_board = FALSE;
			$group->save();
		}

		/* Disable cron task job */
		\IPS\Settings::i()->changeValues( array( 'task_use_cron' => 0 ) );

		/* Delete old settings - some of these should not still be present but might have slipped through */
		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN ('admob_bottom', 'admob_pub_id', 'admob_top', 'ad_code_board_index_footer', 'ad_code_board_index_header', 'ad_code_board_sidebar', 
			'ad_code_exempt_groups', 'ad_code_forum_view_footer', 'ad_code_forum_view_header', 'ad_code_forum_view_topic_code', 'ad_code_global_enabled', 'ad_code_global_footer', 'ad_code_global_header', 
			'ad_code_topic_view_code', 'ad_code_topic_view_footer', 'ad_code_topic_view_header', 'allow_online_list', 'allow_search', 'archive_restore_days', 'attach_img_max_h', 'attach_img_max_w', 
			'auth_allow_dnames', 'auth_dnames_nologinname', 'autohide_bday', 'autohide_calendar', 'au_cutoff', 'bbcode_automatic_media', 'board_offline', 'calendar_date_select_locale', 'calendar_limit', 
			'cal_date_format', 'cal_time_format', 'cannot_ignore_groups', 'charset_conv_method', 'clock_date', 'clock_joined', 'clock_long', 'clock_short', 'clock_short2', 'clock_tiny', 'cookie_domain', 
			'cookie_id', 'cookie_path', 'custom_profile_topic', 'days_to_keep_deletions', 'debug_level', 'default_vnc_method', 'disable_gzip', 'disable_lightbox', 'disable_prefetching', 
			'disable_subforum_show', 'disable_summary', 'disable_text_ajax', 'email_use_html', 'error_log_notify', 'etfilter_punct', 'etfilter_shout', 'fbc_appid', 
			'fbc_enable', 'fbc_mgid', 'fbc_secret', 'fb_locale', 'fb_realname', 'force_login', 'force_sql_vnc', 'forums_enabled_also_tagged', 'gb_char_set', 'gl_show', 'guests_sig', 'guest_name_pre', 
			'guest_name_suf', 'header_redirect', 'hide_ftext_note', 'hot_topic', 'http_auth_password', 'http_auth_username', 'img_ext', 'incoming_emails_textpref', 'ipb_cache_path', 'ipb_cache_url', 
			'ipb_css_url', 'ipb_display_version', 'ipb_img_url', 'ipb_js_url', 'ipb_prune_admin', 'ipb_prune_email', 'ipb_prune_emailerror', 'ipb_prune_mod', 'ipb_prune_spam', 'ipb_prune_sql', 'ipb_prune_task', 
			'ipb_reg_name', 'ipb_use_url_filter', 'iphone_notifications_enabled', 'iphone_notifications_groups', 'ipseo_guest_skin', 'ipseo_ping_services', 'ips_cdn', 'kill_search_after', 'links_external', 
			'load_limit', 'login_key_expire', 'lost_pw_prune', 'mail_wrap_brackets', 'map_bing_api_key', 'maxmind_error', 'maxmind_key', 'max_emos', 'max_h_flash', 'max_images', 'max_media_files', 
			'max_post_length', 'max_quotes_per_post', 'max_w_flash', 'member_topic_avatar_max', 'mem_photo_url', 'meta_imagesrc', 'min_search_word', 'nocache', 'no_au_forum', 'no_au_topic', 'no_reg', 
			'override_inbox_full', 'photo_ext', 'postageapp_api_key', 'post_merge_conc', 'post_order_sort', 'print_headers', 'priv_body', 'priv_title', 'prune_admin_login_logs', 
			'prune_error_logs', 'prune_share_link_logs', 'prune_topic_archive_logs', 'rating_feed_enabled', 'recaptcha_language', 'remote_load_js', 'remove_forums_nav', 'report_mod_group_access', 
			'resize_img_force', 'safe_mode_skins', 'search_hardlimit', 'search_method', 'search_per_page', 'search_ucontent_days', 'seo_index_md', 'seo_index_mk', 'seo_index_title', 'session_expiration', 
			'show_active', 'show_birthdays', 'show_calendar', 'show_img_upload', 'show_max_msg_list', 'show_totals', 'show_user_posted', 'sitemap_count_calendar_future', 'sitemap_count_calendar_past', 
			'sitemap_priority_calendar', 'sitemap_priority_forums', 'sitemap_priority_index', 'sitemap_priority_topics', 'sitemap_recent_topics', 'sitemap_topic_pages', 'siu_height', 'siu_thumb', 
			'siu_width', 'sixty_second_rule', 'smtp_helo', 'spam_service_action_timeout', 'spam_service_timeout', 'sphinx_wildcard', 'spider_active', 'start_year', 'strip_space_chr', 'style_last_updated', 
			'support_email_out', 's_andor_type', 'tc_enabled', 'tc_mgid', 'tc_secret', 'tc_token', 'time_adjust', 'time_dst_auto_correction', 'time_offset', 'time_use_relative', 'time_use_relative_format', 
			'topic_marking_enable', 'topic_marking_guests', 'topic_marking_keep_days', 'topic_rating_needed', 'topic_title_max_len', 'unblockable_pm_groups', 'update_topic_views_immediately', 
			'uploadFormType', 'upload_dir', 'upload_domain', 'upload_url', 'url_type', 'usernames_nobr', 'username_errormsg', 'use_fulltext', 'use_minify', 'vnp_block_forums', 'warnings_enable_other', 
			'warning_custom_noaction', 'warn_gmod_day', 'warn_mod_day', 'webdav_on', 'xmlrpc_enable', 'xmlrpc_log_expire', 'xmlrpc_log_type', 'year_limit', 'reg_rules', 'gl_guidelines', 'blog_offline_text',
			'ipschat_offline_msg', 'ccs_offline_message', 'ccs_online', 'blog_upload_dir', 'blog_upload_url', 'idm_offline_msg', 'idm_online', 'blog_online', 'ipschat_online', 'gallery_offline_text', 'gallery_offline' )" );

		/* Delete old theme stuff */
		\IPS\Db::i()->delete( 'core_theme_css' );
		\IPS\Db::i()->delete( 'core_theme_templates' );
		\IPS\Db::i()->delete( 'core_themes' );
		
		/* Insert a new default theme */
		\IPS\Db::i()->insert( 'core_themes', array(
			"set_id"				=> 1,
			"set_name"				=> "Default",
            "set_key"				=> "default",
            "set_parent_id"			=> 0,
            "set_parent_array"		=> NULL,
            "set_child_array"		=> NULL,
            "set_permissions"		=> '*',
            "set_is_default"		=> 1,
            "set_author_name"		=> "Invision Power Services",
            "set_author_url"		=> "https:\/\/www.invisioncommunity.com",
            "set_emo_dir"			=> "'default'",
            "set_added"				=> 0,
            "set_updated"			=> 0,
            "set_hide_from_list"	=> 0,
            "set_order"			 	=> 0,
            "set_by_skin_gen"		=> 0,
            "set_skin_gen_data"		=> NULL,
            "set_template_settings" => NULL,
            "set_editor_skin"		=> "ips",
            "set_logo_data"			=> NULL,
            "set_update_data"		=> NULL
		) );
		
		\IPS\Lang::saveCustom( 'core', "core_theme_set_title_1", "Default" );
		
		/* URL Filter rules */
		foreach( \IPS\Db::i()->select( '*', 'core_sys_conf_settings', array( "conf_key IN('ipb_use_url_filter', 'ipb_url_filter_option', 'ipb_url_whitelist', 'ipb_url_blacklist')" ) ) as $row )
		{
			if ( $row['conf_value'] )
			{
				$settings[ $row['conf_key'] ] = $row['conf_value'];
			}
		}
		
		foreach( array( 'ipb_url_whitelist', 'ipb_url_blacklist' ) as $list )
		{
			if ( isset( $settings[ $list ] ) AND ! empty( $settings[ $list ] ) )
			{
				\IPS\Settings::i()->changeValues( array( $list => implode( ",", explode( "\n", $settings[ $list ] ) ) ) );
			}
		}
		
		if ( !isset( $settings['ipb_use_url_filter'] ) OR empty( $settings['ipb_use_url_filter'] ) )
		{
			\IPS\Settings::i()->changeValues( array( 'ipb_url_filter_option' => 'none' ) );
		}

		/* Disable all apps not currently being upgraded */
		if ( \is_array( $_SESSION['apps'] ) )
		{
			\IPS\Db::i()->update( 'core_applications', array( 'app_enabled' => 0 ), 'app_directory NOT IN (\'' . implode( "','", array_keys( $_SESSION['apps'] ) ) . '\')' ); 
		}
		
		/* Make sure default language is at the top */
		\IPS\Db::i()->update( 'core_sys_lang', array( 'lang_order' => 2 ) );
		\IPS\Db::i()->update( 'core_sys_lang', array( 'lang_order' => 1 ), "lang_default=1" );

		if( \IPS\Db::i()->checkForTable('warn_logs') )
		{
			\IPS\Db::i()->dropTable( 'warn_logs' );
		}

		if( \IPS\Db::i()->checkForTable('core_rss_imported') )
		{
			\IPS\Db::i()->dropTable( 'core_rss_imported' );
		}

		$acpNotes = \IPS\Db::i()->select( 'cs_value', 'cache_store', array( 'cs_key=?', 'adminnotes' ) )->first();
		\IPS\Settings::i()->changeValues( array( 'acp_notes' => $acpNotes ) );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step21CustomTitle()
	{
		return "Cleaning up miscellaneous data";
	}

	/**
	 * Step 22
	 * Cleanup
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step22()
	{
		if( \IPS\Db::i()->checkForTable( 'content_cache_sigs' ) )
		{
			\IPS\Db::i()->dropTable( 'content_cache_sigs' );
		}

		if( \IPS\Db::i()->checkForTable( 'content_cache_posts' ) )
		{
			\IPS\Db::i()->dropTable( 'content_cache_posts' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_share_links_log' ) )
		{
			\IPS\Db::i()->dropTable( 'core_share_links_log' );
		}

		if( \IPS\Db::i()->checkForTable( 'member_status_actions' ) )
		{
			\IPS\Db::i()->dropTable( 'member_status_actions' );
		}

		if( \IPS\Db::i()->checkForTable( 'mod_queued_items' ) )
		{
			\IPS\Db::i()->dropTable( 'mod_queued_items' );
		}

		if( \IPS\Db::i()->checkForTable( 'attachments_type' ) )
		{
			\IPS\Db::i()->dropTable( 'attachments_type' );
		}

		if( \IPS\Db::i()->checkForTable( 'cache_simple' ) )
		{
			\IPS\Db::i()->dropTable( 'cache_simple' );
		}

		if( \IPS\Db::i()->checkForTable( 'captcha' ) )
		{
			\IPS\Db::i()->dropTable( 'captcha' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_editor_autosave' ) )
		{
			\IPS\Db::i()->dropTable( 'core_editor_autosave' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_geolocation_cache' ) )
		{
			\IPS\Db::i()->dropTable( 'core_geolocation_cache' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_item_markers_storage' ) )
		{
			\IPS\Db::i()->dropTable( 'core_item_markers_storage' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_sys_bookmarks' ) )
		{
			\IPS\Db::i()->dropTable( 'core_sys_bookmarks' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_uagents' ) )
		{
			\IPS\Db::i()->dropTable( 'core_uagents' );
		}

		if( \IPS\Db::i()->checkForTable( 'core_uagent_groups' ) )
		{
			\IPS\Db::i()->dropTable( 'core_uagent_groups' );
		}

		if( \IPS\Db::i()->checkForTable( 'faq' ) )
		{
			\IPS\Db::i()->dropTable( 'faq' );
		}

		if( \IPS\Db::i()->checkForTable( 'mail_queue' ) )
		{
			\IPS\Db::i()->dropTable( 'mail_queue' );
		}

		if( \IPS\Db::i()->checkForTable( 'members_partial' ) )
		{
			\IPS\Db::i()->dropTable( 'members_partial' );
		}

		if( \IPS\Db::i()->checkForTable( 'mobile_app_style' ) )
		{
			\IPS\Db::i()->dropTable( 'mobile_app_style' );
		}

		if( \IPS\Db::i()->checkForTable( 'mobile_device_map' ) )
		{
			\IPS\Db::i()->dropTable( 'mobile_device_map' );
		}

		if( \IPS\Db::i()->checkForTable( 'mobile_notifications' ) )
		{
			\IPS\Db::i()->dropTable( 'mobile_notifications' );
		}

		if( \IPS\Db::i()->checkForTable( 'profile_friends_flood' ) )
		{
			\IPS\Db::i()->dropTable( 'profile_friends_flood' );
		}

		if( \IPS\Db::i()->checkForTable( 'profile_portal_views' ) )
		{
			\IPS\Db::i()->dropTable( 'profile_portal_views' );
		}

		if( \IPS\Db::i()->checkForTable( 'profile_ratings' ) )
		{
			\IPS\Db::i()->dropTable( 'profile_ratings' );
		}

		if( \IPS\Db::i()->checkForTable( 'rc_classes' ) )
		{
			\IPS\Db::i()->dropTable( 'rc_classes' );
		}

		if( \IPS\Db::i()->checkForTable( 'rc_modpref' ) )
		{
			\IPS\Db::i()->dropTable( 'rc_modpref' );
		}

		if( \IPS\Db::i()->checkForTable( 'rc_status' ) )
		{
			\IPS\Db::i()->dropTable( 'rc_status' );
		}

		if( \IPS\Db::i()->checkForTable( 'rc_status_sev' ) )
		{
			\IPS\Db::i()->dropTable( 'rc_status_sev' );
		}

		if( \IPS\Db::i()->checkForTable( 'reputation_cache' ) )
		{
			\IPS\Db::i()->dropTable( 'reputation_cache' );
		}

		if( \IPS\Db::i()->checkForTable( 'reputation_totals' ) )
		{
			\IPS\Db::i()->dropTable( 'reputation_totals' );
		}

		if( \IPS\Db::i()->checkForTable( 'rss_export' ) )
		{
			\IPS\Db::i()->dropTable( 'rss_export' );
		}

		if( \IPS\Db::i()->checkForTable( 'task_manager' ) )
		{
			\IPS\Db::i()->dropTable( 'task_manager' );
		}

		if( \IPS\Db::i()->checkForTable( 'task_logs' ) )
		{
			\IPS\Db::i()->dropTable( 'task_logs' );
		}

		if( \IPS\Db::i()->checkForTable( 'template_sandr' ) )
		{
			\IPS\Db::i()->dropTable( 'template_sandr' );
		}

		if( \IPS\Db::i()->checkForTable( 'twitter_connect' ) )
		{
			\IPS\Db::i()->dropTable( 'twitter_connect' );
		}

		if( \IPS\Db::i()->checkForTable( 'upgrade_sessions' ) )
		{
			\IPS\Db::i()->dropTable( 'upgrade_sessions' );
		}

		if( \IPS\Db::i()->checkForTable( 'backup_log' ) )
		{
			\IPS\Db::i()->dropTable( 'backup_log' );
		}

		if( \IPS\Db::i()->checkForTable( 'backup_queue' ) )
		{
			\IPS\Db::i()->dropTable( 'backup_queue' );
		}

		if( \IPS\Db::i()->checkForTable( 'backup_vars' ) )
		{
			\IPS\Db::i()->dropTable( 'backup_vars' );
		}

		/* Convert sessions table if it is memory and add data column */
		$sessionTable	= \IPS\Db::i()->getTableDefinition( 'core_sessions' );

		if( isset( $sessionTable['engine'] ) AND \in_array( mb_strtolower( $sessionTable['engine'] ), array( 'memory', 'heap' ) ) )
		{
			\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_sessions ENGINE=" . \IPS\Db::i()->defaultEngine() );
		}

		\IPS\Db::i()->addColumn( 'core_sessions', array( "name" => "data", "type" => "TEXT", "length" => null, "allow_null" => true, "default" => null, "comment" => "", "auto_increment" => false, "binary" => false ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step22CustomTitle()
	{
		return "Removing old unused data";
	}

	/**
	 * Serialized: Customer History - This is intentionally here so this can be processed before the Commerce upgrade, by which time this table won't exist.
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step23()
	{
		if( \IPS\Db::i()->checkForTable( 'nexus_customer_history' ) )
		{
			$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
			$select = \IPS\Db::i()->select( '*', 'nexus_customer_history', NULL, 'log_id', array( $offset, 500 ) );
			if ( \count( $select ) )
			{
				foreach ( $select as $row )
				{
					\IPS\Db::i()->update( 'nexus_customer_history', array( 'log_data' => json_encode( \unserialize( $row['log_data'] ) ) ), array( 'log_id=?', $row['log_id'] ) );
				}

				return $offset + 500;
			}
			else
			{
				unset( $_SESSION['_step23Count'] );
				return TRUE;
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step23CustomTitle()
	{
		if( !\IPS\Db::i()->checkForTable( 'nexus_customer_history' ) )
		{
			return NULL;
		}

		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step23Count'] ) )
		{
			$_SESSION['_step23Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_history' )->first();
		}

		return "Upgrading commerce customer history (Upgraded so far: " . ( ( $limit > $_SESSION['_step23Count'] ) ? $_SESSION['_step23Count'] : $limit ) . ' out of ' . $_SESSION['_step23Count'] . ')';
	}

	/**
	 * Update log_by column - This is intentionally here so this can be processed before the Commerce upgrade, by which time this table won't exist.
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step24()
	{
		if( \IPS\Db::i()->checkForTable( 'nexus_customer_history' ) )
		{
			\IPS\Db::i()->changeColumn( 'nexus_customer_history', 'log_by',
				array(
					'allow_null' => true,
					'auto_increment' => false,
					'binary' => false,
					'comment' => 'Action performed by',
					'decimals' => NULL,
					'default' => NULL,
					'length' => 20,
					'name' => 'log_by',
					'type' => 'BIGINT',
					'unsigned' => true,
					'values' => array(),
					'zerofill' => false,
				)
			);
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step24CustomTitle()
	{
		return "Upgrading commerce customer history";
	}

	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		/* Prevent being run twice */
		if( !\IPS\Db::i()->checkForColumn( 'core_groups', 'g_perm_id' ) )
		{
			return TRUE;
		}

		/* Make forums default app */
		\IPS\Db::i()->update( 'core_applications', array( 'app_default' => 0 ) );
		\IPS\Db::i()->update( 'core_applications', array( 'app_default' => 1 ), array( 'app_directory=?', 'forums' ) );

		/* We need to fix permissions */
		$groupMasks	= array();

		foreach( \IPS\Db::i()->select( 'g_id, g_perm_id', 'core_groups' ) as $group )
		{
			$groupMasks[ $group['g_id'] ]	= explode( ',', $group['g_perm_id'] );
		}

		foreach( \IPS\Db::i()->select( '*', 'core_permission_index' ) as $perm )
		{
			$update	= array();

			foreach( array( 'perm_view', 'perm_2', 'perm_3', 'perm_4', 'perm_5', 'perm_6', 'perm_7' ) as $permColumn )
			{
				$update[ $permColumn ]	= '';

				if( !empty( $perm[ $permColumn ] ) )
				{
					if( $perm[ $permColumn ] == '*' )
					{
						$update[ $permColumn ] = implode( ',', array_keys( $groupMasks ) );
					}
					else
					{
						$thisColumnGroups = array();

						foreach( explode( ',', $perm[ $permColumn ] ) as $viewMask )
						{
							foreach( $groupMasks as $groupId => $masks )
							{
								if( \in_array( $viewMask, $masks ) )
								{
									$thisColumnGroups[ $groupId ]	= $groupId;
								}
							}
						}

						$update[ $permColumn ]	= implode( ',', $thisColumnGroups );
					}
				}
			}

			if( \count( $update ) )
			{
				\IPS\Db::i()->update( 'core_permission_index', $update, array( 'perm_id=?', $perm['perm_id'] ) );
			}
		}

		\IPS\Db::i()->dropTable( 'forum_perms' );
		\IPS\Db::i()->dropColumn( 'core_groups', 'g_perm_id' );

		/* Now save some custom lang strings */
		foreach( \IPS\Db::i()->select( '*', 'core_applications' ) as $application )
		{
			/* The public title was 'Help Files' for the core app and 'Store' for Nexus in 3.x, which we don't really want to retain - the ACP title was always right */
			if( $application['app_directory'] == 'nexus' )
			{
				$application['app_title'] = 'Commerce';
			}
			else if( $application['app_directory'] == 'cms' )
			{
				$application['app_title'] = 'Pages';
			}

			\IPS\Lang::saveCustom( $application['app_directory'], "__app_" . $application['app_directory'], /*$application['app_public_title'] ?:*/ $application['app_title'] );
		}

		\IPS\Db::i()->dropColumn( 'core_applications', array( 'app_public_title', 'app_title' ) );

		\IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\core\Statuses\Status' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\core\Statuses\Reply' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Admin' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Announcement' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_CustomField' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Messaging' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Signatures' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Staffdirectory' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Reports' ), 2 );
		\IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'core_Modcp' ), 2 );
		
		/* Recount content */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array(), 4 );

		/* Try to retain most online stat */
		try
		{
			$stats = \IPS\Db::i()->select( '*', 'cache_store', array( 'cs_key=?', 'stats' ) )->first();
			$stats = unserialize( $stats['cs_value'] );
			
			$most_online = array(
				'count'		=> $stats['most_count'],
				'time'		=> $stats['most_date']
			);

			\IPS\Db::i()->replace( 'core_sys_conf_settings', array( 'conf_value' => json_encode( $most_online ), 'conf_key' => 'most_online', 'conf_app' => 'core' ), array( 'conf_key=?', 'most_online' ) );
			\IPS\Settings::i()->most_online = json_encode( $most_online );
		}
		catch( \UnderflowException $e ){}

		\IPS\Db::i()->dropTable( 'cache_store' );
		
		return TRUE;
	}
}