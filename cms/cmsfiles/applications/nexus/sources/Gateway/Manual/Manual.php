<?php
/**
 * @brief		Manual Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Mar 2014
 */

namespace IPS\nexus\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Manual Gateway
 */
class _Manual extends \IPS\nexus\Gateway
{	
	/* !Payment Gateway */
		
	/**
	 * Authorize
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	array|\IPS\nexus\Customer\CreditCard	$values			Values from form OR a stored card object if this gateway supports them
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @param	array									$recurrings		Details about recurring costs
	 * @param	string|NULL								$source			'checkout' if the customer is doing this at a normal checkout, 'renewal' is an automatically generated renewal invoice, 'manual' is admin manually charging. NULL is unknown
	 * @return	\IPS\DateTime|NULL						Auth is valid until or NULL to indicate auth is good forever
	 * @throws	\LogicException							Message will be displayed to user
	 */
	public function auth( \IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, $recurrings = array(), $source = NULL )
	{
		$transaction->status = \IPS\nexus\Transaction::STATUS_WAITING;
		$extra = $transaction->extra;
		$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_WAITING );
		$transaction->extra = $extra;
		$transaction->save();

		/* Send Notification */
		$transaction->sendNotification();
		\IPS\core\AdminNotification::send( 'nexus', 'Transaction', \IPS\nexus\Transaction::STATUS_WAITING, TRUE, $transaction );

		\IPS\Output::i()->redirect( $transaction->url() );
	}
		
	/* !ACP Configuration */
	
	/**
	 * Settings
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function settings( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'manual_instructions', NULL, TRUE, array( 'app' => 'nexus', 'key' => ( $this->id ? "nexus_gateway_{$this->id}_ins" : NULL ), 'editor' => array( 'app' => 'nexus', 'key' => 'Admin', 'autoSaveKey' => ( $this->id ? "nexus-gateway-{$this->id}" : "nexus-new-gateway" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'manual_gateway_description_placeholder' ) ) ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'nexus_gateway_new', $this->id, NULL, 'gateway', TRUE );
		}

		if( isset( $values['manual_instructions'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_gateway_{$this->id}_ins", $values['manual_instructions'] );
			unset( $values['manual_instructions'] );
		}

		return parent::formatFormValues( $values );
	}

	/**
	 * Test Settings
	 *
	 * @param	array	$settings	Settings
	 * @return	array
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings( $settings=array() )
	{
		return $settings;
	}
	
	/**
	 * Refund
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction to be refunded
	 * @param	float|NULL				$amount			Amount to refund (NULL for full amount - always in same currency as transaction)
	 * @return	mixed									Gateway reference ID for refund, if applicable
	 */
	public function refund( \IPS\nexus\Transaction $transaction, $amount = NULL )
	{

	}
}