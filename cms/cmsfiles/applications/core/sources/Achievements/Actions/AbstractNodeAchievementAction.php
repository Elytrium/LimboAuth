<?php
/**
 * @brief		Abstract Achievement Action Extension for node-related things
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
abstract class _AbstractNodeAchievementAction extends AbstractAchievementAction
{
	/**
	 * Get filter form elements
	 *
	 * @param	array|NULL		$filters	Current filter values (if editing)
	 * @param	\IPS\Http\Url	$url		The URL the form is being shown on
	 * @return	array
	 */
	public function filters( ?array $filters, \IPS\Http\Url $url ): array
	{
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
				if ( isset( $class::$containerNodeClass ) )
				{
					$nodeClass = $class::$containerNodeClass;
					$nodeFilterKey = 'achievement_subfilter_Node_type_' . str_replace( '\\', '-', $nodeClass );
					$contentTypeToggles[ $nodeClass ][] = $nodeFilterKey;
					$contentTypeToggles[ $nodeClass ] = [ $nodeFilterKey ];
					$contentTypeOptions[ $nodeClass ] = $nodeClass::fullyQualifiedType();

					$nodeFilter = new \IPS\Helpers\Form\Node( $nodeFilterKey, ( $filters and isset( $filters[ 'nodes_' . str_replace( '\\', '-', $nodeClass ) ] ) and $filters[ 'nodes_' . str_replace( '\\', '-', $nodeClass ) ] ) ? $filters[ 'nodes_' . str_replace( '\\', '-', $nodeClass ) ] : 0, FALSE, [
						'url'				=> $url,
						'class'				=> $class::$containerNodeClass,
						'showAllNodes'		=> TRUE,
						'multiple' 			=> TRUE,
					], NULL, \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_Node_node_prefix') );
					$nodeFilter->label = \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_Node_node', FALSE, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack( ($class::$containerNodeClass)::$nodeTitle . '_sg', FALSE, [ 'strtolower' => TRUE ] ) ] ] );
					$_return[ "nodes_" . str_replace( '\\', '-', $nodeClass ) ] = $nodeFilter;
				}
			}
		}

		$typeFilter = new \IPS\Helpers\Form\Select( 'achievement_filter_Node_type', ( $filters and isset( $filters['type'] ) and $filters['type'] ) ? $filters['type'] : NULL, FALSE, [ 'options' => $contentTypeOptions, 'toggles' => $contentTypeToggles ], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_type_prefix') );
		$typeFilter->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_type');

		$nthFilter = new \IPS\Helpers\Form\Number( 'achievement_filter_Node_nth', ( $filters and isset( $filters['milestone'] ) and $filters['milestone'] ) ? $filters['milestone'] : 0, FALSE, [], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_nth_their'), \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_Node_nth_suffix') );
		$nthFilter->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_Node_nth');

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
		$return = [];
		if ( isset( $values['achievement_filter_Node_type'] ) )
		{			
			$return['type'] = $values['achievement_filter_Node_type'];
			
			$nodeClass = $return['type'];
			if ( isset( $values[ 'achievement_subfilter_Node_type_' . str_replace( '\\', '-', $nodeClass ) ] ) )
			{
				$return[ 'nodes_' . str_replace( '\\', '-', $nodeClass ) ] = array_keys( $values[ 'achievement_subfilter_Node_type_' . str_replace( '\\', '-', $nodeClass ) ] );
			}
		}
		if ( isset( $values['achievement_filter_Node_nth'] ) )
		{
			$return['milestone'] = $values['achievement_filter_Node_nth'];
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
			
			if ( isset( $filters[ 'nodes_' . str_replace( '\\', '-', \get_class( $extra ) ) ] ) )
			{
				if ( !\in_array( $extra->_id, $filters[ 'nodes_' . str_replace( '\\', '-', \get_class( $extra ) ) ] ) )
				{
					return FALSE;
				}
			}
		}

		return TRUE;
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
		return \get_class( $extra ) . ':' . $extra->{$extra::$databaseColumnId} . ':' . $subject->member_id;
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
			$nodeClass = $rule->filters['type'];

			if ( isset( $rule->filters[ 'nodes_' . str_replace( '\\', '-', $nodeClass ) ] ) )
			{
				$nodeNames = [];
				foreach ( $rule->filters[ 'nodes_' . str_replace( '\\', '-', $nodeClass ) ] as $id )
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
									\IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle, FALSE, [ 'strtolower' => TRUE ] )
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
}