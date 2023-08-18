<?php
/**
 * @brief		Achievement Action Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Downloads
 * @since		30 Sep 2021
 */

namespace IPS\downloads\extensions\core\AchievementAction;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement Action Extension
 */
class _DownloadFile extends \IPS\core\Achievements\Actions\AbstractAchievementAction
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
		$return	= array();

		$return['nodes'] = new \IPS\Helpers\Form\Node( 'achievement_filter_DownloadFile_nodes', ( $filters and isset( $filters['nodes'] ) and $filters['nodes'] ) ? $filters['nodes'] : 0, FALSE, [
			'url'				=> $url,
			'class'				=> 'IPS\downloads\Category',
			'showAllNodes'		=> TRUE,
			'multiple' 			=> TRUE,
		], NULL, \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_NewContentItem_node_prefix', FALSE, [ 'sprintf' => [
			\IPS\Member::loggedIn()->language()->addToStack( 'downloads_file_sg_lc', FALSE ),
			\IPS\Member::loggedIn()->language()->addToStack( 'categories_sg_lc', FALSE )
		] ] ) );
		$return['nodes']->label = \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_NewContentItem_node', FALSE, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack( 'downloads_file_sg_lc', FALSE, [ 'strtolower' => TRUE ] ) ] ] );

		$return['milestone'] = new \IPS\Helpers\Form\Number( 'achievement_filter_DownloadFile_nth', ( $filters and isset( $filters['milestone'] ) and $filters['milestone'] ) ? $filters['milestone'] : 0, FALSE, [], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_nth_their'), \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_DownloadFile_nth_suffix') );
		$return['milestone']->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_nth');

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
		if ( isset( $values['achievement_filter_DownloadFile_nodes'] ) )
		{
			$return['nodes'] = array_keys( $values['achievement_filter_DownloadFile_nodes'] );
		}
		if ( isset( $values['achievement_filter_DownloadFile_nth'] ) )
		{
			$return['milestone'] = $values['achievement_filter_DownloadFile_nth'];
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
		if ( isset( $filters['nodes'] ) )
		{
			if ( !\in_array( $extra['file']->container()->_id, $filters['nodes'] ) )
			{
				return FALSE;
			}
		}

		if ( isset( $filters['milestone'] ) )
		{
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'downloads_downloads', [ 'dmid=?', $subject->member_id ] )->first() < $filters['milestone'] )
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Get the labels for the people this action might give awards to
	 *
	 * @param	array|NULL		$filters	Current filter values
	 *
	 * @return	array
	 */
	public function awardOptions( ?array $filters ): array
	{
		return [
			'subject'	=> 'achievement_filter_DownloadFile_receiver',
			'other'		=> 'achievement_filter_DownloadFile_giver'
		];
	}

	/**
	 * Get the "other" people we need to award =stuff to
	 *
	 * @param	mixed		$extra		Any additional information about what is happening (e.g. if a post is being made: the post object)
	 * @param	array|NULL	$filters	Current filter values
	 * @return	array
	 */
	public function awardOther( $extra = NULL, ?array $filters = NULL ): array
	{
		return [ $extra['downloader'] ];
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
		return (string) $extra['file']->id . '.' . $extra['downloader']->member_id;
	}
	
	/**
	 * Return a description for this action to show in the log
	 *
	 * @param	string	$identifier	The identifier as returned by identifier()
	 * @param	array	$actor		If the member was the "subject", "other", or both
	 * @return	string
	 */
	public function logRow( string $identifier, array $actor ): string
	{
		list( $fileId, $memberId ) = explode( '.', $identifier );

		try
		{
			$item = \IPS\downloads\File::load( $fileId );
			$sprintf = [ 'htmlsprintf' => [
				\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $item->url(), TRUE, $item->mapped('title') ?: $item->indefiniteArticle(), FALSE )
			] ];
		}
		catch ( \OutOfRangeException $e )
		{
			$sprintf = [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack('modcp_deleted') ] ];
		}

		return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__DownloadFile_log', FALSE, $sprintf );
	}
	
	/**
	 * Get "description" for rule
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|NULL
	 */
	public function ruleDescription( \IPS\core\Achievements\Rule $rule ): ?string
	{
		$conditions = [];
		if ( isset( $rule->filters['milestone'] ) )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_milestone', FALSE, [
				'htmlsprintf' => [
					\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'milestone', \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_milestone_nth', FALSE, [ 'pluralize' => [ $rule->filters['milestone'] ] ] ) )
				],
				'sprintf'		=> [ \IPS\Member::loggedIn()->language()->addToStack('AchievementAction__DownloadFile_title_generic') ]
			] );
		}

		if ( $nodeCondition = $this->_nodeFilterDescription( $rule ) )
		{
			$conditions[] = $nodeCondition;
		}

		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			\IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__DownloadFile_title' ),
			$conditions
		);
	}

	/**
	 * Get "description" for rule (usually a description of the rule's filters)
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|NULL
	 */
	protected function _nodeFilterDescription( \IPS\core\Achievements\Rule $rule ): ?string
	{
		if ( isset( $rule->filters['nodes'] ) )
		{
			$nodeNames = [];
			foreach ( $rule->filters['nodes'] as $id )
			{
				try
				{
					$nodeNames[] = \IPS\downloads\Category::load( $id )->_title;
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
								\IPS\Member::loggedIn()->language()->addToStack( 'download_categories_lc', FALSE, [ 'strtolower' => TRUE ] )
							] ] ),
							\count( $nodeNames ) === 1 ? NULL : $nodeNames
						)
					],
				] );
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
		return [ [
			'table' => 'downloads_downloads',
			'pkey'  => 'did',
			'date'  => 'dtime',
			'where' => [],
		] ];
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
		$file = \IPS\downloads\File::load( $row['dfid'] );
		$file->author()->achievementAction( 'downloads', 'DownloadFile', [
			'file' => $file,
			'downloader' => \IPS\Member::load( $row['dmid'] )
		] );
	}

}