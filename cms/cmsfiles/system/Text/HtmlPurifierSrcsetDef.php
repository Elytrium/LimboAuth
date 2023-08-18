<?php
/**
 * @brief		A HTMLPurifier Attribute Definition for srcset attributes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 May 2016
 */

namespace IPS\Text;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * A HTMLPurifier Attribute Definition used for attributes which must be internal URLs
 */
class _HtmlPurifierSrcsetDef extends \HTMLPurifier_AttrDef_URI
{
	/**
	 * Validate
	 * 
     * @param	string					$srcset
     * @param	\HTMLPurifier_Config	$config
     * @param	\HTMLPurifier_Context	$context
     * @return	bool|string
     */
    public function validate($srcset, $config, $context)
    {
	    $return = array();
	    	    
	    foreach ( explode( ',', $srcset ) as $src )
	    {
		    $exploded = explode( ' ', trim( $src ) );
		    $uri = array_shift( $exploded );
		    $descriptor = implode( ' ', $exploded );
		    
		    $validated = parent::validate( $uri, $config, $context );
		    if ( $validated !== FALSE )
		    {
			    $return[] = $validated . ( $descriptor ? ( ' ' . $descriptor ) : '' );
		    }
	    }
	    	    
	    if ( \count( $return ) )
	    {
		    return implode( ', ', $return );
	    }
	    
	    return FALSE;
    }
}