<?php
/**
 * @brief		4.0.0 Beta 6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		06 Jan 2015
 */

namespace IPS\cms\setup\upg_100010;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Beta 5 Upgrade Code
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert menu titles to translatable lang strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$menu	= array();

		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $db )
		{
			foreach( \IPS\Lang::languages() as $id => $lang )
			{
				try
				{
					$base = $lang->get('cms_create_menu_records_x');
				}
				catch ( \UnderflowException $e )
				{
					$base = "%s in %s"; // If upgrading from 3.x it won't exist yet
				}
				
				$menu[ $lang->_id ]		= \sprintf( $base, $lang->get("content_db_lang_su_" . $db['database_id'] ), $lang->get('content_db_' . $db['database_id'] ) );
			}

			\IPS\Lang::saveCustom( 'cms', "cms_create_menu_records_" . $db['database_id'], $menu );
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
		return "Storing create menu translations";
	}

	/**
	 * Step 2
	 * When upgrading from 3.x a database may be configured so that primary_id_field (or similar) is the content or title field..
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $db )
		{
			$updates	= array();
			
			if( !\is_numeric( $db['database_field_title'] ) or !\is_numeric( $db['database_field_content'] ) )
			{
				$definition	= \IPS\Db::i()->getTableDefinition( 'cms_custom_database_' . $db['database_id'] );
				$fields		= array();

				/* Figure out which field_xx columns are available */
				foreach( $definition['columns'] as $k => $v )
				{
					if( mb_strpos( $v['name'], 'field_' ) === 0 )
					{
						$fields[]	= str_replace( 'field_', '', $v['name'] );
					}
				}

				/* Do we need to reset the title field, i.e. if it was mapped to primary_id_field? */
				if( !\is_numeric( $db['database_field_title'] ) AND \count( $fields ) )
				{
					$updates['database_field_title']	= $fields[0];

					/* If there are other fields to use, remove this one from the array */
					if( \count( $fields ) > 1 )
					{
						unset( $fields[0] );
					}
				}
				/* Otherwise we need to remove the title field from the fields array */
				else
				{
					if( $titleField = array_search( $db['database_field_title'], $fields ) )
					{
						unset( $fields[ $titleField ] );
					}
				}

				/* Do we need to reset the content field, i.e. if it was mapped to primary_id_field? */
				if( !\is_numeric( $db['database_field_content'] ) AND \count( $fields ) )
				{
					$updates['database_field_content']	= array_shift( $fields );
				}
			}

			/* If we have any changes, store them */
			if( \count( $updates ) )
			{
				\IPS\Db::i()->update( 'cms_databases', $updates, array( 'database_id=?', $db['database_id'] ) );
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
		return "Fixing misconfigured databases";
	}

	/**
	 * Step 3
	 * Convert page titles to translatable lang strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		foreach ( \IPS\Db::i()->select( '*', 'cms_pages' ) as $page )
		{
			\IPS\Lang::saveCustom( 'cms', "cms_page_" . $page['page_id'], $page['page_name'] );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Converting page titles to translatable fields";
	}
}