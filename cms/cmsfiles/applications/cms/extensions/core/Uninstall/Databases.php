<?php
/**
 * @brief		File Storage Extension: Records
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage  Content
 * @since		11 April 2014
 */

namespace IPS\cms\extensions\core\Uninstall;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Remove custom databases
 */
class _Databases
{
    /**
     * Constructor
     *
     *
     */
    public function __construct()
    {
    }

    /**
     * Uninstall custom databases
     *
     * @return void
     */
    public function preUninstall( )
    {
        if ( \IPS\Db::i()->checkForTable( 'cms_databases' ) )
        {
            foreach ( \IPS\Db::i()->select( '*', 'cms_databases' ) as $db )
            {
                /* The content router only returns databases linked to pages. In theory, you may have linked a database and then removed it,
                    so the method to remove all app content from the search index fails, so we need to account for that here: */
                \IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\cms\Records' . $db['database_id'] );
            }
        }
    }

    /**
     * Uninstall custom databases
     *
     * @return void
     */
    public function postUninstall()
    {
        /* cms_databases has been removed */
        $tables = array();
        try
        {
            $databaseTables = \IPS\Db::i()->query( "SHOW TABLES LIKE '" . \IPS\Db::i()->prefix . "cms_custom_database_%'" )->fetch_assoc();
            if ( $databaseTables )
            {
                foreach( $databaseTables as $row )
                {
                    if( \is_array( $row ) )
                    {
                        $tables[] = array_pop( $row );
                    }
                    else
                    {
                        $tables[] = $row;
                    }
                }
            }

        }
        catch( \IPS\Db\Exception $ex ) { }

        foreach( $tables as $table )
        {
            if ( \IPS\Db::i()->checkForTable( $table ) )
            {
                \IPS\Db::i()->dropTable( $table );
            }
        }

        if ( isset( \IPS\Data\Store::i()->cms_menu ) )
        {
            unset( \IPS\Data\Store::i()->cms_menu );
        }
    }
}