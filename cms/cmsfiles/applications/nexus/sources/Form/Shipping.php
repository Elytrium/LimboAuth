<?php
/**
 * @brief		Shipping method input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Mar 2014
 */

namespace IPS\nexus\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Shipping method input class for Form Builder
 */
class _Shipping extends \IPS\Helpers\Form\FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'options'	=> array(			// Options
	 			'set1'		=> array(			// Group items into sets with similar methods
	 				'items'		=> array(			// Array of item data
	 					'itemName'		=> '',				// Name
	 				),
	 				'methods'	=> array(			// Array of methods (classes which implement \IPS\nexus\Shipping\Rate)
	 					\IPS\nexus\Shipping\Rate,
	 					...
	 				)
	 			),
	 			'set2'		=> array(...),	// Repeat for however many sets you like
	 		),
	 		'currency'	=> 'USD',			// Currency to display prices in
	 		'invoice'	=> \IPS\nexus\Invoice::load(1),	// Invoice (some shipping methods may use it for determining price)
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'options'	=> NULL,
		'currency'	=> NULL,
		'invoice'	=> NULL,
	);

	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->shipping( $this->name, $this->value, $this->options );
	}
		
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		foreach ( $this->options['options'] as $group => $options )
		{
			if ( !isset( $this->value[ $group ] ) or !$this->value[ $group ] )
			{
				if ( $this->required )
				{
					throw new \InvalidArgumentException('form_required');
				}
			}
			elseif ( !\in_array( $this->value[ $group ], array_keys( $options['methods'] ) ) )
			{
				throw new \OutOfRangeException( 'form_bad_value' );
			}
		}
		
		return parent::validate();
	}
}