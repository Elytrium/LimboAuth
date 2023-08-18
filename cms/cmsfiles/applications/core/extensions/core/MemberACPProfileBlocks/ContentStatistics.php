<?php
/**
 * @brief		ACP Member Profile: Content Statistics Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Content Statistics
 */
class _ContentStatistics extends \IPS\core\MemberACPProfile\TabbedBlock
{
	/**
	 * @brief	Percentages
	 */
	protected $percentages = array();
	
	/**
	 * @brief	Raw Counts
	 */
	protected $rawCounts = array();
	
	/**
	 * Get Block Title
	 *
	 * @return	string
	 */
	public function blockTitle()
	{
		return 'content_statistics';
	}
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		parent::__construct( $member );
		
		$classes = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter' ) as $contentRouter )
		{
			foreach ( $contentRouter->classes as $class )
			{
				$include = FALSE;
				$exploded = explode( '\\', $class );
				if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
				{
					if ( $class::incrementPostCount() )
					{
						$include = TRUE;
						$classes[ $exploded[1] ]['items'][] = $class;
					}
					if ( isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						if ( $commentClass::incrementPostCount() )
						{
							$include = TRUE;
							$classes[ $exploded[1] ]['comments'][] = $commentClass;
						}
					}
					if ( isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						if ( $reviewClass::incrementPostCount() )
						{
							$include = TRUE;
							$classes[ $exploded[1] ]['comments'][] = $class::$reviewClass;
						}
					}
				}
				elseif ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
				{
					if ( $class::incrementPostCount() )
					{
						$include = TRUE;
						$classes[ $exploded[1] ]['comments'][] = $class;
					}
				}
				
				if ( $include and !isset( $this->percentages[ $exploded[1] ] ) )
				{
					$this->percentages[ $exploded[1] ] = 0;
				}
			}
		}
				
		$sum = 0;
		$counts = array();
		foreach ( $classes as $app => $types )
		{
			foreach ( $types as $type => $typeClasses )
			{
				foreach ( $typeClasses as $class )
				{
					if( !isset( $class::$databaseColumnMap['author'] ) )
					{
						continue;
					}
					$authorColumn = $class::$databasePrefix . $class::$databaseColumnMap['author'];
					
					if ( !isset( $counts[ $app ][ $type ] ) )
					{
						$counts[ $app ][ $type ] = 0;
					}
					if ( !isset( $this->rawCounts[ $app ][ $class ] ) )
					{
						$this->rawCounts[ $app ][ $class ] = 0;
					}
					
					$count = $class::memberPostCount( $member, TRUE );
					$sum += $count;
					$counts[ $app ][ $type ] += $count;
					$this->rawCounts[ $app ][ $class ] += $count;
				}
			}
		}
		
		if ( $sum )
		{
			foreach ( $counts as $app => $types )
			{
				if ( array_sum( $types ) )
				{
					$this->percentages[ $app ] = number_format( round( 100 / $sum * array_sum( $types ), 2 ), 2 );
				}
			}
		}
	}
	
	/**
	 * Get Tab Names
	 *
	 * @return	string
	 */
	public function tabs()
	{
		$return = array();
		
		if ( array_sum( $this->percentages ) )
		{
			$return['breakdown'] = 'content_count_breakdown';
		}
		
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberACPProfileContentTab', TRUE, NULL, NULL, FALSE ) as $class )
		{
			try
			{
				$ext = new $class( $this->member );
				$exploded = explode( '\\', $class );
				$return[ $exploded[1] . '_' . $exploded[5] ] = 'content_stats__' . $exploded[1] . '_' . $exploded[5];
			}
			catch ( \Exception $e ) { }
		}
				
		return $return;
	}

	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function tabOutput( $tab )
	{
		if ( $tab == 'breakdown' )
		{
			return \IPS\Theme::i()->getTemplate('memberprofile')->contentBreakdown( $this->member, $this->percentages, $this->rawCounts );
		}
		else
		{
			$exploded = explode( '_', $tab );
			$class = 'IPS\\' . $exploded[0] . '\\extensions\\core\\MemberACPProfileContentTab\\' . $exploded[1];
			
			if ( class_exists( $class ) )
			{
				$ext = new $class( $this->member );
				return $ext->output();
			}
			
			return '';
		}
	}
}