<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		26 Jan 2023
 */

namespace IPS\core\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Language extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_preferences_theme';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart;
		$counts = iterator_to_array( \IPS\Db::i()->select( 'language, COUNT(member_id) as count', 'core_members', array( "language > ?", 0), NULL, NULL, "language" )->setKeyField( 'language' ) );

		$chart->addHeader( "language", "string" );
		$chart->addHeader( \IPS\Member::loggedIn()->language()->get('chart_members'), "number" );
		
		/* We need to make sure the language exists - otherwise apply the count to the default language. */
		$rows = [];
		foreach( $counts as $id => $lang )
		{
			try
			{
				$l = \IPS\Lang::load( $id );
				if ( !isset( $rows[ $id ] ) )
				{
					$rows[ $id ] = array( 'title' => $l->title, 'count' => 0 );
				}
				$rows[ $id ]['count'] += $lang['count'];
			}
			catch( \OutOfRangeException $e )
			{
				if ( !isset( $rows[ \IPS\Lang::defaultLanguage() ] ) )
				{
					$rows[ \IPS\Lang::defaultLanguage() ] = array( 'title' => \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->title, 'count' => 0 );
				}
				$rows[ \IPS\Lang::defaultLanguage() ]['count'] += $lang['count'];
			}
		}
		
		/* Now add the rows to the chart */
		foreach( $rows AS $row )
		{
			$chart->addRow( array( $row['title'], $row['count'] ) );
		}
		
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_language_title');
		
		return $chart;
	}
}