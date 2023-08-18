<?php
/**
 * @brief		4.2.0 Beta 3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		18 May 2017
 */

namespace IPS\cms\setup\upg_101105;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.0 Beta 3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix customized templates to ensure reactions show
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Update records templates */
		foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( "template_content LIKE '%settings.reputation_enabled%'" ) ) as $row )
		{
			if ( preg_match( '#\$record\s+?instanceof\s+?\\\IPS\\\Content\\\Reputation#i', $row['template_content'] ) )
			{
				$row['template_content'] = preg_replace( '#\$record\s+?instanceof\s+?\\\IPS\\\Content\\\Reputation#i', '\IPS\IPS::classUsesTrait( $record, \'IPS\Content\Reactable\' )', $row['template_content'] );
				
				\IPS\Db::i()->update( 'cms_templates', array( 'template_content' => $row['template_content'] ), array( 'template_id=?', $row['template_id'] ) ); 
			}
		}

		return TRUE;
	}
}