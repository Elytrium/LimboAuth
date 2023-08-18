<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\setup\upg_40000;

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
	 * Act on blogs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Some init */
		$did		= 0;
		$limit		= 0;
		
		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'blog_blogs', null, 'blog_id ASC', array( $limit, 50 ) ) as $blog )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$original	= $blog;
			$blog	= \IPS\blog\Blog::constructFromData( $blog );

			$did++;

			/* Remove external blogs */
			if( $blog->type == 'external' )
			{
				$blog->delete();
			}

			/* Convert settings from serialize to json, ignore anything except allowrss and convert allowrss to bool */
			$settings		= unserialize( $blog->settings );
			$blog->settings	= array( 'allowrss' => (bool) $settings['allowrss'] );

			/* Update counts */
			$blog->count_entries			= (int) \IPS\Db::i()->select( 'COUNT(*)', 'blog_entries', array( "entry_blog_id=? and entry_status=?", $blog->id, 'published' ) )->first();
			$blog->count_entries_hidden		= (int) \IPS\Db::i()->select( 'COUNT(*)', 'blog_entries', array( "entry_blog_id=? and entry_status=?", $blog->id, 'draft' ) )->first();
			$blog->count_comments			= (int) \IPS\Db::i()->select( 'SUM(entry_num_comments)', 'blog_entries', array( "entry_blog_id=?", $blog->id ) )->first();
			$blog->count_comments_hidden	= (int) \IPS\Db::i()->select( 'SUM(entry_queued_comments)', 'blog_entries', array( "entry_blog_id=?", $blog->id ) )->first();

			/* Wipe out member id if this is a group blog */
			if( $blog->groupblog_ids )
			{
				$blog->member_id	= 0;
			}

			/* Set language strings */
			\IPS\Lang::saveCustom( 'blog', "blogs_blog_{$blog->id}", \IPS\Text\Parser::utf8mb4SafeDecode( $original['blog_name'] ) );
			\IPS\Lang::saveCustom( 'blog', "blogs_blog_{$blog->id}_desc", \IPS\Text\Parser::parseStatic( $original['blog_desc'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );
			\IPS\Lang::saveCustom( 'blog', "blogs_groupblog_name_{$blog->id}", \IPS\Text\Parser::utf8mb4SafeDecode( $original['blog_groupblog_name'] ) );

			$blog->save();
		}
		
		if( $did )
		{
			return $limit + $did;
		}
		else
		{
			$columns = array( 'blog_name', 'blog_desc', 'blog_groupblog_name', 'blog_type' );

			foreach( $columns as $id => $column )
			{
				if( !\IPS\Db::i()->checkForColumn( 'blog_blogs', $column ) )
				{
					unset( $columns[$id] );
				}
			}

			if( \count( $columns ) )
			{
				\IPS\Db::i()->dropColumn( 'blog_blogs', $columns );
			}


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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs' )->first();
		}

		return "Upgrading blogs (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}

	/**
	 * Convert ratings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->replace( 'core_ratings', 
			\IPS\Db::i()->select( "NULL, 'IPS\\blog\\Entry', entry_id, member_id, rating, '', NULL", 'blog_ratings' )
		);

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading blog ratings";
	}

	/**
	 * Update blog entry hidden status
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* 1 means approved */
		\IPS\Db::i()->update( 'blog_entries', array( 'entry_hidden' => 1 ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Upgrading blog entries";
	}

	/**
	 * Convert blog categories to tags
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		/* Some init */
		$did		= 0;
		$limit		= 0;
		
		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();
		
		foreach( \IPS\Db::i()->select( '*', 'blog_category_mapping', null, 'map_entry_id ASC', array( $limit, 250 ) ) as $map )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			try
			{
				$entry = \IPS\blog\Entry::load( $map['map_entry_id'] );
				$tags	= $entry->tags();

				$newTag = \IPS\Db::i()->select( 'category_title', 'blog_categories', array( 'category_id=?', $map['map_category_id'] ) )->first();

				$tags[]	= $newTag;
				$entry->setTags( array( 'tags' => $tags, 'prefix' => $entry->prefix() ) );
				$entry->save();
			}
			catch( \Exception $e ){}
		}

		if( $did )
		{
			return $limit + $did;
		}
		else
		{
			unset( $_SESSION['_step4Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step4Count'] ) )
		{
			$_SESSION['_step4Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'blog_category_mapping' )->first();
		}

		return "Converting blog categories to tags (Converted so far: " . ( ( $limit > $_SESSION['_step4Count'] ) ? $_SESSION['_step4Count'] : $limit ) . ' out of ' . $_SESSION['_step4Count'] . ')';
	}

    /**
     * Update serialized blog settings
     *
     * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
     */
    public function step5()
    {
	    try
	    {
	        \IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . "core_groups ADD COLUMN g_blog_allowlocal TINYINT(1) NOT NULL DEFAULT '0',
					ADD COLUMN g_blog_maxblogs INT(10) NOT NULL DEFAULT '0',
					ADD COLUMN g_blog_allowownmod TINYINT(1) NOT NULL DEFAULT '0',
					ADD COLUMN g_blog_allowdelete TINYINT(1) NOT NULL DEFAULT '0',
					ADD COLUMN g_blog_allowcomment TINYINT(1) NOT NULL DEFAULT '0'" );
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( $e->getCode() != 1060 )
			{
				/* 1060: Column exists */
				throw $e;
			}
		}
		
        if( \IPS\Db::i()->checkForColumn( 'core_groups', 'g_blog_settings' ) )
        {
            /* Groups */
            foreach (\IPS\Member\Group::groups() as $group)
            {
                if( $group->g_blog_settings )
                {
                    $settings = unserialize($group->g_blog_settings);
							
                    $group->g_blog_allowlocal   = ( isset( $settings['g_blog_allowlocal'] ) )   ? (int) $settings['g_blog_allowlocal']   : 0;
                    $group->g_blog_maxblogs     = ( isset( $settings['g_blog_maxblogs'] ) )     ? (int) $settings['g_blog_maxblogs']     : 0;
                    $group->g_blog_allowownmod  = ( isset( $settings['g_blog_allowownmod'] ) )  ? (int) $settings['g_blog_allowownmod']  : 0;
                    $group->g_blog_allowdelete  = ( isset( $settings['g_blog_allowdelete'] ) )  ? (int) $settings['g_blog_allowdelete']  : 0;
                    $group->g_blog_allowcomment = ( isset( $settings['g_blog_allowcomment'] ) ) ? (int) $settings['g_blog_allowcomment'] : 0;

                    $group->save();
                }
            }

            \IPS\Db::i()->dropColumn( 'core_groups', 'g_blog_settings' );
        }

        return TRUE;
    }

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Converting blog group settings";
	}
	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
    {
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\blog\Entry' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\blog\Entry\Comment' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'blog_Blogs' ), 2 );

        return TRUE;
    }
}