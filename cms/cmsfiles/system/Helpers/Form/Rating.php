<?php
/**
 * @brief		Rating input Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Oct 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Rating input class for Form Builder
 */
class _Rating extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'max'		=> 5,		// Maximum number of stars
	 		'display'	=> 2.5,		// If provided, this number of stars will be highlighted initially rather than the value. Can be used for half-values
	 		'userRated'	=> 3,		// If provided, will indicate to the user a previous rating
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'max'		=> 5,
		'display'	=> NULL,
		'userRated'	=> NULL,
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->rating( $this->name, $this->value, $this->required, $this->options['max'], $this->options['display'], $this->options['userRated'] );
	}
	
	/**
	 * Format Value
	 *
	 * @return	int|NULL
	 */
	public function formatValue()
	{
		return $this->value ? \intval( $this->value ) : NULL;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if( $this->value === NULL and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}
		
		return parent::validate();
	}
}