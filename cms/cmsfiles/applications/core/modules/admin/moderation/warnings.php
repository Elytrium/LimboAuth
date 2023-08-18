<?php
/**
 * @brief		Warnings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Apr 2013
 */

namespace IPS\core\modules\admin\moderation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Warnings
 */
class _warnings extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\core\Warnings\Reason';

	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'warn_settings' );
		parent::execute();
	}
		
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{		
		/* Init */
		$activeTab = \IPS\Request::i()->tab ?: NULL;
		$activeTabContents = '';
		$tabs = array();
				
		/* Reasons */
		if ( \IPS\Settings::i()->warn_on and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'reasons_view' ) )
		{
			$tabs['reasons'] = 'warn_reasons';
			if ( $activeTab == 'reasons' )
			{
				parent::manage();
				$activeTabContents = \IPS\Output::i()->output;
			}
		}
		
		/* Actions */
		if ( \IPS\Settings::i()->warn_on and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'actions_view' ) )
		{
			$tabs['actions'] = 'warn_actions';
			if ( $activeTab == 'actions' )
			{
				/* Init */
				$table = new \IPS\Helpers\Table\Db( 'core_members_warn_actions', \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings' ) );
				$table->include = array( 'wa_points', 'wa_mq', 'wa_rpa', 'wa_suspend' );
				$table->sortBy        = $table->sortBy        ?: 'wa_points';
				$table->sortDirection = $table->sortDirection ?: 'asc';
				
				/* Row buttons */
				$table->rowButtons = function( $row )
				{
					$return = array();
					
					if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'actions_edit' ) )
					{
						$return['edit'] = array(
							'icon'	=> 'pencil',
							'link'	=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings&do=actionForm&id=' ) . $row['wa_id'],
							'title'	=> 'edit',
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') )
						);
					}
					
					if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'actions_delete' ) )
					{
						$return['delete'] = array(
							'icon'	=> 'times-circle',
							'link'	=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings&do=actionDelete&id=' ) . $row['wa_id'],
							'title'	=> 'delete',
							'data'	=> array( 'delete' => '' )
						);
					}
					
					return $return;
				};
				
				/* Parsers */
				$table->parsers = array(
					'wa_mq'	=> function( $val, $row )
					{
						return $val ? ( ( $val == -1 ) ? \IPS\Member::loggedIn()->language()->addToStack('indefinitely') : ( \IPS\Member::loggedIn()->language()->addToStack('for') . ' ' . $val . ' ' . ( $row['wa_mq_unit'] == 'd' ? \IPS\Member::loggedIn()->language()->addToStack('days') : \IPS\Member::loggedIn()->language()->addToStack('hours') ) ) ) : '-';
					},
					'wa_rpa'	=> function( $val, $row )
					{
						return $val ? ( ( $val == -1 ) ? \IPS\Member::loggedIn()->language()->addToStack('indefinitely') : ( \IPS\Member::loggedIn()->language()->addToStack('for') . ' ' . $val . ' ' . ( $row['wa_rpa_unit'] == 'd' ? \IPS\Member::loggedIn()->language()->addToStack('days') : \IPS\Member::loggedIn()->language()->addToStack('hours') ) ) ) : '-';
					},
					'wa_suspend'	=> function( $val, $row )
					{
						return $val ? ( ( $val == -1 ) ? \IPS\Member::loggedIn()->language()->addToStack('indefinitely') : ( \IPS\Member::loggedIn()->language()->addToStack('for') . ' ' . $val . ' ' . ( $row['wa_suspend_unit'] == 'd' ? \IPS\Member::loggedIn()->language()->addToStack('days') : \IPS\Member::loggedIn()->language()->addToStack('hours') ) ) ) : '-';
					}
				);
				
				/* Add button */
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'actions_add' ) )
				{
					$table->rootButtons = array(
						'add'	=> array(
							'icon'	=> 'plus',
							'link'	=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings&do=actionForm' ),
							'title'	=> 'add',
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') )
						)
					);
				}
				
				/* Display */
				$activeTabContents = (string) $table;
			}
		}

		/* Settings */
		$tabs['settings'] = 'settings';
		if ( $activeTab == 'settings' )
		{
			$form = new \IPS\Helpers\Form;
		
			$form->add( new \IPS\Helpers\Form\YesNo( 'warn_on', \IPS\Settings::i()->warn_on, FALSE, array( 'togglesOn' => array(
				'warn_protected',
				'warn_show_own',
				'warnings_acknowledge'
			) ) ) );
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'warn_protected', explode( ',', \IPS\Settings::i()->warn_protected ), FALSE, array( 'options' => \IPS\Member\Group::groups( TRUE, FALSE ), 'parse' => 'normal', 'multiple' => TRUE ), NULL, NULL, NULL, 'warn_protected' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'warn_show_own', \IPS\Settings::i()->warn_show_own, FALSE, array(), NULL, NULL, NULL, 'warn_show_own' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'warnings_acknowledge', \IPS\Settings::i()->warnings_acknowledge, FALSE, array(), NULL, NULL, NULL, 'warnings_acknowledge' ) );
			
			if ( $values = $form->values() )
			{
				$form->saveAsSettings();
				\IPS\Session::i()->log( 'acplog__warn_settings' );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings&tab=settings' ), 'saved' );
			}
			
			$activeTabContents = (string) $form;
		}		
				
		/* Add the blurb in */
		if ( $activeTab != 'settings' )
		{
			$activeTabContents = \IPS\Theme::i()->getTemplate( 'forms' )->blurb( 'warn_' . $activeTab . '_blurb', TRUE, TRUE ) . $activeTabContents;
		}
				
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('warnings');
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=moderation&controller=warnings" ) );
		}
	}
	
	/**
	 * Warn Action Form
	 *
	 * @return	void
	 */
	protected function actionForm()
	{
		$current = NULL;
		if ( \IPS\Request::i()->id )
		{
			$current = \IPS\Db::i()->select( '*', 'core_members_warn_actions', array( 'wa_id=?', \IPS\Request::i()->id ) )->first();
			\IPS\Dispatcher::i()->checkAcpPermission( 'actions_edit' );
		}
		
		if ( !$current )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'actions_add' );
		}
	
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Number( 'wa_points', ( $current ? $current['wa_points'] : 0 ), TRUE ) );
		foreach ( array( 'mq', 'rpa', 'suspend' ) as $k )
		{
			$form->add( new \IPS\Helpers\Form\Custom( 'wa_' . $k, ( $current ? array( $current[ 'wa_' . $k ], $current[ 'wa_' . $k . '_unit' ] ) : array( NULL, NULL ) ), FALSE, array(
				'getHtml'	=> function( $element )
				{
					return \IPS\Theme::i()->getTemplate( 'members' )->warningTime( $element->name, $element->value, 'for', 'indefinitely' );
				},
				'formatValue'=> function( $element )
				{
					if ( isset( $element->value[3] ) )
					{
						$element->value[0] = -1;
						$element->value[1] = 'h';
						unset( $element->value[3] );
					}
					return $element->value;
				}
			) ) );
		}
		$form->add( new \IPS\Helpers\Form\YesNo( 'wa_override', ( $current ? $current['wa_override'] : FALSE ) ) );
		
		if ( $values = $form->values() )
		{
			$save = array( 'wa_points' => $values['wa_points'], 'wa_override' => $values['wa_override'] );
			foreach ( array( 'mq', 'rpa', 'suspend' ) as $k )
			{
				$save[ 'wa_' . $k ] = \intval( $values[ 'wa_' . $k ][0] );
				$save[ 'wa_' . $k . '_unit' ] = $values[ 'wa_' . $k ][1];
			}
						
			if ( $current )
			{
				\IPS\Db::i()->update( 'core_members_warn_actions', $save, array( 'wa_id=?', $current['wa_id'] ) );
				\IPS\Session::i()->log( 'acplog__wa_edited', array( $save['wa_points'] => FALSE ) );
			}
			else
			{
				\IPS\Db::i()->insert( 'core_members_warn_actions', $save );
				\IPS\Session::i()->log( 'acplog__wa_created', array( $save['wa_points'] => FALSE ) );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings&tab=actions' ), 'saved' );
		}
		
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global' )->block( 'warn_action', $form, FALSE );
	}
	
	/**
	 * Delete Warn Action
	 *
	 * @return	void
	 */
	protected function actionDelete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'actions_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_members_warn_actions', array( 'wa_id=?', \IPS\Request::i()->id ) )->first();
			
			\IPS\Session::i()->log( 'acplog__wa_deleted', array( $current['wa_points'] => FALSE ) );
			\IPS\Db::i()->delete( 'core_members_warn_actions', array( 'wa_id=?', \IPS\Request::i()->id ) );
		}
		catch ( \UnderflowException $e ) { }
				
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=warnings&tab=actions' ), 'saved' );
	}
}