<?php
/**
 * @brief		Customization AJAX actions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		07 May 2013
 */

namespace IPS\cms\modules\admin\pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Members AJAX actions
 */
class _ajax extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Return a CSS or HTML menu
	 *
	 * @return	void
	 */
	public function loadMenu()
	{
		$request   = array(
			't_location'  => ( isset( \IPS\Request::i()->t_location ) ) ? \IPS\Request::i()->t_location : null,
			't_group'     => ( isset( \IPS\Request::i()->t_group ) ) ? \IPS\Request::i()->t_group : null,
			't_key' 	  => ( isset( \IPS\Request::i()->t_key ) ) ? \IPS\Request::i()->t_key : null,
			't_type'      => ( isset( \IPS\Request::i()->t_type ) ) ? \IPS\Request::i()->t_type : 'templates',
		);

		switch( $request['t_type'] )
		{
			default:
			case 'template':
				$flag = \IPS\cms\Templates::RETURN_ONLY_TEMPLATE;
				break;
			case 'js':
				$flag = \IPS\cms\Templates::RETURN_ONLY_JS;
				break;
			case 'css':
				$flag = \IPS\cms\Templates::RETURN_ONLY_CSS;
				break;
		}

		$templates = \IPS\cms\Templates::buildTree( \IPS\cms\Templates::getTemplates( $flag + \IPS\cms\Templates::RETURN_DATABASE_ONLY ) );

		$current = new \IPS\cms\Templates;
		
		if ( ! empty( $request['t_key'] ) )
		{
			try
			{
				$current = \IPS\cms\Templates::load( $request['t_key'] );
			}
			catch( \OutOfRangeException $ex )
			{
				
			}
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'templates' )->menu( $templates, $current, $request );
	}

	/**
	 * Return HTML template as JSON
	 *
	 * @return	void
	 */
	public function loadTemplate()
	{
		$t_location  = \IPS\Request::i()->t_location;
		$t_key       = \IPS\Request::i()->t_key;
		
		if ( $t_location === 'block' and $t_key === '_default_' and isset( \IPS\Request::i()->block_key ) )
		{
			/* Find it from the normal template system */
			if ( isset( \IPS\Request::i()->block_app ) )
			{
				$plugin = \IPS\Widget::load( \IPS\Application::load( \IPS\Request::i()->block_app ), \IPS\Request::i()->block_key, mt_rand() );
			}
			else
			{
				$plugin = \IPS\Widget::load( \IPS\Plugin::load( \IPS\Request::i()->block_plugin ), \IPS\Request::i()->block_key, mt_rand() );
			}
			
			$location = $plugin->getTemplateLocation();
			
			$templateBits  = \IPS\Theme::master()->getRawTemplates( $location['app'], $location['location'], $location['group'], \IPS\Theme::RETURN_ALL );
			$templateBit   = $templateBits[ $location['app'] ][ $location['location'] ][ $location['group'] ][ $location['name'] ];
			
			if ( ! isset( \IPS\Request::i()->noencode ) OR ! \IPS\Request::i()->noencode )
			{
				$templateBit['template_content'] = htmlentities( $templateBit['template_content'], ENT_DISALLOWED, 'UTF-8', TRUE );
			}
			
			$templateArray = array(
				'template_id' 			=> $templateBit['template_id'],
				'template_key' 			=> 'template_' . $templateBit['template_name'] . '.' . $templateBit['template_id'],
				'template_title'		=> $templateBit['template_name'],
				'template_desc' 		=> null,
				'template_content' 		=> $templateBit['template_content'],
				'template_location' 	=> null,
				'template_group' 		=> null,
				'template_container' 	=> null,
				'template_rel_id' 		=> null,
				'template_user_created' => null,
				'template_user_edited'  => null,
				'template_params'  	    => $templateBit['template_data']
			);
		}
		else
		{
			try
			{
				if ( \is_numeric( $t_key ) )
				{
					$template = \IPS\cms\Templates::load( $t_key, 'template_id' );
				}
				else
				{
					$template = \IPS\cms\Templates::load( $t_key );
				}
			}
			catch( \OutOfRangeException $ex )
			{
				\IPS\Output::i()->json( array( 'error' => true ) );
			}

			if ( $template !== null )
			{
				$templateArray = array(
	                'template_id' 			=> $template->id,
	                'template_key' 			=> $template->key,
	                'template_title'		=> $template->title,
	                'template_desc' 		=> $template->desc,
	                'template_content' 		=> ( isset( \IPS\Request::i()->noencode ) AND \IPS\Request::i()->noencode ) ? $template->content : htmlentities( $template->content, ENT_DISALLOWED, 'UTF-8', TRUE ),
	                'template_location' 	=> $template->location,
	                'template_group' 		=> $template->group,
	                'template_container' 	=> $template->container,
	                'template_rel_id' 		=> $template->rel_id,
	                'template_user_created' => $template->user_created,
	                'template_user_edited'  => $template->user_edited,
	                'template_params'  	    => $template->params
	            );
			}
		}

		if ( \IPS\Request::i()->show == 'json' )
		{
			\IPS\Output::i()->json( $templateArray );
		}
		else
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Theme::i()->getTemplate( 'templates', 'cms', 'admin' )->viewTemplate( $templateArray ) ), 200, 'text/html' );
		}
	}
	
	/**
	 * [AJAX] Search templates
	 *
	 * @return	void
	 */
	public function searchtemplates()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'template_manage' );
				
		$where = array();
		if ( \IPS\Request::i()->term )
		{
			$where[] = array( '( LOWER(template_title) LIKE ? OR LOWER(template_content) LIKE ? )', '%' . mb_strtolower( \IPS\Request::i()->term ) . '%', '%' . mb_strtolower( \IPS\Request::i()->term ) . '%' );
		}
	
		if ( ! \in_array( 'custom', explode( ',', \IPS\Request::i()->filters ) ) )
		{
			$where[] = array( 'template_master=1 OR (template_user_created=1 and template_user_edited=0)' );
		}
		
		if ( ! \in_array( 'unmodified', explode( ',', \IPS\Request::i()->filters ) ) )
		{
			$where[] = array( 'template_user_created=1 and template_user_edited=1' );
		}

		if ( isset( \IPS\Request::i()->type ) )
		{
			$where[] = array( 'template_type=?', \IPS\Request::i()->type );
		}
		
		$select = \IPS\Db::i()->select(
			'*',
			'cms_templates',
			$where,
			'template_location, template_group, template_title, template_master desc'
		);

		$return = array();
		foreach( $select as $result )
		{
			$return[ $result['template_location'] ][ $result['template_group'] ][ $result['template_key'] ] = $result['template_title'];
		}
		
		\IPS\Output::i()->json( $return );
	}
	
	/**
	 * Load Tags
	 *
	 * @return	void
	 */
	public function loadTags()
	{
		$page = NULL;
		if ( isset( \IPS\Request::i()->pageId ) )
		{
			try
			{
				$page = \IPS\cms\Pages\Page::load( \IPS\Request::i()->pageId );
			}
			catch( \OutOfRangeException $e ) {}
		}
		
		$tags		= array();
		$tagLinks	= array();

		if ( $page and $page->id == \IPS\Settings::i()->cms_error_page )
		{
			$tags['cms_error_page']['{error_message}'] = 'cms_error_page_message';
			$tags['cms_error_page']['{error_code}']    = 'cms_error_page_code';
		}

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'databases', 'databases_use' ) )
		{
			foreach ( \IPS\cms\Databases::roots( NULL, NULL ) as $id => $db )
			{
				if ( !$db->page_id )
				{
					$tags['cms_tag_databases']['{database="' . $db->key . '"}'] = $db->_title;
				}
			}
		}

		foreach( \IPS\cms\Blocks\Block::roots( NULL, NULL ) as $id => $block )
		{
			$tags['cms_tag_blocks']['{block="' . $block->key . '"}'] = $block->_title;
		}

		foreach( \IPS\Db::i()->select( '*', 'cms_pages', NULL, 'page_full_path ASC', array( 0, 50 ) ) as $page )
		{
			$tags['cms_tag_pages']['{pageurl="' . $page['page_full_path'] . '"}' ] = \IPS\Member::loggedIn()->language()->addToStack( \IPS\cms\Pages\Page::$titleLangPrefix . $page['page_id'] );
		}
		
		/* If we can manage words, then the header needs to always show */
		if (  \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'languages', 'lang_words' ) )
		{
			$tags['cms_tag_lang'] = array();
			$tagLinks['cms_tag_lang']	= array(
				'icon'		=> 'plus',
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack('add_word'),
				'link'		=> \IPS\Http\Url::internal( "app=core&module=languages&controller=languages&do=addWord" ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'add_word' ), 'ipsDialog-remoteSubmit' => TRUE, 'action' => "wordForm" )
			);
		}
		
		foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( "word_is_custom=? AND lang_id=?", 1, \IPS\Member::loggedIn()->language()->_id ) ) AS $lang )
		{
			$tags['cms_tag_lang']['{lang="' . $lang['word_key'] . '"}'] = $lang['word_custom'] ?: $lang['word_default'];
		}
		
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->editorTags( $tags, $tagLinks ) ), 200, 'text/html' );
	}
}