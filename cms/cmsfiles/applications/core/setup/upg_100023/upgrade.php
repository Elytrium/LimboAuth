<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Apr 2015
 */

namespace IPS\core\setup\upg_100023;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
    /**
     * Step 1
     * Fix emoticon codes
     *
     * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
     */
    public function step1()
    {
        foreach( \IPS\Db::i()->select( '*', 'core_emoticons' ) as $emoticon )
        {
            \IPS\Db::i()->update( 'core_emoticons', array( 'typed' => \IPS\Text\Parser::utf8mb4SafeDecode( $emoticon['typed'] ) ), array( 'id=?', $emoticon['id'] ) );
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
        return "Fixing emoticons";
    }
}