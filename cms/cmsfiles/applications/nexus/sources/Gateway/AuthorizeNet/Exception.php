<?php
/**
 * @brief		Authorize.Net Exception
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Mar 2014
 */

namespace IPS\nexus\Gateway\AuthorizeNet;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * AuthorizeNet Exception
 */
class _Exception extends \DomainException
{
	/**
	 * Constructor
	 *
	 * @param	int	$reasonCode	Response Reason Code
	 * @return	void
	 */
	public function __construct( $reasonCode )
	{
		$reasonCode = intval( $reasonCode );
		
		switch ( $reasonCode )
		{				
			case 2:
			case 3:
			case 4:
			case 41:
			case 45:
				$message = \IPS\Member::loggedIn()->language()->get( 'card_refused' );
				break;
				
			case 6:
			case 37:
				$message = \IPS\Member::loggedIn()->language()->get( 'card_number_invalid' );
				break;
				
			case 8:
				$message = \IPS\Member::loggedIn()->language()->get( 'card_expire_expired' );
				break;
			
			case 44:
			case 65:
			case 78:
				$message = \IPS\Member::loggedIn()->language()->get( 'ccv_invalid' );
				break;
							
			default:
				$message = \IPS\Member::loggedIn()->language()->get( 'gateway_err' );
				break;
		}
		
		return parent::__construct( $message, $reasonCode );
	}
}