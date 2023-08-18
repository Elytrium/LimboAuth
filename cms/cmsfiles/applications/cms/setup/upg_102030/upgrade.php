<?php
/**
 * @brief		4.2.6 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		25 Oct 2017
 */

namespace IPS\cms\setup\upg_102030;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.6 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove orphaned promotions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$classes = array();

		/* Get all existing Content Classes */
		foreach(  \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) AS $contentRouter )
		{
			foreach ( $contentRouter->classes as $class )
			{
				$classes[]	= $class;

				if ( isset( $class::$commentClass ) )
				{
					$classes[]	= $class::$commentClass;
				}

				if ( isset( $class::$reviewClass ) )
				{
					$classes[]	= $class::$reviewClass;
				}
			}

			if( isset( $contentRouter->ownedNodes ) )
			{
				foreach( $contentRouter->ownedNodes as $class )
				{
					$classes[]	= $class;
				}
			}
		}

		if( \count( $classes ) )
		{
			/* Delete Promoted Content from all not existing content classes */
			\IPS\Db::i()->delete( 'core_social_promote', \IPS\Db::i()->in( 'promote_class', $classes, TRUE ) );
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
		return "Cleaning up orphaned promotions";
	}

	/**
	 * Cleanup Database Record Slugs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) AS $db )
		{
			\IPS\Db::i()->update( "cms_custom_database_{$db['database_id']}", array( 'record_static_furl' => NULL ), array( "record_static_furl=?", '' ) );
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
		return "Cleaning up database records";
	}
}