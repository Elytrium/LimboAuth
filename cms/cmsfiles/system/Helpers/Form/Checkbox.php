<?php
/**
 * @brief		Checkbox class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Feb 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Checkbox class for Form Builder
 */
class _Checkbox extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'disabled'	=> FALSE,			// Disabled?
	 		'togglesOn'		=> array( ... ),	// IDs of elements to show when checked
	 		'togglesOff'	=> array( ... ),	// IDs of elements to show when unchecked
	 		'label'		=> '',				// Label language key
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'disabled'		=> FALSE,
		'togglesOn'		=> array(),
		'togglesOff'	=> array(),
		'label'			=> '',
	);

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		if ( !$this->htmlId )
		{
			$this->htmlId = md5( 'ips_checkbox_' . $this->name );
		}
		
		$checkboxName = preg_replace( '/^(.+?\[?.+?)(\])?$/', '$1_checkbox$2', $this->name );
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->checkbox( $checkboxName, $this->value, $this->options['disabled'], $this->options['togglesOn'], $this->options['togglesOff'], $this->options['label'], $this->name, $this->htmlId );
	}

	/**
	 * Format Value
	 *
	 * @return	bool
	 */
	public function formatValue()
	{
		return (bool) $this->value;
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$checkboxName = preg_replace( '/^(.+?\[?.+?)(\])?$/', '$1_checkbox$2', $this->name );
		if ( mb_strpos( $checkboxName, '[' ) === FALSE )
		{
			return \IPS\Request::i()->$checkboxName;
		}
		else
		{
			return \IPS\Request::i()->valueFromArray( $checkboxName );
		}
	}
	
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	int
	 */
	public static function stringValue( $value )
	{
		return \intval( $value );
	}
}