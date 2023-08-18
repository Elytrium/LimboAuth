<?php
/**
 * @brief		4.0.9 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Jun 2015
 */

namespace IPS\core\setup\upg_100035;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.9 Upgrade Code
 */
class _Upgrade
{
	/**
	 * In 4.x, attach_post_key is only filled in when you upload an attachment but haven't submitted the post yet. Once the attachment is "saved" on a post, attach_post_key is blank.
	 * The system uses this to know which attachments can be deleted - when something is deleted, it will remove the rows from core_attachments_map and then delete all attachments which have no rows there *and* have no attach_post_key.
	 * In 3.x, attach_post_key was always filled in, even when an attachment had been saved. This meant that if you upgraded from 3.x, then deleted a post with an attachment on it, that attachment would never be deleted.
	 * This query wipes the attach_post_key for all attachments. It is done here so that the fix is retroactive. Thoeretically, anyome who started making an attachment before the upgrade here started may lose it, but this would be a rare occurance, and since
	 * we're performing an upgrade anyway, that's okay - it's better that old attachments are able to be deleted correctly.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->update( 'core_attachments', array( 'attach_post_key' => '' ) );
		
		/* Make sure the max_video_width_css is populated correctly */
		if ( \IPS\Settings::i()->max_video_width )
		{
			try
			{
				\IPS\Db::i()->select( 'conf_value', 'core_sys_conf_settings', array( 'conf_key=?', 'max_video_width_css' ) )->first();
			}
			catch( \UnderflowException $ex )
			{
				$insert = array(
					'conf_key'      => 'max_video_width_css',
					'conf_value'    => '',
					'conf_default'  => 'none', 
					'conf_keywords' => '',
					'conf_app'      => 'core'
				);
							
				/* This key was added in 4.0.9 so it will not exist */
				\IPS\Db::i()->insert( 'core_sys_conf_settings', $insert );
			}
		
			\IPS\Settings::i()->changeValues( array( 'max_video_width_css' => \IPS\Settings::i()->max_video_width . 'px' ) );
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
		return "Adjusting old attachments";
	}

    /**
     * A 3.x legacy bug can cause some members to have an invalid group set. Previous versions would silently ignore this but 4.x is not as forgiving.
     *
     * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
     */
    public function step2()
    {
        /* Make sure groups are valid */
        $groups = \IPS\Db::i()->select( 'member_group_id', 'core_members', NULL, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_DISTINCT );

        $invalidGroups = array();
        foreach( $groups as $group )
        {
            try
            {
                \IPS\Member\Group::load( $group );
            }
            catch ( \OutOfRangeException $e )
            {
                $invalidGroups[] = $group;
            }
        }

        if( !empty( $invalidGroups ) )
        {
            $toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
                'table' => 'core_members',
                'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_members SET member_group_id=" . \IPS\Settings::i()->member_group . " WHERE member_group_id IN( " . implode( ',', $invalidGroups ) . " );"
            ) ) );

            if ( \count( $toRun ) )
            {
                \IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 3 ) ) );

                /* Queries to run manually */
                return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
            }
        }

        return TRUE;
    }

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Correcting invalid group mappings";
	}
}