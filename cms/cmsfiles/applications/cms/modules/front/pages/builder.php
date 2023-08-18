<?php
/**
 * @brief		[Front] Page Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		25 Feb 2014
 */

namespace IPS\cms\modules\front\pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * page
 */
class _builder extends \IPS\core\modules\front\system\widgets
{
	
	/**
	 * Get Output For Adding A New Block
	 *
	 * @return	void
	 */
	protected function getBlock()
	{		
		$key = $block = explode( "_", \IPS\Request::i()->blockID );
		
		if ( isset( \IPS\Request::i()->pageID ) )
		{
			try
			{
				foreach ( \IPS\Db::i()->select( '*', 'cms_page_widget_areas', array( 'area_page_id=?', \IPS\Request::i()->pageID ) ) as $item )
				{
					$blocks = json_decode( $item['area_widgets'], TRUE );
					
					foreach( $blocks as $block )
					{
						if( $block['key'] == $key[2] AND $block['unique'] == $key[3] )
						{ 
							if ( isset( $block['app'] ) and $block['app'] == $key[1] )
							{
								$widget = \IPS\Widget::load( \IPS\Application::load( $block['app'] ), $block['key'], $block['unique'], $block['configuration'], null, \IPS\Request::i()->orientation );
							}
							elseif ( isset( $block['plugin'] ) and $block['plugin'] == $key[1] )
							{
								$widget = \IPS\Widget::load( \IPS\Plugin::load( $block['plugin'] ), $block['key'], $block['unique'], $block['configuration'], null, \IPS\Request::i()->orientation );
							}
						}
					}
				}
			}
			catch ( \UnderflowException $e ) { }

			/* Make sure the current page is set so the widgets have database/page scope */
			\IPS\cms\Pages\Page::$currentPage = \IPS\cms\Pages\Page::load( \IPS\Request::i()->pageID );

			/* Have we got a database for this page? */
			$database = \IPS\cms\Pages\Page::$currentPage->getDatabase();

			if ( $database )
			{
				\IPS\cms\Databases\Dispatcher::i()->setDatabase( $database->id );
			}
		}
		
		if ( !isset( $widget ) )
		{
			try
			{
				$widget = \IPS\Widget::load( \IPS\Application::load( $key[1] ), $key[2], $key[3], array(), null, \IPS\Request::i()->orientation );

			}
			catch ( \OutOfRangeException $e )
			{
				$widget = \IPS\Widget::load( \IPS\Plugin::load( $key[1] ), $key[2], $key[3], array(), null, \IPS\Request::i()->orientation );
			}
		}

		$output = (string) $widget;

		\IPS\Output::i()->output = ( $output ) ? $output :  \IPS\Theme::i()->getTemplate( 'widgets', 'core', 'front' )->blankWidget( $widget );
	}

