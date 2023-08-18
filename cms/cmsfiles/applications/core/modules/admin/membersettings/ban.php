<?php
/**
 * @brief		ban
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Apr 2013
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ban
 */
class _ban extends \IPS\Dispatcher\Controller
{	
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'ban_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		$table = new \IPS\Helpers\Table\Db( 'core_banfilters', \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=ban' ) );
		
		$table->filters = array(
				'ban_filter_ip'		=> 'ban_type=\'ip\'',
				'ban_filter_email'	=> 'ban_type=\'email\'',
				'ban_filter_name'	=> 'ban_type=\'name\''
		);
		
		$table->include    = array( 'ban_type', 'ban_content', 'ban_reason', 'ban_date' );
		$table->mainColumn = 'ban_content';
		$table->rowClasses = array( 'ban_reason' => array( 'ipsTable_wrap' ) );
		
		$table->sortBy        = $table->sortBy        ?: 'ban_date';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		$table->quickSearch   = 'ban_content';
		$table->advancedSearch = array(
			'ban_reason'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'ban_date'		=> \IPS\Helpers\Table\SEARCH_DATE_RANGE
		);
		
		/* Custom parsers */
		$table->parsers = array(
				'ban_date'			=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
				'ban_type'			=> function( $val, $row )
				{
					switch( $val )
					{
						default:
						case 'ip':
							return \IPS\Member::loggedIn()->language()->addToStack('ban_filter_ip_select');
						break;
						case 'email':
							return \IPS\Member::loggedIn()->language()->addToStack('ban_filter_email_select');
						break;
						case 'name':
							return \IPS\Member::loggedIn()->language()->addToStack('ban_filter_name_select');
						break;
					}
				}
		);
		
		/* Row buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();
		
			$return['edit'] = array(
						'icon'		=> 'pencil',
						'title'		=> 'edit',
						'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ),
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=ban&do=form&id=' ) . $row['ban_id'],
			);
			
		
		
			$return['delete'] = array(
						'icon'		=> 'times-circle',
						'title'		=> 'delete',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=ban&do=delete&id=' ) . $row['ban_id'],
						'data'		=> array( 'delete' => '' ),
			);
		
			return $return;
		};
		
		/* Specify the buttons */
		\IPS\Output::i()->sidebar['actions'] = array(
			'add'	=> array(
				'primary'	=> TRUE,
				'icon'		=> 'plus',
				'title'		=> 'ban_filter_add',
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('ban_filter_add') ),
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=ban&do=form' )
			)
		);
		
        /* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_membersettings_ban');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Add/Edit Rank
	 */
	public function form()
	{
		$current = NULL;
		if ( \IPS\Request::i()->id )
		{
			$current = \IPS\Db::i()->select( '*', 'core_banfilters', array( 'ban_id=?', \IPS\Request::i()->id ) )->first();
		}
	
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Select( 'ban_type', $current ? $current['ban_type'] : NULL , TRUE, array( 'options' => array(
			'ip'    => 'ban_filter_ip_select',
			'email' => 'ban_filter_email_select',
			'name'  => 'ban_filter_name_select'
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'ban_content', $current ? $current['ban_content'] : "", TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'ban_reason', $current ? $current['ban_reason'] : "" ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$save = array(
				'ban_type'    => $values['ban_type'],
				'ban_content' => $values['ban_content'],
				'ban_reason'  => $values['ban_reason'],
				'ban_date'	  => time()  
			);
				
			if ( $current )
			{
				unset( $save['ban_date'] );
				\IPS\Db::i()->update( 'core_banfilters', $save, array( 'ban_id=?', $current['ban_id'] ) );
				\IPS\Session::i()->log( 'acplog__ban_edited', array( 'ban_filter_' . $save['ban_type'] . '_select' => TRUE, $save['ban_content'] => FALSE ) );
			}
			else
			{
				\IPS\Db::i()->insert( 'core_banfilters', $save );
				\IPS\Session::i()->log( 'acplog__ban_created', array( 'ban_filter_' . $save['ban_type'] . '_select' => TRUE, $save['ban_content'] => FALSE ) );
			}
			
			unset( \IPS\Data\Store::i()->bannedIpAddresses );
	
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=ban' ), 'saved' );
		}
	
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( $current ? $current['ban_content'] : 'add', $form, FALSE );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_banfilters', array( 'ban_id=?', \IPS\Request::i()->id ) )->first();
			\IPS\Session::i()->log( 'acplog__ban_deleted', array( 'ban_filter_' . $current['ban_type'] . '_select' => TRUE, $current['ban_content'] => FALSE ) );
			\IPS\Db::i()->delete( 'core_banfilters', array( 'ban_id=?', \IPS\Request::i()->id ) );
			unset( \IPS\Data\Store::i()->bannedIpAddresses );
		}
		catch ( \UnderflowException $e ) { } 
	
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=ban' ) );
	}
}