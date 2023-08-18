<?php
/**
 * @brief		forums
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		07 Jan 2014
 */

namespace IPS\forums\modules\admin\forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * forums
 */
class _forums extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\forums\Forum';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'forums_manage' );
		parent::execute();
	}
	
	/**
	 * Permissions
	 *
	 * @return	void
	 */
	protected function permissions()
	{
		try
		{
			$forum = \IPS\forums\Forum::load( \IPS\Request::i()->id );
			
			if ( $forum->min_posts_view )
			{
				\IPS\Member::loggedIn()->language()->words['perm_forum_perm__view'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__view_rest', FALSE, array( 'pluralize' => array( $forum->min_posts_view ) ) );
			}
			
			if ( $forum->forums_bitoptions['bw_enable_answers'] )
			{
				if ( $forum->password and !$forum->can_view_others )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read2_qa_pass', FALSE );
				}
				elseif ( !$forum->can_view_others )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read2_qa', FALSE );
				}
				elseif ( $forum->password )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read_qa_pass', FALSE );
				}
				else
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read_qa', FALSE );
				}
			}
			else
			{
				if ( $forum->password and !$forum->can_view_others )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read2_pass', FALSE );
				}
				elseif ( !$forum->can_view_others )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read2', FALSE );
				}
				elseif ( $forum->password )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__read'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__read_pass', FALSE );
				}
			}
			
			if ( $forum->forums_bitoptions['bw_enable_answers'] )
			{
				if ( $forum->min_posts_post )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__add'] = \IPS\Member::loggedIn()->language()->addToStack('perm_forum_perm__add_qa_rest', FALSE, array( 'pluralize' => array( $forum->min_posts_post ) ) );
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__reply'] = \IPS\Member::loggedIn()->language()->addToStack('perm_forum_perm__reply_qa_rest', FALSE, array( 'pluralize' => array( $forum->min_posts_post ) ) );
				}
				else
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__add'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__add_qa', FALSE, array( 'pluralize' => array( $forum->min_posts_post ) ) );
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__reply'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__reply_qa', FALSE, array( 'pluralize' => array( $forum->min_posts_post ) ) );
				}
			}
			else
			{
				if ( $forum->min_posts_post )
				{
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__add'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__add_rest', FALSE, array( 'pluralize' => array( $forum->min_posts_post ) ) );
					\IPS\Member::loggedIn()->language()->words['perm_forum_perm__reply'] = \IPS\Member::loggedIn()->language()->addToStack( 'perm_forum_perm__reply_rest', FALSE, array( 'pluralize' => array( $forum->min_posts_post ) ) );
				}
			}
		}
		catch ( \OutOfRangeException $e ) {}
		
		return parent::permissions();
	}

	/**
	 * Form to add/edit a forum
	 *
	 * @return void
	 */
	protected function form()
	{
		parent::form();

		if ( \IPS\Request::i()->id )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('edit_forum') . ': ' . \IPS\Output::i()->title;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('add_forum');
		}
	}
}