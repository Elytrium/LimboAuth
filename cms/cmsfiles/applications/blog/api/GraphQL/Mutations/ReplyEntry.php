<?php
/**
 * @brief		GraphQL: Reply to topic mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Reply to entry mutation for GraphQL API
 */
class _ReplyEntry extends \IPS\Content\Api\GraphQL\CommentMutator
{
    /**
     * Class
     */
    protected $class = \IPS\blog\Entry\Comment::class;

    /*
     * @brief 	Query description
     */
    public static $description = "Create a new comment";

    /*
     * Mutation arguments
     */
    public function args(): array
    {
        return [
            'entryID'		=> TypeRegistry::nonNull( TypeRegistry::id() ),
            'content'		=> TypeRegistry::nonNull( TypeRegistry::string() ),
            'replyingTo'	=> TypeRegistry::id(),
            'postKey'		=> TypeRegistry::string()
        ];
    }

    /**
     * Return the mutation return type
     */
    public function type()
    {
        return \IPS\blog\api\GraphQL\TypeRegistry::comment();
    }

    /**
     * Resolves this mutation
     *
     * @param 	mixed 	Value passed into this resolver
     * @param 	array 	Arguments
     * @param 	array 	Context values
     * @return	\IPS\blog\Entry
     */
    public function resolve($val, $args, $context, $info)
    {
        /* Get topic */
        try
        {
            $entry = \IPS\blog\Entry::loadAndCheckPerms( $args['entryID'] );
        }
        catch ( \OutOfRangeException $e )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_TOPIC', '1F295/1_graphql', 403 );
        }

        /* Get author */
        if ( !$entry->canComment( \IPS\Member::loggedIn() ) )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', '2F294/A_graphql', 403 );
        }

        /* Check we have a post */
        if ( !$args['content'] )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1F295/3_graphql', 403 );
        }

        $originalPost = NULL;

        if( isset( $args['replyingTo'] ) )
        {
            try
            {
                $originalPost = \IPS\blog\Entry\Comment::loadAndCheckPerms( $args['replyingTo'] );
            }
            catch ( \OutOfRangeException $e )
            {
                // Just ignore it
            }
        }

        /* Do it */
        return $this->_createComment( $args, $entry, $args['postKey'] ?? NULL, $originalPost );
    }
}
