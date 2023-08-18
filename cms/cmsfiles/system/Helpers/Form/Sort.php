<?php
/**
 * @brief		Sort items input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Oct 2016
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Sort items input class for Form Builder
 */
class _Sort extends FormAbstract
{
	/**
	 * @brief	Default Options
	 */
	protected $defaultOptions = array(
        'checkboxes'	=> NULL
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-touchpunch.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery.menuaim.js', 'core', 'interface' ) );

		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->sort( $this->name, $this->value, $this->options );
	}
		
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if ( array_diff( array_keys( $this->value ), array_keys( $this->defaultValue ) ) or array_diff( array_keys( $this->defaultValue ), array_keys( $this->value ) ) )
		{
			throw new \DomainException('form_bad_value');
		}

		parent::validate();
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$value = parent::getValue();
		$checkboxName = preg_replace( '/^(.+?\[?.+?)(\])?$/', '$1_checkboxes$2', $this->name );
		if ( $this->options['checkboxes'] and \IPS\Request::i()->$checkboxName )
		{
			foreach ( $value as $k => $v )
			{
				$value[ $k ] = isset( \IPS\Request::i()->$checkboxName[ $k ] );
			}
			return $value;
		}
		else
		{
			return $value;
		}
	}
}