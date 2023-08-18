<?php
/**
 * @brief		GraphQL: Stats Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		21 Sep 2020
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * StatsType for GraphQL API
 */
class _StatsType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Stats',
			'description' => 'Statistics',
			'fields' => function () {
				return [
					'contentCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Content count",
						'resolve' => function () {
							return (int) static::getContentCount();
						}
					],
					'memberCount' => [
						'type' => TypeRegistry::int(),
						'description' => 'Member count',
						'resolve' => function () {
							return (int) static::getMemberCount();
						}
					]					
				];
			}
		];

		parent::__construct($config);
	}

	protected static function getContentCount()
	{
		$cacheKey = 'content_count';
		$total = 0;

		try
		{
			return \IPS\Data\Cache::i()->getWithExpire( $cacheKey, TRUE );
		}
		catch( \OutOfRangeException $e ){}

		foreach( \IPS\Application::enabledApplications() as $app )
		{
			foreach( $app->extensions( 'core', 'ContentRouter', TRUE, TRUE ) as $object )
			{			
				foreach( $object->classes as $itemClass )
				{
					foreach( array( 'items', 'comments', 'reviews' ) as $type )
					{	
						try 
						{
							$classes = [];
							switch ( $type )
							{
								case 'items':
									$classes[] = $itemClass;
									break;
								case 'comments':
									if ( isset( $itemClass::$commentClass ) )
									{
										$classes[] = $itemClass::$commentClass;
									}
									if ( isset( $itemClass::$archiveClass ) )
									{
										$classes[] = $itemClass::$archiveClass;
									}
									break;
								case 'reviews':
									if ( isset( $itemClass::$reviewClass ) )
									{
										$classes[] = $itemClass::$reviewClass;
									}
									break;
							}
							
							if ( $classes )
							{						
								foreach ( $classes as $class )
								{
									$where = method_exists( $class, 'digestWhere' ) ? $class::digestWhere() : [];
									if ( isset( $class::$databaseColumnMap['approved'] ) )
									{
										$where[] = [ $class::$databasePrefix . $class::$databaseColumnMap['approved'] . '=1' ];
									}
									if ( isset( $class::$databaseColumnMap['hidden'] ) )
									{
										$where[] = [ $class::$databasePrefix . $class::$databaseColumnMap['hidden'] . '=0' ];
									}
									
									$total += $class::db()->select( 'COUNT(*)', $class::$databaseTable, $where )->first();
								}
							}
						} 
						catch( \Exception $e ){}
					}
				}
			}	
		}

		\IPS\Data\Cache::i()->storeWithExpire( $cacheKey, $total, \IPS\DateTime::create()->add( new \DateInterval('P1W') ), TRUE );
		
		return $total;
	}

	protected static function getMemberCount()
	{
		try 
		{
			$memberCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', array( 'completed=?', true ) )->first();
			return $memberCount;
		} 
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
}

