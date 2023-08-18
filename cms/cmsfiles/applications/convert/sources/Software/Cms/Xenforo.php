<?php

/**
 * @brief		Converter XenForo Page Nodes Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Xenforo Pages Converter
 */
class _Xenforo extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "XenForo Page Nodes (1.5.x/2.0.x/2.1.x/2.2.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "xenforo";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertCmsPages' => array(
				'table'				=> 'xf_page',
				'where'				=> NULL,
			)
		);
	}
	
	/**
	 * Uses Prefix
	 *
	 * @return	bool
	 */
	public static function usesPrefix()
	{
		return FALSE;
	}

	/**
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}
	
	/**
	 * Possible Parent Conversions
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'xenforo' ) );
	}

	/**
	 * Convert CMS page
	 *
	 * @return void
	 */
	public function convertCmsPages()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'xf_page.node_id' );
		
		foreach( $this->fetch( 'xf_page', 'xf_page.node_id' )->join( 'xf_node', 'xf_node.node_id = xf_page.node_id' ) AS $row )
		{
			try
			{
				$template = $this->db->select( 'template', 'xf_template', array( "title=?", "_page_node.{$row['node_id']}" ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$template = '';
			}
			
			$libraryClass->convertCmsPage( array(
				'page_id'		=> $row['node_id'],
				'page_name'		=> $row['title'],
				'page_seo_name'	=> $row['node_name'],
				'page_content'	=> $template,
			) );
			
			$libraryClass->setLastKeyValue( $row['node_id'] );
		}
	}
}