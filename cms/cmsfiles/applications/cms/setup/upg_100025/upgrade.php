<?php
/**
 * @brief		4.0.2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		15 Apr 2015
 */

namespace IPS\cms\setup\upg_100025;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.2 Upgrade Code
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Create language strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$strings = array();

		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $db )
		{
			foreach( \IPS\Lang::languages() as $id => $lang )
			{
				try
				{
					$base = $lang->get('digest_area_records' . $db['database_id'] );
				}
				catch ( \UnderflowException $e )
				{
					$base = "";
				}
				
				$strings[ $lang->_id ]		= $base;
			}

			\IPS\Lang::saveCustom( 'cms', "digest_area_categories" . $db['database_id'], $strings );
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
		return "Creating missing language strings";
	}
}