<?php
/**
 * @brief		Credit card input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Mar 2014
 */

namespace IPS\nexus\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Credit card input class for Form Builder
 */
class _CreditCard extends \IPS\Helpers\Form\FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'types'		=> array( \IPS\nexus\CreditCard::TYPE_VISA, ... ),	// Accepted card types
	 		'save'		=> \IPS\nexus\Gateway,								// A gateway to handle saving cards (you must save the card manually, but this will allow stored cards to be received)
	 		'member'	=> \IPS\Member,										// The member for saving cards (if NULL, will use currently loggeed in member)
	 		'attr'		=> array(...),										// Will wrap fields in a div with the specified attributes
	 		'jsRequired'=> FALSE,											// If true, will add an error in <noscript> tags
	 		'names'		=> TRUE,											// Sets if name="" attributes should be on the fields (for gateways that use JS, this avoids details hitting the server)
	 		'dummy'		=> TRUE,											// If TRUE, will create <div>s rather than actual input boxes which is needed for some gateways
	 		'loading'	=> TRUE,											// If TRUE, will apply a loading filter over the new card input area in case you have any extra JS that needs to be set up
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'types'		=> array(),
		'save'		=> NULL,
		'member'	=> NULL,
		'attr'		=> NULL,
		'jsRequired'=> FALSE,
		'names'		=> TRUE,
		'dummy'		=> FALSE,
		'disabled'	=> FALSE,
		'loading'	=> FALSE
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$member = $this->options['member'] ?: \IPS\Member::loggedIn();
		
		$number = '';
		$expMonth = NULL;
		$expYear = NULL;
		$ccv = '';
		if ( \is_array( $this->value ) )
		{
			$number = $this->value['number'];
			$expMonth = $this->value['exp_month'];
			$expYear = $this->value['exp_year'];
			$ccv = $this->value['ccv'];
		}
		elseif ( $this->value instanceof \IPS\nexus\CreditCard )
		{
			$number = $this->value->number;
			$expMonth = $this->value->expMonth;
			$expYear = $this->value->expYear;
			$ccv = $this->value->ccv;
		}
		
		$types = array();
		foreach ( $this->options['types'] as $type )
		{
			$types[ $type ] = \IPS\Member::loggedIn()->language()->addToStack( 'card_type_' . $type );
		}
		
		$storedCards = array();
		if ( $member->member_id and $this->options['save'] )
		{
			foreach( \IPS\Db::i()->select( '*', 'nexus_customer_cards', array( 'card_member=? AND card_method=?', $member->member_id, $this->options['save']->id ) ) as $card )
			{				
				try
				{
					$card = \IPS\nexus\Customer\CreditCard::constructFromData( $card );
					$card->card; // This is just to make the API call now and cache the response so we can catch the exception if one is thrown
					$storedCards[ $card->id ] = $card;
				}
				catch ( \Exception $e ) {}
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->creditCard( $this, $types, $number, \intval( $expMonth ), \intval( $expYear ), $ccv, $storedCards );
	}
	
	/**
	 * Get HTML
	 *
	 * @param	\IPS\Helpers\Form|null	$form	Form helper object
	 * @return	string
	 */
	public function rowHtml( $form=NULL )
	{
		if ( !$this->htmlId and $form )
		{
			$this->htmlId = "{$form->id}_{$this->name}";
		}
		
		return $this->html();
	}
	
	/**
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{
		$member = $this->options['member'] ?: \IPS\Member::loggedIn();
				
		if ( $this->value !== NULL and !( $this->value instanceof \IPS\nexus\CreditCard ) and !( $this->value instanceof \IPS\nexus\Customer\CreditCard ) )
		{			
			/* Stored Card */
			if ( $this->options['save'] and isset( $this->value['stored'] ) and $this->value['stored'] and ( !isset( $this->value['number'] ) or !$this->value['number'] ) and ( !isset( $this->value['token'] ) or !$this->value['token'] ) )
			{
				$card = \IPS\nexus\Customer\CreditCard::load( $this->value['stored'] );
				if ( $card->member->member_id === $member->member_id and $card->method->id === $this->options['save']->id )
				{
					return $card;
				}
			}
			/* New card */
			else
			{
				/* If we don't send the values, we won't have them */
				if ( !$this->options['names'] )
				{
					$obj = new \IPS\nexus\CreditCard;
					if ( isset( $this->value['token'] ) )
					{
						$obj->token = $this->value['token'];
					}
					if ( $this->options['save'] and isset( $this->value['save'] ) )
					{
						$obj->save = $this->value['save'];
					}
					return $obj;
				}
				
				/* Or if we've just not given anything, return no value */
				if ( !$this->value['number'] )
				{
					return '';
				}
				
				/* But if we do, we can build an object */
				try
				{
					return \IPS\nexus\CreditCard::build( $this->value['number'], $this->value['exp_month'], $this->value['exp_year'], $this->value['ccv'], ( $this->options['save'] and isset( $this->value['save'] ) and $this->value['save'] ) );
				}
				catch ( \InvalidArgumentException $e )
				{
					throw new \DomainException( $e->getMessage() );
				}
				catch ( \DomainException $e )
				{
					throw new \DomainException( 'card_expire_expired' );
				}
			}
		}
	}
	
	/**
	 * Validate
	 *
	 * @return	mixed
	 */
	public function validate()
	{	
		if ( $this->value instanceof \IPS\nexus\CreditCard )
		{
			if ( $this->value->type and !\in_array( $this->value->type, $this->options['types'] ) )
			{
				throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( 'card_bad_type', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "card_type_" . $this->value->type ) ) ) ) );
			}
		}
		
		return parent::validate();
	}
}