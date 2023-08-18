<?php
/**
 * @brief		4.1.6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		14 Dec 2015
 */

namespace IPS\blog\setup\upg_101021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Blog RSS Feed Imports
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 1000;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		
		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'blog_rss_import', array( "rss_member=?", 0 ), 'rss_id ASC', array( $limit, $perCycle ) ) as $rss )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;
			
			try
			{
				$blog = \IPS\blog\Blog::load( $rss['rss_blog_id'] );

				\IPS\Db::i()->update( 'blog_rss_import', array( 'rss_member' => $blog->member_id ), array( "rss_id=?", $rss['rss_id'] ) );
			}
			catch( \Exception $e ){}
		}
		
		if ( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step1Count'] );
			
			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'blog_rss_import', array( "rss_member=?", 0 ) )->first();
		}
		
		return "Fixing blog RSS imports (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}