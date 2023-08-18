<?php
/**
 * @brief		GraphQL: Delete attachment mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 May 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Delete attachment mutation for GraphQL API
 */
class _DeleteAttachment
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Delete an attachment";
	
	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id'				=> TypeRegistry::nonNull( TypeRegistry::id() ),
			'editorLocation'	=> TypeRegistry::string(),
			'locationId1'		=> TypeRegistry::int(),
			'locationId2'		=> TypeRegistry::int(),
			'locationId3'		=> TypeRegistry::string(),
		];
	}
	
	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return TypeRegistry::boolean();
	}
	
	/**
	 * Resolves this mutation
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	bool
	 */
	public function resolve($val, $args, $context, $info)
	{
		/* Get the attachment */
		try
		{				
			$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', $args['id'] ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_ATTACHMENT', 'GQL/0004/1', 404 );
		}
				
		/* Delete the maps - Only do this for attachments that have actually been saved (if they haven't been saved, there's nothing in core_attachments_map for us to delete */
		if ( isset( $args['editorLocation'] ) and ( isset( $args['locationId1'] ) or isset( $args['locationId2'] ) or isset( $args['locationId3'] ) ) )
		{
			$where = array( array( 'location_key=?', $args['editorLocation'] ), array( 'attachment_id=?', $attachment['attach_id'] ) );
			
			foreach ( range( 1, 3 ) as $i )
			{
				if ( isset( $args["locationId{$i}"] ) )
				{
					$where[] = array( "id{$i}=?", $args["locationId{$i}"] );
				}
			}

			\IPS\Db::i()->delete( 'core_attachments_map', $where );
		}
		else
		{
			/* If the attachment hasn't been claimed yet, it should only be deletable by the person who uploaded it */
			if( $attachment['attach_member_id'] != \IPS\Member::loggedIn()->member_id )
			{
				throw new \IPS\Api\GraphQL\SafeException( 'CANNOT_DELETE_OTHERS_ATTACHMENTS', 'GQL/0004/2', 403 );
			}
		}
		
		/* If there's no other maps, we can delete the attachment itself */
		$otherMaps = \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( 'attachment_id=?', $attachment['attach_id'] ) )->first();
		if ( !$otherMaps )
		{
			\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', $attachment['attach_id'] ) );
			try
			{
				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
			}
			catch ( \Exception $e ) { }
			if ( $attachment['attach_thumb_location'] )
			{
				try
				{
					\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
				}
				catch ( \Exception $e ) { }
			}
		}
		
		/* Return that we're done */
		return true;
	}
}