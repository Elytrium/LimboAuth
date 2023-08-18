<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Mar 2013
 */

namespace IPS\core\setup\upg_100005;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Fix stuff
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if( !\IPS\Db::i()->checkForColumn( 'core_widgets', 'embeddable' ) )
		{
			\IPS\Db::i()->addColumn( 'core_widgets', array( 
				'name'			=> 'embeddable',
				'type'			=> 'TINYINT',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> '0',
				'comment'		=> "Determines if Pages can embed this widget in a custom block.",
				'unsigned'		=> true,
			)	);
		}

        if( !\IPS\Db::i()->checkForColumn( 'core_members', 'timezone' ) )
        {
            \IPS\Db::i()->addColumn( 'core_members', array(
                'name'			=> 'timezone',
                'type'			=> 'VARCHAR',
                'length'		=> 64,
                'allow_null'	=> true,
                'default'		=> NULL,
            )	);
        }

        if( \IPS\Db::i()->checkForColumn( 'core_members', 'pp_profile_update' ) )
        {
        	\IPS\Db::i()->dropColumn( 'core_members', 'pp_profile_update' );
        }
        
         if( !\IPS\Db::i()->checkForColumn( 'core_applications', 'app_hide_tab' ) )
        {
            \IPS\Db::i()->addColumn( 'core_applications', array(
                'name'			=> 'app_hide_tab',
                'type'			=> 'TINYINT',
                'length'		=> 1,
                'allow_null'	=> FALSE,
                'default'		=> 0,
            )	);
        }

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Checking for missing columns";
	}
}