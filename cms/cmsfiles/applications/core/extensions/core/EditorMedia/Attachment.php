<?php
/**
 * @brief		Editor Media: Attachment
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		{date}
 */

namespace IPS\core\extensions\core\EditorMedia;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Media: Attachments
 */
class _Attachment
{
	/**
	 * Get Counts
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	string		$postKey	The post key
	 * @param	string|null	$search		The search term (or NULL for all)
	 * @return	int
	 */
	public function count( $member, $postKey, $search=NULL )
	{		
		$where = array(
			array( "attach_member_id=?", $member->member_id ),
		);
		if ( $postKey )
		{
			$where[] = array( 'attach_post_key<>?', $postKey );
		}
		if ( $search )
		{
			$where[] = array( "attach_file LIKE ( CONCAT( '%', ?, '%' ) )", $search );
		}
		
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments', $where )->first();
	}
	
	/**
	 * Get Files
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string|null	$search	The search term (or NULL for all)
	 * @param	string		$postKey	The post key
	 * @param	int			$page	Page
	 * @param	int			$limit	Number to get
	 * @return	array		array( 'Title' => array( 'http://www.example.com/file1.txt' => \IPS\File, 'http://www.example.com/file2.txt' => \IPS\File, ... ), ... )
	 */
	public function get( $member, $search, $postKey, $page, $limit )
	{
		$where = array(
			array( "attach_member_id=?", $member->member_id ),
		);
		if ( $postKey )
		{
			$where[] = array( 'attach_post_key<>?', $postKey );
		}
		if ( $search )
		{
			$where[] = array( "attach_file LIKE ( CONCAT( '%', ?, '%' ) )", $search );
		}
		
		$return = array();
		foreach ( \IPS\Db::i()->select( 'core_attachments.*', 'core_attachments', $where, 'attach_date DESC', array( ( $page - 1 ) * $limit, $limit ) ) as $row )
		{			
			$url = \IPS\Settings::i()->base_url . "applications/core/interface/file/attachment.php?id={$row['attach_id']}";
			if ( $row['attach_security_key'] )
			{
				$url .= "&key={$row['attach_security_key']}";
			}
			$obj = \IPS\File::get( 'core_Attachment', $row['attach_location'] );
			$obj->originalFilename = $row['attach_file'];
			
			if( $row['attach_thumb_location'] )
			{
				$obj->attachmentThumbnailUrl = \IPS\File::get( 'core_Attachment', $row['attach_thumb_location'] )->url;
			}

			$return[ $url ] = $obj;
		}
		
		return $return;
	}
	
	/**
	 * @brief	Loaded Extensions
	 */
	protected static $loadedExtensions = array();
	
	/**
	 * @brief	Locations
	 */
	public static $locations = array();
	
	/**
	 * Get locations
	 *
	 * @param	int	$attachId	The attachment ID
	 * @return	void
	 */
	public static function getLocations( $attachId )
	{
		if ( !isset( static::$locations[ $attachId ] ) )
		{
			static::$locations[ $attachId ] = array();
			
			$select = \IPS\Db::i()->select( '*', 'core_attachments_map', array( 'attachment_id=?', $attachId ) );
			$count = $select->count();
			foreach ( $select as $map )
			{				
				if ( !isset( static::$loadedExtensions[ $map['location_key'] ] ) )
				{
					$exploded = explode( '_', $map['location_key'] );
					try
					{
						$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'EditorLocations' );
						if ( isset( $extensions[ $exploded[1] ] ) )
						{
							static::$loadedExtensions[ $map['location_key'] ] = $extensions[ $exploded[1] ];
						}
					}
					catch ( \OutOfRangeException $e ) { }
					catch ( \UnexpectedValueException $e ) { }
				}
				
				if ( isset( static::$loadedExtensions[ $map['location_key'] ] ) )
				{

					if( method_exists( static::$loadedExtensions[$map['location_key']], 'attachmentLookup'))
					{
						try {
							$url = static::$loadedExtensions[$map['location_key']]->attachmentLookup($map['id1'], $map['id2'], $map['id3']);

							/* Test url() method to prevent BadMethodCallException from the template below - an attachment may be
								located within a Node class that doesn't support urls, such as CMS Blocks. */

							if ($url instanceof \IPS\Content or $url instanceof \IPS\Node\Model){
								$url->url();
							}

							static::$locations[$attachId][] = $url;
						} catch (\LogicException $e) {
						} catch (\BadMethodCallException $e) {
						}
					}
				}
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->attachmentLocations( static::$locations[ $attachId ] );
	}
}