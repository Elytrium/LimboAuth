<?php
/**
 * @brief		Editor Extension: Record Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		20 Feb 2014
 */

namespace IPS\cms\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Record Content
 */
class _Widgets
{
	/**
	 * Can we use HTML in this editor?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canUseHtml( $member )
	{
		return NULL;
	}
	
	/**
	 * Can we use attachments in this editor?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canAttach( $member )
	{
		return TRUE;
	}

	/**
	 * Permission check for attachments
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	int|null	$id1		Primary ID
	 * @param	int|null	$id2		Secondary ID
	 * @param	string|null	$id3		Arbitrary data
	 * @param	array		$attachment	The attachment data
	 * @param	bool		$viewOnly	If true, just check if the user can see the attachment rather than download it
	 * @return	bool
	 */
	public function attachmentPermissionCheck( $member, $id1, $id2, $id3, $attachment, $viewOnly=FALSE )
	{
		if ( ! $id3 )
		{
			throw new \OutOfRangeException;
		}
		
		/* See if it's on a page in Pages */
		$pageId = $this->getPageIdFromWidgetUniqueId( $id3 );
		
		if ( $pageId !== NULL )
		{
			return \IPS\cms\Pages\Page::load( $pageId )->can( 'view', $member );
		}
		
		/* Still here? Look elsewhere */
		$area = $this->getAreaFromWidgetUniqueId( $id3 );
		
		if ( $area !== NULL )
		{
			return \IPS\Application\Module::get( $area[0], $area[1], 'front' )->can( 'view', $member );
		}
		
		/* Still here? */
		throw new \OutOfRangeException;
	}
	
	/**
	 * Attachment lookup
	 *
	 * @param	int|null	$id1	Primary ID
	 * @param	int|null	$id2	Secondary ID
	 * @param	string|null	$id3	Arbitrary data
	 * @return	\IPS\Http\Url|\IPS\Content|\IPS\Node\Model
	 * @throws	\LogicException
	 */
	public function attachmentLookup( $id1, $id2, $id3 )
	{
		$pageId = $this->getPageIdFromWidgetUniqueId( $id3 );
		
		if ( $pageId !== NULL )
		{
			return \IPS\cms\Pages\Page::load( $pageId );
		}
		
		$area = $this->getAreaFromWidgetUniqueId( $id3 );
		
		if ( $area !== NULL )
		{
			return \IPS\Application\Module::get( $area[0], $area[1] );
		}
		
		return FALSE;
	}
	
	/**
	 * Returns the page ID based on the widget's unique ID
	 *
	 * @param	string	$uniqueId	The widget's unique ID
	 * @return	null|int
	 */
	protected function getPageIdFromWidgetUniqueId( $uniqueId )
	{
		$pageId = NULL;
		foreach( \IPS\Db::i()->select( '*', 'cms_page_widget_areas' ) as $item )
		{
			$widgets = json_decode( $item['area_widgets'], TRUE );

			foreach( $widgets as $widget )
			{
				if ( $widget['unique'] == $uniqueId )
				{
					$pageId = $item['area_page_id'];
				}
			}
		}
		
		return $pageId;
	}
	
	/**
	 * Returns area information if the widget is not on a cms page
	 *
	 * @param	string	$uniqueId	The widget's unique ID
	 * @return	array|null			Index 0 = Application, Index 1 = Module, Index 2 = Controller
	 */
	protected function getAreaFromWidgetUniqueId( $uniqueId )
	{
		$return = NULL;
		foreach( \IPS\Db::i()->select( '*', 'core_widget_areas' ) AS $row )
		{
			$widgets = json_decode( $row['widgets'], TRUE );
			
			foreach( $widgets AS $widget )
			{
				if ( $widget['unique'] == $uniqueId )
				{
					$return = array( $row['app'], $row['module'], $row['controller'] );
				}
			}
		}
		
		return $return;
	}
}