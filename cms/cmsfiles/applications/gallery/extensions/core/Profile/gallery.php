<?php
/**
 * @brief		Profile extension: Gallery
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		02 Apr 2014
 */

namespace IPS\gallery\extensions\core\Profile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Profile extension: Gallery
 */
class _gallery
{
	/**
	 * Member
	 */
	protected \IPS\Member $member;
	
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
	public function showTab(): bool
	{
		$where = array( array( 'album_owner_id=?', $this->member->member_id ) );
		
		if( \count( \IPS\Member::loggedIn()->socialGroups() ) )
		{
			$where[] = array( '( album_type=1 OR ( album_type=2 AND album_owner_id=? ) OR ( album_type=3 AND ( album_owner_id=? OR ( album_allowed_access IS NOT NULL AND album_allowed_access IN(' . implode( ',', \IPS\Member::loggedIn()->socialGroups() ) . ') ) ) ) )', \IPS\Member::loggedIn()->member_id, \IPS\Member::loggedIn()->member_id );
		}
		else
		{
			$where[] = array( '( album_type=1 OR ( album_type IN (2,3) AND album_owner_id=? ) )', \IPS\Member::loggedIn()->member_id );
		}

		$where[] = array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_view', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'core_permission_index.perm_view=? )', '*' );
		
		$select = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', $where );
		$select->join( 'gallery_categories', array( "gallery_categories.category_id=gallery_albums.album_category_id" ) );
		$select->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=gallery_categories.category_id", 'gallery', 'category' ) );

		return (bool) $select->first();
	}
	
	/**
	 * Display
	 *
	 * @return	string
	 */
	public function render(): string
	{
		\IPS\gallery\Application::outputCss();
		
		$table = new \IPS\gallery\Album\Table( $this->member->url()->setQueryString( 'tab', 'node_gallery_gallery') );
		$table->setOwner( $this->member );
		$table->limit = 10;
		$table->sortBy = 'album_last_img_date';
		$table->sortDirection = 'desc';
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'gallery' ), 'profileAlbumTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'albums' );
		
		return (string) $table;
	}
}