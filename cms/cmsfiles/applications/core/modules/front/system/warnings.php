<?php
/**
 * @brief		Member Warnings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Jul 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Warnings
 */
class _warnings extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\core\Warnings\Warning';

	/**
	 * Rebuilt FURL
	 */
	protected $rebuiltUrl	= NULL;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		parent::execute();
		
		if ( !\IPS\Settings::i()->warn_on )
		{
			\IPS\Output::i()->error( 'warning_system_disabled', '2C184/7', 403, '' );
		}
	}
	
	/**
	 * View List
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Load the member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		if ( !$member->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '2C135/A', 403, '' );
		}
		
		/* Check permission */
		if ( !( \IPS\Settings::i()->warn_on AND ( \IPS\Member::loggedIn()->modPermission('mod_see_warn') or ( \IPS\Settings::i()->warn_show_own and \IPS\Member::loggedIn()->member_id == $member->member_id ) ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C135/9', 403, '' );
		}
		
		$table = new \IPS\Helpers\Table\Content( 'IPS\core\Warnings\Warning', \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&id={$member->member_id}", 'front', 'warn_list', $member->members_seo_name ), array( array( 'wl_member=?', $member->member_id ) ) );
		$table->rowsTemplate	  = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'warningRow' );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('members_warnings', FALSE, array( 'sprintf' => array( $member->name ) ) );
		\IPS\Output::i()->breadcrumb[] = array( $member->url(), $member->name );
		
		if( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'tables', 'core' )->container( (string) $table );	
		}
		else
		{
			\IPS\Output::i()->output = (string) $table;
		}
		
	}
	
	/**
	 * View Warning
	 *
	 * @return	void
	 */
	protected function view()
	{		
		/* Load the member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		if ( !$member->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '2C135/4', 403, '' );
		}
		
		/* Load it */
		try
		{
			$warning = \IPS\core\Warnings\Warning::loadAndCheckPerms( \IPS\Request::i()->w );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C184/3', 404, '' );
		}
		
		/* Show it */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'view_warning_details' );
		\IPS\Output::i()->breadcrumb[] = array( $member->url(), $member->name );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&id={$member->member_id}", NULL, 'warn_list', $member->members_seo_name ), \IPS\Member::loggedIn()->language()->addToStack('members_warnings', FALSE, array( 'sprintf' => array( $member->name ) ) ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'view_warning_details' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('modcp')->warnHovercard( $warning );
	}
		
	/**
	 * Warn
	 *
	 * @return	void
	 */
	protected function warn()
	{
		/* Load the member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		if ( !$member->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '2C135/2', 403, '' );
		}
		
		/* Permission Check */
		if ( !\IPS\Member::loggedIn()->canWarn( $member ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C184/6', 403, '' );
		}

		/* Build the form */
		$form = \IPS\core\Warnings\Warning::create();
		$form->class = 'ipsForm_vertical';
		$form->attributes = array( 'data-controller' => 'core.front.modcp.warnForm', 'data-member' => $member->member_id );
		$form->hiddenValues['ref'] = \IPS\Request::i()->ref;
		$form->hiddenValues['member'] = $member->member_id;
		
		/* Display */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_modcp.js', 'core' ) );
		$actions = \IPS\Db::i()->select( '*', 'core_members_warn_actions', NULL, 'wa_points ASC' );
		if ( \count( $actions ) )
		{
			$min = NULL;
			foreach ( $actions as $a )
			{
				if ( ( $a['wa_points'] - $member->warn_level ) > 1 )
				{
					$min = $a['wa_points'] - $member->warn_level;
				}
				break;
			}
			
			$form->addSidebar( \IPS\Theme::i()->getTemplate( 'modcp' )->warnActions( $actions, $member, $min ) );
		}
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('warn_member', FALSE, array( 'sprintf' => array( $member->name ) ) );
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Acknowledge Warning
	 *
	 * @return	void
	 */
	protected function acknowledge()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Load the member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		if ( !$member->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '2C184/4', 403, '' );
		}
		
		/* Load it */
		try
		{
			$warning = \IPS\core\Warnings\Warning::loadAndCheckPerms( \IPS\Request::i()->w );
			
			if ( $warning->member !== $member->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C184/5', 404, '' );
		}
				
		/* Acknowledge it */
		$warning->acknowledged = TRUE;
		$warning->save();
		$member->members_bitoptions['unacknowledged_warnings'] = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_members_warn_logs', array( "wl_member=? AND wl_acknowledged=0", $member->member_id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$member->save();
		
		/* Redirect */
		if ( $redirectTo = \IPS\Request::i()->referrer() )
		{
			\IPS\Output::i()->redirect( $redirectTo );
		}
		else
		{
			\IPS\Output::i()->redirect( $warning->url() );
		}
	}
	
	/**
	 * Revoke Warning
	 *
	 * @return	void
	 */
	protected function delete()
	{
		$class	= static::$contentModel;
		try
		{
			$item	= $class::loadAndCheckPerms( \IPS\Request::i()->w );
			$member	= \IPS\Member::load( $item->member );
			
			if ( $item->canDelete() )
			{
				if ( isset( \IPS\Request::i()->undo ) )
				{
					\IPS\Session::i()->csrfCheck();
					if ( \IPS\Request::i()->undo )
					{
						$item->undo();
					}
					$item->delete();
					\IPS\Output::i()->redirect( $member->url(), 'warn_revoked' );
				}
				else
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('modcp')->warningRevoke( $item );
				}
			}
			else
			{
				\IPS\Output::i()->error( 'generic_error', '2C184/1', 403, '' );
			}
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C184/2', 404, '' );
		}
	}
	
	/**
	 * Add Warning Form - AJAX response to reason select
	 *
	 * @return	void
	 */
	protected function reasonAjax()
	{
		/* Check permission */
		if ( ! ( \IPS\Settings::i()->warn_on AND \IPS\Member::loggedIn()->modPermission('mod_see_warn') ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C135/9', 403, '' );
		}
		
		$remove = array(
			'date'		=> NULL,
			'time'		=> NULL,
			'unlimited'	=> TRUE,
		);
		
		if ( \IPS\Request::i()->id == 'other' )
		{
			\IPS\Output::i()->json( array(
				'points'			=> 0,
				'points_override'	=> TRUE,
				'remove'			=> $remove,
				'remove_override'	=> TRUE,
				'notes'				=> NULL,
				'cheev_point_reduction' => 0,
				'cheev_override' => TRUE
			)	);
		}
		
		try
		{	
			$reason = \IPS\core\Warnings\Reason::load( \IPS\Request::i()->id );
			
			/* Add in the remove properties */
			if ( $reason->remove AND $reason->remove != -1 )
			{
				$date = \IPS\DateTime::create();
				if ( $reason->remove_unit == 'h' )
				{
					$date->add( new \DateInterval( "PT{$reason->remove}H" ) );
				}
				else
				{
					$date->add( new \DateInterval( "P{$reason->remove}D" ) );
				}
				
				$remove = array(
					'date'		=> $date->format( 'Y-m-d' ),
					'time'		=> $date->format( 'H:i' ),
					'unlimited'	=> FALSE
				);
			}
						
			\IPS\Output::i()->json( array(
				'points'			=> $reason->points,
				'points_override'	=> $reason->points_override,
				'remove'			=> $remove,
				'remove_override'	=> $reason->remove_override,
				'notes'				=> $reason->notes,
				'cheev_point_reduction' => $reason->cheev_point_reduction,
				'cheev_override' => $reason->cheev_override
			)	);
		}
		
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->json( array(
				'points'			=> 0,
				'points_override'	=> FALSE,
				'remove'			=> $remove,
				'remove_override'	=> FALSE,
				'notes'				=> NULL,
				'cheev_point_reduction' => 0,
				'cheev_override' => FALSE
			)	);
		}
	}
	
	/**
	 * Add Warning Form - AJAX response to points change
	 *
	 * @return	void
	 */
	protected function actionAjax()
	{
		$actions = array(
			'mq'	=> array(
				'date'		=> NULL,
				'time'		=> NULL,
				'unlimited'	=> FALSE,
			),
			'rpa'	=> array(
				'date'		=> NULL,
				'time'		=> NULL,
				'unlimited'	=> FALSE,
			),
			'suspend'	=> array(
				'date'		=> NULL,
				'time'		=> NULL,
				'unlimited'	=> FALSE,
			),
		);
		
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		
		/* Check permission */
		if ( !( \IPS\Settings::i()->warn_on AND ( \IPS\Member::loggedIn()->modPermission('mod_see_warn') or ( \IPS\Settings::i()->warn_show_own and \IPS\Member::loggedIn()->member_id == $member->member_id ) ) ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C135/9', 403, '' );
		}
		
		try
		{
			$action = \IPS\Db::i()->select( '*', 'core_members_warn_actions', array( 'wa_points<=?', ( $member->warn_level + \IPS\Request::i()->points ) ), 'wa_points DESC', 1 )->first();
			foreach ( array( 'mq', 'rpa', 'suspend' ) as $k )
			{
				if ( $action[ 'wa_' . $k ] == -1 )
				{
					$actions[ $k ]['unlimited'] = TRUE;
				}
				elseif ( $action[ 'wa_' . $k ] )
				{
					$date = \IPS\DateTime::ts( time() )->add( new \DateInterval( $action[ 'wa_' . $k . '_unit' ] == 'h' ? "PT{$action[ 'wa_' . $k ]}H" : "P{$action[ 'wa_' . $k ]}D" ) );
					
					$actions[ $k ]['date'] = $date->format( 'Y-m-d' );
					$actions[ $k ]['time'] = $date->format( 'H:i' );
				}
			}
		}
		catch ( \UnderflowException $e ) { }
		
		\IPS\Output::i()->json( array(
			'actions'	=> $actions,
			'override'	=> isset( $action ) ? $action['wa_override'] : \IPS\Member::loggedIn()->modPermission('warning_custom_noaction')
		)	);
	}
}