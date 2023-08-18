<?php
/**
 * @brief		Checkbox Set for Webhooks
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Dec 2021
 */

namespace IPS\core\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Checkbox Set for Webhooks
 */
class _WebhookSelector extends \IPS\Helpers\Form\CheckboxSet
{

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/webhooks.css' ) );

		/* Get descriptions */
		$descriptions = $this->options['descriptions'];

		foreach ( $this->options['options'] as $k => $v )
		{
			if ( \IPS\Member::loggedIn()->language()->checkKeyExists( $v ) )
			{
				$descriptions[ $k ] = \IPS\Member::loggedIn()->language()->addToStack( $v );
			}
		}

		$value = $this->value;
		
		/* If the value is NULL or an empty string, i.e. from a custom field, then we should not convert it into an array because the
			value will evaluate to 0 with an == check and the first option in the checkbox set will always be selected erroneously */
		if ( $this->options['unlimited'] === NULL and !\is_array( $value ) AND $value !== NULL AND $value !== '' )
		{
			$value = array( $value );
		}

		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'admin' )->webhookselector( $this->name, $value, $this->required, $this->parseOptions(), $this->options['multiple'], $this->options['class'], $this->options['disabled'], $this->options['toggles'], NULL, $this->options['unlimited'], $this->options['unlimitedLang'], $this->options['unlimitedToggles'], $this->options['unlimitedToggleOn'], $descriptions, $this->options['impliedUnlimited'] );
	}
}