<?php
/**
 * @brief		Achievement settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Mar 2021
 */

namespace IPS\core\modules\admin\achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\YesNo( 'achievements_enabled', \IPS\Settings::i()->achievements_enabled, FALSE, [ 'togglesOn' => [ 'rare_badge_percent', 'prune_points_log', 'rules_exclude_groups', 'achievements_recognize_max_per_user_day' ] ] ) );

		$form->add( new \IPS\Helpers\Form\Number( 'rare_badge_percent', \IPS\Settings::i()->rare_badge_percent, FALSE, [ 'decimals' => 1, 'unlimited' => 0, 'unlimitedLang' => 'never' ], NULL, \IPS\Member::loggedIn()->language()->addToStack('rare_badge_percent_prefix'), \IPS\Member::loggedIn()->language()->addToStack('rare_badge_percent_suffix'), 'rare_badge_percent' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_points_log', \IPS\Settings::i()->prune_points_log, FALSE, [ 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ], NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_points_log' ) );
		$groups		= array_combine( array_keys( \IPS\Member\Group::groups( TRUE, FALSE ) ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups( TRUE, FALSE ) ) );
		$selectedGroups = json_decode( \IPS\Settings::i()->rules_exclude_groups, TRUE );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'rules_exclude_groups', $selectedGroups, FALSE, array( 'options' => $groups, 'multiple' => true ), NULL, NULL, NULL, 'rules_exclude_groups' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'achievements_recognize_max_per_user_day', \IPS\Settings::i()->achievements_recognize_max_per_user_day, FALSE, [ 'unlimited' => -1 ], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievements_recognize_max_per_user_day_prefix'), \IPS\Member::loggedIn()->language()->addToStack('achievements_recognize_max_per_user_day_suffix'), 'achievements_recognize_max_per_user_day' ) );

		if ( $values = $form->values() )
		{
			$values['rules_exclude_groups'] = json_encode( $values['rules_exclude_groups'] );
			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplog__achievement_settings' );
		}

		if ( \IPS\Settings::i()->achievements_enabled )
		{
			\IPS\Output::i()->sidebar['actions']['rebuild'] = array(
				'primary' => true,
				'icon' => 'plus',
				'link' => \IPS\Http\Url::internal( 'app=core&module=achievements&controller=settings&do=rebuildForm' )->csrf(),
				'data' => array('ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'acp_rebuild_achievements' )),
				'title' => 'acp_rebuild_achievements',
			);
		}

		if( $data = \IPS\core\Achievements\Rule::getRebuildProgress() )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'achievements' )->rebuildProgress( $data, TRUE );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('achievements_settings');
		\IPS\Output::i()->output .= $form;
	}

	/**
	 * Rebuild Members' Achievements
	 *
	 * @return	void
	 */
	protected function rebuildForm()
	{
		if ( ! \IPS\Settings::i()->achievements_enabled )
		{
			\IPS\Output::i()->error( 'achievements_not_enabled', '1C421/1', 403, '' );
		}

		$form = new \IPS\Helpers\Form( 'rebuild_form', 'acp_rebuild_achievements_rebuild');
		$form->addMessage('acp_rebuild_achievements_blurb', 'ipsMessage ipsMessage_info');
		$form->add( new \IPS\Helpers\Form\Checkbox( 'acp_rebuild_achievements_checkbox', FALSE, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Date( 'acp_rebuild_achievements_time', 0, TRUE, [ 'unlimited' => 0, 'unlimitedLang' => 'acp_rebuild_achievements_time_unlimited' ] ) );

		if ( $values = $form->values() )
		{
			if ( ! $values['acp_rebuild_achievements_checkbox'] )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_rebuild_not_checked');
			}
			else
			{
				\IPS\Session::i()->log( 'acplogs__achievements_rebuild' );
				\IPS\core\Achievements\Rule::rebuildAllAchievements( $values['acp_rebuild_achievements_time'] ?: NULL );

				\IPS\Settings::i()->changeValues( array('achievements_rebuilding' => 1) );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=settings' ) );
			}
		}

		\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('acp_rebuild_achievements');
		\IPS\Output::i()->output = $form;
	}
	
}