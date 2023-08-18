<?php
/**
 * @brief		Forums API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		3 Apr 2017
 */

namespace IPS\forums\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Forums API
 */
class _forums extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Forum';
	
	/**
	 * GET /forums/forums
	 * Get list of forums
	 *
	 * @apiparam	int		clubs		0|1 Include club forums, default: 1
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage		Number of results per page - defaults to 25
	 * @note		For requests using an OAuth Access Token for a particular member, only forums the authorized user can view will be included
	 * @return		\IPS\Api\PaginatedResponse<IPS\forums\Forum>
	 */
	public function GETindex()
	{
		/* Return */
		return $this->_list();
	}

	/**
	 * GET /forums/forums/{id}
	 * Get specific forum
	 *
	 * @param		int		$id			ID Number
	 * @throws		1F363/1	INVALID_ID	The forum does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\forums\Forum
	 */
	public function GETitem( $id )
	{
		try
		{
			return $this->_view( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1F363/1', 404 );
		}
	}

	/**
	 * POST /forums/forums
	 * Create a forum
	 *
	 * @apiclientonly
	 * @reqapiparam	string		title				The forum title
	 * @apiparam	string		description			The forum description
	 * @apiparam	string		type				normal|qa|redirect|category
	 * @apiparam	int|null	parent				The ID number of the parent the forum should be created in. NULL for root.
	 * @apiparam	string		password			Forum password
	 * @apiparam	int			theme				Theme to use as an override
	 * @apiparam	int			sitemap_priority	1-9 1 highest priority
	 * @apiparam	int			min_content			The minimum amount of posts to be able to view
	 * @apiparam	int			can_see_others		0|1 Users can see topics posted by other users?
	 * @apiparam	object		permissions			An object with the keys as permission options (view, read, add, reply, attachments) and values as permissions to use (which may be * to grant access to all groups, or an array of group IDs to permit access to)
	 * @apiparam	string	redirect_url		If creating a redirect forum, the URL to redirect to.
	 * @return		\IPS\forums\Forum
	 * @throws		1F363/2	NO_TITLE	A title for the forum must be supplied
	 * @throws		1F363/3	NO_REDIRECT_URL	No redirect URL supplied.
	 * @throws		1F363/4	INVALID_TYPE		Invalid type of forum.
	 */
	public function POSTindex()
	{
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1F363/2', 400 );
		}

		return new \IPS\Api\Response( 201, $this->_create()->apiOutput( $this->member ) );
	}
	
	/**
	 * POST /forums/forums/{id}
	 * Edit a forum
	 *
	 * @apiclientonly
	 * @reqapiparam	string		title				The forum title
	 * @apiparam	string		description			The forum description
	 * @apiparam	string		type				normal|qa|redirect|category
	 * @apiparam	int|null	parent				The ID number of the parent the forum should be created in. NULL for root.
	 * @apiparam	string		password			Forum password
	 * @apiparam	int			theme				Theme to use as an override
	 * @apiparam	int			sitemap_priority	1-9 1 highest priority
	 * @apiparam	int			min_content			The minimum amount of posts to be able to view
	 * @apiparam	int			can_see_others		0|1 Users can see topics posted by other users?
	 * @apiparam	object		permissions			An object with the keys as permission options (view, read, add, reply, attachments) and values as permissions to use (which may be * to grant access to all groups, or an array of group IDs to permit access to)
	 * @apiparam	string	redirect_url		If editing a redirect forum, the URL to redirect too.
	 * @param		int		$id			ID Number
	 * @return		\IPS\forums\Forum
	 * @throws		1F363/3	NO_REDIRECT_URL	No redirect URL supplied.
	 * @throws		1F363/4	INVALID_TYPE		Invalid type of forum.
	 */
	public function POSTitem( $id )
	{
		$class = $this->class;
		$forum = $class::load( $id );
		
		return new \IPS\Api\Response( 200, $this->_createOrUpdate( $forum )->apiOutput( $this->member ) );
	}
	
	/**
	 * DELETE /forums/forums/{id}
	 * Delete a forum
	 *
	 * @apiclientonly
	 * @param		int			$id							ID Number
	 * @apiparam	int			deleteChildrenOrMove		The ID number of the new parent or -1 to delete all child nodes.
	 * @return		void
	 * @throws	1S359/1	INVALID_ID		The node ID does not exist
	 * @throws	1S359/2	INVALID_TARGET	The target node cannot be deleted because the new parent node does not exist
	 * @throws	1S359/3	HAS_CHILDREN	The target node cannot be deleted because it has children (pass deleteChildrenOrMove in the request to specify how to handle the children)
	 */
	public function DELETEitem( $id )
	{
		return $this->_delete( $id, \IPS\Request::i()->deleteChildrenOrMove ?? NULL );
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\Node\Model	$forum				The node
	 * @return	\IPS\Node\Model
	 */
	protected function _createOrUpdate( \IPS\Node\Model $forum )
	{
		foreach ( array( 'title' => "forums_forum_{$forum->id}", 'description' => "forums_forum_{$forum->id}_desc" ) as $fieldKey => $langKey )
		{
			if ( isset( \IPS\Request::i()->$fieldKey ) )
			{
				\IPS\Lang::saveCustom( 'forums', $langKey, \IPS\Request::i()->$fieldKey );

				if ( $fieldKey === 'title' )
				{
					$forum->name_seo = \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->$fieldKey );
				}
			}
		}

		if( isset( \IPS\Request::i()->parent ) )
		{
			$forum->parent_id = (int) \IPS\Request::i()->parent ?: -1;

			if ( !isset( \IPS\Request::i()->type ) )
			{
				if ( $forum->parent_id === -1 )
				{
					\IPS\Request::i()->type = 'category';
				}
				else
				{
					\IPS\Request::i()->type = 'normal';
				}
			}

			switch ( \IPS\Request::i()->type )
			{
				case 'category':
					$forum->sub_can_post = 0;
					break;

				case 'normal':
				case 'discussion':
					$forum->sub_can_post = 1;
					break;

				case 'qa':
				case 'questions':
					$forum->forums_bitoptions['bw_enable_answers'] = TRUE;
					break;

				case 'redirect':
					if ( !isset( \IPS\Request::i()->redirect_url ) )
					{
						throw new \IPS\Api\Exception( 'NO_REDIRECT_URL', '1F363/3', 400 );
					}

					$forum->redirect_on = 1;
					$forum->redirect_url = \IPS\Request::i()->redirect_url;
					break;

				default:
					throw new \IPS\Api\Exception( 'INVALID_TYPE', '1F363/4', 400 );
			}
		}

		if ( isset( \IPS\Request::i()->password ) )
		{
			$forum->password = \IPS\Request::i()->password;
		}

		if ( isset( \IPS\Request::i()->theme ) )
		{
			$forum->skin_id = \IPS\Request::i()->theme;
		}

		if ( isset( \IPS\Request::i()->sitemap_priority ) )
		{
			$forum->ipseo_priority = \IPS\Request::i()->sitemap_priority;
		}

		if ( isset( \IPS\Request::i()->min_content ) )
		{
			$forum->min_content = \IPS\Request::i()->min_content;
		}

		if ( isset( \IPS\Request::i()->can_see_others ) )
		{
			$forum->can_view_others = \IPS\Request::i()->can_see_others;
		}

		$forum = parent::_createOrUpdate( $forum );

		unset( \IPS\Data\Store::i()->forumsCustomNodes );

		return $forum;
	}
}