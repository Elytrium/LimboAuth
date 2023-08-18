<?php
/**
 * @brief		Upgrader: Pre-upgrade check
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Feb 2015
 */

/* Clear settings store in case we've already been here */
\IPS\Settings::i()->clearCache();

/* Do this the easy way first... */
if( isset( \IPS\Settings::i()->gb_char_set ) AND ! empty( \IPS\Settings::i()->gb_char_set ) AND mb_strtolower( \IPS\Settings::i()->gb_char_set ) !== 'utf-8' )
{
	$output = \IPS\Theme::i()->getTemplate( 'global' )->convertUtf8();
	return;
}

/* Check for non-UTF8 database tables */
$convert    = FALSE;
$prefix = \IPS\Db::i()->prefix;
$tables	= \IPS\Db::i()->query( "SHOW TABLES LIKE '{$prefix}%';" );
while ( $table = $tables->fetch_assoc() )
{
	$tableName	= array_pop( $table );

	if( mb_strpos( $tableName, "orig_" ) === 0 )
	{
		continue;
	}
	
	if( mb_strpos( $tableName, "x_utf_" ) === 0 )
	{
		continue;
	}

	/* Skip if it's an _old or _utftemp table */
	if( mb_substr( $tableName, -4 ) == '_old' )
	{
		continue;
	}

	if( mb_substr( $tableName, -8 ) == '_utftemp' )
	{
		continue;
	}
	
	/* Ticket 909929 - SHOW TABLES LIKE 'ibf_%' can match tables like ibf3_table_name */
	if ( $prefix and mb_strpos( $tableName, $prefix ) === FALSE )
	{
		continue;
	}

    $definition = \IPS\Db::i()->getTableDefinition( preg_replace( '/^' . $prefix . '/', '', $tableName ), FALSE, TRUE );

    if( isset( $definition['collation'] ) AND !\in_array( $definition['collation'], array( 'utf8_unicode_ci', 'utf8mb4_unicode_ci', 'utf8_bin', 'utf8mb4_bin' ) ) )
	{
		$convert = TRUE;
		break;
	}

	$columns	= \IPS\Db::i()->query( "SHOW FULL COLUMNS FROM `{$tableName}`;" );

	while ( $column = $columns->fetch_assoc() )
	{
		if ( $column['Collation'] and !\in_array( $column['Collation'], array( 'utf8_unicode_ci', 'utf8mb4_unicode_ci', 'utf8_bin', 'utf8mb4_bin' ) ) )
		{
			$convert = TRUE;
			break 2;
		}
	}
}

if ( $convert === TRUE )
{
	$output = \IPS\Theme::i()->getTemplate( 'global' )->convertUtf8();
}