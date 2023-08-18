<?php
/**
 * @brief		Device usage
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Jan 2018
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Device usage
 */
class _deviceusage extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'deviceusage_manage' );
		parent::execute();
	}

	/**
	 * Device usage chart
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Show button to adjust settings */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'title'		=> 'prunesettings',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=stats&controller=deviceusage&do=settings' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
		);

		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'DeviceUsage' )->getChart( \IPS\Http\Url::internal( "app=core&module=stats&controller=deviceusage" ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_deviceusage');
		\IPS\Output::i()->output	= (string) $chart;
	}

	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'stats_device_usage_prune', \IPS\Settings::i()->stats_device_usage_prune, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_moderator' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__statsonlineusers_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=stats&controller=deviceusage' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('prunesettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'prunesettings', $form, FALSE );
	}
}