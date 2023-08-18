<?php
/**
 * @brief		Type registry for \IPS\Node types
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		29 Aug 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Node\Api\GraphQL;
use GraphQL\Type\Definition\Type;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * \IPS\Node base types
 */
class _TypeRegistry
{
	protected static $node;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}
	
	/**
	 * @return CommentType
	 */
	public static function node()
	{
		return self::$node ?: (self::$node = new \IPS\Node\Api\GraphQL\NodeType());
	}
}