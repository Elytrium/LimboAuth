<?php
/**
 * @brief		Health Dashboard
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 December 2020
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Health dashboard
 */
class _support extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Define the "large log table" size in bytes
	 */
	public const LARGE_LOG_TABLE_SIZE = 2147483648;	// 2GB

	/**
	 * @brief	Define the number of log repeats considered high
	 */
	public const LARGE_NUMBER_LOG_REPEATS = 10;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'get_support' );
		parent::execute();
	}

	/**
	 * Support Wizard
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Build the guide search form */
		$form = new \IPS\Helpers\Form( 'form', 'continue' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Text( 'support_advice_search', NULL, NULL, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('health__guides_form') ), NULL, NULL, NULL, 'support_advice_search' ) );
		$hooks = \IPS\Db::i()->select( 'COUNT(*)', 'core_hooks', array( \IPS\Db::i()->in( 'app', \IPS\IPS::$ipsApps, TRUE ) . ' OR app IS NULL' ) )->first();

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('get_support');
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support/dashboard.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_support.js', 'core', 'admin') );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'support' )->dashboard( $this->_getBlocks(), (string) $this->getLogChart(), $form, $this->_getFeaturedGuides(), $this->_getBulletins(), $hooks );
	}

	/**
	 * Nulled: delete logs
	 *
	 * @return	void
	 */
	public function clearLogs() {

		$form = new \IPS\Helpers\Form( 'form', 'nulled_delete_logs' );

		$options = array(
			'system' => \IPS\Member::loggedIn()->language()->addToStack('health_system_log_title'),
			'error' => \IPS\Member::loggedIn()->language()->addToStack('health_error_log_title'),
			'email' => \IPS\Member::loggedIn()->language()->addToStack('health_email_error_log_title')
		);

		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'nulled_delete_logs', '', TRUE, array( 'options' => $options, 'multiple' => TRUE, 'impliedUnlimited' => TRUE ) ) );

		if ( $values = $form->values() )
		{
			foreach ($values['nulled_delete_logs'] as $key => $value) {
				
				if ( $value == 'system' ) {

					try
					{
						\IPS\Db::i()->delete( "core_log" );
					}
					catch( \Exception $e )
					{
						\IPS\Log::log('Truncate table core_log filed');
					}
				}
				elseif( $value == 'error' ) {
					
					try
					{
						\IPS\Db::i()->delete( "core_error_logs" );
					}
					catch( \Exception $e )
					{
						\IPS\Log::log('Truncate table core_error_logs filed');
					}
				}
				else {

					try
					{
						\IPS\Db::i()->delete( "core_mail_error_logs" );
					}
					catch( \Exception $e )
					{
						\IPS\Log::log('Truncate table core_mail_error_logs filed');
					}
				}
			}

			if ( \IPS\Request::i()->isAjax() )
			{
				return;
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs' ), 'deleted' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('nulled_delete_logs');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'nulled_delete_logs', $form, FALSE );
	}

	/**
	 * Get featured guides
	 *
	 * @return	void
	 */
	protected function _getFeaturedGuides()
	{
		try
		{
			$response = \IPS\Http\Url::ips( 'guides' )->setQueryString( 'featured', 1 )->request()->get();

			if( $response->httpResponseCode !== 200 )
			{
				throw new \IPS\Http\Request\Exception;
			}

			return $response->decodeJson();
		}
		catch( \IPS\Http\Request\Exception $e )
		{
			return array();
		}
	}

	/**
	 * Get AdminCP bulletins
	 *
	 * @return	void
	 */
	protected function _getBulletins()
	{
		try
		{
			/* We will get the last 10 bulletins, process if they apply based on the condition to show and whether they are less than 12 months old, and return the most recent 3 */
			$bulletins = array();
			foreach( \IPS\Db::i()->select( '*', 'core_ips_bulletins', NULL, 'id DESC', 10 ) as $bulletin )
			{
				/* If it's a year old or older, inherently ignore */
				if( $bulletin['cached'] < time() - ( 60 * 60 * 24 * 365 ) )
				{
					continue;
				}

				if( $bulletin['min_version'] AND $bulletin['min_version'] > \IPS\Application::load('core')->long_version )
				{
					continue;
				}

				if( $bulletin['max_version'] AND $bulletin['max_version'] < \IPS\Application::load('core')->long_version )
				{
					continue;
				}

				/* If not cached in the last hour, check it still exists */
				try
				{
					if( ( time() - $bulletin['cached'] ) > 3600 )
					{
						$request = \IPS\Http\Url::ips("bulletin/{$bulletin['id']}")->request( 2 )->get();

						switch( (int) $request->httpResponseCode )
						{
							case 410:
									\IPS\Db::i()->delete( 'core_ips_bulletins', [ 'id=?', $bulletin['id'] ] );
									continue 2;
								break;
							default:
									\IPS\Db::i()->update( 'core_ips_bulletins', [ 'cached' => ( time() + 3600 - 900 ) ], [ 'id=?', $bulletin['id'] ] );
								break;
						}
					}
				}
				catch( \IPS\Http\Request\Exception $e ) {}

				/* If we have conditions, process them */
				if( $bulletin['conditions'] )
				{
					try
					{
						$show = @eval( $bulletin['conditions'] );
					}
					catch ( \Exception | \Throwable $e )
					{
						$show = FALSE;
					}
				}
				else
				{
					$show = TRUE;
				}

				if( $show )
				{
					$bulletins[] = $bulletin;
				}

				/* If we have 3, stop now */
				if( \count( $bulletins ) === 3 )
				{
					break;
				}
			}

			return $bulletins;
		}
		catch( \IPS\Db\Exception $e )
		{
			return array();
		}
	}

	/**
	 * Search guides
	 *
	 * @return void
	 */
	protected function guideSearch()
	{
		\IPS\Output::i()->json( array() );
	}

	/**
	 * Get the block
	 *
	 * @return	void
	 */
	protected function getBlock()
	{
		/* If we are fixing things, run CSRF check */
		if( \IPS\Request::i()->fix )
		{
			\IPS\Session::i()->csrfCheck();
		}

		$blockName = '_showBlock' . mb_convert_case( \IPS\Request::i()->block, MB_CASE_TITLE );

		if( \method_exists( $this, $blockName ) )
		{
			\IPS\Output::i()->json( $this->$blockName() );
		}
		else
		{
			\IPS\Output::i()->error( 'block_not_found', '3C338/3', 404 );
		}
	}

	/**
	 * Get block: PHP
	 *
	 * @return	void
	 */
	protected function _showBlockPhp()
	{
		$requirements = \IPS\CIC ? array( 'list' => array(), 'failures' => 0, 'advice' => 0 ) : $this->_checkRequirements( 'PHP' );

		/* Reformat entries if they exist */
		if( isset( $requirements['list']['version'] ) )
		{
			$requirements['list']['version']['element']	= 'version';
			$requirements['list']['version']['body']	= $requirements['list']['version']['detail'];
			$requirements['list']['version']['detail']	= \IPS\Member::loggedIn()->language()->addToStack( $requirements['list']['version']['critical'] ? 'health_check_update_required' : 'health_check_update_recommended' );
		}

		foreach( $requirements['list'] as $k => $v )
		{
			if( $v['advice'] )
			{
				$requirements['list'][ $k ]['element']	= $k;
				$requirements['list'][ $k ]['body']		= $v['detail'];

				switch( $k )
				{
					case 'php':
						$requirements['list'][ $k ]['detail'] = \IPS\Member::loggedIn()->language()->addToStack( 'health_check_update_recommended' );
					break;

					case 'curl':
						$requirements['list'][ $k ]['detail'] = \IPS\Member::loggedIn()->language()->addToStack( 'health_check_curlupdate_recommended' );
					break;

					default:
						$requirements['list'][ $k ]['detail'] = \IPS\Member::loggedIn()->language()->addToStack( 'health__php_extension', FALSE, array( 'sprintf' => array( $k ) ) );
				}
			}
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Get block: MySQL
	 *
	 * @return	void
	 */
	protected function _showBlockMysql()
	{
		/* Check other requirements */
		$requirements = \IPS\CIC ? array( 'list' => array(), 'failures' => 0, 'advice' => 0 ) : $this->_checkRequirements( 'MySQL' );

		/* Check whether there are any db changes needed */
		$databaseChanges = $this->_databaseChecker( (bool) \IPS\Request::i()->fix );

		if( $databaseChanges )
		{
			$requirements['failures']++;
			$requirements['list'][] = array(
				'critical'		=> TRUE,
				'advice'		=> FALSE,
				'success'		=> FALSE,
				'link'			=> \IPS\Http\Url::internal( "app=core&module=support&controller=support&do=getBlock&block=mysql&fix=1" ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health_database_check_fail')
			);
		}

		/* Reformat entries if they exist */
		if( isset( $requirements['list']['compact'] ) )
		{
			$requirements['list']['compact']['element']	= 'compact';
			$requirements['list']['compact']['body']	= $requirements['list']['compact']['detail'];
			$requirements['list']['compact']['detail']	= \IPS\Member::loggedIn()->language()->addToStack('health_database_compact_fail');
		}

		if( isset( $requirements['list']['version'] ) )
		{
			$requirements['list']['version']['element']	= 'version';
			$requirements['list']['version']['body']	= $requirements['list']['version']['detail'];
			$requirements['list']['version']['detail']	= \IPS\Member::loggedIn()->language()->addToStack( $requirements['list']['version']['critical'] ? 'health_check_update_required' : 'health_check_update_recommended' );
		}

		if ( !\IPS\CIC AND \count( iterator_to_array( \IPS\Db::i()->query( "SHOW TABLE STATUS WHERE Engine!='InnoDB'" ) ) ) )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'element'		=> 'innodb',
				'body'			=> \IPS\Member::loggedIn()->language()->addToStack('health_innodb_details'),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__mysql_innodb')
			);
		}

		if( \IPS\Settings::i()->getFromConfGlobal('sql_utf8mb4') !== TRUE )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'element'		=> 'utf8mb4',
				'body'			=> \IPS\Member::loggedIn()->language()->addToStack( \IPS\CIC ? 'utf8mb4_generic_explain_cic' : 'utf8mb4_generic_explain' ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__mysql_utf8mb4')
			);
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Get block: Vapid
	 *
	 * @return	array
	 */
	protected function _showBlockVapid()
	{
		/* Check other requirements */
		$requirements = array( 'list' => array(), 'failures' => 0, 'advice' => 0 );

		if( ! \IPS\CIC2 and ! \function_exists('gmp_init') )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'element'		=> 'vapidNoGmp',
				'body'			=> \IPS\Member::loggedIn()->language()->addToStack('acp_notifications_cannot_use_web_push'),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health_vapid_gmp_check_fail')
			);
		}
		elseif ( ! \IPS\Settings::i()->vapid_public_key )
		{
			$requirements['failures']++;
			$requirements['list'][] = array(
				'critical' => TRUE,
				'advice' => FALSE,
				'success' => FALSE,
				'link'	  => \IPS\Http\Url::internal( "app=core&module=support&controller=support&do=vapidKeys" )->csrf(),
				'skipDialog'	=> TRUE,
				'detail' => \IPS\Member::loggedIn()->language()->addToStack( 'health_vapid_key_check_fail' )
			);
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Create new vapid keys
	 *
	 * @return void
	 */
	protected function vapidKeys()
	{
		\IPS\Session::i()->csrfCheck();

		if ( ! \IPS\Settings::i()->vapid_public_key )
		{
			try
			{
				$vapid = \IPS\Notification::generateVapidKeys();
				\IPS\Settings::i()->changeValues( array('vapid_public_key' => $vapid['publicKey'], 'vapid_private_key' => $vapid['privateKey']) );
			}
			catch ( \Exception $ex )
			{
				\IPS\Log::log( $ex, 'create_vapid_keys' );
				\IPS\Output::i()->error( '', '2C338/4', 403, \IPS\Member::loggedIn()->language()->addToStack( 'health_vapid_key_check_fail_exception', FALSE, [ 'sprintf' => $ex->getMessage() ] ) );
			}
		}

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=support' ), 'health_vapid_key_check_fail_fixed' );
		}
	}

	/**
	 * Get block: Invision Community
	 *
	 * @return	void
	 */
	protected function _showBlockVersion()
	{
		$requirements = array( 'advice' => 0, 'failures' => 0, 'list' => array() );

		/* Check for updates available */
		if( $updates = $this->_checkUpgrades() )
		{
			if( \is_array( $updates ) )
			{
				$requirements['list'][] = array(
					'critical'		=> FALSE,
					'advice'		=> FALSE,
					'success'		=> FALSE,
					'element'		=> 'patch',
					'body'			=> \IPS\Theme::i()->getTemplate( 'support' )->patchAvailable( $updates ),
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('upgrade_check_patchavail'),
					'button'		=> array( 'lang' => 'upgrade_apply_patch', 'href' => \IPS\Http\Url::internal( "app=core&module=system&controller=upgrade&_new=1&patch=1" ), 'css' => 'ipsButton_intermediate' )
				);
			}
			else
			{
				if( $updates === -1 )
				{
					$requirements['failures']++;
				}
				else
				{
					$requirements['advice']++;
				}

				$requirements['list'][] = array(
					'critical'		=> ( $updates === -1 ),
					'advice'		=> !( $updates === -1 ),
					'success'		=> FALSE,
					'link'			=> \IPS\Http\Url::internal( "app=core&module=system&controller=upgrade&_new=1" ),
					'skipDialog'	=> TRUE,
					'detail'		=> ( $updates === -1 ) ? \IPS\Member::loggedIn()->language()->addToStack('upgrade_check_security') : \IPS\Member::loggedIn()->language()->addToStack('upgrade_check_fail')
				);
			}
		}
		else
		{
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> FALSE,
				'success'		=> TRUE,
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('upgrade_check_ok')
			);
		}

		/* Run MD5 check */
		$modifiedFiles = $this->_md5Checker( (bool) \IPS\Request::i()->fix );

		if( $modifiedFiles )
		{
			$requirements['failures']++;
			$requirements['list'][] = array(
				'critical'		=> TRUE,
				'advice'		=> FALSE,
				'success'		=> FALSE,
				'link'			=> \IPS\Http\Url::internal( "app=core&module=support&controller=support&do=getBlock&block=version&fix=1" ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('md5_check_fail')
			);
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Get block: Hook Scanner
	 *
	 * @return	void
	 */
	final protected function _showBlockHookscanner()
	{
		$requirements = array( 'advice' => 0, 'failures' => 0, 'list' => array() );
		/* Do custom class's overloaded method signatures work on php8? */
		try
		{
			$scannerResult = \IPS\Application\Scanner::scanCustomizationIssues( false, true, 500, array( 'shallowCheckEnabledOnly' => true, 'enabledOnly' => false ) );
			if ( $scannerResult OR $scannerResult === false )
			{
				\IPS\core\AdminNotification::send( 'core', 'ManualInterventionMessage' );
				if( (bool) $scannerResult )
				{
					$requirements['failures']++;
				}
				else
				{
					$requirements['advice']++;
				}

				$requirements['list'][] = array(
					'critical'		=> (bool) $scannerResult,
					'advice'		=> !$scannerResult,
					'success'		=> FALSE,
					'link'			=> \IPS\Http\Url::internal( "app=core&module=support&controller=support&do=getBlock&block=methodcheck&fix=1" ),
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack( 'method_check_fail' ),
					'dialogTitle'   => \IPS\Member::loggedIn()->language()->addToStack( 'method_check_fix' ),
					'dialogSize'    => 'large'
				);
			}
		} catch ( \Exception $e ) {}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Get block: Hook Check. Only returns when "fixing" in the dialog
	 *
	 * @return	array
	 */
	protected function _showBlockMethodcheck()
	{
		if ( !\IPS\Request::i()->fix AND !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=support&controller=support" ) );
		}

		$foundIssues = \IPS\Application\Scanner::scanCustomizationIssues( false, false, 500, array( 'enabledOnly' => false ) )[0];
		$issues = array();
		foreach ( $foundIssues as $appOrPlugin => $classes )
		{
			$components = explode( "-", $appOrPlugin );
			$isApp = \mb_strtolower( trim( $components[0] ) ) === 'app';
			$appDir = trim( $components[1] );
			$appOrPluginId = $appDir;
			/* Plugins are loaded by the ID not directory */
			if ( !$isApp )
			{
				try
				{
					$appOrPluginId = (int) \IPS\Db::i()->select( 'plugin_id', 'core_plugins', [ 'plugin_location=?', $appDir ], null, 1 )->first();
				}
				catch ( \UnderflowException $e )
				{
					continue;
				}
			}

			foreach ( $classes as $classFile => $classIssues )
			{
				foreach( $classIssues as $classIssue )
				{
					$issues[] = array(
						'type'              => $isApp ? 'app' : 'plugin',
						'app'               => $appOrPluginId,
						'reason'            => $classIssue['reason'],
						'scanner_method'    => $classIssue['class'] . '::' . $classIssue['method'] . '()',
						'parameter'         => $classIssue['parameter'],
						'subclassFile'      => $classIssue['subclassFile'] . ':' . $classIssue['subclassMethod']['lineNumber'],
						'baseFile'          => $classIssue['baseFile'] . ':' . $classIssue['baseMethod']['lineNumber'],
					);
				}
			}
		}
		
		if ( empty( $issues ) )
		{
			$table = \IPS\Member::loggedIn()->language()->addToStack( 'methodscanner_no_issues' );
		}
		else
		{
			$table = new \IPS\Helpers\Table\Custom( $issues, \IPS\Http\Url::internal( 'app=core&module=support&controller=support&do=getBlock&block=methodcheck' ) );
			$table->langPrefix = 'method_';
			$table->simplePagination = true;

			$table->parsers = array(
				'app'       => function ( $val, $row ) {
					try
					{
						return $row['type'] === 'app' ?
							\IPS\Application::load( $row['app'] )->_title :
							htmlspecialchars( \is_integer( $row['app'] ? \IPS\Plugin::load( $row['app'] )->name : $row['app'] ), ENT_DISALLOWED, 'UTF-8', FALSE );
					}
					catch ( \Exception $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'method_class_none' );
					}
				},
				'type'      => function ( $val, $row ) {
					return \IPS\Member::loggedIn()->language()->addToStack( 'hooks_hooks_' . $row['type'] );
				},
				'reason'    => function ( $val, $row ) {
					$reason = \IPS\Member::loggedIn()->language()->addToStack( $row['reason'] );
					$reasonDesc = \IPS\Member::loggedIn()->language()->addToStack( $row['reason'] . '_desc' );
					return "<span data-ipstooltip style='cursor:pointer' title=\"$reasonDesc\">$reason <i class=\"fa fa-info-circle\"></i></span>";
				},
				'parameter' => function ( $val, $row ) {
					if ( $row['parameter'] ?? 0 )
					{
						return '$' . $row['parameter'];
					}
					return \IPS\Member::loggedIn()->language()->addToStack( 'method_na' );
				},
				'priority' => function ( $val, $row ) {
					return \IPS\Member::loggedIn()->language()->addToStack( $val ? 'yes' : 'no' );
				}
			);
		}


		if ( !\IPS\Request::i()->fix AND \IPS\Request::i()->isAjax() )
		{
			return (string) $table;
		}
		return \IPS\Theme::i()->getTemplate( 'support' )->methodIssues( $table );
	}


	/**
	 * Endpoint to get just the chart
	 *
	 * @return  void
	 */
	protected function methodCheck()
	{
		/* Trick the method into giving its chart */
		$fix = \IPS\Request::i()->fix ?? null;
		\IPS\Request::i()->fix = 1;

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'method_check_fix' );
		\IPS\Output::i()->output = $this->_showBlockMethodcheck();
		if ( $fix === null )
		{
			unset( \IPS\Request::i()->fix );
		}
		else
		{
			\IPS\Request::i()->fix = $fix;
		}
	}

	/**
	 * Get block: Third Party
	 *
	 * @return	void
	 */
	protected function _showBlockThirdparty()
	{
		$requirements = array( 'advice' => 0, 'failures' => 0, 'list' => array() );

		$count = $this->_getThirdPartyCount();

		if( $count )
		{
			$requirements['advice']++;
		}

		$requirements['list'][] = array(
			'critical'		=> FALSE,
			'advice'		=> (bool) $count,
			'success'		=> FALSE,
			'link'			=> \IPS\Http\Url::internal( 'app=core&module=support&controller=support&do=thirdparty' ),
			'detail'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__thirdparty_count', FALSE, array( 'pluralize' => array( $count ) ) ),
			'dialogTitle'	=> 'health_thirdparty_disabled'
		);

		$appUpdates		= 0;
		$pluginUpdates	= 0;

		foreach( \IPS\Application::applications() as $app )
		{
			if( !\in_array( $app->directory, \IPS\IPS::$ipsApps ) AND $app->availableUpgrade( TRUE ) )
			{
				$appUpdates++;
			}
		}

		foreach( \IPS\Plugin::plugins() as $plugin )
		{
			if( $plugin->update_check_data )
			{
				$data	= json_decode( $plugin->update_check_data, TRUE );

				if( !empty( $data['longversion'] ) AND $data['longversion'] > $plugin->version_long )
				{
					$pluginUpdates++;
				}
			}
		}

		if( $appUpdates )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'link'			=> \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__thirdparty_appupdates', FALSE, array( 'sprintf' => array( $appUpdates ) ) )
			);
		}

		if( $pluginUpdates )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'link'			=> \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins' ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__thirdparty_pluginupdates', FALSE, array( 'sprintf' => array( $pluginUpdates ) ) )
			);
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Get block: Caching
	 *
	 * @return	void
	 */
	protected function _showBlockCaching()
	{
		$requirements = array( 'advice' => 0, 'failures' => 0, 'list' => array() );

		/* Check if Redis is being used */
		$redis = NULL;
		
		if ( !\IPS\CIC and \IPS\CACHE_METHOD == 'Redis' or \IPS\STORE_METHOD == 'Redis' )
		{
			try
			{
				$redis = \IPS\Redis::i()->info();
			}
			catch( \RedisException $e )
			{
				$requirements['failures']++;
				$requirements['list'][] = array(
					'critical'		=> TRUE,
					'advice'		=> FALSE,
					'success'		=> FALSE,
					'element'		=> 'redisfail',
					'body'			=> \IPS\Member::loggedIn()->language()->addToStack('health__cache_redisfail_detail'),
					'button'		=> array( 'lang' => 'health_view_redis_config', 'href' => \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=datastore' ), 'css' => 'ipsButton_intermediate' ),
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__cache_redisfail')
				);
			}
		}

		if( $redis and !\IPS\CIC )
		{
			if( isset( $redis['total_system_memory'] ) OR isset( $redis['maxmemory'] ) )
			{
				$detail = \IPS\Member::loggedIn()->language()->addToStack('health__cache_redis', FALSE, array( 'sprintf' => array( $redis['redis_version'], $redis['used_memory_human'], $redis['maxmemory'] ? $redis['maxmemory_human'] : $redis['total_system_memory_human'] ) ) );
			}
			else
			{
				$detail = \IPS\Member::loggedIn()->language()->addToStack('health__cache_redis_nototal', FALSE, array( 'sprintf' => array( $redis['redis_version'], $redis['used_memory_human'] ) ) );
			}

			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> FALSE,
				'success'		=> FALSE,
				'link'			=> \IPS\Http\Url::internal( 'app=core&module=support&controller=redis' ),
				'detail'		=> $detail,
				'dialogTitle'	=> 'health__more_information',
			);
		}

		/* Make a request so we can inspect the response headers */
		try
		{
			$request = \IPS\Http\Url::internal( "app=core&module=system&controller=metatags&do=manifest", "front", "manifest" )
				->request()
				->get();

			$headerKeys = \is_array( $request->httpHeaders ) ? array_map( 'mb_strtolower', array_keys( $request->httpHeaders ) ) : [];

			if( \in_array( 'cf-cache-status', $headerKeys ) )
			{
				$requirements['list'][] = array(
					'critical'		=> FALSE,
					'advice'		=> FALSE,
					'success'		=> FALSE,
					'learnmore'     => TRUE,
					'dialogTitle'   => \IPS\Member::loggedIn()->language()->addToStack( 'health_learn_more'),
					'element'		=> 'cloudflare',
					'body'			=> \IPS\Member::loggedIn()->language()->addToStack('health__cache_cloudflare_details'),
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__cache_cloudflare')
				);
			}

			if( \in_array( 'x-varnish', $headerKeys ) )
			{
				$requirements['list'][] = array(
					'critical'		=> FALSE,
					'advice'		=> FALSE,
					'success'		=> FALSE,
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__cache_varnish')
				);
			}

			if( \in_array( 'x-akamai-transformed', $headerKeys ) )
			{
				$requirements['list'][] = array(
					'critical'		=> FALSE,
					'advice'		=> FALSE,
					'success'		=> FALSE,
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__cache_akamai')
				);
			}
		}
		catch( \IPS\Http\Request\Exception $e ) { }

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Get block: Server
	 *
	 * @return	void
	 */
	protected function _showBlockServer()
	{
		if( \IPS\CIC )
		{
			return array(
				'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( array() ),
				'criticalIssues'	=> 0,
				'recommendedIssues'	=> 0
			);
		}

		$writeablesKey	= \IPS\Member::loggedIn()->language()->addToStack('requirements_file_system');
		$requirements	= $this->_checkRequirements( $writeablesKey );

		/* Windows server? */
		if( \strtoupper( \substr( PHP_OS, 0, 3 ) ) === 'WIN' )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'element'		=> 'windows',
				'body'			=> \IPS\Member::loggedIn()->language()->addToStack('health__server_windows'),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__server_windows_title')
			);
		}

		/* Reformat some entries */
		if( isset( $requirements['list']['tmp'] ) )
		{
			$requirements['list']['tmp']['element']	= 'tmp';
			$requirements['list']['tmp']['body']	= $requirements['list']['tmp']['detail'];
			$requirements['list']['tmp']['detail']	= \IPS\Member::loggedIn()->language()->addToStack( 'health__server_tmp' );
		}

		if( isset( $requirements['list']['suhosin'] ) )
		{
			$requirements['list']['suhosin']['element']	= 'suhosin';
			$requirements['list']['suhosin']['body']	= $requirements['list']['suhosin']['detail'];
			$requirements['list']['suhosin']['detail']	= \IPS\Member::loggedIn()->language()->addToStack( 'health__server_suhosin' );
		}

		foreach ( array( 'applications', 'datastore', 'plugins', 'uploads' ) as $dir )
		{
			if( isset( $requirements['list'][ $dir ] ) )
			{
				$requirements['list'][ $dir ]['element']	= $dir;
				$requirements['list'][ $dir ]['body']		= $requirements['list'][ $dir ]['detail'];
				$requirements['list'][ $dir ]['detail']		= \IPS\Member::loggedIn()->language()->addToStack( 'health__server_filesystem', FALSE, array( 'sprintf' => array( $dir ) ) );
			}
		}

		foreach( array_keys( $requirements['list'] ) as $key )
		{
			if( mb_strpos( $key, 'filesystem' ) === 0 )
			{
				$class = \IPS\File::getClass( (int) mb_substr( $key, 10 ) );
				$requirements['list'][ $key ]['element']	= $key;
				$requirements['list'][ $key ]['body']		= $requirements['list'][ $key ]['detail'];
				$requirements['list'][ $key ]['detail']		= \IPS\Member::loggedIn()->language()->addToStack( 'health__server_filestorage', FALSE, array( 'sprintf' => array( $class->displayName( $class->configuration ) ) ) );
			}
		}

		/* Check connections and server time */
		try
		{
			$result = \intval( (string) \IPS\Http\Url::ips( 'connectionCheck' )->request()->get() );
		}
		catch ( \Exception $e )
		{
			$result = (string) $e->getMessage();
		}

		if( !\is_int( $result ) )
		{
			$requirements['failures']++;
			$requirements['list'][] = array(
				'critical'		=> TRUE,
				'advice'		=> FALSE,
				'success'		=> FALSE,
				'element'		=> 'connection',
				'body'			=> \IPS\Theme::i()->getTemplate( 'support' )->fixConnection( $result ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('connection_check_fail')
			);
		}
		else if( abs( $result - time() ) > 30 )
		{
			$requirements['failures']++;
			$requirements['list'][] = array(
				'critical'		=> TRUE,
				'advice'		=> FALSE,
				'success'		=> FALSE,
				'element'		=> 'servertime',
				'body'			=> \IPS\Member::loggedIn()->language()->addToStack('sever_time_fail_desc', FALSE, array( 'sprintf' => array( (string)  new \IPS\DateTime ) ) ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('server_time_fail')
			);
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}
	
	/**
	 * Return all IPS log tables which can become quite large
	 *
	 * @return array<string, \IPS\Http\Url>
	 */
	public function getLogTables(): array
	{
		return [
			'core_log' => \IPS\Http\Url::internal( 'app=core&module=support&controller=systemLogs&do=logSettings' ),
			'core_error_logs' => \IPS\Http\Url::internal( 'app=core&module=support&controller=errorLogs&do=settings&searchResult=prune_log_error' ),
			'core_mail_error_logs' => \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogSettings' ),
			'core_edit_history' => \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=general&searchResult=edit_log_prune' ),
			'core_api_logs' => \IPS\Http\Url::internal( 'app=core&module=applications&controller=apiLogs&do=settings' ),
		];
	}
	
	/**
	 * Get block: Logs
	 *
	 * @return	void
	 */
	protected function _showBlockLogs()
	{
		$requirements = array( 'advice' => 0, 'failures' => 0, 'list' => array() );
		
		foreach( $this->getLogTables() as $table => $url )
		{
			$size = $this->_getLogTableSize( $table );

			if( $size === NULL OR $size > static::LARGE_LOG_TABLE_SIZE )
			{
				if( $size !== NULL )
				{
					$size = \IPS\Output\Plugin\Filesize::humanReadableFilesize( $size );
				}
				else
				{
					$size = \IPS\Member::loggedIn()->language()->addToStack('unavailable');
				}

				$requirements['failures']++;
				$requirements['list'][] = array(
					'critical'		=> TRUE,
					'advice'		=> FALSE,
					'success'		=> FALSE,
					'element'		=> $table . 'logtablesize',
					'body'			=> \IPS\Member::loggedIn()->language()->addToStack('health__logs_large_desc', FALSE, array( 'sprintf' => array( $table, $size, (string) $url ) ) ),
					'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__logs_large', FALSE, array( 'sprintf' => array( $table ) ) )
				);
			}
		}

		/* Check the last 500 system logs for reoccurring entries */
		$lastIds		= iterator_to_array( \IPS\Db::i()->select( 'id', 'core_log', NULL, 'id DESC', 500 ) );
		$repeatedLogs	= array();

		foreach( \IPS\Db::i()->select( 'message, COUNT(*) as occurrences', 'core_log', array( \IPS\Db::i()->in( 'id', $lastIds ) ), 'occurrences DESC', NULL, 'message' ) as $log )
		{
			if( $log['occurrences'] > static::LARGE_NUMBER_LOG_REPEATS )
			{
				$repeatedLogs[ $log['message'] ] = $log['occurrences'];
			}
		}

		if( \count( $repeatedLogs ) )
		{
			$requirements['advice']++;
			$requirements['list'][] = array(
				'critical'		=> FALSE,
				'advice'		=> TRUE,
				'success'		=> FALSE,
				'element'		=> 'repeatedlogs',
				'body'			=> \IPS\Theme::i()->getTemplate( 'support' )->fixRepeatLogs( $repeatedLogs ),
				'detail'		=> \IPS\Member::loggedIn()->language()->addToStack('health__logs_repeats'),
				'button'		=> array( 'lang' => 'health_view_system_log', 'href' => \IPS\Http\Url::internal( "app=core&module=support&controller=systemLogs" ), 'css' => 'ipsButton_intermediate' )
			);
		}

		return array(
			'html'				=> \IPS\Theme::i()->getTemplate( 'support' )->supportBlockList( $requirements['list'] ),
			'criticalIssues'	=> $requirements['failures'],
			'recommendedIssues'	=> $requirements['advice']
		);
	}

	/**
	 * Generic requirements check
	 *
	 * @param	string	$category	Requirements category
	 * @return	array
	 */
	protected function _checkRequirements( $category )
	{
		/* Check required and recommended PHP versions and extensions */
		$requirements = \IPS\core\Setup\Upgrade::systemRequirements();

		$failedRequirements		= 0;
		$failedRecommendations	= 0;
		$listItems				= array();

		if( !empty( $requirements['requirements'] ) AND !empty( $requirements['requirements'][ $category] ) )
		{
			foreach( $requirements['requirements'][ $category ] as $key => $requirement )
			{
				if( !$requirement['success'] )
				{
					$failedRequirements++;
					$listItems[ $key ] = array(
						'critical'		=> TRUE,
						'advice'		=> FALSE,
						'success'		=> FALSE,
						'link'			=> NULL,
						'detail'		=> $requirement['message']
					);

					if( isset( $requirement['short'] ) )
					{
						$listItems[ $key ]['element']	= $key;
						$listItems[ $key ]['body']		= $listItems[ $key ]['detail'];
						$listItems[ $key ]['detail']	= $requirement['short'];
					}
				}
			}
		}

		if( !empty( $requirements['advice'] ) AND !empty( $requirements['advice'][ $category ] ) )
		{
			foreach( $requirements['advice'][ $category ] as $key => $requirement )
			{
				$failedRecommendations++;
				$listItems[ $key ] = array(
					'critical'		=> FALSE,
					'advice'		=> TRUE,
					'success'		=> FALSE,
					'link'			=> NULL,
					'detail'		=> $requirement
				);
			}
		}

		return array( 'failures' => $failedRequirements, 'advice' => $failedRecommendations, 'list' => $listItems );
	}

	/**
	 * Check for upgrades/patches
	 *
	 * @return	bool|int|array
	 */
	protected function _checkUpgrades()
	{
		return FALSE;
	}

	/**
	 * Clear caches for nulled
	 *
	 * @return void
	 */
	protected function clearCachesNull()
	{
		/* Check CSRF Key*/
		\IPS\Session::i()->csrfCheck();

		/* URL for redirect */
		$md = \IPS\Request::i()->md;
		$ct = \IPS\Request::i()->ct;

		/* Clear JS Maps first */
		\IPS\Output::clearJsFiles();
		
		/* Reset theme maps to make sure bad data hasn't been cached by visits mid-setup */
		\IPS\Theme::deleteCompiledCss();
		\IPS\Theme::deleteCompiledResources();
		
		foreach( \IPS\Theme::themes() as $id => $set )
		{
			/* Invalidate template disk cache */
			$set->cache_key = md5( microtime() . mt_rand( 0, 1000 ) );
			$set->save();
		}
		
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();
		\IPS\Output\Cache::i()->clearAll();

		\IPS\Member::clearCreateMenu();

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module={$md}&controller={$ct}" ), 'nulled_clear_caches_done' );
		}
	}

	/**
	 * Clear caches
	 *
	 * @return void
	 */
	protected function clearCaches()
	{
		/* Check CSRF Key*/
		\IPS\Session::i()->csrfCheck();

		/* Clear JS Maps first */
		\IPS\Output::clearJsFiles();
		
		/* Reset theme maps to make sure bad data hasn't been cached by visits mid-setup */
		\IPS\Theme::deleteCompiledCss();
		\IPS\Theme::deleteCompiledResources();
		
		foreach( \IPS\Theme::themes() as $id => $set )
		{
			/* Invalidate template disk cache */
			$set->cache_key = md5( microtime() . mt_rand( 0, 1000 ) );
			$set->save();
		}
		
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();
		\IPS\Output\Cache::i()->clearAll();

		\IPS\Member::clearCreateMenu();
		
		\IPS\Session::i()->log( 'acplog__support_tool_caches_cleared' );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=support' ) );
		}
	}

	/**
	 * Step 2: Disable Third Party Customizations
	 *
	 * @return	void
	 */
	protected function thirdparty()
	{
		\IPS\Session::i()->csrfCheck();

		if( isset( \IPS\Request::i()->enable ) )
		{
			if( \IPS\Request::i()->enable )
			{
				$this->_enableThirdParty();
			}
			else
			{
				$this->_disableThirdParty();
			}
		}
		else
		{
			/* Display */
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->thirdPartyItems(
				$this->_thirdPartyApps( TRUE ),
				$this->_thirdPartyPlugins( TRUE ),
				$this->_thirdPartyTheme(),
				$this->_thirdPartyEditor(),
				$this->_thirdPartyAds()
			);
		}
	}

	/**
	 * Disable third party customizations
	 *
	 * @return void
	 */
	protected function _disableThirdParty()
	{		
		/* Init */
		$disabledApps = array();
		$disabledPlugins = array();
		$disabledAppNames = array();
		$disabledPluginNames = array();
		$restoredDefaultTheme = FALSE;
		$restoredEditor = FALSE;
		$disabledAds = array();

		/* Do we need to disable any third party apps/plugins? */
		if ( !\IPS\NO_WRITES )
		{		
			/* Loop Apps */
			foreach ( $this->_thirdPartyApps() as $app )
			{
				\IPS\Db::i()->update( 'core_applications', array( 'app_enabled' => 0 ), array( 'app_id=?', $app->id ) );
				
				$disabledApps[] = $app->directory;
				$disabledAppNames[ $app->directory ] = $app->_title;
			}
			
			if ( \count( $disabledApps ) )
			{
				\IPS\Session::i()->log( 'acplog__support_tool_apps_disabled' );
			}
			
			/* Look Plugins */
			foreach ( $this->_thirdPartyPlugins() as $plugin )
			{
				\IPS\Db::i()->update( 'core_plugins', array( 'plugin_enabled' => 0 ), array( 'plugin_id=?', $plugin->id ) );
				
				$disabledPlugins[] = $plugin->id;
				$disabledPluginNames[ $plugin->id ] = $plugin->_title;
			}
			
			if ( \count( $disabledPlugins ) )
			{
				\IPS\Session::i()->log( 'acplog__support_tool_plugins_disabled' );
			}

			if( \count( $this->_thirdPartyApps() ) )
			{
				\IPS\Application::postToggleEnable();
			}

			if( \count( $this->_thirdPartyPlugins() ) )
			{
				\IPS\Plugin::postToggleEnable( TRUE );
			}
		}
		
		/* Do we need to restore the default theme? */
		if ( $this->_thirdPartyTheme() )
		{
			$newTheme = new \IPS\Theme;
			$newTheme->permissions = \IPS\Member::loggedIn()->member_group_id;
			$newTheme->save();
			$newTheme->installThemeSettings();
			$newTheme->copyResourcesFromSet();
			
			\IPS\Lang::saveCustom( 'core', "core_theme_set_title_" . $newTheme->id, "IPS Default" );
			
			\IPS\Member::loggedIn()->skin = $newTheme->id;
			\IPS\Member::loggedIn()->save();
			
			$restoredDefaultTheme = TRUE;
		}
		
		if ( $restoredDefaultTheme )
		{
			\IPS\Session::i()->log( 'acplog__support_tool_theme_restored' );
		}
		
		/* Do we need to revert the editor? */
		if ( $this->_thirdPartyEditor() )
		{
			\IPS\Data\Store::i()->editorConfigurationToRestore = array(
				'extraPlugins' 	=> \IPS\Settings::i()->ckeditor_extraPlugins,
				'toolbars'		=> \IPS\Settings::i()->ckeditor_toolbars,
			);
			
			\IPS\Settings::i()->changeValues( array( 'ckeditor_extraPlugins' => '', 'ckeditor_toolbars' => '' ) );
			
			$restoredEditor = TRUE;
		}
		
		if ( $restoredEditor )
		{
			\IPS\Session::i()->log( 'acplog__support_tool_editor_restored' );
		}
		
		/* Do we need to disable any thid party ads? */
		foreach ( $this->_thirdPartyAds() as $ad )
		{
			$ad = \IPS\core\Advertisement::constructFromData( $ad );
			$ad->active = 0;
			$ad->save();
			$disabledAds[] = $ad->id;
		}
		
		if ( \count( $disabledAds ) )
		{
			\IPS\Session::i()->log( 'acplog__support_tool_ads_disabled' );
		}
		
		/* Clear cache */
		\IPS\Data\Cache::i()->clearAll();

		/* Store what we've done so we can restore it after if we want */
		$_SESSION['thirdParty'] = array(
			'enableApps'	=> implode( ',', $disabledApps ),
			'enablePlugins'	=> implode( ',', $disabledPlugins ),
			'deleteTheme'	=> $restoredDefaultTheme ? $newTheme->id : 0,
			'restoreEditor'	=> \intval( $restoredEditor ),
			'enableAds'		=> implode( ',', $disabledAds )
		);
		
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->thirdPartyDisabled(
			$disabledAppNames,
			$disabledPluginNames,
			$restoredDefaultTheme ? $newTheme->id : 0,
			$restoredEditor,
			$disabledAds
		);
	}
	
	/**
	 * Step 2: Re-Enable Third Party Customizations
	 *
	 * @return	void
	 */
	protected function _enableThirdParty()
	{
		/* Theme */
		if ( isset( $_SESSION['thirdParty']['deleteTheme'] ) and $_SESSION['thirdParty']['deleteTheme'] and ( \IPS\Request::i()->type == 'all' or \IPS\Request::i()->type == 'theme' ) )
		{
			try
			{
				\IPS\Theme::load(  $_SESSION['thirdParty']['deleteTheme'] )->delete();
				\IPS\Session::i()->log( 'acplog__support_tool_theme_deleted' );
			}
			catch ( \Exception $e ) {}

			unset( $_SESSION['thirdParty']['deleteTheme'] );
		}
		
		/* Apps */
		if( \IPS\Request::i()->type == 'all' or \IPS\Request::i()->type == 'apps' )
		{
			foreach ( explode( ',', $_SESSION['thirdParty']['enableApps'] ) as $app )
			{			
				try
				{
					\IPS\Db::i()->update( 'core_applications', array( 'app_enabled' => 1 ), array( 'app_directory=?', $app ) );
				}
				catch ( \Exception $e ) {}
			}

			if( $_SESSION['thirdParty']['enableApps'] )
			{
				\IPS\Application::postToggleEnable();
				\IPS\Session::i()->log( 'acplog__support_tool_apps_enabled' );
			}

			unset( $_SESSION['thirdParty']['enableApps'] );
		}
		
		/* Plugins */
		if( \IPS\Request::i()->type == 'all' or \IPS\Request::i()->type == 'plugins' )
		{
			foreach ( explode( ',', $_SESSION['thirdParty']['enablePlugins'] ) as $plugin )
			{			
				try
				{
					\IPS\Db::i()->update( 'core_plugins', array( 'plugin_enabled' => 1 ), array( 'plugin_id=?', $plugin ) );
				}
				catch ( \Exception $e ) {}
			}

			if( $_SESSION['thirdParty']['enablePlugins'] )
			{
				\IPS\Plugin::postToggleEnable( TRUE );
				\IPS\Session::i()->log( 'acplog__support_tool_plugins_enabled' );
			}

			unset( $_SESSION['thirdParty']['enablePlugins'] );
		}

		/* Editor Plugins */
		if ( isset( $_SESSION['thirdParty']['restoreEditor'] ) and $_SESSION['thirdParty']['restoreEditor'] and ( \IPS\Request::i()->type == 'all' or \IPS\Request::i()->type == 'editor' ) )
		{
			$editorConfiguration = \IPS\Data\Store::i()->editorConfigurationToRestore;
			
			\IPS\Settings::i()->changeValues( array( 'ckeditor_extraPlugins' => $editorConfiguration['extraPlugins'], 'ckeditor_toolbars' => $editorConfiguration['toolbars'] ) );
			
			\IPS\Session::i()->log( 'acplog__support_tool_editor_customized' );
			
			unset( \IPS\Data\Store::i()->editorConfigurationToRestore );
			unset( $_SESSION['thirdParty']['restoreEditor'] );
		}
		
		/* Ads Ads */
		if( \IPS\Request::i()->type == 'all' or \IPS\Request::i()->type == 'ads' )
		{
			foreach ( explode( ',', $_SESSION['thirdParty']['enableAds'] ) as $ad )
			{
				try
				{
					$ad = \IPS\core\Advertisement::load( $ad );
					$ad->active = 1;
					$ad->save();
				}
				catch ( \Exception $e ) {}
			}
			
			if ( $_SESSION['thirdParty']['enableAds'] )
			{
				\IPS\Session::i()->log( 'acplog__support_tool_ads_enabled' );
			}

			unset( $_SESSION['thirdParty']['enableAds'] );
		}
		
		/* Clear cache */
		\IPS\Data\Cache::i()->clearAll();
		
		/* Output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=support&controller=support') );
		}
	}

	/**
	 * Get our blocks for the dashboard. Skeleton templates are returned that will then be lazy loaded.
	 *
	 * @return array
	 */
	protected function _getBlocks()
	{
		$blocks = array(
			'version'		=> array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__version_title'),
				'details'	=> \IPS\Member::loggedIn()->language()->addToStack( 'acp_version_number_raw', FALSE, array( 'sprintf' => array( \IPS\Application::load('core')->version ) ) )
			)
		);

		if( !\IPS\CIC )
		{
			$blocks['php'] = array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__php_title'),
				'details'	=> \IPS\Member::loggedIn()->language()->addToStack( 'acp_version_number_raw', FALSE, array( 'sprintf' => array( PHP_VERSION ) ) )
			);
		}

		$blocks['hookscanner'] = array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__scanner_title'),
		);

		$blocks['mysql'] = array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack( \IPS\CIC ? 'health__mysql_title' : 'health__mysql_title_cic' ),
			'details'	=> !\IPS\CIC ? \IPS\Member::loggedIn()->language()->addToStack( 'acp_version_number_raw', FALSE, array( 'sprintf' => array( \IPS\Db::i()->server_info ) ) ) : NULL
		);

		if( !\IPS\CIC )
		{
			$blocks['caching'] = array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__caching_title'),
				'details'	=> \IPS\Member::loggedIn()->language()->addToStack('health__caching_enabled', FALSE, array( 'sprintf' => array( mb_ucfirst( \IPS\CACHE_METHOD ) ) ) )
			);

			$blocks['server'] = array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__server_title'),
				'details'	=> \IPS\Member::loggedIn()->language()->addToStack('health__server_subtitle', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH, $this->_getServerAddress() ) ) )
			);
		}

		if( $size = $this->_getLogTableSize() )
		{
			$size = \IPS\Output\Plugin\Filesize::humanReadableFilesize( $size );
		}
		else
		{
			$size = \IPS\Member::loggedIn()->language()->addToStack('unavailable');
		}

		$blocks['logs'] = array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__logs_title'),
			'details'	=> \IPS\Member::loggedIn()->language()->addToStack( 'health__logs_table', FALSE, array( 'sprintf' => array( $size ) ) )
		);

		$blocks['vapid'] = array(
			'title'		=> \IPS\Member::loggedIn()->language()->addToStack('health__vapid_title'),
		);

		return $blocks;
	}

	/**
	 * Get the server address
	 *
	 * @return string
	 */
	protected function _getServerAddress()
	{
		if( array_key_exists( 'SERVER_ADDR', $_SERVER ) )
		{
			return $_SERVER['SERVER_ADDR'];
		}
		elseif( array_key_exists( 'LOCAL_ADDR', $_SERVER ) )
		{
			return $_SERVER['LOCAL_ADDR'];
		}

		return \IPS\Member::loggedIn()->language()->addToStack('unavailable');
	}

	/**
	 * Get the error/system log chart
	 *
	 * @return \IPS\Helpers\Chart
	 */
	protected function getLogChart()
	{
		$chart = new \IPS\Helpers\Chart\Callback( 
			\IPS\Http\Url::internal( 'app=core&module=support&controller=support&do=getLogChart' ), 
			array( $this, '_getLogChartResults' ),
			'', 
			array( 
				'isStacked' => TRUE,
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963', '#de6470' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			), 
			'LineChart', 
			'daily', 
			array( 'start' => \IPS\DateTime::create()->sub( new \DateInterval( 'P30D' ) ), 'end' => \IPS\DateTime::ts( time() ) )
		);
		$chart->addSeries( \IPS\Member::loggedIn()->language()->get('health_system_log_title'), 'number', FALSE );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->get('health_error_log_title'), 'number', FALSE );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->get('health_email_error_log_title'), 'number', FALSE );
		$chart->title = NULL;
		$chart->showFilterTabs = FALSE;
		$chart->showSave = FALSE;
		$chart->availableTypes = array( 'LineChart' );
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output	= (string) $chart;
		}
		else
		{
			return $chart;
		}
	}

	/**
	 * Fetch the results
	 *
	 * @param	\IPS\Helpers\Chart\Callback	$chart	Chart object
	 * @return	array
	 */
	public function _getLogChartResults( $chart )
	{
		$finalResults = array();

		foreach( $this->_getLogChartResultsSql( 'core_log', 'time', $chart ) as $date => $count )
		{
			if( !isset( $finalResults[ $date ] ) )
			{
				$finalResults[ $date ] = array( 'time' => $date, \IPS\Member::loggedIn()->language()->get('health_error_log_title') => 0, \IPS\Member::loggedIn()->language()->get('health_email_error_log_title') => 0 );
			}

			$finalResults[ $date ][ \IPS\Member::loggedIn()->language()->get('health_system_log_title') ] = $count;
		}

		foreach( $this->_getLogChartResultsSql( 'core_error_logs', 'log_date', $chart ) as $date => $count )
		{
			if( !isset( $finalResults[ $date ] ) )
			{
				$finalResults[ $date ] = array( 'time' => $date, \IPS\Member::loggedIn()->language()->get('health_system_log_title') => 0, \IPS\Member::loggedIn()->language()->get('health_email_error_log_title') => 0 );
			}

			$finalResults[ $date ][ \IPS\Member::loggedIn()->language()->get('health_error_log_title') ] = $count;
		}

		foreach( $this->_getLogChartResultsSql( 'core_mail_error_logs', 'mlog_date', $chart ) as $date => $count )
		{
			if( !isset( $finalResults[ $date ] ) )
			{
				$finalResults[ $date ] = array( 'time' => $date, \IPS\Member::loggedIn()->language()->get('health_error_log_title') => 0, \IPS\Member::loggedIn()->language()->get('health_system_log_title') => 0 );
			}

			$finalResults[ $date ][ \IPS\Member::loggedIn()->language()->get('health_email_error_log_title') ] = $count;
		}

		return $finalResults;
	}

	/**
	 * Get SQL query/results
	 *
	 * @note Consolidated to reduce duplicated code
	 * @param	string	$table	Database table
	 * @param	string	$date	Date column
	 * @param	object	$chart	Chart
	 * @return	array
	 */
	protected function _getLogChartResultsSql( $table, $date, $chart )
	{
		/* What's our SQL time? */
		switch ( $chart->timescale )
		{
			case 'daily':
				$timescale = '%Y-%c-%e';
				break;
			
			case 'weekly':
				$timescale = '%x-%v';
				break;
				
			case 'monthly':
				$timescale = '%Y-%c';
				break;
		}

		$results	= array();
		$where		= array();
		if ( $chart->start )
		{
			$where[] = array( "{$date}>?", $chart->start->getTimestamp() );
		}
		else
		{
			$where[] = array( "{$date}>?", 0 );
		}
		if ( $chart->end )
		{
			$where[] = array( "{$date}<?", $chart->end->getTimestamp() );
		}

		/* First we need to get search index activity */
		$fromUnixTime = "FROM_UNIXTIME( IFNULL( {$date}, 0 ) )";
		if ( !$chart->timezoneError and \IPS\Member::loggedIn()->timezone and \in_array( \IPS\Member::loggedIn()->timezone, \IPS\DateTime::getTimezoneIdentifiers() ) )
		{
			$fromUnixTime = "CONVERT_TZ( {$fromUnixTime}, @@session.time_zone, '" . \IPS\Db::i()->escape_string( \IPS\Member::loggedIn()->timezone ) . "' )";
		}

		$stmt = \IPS\Db::i()->select( "COUNT(*) as total, DATE_FORMAT( {$fromUnixTime}, '{$timescale}' ) AS ctime", $table, $where, 'ctime ASC', NULL, array( 'ctime' ) );

		foreach( $stmt as $row )
		{
			$results[ $row['ctime'] ] = $row['total'];
		}

		return $results;
	}
	
	/**
	 * Run database checker
	 *
	 * @param	bool	$fix	Fix the issue instead of returning the count
	 * @return	mixed
	 */
	public function _databaseChecker( $fix = FALSE )
	{
		$changesToMake = array();

		foreach ( \IPS\Application::enabledApplications() as $app )
		{
			$changesToMake = array_merge( $changesToMake, $app->databaseCheck() );
		}

		if( !$fix )
		{
			return $changesToMake;
		}

		\IPS\Output::i()->httpHeaders['X-IPS-FormNoSubmit'] = "true";
		
		if ( isset( \IPS\Request::i()->run ) )
		{
			$erroredQueries = array();
			$errors = array();
			foreach ( $changesToMake as $query )
			{
				try
				{
					\IPS\Db::i()->query( $query['query'] );
				}
				catch ( \Exception $e )
				{
					$erroredQueries[] = $query['query'];
					$errors[] = $e->getMessage();
				}
			}
			
			\IPS\Session::i()->log( 'acplog__support_tool_db_check' );
			
			if ( \count( $erroredQueries ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->fixDatabase( $erroredQueries, $errors, \IPS\Request::i()->_upgradeVersion );
			}
			else
			{
				if ( isset( \IPS\Request::i()->_upgradeVersion ) and \IPS\Request::i()->_upgradeVersion )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=upgrade&_chosenVersion=' . \IPS\Request::i()->_upgradeVersion ) );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=support&controller=support') );
				}
			}
		}
		else
		{
			$queries = array();
			foreach ( $changesToMake as $query )
			{
				$queries[] = $query['query'];
			}
			
			if ( \count( $queries ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->fixDatabase( $queries, NULL, \IPS\Request::i()->_upgradeVersion );
			}
			else
			{
				if ( isset( \IPS\Request::i()->_upgradeVersion ) and \IPS\Request::i()->_upgradeVersion )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=upgrade&_chosenVersion=' . \IPS\Request::i()->_upgradeVersion ) );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=support&controller=support') );
				}
			}
		}

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html' );
	}

	/**
	 * Run MD5 checker
	 *
	 * @param	bool	$fix	Fix the issue instead of returning the count
	 * @return	mixed
	 */
	protected function _md5Checker( $fix = FALSE )
	{
		return 0;
	}

	/**
	 * Get a count of third party apps and plugins
	 *
	 * @return	int
	 */
	protected function _getThirdPartyCount()
	{
		return \count( $this->_thirdPartyApps() ) +
			\count( $this->_thirdPartyPlugins() ) + 
			$this->_thirdPartyTheme() + 
			$this->_thirdPartyEditor() +
			\count( $this->_thirdPartyAds() );
	}

	/**
	 * Get the size of the system log table
	 *
	 * @param	string	$tableName	Database table to get size of
	 * @return	int|string
	 */
	protected function _getLogTableSize( $tableName = 'core_log' )
	{
		try
		{
			if( $result = \IPS\Db::i()->query( "SELECT DATA_LENGTH + INDEX_LENGTH as _size FROM `information_schema`.`TABLES` WHERE TABLE_SCHEMA = '" . \IPS\Settings::i()->sql_database . "' AND TABLE_NAME='" . \IPS\Db::i()->prefix . $tableName . "'" ) )
			{
				if( $resultSet = $result->fetch_assoc() )
				{
					return (int) $resultSet['_size'];
				}
				else
				{
					throw new \IPS\Db\Exception;
				}
			}
			else
			{
				throw new \IPS\Db\Exception;
			}
		}
		catch( \IPS\Db\Exception $e )
		{
			return NULL;
		}
	}

	/**
	 * Get third-party applications
	 *
	 * @param	bool	$separateMarketplace		If TRUE, will separate ones installed from the marketplace and custom ones
	 * @return	array
	 */
	protected function _thirdPartyApps( $separateMarketplace = FALSE )
	{	
		if ( \IPS\NO_WRITES )
		{
			return array();
		}
		
		$apps = $separateMarketplace ? [ 'marketplace' => [], 'custom' => [] ] : [];
		
		foreach ( \IPS\Application::applications() as $app )
		{
			if ( $app->enabled and !\in_array( $app->directory, \IPS\IPS::$ipsApps ) )
			{
				if ( $separateMarketplace )
				{
					if ( $app->marketplace_id )
					{
						$apps['marketplace'][] = $app;
					}
					else
					{
						$apps['custom'][] = $app;
					}
				}
				else
				{
					$apps[] = $app;
				}
			}
		}
		
		return $apps;
	}
	
	/**
	 * Get third-party plugins
	 *
	 * @param	bool	$separateMarketplace		If TRUE, will separate ones installed from the marketplace and custom ones
	 * @return	array
	 */
	protected function _thirdPartyPlugins( $separateMarketplace = FALSE )
	{	
		if ( \IPS\NO_WRITES )
		{
			return array();
		}
		
		$plugins = $separateMarketplace ? [ 'marketplace' => [], 'custom' => [] ] : [];
		
		foreach ( \IPS\Plugin::plugins() as $plugin )
		{
			if ( $plugin->enabled )
			{
				if ( $separateMarketplace )
				{
					if ( $plugin->marketplace_id )
					{
						$plugins['marketplace'][] = $plugin;
					}
					else
					{
						$plugins['custom'][] = $plugin;
					}
				}
				else
				{
					$plugins[] = $plugin;
				}
			}
		}
		
		return $plugins;
	}
	
	/**
	 * Has the theme been customised?
	 *
	 * @return	bool
	 */
	protected function _thirdPartyTheme()
	{	
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_templates', 'template_set_id>0' )->first() or \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_css', 'css_set_id>0' )->first();
	}
	
	/**
	 * Has the editor been customised?
	 *
	 * @return	bool
	 */
	protected function _thirdPartyEditor()
	{	
		return ( \IPS\Settings::i()->ckeditor_extraPlugins or \IPS\Settings::i()->ckeditor_toolbars != \IPS\Db::i()->select( 'conf_default', 'core_sys_conf_settings', array( 'conf_key=?', 'ckeditor_toolbars' ) )->first() );
	}
	
	/**
	 * Get third-party advertisements
	 *
	 * @return	\IPS\Db\Select
	 */
	protected function _thirdPartyAds()
	{	
		return \IPS\Db::i()->select( '*','core_advertisements', array( 'ad_active=?', 1 ) );
	}

	/**
	 * Create Admin
	 * 
	 * @return 	void
	 */
	public function admin()
	{
		if ( \IPS\Login\Handler::findMethod( 'IPS\Login\Handler\Standard' ) )
		{
			$password = '';
			$length = rand( 8, 15 );
			for ( $i = 0; $i < $length; $i++ )
			{
				do {
					$key = rand( 33, 126 );
				} while ( \in_array( $key, array( 34, 39, 60, 62, 92 ) ) );
				$password .= \chr( $key );
			}
			
			$supportAccount = \IPS\Member::load( 'ipstempadmin@invisionpower.com', 'email' );
			if ( !$supportAccount->member_id )
			{
				$supportAccount = \IPS\Member::load( 'nobody@invisionpower.com', 'email' );
			}
			
			if ( !$supportAccount->member_id )
			{
				$name = 'IPS Temp Admin';
				$_supportAccount = \IPS\Member::load( $name, 'name' );
				if ( $_supportAccount->member_id )
				{
					$number = 2;
					while ( $_supportAccount->member_id )
					{
						$name = "IPS Temp Admin {$number}";
						$_supportAccount = \IPS\Member::load( $name, 'name' );
						$number++;
					}
				}
				
				$supportAccount = new \IPS\Member;
				$supportAccount->name = $name;
				$supportAccount->member_group_id = \IPS\Settings::i()->admin_group;
			}
			
			/* Always update the email in case we found the old "nobody" support account. */
			$supportAccount->email = 'ipstempadmin@invisionpower.com';

			/* Set english language to the admin account / create new english language if needed */
			$locales	= array( 'en_US', 'en_US.UTF-8', 'en_US.UTF8', 'en_US.utf8', 'english' );
			try
			{
				$existingEnglishLangPack = \IPS\Db::i()->select( 'lang_id', 'core_sys_lang', array( \IPS\Db::i()->in( 'lang_short', $locales ) ) )->first();
				$supportAccount->language = $existingEnglishLangPack;
				$supportAccount->acp_language = $existingEnglishLangPack;
			}
			catch ( \UnderflowException $e )
			{
				/* Install the default language */
				$locale		= 'en_US';
				foreach ( $locales as $k => $localeCode )
				{
					try
					{
						\IPS\Lang::validateLocale( $localeCode );
						$locale = $localeCode;
						break;
					}
					catch ( \InvalidArgumentException $e ){}
				}

				$insertId = \IPS\Db::i()->insert( 'core_sys_lang', array(
					'lang_short'	=> $locale,
					'lang_title'	=> "Default ACP English",
					'lang_enabled'	=> 0,
				) );
				
				$supportAccount->language		= $insertId;
				$supportAccount->acp_language	= $insertId;

				/* Initialize Background Task to insert the language strings */
				foreach ( \IPS\Application::applications() as $key => $app )
				{
					\IPS\Task::queue( 'core', 'InstallLanguage', array( 'application' => $key, 'language_id' => $insertId ), 1 );
				}
			}
			
			$supportAccount->members_bitoptions['is_support_account'] = TRUE;
			$supportAccount->setLocalPassword( $password );
			$supportAccount->save();
			\IPS\core\AdminNotification::send( 'core', 'ConfigurationError', "supportAdmin-{$supportAccount->member_id}", TRUE, NULL, TRUE );
			
			\IPS\Session::i()->log( 'acplog__support_tool_admin' );
			
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'administrator_account' );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->admin( $supportAccount->name, $supportAccount->email, $password );
		}
	}
}