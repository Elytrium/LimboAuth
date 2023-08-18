<?php
/**
 * @brief		PayPal Stored Card
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\Gateway\PayPal;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayPal Stored Card
 */
class _CreditCard extends \IPS\nexus\Customer\CreditCard
{
	/**
	 * @brief	Card
	 */
	protected $_card;
	
	/**
	 * Get card
	 *
	 * @return	\IPS\nexus\CreditCard
	 */
	public function get_card()
	{
		if ( !$this->_card )
		{		
			$response = $this->method->api( 'vault/credit-card/' . $this->data, NULL, 'get' );
	
			$this->_card = new \IPS\nexus\CreditCard;
			$this->_card->lastFour = mb_substr( $response['number'], -4 );
			switch ( $response['type'] )
			{
				case 'visa':
					$this->_card->type = \IPS\nexus\CreditCard::TYPE_VISA;
					break;
				case 'mastercard':
					$this->_card->type = \IPS\nexus\CreditCard::TYPE_MASTERCARD;
					break;
				case 'discover':
					$this->_card->type =  \IPS\nexus\CreditCard::TYPE_DISCOVER;
					break;
				case 'amex':
					$this->_card->type =  \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS;
					break;
			}
			$this->_card->expMonth = $response['expire_month'];
			$this->_card->expYear = $response['expire_year'];
		}
		return $this->_card;
	}
	
	/**
	 * Set card
	 *
	 * @param	\IPS\nexus\CreditCard	$card	The card
	 * @return	void
	 */
	public function set_card( \IPS\nexus\CreditCard $card )
	{
		switch ( $card->type )
		{
			case \IPS\nexus\CreditCard::TYPE_VISA:
				$cardType = 'visa';
				break;
			case \IPS\nexus\CreditCard::TYPE_MASTERCARD:
				$cardType = 'mastercard';
				break;
			case \IPS\nexus\CreditCard::TYPE_DISCOVER:
				$cardType = 'discover';
				break;
			case \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS:
				$cardType = 'amex';
				break;
		}

		/* Check it doesn't already exist */
		$otherCards = \IPS\Db::i()->select( 'card_data', 'nexus_customer_cards', array( 'card_member=? AND card_method=?', $this->member->member_id, $this->method->id ) );
		if ( \count( $otherCards ) )
		{
			foreach ( $otherCards as $otherCardId )
			{
				$otherCardData = $this->method->api( 'vault/credit-card/' . $otherCardId, NULL, 'get' );
				if ( $otherCardData['state'] === 'ok' and mb_substr( $otherCardData['number'], -4 ) === mb_substr( $card->number, -4 ) and \intval( $otherCardData['expire_month'] ) === \intval( $card->expMonth ) and \intval( $otherCardData['expire_year'] ) === \intval( $card->expYear ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('card_is_duplicate') );
				}
			}
		}

		/* Save */
		$response = $this->method->api( 'vault/credit-card', array(
			'number'			=> $card->number,
			'type'				=> $cardType,
			'expire_month'		=> \intval( $card->expMonth ),
			'expire_year'		=> \intval( $card->expYear ),
			'cvv2'				=> $card->ccv,
			'first_name'		=> $this->member->cm_first_name,
			'last_name'			=> $this->member->cm_last_name,
		) );
		$this->data = $response['id'];
		$this->save();
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		$this->method->api( 'vault/credit-card/' . $this->data, NULL, 'delete' );
		return parent::delete();
	}
}