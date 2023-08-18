<?php
/**
 * @brief		Profile extension: galleryImages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		03 Nov 2022
 */

namespace IPS\gallery\extensions\core\Profile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Profile extension: galleryImages
 */
class _galleryImages
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
		return TRUE;
	}

	/**
	 * Display
	 *
	 * @return	string
	 */
	public function render(): string
	{
		$table ="";
		foreach ( \IPS\Application::load( 'gallery' )->extensions( 'core', 'ContentRouter' ) as $ext )
		{
			$table = $ext->customTableHelper( 'IPS\gallery\Image', $this->member->url()->setQueryString( 'tab', 'node_gallery_galleryImages'), array( array( 'image_member_id=? and image_album_id=0', $this->member->member_id ) ) );
		}

		return (string) $table;
	}
}