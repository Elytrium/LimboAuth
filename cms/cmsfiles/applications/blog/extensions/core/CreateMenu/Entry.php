<?php
/**
 * @brief		Create Menu Extension : Entry
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		10 Mar 2014
 */

namespace IPS\blog\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension: Entry
 */
class _Entry
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{
		/* Posting limits */
		if ( \IPS\Member::loggedIn()->checkPostsPerDay() === FALSE )
		{
			return array();
		}
		
		$blogs = \IPS\blog\Blog::loadByOwner( \IPS\Member::loggedIn(), array( array( 'blog_disabled=?', 0 ) ) );
		if (
			\count( $blogs )
			or
			( \IPS\Settings::i()->club_nodes_in_apps and \count( \IPS\blog\Blog::clubNodes( 'add' ) ) )
		) {
			if ( !\IPS\Settings::i()->club_nodes_in_apps AND \count( $blogs ) === 1 )
			{
				$ourBlog = array_shift( $blogs );
				return array(
					'blog_entry' => array(
						'link' 	=> \IPS\Http\Url::internal( "app=blog&module=blogs&controller=submit&id=" . $ourBlog->_id, 'front', 'blog_submit' ),
					)
				);
			}
			else
			{
				return array(
					'blog_entry' => array(
						'link' 		=> \IPS\Http\Url::internal( "app=blog&module=blogs&controller=submit", 'front', 'blog_submit' ),
						'extraData'	=> array( 'data-ipsDialog' => true, 'data-ipsDialog-size' => "narrow", ),
						'title' 	=> 'select_blog'
					)
				);
			}
		}
		else
		{
			return array();
		}
	}
}