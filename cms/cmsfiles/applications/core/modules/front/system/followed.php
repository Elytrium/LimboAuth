<?php
/**
 * @brief		My followed content
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Apr 2014
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * My followed content
 */
class _followed extends \IPS\Dispatcher\Controller
{
	/**
	 * My followed content
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Guests can't follow things */
		if( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C266/1', 403, '' );
		}

		/* Get the different types */
		$types			= array();
		
		foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\Followable' ) )
			{
				$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;

				if ( isset( $class::$containerNodeClass ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class::$containerNodeClass, 4 ) ) ) ] = $class::$containerNodeClass;
				}
				
				if ( isset( $class::$containerFollowClasses ) )
				{
					foreach( $class::$containerFollowClasses as $followClass )
					{
						$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $followClass, 4 ) ) ) ] = $followClass;
					}
				}
			}
		}
		
		/* Don't forget Members - add this on to the end so it never defaults UNLESS we only have apps that do not have followable content */
		$types['core'] = array( 'core_member' => "\IPS\Member" );

		/* What type are we looking at? */
		$currentAppModule = NULL;
		$currentType = NULL;
		if ( isset( \IPS\Request::i()->type ) )
		{
			foreach ( $types as $appModule => $_types )
			{
				if ( array_key_exists( \IPS\Request::i()->type, $_types ) )
				{
					$currentAppModule = $appModule;
					$currentType = \IPS\Request::i()->type;
					break;
				}
			}
		}
		
		if ( $currentType === NULL )
		{
			foreach ( $types as $appModule => $_types )
			{
				foreach ( $_types as $key => $class )
				{
					$currentAppModule = $appModule;
					$currentType = $key;
					break 2;
				}
			}
		}
		
		$currentClass = $types[ $currentAppModule ][ $currentType ];

		$output = new \IPS\core\Followed\Table( $currentClass, explode( '_', $currentType ) );
		
		/* If we've clicked from the tab section */
		if ( \IPS\Request::i()->isAjax() && \IPS\Request::i()->change_section )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->followedContentSection( $types, $currentAppModule, $currentType, $output );
		}
		else
		{
			/* Display */
			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/followedcontent_responsive.css' ) );
			}

			\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_system.js', 'core' ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu_followed_content');
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('menu_followed_content') );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->followedContent( $types, $currentAppModule, $currentType, $output );
		}
	}
}