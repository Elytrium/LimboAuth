<?php
/**
 * @brief		hookedFiles
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		11 Nov 2016
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * hookedFiles
 */
class _hookedFiles extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'app_manage' );
		parent::execute();
	}

	/**
	 * Show all third party hooks
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$table = new \IPS\Helpers\Table\Db( 'core_hooks', \IPS\Http\Url::internal( 'app=core&module=support&controller=hookedFiles' ), array( array( "( app NOT IN( '" . implode( "','", \IPS\IPS::$ipsApps ) . "' ) OR app IS NULL )" ) ) );
		$table->langPrefix = 'hooks_';
		$table->quickSearch = array( 'class', 'hook_class' );
		$table->include = array(  'hooks_id', 'hooks_app', 'hooks_plugin', 'hooks_type', 'hooks_class', 'hooks_file' );

		$table->parsers = array(
			'hooks_id' => function( $val, $row )
			{
				return $row['id'];
			},
			'hooks_app' => function( $val, $row )
			{
				try
				{
					return \IPS\Application::load($row['app'])->_title;
				}
				catch ( \OutOfRangeException | \UnexpectedValueException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('hook_class_none');
				}
			},
			'hooks_plugin' 	=> function( $val, $row )
			{
				try
				{
					return htmlspecialchars( \IPS\Plugin::load($row['plugin'])->name, ENT_DISALLOWED, 'UTF-8', FALSE );
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('hook_class_none');
				}
			},
			'hooks_type' 	=> function( $val, $row )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'plugin_hook_type_' . mb_strtolower( $row['type'] ) );
			},
			'hooks_class' => function( $val, $row )
			{
				return $row['class'];
			},
			'hooks_file' => function( $val, $row )
			{
				if ( $row['app'] )
				{
					return '/applications/' . $row['app'] . '/hooks/' . $row['filename'] . '.php';
				}
				else if ( $row['plugin'] )
				{
					try
					{
						$plugin = \IPS\Plugin::load($row['plugin']);
						return '/plugins' . ( $plugin->location ? '/' . $plugin->location . '/hooks/' : '/' ) . $row['filename'] . '.php';
					}
					catch ( \OutOfRangeException $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack('hook_class_none');
					}
				}
			}
		);

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'hooked_classes' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->hookedClasses( $table );
	}
	
}