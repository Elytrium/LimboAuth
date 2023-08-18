<?php
/**
 * @brief		4.3.4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		05 Jun 2018
 */

namespace IPS\core\setup\upg_103021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.4 Upgrade Code
 */
class _Upgrade
{
	
	/**
	 * Only truncate search index if ! CiC
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Discussed with Charles, and CIC accounts can run these queries fast and it takes more resources to rebuild the index afterwards en-masse */
		if ( ! \IPS\CIC )
		{
			\IPS\Db::i()->delete( 'core_search_index' );
		}
		
		$json = <<<EOF
[
	{
        "method": "changeIndex",
        "params": [
            "core_search_index",
            "index_date_updated",
            {
                "type": "key",
                "name": "index_date_updated",
                "columns": [
                    "index_date_updated",
                    "index_date_commented"
                ],
                "length": [
                    null,
                    null
                ]
            }
        ]
    },
    {
        "method": "changeIndex",
        "params": [
            "core_search_index",
            "index_date_created",
            {
                "type": "key",
                "name": "index_date_created",
                "columns": [
                    "index_date_created",
                    "index_date_commented"
                ],
                "length": [
                    null,
                    null
                ]
            }
        ]
    },
    {
        "method": "changeIndex",
        "params": [
            "core_search_index",
            "author_lookup",
            {
                "type": "key",
                "name": "author_lookup",
                "columns": [
                    "index_author",
                    "index_class",
                    "index_hidden",
                    "index_date_updated",
                    "index_date_commented"
                ],
                "length": [
                    null,
                    250,
                    null,
                    null,
                    null
                ]
            }
        ]
    },
    {
        "method": "addIndex",
        "params": [
            "core_search_index",
            {
                "type": "key",
                "name": "index_item_author",
                "columns": [
                    "index_item_author",
                    "index_date_commented"
                ],
                "length": [
                    null,
                    null
                ]
            }
        ]
    },
    {
        "method": "changeIndex",
        "params": [
            "core_search_index",
            "index_date_commented",
            {
                "type": "key",
                "name": "index_date_commented",
                "columns": [
                    "index_date_commented",
                    "index_date_updated"
                ],
                "length": [
                    null,
                    null
                ]
            }
        ]
    }
]
EOF;
		$queries = json_decode( $json, TRUE );
		
		foreach( $queries as $query )
		{
			try
			{
				$run = \call_user_func_array( array( \IPS\Db::i(), $query['method'] ), $query['params'] );
			}
			catch( \IPS\Db\Exception $e )
			{
				if( !in_array( $e->getCode(), array( 1007, 1008, 1050, 1060, 1061, 1062, 1091, 1051 ) ) )
				{
					throw $e;
				}
			}
		}

		return TRUE;
	}

    /**
     * Custom title for this step
     *
     * @return string
     */
    public function step1CustomTitle()
    {
        return "Optimizing search index";
    }
		
 	/**
	 * Finish step
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		if( ! \IPS\CIC AND isset( \IPS\Settings::i()->search_method ) AND \IPS\Settings::i()->search_method == 'mysql' )
		{
			\IPS\Content\Search\Index::i()->rebuild();
		}

		return TRUE;
	}
}