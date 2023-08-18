<?php
/**
 * @brief		Group Form: Core: Social
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Mar 2013
 */

namespace IPS\core\extensions\core\GroupForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Group Form: Core: Social
 */
class _Social
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\Group		$group	Existing Group
	 * @return	void
	 */
	public function process( &$form, $group )
	{
		/* Profiles */
		if ( $group->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) )
		{
			$form->addHeader( 'group_profiles' );
			if ( $group->g_id != \IPS\Settings::i()->guest_group )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'g_edit_profile', $group->g_id ? $group->g_edit_profile : 1, FALSE, array( 'togglesOn' => array( 'gbw_allow_upload_bgimage', 'g_photo_max_vars_size', 'g_photo_max_vars_wh', 'g_upload_animated_photos' ) ) ) );
				$photos = ( $group->g_id ? explode( ':', $group->g_photo_max_vars ) : array( 50, 150, 150 ) );
				$form->add( new \IPS\Helpers\Form\Number( 'g_photo_max_vars_size', $photos[0], FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'g_photo_max_vars_none', 'unlimitedToggleOn' => FALSE, 'unlimitedToggles' => array( 'g_photo_max_vars_wh', 'g_upload_animated_photos' ) ), NULL, NULL, 'kB', 'g_photo_max_vars_size' ) );
				$form->add( new \IPS\Helpers\Form\Number( 'g_photo_max_vars_wh', $photos[1], FALSE, array(), NULL, NULL, 'px', 'g_photo_max_vars_wh' ) );
				$form->add( new \IPS\Helpers\Form\YesNo( 'g_upload_animated_photos', $group->g_id ? $group->g_upload_animated_photos : TRUE, FALSE, array(), NULL, NULL, NULL, 'g_upload_animated_photos' ) );
	
				$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_allow_upload_bgimage', $group->g_id ? ( $group->g_bitoptions['gbw_allow_upload_bgimage'] ) : TRUE, FALSE, array( 'togglesOn' => array( 'g_max_bgimg_upload' ) ), NULL, NULL, NULL, 'gbw_allow_upload_bgimage' ) );
				$form->add( new \IPS\Helpers\Form\Number( 'g_max_bgimg_upload', $group->g_id ? $group->g_max_bgimg_upload : -1, FALSE, array( 'unlimited' => -1 ), function( $value ) {
					if( !$value )
					{
						throw new \InvalidArgumentException('form_required');
					}
				}, NULL, 'kB', 'g_max_bgimg_upload' ) );
			}
			$form->add( new \IPS\Helpers\Form\YesNo( 'g_view_displaynamehistory' , $group->g_view_displaynamehistory, FALSE ) );
		}
	
		/* Personal Conversations */
		if ( $group->g_id != \IPS\Settings::i()->guest_group and $group->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging', 'front' ) ) )
		{
			$form->addHeader( 'personal_conversations' );
			$form->add( new \IPS\Helpers\Form\Number( 'g_pm_perday', $group->g_pm_perday, FALSE, array( 'unlimited' => -1, 'min' => 0 ), NULL, NULL, NULL, 'g_pm_perday' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'g_pm_flood_mins', $group->g_pm_flood_mins, FALSE, array( 'unlimited' => -1, 'min' => 0 ), NULL, NULL, NULL, 'g_pm_flood_mins' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'g_max_mass_pm', $group->g_max_mass_pm, FALSE, array( 'unlimited' => -1, 'max' => 500, 'min' => 0 ), NULL, NULL, NULL, 'g_max_mass_pm' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'g_max_messages', $group->g_max_messages, FALSE, array( 'unlimited' => -1, 'min' => 0 ), NULL, NULL, NULL, 'g_max_messages' ) );
			if ( \IPS\Settings::i()->attach_allowed_types != 'none' )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'g_can_msg_attach', $group->g_can_msg_attach, FALSE, array(), NULL, NULL, NULL, 'g_can_msg_attach' ) );
			}
			$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_pm_override_inbox_full', $group->g_id ? ( $group->g_bitoptions['gbw_pm_override_inbox_full'] ) : TRUE ) );
		}
		
		/* Column does not have a default value, so for a new group we have to explicitly set something */
		$group->g_club_allowed_nodes = $group->g_club_allowed_nodes ?: '';

		/* Clubs */
		if ( \IPS\Settings::i()->clubs and $group->g_id != \IPS\Settings::i()->guest_group and $group->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
		{
			$form->addHeader( 'module__core_clubs' );
			
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'g_create_clubs', explode( ',', $group->g_create_clubs ), FALSE, array(
				'options' => array(
					\IPS\Member\Club::TYPE_PUBLIC	=> 'club_type_public',
					\IPS\Member\Club::TYPE_OPEN		=> 'club_type_open',
					\IPS\Member\Club::TYPE_CLOSED	=> 'club_type_closed',
					\IPS\Member\Club::TYPE_PRIVATE	=> 'club_type_private',
					\IPS\Member\Club::TYPE_READONLY	=> 'club_type_readonly',
				),
			), NULL, NULL, NULL, 'g_create_clubs' ) );
			
			if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_paid_clubs', $group->g_id ? ( $group->g_bitoptions['gbw_paid_clubs'] ) : FALSE ) );
			}

			$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_club_manage_indexing', $group->g_id ? ( $group->g_bitoptions['gbw_club_manage_indexing'] ) : FALSE ) );
			
			$form->add( new \IPS\Helpers\Form\Number( 'g_club_limit', $group->g_club_limit ?: -1, FALSE, array( 'unlimited' => -1 ) ) );
			
			$availableClubNodes = array();
			foreach ( \IPS\Member\Club::availableNodeTypes() as $class )
			{
				$availableClubNodes[ $class ] = $class::clubAcpTitle();
			}
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'g_club_allowed_nodes', $group->g_club_allowed_nodes == '*' ? array_keys( $availableClubNodes ) : explode( ',', $group->g_club_allowed_nodes ), FALSE, array( 'options' => $availableClubNodes ), NULL, NULL, NULL, 'g_club_allowed_nodes' ) );
		}
		
		/* Reputation */
		if ( \IPS\Settings::i()->reputation_enabled )
		{
			$form->addHeader( 'reputation' );
		
			if( $group->g_id != \IPS\Settings::i()->guest_group )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'g_rep_max_positive', $group->g_rep_max_positive, FALSE, array( 'unlimited' => -1, ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('per_day') ) );
			}
			
			$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_view_reps', $group->g_id ? ( $group->g_bitoptions['gbw_view_reps'] ) : TRUE ) );
		}
		
		if( $group->g_id != \IPS\Settings::i()->guest_group )
		{
			/* Status Updates */
			if ( $group->canAccessModule( \IPS\Application\Module::load( 'members', 'sys_module_key', array( 'sys_module_application=? AND sys_module_area=?', 'core', 'front' ) ) ) )
			{
				$form->addHeader( 'status_updates' );
				$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_no_status_update', !$group->g_bitoptions['gbw_no_status_update'] ) );
				$form->add( new \IPS\Helpers\Form\YesNo( 'gbw_no_status_import', !$group->g_bitoptions['gbw_no_status_import'] ) );
			}
		}
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member\Group	$group	The group
	 * @return	void
	 */
	public function save( $values, &$group )
	{
		/* Init */
		$bwKeys	= array();
		$keys	= array();

		/* Display Name History */
		if ( array_key_exists( 'g_view_displaynamehistory', $values ) )
		{
			$group->g_view_displaynamehistory = $values['g_view_displaynamehistory'];
		}

		if( $group->g_id != \IPS\Settings::i()->guest_group )
		{
			/* Profiles */
			if ( $group->canAccessModule( \IPS\Application\Module::load( 'members', 'sys_module_key', array( 'sys_module_application=? AND sys_module_area=?', 'core', 'front' ) ) ) )
			{
				$bwKeys[]	= 'gbw_allow_upload_bgimage';
				$keys		= array_merge( $keys, array( 'g_edit_profile', 'g_max_bgimg_upload', 'g_upload_animated_photos' ) );
	
				/* Photos */
				$group->g_photo_max_vars = implode( ':', array( $values['g_photo_max_vars_size'], $values['g_photo_max_vars_wh'], $values['g_photo_max_vars_wh'] ) );
			}
				
			/* Status updates */
			if ( $group->canAccessModule( \IPS\Application\Module::load( 'members', 'sys_module_key', array( 'sys_module_application=? AND sys_module_area=?', 'core', 'front' ) ) ) )
			{
				$values['gbw_no_status_update'] = !$values['gbw_no_status_update'];
				$values['gbw_no_status_import'] = !$values['gbw_no_status_import'];
	
				$bwKeys[] = 'gbw_no_status_import';
				$bwKeys[] = 'gbw_no_status_update';
			}
			else
			{
				unset( $values['gbw_no_status_update'], $values['gbw_no_status_import'] );
			}
	
			/* Personal messages */
			if ( $group->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging', 'front' ) ) )
			{
				$bwKeys[]	= 'gbw_pm_override_inbox_full';
				$keys		= array_merge( $keys, array( 'g_pm_perday', 'g_pm_flood_mins', 'g_max_mass_pm', 'g_max_messages', 'g_can_msg_attach', 'g_max_notifications' ) );
			}
			
			/* Clubs */
			if ( \IPS\Settings::i()->clubs and $group->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
			{
				$group->g_create_clubs = implode( ',', $values['g_create_clubs'] );
				$group->g_club_allowed_nodes = ( \count( $values['g_club_allowed_nodes'] ) === \count( \IPS\Member\Club::availableNodeTypes() ) ) ? '*' : implode( ',', $values['g_club_allowed_nodes'] );
				$group->g_club_limit = $values['g_club_limit'] == -1 ? NULL : $values['g_club_limit'];
				$bwKeys[] = 'gbw_club_manage_indexing';
				if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on )
				{
					$bwKeys[] = 'gbw_paid_clubs';
				}
			}
		}
		
		/* Reputation */
		if ( \IPS\Settings::i()->reputation_enabled )
		{
			$bwKeys[] = 'gbw_view_reps';

			if( $group->g_id != \IPS\Settings::i()->guest_group )
			{
				$keys[] = 'g_rep_max_positive';
			}
		}

		/* Store bitwise options */
		foreach ( $bwKeys as $k )
		{
			$group->g_bitoptions[ $k ] = $values[ $k ];
		}

		/* Store other options */
		foreach ( $keys as $k )
		{
			if ( isset( $values[ $k ] ) )
			{
				$group->$k = $values[ $k ];
			}
		}
	}
}
