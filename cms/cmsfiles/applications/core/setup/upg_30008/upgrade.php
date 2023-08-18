<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30008;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrade steps
 */
class _Upgrade
{
	/**
	 * Step 1
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'pfields_data' ) as $row )
		{
			/* Attempt conversion of dd / dt microformats */
			if ( stristr( $row['pf_topic_format'], '<dt>' ) OR stristr( $row['pf_topic_format'], '<dd>' ) )
			{
				$row['pf_topic_format'] = str_replace( '<dt>', "<span class='ft'>", $row['pf_topic_format'] );
				$row['pf_topic_format'] = str_replace( '<dd>', "<span class='fc'>", $row['pf_topic_format'] );
				$row['pf_topic_format'] = str_replace( array( '</dt>', '</dd>' ), "</span>", $row['pf_topic_format'] );
				
				\IPS\Db::i()->update( 'pfields_data', array( 'pf_topic_format' => $row['pf_topic_format'] ), 'pf_id=' . $row['pf_id'] );
			}
		}

		/* Finish */
		return TRUE;
	}
}