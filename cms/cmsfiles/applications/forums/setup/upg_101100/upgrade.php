<?php
/**
 * @brief		4.2.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		12 Apr 2017
 */

namespace IPS\forums\setup\upg_101100;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Adjust some columns - we do it here to combine queries
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( 
			array(
				'table' => 'forums_topics',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_topics ADD COLUMN topic_meta_data bit(1) NOT NULL DEFAULT b'0',
					CHANGE title title VARCHAR(255) COLLATE " . \IPS\Db::i()->collation . " NOT NULL DEFAULT '',
					CHANGE title_seo title_seo VARCHAR(255) COLLATE " . \IPS\Db::i()->collation . " NOT NULL DEFAULT ''"
			),
			array(
				'table' => 'forums_forums',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "forums_forums ADD COLUMN club_id BIGINT(20) DEFAULT NULL COMMENT 'The club ID if this forum belongs to a club, or NULL',
					ADD COLUMN feature_color VARCHAR(15) COLLATE " . \IPS\Db::i()->collation . " DEFAULT NULL COMMENT 'Feature color',
					CHANGE last_title last_title VARCHAR(255) COLLATE " . \IPS\Db::i()->collation . " DEFAULT NULL"
		) ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'forums', 'extra' => array( '_upgradeStep' => 2, '_upgradeData' => 0 ) ) );

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
		return "Adjusting topic and forum database tables";
	}

	/**
	 * Theme settings clean up
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* Remove forum_layout theme setting */
		$ids = iterator_to_array( \IPS\Db::i()->select( 'sc_id', 'core_theme_settings_fields', array( 'sc_key=? and sc_app=?', 'forum_layout', 'forums' ) ) );
		
		if( \count( $ids ) )
		{
			\IPS\Db::i()->delete( 'core_theme_settings_values', 'sv_id IN(' . implode( ',', $ids ) . ')' );
			\IPS\Db::i()->delete( 'core_theme_settings_fields', 'sc_id IN(' . implode( ',', $ids ) . ')' );
		}

		/* Then remove it from each theme where it is cached */
		foreach( \IPS\Db::i()->select( '*', 'core_themes' ) as $theme )
		{
			$settings = json_decode( $theme['set_template_settings'], true );

			if( isset( $settings['forum_layout'] ) )
			{
				unset( $settings['forum_layout'] );

				\IPS\Db::i()->update( 'core_themes', array( 'set_template_settings' => json_encode( $settings ) ), array( 'set_id=?', $theme['set_id'] ) );
			}
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
		return "Removing old theme settings";
	}
}