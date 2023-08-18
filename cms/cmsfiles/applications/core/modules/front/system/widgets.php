<?php
/**
 * @brief		Sidebar Widgets
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Nov 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Sidebar Widgets
 */
class _widgets extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Check Permissions */
		if ( ! \IPS\Member::loggedIn()->modPermission('can_manage_sidebar') )
		{
			\IPS\Output::i()->error( 'no_permission_manage_sidebar', '2S172/1', 403, '' );
		}
		
		parent::execute();
	}
	
	/**
	 * Build The Block List For Front End
	 *
	 * @return	void
	 */
	protected function getBlockList()
	{
		$availableBlocks = array();
		
		foreach ( \IPS\Db::i()->select( "*", 'core_widgets' ) as $widget )
		{
			try
			{
				$appOrPlugin = isset( $widget['plugin'] ) ? \IPS\Plugin::load( $widget['plugin'] ) : \IPS\Application::load( $widget['app'] );

				if ( isset( $widget['plugin'] ) )
				{
					if ( !$appOrPlugin->enabled )
					{
						continue;
					}
				}
				else
				{
					if ( !\IPS\Application::appIsEnabled( $appOrPlugin->directory ) or !\IPS\Application::load( $appOrPlugin->directory )->canAccess() )
					{
						continue;
					}
				}

				$block = \IPS\Widget::load( $appOrPlugin, $widget['key'], mt_rand(), array(), $widget['restrict'], null );
				$block->allowReuse = (boolean) $widget['allow_reuse'];
				$block->menuStyle  = $widget['menu_style'];
				
				if ( ! $block->isExecutableByApp( array( \IPS\Request::i()->pageApp, 'sidebar' ) ) )
				{
					throw new \OutOfRangeException;
				}
			}
			catch( \Exception $e )
			{
				continue;
			}
			
			if( isset( $widget['app'] ) )
			{
				$availableBlocks['apps'][ $widget['app'] ][] = $block;
			}
			else
			{
				$availableBlocks['plugin'][ $widget['plugin'] ][] = $block;
			}
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'widgets' )->blockList( $availableBlocks );
	}
	
	/**
	 * Get Output For Adding A New Block
	 *
	 * @return	void
	 */
	protected function getBlock()
	{		
		$key = $block = explode( "_", \IPS\Request::i()->blockID );

		if ( isset( \IPS\Request::i()->pageApp ) )
		{
			try
			{
				foreach ( json_decode( \IPS\Db::i()->select( 'widgets', 'core_widget_areas', array( 'app=? AND module=? AND controller=? AND area=?', \IPS\Request::i()->pageApp, \IPS\Request::i()->pageModule, \IPS\Request::i()->pageController, \IPS\Request::i()->pageArea ) )->first(), TRUE ) as $k => $block )
				{
					switch( $key[0] )
					{
						case 'app':

							if( ( isset( $block['app'] ) and $block['app'] == $key[1] ) AND $block['key'] == $key[2] AND $block['unique'] == $key[3] )
							{
								$widget = \IPS\Widget::load( \IPS\Application::load( $block['app'] ), $block['key'], $block['unique'], $block['configuration'], null, \IPS\Request::i()->orientation );
								break 2;
							}
							break;
						
						case 'plugin':
							if( ( isset( $block['plugin'] ) and $block['plugin'] == $key[1] ) AND $block['key'] == $key[2] AND $block['unique'] == $key[3] )
							{
								$widget = \IPS\Widget::load( \IPS\Plugin::load( $block['plugin'] ), $block['key'], $block['unique'], $block['configuration'], null, \IPS\Request::i()->orientation );
								break 2;
							}
							break;
					}
				}
			}
			catch ( \OutOfRangeException $e ) { }
			catch ( \UnderflowException $e ) { }
		}
		
		if ( !isset( $widget ) )
		{
			try
			{
				$widget = \IPS\Widget::load( ( $key[0] == 'plugin' ) ? \IPS\Plugin::load( $key[1] ) : \IPS\Application::load( $key[1] ), $key[2], $key[3], array(), null, \IPS\Request::i()->orientation );
			}
			catch( \OutOfRangeException $e )
			{
				$widget = '';
			}
		}

		\IPS\Output::i()->json( array( 'html' => (string) $widget, 'devices' => ( isset( $widget->configuration['devices_to_show'] ) ) ? $widget->configuration['devices_to_show'] : array('Phone', 'Tablet', 'Desktop') ) );
	}
	
	/**
	 * Get Configuration
	 *
	 * @return	void
	 */
	protected function getConfiguration()
	{
		$key	= explode( "_", \IPS\Request::i()->block );
		$blocks	= array();

		try
		{
			$where = ( $key[0] ) == 'app' ? '`key`=? AND `app`=?' : '`key`=? AND `plugin`=?';
			$widgetMaster = \IPS\Db::i()->select( '*', 'core_widgets', array( $where, $key[2], $key[1] ) )->first();

			$blocks = \IPS\Db::i()->select( '*', 'core_widget_areas', array( 'app=? AND module=? AND controller=? AND area=?', \IPS\Request::i()->pageApp, \IPS\Request::i()->pageModule, \IPS\Request::i()->pageController, \IPS\Request::i()->pageArea ) )->first();
			$blocks	= json_decode( $blocks['widgets'], TRUE );
		}
		catch ( \UnderflowException $e )
		{
			switch( $key[0] )
			{
				case 'app':
					$blocks = array( array( 'app' => $key[1], 'key' => $key[2], 'unique' => $key[3], 'configuration' => array() ) );
					break;
						
				case 'plugin':
					$blocks = array( array( 'plugin' => $key[1], 'key' => $key[2], 'unique' => $key[3], 'configuration' => array() ) );
					break;
			}
		}
		
		$widget	= NULL;

		if( !empty( $blocks ) )
		{
			foreach ( $blocks as $k => $block )
			{
				switch( $key[0] )
				{
					case 'app':
						if( ( isset( $block['app'] ) AND $block['app'] == $key[1] ) AND $block['key'] == $key[2] AND $block['unique'] == $key[3] )
						{
							$widget = \IPS\Widget::load( \IPS\Application::load( $block['app'] ), $block['key'], $block['unique'], $block['configuration'] );
						}
						break;
					
					case 'plugin':
						if( ( isset( $block['plugin'] ) AND $block['plugin'] == $key[1] ) AND $block['key'] == $key[2] AND $block['unique'] == $key[3] )
						{
							$widget = \IPS\Widget::load( \IPS\Plugin::load( $block['plugin'] ), $block['key'], $block['unique'], $block['configuration'] );
						}
						break;
				}

				if( $widget !== NULL AND method_exists( $widget, 'configuration' ) )
				{
					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->jsFiles = array();
					}
					
					$widget->menuStyle = $widgetMaster['menu_style'];
					$form = new \IPS\Helpers\Form( 'form', 'saveSettings' );

					if ( $configurationForm = $widget->configuration( $form ) )
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
							
							if( isset( $values['show_on_all_devices'] ) and $values['show_on_all_devices'] )
							{
								$values['devices_to_show'] = array( 'Phone', 'Tablet', 'Desktop' );
							}

							unset( $values['show_on_all_devices'] );

							$blocks[ $k ]['configuration'] = $values;
							\IPS\Db::i()->insert( 'core_widget_areas', array( 'app' => \IPS\Request::i()->pageApp, 'module' => \IPS\Request::i()->pageModule, 'controller' => \IPS\Request::i()->pageController, 'area' => \IPS\Request::i()->pageArea, 'widgets' => json_encode( $blocks ) ), TRUE );
							\IPS\Widget::deleteCaches( $block['key'], ( isset( $block['app'] ) ) ? $block['app'] : NULL, ( isset( $block['plugin'] ) ) ? $block['plugin'] : NULL );
							\IPS\Output::i()->json( 'OK' );
						}
						
						\IPS\Member::loggedIn()->language()->words['widget_adv__custom_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'widget_adv__custom__desc', FALSE, array( 'sprintf' => array( \IPS\Request::i()->block ) ) );
						\IPS\Output::i()->output = $configurationForm->customTemplate( array( \IPS\Theme::i()->getTemplate( 'widgets', 'core' ), 'formTemplate' ), $widget );
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
		if( !\in_array( \IPS\Request::i()->area, array( 'sidebar', 'header', 'footer' ) ) )
		{
			\IPS\Output::i()->error( 'invalid_widget_area', '3S172/2', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		
		$newOrder = array();
		$seen     = array();
		
		try
		{
			$currentConfig = \IPS\Db::i()->select( '*', 'core_widget_areas', array( 'app=? AND module=? AND controller=? AND area=?', \IPS\Request::i()->pageApp, \IPS\Request::i()->pageModule, \IPS\Request::i()->pageController, \IPS\Request::i()->area ) )->first();
			$widgets = json_decode( $currentConfig['widgets'], TRUE );
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
					if ( $widget['key'] == $block[2] AND $widget['unique'] == $block[3] )
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
					
					$newBlock['key'] 		= $block[2];
					$newBlock['unique']		= $block[3];
					$newBlock['configuration']	= array();
					
					/* Make sure this widget doesn't have configuration in another area */
					$newBlock['configuration'] = \IPS\Widget::getConfiguration( $newBlock['unique'] );

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

		/* Expire Caches so up to date information displays */
		\IPS\Widget::deleteCaches();

		/* Save to database */
		\IPS\Db::i()->replace( 'core_widget_areas', array( 'app' => \IPS\Request::i()->pageApp, 'module' => \IPS\Request::i()->pageModule, 'controller' => \IPS\Request::i()->pageController, 'widgets' => json_encode( $newOrder ), 'area' => \IPS\Request::i()->area ) );
	}
}