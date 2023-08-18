<?php
/**
 * @brief		Type registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		3 Dec 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Type registry
 */
class _TypeRegistry
{
	protected static $query;
	protected static $mutation;
	protected static $itemState;
	protected static $image;
	protected static $reputation;
	protected static $richText;
	protected static $url;
	protected static $follow;
	protected static $moduleAccess;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}

	/**
	* @return \IPS\Api\GraphQL\Types\QueryType
	*/
	public static function query(): \IPS\Api\GraphQL\Types\QueryType
	{
		return self::$query ?: (self::$query = new \IPS\Api\GraphQL\Types\QueryType());
	}

	/**
	* @return \IPS\Api\GraphQL\Types\MutationType
	*/
	public static function mutation(): \IPS\Api\GraphQL\Types\MutationType
	{
		return self::$mutation ?: (self::$mutation = new \IPS\Api\GraphQL\Types\MutationType());
	}

	/**
	 * @return \IPS\Api\GraphQL\Types\ItemStateType
	 */
	public static function itemState(): \IPS\Api\GraphQL\Types\ItemStateType
	{
		return self::$itemState ?: (self::$itemState = new \IPS\Api\GraphQL\Types\ItemStateType());
	}
	
	/**
	 * @return ImageType
	 */
	public static function image(): \IPS\Api\GraphQL\Types\ImageType
	{
		return self::$image ?: (self::$image = new \IPS\Api\GraphQL\Types\ImageType());
	}
	
	/**
	 * @return \IPS\Api\GraphQL\Types\ReputationType
	 */
	public static function reputation(): \IPS\Api\GraphQL\Types\ReputationType
	{
		return self::$reputation ?: (self::$reputation = new \IPS\Api\GraphQL\Types\ReputationType());
	}
	
	/**
	 * @return \IPS\Api\GraphQL\Types\RichTextType
	 */
	public static function richText(): \IPS\Api\GraphQL\Types\RichTextType
	{
		return self::$richText ?: (self::$richText = new \IPS\Api\GraphQL\Types\RichTextType());
	}

	/**
	 * @return \IPS\Api\GraphQL\Types\UrlType
	 */
	public static function url(): \IPS\Api\GraphQL\Types\UrlType
	{
		return self::$url ?: (self::$url = new \IPS\Api\GraphQL\Types\UrlType());
	}

	/**
	 * @return \IPS\Api\GraphQL\Types\FollowType
	 */
	public static function follow(): \IPS\Api\GraphQL\Types\FollowType
	{
		return self::$follow ?: (self::$follow = new \IPS\Api\GraphQL\Types\FollowType());
	}

	/**
	 * @return \IPS\Api\GraphQL\Types\ModuleAccessType
	 */
	public static function moduleAccess(): \IPS\Api\GraphQL\Types\ModuleAccessType
	{
		return self::$moduleAccess ?: (self::$moduleAccess = new \IPS\Api\GraphQL\Types\ModuleAccessType());
	}

	/**
	* @return \GraphQL\Type\Definition\IDType
	*/
	public static function id(): \GraphQL\Type\Definition\IDType
	{
		return Type::id();
	}

	/**
	* @return \GraphQL\Type\Definition\StringType
	*/
	public static function string(): \GraphQL\Type\Definition\StringType
	{
		return Type::string();
	}

	/**
	* @return \GraphQL\Type\Definition\IntType
	*/
	public static function int(): \GraphQL\Type\Definition\IntType
	{
		return Type::int();
	}

	/**
	* @return \GraphQL\Type\Definition\FloatType
	*/
	public static function float(): \GraphQL\Type\Definition\FloatType
	{
		return Type::float();
	}

	/**
	* @return \GraphQL\Type\Definition\BooleanType
	*/
	public static function boolean(): \GraphQL\Type\Definition\BooleanType
	{
		return Type::boolean();
	}

	/**
	* @return \GraphQL\Type\Definition\ListOfType
	*/
	public static function listOf($type): \GraphQL\Type\Definition\ListOfType
	{
		return new ListOfType($type);
	}

	/**
	* @return \GraphQL\Type\Definition\EnumType
	*/
	public static function eNum($config): \GraphQL\Type\Definition\EnumType
	{
		return new EnumType($config);
	}

	public static function inputObjectType($config): \GraphQL\Type\Definition\InputObjectType
	{
		return new InputObjectType($config);
	}

	/**
	* @param Type $type
	* @return NonNull
	*/
	public static function nonNull($type): \GraphQL\Type\Definition\NonNull
	{
		return new NonNull($type);
	}
}
