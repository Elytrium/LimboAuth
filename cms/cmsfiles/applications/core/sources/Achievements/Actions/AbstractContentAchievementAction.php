<?php
/**
 * @brief		Abstract Achievement Action Extension for content-related things
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @since		24 Feb 2021
 */

namespace IPS\core\Achievements\Actions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Achievement Action Extension for content-related things
 */
abstract class _AbstractContentAchievementAction extends AbstractAchievementAction
{	
	protected static $includeItems = TRUE;
	protected static $includeComments = TRUE;
	protected static $includeReviews = TRUE;
	
	/**
	 * Get filter form elements
	 *
	 * @param	array|NULL		$filters	Current filter values (if editing)
	 * @param	\IPS\Http\Url	$url		The URL the form is being shown on
	 * @return	array
	 */
	public function filters( ?array $filters, \IPS\Http\Url $url ): array
	{
		$classKey = explode( '\\', \get_called_class() )[5];
		
		$contentTypeOptions = [];
		$contentTypeToggles = [];
		$_return = [];
		
		$defaultApp = NULL;
		foreach( \IPS\Application::applications() as $directory => $application )
		{
			if( $application->default )
			{
				$defaultApp	= $directory;
				break;
			}
		}
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE, $defaultApp ) as $extension )
		{
			foreach ( $extension->classes as $class )
			{
				if ( isset( $class::$databaseColumnMap['author'] ) )
				{
					$nodeFilterKey = NULL;
					$haveMatchingClass = FALSE;
					if ( isset( $class::$containerNodeClass ) )
					{
						$nodeFilterKey = 'achievement_subfilter_' . $classKey . '_type_' . str_replace( '\\', '-', $class );
						$contentTypeToggles[ $class ][] = $nodeFilterKey;
					}
					if ( static::$includeItems )
					{
						$contentTypeOptions[ $class ] = $class::$title;
						$contentTypeToggles[ $class ] = $nodeFilterKey ? [ $nodeFilterKey ] : [];
						$haveMatchingClass = TRUE;
					}
					if ( static::$includeComments and isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						$contentTypeOptions[ $commentClass ] = $commentClass::$title;
						$contentTypeToggles[ $commentClass ] = $nodeFilterKey ? [ $nodeFilterKey ] : [];
						$haveMatchingClass = TRUE;
					}
					if ( static::$includeReviews and isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						$contentTypeOptions[ $reviewClass ] = $reviewClass::$title;
						$contentTypeToggles[ $reviewClass ] = $nodeFilterKey ? [ $nodeFilterKey ] : [];
						$haveMatchingClass = TRUE;
					}
					
					if ( $nodeFilterKey and $haveMatchingClass )
					{
						try
						{
							$nodeTitle = \IPS\Member::loggedIn()->language()->get( ($class::$containerNodeClass)::$nodeTitle . '_sg_lc' );
						}
						catch( \UnderflowException $e )
						{
							$nodeTitle = ($class::$containerNodeClass)::$nodeTitle . '_sg';
						}

						$nodeFilter = new \IPS\Helpers\Form\Node( $nodeFilterKey, ( $filters and isset( $filters[ 'nodes_' . str_replace( '\\', '-', $class ) ] ) and $filters[ 'nodes_' . str_replace( '\\', '-', $class ) ] ) ? $filters[ 'nodes_' . str_replace( '\\', '-', $class ) ] : 0, FALSE, [
							'url'				=> $url,
							'class'				=> $class::$containerNodeClass,
							'showAllNodes'		=> TRUE,
							'multiple' 			=> TRUE,
						], NULL, \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_NewContentItem_node_prefix', FALSE, [ 'sprintf' => [
							\IPS\Member::loggedIn()->language()->addToStack( $class::_definiteArticle(), FALSE ),
							\IPS\Member::loggedIn()->language()->addToStack( $nodeTitle, FALSE )
						] ] ) );
						$nodeFilter->label = \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_NewContentItem_node', FALSE, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack( $nodeTitle, FALSE ) ] ] );
						$_return[ "nodes_" . str_replace( '\\', '-', $class ) ] = $nodeFilter;
					}
				}
			}
		}
		
		$typeFilter = new \IPS\Helpers\Form\Select( 'achievement_filter_' . $classKey . '_type', ( $filters and isset( $filters['type'] ) and $filters['type'] ) ? $filters['type'] : NULL, FALSE, [ 'options' => $contentTypeOptions, 'toggles' => $contentTypeToggles ], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_type_prefix') );
		$typeFilter->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_type');

		$nthFilter = new \IPS\Helpers\Form\Number( 'achievement_filter_' . $classKey . '_nth', ( $filters and isset( $filters['milestone'] ) and $filters['milestone'] ) ? $filters['milestone'] : 0, FALSE, [], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_nth_their'), \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_' . $classKey . '_nth_suffix') );
		$nthFilter->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_nth');

		$return = [ 'type' => $typeFilter ];
		foreach ( $_return as $k => $v )
		{
			$return[ $k ] = $v;
		}
		$return['milestone'] = $nthFilter;

		return $return;
	}
	
	/**
	 * Format filter form values
	 *
	 * @param	array	$values	The values from the form
	 * @return	array
	 */
	public function formatFilterValues( array $values ): array
	{
		$classKey = explode( '\\', \get_called_class() )[5];
		
		$return = [];
		if ( isset( $values['achievement_filter_' . $classKey . '_type'] ) )
		{			
			$return['type'] = $values['achievement_filter_' . $classKey . '_type'];
			
			$itemClass = $return['type'];
			if ( \in_array( 'IPS\Content\Comment', class_parents( $return['type'] ) ) )
			{
				$itemClass = $return['type']::$itemClass;
			}
			
			if ( isset( $values[ 'achievement_subfilter_' . $classKey . '_type_' . str_replace( '\\', '-', $itemClass ) ] ) )
			{
				$return[ 'nodes_' . str_replace( '\\', '-', $itemClass ) ] = array_keys( $values[ 'achievement_subfilter_' . $classKey . '_type_' . str_replace( '\\', '-', $itemClass ) ] );
			}
		}
		if ( isset( $values['achievement_filter_' . $classKey . '_nth'] ) )
		{
			$return['milestone'] = $values['achievement_filter_' . $classKey . '_nth'];
		}
		return $return;
	}
	
	/**
	 * Work out if the filters applies for a given action
	 *
	 * Important note for milestones: consider the context. This method is called by \IPS\Member::achievementAction(). If your code 
	 * calls that BEFORE making its change in the database (or there is read/write separation), you will need to add
	 * 1 to the value being considered for milestones
	 *
	 * @param	\IPS\Member	$subject	The subject member
	 * @param	array		$filters	The value returned by formatFilterValues()
	 * @param	mixed		$extra		Any additional information about what is happening (e.g. if a post is being made: the post object)
	 * @return	bool
	 */
	public function filtersMatch( \IPS\Member $subject, array $filters, $extra = NULL ): bool
	{
		if ( isset( $filters['type'] ) )
		{
			if ( !( $extra instanceof $filters['type'] ) )
			{
				return FALSE;
			}
			
			$item = $extra;
			if ( \in_array( 'IPS\Content\Comment', class_parents( $filters['type'] ) ) )
			{
				$item = $extra->item();
			}

			if ( isset( $filters[ 'nodes_' . str_replace( '\\', '-', \get_class( $item ) ) ] ) )
			{
				if ( !\in_array( $item->container()->_id, $filters[ 'nodes_' . str_replace( '\\', '-', \get_class( $item ) ) ] ) )
				{
					return FALSE;
				}
			}

			return TRUE;
		}
		else
		{
			/* Make sure the class is a contentRouter approved class, mostly to stop private messages being awarded points */
			foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $extension )
			{
				foreach ( $extension->classes as $class )
				{
					if ( \get_class( $extra ) == $class )
					{
						return TRUE;
					}
					elseif ( isset( $class::$commentClass ) and \get_class( $extra ) == $class::$commentClass )
					{
						return TRUE;
					}
					elseif ( isset( $class::$reviewClass ) and \get_class( $extra ) == $class::$reviewClass )
					{
						return TRUE;
					}
				}
			}

			return FALSE;
		}

		/* Stop PHPStorm from complaining about incorrect return types >_> */
		return FALSE;
	}
		
	/**
	 * Get identifier to prevent the member being awarded points for the same action twice
	 * Must be unique within within of this domain, must not exceed 32 chars.
	 *
	 * @param	\IPS\Member	$subject	The subject member
	 * @param	mixed		$extra		Any additional information about what is happening (e.g. if a post is being made: the post object)
	 * @return	string
	 */
	public function identifier( \IPS\Member $subject, $extra = NULL ): string
	{
		return \get_class( $extra ) . ':' . $extra->{$extra::$databaseColumnId};
	}
	
	/**
	 * Get "description" for rule (usually a description of the rule's filters)
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|NULL
	 */
	protected function _nodeFilterDescription( \IPS\core\Achievements\Rule $rule ): ?string
	{
		if ( isset( $rule->filters['type'] ) )
		{
			$itemClass = $rule->filters['type'];
			if ( \in_array( 'IPS\Content\Comment', class_parents( $rule->filters['type'] ) ) )
			{
				$itemClass = $itemClass::$itemClass;
			}
			
			if ( isset( $rule->filters[ 'nodes_' . str_replace( '\\', '-', $itemClass ) ] ) )
			{
				$nodeClass = $itemClass::$containerNodeClass;
				
				$nodeNames = [];
				foreach ( $rule->filters[ 'nodes_' . str_replace( '\\', '-', $itemClass ) ] as $id )
				{
					try
					{
						$nodeNames[] = $nodeClass::load( $id )->_title;
					}
					catch ( \OutOfRangeException $e ) {}
				}
				if ( $nodeNames )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_location', FALSE, [
						'htmlsprintf' => [
							\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'location',
								\count( $nodeNames ) === 1 ? $nodeNames[0] : \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_location_val', FALSE, [ 'sprintf' => [
									\count( $nodeNames ),
									\IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle, FALSE )
								] ] ),
								\count( $nodeNames ) === 1 ? NULL : $nodeNames
							)
						],
					] );
				}
			}
		}
		return NULL;
	}

	/**
	 * Get rebuild data
	 *
	 * @return	array
	 */
	static public function rebuildData()
	{
		$return = [];
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $extension )
		{
			foreach ( $extension->classes as $class )
			{
				if ( isset( $class::$databaseColumnMap['author'] ) )
				{
					if ( static::$includeItems )
					{
						$return[] = [
							'table' => $class::$databaseTable,
							'pkey'  => $class::$databasePrefix . $class::$databaseColumnId,
							'date'  => ( isset( $class::$databaseColumnMap['date'] ) ) ? $class::$databasePrefix . $class::$databaseColumnMap['date'] : NULL,
							'where' => [],
							'class' => $class
						];
					}
					if ( static::$includeComments and isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						$return[] = [
							'table' => $commentClass::$databaseTable,
							'pkey'  => $commentClass::$databasePrefix . $commentClass::$databaseColumnId,
							'date'  => ( isset( $commentClass::$databaseColumnMap['date'] ) ) ? $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] : NULL,
							'where' => [],
							'class' => $commentClass
						];
					}
					if ( static::$includeReviews and isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						$return[] = [
							'table' => $reviewClass::$databaseTable,
							'pkey'  => $reviewClass::$databasePrefix . $reviewClass::$databaseColumnId,
							'date'  => ( isset( $reviewClass::$databaseColumnMap['date'] ) ) ? $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['date'] : NULL,
							'where' => [],
							'class' => $reviewClass
						];
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Get a where clause to check based on the type/class
	 *
	 * @param	array				$filters	The value returned by formatFilterValues()
	 * @param	string				$class		Class we are working with
	 * @param	\IPS\DateTime|null	$time		Any time limit to add
	 * @return	array
	 */
	protected function getWherePerType( $filters, $class, $time=NULL ): array
	{
		$where = ( $time ? [ [ $class::$databasePrefix . $class::$databaseColumnMap['date'] . ' >= ?', $time->getTimestamp() ] ] : [] );
		$itemClass = $class;

		if ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
		{
			$itemClass = $class::$itemClass;
		}

		if ( isset( $filters[ 'nodes_' . str_replace( '\\', '-', $itemClass ) ] ) )
		{
			if ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
			{
				$itemClass = $class::$itemClass;
				$where[] = [ $class::$databasePrefix . $class::$databaseColumnMap['item'] . ' IN(?)',
					\IPS\Db::i()->select( $itemClass::$databasePrefix . $itemClass::$databaseColumnId, $itemClass::$databaseTable, [ \IPS\Db::i()->in( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'], $filters[ 'nodes_' . str_replace( '\\', '-', $itemClass ) ] ) ] ) ];
			}
			else
			{
				$where[] = [ \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnMap['container'], $filters[ 'nodes_' . str_replace( '\\', '-', $class ) ] ) ];
			}
		}

		return $where;
	}

	/**
	 * Process the rebuild row
	 *
	 * @param array		$row	Row from database
	 * @param array		$data	Data collected when starting rebuild [table, pkey...]
	 * @return void
	 */
	public static function rebuildRow( $row, $data )
	{
		$classCalled = explode( '\\', \get_called_class() );
		$class = $data['class'];
		$item = $class::constructFromData( $row );
		$item->author()->achievementAction( 'core', array_pop( $classCalled ), $item );
	}
}