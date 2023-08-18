<?php
/**
 * @brief		4.3.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Oct 2017
 */

namespace IPS\core\setup\upg_103000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Move login handlers
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\Db::i()->select( '*', 'core_login_handlers' ) as $row )
		{
			$settings = ( isset( $row['login_settings'] ) and $row['login_settings'] ) ? json_decode( $row['login_settings'], TRUE ) : array();

			$title = $row['login_key'];
			switch ( mb_strtolower( $row['login_key'] ) )
			{
				case 'internal':
					$classname = 'IPS\\Login\\Handler\\Standard';
					$title = "Standard";
					/* This may not be set for some communities - check that it's valid */
					if( !isset( $settings['auth_types'] ) )
					{
						$settings['auth_types'] = \IPS\Login::AUTH_TYPE_EMAIL;
					}
					/* < 4.3 didn't allow this handler to be disabled, make sure that it is enabled (some communities may have worked around this limitation) */
					$row['login_acp'] = TRUE;
					$row['login_enabled'] = TRUE;
					break;
				case 'facebook':
					$classname = 'IPS\\Login\\Handler\\OAuth2\\Facebook';
					$settings['client_id'] = isset( $settings['app_id'] ) ? $settings['app_id'] : NULL;
					$settings['client_secret'] = isset( $settings['app_secret'] ) ? $settings['app_secret'] : NULL;
					$settings['legacy_redirect'] = TRUE;
					$title = "Facebook";
					break;
				case 'google':
					$classname = 'IPS\\Login\\Handler\\OAuth2\\Google';
					$settings['legacy_redirect'] = TRUE;
					$title = "Google";
					break;
				case 'linkedin':
					$classname = 'IPS\\Login\\Handler\\OAuth2\\LinkedIn';
					$settings['client_id'] = isset( $settings['api_key'] ) ? $settings['api_key'] : NULL;
					$settings['client_secret'] = isset( $settings['secret_key'] ) ? $settings['secret_key'] : NULL;
					$settings['legacy_redirect'] = TRUE;
					$title = "LinkedIn";
					break;
				case 'live':
				case 'microsoft':
					$classname = 'IPS\\Login\\Handler\\OAuth2\\Microsoft';
					$settings['legacy_redirect'] = TRUE;
					$title = "Microsoft";
					break;
				case 'twitter':
					$classname = 'IPS\\Login\\Handler\\OAuth1\\Twitter';
					$title = "Twitter";
					break;
				case 'external':
					$classname = 'IPS\\Login\\Handler\\ExternalDatabase';
					$title = "MySQL Database";
					break;
				case 'ldap':
					$classname = 'IPS\\Login\\Handler\\LDAP';
					$title = "LDAP";
					break;
				case 'ipsconnect':
					$classname = 'IPS\\Login\\Handler\\OAuth2\\Invision';
					$title = "Invision Community";
					$oldsettings = $settings;
					$settings = array();
					$settings['url'] = $_SESSION['upgrade_options']['core']['103000']['ipsconnect_url'];
					$settings['grant_type'] = 'password';
					$settings['client_id'] = $_SESSION['upgrade_options']['core']['103000']['ipsconnect_client_id'];
					$settings['client_secret'] = $_SESSION['upgrade_options']['core']['103000']['ipsconnect_client_secret'];
					$settings['auth_types'] = $oldsettings['auth_types'];
					$settings['button_color'] = '#3e4148';
					$settings['button_text'] = NULL;
					$settings['button_icon'] = '';
					$settings['show_in_ucp'] = 'loggedin';
					$settings['update_name_changes'] = ( \defined( '\IPS\CONNECT_NOSYNC_NAMES' ) and \IPS\CONNECT_NOSYNC_NAMES ) ? 'disabled' : 'force';
					$settings['update_email_changes'] = 'force';
					break;
				case 'convert':
					continue 2;
					break;
				default:
					$classname = 'IPS\\Login\\' . $row['login_key'];
					break;
			}

			$id = \IPS\Db::i()->insert( 'core_login_methods', array(
				'login_classname'	=> $classname,
				'login_order'		=> $row['login_order'],
				'login_acp'			=> $row['login_acp'],
				'login_settings'	=> json_encode( $settings ),
				'login_enabled'		=> $row['login_enabled'],
				/* If we are coming from 3.x the setting will not exist yet, so we should default to 1. There was previously a no_reg setting in 3.x,
					however it is removed during 40000/step21 so we can't check it. */
				'login_register'	=> ( isset( \IPS\Settings::i()->allow_reg ) ) ? \IPS\Settings::i()->allow_reg : 1
			) );

			\IPS\Lang::saveCustom( 'core', "login_method_{$id}", $title );

			\IPS\Db::i()->update( 'core_members_known_devices', array( 'login_handler' => $id ), array( 'login_handler=?', $row['login_key'] ) );
			\IPS\Db::i()->update( 'core_profile_steps', array( 'step_subcompletion_act' => $id ), array( 'step_completion_act=? and step_subcompletion_act=?', 'social_login', $row['login_key'] ) );
		}

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return 'Updating login handlers';
	}

	/**
	 * Step 2
	 * Update Login IDs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$loginMethods = iterator_to_array( \IPS\Db::i()->select( '*', 'core_login_methods' )->setKeyField('login_classname') );

		/* Some init */
		$did		= 0;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit = \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff = \IPS\core\Setup\Upgrade::determineCutoff();

		$where = array();
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'fb_uid' ) )
		{
			$where[] = 'fb_uid>0';
		}
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'live_id' ) )
		{
			$where[] = 'live_id IS NOT NULL';
		}
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'twitter_id' ) )
		{
			$where[] = "twitter_id<>''";
		}
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'google_id' ) )
		{
			$where[] = 'google_id IS NOT NULL';
		}
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'linkedin_id' ) )
		{
			$where[] = 'linkedin_id IS NOT NULL';
		}
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'ipsconnect_id' ) )
		{
			$where[] = 'ipsconnect_id>0';
		}
		if ( $where )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_members', implode( ' OR ', $where ), 'member_id ASC', array( $limit, 500 ) ) as $member )
			{
				if( $cutOff !== null AND time() >= $cutOff )
				{
					return ( $limit + $did );
				}

				$did++;

				if ( isset( $member['fb_uid'] ) and $member['fb_uid'] and isset( $loginMethods['IPS\\Login\\Handler\\OAuth2\\Facebook'] ) )
				{
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $loginMethods['IPS\\Login\\Handler\\OAuth2\\Facebook']['login_id'],
						'token_member'			=> $member['member_id'],
						'token_identifier'		=> $member['fb_uid'],
						'token_linked'			=> TRUE,
						'token_access_token'	=> $member['fb_token'],
					), FALSE, TRUE );
				}
				if ( isset( $member['live_id'] ) and $member['live_id'] and isset( $loginMethods['IPS\\Login\\Handler\\OAuth2\\Microsoft'] ) )
				{
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $loginMethods['IPS\\Login\\Handler\\OAuth2\\Microsoft']['login_id'],
						'token_member'			=> $member['member_id'],
						'token_identifier'		=> $member['live_id'],
						'token_linked'			=> TRUE,
						'token_access_token'	=> $member['live_token'],
					), FALSE, TRUE );
				}
				if ( isset( $member['twitter_id'] ) and $member['twitter_id'] and isset( $loginMethods['IPS\\Login\\Handler\\OAuth1\\Twitter'] ) )
				{
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $loginMethods['IPS\\Login\\Handler\\OAuth1\\Twitter']['login_id'],
						'token_member'			=> $member['member_id'],
						'token_identifier'		=> $member['twitter_id'],
						'token_linked'			=> TRUE,
						'token_access_token'	=> $member['twitter_token'],
						'token_secret'			=> $member['twitter_secret'],
					), FALSE, TRUE );
				}
				if ( isset( $member['google_id'] ) and $member['google_id'] and isset( $loginMethods['IPS\\Login\\Handler\\OAuth2\\Google'] ) )
				{
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $loginMethods['IPS\\Login\\Handler\\OAuth2\\Google']['login_id'],
						'token_member'			=> $member['member_id'],
						'token_identifier'		=> $member['google_id'],
						'token_linked'			=> TRUE,
						'token_access_token'	=> $member['google_token'],
					), FALSE, TRUE );
				}
				if ( isset( $member['linkedin_id'] ) and $member['linkedin_id'] and isset( $loginMethods['IPS\\Login\\Handler\\OAuth2\\LinkedIn'] ) )
				{
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $loginMethods['IPS\\Login\\Handler\\OAuth2\\LinkedIn']['login_id'],
						'token_member'			=> $member['member_id'],
						'token_identifier'		=> $member['linkedin_id'],
						'token_linked'			=> TRUE,
						'token_access_token'	=> $member['linkedin_token'],
					), FALSE, TRUE );
				}
				if ( isset( $member['ipsconnect_id'] ) and $member['ipsconnect_id'] and isset( $loginMethods['IPS\\Login\\Handler\\OAuth2\\Invision'] ) )
				{
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $loginMethods['IPS\\Login\\Handler\\OAuth2\\Invision']['login_id'],
						'token_member'			=> $member['member_id'],
						'token_identifier'		=> $member['ipsconnect_id'],
						'token_linked'			=> TRUE,
					), FALSE, TRUE );
				}
			}
		}

		if( $did )
		{
			return $limit + $did;
		}
		else
		{
			unset( $_SESSION['_step2Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step2CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step2Count'] ) )
		{
			$where = array();
			if ( \IPS\Db::i()->checkForColumn( 'core_members', 'fb_uid' ) )
			{
				$where[] = 'fb_uid>0';
			}
			if ( \IPS\Db::i()->checkForColumn( 'core_members', 'live_id' ) )
			{
				$where[] = 'live_id IS NOT NULL';
			}
			if ( \IPS\Db::i()->checkForColumn( 'core_members', 'twitter_id' ) )
			{
				$where[] = "twitter_id<>''";
			}
			if ( \IPS\Db::i()->checkForColumn( 'core_members', 'google_id' ) )
			{
				$where[] = 'google_id IS NOT NULL';
			}
			if ( \IPS\Db::i()->checkForColumn( 'core_members', 'linkedin_id' ) )
			{
				$where[] = 'linkedin_id IS NOT NULL';
			}
			if ( $where )
			{
				$_SESSION['_step2Count']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_members', implode( ' OR ', $where ) )->first();
			}
			else
			{
				$_SESSION['_step2Count']	= 0;
			}
		}

		return 'Converting login handlers for 4.3 (Fixed so far: ' . ( ( $limit > $_SESSION['_step2Count'] ) ? $_SESSION['_step2Count'] : $limit ) . ' out of ' . $_SESSION['_step2Count'] . ')';
	}

	/**
	 * Step 3
	 * Adjust the members table
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$statements	= array( 
			'DROP COLUMN ipsconnect_id', 
			'DROP COLUMN ipsconnect_revalidate_url',
			'CHANGE members_pass_hash members_pass_hash VARCHAR(255) NULL DEFAULT NULL',
			'ADD INDEX last_activity (last_activity)'
		);

		foreach ( array('fb_uid','fb_token','live_id','live_token','twitter_id','twitter_token','twitter_secret','tc_last_sid_import','tc_photo','tc_bwoptions','fb_photo','fb_photo_thumb','fb_bwoptions','google_id','google_token','linkedin_id','linkedin_token') as $col )
		{
			if ( \IPS\Db::i()->checkForColumn( 'core_members', $col ) )
			{
				$statements[] = 'DROP COLUMN ' . $col;
			}
		}

		$toRunQueries	= array( array(
			'table' => 'core_members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members " . implode( ', ', $statements )
		) );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 4 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		return 'Adjusting members table';
	}

	/**
	 * Populate spam defense whitelist with installed domain
	 *
	 * @return	boolean
	 */
	public function step4()
	{
		$domain = rtrim( str_replace( 'www.', '', parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ) ), '/' );
		\IPS\Db::i()->insert( 'core_spam_whitelist', array( 'whitelist_type' => 'domain', 'whitelist_content' => $domain, 'whitelist_date' => time(), 'whitelist_reason' => 'Install Domain' ) );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step4CustomTitle()
	{
		return 'Setting defaults for spam service email address whitelist';
	}

	/**
	 * Step 5
	 * Update Email Settings if Sparkpost is used
	 *
	 * @return bool
	 */
	public function step5()
	{
		if ( \IPS\Settings::i()->mail_method === 'sparkpost' AND \IPS\Settings::i()->sparkpost_api_key AND \IPS\Settings::i()->sparkpost_use_for )
		{
			$newSettings = array(
				'mail_method' => 'smtp',
				'smtp_host' => 'smtp.sparkpostmail.com',
				'smtp_port' => '587',
				'smtp_protocol' => 'tls',
				'smtp_user' => 'SMTP_Injection',
				'smtp_pass' => \IPS\Settings::i()->sparkpost_api_key,
			);
			\IPS\Settings::i()->changeValues( $newSettings );
		}
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step5CustomTitle()
	{
		return 'Checking Sparkpost configuration';
	}
	
	/**
	 * Step 6
	 * Fix themes
	 *
	 * @return bool
	 */
	public function step6()
	{
		/* Get rid of any duplicates that will cause a unique key error */
		foreach( \IPS\Db::i()->select( '*', 'core_theme_templates', array( 'template_set_id=0 and template_added_to > 0' ) ) as $template )
		{
			try
			{
				/* See if we have a 'default' copy of this template yet */
				\IPS\Db::i()->select( 'template_id', 'core_theme_templates', array( 'template_set_id=? and template_group=? and template_name=? and template_app=? and template_location=?', $template['template_added_to'], $template['template_group'], $template['template_name'], $template['template_app'], $template['template_location'] ) )->first();

				/* If we're here, it means this template exists */
				\IPS\Db::i()->delete( 'core_theme_templates', array( 'template_id=?', $template['template_id'] ) );
			}
			/* We want an underflow exception - it means the template isn't present */
			catch( \UnderflowException $e ){}
		}

		/* Fix template_set_id=0 for non master */
		\IPS\Db::i()->update( 'core_theme_templates', 'template_set_id=template_added_to', array( 'template_set_id=0 and template_added_to > 0' ) );
		
		/* Set customized flag */
		foreach( \IPS\Db::i()->select( 'set_id', 'core_themes' ) as $theme )
		{
			\IPS\Theme::setThemeCustomized( $theme['set_id'] );
		}
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step6CustomTitle()
	{
		return 'Flagging customized theme templates';
	}

	/**
	 * Step 7
	 * Settings Changes
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		/* Usage reporting settings */
		\IPS\Db::i()->replace( 'core_sys_conf_settings', array( 
				'conf_key' => 'usage_reporting',
				'conf_value' => \intval( $_SESSION['upgrade_options']['core']['103000']['usage_reporting'] ),
				'conf_default' => 1,
				'conf_app' => 'core'
			) );
		
		/* reCAPTCHA 1 removed */
		if ( \IPS\Settings::i()->bot_antispam_type === 'recaptcha' )
		{
			\IPS\Settings::i()->changeValues( array(
				'bot_antispam_type'		=> 'invisible',
				'recaptcha2_public_key'	=> '6LcH7UEUAAAAAIGWgOoyBKAqjLmOIKzfJTOjyC7z',
				'recaptcha2_private_key'=> '6LcH7UEUAAAAANmcQmZErdGW2FXwVhtRBVXBWBUA',
			) );
		}
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'core', 'recaptcha2_public_key_desc' ) );
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'core', 'recaptcha_public_key' ) );
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'core', 'recaptcha_private_key' ) );
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'core', 'recaptcha_private_key_desc' ) );
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'core', 'captcha_type_recaptcha' ) );
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? AND word_key=?', 'core', 'captcha_type_recaptcha_desc' ) );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Updating CAPTCHA settings";
	}
	
	/**
	 * Step 8
	 * Update positions for profile steps
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		$position = 1;
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_profile_steps' ), 'IPS\Member\ProfileStep' ) AS $step )
		{
			$step->position = $position;
			$step->save();
			
			$position++;
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step8CustomTitle()
	{
		return 'Adjusting profile step configuration';
	}

	/**
	 * Step 9
	 * Remove announcement widgets
	 *
	 * @return bool
	 */
	public function step9()
	{
		foreach( \IPS\Db::i()->select( 'id, widgets', 'core_widget_areas', array( 'widgets LIKE(?)', '"key": "announcements"' ) )->setKeyField('id')->setValueField( 'widgets' ) as $id => $config )
		{
			$json = json_decode( $config, TRUE );

			foreach( $json as $key => $value )
			{
				if( $value['key'] == 'announcements' AND $value['app'] == 'core' )
				{
					unset( $json[ $key ] );
				}
			}

			\IPS\Db::i()->update( 'core_widget_areas', array( 'widgets' => json_encode( $json ) ), array( 'id=?', $id ) );
		}

		\IPS\Db::i()->delete( 'core_widgets', array( 'app=? AND `key`=?', 'core', 'announcements' ) );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step9CustomTitle()
	{
		return "Removing announcement widgets";
	}

	/**
	 * Step 10
	 * Remove orphaned known device data
	 *
	 * @return bool
	 */
	public function step10()
	{
		$queries = array();

		/* Orphaned devices */
		try
		{
			\IPS\Db::i()->select( 'count(*)', 'core_members_known_devices', array( 'member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ), NULL, 1 )->first();

			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array(
				'table' => 'core_members_known_devices',
				'query' => \IPS\Db::i()->delete( 'core_members_known_devices', array( 'member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ) )
			);
			\IPS\Db::i()->returnQuery = FALSE;
		}
		catch( \UnderflowException $e ) {}


		/* Orphaned ip addresses */
		try
		{
			\IPS\Db::i()->select( 'COUNT(*)', 'core_members_known_ip_addresses', array( 'member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ), NULL, 1 )->first();

			/* Remove the offending entries */
			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array(
				'table' => 'core_members_known_ip_addresses',
				'query' => \IPS\Db::i()->delete( 'core_members_known_ip_addresses', array( 'member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ) )
			);
			\IPS\Db::i()->returnQuery = FALSE;
		}
		catch( \UnderflowException $e ) {}

		/* Is this site affected by any of these issues? */
		if( !\count( $queries ) )
		{
			return TRUE;
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 11 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;

	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step10CustomTitle()
	{
		return "Removing orphaned member data";
	}
	
	/**
	 * Rebuild search index
	 *
	 * @return boolean
	 */
	public function finish()
	{
		\IPS\Content\Search\Index::i()->rebuild();
		return TRUE;
	}

}