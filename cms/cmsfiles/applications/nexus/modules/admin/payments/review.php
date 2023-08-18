<?php
/**
 * @brief		Transaction Review
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		26 Mar 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Transaction Review
 */
class _review extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'transaction_review_settings' );
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
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_revw_sa_on', \IPS\Settings::i()->nexus_revw_sa != -1, FALSE, array( 'togglesOn' => array( 'nexus_revw_sa' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'nexus_revw_sa', \IPS\Settings::i()->nexus_revw_sa ?: 0, FALSE, array( 'class' => 'IPS\nexus\Support\StockAction', 'zeroVal' => 'do_not_suggest_stock_action' ), NULL, NULL, NULL, 'nexus_revw_sa' ) );
		if ( $values = $form->values() )
		{
			if ( !$values['nexus_revw_sa_on'] )
			{
				$values['nexus_revw_sa'] = -1;
			}
			elseif ( $values['nexus_revw_sa'] )
			{
				$values['nexus_revw_sa'] = $values['nexus_revw_sa']->id;
			}
			unset( $values['nexus_revw_sa_on'] );
						
			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplogs__transaction_review_settings' );	
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=paymentsettings&tab=review' ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}
}