<?php
/**
 * @brief		Money input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		12 Feb 2014
 */

namespace IPS\nexus\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Money input class for Form Builder
 */
class _Money extends \IPS\Helpers\Form\FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'unlimitedLang'			=> 'unlimited',	// Language string to use for unlimited checkbox label
	 		'unlimitedTogglesOn'	=> array(),		// IDs to show when unlimited box is ticked
	 		'unlimitedTogglesOff'	=> array(),		// IDs to show when unlimited box is not ticked
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'unlimitedLang'			=> NULL,
		'unlimitedTogglesOn'	=> array(),
		'unlimitedTogglesOff'	=> array(),
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->money( $this->name, $this->value, $this->options );
	}
	
	/**
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{
		if ( ( $this->options['unlimitedLang'] and isset( $this->value['__unlimited'] ) ) or $this->value === '*' )
		{
			return '*';
		}
		
		$_value = \is_array( $this->value ) ? $this->value : @json_decode( $this->value, TRUE );
		$value = array();
		if ( \is_array( $_value ) )
		{
			foreach ( $_value as $currency => $amount )
			{
				if ( $amount !== '' )
				{
					if ( $amount instanceof \IPS\nexus\Money )
					{
						$value[ $currency ] = $amount;
					}
					elseif ( \is_array( $amount ) and isset( $amount['amount'] ) )
					{
						$value[ $currency ] = new \IPS\nexus\Money( $amount['amount'], $currency );
					}
					elseif ( \is_numeric( $amount ) )
					{
						$value[ $currency ] = new \IPS\nexus\Money( $amount, $currency );
					}
				}
			}
		}
		return $value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if ( $this->required )
		{
			if ( ( $this->options['unlimitedLang'] and $this->value === '*' ) or \count( $this->value ) )
			{
				return TRUE;
			}
			
			throw new \InvalidArgumentException('form_required');
		}
		return parent::validate();
	}
	
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public static function stringValue( $value )
	{
		return json_encode( $value );
	}
}