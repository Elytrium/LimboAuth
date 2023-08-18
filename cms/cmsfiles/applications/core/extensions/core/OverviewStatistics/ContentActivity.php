<?php
/**
 * @brief		Overview statistics extension: ContentActivity
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
 * @brief	Overview statistics extension: ContentActivity
 */
class _ContentActivity
{
	/**
	 * @brief	Which statistics page (activity or user)
	 */
	public $page	= 'activity';

	/**
	 * @brief	Content item classes returned by ContentRouter
	 */
	protected $classes	= array();

	/**
	 * Constructor: load content router classes
	 *
	 * @return void
	 */
	public function __construct()
	{
		/* Note that we only load content item classes - we will show comment/review counts within the block itself ourselves */
		foreach( \IPS\Content::routedClasses( FALSE, TRUE, TRUE ) as $class )
		{
			$this->classes[] = $class;
		}

		$this->classes[] = 'IPS\\core\\Messenger\\Conversation';
	}

	/**
	 * Return the sub-block keys
	 *
	 * @note This is designed to allow one class to support multiple blocks, for instance using the ContentRouter to generate blocks.
	 * @return array
	 */
	public function getBlocks()
	{
		return $this->classes;
	}

	/**
	 * Return block details (title and description)
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockDetails( $subBlock = NULL )
	{
		/* $subBlock will be set to the class we want to work with */
		return array( 'app' => $subBlock::$application, 'title' => $subBlock::$title . '_pl', 'description' => null, 'refresh' => 10, 'form' => (bool) isset( $subBlock::$containerNodeClass ) );
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
		/* Make sure someone isn't trying to manipulate the request or do something weird */
		if( !\in_array( $subBlock, $this->classes ) )
		{
			return '';
		}

		$classesToCheck	= array( $subBlock );
		$values			= array();
		$previousValues	= array();
		$nodeNames		= array();

		if ( isset( $subBlock::$commentClass ) )
		{
			$commentClass = $subBlock::$commentClass;
			if ( $commentClass::incrementPostCount() )
			{
				$classesToCheck[] = $commentClass;
			}
		}
		if ( isset( $subBlock::$reviewClass ) )
		{
			$reviewClass = $subBlock::$reviewClass;
			if ( $reviewClass::incrementPostCount() )
			{
				$classesToCheck[] = $subBlock::$reviewClass;
			}
		}

		/* Loop over our classes to fetch the data */
		foreach( $classesToCheck as $class )
		{
			/* Build where clause in the event we are filtering */
			$where = NULL;

			if( $dateRange !== NULL AND isset( $class::$databaseColumnMap['date'] ) )
			{
				$dateColumn = \is_array( $class::$databaseColumnMap['date'] ) ? $class::$databaseColumnMap['date'][0] : $class::$databaseColumnMap['date'];

				if( \is_array( $dateRange ) )
				{
					$where = array(
						array( $class::$databasePrefix . $dateColumn . ' > ?', $dateRange['start']->getTimestamp() ),
						array( $class::$databasePrefix . $dateColumn . ' < ?', $dateRange['end']->getTimestamp() ),
					);
				}
				else
				{
					$currentDate	= new \IPS\DateTime;
					$interval		= NULL;

					switch( $dateRange )
					{
						case '7':
							$interval = new \DateInterval( 'P7D' );
						break;

						case '30':
							$interval = new \DateInterval( 'P1M' );
						break;

						case '90':
							$interval = new \DateInterval( 'P3M' );
						break;

						case '180':
							$interval = new \DateInterval( 'P6M' );
						break;

						case '365':
							$interval = new \DateInterval( 'P1Y' );
						break;
					}

					$initialTimestamp = $currentDate->sub( $interval )->getTimestamp();
					$where = array( array( $class::$databasePrefix . $dateColumn . ' > ?', $initialTimestamp ) );

					$previousValues[ $class::$title . '_pl' ] = \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $this->_modifyWhereClause( array( array( $class::$databasePrefix . $dateColumn . ' BETWEEN ? AND ?', $currentDate->sub( $interval )->getTimestamp(), $initialTimestamp ) ), $class ) )->first();
				}
			}

			if( isset( $class::$containerNodeClass ) AND \IPS\Request::i()->nodes )
			{
				foreach( explode( ',', \IPS\Request::i()->nodes ) as $nodeId )
				{
					$containerClass = $class::$containerNodeClass;
					$nodeNames[] = $containerClass::load( $nodeId )->_title;
				}
			}

			$values[ $class::$title . '_pl' ] = \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $this->_modifyWhereClause( $where, $class ) )->first();
		}

		return \IPS\Theme::i()->getTemplate( 'activitystats' )->overviewCounts( $values, $previousValues, $nodeNames );
	}

	/**
	 * Modify the where clause to apply other filters
	 * 
	 * @param	array			$where	Current where clause
	 * @param	\IPS\Content	$class	Class we are working with
	 * @return	array
	 */
	protected function _modifyWhereClause( $where, $class )
	{
		/* Don't include soft deleted content */
		$column = NULL;
		if ( isset( $class::$databaseColumnMap['hidden'] ) )
		{
			$column = $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
		}
		elseif ( isset( $class::$databaseColumnMap['approved'] ) )
		{
			$column = $class::$databasePrefix . $class::$databaseColumnMap['approved'];
		}
		
		if ( $column )
		{
			$where[] = array( \IPS\Db::i()->in( $column, array( -2, -3 ), TRUE ) );
		}
		
		if ( method_exists( $class, 'overviewStatisticsWhere' ) )
		{
			$where = array_merge( $where, $class::overviewStatisticsWhere() );
		}
		
		if( isset( $class::$containerNodeClass ) AND \IPS\Request::i()->nodes )
		{
			$where[] = array( \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnMap['container'], explode( ',', \IPS\Request::i()->nodes ) ) );
		}

		return $where;
	}

	/**
	 * Return block filter form, or the updated block result upon submit
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockForm( $subBlock = NULL )
	{
		/* Make sure someone isn't trying to manipulate the request or do something weird */
		if( !\in_array( $subBlock, $this->classes ) )
		{
			return '';
		}

		if( !isset( $subBlock::$containerNodeClass ) )
		{
			return '';
		}

		$containerClass = $subBlock::$containerNodeClass;

		$form = new \IPS\Helpers\Form;
		$form->attributes['data-controller'] = 'core.admin.stats.nodeFilters';
		$form->attributes['data-block'] = \IPS\Request::i()->blockKey;
		$form->attributes['data-subblock'] = $subBlock;
		$form->add( new \IPS\Helpers\Form\Node( $containerClass::$nodeTitle, NULL, TRUE, array( 'class' => $containerClass, 'multiple' => TRUE, 'clubs' => TRUE ) ) );

		if( $values = $form->values() )
		{
			$dateFilters = NULL;

			if( \IPS\Request::i()->range )
			{
				$dateFilters = \IPS\Request::i()->range;
			}
			elseif( \IPS\Request::i()->start )
			{
				try
				{
					$timezone = \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL;
				}
				catch ( \Exception $e )
				{
					$timezone = NULL;
				}

				$dateFilters = array(
					'start'	=> new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->start ), $timezone ),
					'end'	=> new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->end ), $timezone )
				);
			}

			return $this->getBlock( $dateFilters, $subblock );
		}

		return $form;
	}
}