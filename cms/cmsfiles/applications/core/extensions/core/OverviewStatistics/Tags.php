<?php
/**
 * @brief		Overview statistics extension: Tags
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Jan 2020
 */

namespace IPS\core\extensions\core\OverviewStatistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Overview statistics extension: Tags
 */
class _Tags
{
	/**
	 * @brief	Which statistics page (activity or user)
	 */
	public $page	= 'activity';

	/**
	 * Return the sub-block keys
	 *
	 * @note This is designed to allow one class to support multiple blocks, for instance using the ContentRouter to generate blocks.
	 * @return array
	 */
	public function getBlocks()
	{
		return array( 'activity' );
	}

	/**
	 * Return block details (title and description)
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockDetails( $subBlock = NULL )
	{
		/* Description can be null and will not be shown if so */
		return \IPS\Settings::i()->tags_enabled ? array( 'app' => 'core', 'title' => 'stats_overview_toptags', 'description' => null, 'refresh' => 60 ) : NULL;
	}

	/** 
	 * Return the block HTML to show
	 *
	 * @param	array|NULL	$dateRange	NULL for all time, or an array with 'start' and 'end' \IPS\DateTime objects to restrict to
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	string
	 */
	public function getBlock( $dateRange = NULL, $subBlock = NULL )
	{
		$topTags = array();

		/* Add Rows */
		$where		= NULL;

		if( $dateRange !== NULL )
		{
			if( \is_array( $dateRange ) )
			{
				$where = array(
					array( 'tag_added > ?', $dateRange['start']->getTimestamp() ),
					array( 'tag_added < ?', $dateRange['end']->getTimestamp() ),
				);
			}
			else
			{
				$currentDate = new \IPS\DateTime;

				switch( $dateRange )
				{
					case '7':
						$where = array( array( 'tag_added > ? ', $currentDate->sub( new \DateInterval( 'P7D' ) )->getTimestamp() ) );
					break;

					case '30':
						$where = array( array( 'tag_added > ? ', $currentDate->sub( new \DateInterval( 'P1M' ) )->getTimestamp() ) );
					break;

					case '90':
						$where = array( array( 'tag_added > ? ', $currentDate->sub( new \DateInterval( 'P3M' ) )->getTimestamp() ) );
					break;

					case '180':
						$where = array( array( 'tag_added > ? ', $currentDate->sub( new \DateInterval( 'P6M' ) )->getTimestamp() ) );
					break;

					case '365':
						$where = array( array( 'tag_added > ? ', $currentDate->sub( new \DateInterval( 'P1Y' ) )->getTimestamp() ) );
					break;
				}
			}
		}
		
		/* Only get visible tags */
		$where[] = array( 'tag_aai_lookup IN(?)', \IPS\Db::i()->select( 'tag_perm_aai_lookup', 'core_tags_perms', [ 'tag_perm_visible=1' ] ) );
		
		foreach( \IPS\Db::i()->select( 'COUNT(*) as total, tag_text', 'core_tags', $where, 'total DESC', 5, 'tag_text' ) as $tag )
		{
			$topTags[ $tag['tag_text'] ] = $tag['total'];
		}

		return \IPS\Theme::i()->getTemplate( 'stats' )->overviewTable( $topTags );
	}
}