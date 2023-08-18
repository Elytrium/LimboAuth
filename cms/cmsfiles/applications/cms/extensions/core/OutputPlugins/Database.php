<?php
/**
 * @brief		Template Plugin - Content: Database
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		12 March 2014
 */

namespace IPS\cms\extensions\core\OutputPlugins;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Template Plugin - Content: Database
 */
class _Database
{
	/**
	 * @brief	Can be used when compiling CSS
	 */
	public static $canBeUsedInCss = FALSE;
	
	/**
	 * @brief	Record how many database tags there are per page
	 */
	public static $count = 0;
	
	/**
	 * Run the plug-in
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options    Array of options
	 * @return	string		Code to eval
	 */
	public static function runPlugin( $data, $options )
	{
		if ( isset( $options['category'] ) )
		{
			return '\IPS\cms\Databases\Dispatcher::i()->setDatabase( "' . $data . '" )->setCategory( "' . $options['category'] . '" )->run()';
		}
		
		return '\IPS\cms\Databases\Dispatcher::i()->setDatabase( "' . $data . '" )->run()';
	}
	
	/**
	 * Do any processing before a page is added/saved
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options  Array of options
	 * @param	object		$page	  Page being edited/saved
	 * @return	void
	 */
	public static function preSaveProcess( $data, $options, $page )
	{
		/* Keep a count of databases used so far */
		static::$count++;
		
		if ( static::$count > 1 )
		{
			throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack('cms_err_db_already_on_page') );
		}
	}
	
	/**
	 * Do any processing after a page is added/saved
	 *
	 * @param	string 		$data	  The initial data from the tag
	 * @param	array		$options  Array of options
	 * @param	object		$page	  Page being edited/saved
	 * @return	void
	 */
	public static function postSaveProcess( $data, $options, $page )
	{
		$database = NULL;
		
		try
		{
			if ( \is_numeric( $data ) )
			{
				$database = \IPS\cms\Databases::load( $data );
			}
			else
			{
				$database = \IPS\cms\Databases::load( $data, 'database_key' );
			}
			
			if ( $database->id AND $page->id )
			{
				try
				{
					$page->mapToDatabase( $database->id );
				}
				catch( \LogicException $ex )
				{
					throw new \LogicException( $ex->getMessage() );
				}
			}
		}
		catch( \OutofRangeException $ex ) { }
	}

}