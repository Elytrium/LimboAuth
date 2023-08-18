<?php
/**
 * @brief		GraphQL: Messenger reply Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		25 Sep 2019
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
 * MessengerReply for GraphQL API
 */
class _MessengerReplyType extends \IPS\Content\Api\GraphQL\CommentType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $commentClass	= '\IPS\core\Messenger\Message';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'core_MessengerReply';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'A reply';

	/**
	 * Get the item type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	public static function getItemType()
	{
		return \IPS\core\api\GraphQL\TypeRegistry::messengerConversation();
	}

	/**
	 * Return the fields available in this type
	 *
	 * @return	array
	 */
	/*public function fields()
	{
		
	}*/

	/**
	 * Return the definite article, but without the item type
	 *
	 * @return	string
	 */
	/*public static function definiteArticleNoItem($post, $options = array())
	{
		$type = $post->item()->isQuestion() ? 'answer_lc' : 'post_lc';
		return \IPS\Member::loggedIn()->language()->addToStack($type, FALSE, $options);
	}*/
}
