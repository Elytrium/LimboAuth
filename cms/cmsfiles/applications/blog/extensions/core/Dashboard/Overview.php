<?php
/**
 * @brief		Dashboard extension: Overview
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		20 Mar 2014
 */

namespace IPS\blog\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Overview
 */
class _Overview
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/** 
	 * Return the block HTML show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		/* Basic stats */
		$data = array(
			'total_blogs'		=> (int) \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs' )->first(),
			'total_entries'		=> (int) \IPS\Db::i()->select( 'COUNT(*)', 'blog_entries' )->first(),
			'total_comments'	=> (int) \IPS\Db::i()->select( 'COUNT(*)', 'blog_comments' )->first(),
		);
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'dashboard', 'blog' )->overview( $data );
	}

	/** 
	 * Return the block information
	 *
	 * @return	array	array( 'name' => 'Block title', 'key' => 'unique_key', 'size' => [1,2,3], 'by' => 'Author name' )
	 */
	public function getInfo()
	{
		return array();
	}
}