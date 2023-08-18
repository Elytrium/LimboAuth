<?php
/**
 * @brief		Profile Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		08 Jan 2018
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile Settings
 */
class _profilesettings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'profiles_manage' );

		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_members.js', 'core' ) );
		
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		$form = new \IPS\Helpers\Form;

		$form->addHeader( 'photos' );
		$form->add( new \IPS\Helpers\Form\Radio( 'letter_photos', \IPS\Settings::i()->letter_photos, FALSE, array( 'options' => array( 'default' => 'letterphoto_default', 'letters' => 'letterphoto_letters' ) ) ) );

		$form->addHeader( 'usernames' );
		$form->add( new \IPS\Helpers\Form\Custom( 'user_name_length', array( \IPS\Settings::i()->min_user_name_length, \IPS\Settings::i()->max_user_name_length ), FALSE, array(
			'getHtml'	=> function( $field ) {
				return \IPS\Theme::i()->getTemplate('members')->usernameLengthSetting( $field->name, $field->value );
			}
		),
		function( $val )
		{
			if ( $val[0] < 1 )
			{
				throw new \DomainException('user_name_length_too_low');
			}
			if ( $val[1] > 255 )
			{
				throw new \DomainException('user_name_length_too_high');
			}
			if ( $val[0] > $val[1] )
			{
				throw new \DomainException('user_name_length_no_match');
			}
		} ) );

		$form->add( new \IPS\Helpers\Form\Custom( 'username_characters', \IPS\Settings::i()->username_characters, FALSE, array(
			'getHtml'	=> function( $field ) {
				$easy = NULL;
				if ( \is_array( $field->value ) ) 
				{
					$easy['letters'] = $field->value['letters'];
					$easy['numbers'] = $field->value['numbers'];
					$easy['spaces'] = (bool) isset( $field->value['spaces'] );
					$easy['extra'] = isset( $field->value['extra_enabled'] ) ? $field->value['extra'] : '';
					$field->value = $field->value['regex'];
				}
				else{				
					if ( preg_match( '/^\/\^\(\(\[(\\\p\{L\}\\\p\{M\}|A\-Za\-z)(\\\p\{N\}|0-9)?(.+)?\]\+\)( )?\?\)\+\$\/u$/', $field->value, $matches ) )
					{
						$easy['letters'] = ( $matches[1] == '\\p{L}\\p{M}' ) ? 'all' : 'latin';
						if ( $matches[2] )
						{
							$easy['numbers'] = ( $matches[2] == '\\p{N}' ) ? 'all' : 'arabic';
						}
						else
						{
							$easy['numbers'] = 'none';
						}
						$easy['spaces'] = (bool) isset( $matches[4] );
						$easy['extra'] = stripslashes( $matches[3] );
					}
				}
				return \IPS\Theme::i()->getTemplate('members')->usernameRegexSetting( $field->name, $field->value, $easy );
			}
		) ) );
		$form->addHeader( 'signatures' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'signatures_enabled', \IPS\Settings::i()->signatures_enabled,  FALSE, array( 'togglesOn' => array( 'signatures_mobile', 'signatures_guests' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'signatures_mobile', \IPS\Settings::i()->signatures_mobile,  FALSE, array(), NULL, NULL, NULL, 'signatures_mobile' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'signatures_guests', \IPS\Settings::i()->signatures_guests,  FALSE, array(), NULL, NULL, NULL, 'signatures_guests' ) );

		/* Status updates */
		$options = array(
			0	=> 'status_updates_disabled',
			1	=> 'status_updates_enabled_nomem',
			2	=> 'status_updates_enabled_mem'
		);

		$default = \IPS\Settings::i()->profile_comments ? ( \IPS\Settings::i()->status_updates_mem_enable ? 2 : 1 ) : 0;

		$form->addHeader( 'statuses_profile_comments' );
		$form->add( new \IPS\Helpers\Form\Radio( 'profile_comments', $default, FALSE, array( 'options' => $options, 'toggles' => array( 1 => array( 'profile_comment_approval' ), 2 => array( 'profile_comment_approval' ) ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'profile_comment_approval', \IPS\Settings::i()->profile_comment_approval, FALSE, array(), NULL, NULL, NULL, 'profile_comment_approval' ) );

		$form->addHeader( 'profile_settings_birthdays' );
		$form->add( new \IPS\Helpers\Form\Radio( 'profile_birthday_type', \IPS\Settings::i()->profile_birthday_type, TRUE, array(
			'options'	=> array( 'public' => 'profile_birthday_type_public', 'private' => 'profile_birthday_type_private', 'none' => 'profile_birthday_type_none' )
		), NULL, NULL, NULL, 'profile_birthday_type' ) );

		if( !\IPS\CIC AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'member_history_prune' ) )
		{
			$form->addHeader( 'profile_member_history' );
			$form->add( new \IPS\Helpers\Form\Interval( 'prune_member_history', \IPS\Settings::i()->prune_member_history, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_member_history' ) );

			$form->add( new \IPS\Helpers\Form\Interval( 'prune_known_ips', \IPS\Settings::i()->prune_known_ips, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), function( $val ) {
				if( $val > 0 AND $val < 7 )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_interval_min_d', FALSE, array( 'pluralize' => array( 6 ) ) ) );
				}
			}, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_known_ips' ) );

			$form->add( new \IPS\Helpers\Form\Interval( 'prune_known_devices', \IPS\Settings::i()->prune_known_devices, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), function( $val ) {
				if( $val > 0 AND $val < 30 )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_interval_min_d', FALSE, array( 'pluralize' => array( 29 ) ) ) );
				}
			}, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_known_devices' ) );

			$form->add( new \IPS\Helpers\Form\Interval( 'prune_item_markers', \IPS\Settings::i()->prune_item_markers, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), function( $val ) {
				if( $val > 0 AND $val < 7 )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_interval_min_d', FALSE, array( 'pluralize' => array( 6 ) ) ) );
				}
			}, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_item_markers' ) );
		}

		$form->addHeader( 'profile_settings_pms' );
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_pms', \IPS\Settings::i()->prune_pms, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_pms' ) );

		$form->addHeader( 'profile_settings_ignore' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ignore_system_on', \IPS\Settings::i()->ignore_system_on, FALSE, array(), NULL, NULL, NULL, 'ignore_system_on' ) );

		$form->addHeader( 'profile_settings_display' );
		$form->add( new \IPS\Helpers\Form\Radio( 'group_formatting_type', \IPS\Settings::i()->group_formatting, FALSE, array( 'options' => array(
			'legacy'	=> 'group_formatting_type_legacy',
			'global'	=> 'group_formatting_type_global'
		) ) ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'link_default', \IPS\Settings::i()->link_default, FALSE, array( 'options' => array(
			'unread'	=> 'profile_settings_cvb_unread',
			'last'	=> 'profile_settings_cvb_last',
			'first'	=> 'profile_settings_cvb_first'
		) ) ) );

		if ( $values = $form->values() )
		{
			if ( $values['username_characters']['easy'] )
			{
				$regex = '/^(([';
				if ( $values['username_characters']['letters'] == 'all' )
				{
					$regex .= '\p{L}\p{M}';
				}
				else
				{
					$regex .= 'A-Za-z';
				}
				if ( $values['username_characters']['numbers'] == 'all' )
				{
					$regex .= '\p{N}';
				}
				elseif ( $values['username_characters']['numbers'] == 'arabic' )
				{
					$regex .= '0-9';
				}
				if ( isset( $values['username_characters']['extra_enabled'] ) )
				{
					$regex .= preg_quote( $values['username_characters']['extra'], '/' );
				}
				$regex .= ']+)';
				if ( isset( $values['username_characters']['spaces'] ) )
				{
					$regex .= ' ';
				}
				$regex .= '?)+$/u';
				
				$values['username_characters'] = $regex;
			}
			else
			{
				$values['username_characters'] = $values['username_characters']['regex'];
			}
			
			$values['group_formatting'] = $values['group_formatting_type'];
			unset( $values['group_formatting_type'] );
			
			$values['min_user_name_length'] = $values['user_name_length'][0];
			$values['max_user_name_length'] = $values['user_name_length'][1];
			unset( $values['user_name_length'] );

			$values['status_updates_mem_enable']	= ( $values['profile_comments'] == 2 ) ? 1 : 0;
			$values['profile_comments']				= ( $values['profile_comments'] > 0 ) ? 1 : 0;

			/* If we're enabling pruning on a potentially large table, handle that */
			if( !\IPS\Settings::i()->prune_member_history AND $values['prune_member_history'] )
			{
				\IPS\Task::queue( 'core', 'PruneLargeTable', array(
					'table'			=> 'core_member_history',
					'where'			=> array( 'log_date < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_member_history'] . 'D' ) )->getTimestamp() ),
					'setting'		=> 'prune_member_history',
				), 4 );
			}

			if( !\IPS\Settings::i()->prune_known_ips AND $values['prune_known_ips'] )
			{
				\IPS\Task::queue( 'core', 'PruneLargeTable', array(
					'table'			=> 'core_members_known_ip_addresses',
					'where'			=> array( 'last_seen < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_known_ips'] . 'D' ) )->getTimestamp() ),
					'setting'		=> 'prune_known_ips',
				), 4 );
			}

			if( !\IPS\Settings::i()->prune_known_devices AND $values['prune_known_devices'] )
			{
				\IPS\Task::queue( 'core', 'PruneLargeTable', array(
					'table'			=> 'core_members_known_devices',
					'where'			=> array( 'last_seen < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_known_devices'] . 'D' ) )->getTimestamp() ),
					'setting'		=> 'prune_known_devices',
				), 4 );
			}

			if( !\IPS\Settings::i()->prune_item_markers AND $values['prune_item_markers'] )
			{
				\IPS\Task::queue( 'core', 'PruneLargeTable', array(
					'table'			=> 'core_item_markers',
					'where'			=> array( 'item_member_id IN(?)', \IPS\Db::i()->select( 'member_id', 'core_members', array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_item_markers'] . 'D' ) )->getTimestamp() ) ) ),
					'setting'		=> 'prune_item_markers',
					'deleteJoin'	=> array(
						'column'		=> 'member_id',
						'table'			=> 'core_members',
						'where'			=> array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_item_markers'] . 'D' ) )->getTimestamp() ),
						'outerColumn'	=> 'item_member_id'
					)
				), 4 );
			}
		
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__profile_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&tab=profilesettings' ), 'saved' );
		}
		
		\IPS\Output::i()->output = (string) $form;
	}
}