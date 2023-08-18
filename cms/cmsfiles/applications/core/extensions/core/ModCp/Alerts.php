<?php
/**
 * @brief		Moderator Control Panel Extension: Alerts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		12 May 2022
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Moderator Control Panel Extension: Alerts
 */
class _Alerts
{
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string|null
	 */
	public function getTab()
	{
		/* Check Permissions */
		if ( ! \IPS\Member::loggedIn()->modPermission('can_manage_alerts') )
		{
			return null;
		}

		return 'alerts';
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Check Permissions */
		if ( ! \IPS\Member::loggedIn()->modPermission('can_manage_alerts') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C427/1', 403, '' );
		}

		$table = new \IPS\Helpers\Table\Content( '\IPS\core\Alerts\Alert', \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=alerts' ) );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' ), 'alertRow' );
		$table->include = array( 'alert_title' );
		$table->mainColumn = 'alert_title';
		$table->sortBy = 'alert_id';
		$table->sortDirection = 'desc';
		$table->sortOptions = array( 'alert_id' );
		$table->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_alerts' );

		/* Filters */
		$table->filters = array(
			'alert_filter_active' => [ 'alert_enabled=1' ],
			'alert_filter_inactive' => [ 'alert_enabled=0' ],
			'alert_filter_viewed'	=> [ 'alert_viewed > 0 ' ],
			'alert_filter_not_viewed'	=> [ 'alert_viewed = 0 ' ],
			'alert_filter_groups'	=> [ "alert_recipient_type='group'"],
			'alert_filter_user'		=> [ "alert_recipient_type='user'"]
		);

		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'modcp_alerts' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_alerts' );
		return \IPS\Theme::i()->getTemplate( 'modcp' )->alerts( (string) $table );
	}

	/**
	 * Add/Edit Alert
	 *
	 * @return	void
	 */
	public function create()
	{
		$current = NULL;
		if ( \IPS\Request::i()->id )
		{
			$current = \IPS\core\Alerts\Alert::load( \IPS\Request::i()->id );
		}

		$form = \IPS\core\Alerts\Alert::form( $current );
		$form->class = 'ipsForm_vertical';

		if ( $values = $form->values() )
		{
			$alert = \IPS\core\Alerts\Alert::_createFromForm( $values, $current );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=alerts", 'front', 'modcp_alerts' ) );
		}

		if ( !\is_null( $current ) )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'edit_alert' );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'add_alert' );
		}

		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=alerts' ), \IPS\Member::loggedIn()->language()->addToStack( 'modcp_alerts' ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, ( !\is_null( $current ) ) ? \IPS\Member::loggedIn()->language()->addToStack( 'edit_alert' ) : \IPS\Member::loggedIn()->language()->addToStack( 'add_alert' ) );
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}

	/**
	 * Delete Alert
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_manage_alerts' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C427/2', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		try
		{
			$alert = \IPS\core\Alerts\Alert::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C427/3', 404, '' );
		}

		$alert->delete();

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=alerts", 'front', 'modcp_alerts' ) );
	}

	/**
	 * Change Author
	 *
	 * @return	void
	 */
	public function changeAuthor()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_manage_alerts' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C427/4', 403, '' );
		}

		try
		{
			$alert = \IPS\core\Alerts\Alert::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C427/5', 404, '' );
		}

		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'author', $alert->author(), TRUE ) );
		$form->class .= 'ipsForm_vertical';

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$alert->changeAuthor( $values['author'] );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=alerts", 'front', 'modcp_alerts' ) );
		}

		/* Display form */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}

	/**
	 * Change Alert Status
	 *
	 * @return	void
	 */
	public function status()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_manage_alerts' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C427/6', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		try
		{
			$alert = \IPS\core\Alerts\Alert::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C427/7', 404, '' );
		}

		$alert->enabled = ( $alert->enabled === 1 ? 0 : 1 );
		$alert->save();

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'OK' ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&tab=alerts", 'front', 'modcp_alerts' ) );
		}
	}
}