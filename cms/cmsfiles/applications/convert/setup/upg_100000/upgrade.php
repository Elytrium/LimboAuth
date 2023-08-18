<?php


namespace IPS\convert\setup\upg_100000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Make sure conv_password and conv_password_extra are present and accurate.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries = array();

		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'conv_password' ) )
		{
			$queries[]	= array(
				'table' => 'core_members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members CHANGE conv_password conv_password VARCHAR(255) null default null;"
			);
		}
		else
		{
			$queries[]	= array(
				'table' => 'core_members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members ADD COLUMN conv_password VARCHAR(255) null default null;"
			);
		}
		
		if ( \IPS\Db::i()->checkForColumn( 'core_members', 'misc' ) )
		{
			$queries[]	= array(
				'table' => 'core_members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members CHANGE misc conv_password_extra VARCHAR(255) null default null;"
			);
		}
		else if ( \IPS\Db::i()->checkForColumn( 'core_members', 'conv_password_extra' ) )
		{
			$queries[]	= array(
				'table'	=> 'core_members',
				'query'	=> "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members CHANGE conv_password_extra conv_password_extra VARCHAR(255) null default null;"
			);
		}
		else
		{
			$queries[]	= array(
				'table' => 'core_members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members ADD conv_password_extra VARCHAR(255) null default null;"
			);
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'convert', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		
		return TRUE;
	}
}