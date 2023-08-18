<?php
/**
 * @brief		Community Enhancements: MaxMind
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		07 Mar 2014
 */

namespace IPS\nexus\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancement
 */
class _MaxMind
{
	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "maxmind.png";
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = (bool) \IPS\Settings::i()->maxmind_key;
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$form = new \IPS\Helpers\Form;		
		$form->add( new \IPS\Helpers\Form\YesNo( 'maxmind_enable', (bool) \IPS\Settings::i()->maxmind_key, FALSE, array( 'togglesOn' => array( 'maxmind_key', 'maxmind_gateways', 'maxmind_error' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'maxmind_id', \IPS\Settings::i()->maxmind_id, FALSE, array(), NULL, NULL, NULL, 'maxmind_id' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'maxmind_key', \IPS\Settings::i()->maxmind_key, FALSE, array(), NULL, NULL, NULL, 'maxmind_key' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'maxmind_gateways', ( !\IPS\Settings::i()->maxmind_gateways or \IPS\Settings::i()->maxmind_gateways === '*' ) ? 0 : explode( ',', \IPS\Settings::i()->maxmind_gateways ), FALSE, array( 'class' => 'IPS\nexus\Gateway', 'multiple' => TRUE, 'zeroVal' => 'all' ), NULL, NULL, NULL, 'maxmind_gateways' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'maxmind_error', \IPS\Settings::i()->maxmind_error, FALSE, array( 'options' => array( 'okay' => 'maxmind_error_okay', 'hold' => 'maxmind_error_hold' ) ), NULL, NULL, NULL, 'maxmind_error' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'maxmind_tracking_code', (bool) \IPS\Settings::i()->maxmind_tracking_code, FALSE, array(), NULL, NULL, NULL, 'maxmind_tracking_code' ) );

		if ( $values = $form->values() )
		{
			try
			{
				if ( $values['maxmind_enable'] )
				{
					unset( $values['maxmind_enable'] );
					$this->testSettings( $values['maxmind_key'], $values['maxmind_id'] );
					$values['maxmind_gateways'] = \is_array( $values['maxmind_gateways'] ) ? implode( ',', array_keys( $values['maxmind_gateways'] ) ) : '*';
					$form->saveAsSettings( $values );
				}
				else
				{
					unset( $values['maxmind_enable'] );
					$values['maxmind_key'] = '';
					$values['maxmind_id'] = '';
					$values['maxmind_gateways'] = '*';
					$form->saveAsSettings( $values );
				}

				\IPS\core\AdminNotification::remove( 'nexus', 'Maxmind' );
				\IPS\Output::i()->inlineMessage	= \IPS\Member::loggedIn()->language()->addToStack('saved');
			}
			catch ( \LogicException $e )
			{
				$form->error = $e->getMessage();
			}
		}
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'learn_more',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/maxmind' ),
				'target'	=> '_blank'
			),
		);
				
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 * @throws	\LogicException
	 */
	public function toggle( $enabled )
	{
		if ( $enabled )
		{
			throw new \LogicException;
		}
		else
		{	
			\IPS\Settings::i()->changeValues( array( 'maxmind_key' => '', 'maxmind_id' => '' ) );
			\IPS\core\AdminNotification::remove( 'nexus', 'Maxmind' );
		}
	}
	
	/**
	 * Test Settings
	 *
	 * @param	string|NULL	$key	Key to check
	 * @param	string|NULL	$id		ID to check
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function testSettings( $key=NULL, $id=NULL )
	{
		$testAddress = new \IPS\GeoLocation;
		$testAddress->addressLines = array( 'Invision Power Services, Inc.', 'PO Box 2365' );
		$testAddress->city = 'Forest';
		$testAddress->region = 'VA';
		$testAddress->country = 'US';
		$testAddress->postalCode = '24551';
		
		$maxMind = new \IPS\nexus\Fraud\MaxMind\Request( FALSE, $key, $id );
		$maxMind->setIpAddress( \IPS\Request::i()->ipAddress() );
		$maxMind->setBillingAddress( $testAddress );
		$maxMind = $maxMind->request();

		if ( $maxMind->error )
		{
			throw new \LogicException( $maxMind->error );
		}
	}
}