<?php
/**
 * @brief		Profile settings gateway
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
 * Profile settings gateway
 */
class _profiles extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Call
	 *
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		/* Init */
		$activeTab			= \IPS\Request::i()->tab ?: 'profilefields';
		$activeTabContents	= '';
		$tabs				= array();

		/* Add a tab for fields and completion */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'profilefields_manage' ) )
		{
			$tabs['profilefields']		= 'profile_fields';
			$tabs['profilecompletion']	= 'profile_completion';
		}

		/* Add a tab for settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'profiles_manage' ) )
		{
			$tabs['profilesettings']	= 'profile_settings';
		}

		/* Route */
		$classname = 'IPS\core\modules\admin\membersettings\\' . $activeTab;
		$class = new $classname;
		$class->url = \IPS\Http\Url::internal("app=core&module=membersettings&controller=profiles&tab={$activeTab}");
		$class->execute();
		
		$output = \IPS\Output::i()->output;
				
		if ( $method !== 'manage' or \IPS\Request::i()->isAjax() )
		{
			return;
		}
		\IPS\Output::i()->output = '';
				
		/* Output */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('module__core_profile');
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $output, \IPS\Http\Url::internal( "app=core&module=membersettings&controller=profiles" ) );
	}
}