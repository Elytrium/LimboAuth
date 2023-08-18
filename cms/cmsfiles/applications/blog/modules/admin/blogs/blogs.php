<?php
/**
 * @brief		Group Blogs Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\modules\admin\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * groupBlogs
 */
class _blogs extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\blog\Category';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'blogs_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		parent::manage();

		/* Root buttons */
		\IPS\Output::i()->sidebar['actions']['add'] = array(
			'primary'	=> true,
			'icon'		=> 'plus',
			'title'		=> 'blog_create_category',
			'link'		=> \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=blogs&do=form' ),
			'data'		=> array()
		);
	}
}