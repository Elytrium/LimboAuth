<?php
/**
 * @brief		System Logs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Mar 2016
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Error Logs
 */
class _systemLogs extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'system_logs_view' );
		parent::execute();
	}

	/**
	 * Manage Error Logs
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Button to settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'support', 'diagnostic_log_settings' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'prunesettings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=logSettings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
			);
		}

		/* Button to view filebased logs */
		$dir = \IPS\Log::fallbackDir();
		if ( !\IPS\NO_WRITES and is_dir( $dir ) )
		{
			$hasFiles = FALSE;
			$dir = new \DirectoryIterator( $dir );
			foreach ( $dir as $file )
			{
				if ( mb_substr( $file, 0, 1 ) !== '.' and $file != 'index.html' )
				{
					$hasFiles = TRUE;
					break;
				}
			}
			
			if ( $hasFiles )
			{
				\IPS\Output::i()->sidebar['actions']['files'] = array(
					'title'		=> 'log_view_file_logs',
					'icon'		=> 'search',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=fileLogs' ),
				);
			}
		}


		/* Create table */
		$table = new \IPS\Helpers\Table\Db( 'core_log', \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ) );
		$table->langPrefix = 'log_';
		$table->include = array( 'time', 'category', 'message' );
		$table->parsers = array(
			'message'	=> function( $val, $row )
			{
				if ( mb_strlen( $val ) > 100 )
				{
					$val = mb_substr( $val, 0, 100 ) . '...';
				}
				if ( $row['exception_class'] )
				{
					$val = "{$row['exception_class']} ({$row['exception_code']})\n{$val}";
				}

				$val = htmlspecialchars( $val, ENT_DISALLOWED, 'UTF-8', FALSE );
				
				return $val;
			},
			'time'		=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			},
			'member_id'	=> function ( $val )
			{
				return htmlspecialchars( \IPS\Member::load( $val )->name, ENT_DISALLOWED, 'UTF-8', FALSE );
			}
		);
		$table->sortBy = $table->sortBy ?: 'time';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		$table->quickSearch = 'message';
		$table->advancedSearch = array(
			'category'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => iterator_to_array( \IPS\Db::i()->select( 'DISTINCT(category) AS cat', 'core_log' )->setKeyField( 'cat' )->setValueField( 'cat' ) ), 'multiple' => TRUE, 'parse' => 'normal' ) ),
			'message'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'time'		=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'member_id'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
			'url'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT
		);
		$table->rowButtons = function( $row ) {
			return array(
				'view'		=> array(
					'title'	=> 'view',
					'icon'	=> 'search',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=view' )->setQueryString( 'id', $row['id'] )
				),
				'delete'	=> array(
					'title'	=> 'delete',
					'icon'	=> 'times-circle',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=delete' )->setQueryString( 'id', $row['id'] )->csrf(),
					'data'	=> array( 'delete' => '' )
				)
			);
		};
		
		/* Display */		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('r__system_logs');
		\IPS\Output::i()->output = $table;
	}
	
	/**
	 * View a log
	 * 
	 * @return void
	 */
	protected function view()
	{
		/* Load */
		try
		{
			$log = \IPS\Log::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C324/1', 404, '' );
		}
		
		/* Delete button */
		\IPS\Output::i()->sidebar['actions']['delete'] = array(
			'icon'	=> 'times-circle',
			'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=delete' )->setQueryString( 'id', $log->id )->csrf(),
			'title'	=> 'delete',
			'data'	=> array( 'confirm' => '' )
		);
		
		/* Display */
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('r__system_logs');
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs" ), \IPS\Member::loggedIn()->language()->addToStack('r__system_logs') );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->systemLogView( $log );
	}

	/**
	 * Delete all logs
	 * 
	 * @return void
	 */
	protected function deleteAll()
	{
		/* Delete */
		\IPS\Db::i()->query( "TRUNCATE core_log" );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ), 'deleted' );
	}
	
	/**
	 * Delete a log
	 * 
	 * @return void
	 */
	protected function delete()
	{
		/* Load */
		try
		{
			$log = \IPS\Log::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C324/2', 404, '' );
		}

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		/* Delete */
		$log->delete();
		
		/* Log and redirect */
		if ( $log->category )
		{
			\IPS\Session::i()->log( 'acplog__log_delete', array( $log->category => FALSE, ( (string) \IPS\DateTime::ts( $log->time ) ) => FALSE ) );
		}
		else
		{
			\IPS\Session::i()->log( 'acplog__log_delete_uncategoried', array( ( (string) \IPS\DateTime::ts( $log->time ) ) => FALSE ) );
		}
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ), 'deleted' );
	}
	
	/**
	 * View File-based Logs list
	 *
	 * @return	void
	 */
	protected function fileLogs()
	{
		/* NO_WRITES check */
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C324/8', 403, '' );
		}
		
		/* Get list of files */
		$dir = \IPS\Log::fallbackDir();
		$source = array();
		if ( is_dir( $dir ) )
		{
			$directoryIterator = new \DirectoryIterator( $dir );
			foreach ( $directoryIterator as $file )
			{
				if ( mb_substr( $file, 0, 1 ) !== '.' and $file != 'index.html' )
				{
					$source[] = array( 'time' => $file->getMTime(), 'file' => (string) $file );
				}
			}
		}
		
		/* Create table */
		$table = new \IPS\Helpers\Table\Custom( $source, \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=fileLogs' ) );
		$table->langPrefix = 'log_';
		$table->parsers = array(
			'time'		=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			}
		);
		$table->sortBy = $table->sortBy ?: 'time';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		$table->rowButtons = function( $row ) {
			return array(
				'view'		=> array(
					'title'	=> 'view',
					'icon'	=> 'search',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=viewFile' )->setQueryString( 'file', $row['file'] )
				),
				'delete'	=> array(
					'title'	=> 'delete',
					'icon'	=> 'times-circle',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=deleteFile' )->setQueryString( 'file', $row['file'] )->csrf(),
					'data'	=> array( 'delete' => '' )
				)
			);
		};
		
		/* Display */		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs" ), \IPS\Member::loggedIn()->language()->addToStack('r__system_logs') );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('file_logs') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('file_logs');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms' )->blurb( \IPS\Member::loggedIn()->language()->addToStack('log_view_file_logs_info', FALSE, array( 'sprintf' => $dir ) ) ) . $table;
	}
	
	/**
	 * View File-based Log
	 *
	 * @return	void
	 */
	protected function viewFile()
	{
		/* NO_WRITES check */
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C324/3', 403, '' );
		}
		
		/* Try to find it */
		$file = \IPS\Log::fallbackDir() . DIRECTORY_SEPARATOR . preg_replace( '/[^a-z_0-9\.]/i', '', \IPS\Request::i()->file );
		if ( !is_file( $file ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C324/5', 404, '' );
		}
		
		/* Delete button */
		\IPS\Output::i()->sidebar['actions']['delete'] = array(
			'icon'	=> 'times-circle',
			'link'	=> \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=deleteFile' )->setQueryString( 'file', \IPS\Request::i()->file )->csrf(),
			'title'	=> 'delete',
			'data'	=> array( 'confirm' => '' )
		);
		
		/* Display */
		\IPS\Output::i()->title	 = basename( $file );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs" ), \IPS\Member::loggedIn()->language()->addToStack('r__system_logs') );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs&do=fileLogs" ), \IPS\Member::loggedIn()->language()->addToStack('log_view_file_logs') );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->systemLogFileView( file_get_contents( $file ) );
	}
	
	/**
	 * View File-based Log
	 *
	 * @return	void
	 */
	protected function deleteFile()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* NO_WRITES check */
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C324/4', 403, '' );
		}
		
		/* Try to find it */
		$file = \IPS\Log::fallbackDir() . DIRECTORY_SEPARATOR . preg_replace( '/[^a-z_0-9\.]/i', '', \IPS\Request::i()->file );
		if ( !is_file( $file ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C324/6', 404, '' );
		}
		
		/* Delete it */
		if ( !@unlink( $file ) )
		{
			\IPS\Output::i()->error( 'log_file_could_not_delete', '1C324/7', 403, '' );
		}
		
		/* Log and redirect */
		\IPS\Session::i()->log( 'acplog__log_delete_file', array( basename( $file ) => FALSE ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=fileLogs' ), 'deleted' );
	}
	
	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function logSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'diagnostic_log_settings' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_system', \IPS\Settings::i()->prune_log_system, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), function( $val ) {
			if( $val > 0 AND $val < 7 )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_interval_min_d', FALSE, array( 'pluralize' => array( 6 ) ) ) );
			}
		}, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_moderator' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__systemlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('systemlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'systemlogssettings', $form, FALSE );
	}
}