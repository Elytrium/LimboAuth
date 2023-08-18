<?php
/**
 * @brief		Admin CP Member Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Apr 2013
 */

namespace IPS\core\extensions\core\MemberForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Member Form
 */
class _Preferences
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member				$member	Existing Member
	 * @return	void
	 */
	public function process( &$form, $member )
	{
		$form->addHeader('member_preferences_system');

		/* Language */
		$languages = array( 0 => 'language_none' );
		foreach ( \IPS\Lang::languages() as $lang )
		{
			$languages[ $lang->id ] = $lang->title;
		}
		$form->add( new \IPS\Helpers\Form\Select( 'language', $member->language, TRUE, array( 'options' => $languages ) ) );

		if( $member->isAdmin() )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'acp_language', $member->acp_language, TRUE, array( 'options' => $languages ) ) );
		}
		
		/* Skin */
		$themes = array();
		foreach( \IPS\Theme::themes() as $theme )
		{
			$themes[ $theme->id ] = $theme->_title;
		}
		$themes[0] = 'skin_none';
		
		$form->add( new \IPS\Helpers\Form\Select( 'skin', ( $member->skin ) ? $member->skin : 0, TRUE, array( 'options' => $themes ) ) );

		/* Content */
		$form->addHeader('member_preferences_content');
		$form->add( new \IPS\Helpers\Form\YesNo( 'view_sigs', $member->members_bitoptions['view_sigs'], FALSE ) );
		
		/* Profile */
		$form->addHeader('member_preferences_profile');
		$form->add( new \IPS\Helpers\Form\YesNo( 'pp_setting_count_visitors', $member->members_bitoptions['pp_setting_count_visitors'], FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'pp_setting_moderate_followers', !$member->members_bitoptions['pp_setting_moderate_followers'] ) );

		/* Link behavior */
		$form->add( new \IPS\Helpers\Form\Radio( 'link_pref', $member->linkPref() ?: \IPS\Settings::i()->link_default, FALSE, array( 'options' => array(
			'unread'	=> 'profile_settings_cvb_unread',
			'last'	=> 'profile_settings_cvb_last',
			'first'	=> 'profile_settings_cvb_first'
		) ) ) );
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member			$member	The member
	 * @return	void
	 */
	public function save( $values, &$member )
	{
		/* Language and Theme */
		$member->language = $values['language'];		
		$member->skin = ( $values['skin'] ) ? $values['skin'] : NULL;
		if( $member->isAdmin() )
		{
			$member->acp_language = $values['acp_language'];
		}

		/* Link Behavior */
		switch( $values['link_pref'] )
		{
			case 'last':
				$member->members_bitoptions['link_pref_unread'] = FALSE;
				$member->members_bitoptions['link_pref_last'] = TRUE;
				break;
			case 'unread':
				$member->members_bitoptions['link_pref_unread'] = TRUE;
				$member->members_bitoptions['link_pref_last'] = FALSE;
				break;
			default:
				$member->members_bitoptions['link_pref_unread'] = FALSE;
				$member->members_bitoptions['link_pref_last'] = FALSE;
				break;
		}
			
		/* Other */
		$member->members_bitoptions['view_sigs'] = $values['view_sigs'];
		$member->members_bitoptions['pp_setting_count_visitors']		= $values['pp_setting_count_visitors'];
		$member->members_bitoptions['pp_setting_moderate_followers']	= $values['pp_setting_moderate_followers'] ? FALSE : TRUE;		
	}
}