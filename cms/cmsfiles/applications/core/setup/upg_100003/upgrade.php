<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Mar 2013
 */

namespace IPS\core\setup\upg_100003;

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
	 * Fix languages
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->delete( 'core_sys_lang_words', "word_app IN('members','ccs','ipseo','ipchat')" );

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Cleaning up languages";
	}

	/**
	 * Step 2
	 * Convert old friends to followers
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* If we aren't upgrading from 3.x the table won't be present */
		if( !\IPS\Db::i()->checkForTable('profile_friends') )
		{
			return true;
		}

		/* Did we skip? */
		if ( $_SESSION['upgrade_options']['core']['100003']['follow_options'] == 'no_convert' )
		{
			\IPS\Db::i()->dropTable( 'profile_friends' );
			return true;
		}

		$perCycle	= 250;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'profile_friends', null, 'friends_id ASC', array( $limit, $perCycle ) ) as $friend )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* Make sure the users aren't orphaned */
			$requester = \IPS\Member::load( $friend['friends_friend_id'] );
			$requestee = \IPS\Member::load( $friend['friends_member_id'] );

			if( !$requestee->member_id OR !$requester->member_id )
			{
				continue;
			}

			/* Follower */
			$follower	= array(
				'follow_id'				=> md5( 'core;member;' . $friend['friends_friend_id'] . ';' . $friend['friends_member_id'] ),
				'follow_app'			=> 'core',
				'follow_area'			=> 'member',
				'follow_rel_id'			=> $friend['friends_friend_id'],
				'follow_member_id'		=> $friend['friends_member_id'],
				'follow_is_anon'		=> 0,
				'follow_added'			=> $friend['friends_added'],
				'follow_notify_sent'	=> time(),
				'follow_notify_do'		=> 1,
				'follow_notify_meta'	=> '',
				'follow_notify_freq'	=> 'immediate',
				'follow_visible'		=> 1
			);

			\IPS\Db::i()->replace( 'core_follow', $follower );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step3Count'] );
			\IPS\Db::i()->dropTable( 'profile_friends' );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		if ( $_SESSION['upgrade_options']['core']['100003']['follow_options'] == 'no_convert' )
		{
			return "Skipping friends to followers conversion";
		}
		
		if( \IPS\Db::i()->checkForTable('profile_friends') )
		{
			$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

			if( !isset( $_SESSION['_step3Count'] ) )
			{
				$_SESSION['_step3Count']	= \IPS\Db::i()->select( 'COUNT(*)', 'profile_friends' )->first();
			}

			return "Converting friends to followers (Converted so far: " . ( ( $limit > $_SESSION['_step3Count'] ) ? $_SESSION['_step3Count'] : $limit ) . ' out of ' . $_SESSION['_step3Count'] . ')';
		}
		else
		{
			return "No friends to convert";
		}
	}
	
	/**
	 * Step 3
	 * Fix advertisements
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first() )
		{
			\IPS\Settings::i()->changeValues( array( 'ads_exist' => 1 ) );
		}

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Setting advertisement statuses";
	}

	/**
	 * Step 4
	 * Clean up some misc stuff
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		if( !\IPS\Db::i()->checkForColumn( 'core_member_ranks', 'icon' ) )
		{
			\IPS\Db::i()->addColumn( 'core_member_ranks', array(
				"name"			=> "icon",
				"type"			=> "TEXT",
				"length"		=> 0,
				'allow_null'	=> false,
				"null"			=> false,
				"default"		=> '',
			)	);
		}
		if( !\IPS\Db::i()->checkForColumn( 'core_member_ranks', 'use_icon' ) )
		{
			\IPS\Db::i()->addColumn( 'core_member_ranks', array(
				"name"			=> "use_icon",
				"type"			=> "TINYINT",
				"length"		=> 1,
				'allow_null'	=> false,
				"null"			=> false,
				"default"		=> 0,
			)	);
		}
		
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			\IPS\Db::i()->update( 'core_member_ranks', "icon=pips, use_icon=1", "pips REGEXP('[A-Za-z]')" );
			\IPS\Db::i()->update( 'core_member_ranks', array( 'pips' => null ), "use_icon=1" );
		}
		
		$memberCleanup	= array();

		if( \IPS\Db::i()->checkForColumn( 'core_members', 'fb_lastsync' ) )
		{
			$memberCleanup[]	= "DROP COLUMN fb_lastsync";
		}

		if( \IPS\Db::i()->checkForColumn( 'core_members', 'title' ) )
		{
			$memberCleanup[]	= "CHANGE COLUMN title member_title VARCHAR(64) null DEFAULT null";
		}

		if( \IPS\Db::i()->checkForColumn( 'core_members', 'posts' ) )
		{
			$memberCleanup[]	= "CHANGE COLUMN posts member_posts MEDIUMINT(7) not null DEFAULT 0";
		}

		if( \IPS\Db::i()->checkForColumn( 'core_members', 'last_post' ) )
		{
			$memberCleanup[]	= "CHANGE COLUMN last_post member_last_post INT(10) null DEFAULT null";
		}

		if( \count( $memberCleanup ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
				'table' => 'core_members',
				'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "core_members " . implode( ', ', $memberCleanup )
			) ) );
			
			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 5 ) ) );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
		}

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Cleaning up member and member rank tables";
	}

    /* ! Conversation Participants */
    /**
     * Step 5
     * Conversation Participants
     *
     * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
     */
    public function step5()
    {
        $perCycle	= 1000;
        $did		= 0;
        $limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();
		
        foreach( \IPS\Db::i()->select( '*', 'core_message_topics', null, 'mt_id ASC', array( $limit, $perCycle ) ) as $row )
        {
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

            $did++;

            try
            {
                $conversation = \IPS\core\Messenger\Conversation::constructFromData( $row );
                $conversation->rebuildParticipants();
            }
            catch( \Exception $e ) {}
        }

        if( $did )
        {
            return ( $limit + $did );
        }
        else
        {
        	unset( $_SESSION['_step6Count'] );
            return TRUE;
        }
    }

    /**
     * Custom title for this step
     *
     * @return string
     */
    public function step5CustomTitle()
    {
        $limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		if( !isset( $_SESSION['_step6Count'] ) )
		{
			$_SESSION['_step6Count']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_message_topics' )->first();
		}

        return "Rebuilding conversation participant data (Updated so far: " . ( ( $limit > $_SESSION['_step6Count'] ) ? $_SESSION['_step6Count'] : $limit ) . ' out of ' . $_SESSION['_step6Count'] . ')';
    }
}