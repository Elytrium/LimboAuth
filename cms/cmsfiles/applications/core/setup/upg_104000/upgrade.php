<?php
/**
 * @brief		4.4.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Jun 2018
 * @since		17 Jul 2018
 */

namespace IPS\core\setup\upg_104000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Upgrade custom field configuration
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Loop over our fields */
		foreach( \IPS\Db::i()->select( '*', 'core_pfields_data' ) as $field )
		{
			$update = array();

			/* If pf_admin_only is set, adjust configuration appropriately as we will drop this column */
			if( $field['pf_admin_only'] )
			{
				$update['pf_show_on_reg']	= 0;
				$update['pf_member_edit']	= 0;
				$update['pf_member_hide']	= 'hide';
				$update['pf_topic_hide']	= 'hide';
			}
			else
			{
				/* If there is a topic format, set the flag to show it to everyone to match 4.3.x and below */
				if( $field['pf_format'] AND !$field['pf_member_hide'] )
				{
					$update['pf_topic_hide']	= 'all';
				}

				/* When the show on profile option was off on 4.3.x and below, the profile owner and staff could still see the field */
				if( $field['pf_member_hide'] )
				{
					$update['pf_member_hide']	= 'owner';
				}
				else
				{
					$update['pf_member_hide']	= 'all';
				}
			}

			if( $field['pf_format'] )
			{
				$update['pf_format'] = str_replace( array( '{title}', '{content}', '{member_id}' ), array( '{$title}', '{$content}', '{$member_id}' ), $field['pf_format'] );
			}

			if( count( $update ) )
			{
				\IPS\Db::i()->update( 'core_pfields_data', $update, array( 'pf_id=?', $field['pf_id'] ) );
			}
		}

		/* Drop the now-defunct pf_admin_only column */
		\IPS\Db::i()->dropColumn( 'core_pfields_data', 'pf_admin_only' );

		/* Clear cache */
		unset( \IPS\Data\Store::i()->profileFields );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Adjusting custom profile field configuration";
	}
	
	/**
	 * Update settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$settingsToUpdate = array( 'prune_notifications' => \IPS\Settings::i()->prune_notifications * 30 );
		
		if ( \IPS\Settings::i()->emoji_style == 'emojione' )
		{
			$settingsToUpdate['emoji_style'] = 'twemoji';
		}
		if ( \IPS\Settings::i()->stats_keywords )
		{
			$settingsToUpdate['stats_keywords'] = json_encode( array_unique( json_decode( \IPS\Settings::i()->stats_keywords, true ) ) );
		}
		
		\IPS\Settings::i()->changeValues( $settingsToUpdate );		
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Updating settings";
	}
	
	/**
	 * Update Notification Settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		foreach ( array_unique( explode( ',', \IPS\Settings::i()->upgrade_email ) ) as $email )
		{
			$member = \IPS\Member::load( $email, 'email' );
			if ( $member->member_id )
			{
				\IPS\Db::i()->insert( 'core_acp_notifications_preferences', array(
					'member'	=> $member->member_id,
					'type'		=> 'core_NewVersion',
					'view'		=> 1,
					'email'		=> 'always'
				) );
			}
		}
		
		foreach ( array( 'new_reg_notify' => 'core_NewRegComplete', 'spm_notify' => 'core_Spammer', 'error_notify_level' => 'core_Error' ) as $settingKey => $notificationKey )
		{
			if ( \IPS\Settings::i()->$settingKey )
			{
				$member = \IPS\Member::load( \IPS\Settings::i()->email_in, 'email' );
				if ( $member->member_id )
				{
					\IPS\Db::i()->insert( 'core_acp_notifications_preferences', array(
						'member'	=> $member->member_id,
						'type'		=> $notificationKey,
						'view'		=> 1,
						'email'		=> 'always'
					) );
				}
			}
		}
		
		$isNobody = FALSE;
		$supportAccount = \IPS\Member::load( 'ipstempadmin@invisionpower.com', 'email' );
		if ( !$supportAccount->member_id )
		{
			$supportAccount = \IPS\Member::load( 'nobody@invisionpower.com', 'email' );
			$isNobody = TRUE;
		}
		
		if ( $supportAccount->member_id )
		{
			if ( $isNobody )
			{
				$supportAccount->email = 'ipstempadmin@invisionpower.com';
			}
			
			$supportAccount->members_bitoptions['is_support_account'] = TRUE;
			$supportAccount->save();
			
			\IPS\Db::i()->insert( 'core_acp_notifications', array(
				'app'		=> 'core',
				'ext'		=> 'ConfigurationError',
				'sent'		=> time(),
				'extra'		=> "supportAdmin-{$supportAccount->member_id}",
			) );
		}


		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Updating AdminCP notifictions";
	}

	/**
	 * Update profile completion
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		foreach ( \IPS\Db::i()->select( '*', 'core_profile_steps', array( "step_extension=? and step_completion_act=?", "core_Core", "basic_profile" ) ) as $row )
		{
			$subActions = json_decode( $row['step_subcompletion_act'] );
			$newActions = array_intersect( $subActions, array( 'photo', 'cover_photo') );

			if( !\count( $newActions ) )
			{
				continue;
			}

			/* Add a new step */
			$newStep = array(
				'step_extension'      		=> 'core_Photo',
				'step_completion_act'   	=> 'profile_photo',
				'step_required' 			=> $row['step_required'],
				'step_subcompletion_act' 	=> json_encode( array_values( $newActions ) ),
			);

			$newId = \IPS\Db::i()->insert( 'core_profile_steps', $newStep );

			/* Add the title */
			$defaultLanguageId = \IPS\Lang::defaultLanguage();
			\IPS\Lang::saveCustom( 'core', "profile_step_title_{$newId}", \IPS\Lang::load( $defaultLanguageId )->get( "complete_profile_photo" ) );

			/* Update the old step */
			\IPS\Db::i()->update( 'core_profile_steps', array( 'step_subcompletion_act' => json_encode( array_values( array_diff( $subActions, $newActions ) ) ) ), array( "step_id=?", $row['step_id'] ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Updating profile completion";
	}
	
	/**
	 * Step 5
	 * Remove orphaned status update attachment maps for the cleanup task to take care of later
	 *
	 * @return	bool
	 */
	public function step5()
	{
		\IPS\Db::i()->delete( 'core_attachments_map', array( "location_key=? AND id1 NOT IN(?)", 'core_Members', \IPS\Db::i()->select( 'status_id', 'core_member_status_updates' ) ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Removing orphaned status update attachments";
	}

	/**
	 * Step 6
	 * Update status update attachment mappings
	 *
	 * @return	bool
	 */
	public function step6()
	{
		/* Init */
		$perCycle	= 250;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		/* Loop */
		foreach( \IPS\Db::i()->select( '*', 'core_attachments_map', array( 'location_key=?', 'core_Members' ), 'attachment_id', array( $limit, $perCycle ) ) as $row )
		{
			/* Timeout? */
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			/* Step up */
			$did++;

			try
			{
				$status = \IPS\core\Statuses\Status::load( $row['id1'] );
				\IPS\Db::i()->update( 'core_attachments_map', array( 'id3' => $status->member_id ), array( 'attachment_id=?', $row['attachment_id'] ) );
			}
			catch ( \OutOfRangeException $e )
			{
				//The status post doesn't exist anymore, so we can just delete this attachment association
				\IPS\Db::i()->delete( 'core_attachments_map', array( "attachment_id=?", $row['attachment_id'] ) );
			}
		}

		/* Did we do anything? */
		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step6CustomTitle()
	{
		return "Updating existing status update attachment mappings";
	}

	/**
	 * Save favicon and share images
	 *
	 * @return string
	 */
	public function step7()
	{
		$defaultTheme	= \IPS\Theme::load( \IPS\Theme::defaultTheme() );

		$logo		= json_decode( $defaultTheme->logo_data, true );
		$settings	= array();

		if( isset( $logo['favicon']['url'] ) AND $logo['favicon']['url'] )
		{
			$settings['icons_favicon']	= $logo['favicon']['url'];
		}

		if( isset( $logo['sharer']['url'] ) AND $logo['sharer']['url'] )
		{
			$settings['icons_sharer_logo'] = json_encode( array( $logo['sharer']['url'] ) );
		}

		if( \count( $settings ) )
		{
			/* The settings are new in this version so they won't exist to update yet */
			\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_app' => 'core', 'conf_key' => 'icons_favicon', 'conf_value' => $settings['icons_favicon'] ) );
			\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_app' => 'core', 'conf_key' => 'icons_sharer_logo', 'conf_value' => $settings['icons_sharer_logo'] ) );
		}

		/* And then we need to point the Icons & Logos file storage configuration to the same location as Theme Resources */
		$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
		$settings[ "filestorage__core_Icons" ] = $settings[ "filestorage__core_Theme" ];
		\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $settings ) ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Saving favicon and share images";
	}
	
	/**
	 * Only truncate search index if ! CiC
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		/* Discussed with Charles, and CIC accounts can run these queries fast and it takes more resources to rebuild the index afterwards en-masse */
		if ( ! \IPS\CIC )
		{
			\IPS\Db::i()->delete( 'core_search_index' );
		}
		
		$json = <<<EOF
[
    {
        "method": "addIndex",
        "params": [
            "core_search_index",
            {
                "type": "key",
                "name": "index_stream",
                "columns": [
                    "index_class",
                    "index_item_id",
                    "index_date_commented",
                    "index_date_updated"
                ],
                "length": [
                    null,
                    null
                ]
            }
        ]
    }
]
EOF;

		$queries = json_decode( $json, TRUE );
		
		foreach( $queries as $query )
		{
			try
			{
				$run = \call_user_func_array( array( \IPS\Db::i(), $query['method'] ), $query['params'] );
			}
			catch( \IPS\Db\Exception $e )
			{
				/* Deal with a MySQL bug - InnoDB presently supports one FULLTEXT index creation at a time */
				if( $e->getCode() === 1795 AND !\IPS\CIC )
				{
					/* Drop the existing FULLTEXT indexes, Database checker will re-add them and not trigger the bug */
					\IPS\Db::i()->dropIndex( 'core_search_index', array( 'index_content', 'index_title' ) );

					/* Run the original query */
					try
					{
						\call_user_func_array( array( \IPS\Db::i(), $query['method'] ), $query['params'] );
					}
					catch( \IPS\Db\Exception $e )
					{
						if ( !\in_array( $e->getCode(), array(1007, 1008, 1050, 1060, 1061, 1062, 1091, 1051) ) )
						{
							throw $e;
						}
					}
					continue;
				}

				if( !\in_array( $e->getCode(), array( 1007, 1008, 1050, 1060, 1061, 1062, 1091, 1051 ) ) )
				{
					throw $e;
				}
			}
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
        return "Optimizing search index";
    }
    
    /**
	 * Make sure only one login handler has force name/email sync set up
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		$set = array( 'name' => FALSE, 'email' => FALSE );
		$areDupes = FALSE;
		
		foreach ( \IPS\Db::i()->select( '*', 'core_login_methods', NULL, 'login_order' ) as $row )
		{		
			if ( $settings = json_decode( $row['login_settings'], TRUE ) )
			{
				$needsUpdating = FALSE;
				
				foreach ( array( 'name', 'email' ) as $type )
				{
					if ( isset( $settings["update_{$type}_changes"] ) and $settings["update_{$type}_changes"] === 'force' )
					{					
						if ( $set[ $type ] )
						{
							$areDupes = TRUE;
							$needsUpdating = TRUE;
							$settings["update_{$type}_changes"] = 'optional';
						}
						else
						{
							$set[ $type ] = TRUE;
						}
					}
				}
				
				if ( $needsUpdating )
				{
					\IPS\Db::i()->update( 'core_login_methods', array( 'login_settings' => json_encode( $settings ) ), array( 'login_id=?', $row['login_id'] ) );
				}
			}
		}
		
		if ( $areDupes )
		{
			unset( \IPS\Data\Store::i()->loginMethods );
		}
		
		return TRUE;
	}
	
	/**
     * Custom title for this step
     *
     * @return string
     */
    public function step9CustomTitle()
    {
        return "Updating login handlers";
    }

	/**
	 * Recount Club Members
	 *
	 * @return bool
	 */
    public function step10()
	{
		foreach( \IPS\Member\Club::clubs( NULL, NULL, 'created' ) as $club )
		{
			$club->recountMembers();
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
		return "Rebuilding club member counts";
	}

	/**
	 * Remove Google+ custom site link
	 *
	 * @return bool
	 */
    public function step11()
	{
		if( \IPS\Settings::i()->site_social_profiles AND $links = json_decode( \IPS\Settings::i()->site_social_profiles, TRUE ) AND \count( $links ) )
		{
			$needsUpdating	= FALSE;
			$newLinks		= array();

			/* Loop over the links...if we detect google, set the flag that we need to update the site links array */
			foreach( $links as $link )
			{
				if( mb_strpos( $link['value'], 'google' ) === FALSE )
				{
					$newLinks[] = $link;
				}
				else
				{
					$needsUpdating = TRUE;
				}
			}

			if( $needsUpdating === TRUE )
			{
				\IPS\Settings::i()->changeValues( array( 'site_social_profiles' => json_encode( $newLinks ) ) );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step11CustomTitle()
	{
		return "Checking for and removing Google+ site link";
	}

	/**
	 * Adjust members table - combined here into one query, vs the individual queries that queries.json prefers
	 *
	 * @return bool
	 */
    public function step12()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( 
			array(
				'table' => 'core_members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members ADD COLUMN completed BIT NOT NULL DEFAULT 0 COMMENT 'Whether the account is completed or not',
				ADD INDEX completed (completed, temp_ban),
				ADD INDEX profilesync (profilesync_lastsync, profilesync(150))"
			),
			array(
				'table' => 'core_members',
				'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_members SET completed=1 WHERE name != '' and name IS NOT NULL and email != '' and email IS NOT NULL"
			)
		 ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 13 ) ) );

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
	public function step12CustomTitle()
	{
		return "Adjusting members table";
	}
	
	/**
	 * Store a bg task to remove existing letter photos and rebuild reputation
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* Remove letter photos */
		\IPS\Task::queue( 'core', 'RemoveLetterPhotos', array(), 5 );

		/* Remove orphaned reputation */
		$classes = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			$classes = array_merge( $object->classes, $classes );
		}

		foreach( $classes as $item )
		{
			try
			{
				$commentClass = NULL;
				$reviewClass  = NULL;

				if ( isset( $item::$commentClass ) )
				{
					$commentClass = $item::$commentClass;
				}

				if ( isset( $item::$reviewClass ) )
				{
					$reviewClass = $item::$reviewClass;
				}

				if ( $commentClass and \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RemoveOrphanedReputation', array( 'class' => $commentClass ), 3, array( 'class' ) );
				}

				if ( $reviewClass and \IPS\IPS::classUsesTrait( $reviewClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RemoveOrphanedReputation', array( 'class' => $reviewClass ), 3, array( 'class' ) );
				}
			}
			catch( \Exception $e ) { }
		}

		/* Revert back to MySQL search if their Elasticsearch version is outdated */
		if ( isset( $_SESSION['upgrade_options']['core']['104000']['es_version'] ) AND $_SESSION['upgrade_options']['core']['104000']['es_version'] )
		{
			\IPS\Settings::i()->changeValues( array( 'search_method' => 'mysql' ) );

			\IPS\Content\Search\Index::i()->rebuild();
		}
		
		if( ! \IPS\CIC AND isset( \IPS\Settings::i()->search_method ) AND \IPS\Settings::i()->search_method == 'mysql' )
		{
			\IPS\Content\Search\Index::i()->rebuild();
		}

		return TRUE;
	}
}