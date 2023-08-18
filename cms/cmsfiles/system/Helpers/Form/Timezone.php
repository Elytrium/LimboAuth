<?php
/**
 * @brief		Timezone class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Feb 2017
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Timezone selector for Form Builder
 */
class _Timezone extends Select
{
	/**
	 * Constructor
	 *
	 * @param	string			$name					Name
	 * @param	mixed			$defaultValue			Default value
	 * @param	bool|NULL		$required				Required? (NULL for not required, but appears to be so)
	 * @param	array			$options				Type-specific options
	 * @param	callback		$customValidationCode	Custom validation code
	 * @param	string			$prefix					HTML to show before input field
	 * @param	string			$suffix					HTML to show after input field
	 * @param	string			$id						The ID to add to the row
	 * @return	void
	 */
	public function __construct( $name, $defaultValue=NULL, $required=FALSE, $options=array(), $customValidationCode=NULL, $prefix=NULL, $suffix=NULL, $id=NULL )
	{
		if ( !isset( $options['options'] ) )
		{
			$timezones = array();
			foreach ( \DateTimeZone::listIdentifiers() as $tz )
			{
				if ( $pos = mb_strpos( $tz, '/' ) )
				{
					$timezones[ 'timezone__' . mb_substr( $tz, 0, $pos ) ][ $tz ] = 'timezone__' . $tz;
				}
				else
				{
					$timezones[ $tz ] = 'timezone__' . $tz;
				}
			}
			
			$options['options'] = $timezones;
			$options['sort'] = TRUE;
		}
		
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
	}
}