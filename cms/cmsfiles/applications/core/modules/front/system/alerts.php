<?php
/**
 * @brief		alerts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		12 May 2022
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * alerts
 */
class _alerts extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		parent::execute();
	}

	/**
	 * Dismiss alert
	 *
	 * @return	void
	 */
	protected function dismiss()
	{
		\IPS\Session::i()->csrfCheck();

		/* Update user last seen */
		try
		{
			$alert = \IPS\core\Alerts\Alert::load( \IPS\Request::i()->id );

			if( $alert->reply == 2 and \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->canUseMessenger() )
			{
				\IPS\Output::i()->error( 'alert_cant_dismiss', '3C428/1', 403, '' );
			}

			$alert->dismiss();
		}
		catch( \OutOfRangeException $e ) {}

		/* Redirect */
		\IPS\Output::i()->redirect( base64_decode( \IPS\Request::i()->ref ) );
	}

	/**
	 * Set currently filtering alert and redirect
	 *
	 * @return void
	 */
	protected function viewReplies()
	{
		\IPS\core\Alerts\Alert::setAlertCurrentlyFilteringMessages( \IPS\core\Alerts\Alert::load( \IPS\Request::i()->id ) );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=messaging&controller=messenger&overview=1') );
	}
}