	/**
	 * Get Configuration
	 *
	 * @return	void
	 */
	protected function getConfiguration()
	{
		/* Standard widget area, allow the core stuff to handle this */
		if( \in_array( \IPS\Request::i()->area, array( 'sidebar', 'header', 'footer' ) ) )
		{
			return parent::getConfiguration();
		}
		
		$key	= explode( "_", \IPS\Request::i()->block );
		$blocks	= array( 'area_widgets' => NULL );
		
		/* CMS only stuff */
		try
		{
			$blocks       = \IPS\Db::i()->select( '*', 'cms_page_widget_areas', array( 'area_page_id=? AND area_area=?', \IPS\Request::i()->pageID, \IPS\Request::i()->pageArea ) )->first();

			$where = ( $key[0] ) == 'app' ? '`key`=? AND `app`=?' : '`key`=? AND `plugin`=?';
			$widgetMaster = \IPS\Db::i()->select( '*', 'core_widgets', array( $where, $key[2], $key[1] ) )->first();
		}
		catch ( \UnderflowException $e )
		{
		}
		
		$blocks	= json_decode( $blocks['area_widgets'], TRUE );
		$widget	= NULL;

		if( !empty( $blocks ) )
		{
			foreach ( $blocks as $k => $block )
			{
				if ( $block['key'] == $key[2] AND $block['unique'] == $key[3] )
				{
					if ( isset( $block['app'] ) and $block['app'] == $key[1] )
					{
						$widget = \IPS\Widget::load( \IPS\Application::load( $block['app'] ), $block['key'], $block['unique'], $block['configuration'] );
						$widget->menuStyle = $widgetMaster['menu_style'];
					}
					elseif ( isset( $block['plugin'] ) and $block['plugin'] == $key[1] )
					{
						$widget = \IPS\Widget::load( \IPS\Plugin::load( $block['plugin'] ), $block['key'], $block['unique'], $block['configuration'] );
						$widget->menuStyle = $widgetMaster['menu_style'];
					}
				}

				if( $widget !== NULL AND method_exists( $widget, 'configuration' ) )
				{
					$form = new \IPS\Helpers\Form( 'form', 'saveSettings' );
					if ( $widget->configuration( $form ) !== NULL )
					{
						if ( $values = $form->values() )
						{
							if ( method_exists( $widget, 'preConfig' ) )
							{
								$values = $widget->preConfig( $values );
							}
							
							/* Special advanced builder stuff */
							if ( \in_array( 'IPS\Widget\Builder', class_implements( $widget ) ) )
							{
								if( isset( $values['widget_adv__background_custom_image'] ) and $values['widget_adv__background_custom_image'] )
								{
									$values['widget_adv__background_custom_image'] = (string) $values['widget_adv__background_custom_image'];
								}
							}

							$blocks[ $k ]['configuration'] = $values;
							\IPS\Db::i()->insert( 'cms_page_widget_areas', array( 'area_page_id' => \IPS\Request::i()->pageID, 'area_area' => \IPS\Request::i()->pageArea, 'area_widgets' => json_encode( $blocks ) ), TRUE );
							\IPS\Output::i()->json( 'OK' );
						}
						\IPS\Output::i()->output = $widget->configuration()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'widgets', 'core' ), 'formTemplate' ), $widget );
					}
				}
			}
		}
	}
	
	/**
	 * Reorder Blocks
	 *
	 * @return	void
	 */
	protected function saveOrder()
	{
		$newOrder = array();
		$seen     = array();

		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$currentConfig = \IPS\Db::i()->select( '*', 'cms_page_widget_areas', array( 'area_page_id=? AND area_area=?', \IPS\Request::i()->pageID, \IPS\Request::i()->area ) )->first();
			$widgets = json_decode( $currentConfig['area_widgets'], TRUE );
			if( !is_countable(  $widgets ) )
			{
				throw new \UnderflowException;
			}
		}
		catch ( \UnderflowException $e )
		{
			$widgets = array();
		}

		/* Loop over the new order and merge in current blocks so we don't lose config */
		if ( isset ( \IPS\Request::i()->order ) )
		{
			foreach ( \IPS\Request::i()->order as $block )
			{
				$block = explode( "_", $block );
				
				$added = FALSE;
				foreach( $widgets as $widget )
				{
					if ( $widget['key'] == $block[2] and $widget['unique'] == $block[3] )
					{
						$seen[]     = $widget['unique'];
						$newOrder[] = $widget;
						$added = TRUE;
						break;
					}
				}
				if( !$added )
				{
					$newBlock = array();
					
					if ( $block[0] == 'app' )
					{
						$newBlock['app'] = $block[1];
					}
					else
					{
						$newBlock['plugin'] = $block[1];
					}
					
					$newBlock['key'] 		  = $block[2];
					$newBlock['unique']		  = $block[3];
					$newBlock['configuration']	= array();

					/* Make sure this widget doesn't have configuration in another area */
					$newBlock['configuration'] = \IPS\cms\Widget::getConfiguration( $newBlock['unique'] );

					$seen[]     = $block[3];
					$newOrder[] = $newBlock;
				}
			}
		}

		/* Anything to update? */
		if ( \count( $widgets ) > \count( $newOrder ) )
		{
			/* No items left in area, or one has been removed */
			foreach( $widgets as $widget )
			{
				/* If we haven't seen this widget, it's been removed, so add to trash */
				if ( ! \in_array( $widget['unique'], $seen ) )
				{
					\IPS\Widget::trash( $widget['unique'], $widget );
				}
			}
		}
		
		/* Check core_widget_areas to ensure that the block wasn't added there */
		if ( isset( \IPS\Request::i()->exclude ) and ! empty( \IPS\Request::i()->exclude ) )
		{
			$bits = explode( "_", \IPS\Request::i()->exclude );
			$this->_checkAndDeleteFromCoreWidgets( $bits[3], $seen );
		}
		
		/* Expire Caches so up to date information displays */
		\IPS\Widget::deleteCaches();

		/* Save to database */
		$orientation = ( isset( \IPS\Request::i()->orientation ) and \IPS\Request::i()->orientation === 'vertical' ) ? 'vertical' : 'horizontal';
		\IPS\Db::i()->replace( 'cms_page_widget_areas', array( 'area_orientation' => $orientation, 'area_page_id' => \IPS\Request::i()->pageID, 'area_widgets' => json_encode( $newOrder ), 'area_area' => \IPS\Request::i()->area ) );
		
		\IPS\cms\Pages\Page::load( \IPS\Request::i()->pageID )->postWidgetOrderSave();
	}
	
	/**
	 * Sometimes the widgets end up in the core table. We haven't really found out why this happens. It happens very rarely.
	 * It may be that the CMS JS mixin doesn't load so the core ajax URLs are used (system/widgets.php) and not the cms widget (page/builder.php).
	 * This method ensures that any widgets in the core table are removed
	 *
	 * @param	string	$uniqueId	The unique key of the widget (eg: wzsj1233)
	 * @param	array	$widgets	Current widgets (eg from core_widget_areas.widgets (json decoded))
	 * @return	bool				True if something removed, false if not
	 */
	protected function _checkAndDeleteFromCoreWidgets( $uniqueId, $widgets )
	{
		if ( ! \in_array( $uniqueId, $widgets ) )
		{
			/* This widget hasn't been seen, so it isn't in the cms table */
			try
			{
				$cmsWidget = \IPS\Db::i()->select( '*', 'core_widget_areas', array( 'app=? and module=? and controller=? and area=?', 'cms', 'pages', 'page', \IPS\Request::i()->area ) )->first();
				$cmsWidgets = json_decode( $cmsWidget['widgets'], TRUE );
				$newWidgets = array();
				
				foreach( $cmsWidgets as $item )
				{
					if ( $item['unique'] !== $uniqueId )
					{
						$newWidgets[] = $item;
					}
				}
				
				/* Anything to save? */
				if ( \count( $newWidgets ) )
				{
					\IPS\Db::i()->replace( 'core_widget_areas', array( 'app' => 'cms', 'module' => 'pages', 'controller' => 'page', 'widgets' => json_encode( $newWidgets ), 'area' => \IPS\Request::i()->area ) );
				}
				else
				{
					/* Just remove the entire row */
					\IPS\Db::i()->delete( 'core_widget_areas', array( 'app=? and module=? and controller=? and area=?', 'cms', 'pages', 'page', \IPS\Request::i()->area ) );
				}
				
				return TRUE;
			}
			catch( \UnderFlowException $ex )
			{
				/* Well, it isn't there either... */
				return FALSE;
			}
		}
	}
}