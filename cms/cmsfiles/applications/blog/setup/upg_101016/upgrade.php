<?php
/**
 * @brief		4.1.4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		05 Nov 2015
 */

namespace IPS\blog\setup\upg_101016;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.4 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert user blog name/desc to database
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Some init */
		$did		= 0;
		$limit		= 0;
		
		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		/* Get owned blogs - group blogs are still translatable */
		foreach( \IPS\Db::i()->select( '*', 'blog_blogs', "blog_member_id > 0", 'blog_id ASC', array( $limit, 500 ) ) as $blog )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$blog	= \IPS\blog\Blog::constructFromData( $blog );

			try
			{
				$language	= \IPS\Lang::load( \IPS\Member::load( $blog->member_id )->language ?: \IPS\Lang::defaultLanguage() );
			}
			catch( \Exception $e )
			{
				$language	= \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
			}

			/* We will now store the name and desc in the blog_blogs table */
			$blog->name = $language->get( 'blogs_blog_' . $blog->_id );
			$blog->desc = $language->get( 'blogs_blog_' . $blog->_id . '_desc' );

			$blog->save();

			/* Remove the translatable language strings */
			\IPS\Lang::deleteCustom( 'blog', 'blogs_blog_' . $blog->_id );
			\IPS\Lang::deleteCustom( 'blog', 'blogs_blog_' . $blog->_id . '_desc' );
		}
		
		if( $did )
		{
			return $limit + $did;
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', "blog_member_id > 0" )->first();
		}

		return "Converting blog names (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}