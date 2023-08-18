<?php
/**
 * @brief		Database Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		22 Aug 2014
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Database Widget
 */
class _Database extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'Database';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * @brief	HTML if widget is called more than once, we store it.
	 */
	protected static $html = NULL;
	
	/**
	 * Specify widget configuration
	 *
	 * @param	\IPS\Helpers\Form|NULL	$form	Form helper
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
		$form = parent::configuration( $form );

 		$databases = array();
	    $disabled  = array();

 		foreach( \IPS\cms\Databases::databases() as $db )
 		{
		    $databases[ $db->id ] = $db->_title;

		    if ( $db->page_id and $db->page_id != \IPS\Request::i()->pageID )
		    {
			    $disabled[] = $db->id;

				try
				{
					$page = \IPS\cms\Pages\Page::load( $db->page_id );
					$databases[ $db->id ] = \IPS\Member::loggedIn()->language()->addToStack( 'cms_db_in_use_by_page', FALSE, array( 'sprintf' => array( $db->_title, $page->full_path ) ) );
				}
				catch( \OutOfRangeException $ex )
				{
					unset( $databases[ $db->id ] );
				}
		    }
 		}

	    if ( ! \count( $databases ) )
	    {
		    $form->addMessage('cms_err_no_databases_to_use');
	    }
 		else
	    {
			$form->add( new \IPS\Helpers\Form\Select( 'database', ( isset( $this->configuration['database'] ) ? (int) $this->configuration['database'] : NULL ), FALSE, array( 'options' => $databases, 'disabled' => $disabled ) ) );
	    }

		return $form;
 	}

	/**
	 * Pre save
	 *
	 * @param   array   $values     Form values
	 * @return  array
	 */
	public function preConfig( $values )
	{
		if ( \IPS\Request::i()->pageID and $values['database'] )
		{
			\IPS\cms\Pages\Page::load( \IPS\Request::i()->pageID )->mapToDatabase( $values['database'] );
		}

		return $values;
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if ( static::$html === NULL )
		{
			if ( isset( $this->configuration['database'] ) )
			{
				try
				{
					$database = \IPS\cms\Databases::load( \intval( $this->configuration['database'] ) );
					
					if ( ! $database->page_id and \IPS\cms\Pages\Page::$currentPage )
					{
						$database->page_id = \IPS\cms\Pages\Page::$currentPage->id;
						$database->save();
					}

					static::$html = \IPS\cms\Databases\Dispatcher::i()->setDatabase( $database->id )->run();
				}
				catch ( \OutOfRangeException $e )
				{
					static::$html = '';
				}
			}
			else
			{
				return '';
			}
		}
		
		return static::$html;
	}
}