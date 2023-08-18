<?php
/**
 * @brief		4.0.8 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Jun 2015
 */

namespace IPS\core\setup\upg_100033;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.8 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix the column
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$indexTitle	= array(
			'name'			=> 'index_title',
			'type'			=> 'VARCHAR',
			'length'		=> 255,
			'allow_null'	=> true,
			'default'		=> null,
			'comment'		=> "Content title",
		);

		$indexContent = array(
			'name'			=> 'index_content',
			'type'			=> 'MEDIUMTEXT',
			'allow_null'	=> false,
			'default'		=> '',
			'comment'		=> "The plain-text content to search",
		);

		$indexTitle		= \IPS\Db::i()->compileColumnDefinition( $indexTitle );
		$indexContent	= \IPS\Db::i()->compileColumnDefinition( $indexContent );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array(
			array(
				'table'	=> 'core_search_index',
				'query'	=> "ALTER TABLE " . \IPS\Db::i()->prefix . "core_search_index CHANGE COLUMN `index_title` {$indexTitle}, CHANGE COLUMN index_content {$indexContent}"
			)
		) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
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
		return "Adjusting search index";
	}
}