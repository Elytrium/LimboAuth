<?php
/**
 * @brief		Profile extension: Blogs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		02 Apr 2014
 */

namespace IPS\blog\extensions\core\Profile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Profile extension: Blogs
 */
class _Blog
{
	/**
	 * Member
	 */
	protected $member;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member whose profile we are viewing
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = $member;
	}
	
	/**
	 * Is there content to display?
	 *
	 * @return	bool
	 */
	public function showTab()
	{
		$where = array(
			array( '(' . \IPS\Db::i()->findInSet( 'blog_groupblog_ids', $this->member->groups ) . ' OR ' . 'blog_member_id=? )', $this->member->member_id ),
			array( 'blog_disabled=0' )
		);
		
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$where[] = array( '( blog_social_group IS NULL OR blog_member_id=? OR blog_social_group IN(?) )', \IPS\Member::loggedIn()->member_id, \IPS\Db::i()->select( 'group_id', 'core_sys_social_group_members', array( 'member_id=?', \IPS\Member::loggedIn()->member_id ) ) );
		}
		else
		{
			$where[] = array( 'blog_social_group IS NULL' );
		}
		
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', $where )->first();
	}
	
	/**
	 * Display
	 *
	 * @return	string
	 */
	public function render()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'profile.css', 'blog', 'front' ) );
		
		$table = new \IPS\blog\Blog\ProfileTable( $this->member->url() );
		$table->setOwner( $this->member );
		$table->tableTemplate	= array( \IPS\Theme::i()->getTemplate( 'global', 'blog' ), 'profileBlogTable' );
		$table->rowsTemplate		= array( \IPS\Theme::i()->getTemplate( 'global', 'blog' ), 'profileBlogRows' );
		
		return (string) $table;
	}
}