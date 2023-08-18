<?php
/**
 * @brief		4.1.18.1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		20 Jan 2017
 */

namespace IPS\downloads\setup\upg_101088;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.18.1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert custom file field database schemas if needed
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries	= array();

		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields', array( "cf_type IN( 'Editor','Codemirror','TextArea','Upload','Address','Select' )" ) ) as $field )
		{
			\IPS\Db::i()->returnQuery = TRUE;

			if( \IPS\Db::i()->checkForIndex( 'downloads_ccontent', 'field_' . $field['cf_id'] ) )
			{
				\IPS\Db::i()->returnQuery = TRUE;
				$queries[] = array( 'table' => 'downloads_ccontent', 'query' => \IPS\Db::i()->dropIndex( 'downloads_ccontent', 'field_' . $field['cf_id'] ) );
			}

			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array( 'table' => 'downloads_ccontent', 'query' => \IPS\Db::i()->changeColumn( 'downloads_ccontent', 'field_' . $field['cf_id'], array( 'name' => 'field_' . $field['cf_id'], 'type' => 'MEDIUMTEXT' ) ) );

			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array( 'table' => 'downloads_ccontent', 'query' => \IPS\Db::i()->addIndex( 'downloads_ccontent', array( 'type' => 'fulltext', 'name' => 'field_' . $field['cf_id'], 'columns' => array( 'field_' . $field['cf_id'] ) ) ) );
			\IPS\Db::i()->returnQuery = FALSE;
		}

		if( \count( $queries ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );

			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'downloads', 'extra' => array( '_upgradeStep' => 2 ) ) );

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
	public function step1CustomTitle()
	{
		return "Adjusting custom field definitions";
	}
}