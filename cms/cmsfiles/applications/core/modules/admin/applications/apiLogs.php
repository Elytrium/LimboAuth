<?php
/**
 * @brief		API Logs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		04 Dec 2015
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * apiReference
 */
class _apiLogs extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'api_logs' );
		parent::execute();
	}

	/**
	 * View Logs
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init table */
		$table = new \IPS\Helpers\Table\Db( 'core_api_logs', \IPS\Http\Url::internal('app=core&module=applications&controller=api&tab=apiLogs') );
		$table->langPrefix = 'api_log_';
		
		/* Columns */
		$table->include = array( 'date', 'endpoint', 'api_key', 'ip_address', 'response_code' );
		$table->parsers = array(
			'date'		=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			},
			'endpoint'	=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate('api')->apiKey( $row['method'] . ' ' . $val );
			},
			'api_key'	=> function( $val, $row )
			{
				$apiKey = NULL;
				$client = NULL;
				$member = NULL;
				if ( $row['api_key'] )
				{
					try
					{
						$apiKey = \IPS\Api\Key::load( $row['api_key'] );
					}
					catch ( \OutOfRangeException $e ) { }
				}
				if ( $row['client_id'] )
				{
					try
					{
						$client = \IPS\Api\OAuthClient::load( $row['client_id'] );
					}
					catch ( \OutOfRangeException $e ) { }
					
					if ( $row['member_id'] )
					{
						$member = \IPS\Member::load( $row['member_id'] );
					}
				}
				
				return \IPS\Theme::i()->getTemplate('api')->apiLogCredentials( $row, $apiKey, $client, $member );
			},
			'response_code'	=> function( $val )
			{
				return $val . ' ' . \IPS\Output::$httpStatuses[ $val ];
			}
		);
		
		/* Default sort */
		$table->sortBy = $table->sortBy ?: 'date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Filters */
		$table->filters = array(
			'api_log_success'	=> array( 'response_code LIKE ?', '2%' ),
			'api_log_fail'		=> array( 'response_code NOT LIKE ?', '2%' ),
		);
		
		/* Search */
		$endpoints = array( '' => 'any' );
		foreach ( \IPS\Api\Controller::getAllEndpoints() as $k => $data )
		{
			$endpoints[ $data['title'] ] = $data['title'];
		}
		$statuses = array( '' => 'any' );
		foreach ( \IPS\Output::$httpStatuses as $code => $name )
		{
			$statuses[ $code ] = "{$code} {$name}";
		}
		$table->advancedSearch = array(
			'date'			=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'endpoint'		=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $endpoints ), function( $val )
			{
				$exploded = explode( ' ', $val );
				return array( 'method=? AND endpoint=?', $exploded[0], trim( $exploded[1], '/' ) );
			} ),
			'api_key'		=> array( \IPS\Helpers\Table\SEARCH_NODE, array( 'class' => 'IPS\Api\Key' ) ),
			'ip_address'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'response_code'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $statuses ) ),
		);
		
		/* Buttons */
		$table->rowButtons = function( $row )
		{
			$return = array(
				'view'	=> array(
					'icon'	=> 'search',
					'title'	=> 'view',
					'link'	=> \IPS\Http\Url::internal('app=core&module=applications&controller=apiLogs&do=view')->setQueryString( 'id', $row['id'] ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => $row['method'] . ' ' . $row['endpoint'] )
				)
			);
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'api_logs_delete' ) )
			{
				$return['delete'] = array(
					'icon'	=> 'times-circle',
					'title'	=> 'delete',
					'link'	=> \IPS\Http\Url::internal('app=core&module=applications&controller=apiLogs&do=delete')->setQueryString( 'id', $row['id'] ),
					'data'	=> array( 'delete' => '' )
				);
			}
			return $return;
		};
		
		/* Display */
		if ( !isset( \IPS\Request::i()->advancedSearchForm ) )
		{
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'api_logs_settings' ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms' )->blurb( \IPS\Member::loggedIn()->language()->addToStack( 'api_log_blurb_change', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->api_log_prune ) ) ), TRUE, TRUE ) . $table;
			}
			else
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms' )->blurb( \IPS\Member::loggedIn()->language()->addToStack( 'api_log_blurb', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->api_log_prune ) ) ), TRUE, TRUE ) . $table;
			}
		}
		else
		{
			\IPS\Output::i()->output = $table;
		}
	}
	
	/**
	 * View Log
	 *
	 * @return	void
	 */
	protected function view()
	{
		try
		{
			$log = \IPS\Db::i()->select( '*', 'core_api_logs', array( 'id=?', \IPS\Request::i()->id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C293/1', 404, '' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('api')->viewLog( $log['request_data'], $log['response_output'] );
	}
	
	/**
	 * Delete Log
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'api_logs_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$log = \IPS\Db::i()->select( '*', 'core_api_logs', array( 'id=?', \IPS\Request::i()->id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C293/2', 404, '' );
		}
		
		\IPS\Db::i()->delete( 'core_api_logs', array( 'id=?', $log['id'] ) );
		
		\IPS\Session::i()->log( 'acplog__api_log_deleted', array( $log['id'] => FALSE ) );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=applications&controller=api&tab=apiLogs') );
	}
	
	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'api_logs_settings' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'api_log_prune', \IPS\Settings::i()->api_log_prune, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplogs__api_log_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=applications&controller=api&tab=apiLogs') );
		}
		
		\IPS\Output::i()->output = $form;
	}	
}