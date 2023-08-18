<?php
/**
 * @brief		4.3.0 Beta 4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		06 Apr 2018
 */

namespace IPS\cms\setup\upg_103004;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 4 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Record Views Column Type
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
        foreach( \IPS\cms\Databases::databases() as $database )
        {
            \IPS\Db::i()->changeColumn( 'cms_custom_database_' . $database->id, 'record_views', array(
                'name'				=> 'record_views',
                'type'				=> 'int',
                'length'			=> 10,
                'allow_null'		=> false,
                'default'			=> '0'
            ) );

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
        return "Fixing custom database table definitions";
    }
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}