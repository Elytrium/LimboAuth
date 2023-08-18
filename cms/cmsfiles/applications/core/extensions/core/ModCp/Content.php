<?php
/**
 * @brief		Moderator Control Panel Extension: Content
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Dec 2013
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Control Panel Extension: Content
 */
class _Content
{	
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string
	 */
	public function getTab()
	{
		return 'hidden';
	}
	
	/**
	 * Hidden Content
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* CSS */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams.css' ) );
		
		/* What types are there? */
		$types = array();
		$exclude = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE ) as $class )
		{
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
			{
				if ( \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_content' ) or \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_' . $class::$title ) )
				{
					$types[ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
					if ( isset( $class::$containerNodeClass ) )
					{
						$containerClass = $class::$containerNodeClass;
						if ( isset( $containerClass::$modPerm ) )
						{
							$allowedContainers = \IPS\Member::loggedIn()->modPermission( $containerClass::$modPerm );
							if ( $allowedContainers !== -1 and $allowedContainers !== true )
							{
								$exclude[ $class ] = $allowedContainers;
							}
						}
					}
				}
				else
				{
					$exclude[ $class ] = array();
				}
			}
		}
		
		/* Looking at a specific content type? */
		$currentType = ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $types ) ) ? \IPS\Request::i()->type : NULL;
		if ( $currentType )
		{
			$currentClass = $types[ $currentType ];
			
			$where = NULL;
			if ( isset( $currentClass::$databaseColumnMap['hidden'] ) )
			{
				$where = array( $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['hidden'] . '=-1' );
			}
			elseif ( isset( $currentClass::$databaseColumnMap['approved'] ) )
			{
				$where = array( $currentClass::$databasePrefix . $currentClass::$databaseColumnMap['approved'] . '=-1' );
			}
			
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( $currentClass::$title . '_pl' ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $currentClass::$title . '_pl' );
			
			$table = new \IPS\Helpers\Table\Content( $currentClass, \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=hidden&type={$currentType}", 'front', 'modcp_content' ), array( $where ) );
			$table->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_hidden' );
			$table->classes = array( 'ipsStream' );
			return \IPS\Theme::i()->getTemplate( 'modcp' )->tableWrapper( (string) $table );
		}
		
		/* Or all? */
		else
		{
			/* Init output */
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'search_everything' ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'search_everything' );
			
			/* Init query */
			$query = \IPS\Content\Search\Query::init()->setHiddenFilter( \IPS\Content\Search\Query::HIDDEN_HIDDEN )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_UPDATED );
			
			/* Can we only view some kinds of hidden content? */
			if ( \count( $exclude ) )
			{
				$filters = array();
				foreach ( $exclude as $class => $allowedContainers )
				{
					if( $allowedContainers )
					{
						$filters[] = \IPS\Content\Search\ContentFilter::init( $class )->excludeInContainers( $allowedContainers );
					}
					else
					{
						$filters[] = \IPS\Content\Search\ContentFilter::init( $class );
					}
				}

				if( !empty( $filters ) )
				{
					$query->filterByContent( $filters, FALSE );
				}
			}
			
			/* What page are we on? */
			$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
			if( $page < 1 )
			{
				$page = 1;
			}
			$query->setPage( $page );
			
			/* Query */
			$results = $query->search();
			
			/* Display */
			$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=hidden", 'front', 'modcp_content' ), ceil( $results->count( TRUE ) / $query->resultsToGet ), $page, $query->resultsToGet ) );
			return \IPS\Theme::i()->getTemplate( 'modcp' )->tableWrapper( \IPS\Theme::i()->getTemplate('search')->resultStream( $results, $pagination, \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=hidden", 'front', 'modcp_content' ), TRUE ), 'modcp_hidden' );
		}
	}
}