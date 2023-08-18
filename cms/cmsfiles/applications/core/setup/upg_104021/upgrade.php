<?php
/**
 * @brief		4.4.3 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		25 Mar 2019
 */

namespace IPS\core\setup\upg_104021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.3 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Delete an old language string causing confusion - chose not to do this via sql json as this won't be run on magic patcher */
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_key=?', 'pf_format_desc' ) );
		
		/* Now try and fix up content fields for the 100th time (actually 2nd but I like to be dramatic */
		foreach( \IPS\Db::i()->select( '*', 'core_pfields_data' ) as $field )
		{
			/* We switched things around a bit, so $rawContent is now $content, and $content is now $processedContent, it made sense like 8 minutes ago */
			$update = array();
			
			foreach( array( 'pf_format', 'pf_profile_format' ) as $type )
			{
				if ( $field[ $type ] and stristr( $field[ $type ], '$rawContent' ) )
				{
					/* using $rawContent already, so lets try and sort that... */
					$fixed = str_replace( '$rawContent', '$_content_', $field[ $type ] ); # Move rawContent to a placeholder so we can...
					$fixed = str_replace( '$content' , '$processedContent', $fixed ); # Move content to processedContent
					$fixed = str_replace( '$_content_', '$content', $fixed ); # And then finalize the rawContent to content move
					
					$update[ $type ] = $fixed;
				}
			}
			
			if ( \count( $update ) )
			{
				\IPS\Db::i()->update( 'core_pfields_data', $update, array( 'pf_id=?', $field['pf_id'] ) );
			}
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}