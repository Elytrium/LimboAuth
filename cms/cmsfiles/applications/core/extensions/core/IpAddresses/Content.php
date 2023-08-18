<?php
/**
 * @brief		IP Address Lookup: Content Classes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Dec 2013
 */

namespace IPS\core\extensions\core\IpAddresses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IP Address Lookup: Content Classes
 */
class _Content extends \IPS\Content\ExtensionGenerator
{
	/**
	 * @brief	If TRUE, will include archive classes
	 */
	protected static $includeArchive = TRUE;

	/**
	 * Supported in the ACP IP address lookup tool?
	 *
	 * @return	bool
	 * @note	If the method does not exist in an extension, the result is presumed to be TRUE
	 */
	public function supportedInAcp()
	{
		return TRUE;
	}

	/**
	 * Supported in the ModCP IP address lookup tool?
	 *
	 * @return	bool
	 * @note	If the method does not exist in an extension, the result is presumed to be TRUE
	 */
	public function supportedInModCp(): bool
	{
		return TRUE;
	}

	/** 
	 * Find Records by IP
	 *
	 * @param	string			$ip			The IP Address
	 * @param	\IPS\Http\Url	$baseUrl	URL table will be displayed on or NULL to return a count
	 * @return	\IPS\Helpers\Table|null
	 */
	public function findByIp( $ip, \IPS\Http\Url $baseUrl = NULL )
	{
		$class = $this->class;
		
		if( !isset( $class::$databaseColumnMap['ip_address'] ) OR !$class::$databaseColumnMap['ip_address'] )
		{
			return NULL;
		}

		if ( ! \IPS\Application::appIsEnabled( $class::$application ) )
		{
			return NULL;
		}
		
		$where = array( "{$class::$databasePrefix}{$class::$databaseColumnMap['ip_address']} LIKE ?" , $ip );

		/* Don't need Posts Before Registration */
		if ( isset( $class::$databaseColumnMap['hidden'] ) )
		{
			$where[0] .= " AND {$class::$databasePrefix}{$class::$databaseColumnMap['hidden']} <> -3";
		}

		if ( isset( $class::$databaseColumnMap['approved'] ) )
		{
			$where[0] .= " AND {$class::$databasePrefix}{$class::$databaseColumnMap['approved']} <> -3";			
		}

		/* Does the class have any filters? */
		if( method_exists( $class,'findByIPWhere') )
		{
			$where[0] .= $class::findByIPWhere();
		}

		/* Return count */
		if ( $baseUrl === NULL )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $where )->first();
		}
		
		/* Init Table */
		$table = new \IPS\Helpers\Table\Db( $class::$databaseTable, $baseUrl, $where );
		
		$table->tableTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'table' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'rows' );
		
		/* Columns we need */
		if ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
		{
			$table->include = array( $class::$databasePrefix . $class::$databaseColumnMap['item'], $class::$databasePrefix . $class::$databaseColumnMap['author'], $class::$databasePrefix . $class::$databaseColumnMap['date'], $class::$databasePrefix . $class::$databaseColumnMap['ip_address'] );
			$table->mainColumn = $class::$databasePrefix . $class::$databaseColumnMap['item'];
			
			$table->parsers = array(
				$class::$databasePrefix . $class::$databaseColumnMap['item']	=> function( $val, $data ) use ( $class )
				{
					try
					{
						$comment = $class::load( $data[ $class::$databasePrefix . $class::$databaseColumnId ] );
						return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $comment->url(), TRUE, $comment->item()->mapped('title') );
					}
					catch ( \OutOfRangeException $e )
					{
						return '';
					}
				}
			);
			
			$contentClass = $class::$itemClass;
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['item'] ] = \IPS\Member::loggedIn()->language()->addToStack( $contentClass::$title, FALSE );
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['author'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'author', FALSE );
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['content'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'content', FALSE );
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['date'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'date', FALSE );
		}
		else
		{
			foreach ( array( 'title', 'container', 'author', 'date', 'ip_address' ) as $k )
			{
				if ( isset( $class::$databaseColumnMap[ $k ] ) )
				{
					$table->include[] = $class::$databasePrefix . $class::$databaseColumnMap[ $k ];
				}
			}
			
			$table->mainColumn = $class::$databasePrefix . $class::$databaseColumnMap['title'];
			
			$table->parsers = array(
				$class::$databasePrefix . $class::$databaseColumnMap['title']	=> function( $val, $data ) use ( $class )
				{
					/* In rare occasions, like status updates, there is no title and we just return the content */
					if( $class::$databaseColumnMap['title'] == $class::$databaseColumnMap['content'] )
					{
						$val	= trim( strip_tags( $val ) );

						if( !$val )
						{
							$val	= \IPS\Member::loggedIn()->language()->get('no_content_to_show');
						}
					}

					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $class::load( $data[ $class::$databasePrefix . $class::$databaseColumnId ] )->url(), TRUE, $val );
				},
			);
			if( isset( $class::$databaseColumnMap['container'] ) )
			{
				$table->parsers[ $class::$databasePrefix . $class::$databaseColumnMap['container'] ] = function( $val ) use ( $class )
				{
					$nodeClass = $class::$containerNodeClass;
					$node = $nodeClass::load( $val );
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $node->url(), TRUE, $node->_title );
				};
			}
			
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['title'] ] = \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE );
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['author'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'author', FALSE );
			\IPS\Member::loggedIn()->language()->words[ $class::$databasePrefix . $class::$databaseColumnMap['date'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'date', FALSE );
		}
				
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: $class::$databasePrefix . $class::$databaseColumnMap['date'];
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Custom parsers */
		$table->parsers = array_merge( $table->parsers, array(
			$class::$databasePrefix . $class::$databaseColumnMap['author']	=> function( $val )
			{
				$member = \IPS\Member::load( $val );
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . ' ' . $member->link();
			},
			$class::$databasePrefix . $class::$databaseColumnMap['date']	=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			}
		) );
				
		/* Return */
		return $table;
	}
	
	/**
	 * Find IPs by Member
	 *
	 * @code
	 	return array(
	 		'::1' => array(
	 			'ip'		=> '::1'// string (IP Address)
		 		'count'		=> ...	// int (number of times this member has used this IP)
		 		'first'		=> ... 	// int (timestamp of first use)
		 		'last'		=> ... 	// int (timestamp of most recent use)
		 	),
		 	...
	 	);
	 * @endcode
	 * @param	\IPS\Member	$member	The member
	 * @return	array|NULL
	 */
	public function findByMember( $member )
	{
		$class = $this->class;
		
		if ( ! \IPS\Application::appIsEnabled( $class::$application ) )
		{
			return NULL;
		}
		
		if( !isset( $class::$databaseColumnMap['ip_address'] ) OR !$class::$databaseColumnMap['ip_address'] )
		{
			return NULL;
		}
		
		return \IPS\Db::i()->select( "{$class::$databasePrefix}{$class::$databaseColumnMap['ip_address']} AS ip, count(*) AS count, MIN({$class::$databasePrefix}{$class::$databaseColumnMap['date']}) AS first, MAX({$class::$databasePrefix}{$class::$databaseColumnMap['date']}) AS last", $class::$databaseTable, array( "{$class::$databasePrefix}{$class::$databaseColumnMap['author']}=?", $member->member_id ), NULL, NULL, "{$class::$databasePrefix}{$class::$databaseColumnMap['ip_address']}" )->setKeyField( 'ip' );
	}	
}