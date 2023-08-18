<?php
/**
 * @brief		Color input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Color input class for Form Builder
 */
class _Color extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'disabled'		=> FALSE,		// Disables input. Default is FALSE.
	 		'swatches'		=> FALSE		// Shows colour swatches
	 		'rgba'			=> FALSE		// Show RGBA mode
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'disabled'	=> FALSE,
		'swatches'  => FALSE,
		'rgba'		=> FALSE
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$swatches = NULL;
				
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->color( $this->name, $this->value, $this->required, $this->options['disabled'], $this->options['swatches'], $this->options['rgba'] );
	}
	
	/**
	 * Format Value
	 *
	 * @return	string
	 */
	public function formatValue()
	{
		$manualName = $this->name . '_manual';

		/* If a manual value has been supplied, use that instead */
		if ( isset( \IPS\Request::i()->$manualName ) )
		{
			$value = \IPS\Request::i()->$manualName;
		}
		else
		{
			$value = $this->value;
		}

		if ( ! $this->options['rgba'] and mb_substr( $value, 0, 1 ) !== '#' )
		{
			$value = '#' . $value;
		}
		
		return mb_strtolower( $value );
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();

		if( !$this->required AND ! $this->options['rgba'] AND ( !$this->value OR $this->value == '#' ) )
		{
			return TRUE;
		}
		
		$hexPass = preg_match( '/^(?:#)?(([a-f0-9]{3})|([a-f0-9]{6}))$/i', $this->value );
		$namePass = preg_match( '/^([a-z]*)$/i', $this->value );
		$rgbaPass = preg_match( '/^rgba\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/mi', $this->value );
		
		if ( $this->options['rgba'] )
		{
			if ( ! $rgbaPass and ! $namePass and ! $hexPass ) 
			{
				throw new \InvalidArgumentException('form_color_bad_rgba');
			}
		}
		else
		{
			if ( ! $namePass and ! $hexPass ) 
			{
				throw new \InvalidArgumentException('form_color_bad');
			}
		}
	}
}