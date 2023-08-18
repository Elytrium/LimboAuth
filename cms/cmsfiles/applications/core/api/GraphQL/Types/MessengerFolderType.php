<?php
/**
 * @brief		GraphQL: Messenger folder Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		27 Sep 2019
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
 * MessengerFolder for GraphQL API
 */
class _MessengerFolderType extends ObjectType
{

    public $memberFolders = NULL;

    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_MessengerFolder',
			'description' => 'Messenger folder',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Folder ID",
						'resolve' => function ($folder) {
							return $folder['id'];
						}
                    ],
                    'name' => [
                        'type' => TypeRegistry::string(),
                        'description' => "The name of this folder (as provided by the user)",
                        'resolve' => function ($folder) {
                            return $folder['name'];
                        }
                    ],
                    'count' => [
                        'type' => TypeRegistry::int(),
                        'description' => "Number of conversations in this folder",
                        'resolve' => function ($folder) {
                            return $folder['count'];
                        }
                    ],
                    'url' => [
                        'type' => TypeRegistry::url(),
                        'description' => "URL to this folder",
                        'resolve' => function ($folder) {
                            return $folder['url'];
                        }
                    ],
				];
			}
		];

        parent::__construct($config);
    }
    
    /**
	 * Return an array of messenger folder data for the logged-in member
	 *
     * @Param   boolean     $refetch    If true, will force folder data to be refetched rather than served from cached values
	 * @return	array
	 */
    public function getMemberFolders($refetch = FALSE)
    {
        if( $this->memberFolders !== NULL && !$refetch )
        {
            return $this->memberFolders;
        }
		
		/* Get folders */
		$folders = array( 'myconvo'	=> \IPS\Member::loggedIn()->language()->addToStack('messenger_folder_inbox') );
		if ( \IPS\Member::loggedIn()->pconversation_filters )
		{
			$folders = $folders + array_filter( json_decode( \IPS\Member::loggedIn()->pconversation_filters, TRUE ) );
		}
		
		/* What are our folder counts? */
		/* Note: The setKeyField and setValueField calls here were causing the folder counts to be incorrect (if you had two folders with 1 message in each, then both showed a count of 2) */
		$counts = iterator_to_array( \IPS\Db::i()->select( 'map_folder_id, count(*) as count', 'core_message_topic_user_map', array( 'map_user_id=? AND map_user_active=1', \IPS\Member::loggedIn()->member_id ), NULL, NULL, 'map_folder_id' ) );
		$folderCounts = array();
		foreach( $counts AS $k => $count )
		{
			$folderCounts[$count['map_folder_id']] = $count['count'];
        }
        
        foreach( $folders as $id => $name)
        {
            $this->memberFolders[ $id ] = array(
                'id' => $id,
                'name' => $name,
                'url' => \IPS\Http\Url::internal( "app=core&module=messaging&controller=messenger", 'front', 'messaging' )->setQueryString('folder', $id),
                'count' => isset( $folderCounts[ $id ] ) ? $folderCounts[ $id ] : 0
            );
        }

        return $this->memberFolders;
    }
}
