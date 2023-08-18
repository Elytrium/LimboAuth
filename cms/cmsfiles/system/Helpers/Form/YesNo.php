<?php
/**
 * @brief		Yes/No Radio Switches class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Yes/No Radio Switches class for Form Builder
 */
class _YesNo extends Checkbox
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'disabled'		=> FALSE,			// Disabled?
	 		'togglesOn'		=> array( ... ),	// IDs of elements to show when checked
	 		'togglesOff'	=> array( ... ),	// IDs of elements to show when unchecked
	 		'label'			=> '',				// Label language key
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'disabled'		=> FALSE,
		'togglesOn'		=> array(),
		'togglesOff'	=> array(),
		'label'			=> '',
		'tooltip'		=> NULL,
	);
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$checkboxName = preg_replace( '/^(.+?\[?.+?)(\])?$/', '$1_checkbox$2', $this->name );
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->checkbox( $checkboxName, $this->value, $this->options['disabled'], $this->options['togglesOn'], $this->options['togglesOff'], $this->options['label'], $this->name, $this->htmlId ?: preg_replace( "/[^a-zA-Z0-9\-_]/", "_", $this->name ), TRUE, $this->options['tooltip'] );
	}
}