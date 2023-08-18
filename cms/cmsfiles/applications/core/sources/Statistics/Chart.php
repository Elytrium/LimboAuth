<?php
/**
 * @brief		Statistics Charts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Jul 2015
 */

namespace IPS\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Charts
 */
abstract class _Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = NULL;
	
	/**
	 * Get Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * 
	 * @return \IPS\Helpers\Chart
	 */
	abstract public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart;
	
	/**
	 * Load from Extension
	 *
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromExtension( string $app, string $extension ): static
	{
		$extensions = \IPS\Application::load( $app )->extensions( 'core', 'Statistics' );
		if ( isset( $extensions[ $extension ] ) )
		{
			return $extensions[ $extension ];
		}
		
		throw new \OutOfRangeException;
	}
	
	/**
	 * Load from Controller
	 *
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromController( string $controller ): static
	{
		foreach( \IPS\Application::allExtensions( 'core', 'Statistics', FALSE ) AS $extension )
		{
			if ( $extension->controller AND $extension->controller === $controller )
			{
				return $extension;
			}
		}
		
		throw new \OutOfRangeException;
	}
	
	/**
	 * Construct a saved chart from data
	 *
	 * @param	array|int			$data			Chart ID or pre-loaded chart data.
	 * @param	\IPS\Http\Url		$url			URL chart is shown on
	 * @param	\IPS\Member|bool	$check			Check chart is owned by a specific member, or the currently logged in member if TRUE. If FALSE, no permission checking.
	 * 
	 * @return	\IPS\Helpers\Chart
	 * @throws	\OutOfRangeException
	 * @throws	\InvalidArgumentException
	 */
	public static function constructMemberChartFromData( array|int $data, \IPS\Http\Url $url, \IPS\Member|bool $check = TRUE ): \IPS\Helpers\Chart
	{
		try
		{
			if ( $check === FALSE )
			{
				if ( !\is_array( $data ) )
				{
					$data = \IPS\Db::i()->select( '*', 'core_saved_charts', array( "id=?", $data ) )->first();
				}
			}
			else if ( ( $check instanceof \IPS\Member ) AND $check->member_id )
			{
				if ( !\is_array( $data ) )
				{
					$data = \IPS\Db::i()->select( '*', 'core_saved_charts', array( "chart_id=? AND chart_member=?", $data, $check->member_id ) )->first();
				}
				else if ( $data['chart_member'] !== $check->member_id )
				{
					throw new \UnderflowException;
				}
			}
			else if ( $check === TRUE AND \IPS\Member::loggedIn()->member_id )
			{
				if ( !\is_array( $data ) )
				{
					$data = \IPS\Db::i()->select( '*','core_saved_charts', array( "chart_id=? AND chart_member=?", $data, \IPS\Member::loggedIn()->member_id ) )->first();
				}
				else if ( $data['chart_member'] !== \IPS\Member::loggedIn()->member_id )
				{
					throw new \UnderflowException;
				}
			}
			else
			{
				/* If we're here, we were passed a guest object which isn't going to work. Throw a different exception as this should lead directly to a bugfix since this should never happen. */
				throw new \InvalidArgumentException;
			}
			
			$extension = static::loadFromController( $data['chart_controller'] );
			$chart = $extension->getChart( $url );
			$chart->currentFilters = json_decode( $data['chart_configuration'], true );
			$chart->timescale = $data['chart_timescale'] ?? $chart->timescale;
			$chart->title = $data['chart_title'];
			$chart->showFilterTabs = FALSE;
			$chart->showIntervals = FALSE;
			$chart->showDateRange = FALSE;
			
			return $chart;
		}
		catch( \UnderflowException $e )
		{
			/* Chart doesn't exist, or isn't owned by $member */
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Get charts for Member
	 *
	 * @param	\IPS\Http\Url		$url		URL
	 * @param	bool				$idsOnly	Return only ID's belonging to the user for lazyloading.
	 * @param	\IPS\Member|null	$member		Member, or NULL for currently logged in member.
	 *
	 * @return	array
	 * @throws	\InvalidArgumentException
	 */
	public static function getChartsForMember( \IPS\Http\Url $url, bool $idsOnly = FALSE, ?\IPS\Member $member = NULL ): array
	{
		$member ??= \IPS\Member::loggedIn();
		
		if ( !$member->member_id )
		{
			throw new \InvalidArgumentException;
		}
		
		$return = [];
		
		foreach( \IPS\Db::i()->select( '*', 'core_saved_charts', array( "chart_member=?", $member->member_id ) ) AS $chart )
		{
			if ( $idsOnly )
			{
				$return[] = $chart['chart_id'];
			}
			else
			{
				try
				{
					$controller = explode( '_', $chart['chart_controller'] );
					$return[$chart['chart_id']] = array(
						'chart'		=> static::constructMemberChartFromData( $chart, $url, $member ),
						'data'		=> $chart
					);
				}
				catch( \OutOfRangeException )
				{
					continue;
				}
			}
		}
		
		return $return;
	}
}