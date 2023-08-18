<?php
/**
 * @brief		Announcement
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Oct 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Announcement
 */
class _announcement extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\core\Announcements\Announcement';
	
	/**
	 * View Announcement
	 *
	 * @return	void
	 */
	protected function manage()
	{
		parent::manage();
		
		/* Load announcement */
		try
		{
			$announcement = \IPS\core\Announcements\Announcement::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'announcement_missing', '2C199/1', 404, '' );
		}
		if ( !$announcement->canView() )
		{
			\IPS\Output::i()->error( 'node_error_no_perm', '2C199/2', 403, '' );
		}
		
		/* Display */
		$announcementHtml = \IPS\Theme::i()->getTemplate( 'system' )->announcement( $announcement );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $announcementHtml );
		}
		else
		{
			/* if the site is offline, use the minimal layout */
			if ( ( !\IPS\Settings::i()->site_online and !\IPS\Member::loggedIn()->group['g_access_offline'] ) OR ( !\IPS\Member::loggedIn()->member_id AND !\IPS\Member::loggedIn()->group['g_view_board'] ) )
			{
				\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
				\IPS\Output::i()->allowDefaultWidgets = FALSE;
				\IPS\Output::i()->sidebar['enabled'] = FALSE;
			}
			
			/* Set Session Location */
			\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=announcement&id=' . $announcement->id, NULL, 'announcement', $announcement->seo_title  ), array(), 'loc_viewing_announcement', array( $announcement->title => FALSE ) );
			
			/* Display */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $announcement->title );
			\IPS\Output::i()->output = $announcementHtml;
		}
	}

	/**
	 * Check permissions for linked content item
	 *
	 * @return	void
	 */
	protected function permissionCheck()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_manage_announcements' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C185/1', 403, '' );
		}

		try
		{
			$url = \IPS\Http\Url::createFromString( \IPS\Request::i()->url, TRUE );
		}
		catch( \IPS\Http\Url\Exception $e )
		{
			\IPS\Output::i()->json( array( 'status' => 'unexpected_format' ) );
		}

		/* Make sure this is a local URL */
		if( !( $url instanceof \IPS\Http\Url\Internal ) )
		{
			\IPS\Output::i()->json( array( 'status' => 'not_local' ) );
		}

		/* Get the definition */
		$furlDefinition = \IPS\Http\Url\Friendly::furlDefinition();

		/* If we don't have a validate callback, we can return NULL */
		if ( !isset( $furlDefinition[ $url->seoTemplate ]['verify'] ) or !$furlDefinition[ $url->seoTemplate ]['verify'] )
		{
			\IPS\Output::i()->json( array( 'status' => 'not_verified' ) );
		}

		$class = $furlDefinition[ $url->seoTemplate ]['verify'];
		$item = $class::loadFromUrl( $url );

		/* If the class does not have our method, return a not_verified status */
		if( !method_exists( $item, 'cannotViewGroups' ) )
		{
			\IPS\Output::i()->json( array( 'status' => 'not_verified' ) );
		}

		/* Get groups that cannot view the item */
		$groups = $item->cannotViewGroups();

		if( !$groups )
		{
			\IPS\Output::i()->json( array( 'status' => 'all_permissions' ) );
		}

		\IPS\Output::i()->json( array( 'html' => \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' )->announcementGroupCheck( $groups ) ) );
	}
}