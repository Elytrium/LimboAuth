<?php
/**
 * @brief		preferences
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		02 Sep 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * preferences
 */
class _preferences extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'preferences_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs		= array(
			'theme'		=> 'stats_member_pref_theme',
			'lang'		=> 'stats_member_pref_lang',
		);
		\IPS\Request::i()->tab ??= 'lang';
		$activeTab	= ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'lang';

		switch( $activeTab )
		{
			case 'theme':
				$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Theme' )->getChart( \IPS\Http\Url::internal( "app=core&module=stats&controller=preferences&tab=theme" ) );
				break;
				
			case 'lang':
				$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Language' )->getChart( \IPS\Http\Url::internal( "app=core&module=stats&controller=preferences&tab=lang" ) );
				break;
		}
		
		$chart = $chart->render('PieChart', array( 
				'backgroundColor' 	=> '#ffffff',
				'pieHole' => 0.4,
				'chartArea' => array( 
					'width' =>"90%", 
					'height' => "90%" 
				) 
			) );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->paddedBlock( (string) $chart, NULL, "ipsPad" );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_preferences');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=core&module=stats&controller=preferences" ), 'tab', '', 'ipsPad' );
		}
			
	}
}