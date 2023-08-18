<?php
/**
 * @brief		4.0.0 RC7 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Mar 2015
 */

namespace IPS\core\setup\upg_100021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC7 Upgrade Code
 */
class _Upgrade
{
    /**
     * Step 1
     * Conversation Notifications
     *
     * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
     */
    public function step1()
    {
        $perCycle	= 1000;
        $did		= 0;
        $limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff	= \IPS\core\Setup\Upgrade::determineCutoff();
		
        if( \IPS\Db::i()->select( 'COUNT(*)', 'core_notifications' )->first() )
        {
            foreach( \IPS\Db::i()->select( '*', 'core_notifications', array( 'notification_key = ?', 'new_private_message' ), 'id ASC', array( $limit, $perCycle ) ) as $row )
            {
    			if( $cutOff !== null AND time() >= $cutOff )
    			{
    				return ( $limit + $did );
    			}

                $did++;

                try
                {
                    $message = \IPS\core\Messenger\Message::load( $row['item_sub_id'] );
                    $conversation = $message->item();

    				\IPS\Db::i()->update( 'core_notifications', array( 'item_class' => 'IPS\core\Messenger\Conversation', 'item_id' => $conversation->id ), array( 'id=?', $row['id'] ) );
                }
                catch( \Exception $e ) {}
            }
        }

        if( $did )
        {
            return ( $limit + $did );
        }
        else
        {
        	unset( $_SESSION['_step1Count'] );
            return TRUE;
        }
    }

    /**
     * Custom title for this step
     *
     * @return string
     */
    public function step1CustomTitle()
    {
        $limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_notifications', array( 'notification_key = ?', 'new_private_message' ) )->first();
		}

        return "Rebuilding message notifications (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
    }
}