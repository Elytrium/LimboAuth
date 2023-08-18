<?php
/**
 * @brief		4.3.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blogs
 * @since		04 Jan 2018
 */

namespace IPS\blog\setup\upg_103000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Move blog names into language system */
		foreach( \IPS\Db::i()->select( '*', 'blog_blogs', array( 'blog_name IS NOT NULL' ) ) as $blog )
		{
			if ( ! empty( $blog['blog_name'] ) )
			{
				\IPS\Lang::saveCustom( 'blog', 'blogs_blog_' . $blog['blog_id'], $blog['blog_name'] );
			}
		}
		return TRUE;
	}
}