<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\setup\upg_22002;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Conversion from 3.0
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if( ! \IPS\Db::i()->checkForColumn( 'blog_themes', 'theme_css_overwrite' ) )
		{
			\IPS\Db::i()->addColumn( 'blog_themes', array( 'name' => 'theme_css_overwrite', 'type' => 'tinyint', 'length' => 1, 'allow_null' => false, 'default' => 0 ) );
		}
		
		return TRUE;
	}
}