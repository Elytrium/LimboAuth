<?php
/**
 * @brief		Referral Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		7 Aug 2019
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Referral Settings
 */
class _refersettings extends \IPS\Dispatcher\Controller
{	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function execute()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\YesNo( 'ref_on', \IPS\Settings::i()->ref_on, FALSE ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			if ( $values['ref_on'] )
			{
				\IPS\Session::i()->log( 'acplog__referrals_enabled' );
			}
			else
			{
				\IPS\Session::i()->log( 'acplog__referrals_disabled' );
			}

			/* update the essential cookie name list */
			unset( \IPS\Data\Store::i()->essentialCookieNames );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=membersettings&controller=referrals') );
		}
		
		\IPS\Output::i()->output = $form;
	}
}