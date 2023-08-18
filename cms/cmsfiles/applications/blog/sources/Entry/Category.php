<?php
/**
 * @brief		Blog Entry Category Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		2 Aug 2019
 */

namespace IPS\blog\Entry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Blog Category
 */
class _Category extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'blog_entry_categories';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'entry_category_';

	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';

	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'blog_entry_categories';

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		$blog = \IPS\blog\Blog::load( $this->blog_id );

		if( $this->_url === NULL )
		{
			$this->_url = \IPS\Http\Url::internal( "app=blog&module=blogs&controller=view&id={$blog->id}&cat={$this->id}", 'front', 'blogs_blog_cat', array( $blog->seo_name, $this->seo_name ) );
		}

		return $this->_url;
	}
	
	/**
	 * Get HTML link
	 *
	 * @return	string
	 */
	public function link()
	{
		return \IPS\Theme::i()->getTemplate( 'global' )->categoryLink( $this );
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int			id			ID number
	 * @apiresponse	string		name		Name
	 * @apiresponse	string		url			URL
	 * @apiresponse	string		class		Node class
	 * @apiresponse	int			position	Node order
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$return = array(
			'id'		=> $this->id,
			'name'		=> $this->name,
			'url'		=> (string) $this->url(),
			'class'		=> \get_class( $this ),
			'position'	=> $this->position
		);

		return $return;
	}

	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		if( !$this->id )
		{
			$this->position = \IPS\Db::i()->select( 'MAX(entry_category_position)', 'blog_entry_categories', array( 'entry_category_blog_id=?', $this->blog_id ))->first() + 1;
		}

		return parent::save();
	}
}
