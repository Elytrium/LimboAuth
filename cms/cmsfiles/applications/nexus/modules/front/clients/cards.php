<?php
/**
 * @brief		Cards
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		08 May 2014
 */

namespace IPS\nexus\modules\front\clients;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Cards
 */
class _cards extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2X236/1', 403, '' );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'clients.css', 'nexus' ) );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=cards', 'front', 'clientscards' ), \IPS\Member::loggedIn()->language()->addToStack('client_cards') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('client_cards');
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		
		if ( $output = \IPS\MFA\MFAHandler::accessToArea( 'nexus', 'Cards', \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=cards', 'front', 'clientscards' ) ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->cards( array() ) . $output;
			return;
		}
		
		parent::execute();
	}
	
	/**
	 * View List
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$cards = array();
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_customer_cards', array( 'card_member=?', \IPS\nexus\Customer::loggedIn()->member_id ) ), 'IPS\nexus\Customer\CreditCard' ) as $card )
		{
			try
			{
				$cardData = $card->card;
				$cards[ $card->id ] = array(
					'id'				=> $card->id,
					'card_type'			=> $cardData->type,
					'card_number'		=> $cardData->lastFour ?: $cardData->number,
					'card_expire'		=> ( !\is_null( $cardData->expMonth ) AND !\is_null( $cardData->expYear ) ) ? str_pad( $cardData->expMonth , 2, '0', STR_PAD_LEFT ). '/' . $cardData->expYear : NULL
				);
			}
			catch ( \Exception $e ) { }
		}
				
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->cards( $cards );
	}
	
	/**
	 * Add
	 *
	 * @return	void
	 */
	public function add()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'checkout.css', 'nexus' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_gateways.js', 'nexus', 'global' ) );
		$form = \IPS\nexus\Customer\CreditCard::create( \IPS\nexus\Customer::loggedIn(), FALSE );
		if ( $form instanceof \IPS\nexus\Customer\CreditCard )
		{
			\IPS\nexus\Customer::loggedIn()->log( 'card', array( 'type' => 'add', 'number' => $form->card->lastFour ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=cards', 'front', 'clientscards' ) );
		}
		else
		{
			\IPS\Output::i()->output = $form;
		}
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Session::i()->csrfCheck();

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		try
		{
			$card = \IPS\nexus\Customer\CreditCard::load( \IPS\Request::i()->id );
			if ( $card->member->member_id === \IPS\nexus\Customer::loggedIn()->member_id )
			{
				$cardData = $card->card;

				$card->delete(); 
				\IPS\nexus\Customer::loggedIn()->log( 'card', array( 'type' => 'delete', 'number' => $cardData->lastFour ) );
			}
		}
		catch ( \Exception $e ) { }
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=cards', 'front', 'clientscards' ) );
	}
}