<?php
/**
 * @brief		4.2.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		12 Apr 2017
 */

namespace IPS\cms\setup\upg_101100;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix multiple member fields
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Loop over any Member fields that allow multiple users */
		foreach( \IPS\Db::i()->select( '*', 'cms_database_fields', array( 'field_type=? AND field_is_multiple=?', 'Member', 1 ) ) as $field )
		{
			/* Then run an update query using REPLACE() on the Pages database to fix the field */
			\IPS\Db::i()->update( 'cms_custom_database_' . $field['field_database_id'], "field_{$field['field_id']}=REPLACE(field_{$field['field_id']},',','\\n')" );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing multiple member fields in Pages databases";
	}
}