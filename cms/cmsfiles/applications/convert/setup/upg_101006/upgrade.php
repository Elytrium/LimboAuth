<?php


namespace IPS\convert\setup\upg_101006;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Adjust converter columns - we do it here to combine queries for efficiency
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( 
			array(
				'table' => 'convert_link_posts',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "convert_link_posts CHANGE foreign_id foreign_id VARCHAR(255) NOT NULL DEFAULT '',
					ADD INDEX local_lookup(ipb_id,type,app),
					DROP INDEX foreign_id,
					ADD INDEX foreign_id (foreign_id(191)),
					DROP INDEX ipb_id,
					DROP INDEX `type`,
					ADD INDEX `type` (`type`),
					ADD INDEX app (app)"
			),
			array(
				'table' => 'convert_link_pms',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "convert_link_pms CHANGE foreign_id foreign_id VARCHAR(255) NOT NULL DEFAULT '',
					ADD INDEX local_lookup(ipb_id,type,app),
					DROP INDEX foreign_id,
					ADD INDEX foreign_id (foreign_id(191)),
					DROP INDEX ipb_id,
					DROP INDEX `type`,
					ADD INDEX `type` (`type`),
					ADD INDEX app (app)"
			),
			array(
				'table' => 'convert_link_topics',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "convert_link_topics CHANGE foreign_id foreign_id VARCHAR(255) NOT NULL DEFAULT '',
					ADD INDEX local_lookup(ipb_id,type,app),
					DROP INDEX foreign_id,
					ADD INDEX foreign_id (foreign_id(191)),
					DROP INDEX ipb_id,
					DROP INDEX `type`,
					ADD INDEX `type` (`type`),
					ADD INDEX app (app)"
			),
			array(
				'table' => 'convert_link',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "convert_link CHANGE foreign_id foreign_id VARCHAR(255) NOT NULL DEFAULT '',
					ADD INDEX local_lookup(ipb_id,type,app),
					DROP INDEX foreign_id,
					ADD INDEX foreign_id (foreign_id(191)),
					DROP INDEX ipb_id,
					DROP INDEX `type`,
					ADD INDEX `type` (`type`),
					ADD INDEX app (app)"
			)
		 ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'convert', 'extra' => array( '_upgradeStep' => 2, '_upgradeData' => 0 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		
		return TRUE;
	}
}