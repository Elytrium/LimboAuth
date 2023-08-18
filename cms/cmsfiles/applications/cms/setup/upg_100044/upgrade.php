<?php
/**
 * @brief		4.0.13 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		30 Jul 2015
 */

namespace IPS\cms\setup\upg_100044;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.13 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Attachments field may have 0 mapped instead of null or empty string
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_database_fields', array( 'field_type=?', 'Upload' ) ) as $field )		
		{
			\IPS\Db::i()->update( 'cms_custom_database_' . $field['field_database_id'], array( 'field_' . $field['field_id'] => NULL ), array( 'field_' . $field['field_id'] . '=?', '0' ) );
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
		return "Adjusting empty attachment fields";
	}

	/**
	 * Databases did not previously remove follows
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$areas = array();

		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $db )		
		{
			$areas[] = "records" . $db['database_id'];
			$areas[] = "categories" . $db['database_id'];
		}

		\IPS\Db::i()->delete( 'core_follow', array( "follow_app=? AND follow_area NOT IN('" . implode( "','", $areas ) . "')", 'cms' ) );

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing invalid follow notifications";
	}
}