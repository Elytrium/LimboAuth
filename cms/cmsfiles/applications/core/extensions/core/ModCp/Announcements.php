<?php
/**
 * @brief		Moderator Control Panel Extension: Announcements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Oct 2013
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Announcements
 */
class _Announcements
{
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return string
	 */
	public function getTab()
	{
		/* Check Permissions */
		if ( ! \IPS\Member::loggedIn()->modPermission('can_manage_announcements') )
		{
			return null;
		}
		
		return 'announcements';
	}
	
	/**
	 * Manage Announcements
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Check Permissions */
		if ( ! \IPS\Member::loggedIn()->modPermission('can_manage_announcements') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '3S148/2', 403, '' );
		}
		
		$table = new \IPS\Helpers\Table\Content( '\IPS\core\Announcements\Announcement', \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=announcements' ) );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' ), 'announcementRow' );
		$table->include = array( 'announce_title' );
		$table->mainColumn = 'announce_title';
		$table->sortBy = 'announce_id';
		$table->sortDirection = 'desc';
		$table->sortOptions = array( 'announce_id' );
		$table->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_announcements' );

		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'modcp_announcements' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_announcements' );
		return \IPS\Theme::i()->getTemplate( 'modcp' )->announcements( (string) $table );
	}
	
	/**
	 * Add/Edit Announcement
	 *
	 * @return	void
	 */
	public function create()
	{
		$current = NULL;
		if ( \IPS\Request::i()->id )
		{
			$current = \IPS\core\Announcements\Announcement::load( \IPS\Request::i()->id );
		}
		
		$form = \IPS\core\Announcements\Announcement::form( $current );
		$form->class = 'ipsForm_vertical';
		$form->attributes = array( 'data-controller' => 'core.front.modcp.announcementForm' );
		
		if ( $values = $form->values() )
		{
			$announcement = \IPS\Core\Announcements\Announcement::_createFromForm( $values, $current );
				
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=announcements", 'front', 'modcp_announcements' ) );
		}
		
		if ( !\is_null( $current ) )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'edit_announcement' );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'add_announcement' );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=announcements' ), \IPS\Member::loggedIn()->language()->addToStack( 'modcp_announcements' ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, ( !\is_null( $current ) ) ? \IPS\Member::loggedIn()->language()->addToStack( 'edit_announcement' ) : \IPS\Member::loggedIn()->language()->addToStack( 'add_announcement' ) );
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Change Announcement Status
	 *
	 * @return	void
	 */
	public function status()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_manage_announcements' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C185/1', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$announcement = \IPS\core\Announcements\Announcement::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C185/4', 404, '' );
		}
		
		$announcement->active = ( $announcement->active === 1 ? 0 : 1 );
		$announcement->save();

		\IPS\Widget::deleteCaches( 'announcements', 'core' );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'OK' ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=announcements", 'front', 'modcp_announcements' ) );
		}
	}
	
	/**
	 * Delete Announcement
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_manage_announcements' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C185/2', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$announcement = \IPS\core\Announcements\Announcement::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C185/5', 404, '' );
		}
		
		$announcement->delete();
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=announcements", 'front', 'modcp_announcements' ) );
	}
}