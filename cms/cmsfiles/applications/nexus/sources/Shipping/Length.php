<?php
/**
 * @brief		Length Object
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Jun 2014
 */

namespace IPS\nexus\Shipping;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Length Object
 */
class _Length
{
	/**
	 * @brief	Conversion rates
	 */
	public static $conversionRates = array(
		'm'		=> 1,
		'cm'	=> 0.01,
		'in'	=> 0.0254,
	);
		
	/**
	 * @brief	Length in metres (naturally we use metres as per le Système international d'unités like good boys)
	 */
	public $metres = 0;
	
	/**
	 * Best display unit
	 *
	 * @param	\IPS\Member|NULL	$member	The member
	 * @return	string
	 */
	public static function bestUnit( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
	
		/* US uses lbs. This is a more reliable way to detect if locale
		   if US, as Windows and Nix use different locale keys, but both
		   will set the currency symbol to USD for US only */
		if ( trim( $member->language()->locale['int_curr_symbol'] ) === 'USD' )
		{
			return 'in';
		}
		
		return 'cm';
	}
	
	/**
	 * Contructor
	 *
	 * @param	float	$amount	Length
	 * @param	string	$unit	Unit
	 * @return	void
	 */
	public function __construct( $amount, $unit='m' )
	{
		$this->metres = ( $amount * static::$conversionRates[ $unit ] );
	}
	
	/**
	 * Get as float
	 *
	 * @param	$unit	Unit
	 * @return	float
	 */
	public function float( $unit='m' )
	{
		return $this->metres / static::$conversionRates[ $unit ];
	}
	
	/**
	 * Get as string
	 *
	 * @param	string|NULL	$unit	Unit (NULL to autodetect)
	 * @param	int|NULL	$round	Decimal places to round to
	 * @return	float
	 */
	public function string( $unit=NULL, $round=2 )
	{
		if ( $unit === NULL )
		{
			$unit = static::bestUnit();
		}
			
		return \IPS\Member::loggedIn()->language()->addToStack( 'length_short_' . $unit, FALSE, array( 'sprintf' => array( round( $this->float( $unit ), $round ) ) ) );
	}
}