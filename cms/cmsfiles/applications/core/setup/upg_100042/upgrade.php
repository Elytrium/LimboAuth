<?php
/**
 * @brief		4.0.12 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Jul 2015
 */

namespace IPS\core\setup\upg_100042;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.12 Upgrade Code
 */
class _Upgrade
{
	/**
	 * CSS can get confused because of previusly fixed issues.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$prefix = \IPS\Db::i()->prefix;
		$dupes = array();
		
		/* Find and clear out duplicate CSS rows, saving the most recently edited */
		foreach( \IPS\Db::i()->query( "SELECT css_set_id, css_app, css_location, css_path, css_name, count( CONCAT_WS(';', css_set_id, css_app, css_location, css_path, css_name) ) as count FROM {$prefix}core_theme_css GROUP BY css_set_id, css_app, css_location, css_path, css_name ORDER BY count DESC" ) as $row )
		{
			if ( $row['count'] < 2 )
			{
				break;
			}
			
			$dupes[] = md5( $row['css_set_id'] . ';' . $row['css_app'] . ';' . $row['css_location'] . ';' . $row['css_path'] . ';' . $row['css_name'] );
		}
		
		foreach( $dupes as $key )
		{
			$keep = \IPS\Db::i()->select( '*', 'core_theme_css', array( "MD5( CONCAT_WS(';', css_set_id, css_app, css_location, css_path, css_name) )=?", $key ), 'css_updated DESC' )->first();
			
			\IPS\Db::i()->delete( 'core_theme_css', array( "MD5( CONCAT_WS(';', css_set_id, css_app, css_location, css_path, css_name) ) =? AND css_id !=?", $key, $keep['css_id'] ) );
		}
		
		\IPS\Db::i()->query( "UPDATE {$prefix}core_theme_css SET css_unique_key=MD5( CONCAT_WS( ';', css_set_id, css_app, css_location, css_path, css_name ) )" );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing old incorrect CSS mappings";
	}

	/**
	 * In an older 4.0.x release a primary key was set incorrectly on this column, which often results in the database checker flagging the column for repair but being unable to repair it (with no errors)
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( \IPS\Db::i()->checkForIndex( 'core_sys_social_group_members', 'PRIMARY KEY' ) )
		{
			\IPS\Db::i()->dropIndex( 'core_sys_social_group_members', 'PRIMARY KEY' );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Adjusting database indexes";
	}

	/**
	 * 3.x allowed website url to be saved as just "http://", but this causes an error when trying to edit the member if you don't spot it
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$queries = array();

		foreach( \IPS\Db::i()->select( '*', 'core_pfields_data', array( 'pf_type=?', 'Url' ) ) as $field )
		{
			$queries[] = array( 'table' => 'core_pfields_content', 'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_pfields_content SET field_" . $field['pf_id'] . "='' WHERE field_" . $field['pf_id'] . "='http://';" );
		}

		if( \count( $queries ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
			
			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 4 ) ) );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Fixing incorrect custom field URLs";
	}
}