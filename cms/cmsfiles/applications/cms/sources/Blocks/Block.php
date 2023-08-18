<?php
/**
 * @brief		Block Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		19 Feb 2014
 */

namespace IPS\cms\Blocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Block Model
 */
class _Block extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'cms_blocks';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'block_';

	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('block_key');
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();

	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = null;

	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'category';

	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\cms\Blocks\Container';

	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnOrder = 'position';

	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;

	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = TRUE;

	/**
	 * @brief	[Node] Title
	 */
	public static $nodeTitle = 'block';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'content_block_name_';

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';

	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 array(
	 'app'		=> 'core',				// The application key which holds the restrictrions
	 'module'	=> 'foo',				// The module key which holds the restrictions
	 'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 'add'			=> 'foo_add',
	 'edit'			=> 'foo_edit',
	 'permissions'	=> 'foo_perms',
	 'delete'		=> 'foo_delete'
	 ),
	 'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
			'app'		=> 'cms',
			'module'	=> 'pages',
			'prefix' 	=> 'block_'
	);

	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'cms';

	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'blocks';

	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
			'view' => 'view'
	);

	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_cms_block_';

	/**
	 * @brief  Templates already loaded and evald via getTemplate()
	 */
	public static $calledTemplates = array();

	/**
	 * Parse a block for display
	 * Wrapped in a static method so we can catch the OutOfRangeException and take action.
	 *
	 * @param	string|int|\IPS\cms\Blocks\Block	$block	Block ID
     * @param	string	$orientation	Orientation
	 * @return	string	Ready to display HTML
	 */
	public static function display( $block, $orientation=NULL )
	{
		try
		{
			try
			{
				if ( \is_numeric( $block ) )
				{
					$block = static::load( $block );
				}
				else if ( ! $block instanceof \IPS\cms\Blocks\Block )
				{
					$block = static::load( $block, 'block_key' );
				}

				if ( !$block->active )
				{
					return NULL;
				}
			}
			catch( \OutOfRangeException $ex )
			{
				return NULL;
			}

			/* We gots the perms to see this? */
			if ( !$block->can( 'view' ) )
			{
				return NULL;
			}

			if ( $block->type === 'custom' )
			{
				try
				{
					$functionName = 'content_blocks_' .  $block->id;
	
					if ( ! isset( \IPS\Data\Store::i()->$functionName ) )
					{
						$content = $block->content;
	
						if( $block->getConfig('editor') == 'php' )
						{
							ob_start();
							eval( $content );
							$content = ob_get_clean();
						}
	
						\IPS\Data\Store::i()->$functionName = \IPS\Theme::compileTemplate( $content, $functionName, null, true );
					}
	
					\IPS\Theme::runProcessFunction( \IPS\Data\Store::i()->$functionName, $functionName );
	
					if( $block->getConfig('editor') == 'php' )
					{
						unset( \IPS\Data\Store::i()->$functionName );
					}

					$themeFunction = 'IPS\\Theme\\'. $functionName;
					$html = $themeFunction();

					if( $block->getConfig('editor') == 'editor' )
					{
						$html = \IPS\Theme::i()->getTemplate( 'widgets', 'cms', 'front' )->Wysiwyg( \IPS\Member::loggedIn()->language()->addToStack( "cms_block_content_{$block->id}" ), $orientation );
					}

					return $html;
				}
				catch ( \ParseError $e )
				{
					@ob_end_clean();
					\IPS\Log::log( $e, 'block_error' );
					return "<span style='background:black;color:white;padding:6px;'>[[Block {$block->key} is throwing an error]]</span>";
				}
			}
			else
			{
				$block->orientation = $orientation;

				if ( $block->template OR $block->content )
				{
					$block->widget()->template( array( $block, 'getTemplate' ) );
				}

				return \IPS\Widget::parseOutput( $block->widget()->render() );
			}
		}
		catch( \OutOfRangeException $ex )
		{
			return NULL;
		}
	}

	/**
	 *  Method to overload standard widget templates
	 *
	 *  @return void
	 */
	public function getTemplate()
	{
		$args		  = \func_get_args();
		$functionName = 'content_template_for_block_' .  $this->id;

		unset( \IPS\Data\Store::i()->$functionName );

		/* Still here */
		if ( ! \in_array( $functionName, array_keys( static::$calledTemplates ) ) )
		{
			if ( ! isset( \IPS\Data\Store::i()->$functionName ) )
			{
				if ( $this->content )
				{
					\IPS\Data\Store::i()->$functionName = \IPS\Theme::compileTemplate( $this->content, 'run', $this->template_params, true );
					
				}
				else if ( $this->template )
				{
					try
					{
						$template	= \IPS\cms\Templates::load( $this->template );
						$object		= \IPS\cms\Theme::i()->getTemplate( $template->group, 'cms', $template->location );
						$title		= $template->title;
						return $object->$title( ...$args );
					}
					catch( \OutOfRangeException $ex )
					{
						/* @todo what to do here? */
					}
				}
			}

			/* Put them in a class */
			$template = <<<EOF
class class_{$functionName}
{

EOF;
			$template .= \IPS\Data\Store::i()->$functionName;

			$template .= <<<EOF
}
EOF;

			/* It lives! */
			\IPS\Theme::runProcessFunction( $template, $functionName );

			$class = "\IPS\Theme\\class_{$functionName}";

			/* Init */
			static::$calledTemplates[ $functionName ] = new $class();
		}
		
		return static::$calledTemplates[ $functionName ]->run( ...$args );
	}

	/**
	 * Delete compiled versions
	 *
	 * @param 	null|int|array 	$ids	Integer ID or Array IDs to remove
	 * @return void
	 */
	public static function deleteCompiled( $ids=NULL )
	{
		if ( $ids === NULL )
		{
			$ids = iterator_to_array( \IPS\Db::i()->select( 'block_id', 'cms_blocks' )->setValueField('block_id') );
		}
		else if ( \is_numeric( $ids ) )
		{
			$ids = array( $ids );
		}

		foreach( $ids as $id )
		{
			$functionName = 'content_blocks_' .  $id;
			if ( isset( \IPS\Data\Store::i()->$functionName ) )
			{
				unset( \IPS\Data\Store::i()->$functionName );
			}

			$functionName = 'content_template_for_block_' .  $id;
			if ( isset( \IPS\Data\Store::i()->$functionName ) )
			{
				unset( \IPS\Data\Store::i()->$functionName );
			}
		}
		
		/* We can also use blocks in per-page CSS */
		\IPS\cms\Pages\Page::deleteCompiledIncludes();
	}

	/**
	 * @brief	Config json as array
	 */
	protected $_config = null;

	/**
	 * @brief   Stores a \IPS\Widget object if this is a custom block with an embedded widget
	 */
	protected $widgetLoaded = [];

	/**
	 * @brief   Orientation for an embedded widget
	 */
	public $orientation = NULL;

	/**
	 * Get config as an array if no $key, or as whatever type corresponds to key
	 *
	 * @param	string|null	$key	Config key to fetch
	 * @return	mixed
	 */
	public function getConfig( $key = NULL)
	{
		if ( $this->_config === NULL )
		{
			$this->_config = json_decode( $this->config, TRUE );

			if ( $this->_config === FALSE )
			{
				$this->_config = array();
			}
		}

		if ( $key )
		{
			if ( isset( $this->_config[ $key ] ) )
			{
				return $this->_config[ $key ];
			}

			return NULL;
		}

		return $this->_config;
	}

	/**
	 * Set config key and value
	 *
	 * @param	string	$key	Config key
	 * @param	mixed	$value	Config value
	 * @return	mixed
	 */
	public function setConfig( $key, $value )
	{
		$this->_config[ $key ] = $value;
	}

	/**
	 * [Node] Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	protected function get__badge()
	{
		return array(
			0	=> 'ipsBadge ipsBadge_intermediary ipsPos_right',
			1	=> $this->type === 'custom' ? 'content_block_add_type_custom' : 'content_block_add_type_plugin',
		);
	}

	/**
	 * [Node] Get description
	 *
	 * @return	string
	 */
	protected function get__description()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'content_block_name_' . $this->_id . '_desc' );
	}

	/**
	 * Get configuration.
	 *
	 * @return array
	 */
	public function get__plugin_config()
	{
		return ( $this->plugin_config ? ( \is_array( $this->plugin_config ) ? $this->plugin_config : json_decode( $this->plugin_config, TRUE ) ) : array() );
	}

	/**
	 * [Node] Get buttons to display in tree
	 *
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );

		if ( isset( $buttons['add'] ) )
		{
			unset( $buttons['add']['data'] );
		}

		if ( isset( $buttons['edit'] ) )
		{
			unset( $buttons['edit']['data'] );
		}

        /* View Details */
        $buttons['details']	= array(
            'icon'	=> 'search',
            'title'	=> 'block_embed_options',
            'link'	=> \IPS\Http\Url::internal( "app=cms&module=pages&controller=blocks&do=embedOptions&id={$this->_id}" ),
            'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('block_embed_options') )
        );

		return $buttons;
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$block_type   = ( isset( \IPS\Request::i()->block_type ) )   ? \IPS\Request::i()->block_type   : ( $this->id ? $this->type : null );
		$block_editor = ( isset( \IPS\Request::i()->block_editor ) ) ? \IPS\Request::i()->block_editor : ( $this->id ? $this->getConfig('editor') : null );
		$block_plugin = ( isset( \IPS\Request::i()->block_plugin ) ) ? \IPS\Request::i()->block_plugin : ( $this->id ? $this->plugin : null );

		/* Build form */
		$form->addTab( 'content_block_form_tab__details' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'block_name', NULL, TRUE, array(
				'app'  => 'cms',
				'key'  => ( $this->id ? "content_block_name_" .  $this->id : NULL )
		) ) );

		$form->add( new \IPS\Helpers\Form\Translatable( 'block_description', NULL, FALSE, array(
            'app' => 'cms',
            'key' => ( $this->id ? "content_block_name_" .  $this->id . '_desc' : NULL )
        ) ) );
		
		try
		{
			$nodeContainer = $this->id ? $this->category :
				( \IPS\Request::i()->parent ?: \IPS\cms\Blocks\Container::load( ( $block_type == 'custom' ? 'block_custom' : 'block_plugins' ), 'container_key' )->id );
		}
		catch( \OutOfRangeException $e )
		{
			$nodeContainer = NULL;
		}
		$form->add( new \IPS\Helpers\Form\Node( 'block_category', $nodeContainer, TRUE, array(
				'class'    => '\IPS\cms\Blocks\Container',
				'subnodes' => false
		) ) );

		$form->addHeader( 'cms_block_form_display' );

		$form->add( new \IPS\Helpers\Form\Text( 'block_key', $this->id ? $this->key : FALSE, FALSE, array(), function( $val )
		{
			try
			{
				if ( ! $val )
				{
					return true;
				}

				try
				{
					$block = \IPS\cms\Blocks\Block::load( $val, 'block_key');
				}
				catch( \OutOfRangeException $ex )
				{
					/* Doesn't exist? Good! */
					return true;
				}

				/* It's taken... */
				if ( \IPS\Request::i()->id == $block->id )
				{
					/* But it's this one so that's ok */
					return true;
				}

				/* and if we're here, it's not... */
				throw new \InvalidArgumentException('cms_block_key_not_unique');
			}
			catch ( \OutOfRangeException $e )
			{
				/* Slug is OK as load failed */
				return true;
			}

			return true;
		} ) );

		/* Do we have config? */
		if ( $block_type === 'plugin' and $block_plugin )
		{
			if ( ! $this->id )
			{
				$this->type       = 'plugin';
				$this->plugin     = $block_plugin;
				
				if ( isset( \IPS\Request::i()->block_plugin_app ) )
				{
					$this->plugin_app = \IPS\Request::i()->block_plugin_app;
				}
				elseif ( isset( \IPS\Request::i()->block_plugin_plugin ) )
				{
					$this->plugin_plugin = \IPS\Request::i()->block_plugin_plugin;
				}
			}

			if ( mb_substr( $block_plugin, 0, 8 ) === 'db_feed_' )
			{
				$databaseId = \intval( mb_substr( $block_plugin, 8 ) );
				$this->plugin = 'RecordFeed';
				$this->plugin_config = array( 'cms_rf_database' => $databaseId );

				/* JS needs this to produce the preview */
				$form->hiddenValues['cms_rf_database'] = $databaseId;
			}
			else if ( $this->id and $block_plugin === 'RecordFeed' )
			{
				$form->hiddenValues['cms_rf_database'] = $this->_plugin_config['cms_rf_database'];
			}
						
			try
			{
				$block_editor = 'html';

				\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack( 'cms_block_feed_form_message', FALSE, array( 'sprintf' => $this->widget()->title() ) ), 'information', NULL, FALSE );
			}
			catch ( \OutOfRangeException $ex )
			{
				throw new \LogicException( 'cms_error_block_plugin_not_found' );
			}

			if ( \is_callable( array( $this->widget(), 'configuration' ) ) )
			{
				$form->addTab( 'content_block_form_tab__feed' );
				$this->widget()->configuration( $form );
			}
		}

		$form->addTab( 'content_block_form_tab__content');

		if ( $block_type === 'plugin' )
		{
			$templates = array( '_default_' => \IPS\Member::loggedIn()->language()->addToStack('content_block_template_use_default') );

			foreach( \IPS\cms\Templates::getTemplates( \IPS\cms\Templates::RETURN_BLOCK ) as $id => $obj )
			{
				if ( $obj->group == $this->widget()->key )
				{
					$templates[ $obj->key ] = $obj->title;
				}
			}

			/* List of templates */
			$form->add( new \IPS\Helpers\Form\Select( 'block_template_id', ( $this->id and $this->template ) ? $this->template : NULL, FALSE, array(
					'options' => $templates
			), NULL, NULL, \IPS\Theme::i()->getTemplate( 'blocks', 'cms', 'admin' )->previewTemplateLink( $block_plugin ), 'block_template_id' ) );

			/* Use or copy to edit */
			$useHow = NULL;
			if ( $this->id )
			{
				if ( \intval( $this->template ) or ( ! \intval( $this->template ) and ! $this->content ) )
				{
					$useHow = 'use';
				}
				else
				{
					$useHow = 'copy';
				}
			}

			$form->add( new \IPS\Helpers\Form\Select( 'block_template_use_how', $useHow, FALSE, array(
					'options' => array(
						'use'	=> 	'block_template_use_how_use',
						'copy'	=>  'block_template_use_how_copy'
					),
					'toggles' => array(
						'copy' => array( 'block_content', 'block_save_as_template' )
					)
			), NULL, NULL, NULL, 'block_template_use_how' ) );
		}

		if ( $block_editor === 'editor' )
		{
			$form->add( new \IPS\Helpers\Form\Translatable( 'block_content', NULL, FALSE, array(
				'key'			=> ( $this->id ) ? "cms_block_content_{$this->id}" : NULL,
				'editor'		=> array(
					'app'         => 'cms',
					'key'         => 'BlockContent',
					'autoSaveKey' => 'block-content-' . ( $this->id ? $this->id : 'new' ),
					'attachIds'	  => ( $this->id ) ? array( $this->id ) : NULL
				)
			) ) );
				
		}
		else
		{
			$form->add( new \IPS\Helpers\Form\Codemirror( 'block_content', htmlentities( $this->content, ENT_DISALLOWED, 'UTF-8', TRUE ), FALSE, array( 'tagSource' => \IPS\Http\Url::internal( "app=cms&module=pages&controller=blocks&do=loadTags" ) ), function( $val ) {
				if ( \IPS\Request::i()->block_editor == 'php' )
				{
					try
					{
						ob_start();
						@eval( $val );
						ob_get_clean();
					}
					catch ( \Exception $e )
					{
						throw new \DomainException( $e->getMessage() );
					}
				}
				
				if ( mb_strpos( $val, '{block="' . \IPS\Request::i()->block_key . '"}' ) !== FALSE )
				{
					throw new \DomainException('block_content_recursive_error');
				}
			}, NULL, NULL, 'block_content' ) );
		}

		if ( $block_type === 'plugin' and ! $this->id )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'block_save_as_template', FALSE, FALSE, array(
				'togglesOn' => array( 'block_save_as_template_name' )
			), NULL, NULL, NULL, 'block_save_as_template' ) );

			$form->add( new \IPS\Helpers\Form\Text( 'block_save_as_template_name', NULL, FALSE, array(), NULL, NULL, NULL, 'block_save_as_template_name' ) );
		}

		if ( $block_type === 'custom' )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'block_cache', ( $this->id ) ? $this->cache : FALSE, FALSE, array(), NULL, NULL, NULL, 'block_cache' ) );
		}

		$form->hiddenValues['block_type']      = $block_type;
		$form->hiddenValues['block_editor']    = $block_editor;
		$form->hiddenValues['block_plugin']    = $this->plugin;
		if ( $this->plugin_app )
		{
			$form->hiddenValues['block_plugin_app']= $this->plugin_app;
		}
		if ( $this->plugin_plugin )
		{
			$form->hiddenValues['block_plugin_plugin']= $this->plugin_plugin;
		}
		$form->hiddenValues['template_params'] = ( $this->id ) ? $this->template_params : '';

		/* If we are editing, we can save and reload */
		if( $this->id )
		{
			$form->canSaveAndReload = true;
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'templates/view.css', 'cms', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'blocks/form.css', 'cms', 'admin' ) );

		\IPS\Output::i()->globalControllers[]  = 'cms.admin.blocks.form';
		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_blocks.js', 'cms' ) );
		\IPS\Output::i()->title = ( $this->id ) ? \IPS\Member::loggedIn()->language()->addToStack( 'content_block_block_editing', FALSE, array( 'sprintf' => array( $this->_title ) ) ) : \IPS\Member::loggedIn()->language()->addToStack('content_block_block_add');
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		/* Claim Attachments - we need to adjust the temp key based on presence if this block existed or not */
		if ( \IPS\Request::i()->block_editor === 'editor' )
		{
			\IPS\File::claimAttachments( ( $this->id ) ? 'block-content-' . $this->id : 'block-content-new', $this->id );
		}
		
		$this->type		= \IPS\Request::i()->block_type;
		$values['type']	= $this->type;
		
		if ( ! $this->id )
		{
			$this->active = 1;
			$this->save();
		}

		$config = array();
		if( isset( $values['language_key'] ) )
		{
			$config['language_key'] = $values['language_key'];
		}

		if( isset( $values['block_name'] ) )
		{
			\IPS\Lang::saveCustom( 'cms', "content_block_name_" . $this->id, $values['block_name'] );
		}

		if( isset( $values['block_description'] ) )
		{
			\IPS\Lang::saveCustom( 'cms', "content_block_name_" . $this->id . '_desc', $values['block_description'] );

			unset ( $values['block_description'] );
		}

		if( isset( $values['block_key'] ) )
		{
			if ( ! $values['block_key'] )
			{
				if ( \is_array( $values['block_name'] ) )
				{
					reset( $values['block_name'] );
					$values['block_key'] = \IPS\Http\Url\Friendly::seoTitle( $values['block_name'][ key( $values['block_name'] ) ] );
				}
				else
				{
					$values['block_key'] = \IPS\Http\Url\Friendly::seoTitle( $values['block_name'] );
				}

				/* Now test it */
				try
				{
					$block = \IPS\cms\Blocks\Block::load( $values['block_key'], 'block_key');

					/* It's taken... */
					if ( $this->id != $block->id )
					{
						$values['block_key'] .= '_' . mt_rand();
					}
				}
				catch( \OutOfRangeException $ex )
				{
					/* Doesn't exist? Good! */
				}
			}
		}

		if ( \IPS\Request::i()->block_type === 'plugin' )
		{
			$values['plugin_app'] = \IPS\Request::i()->block_plugin_app;

			/* configure widget related values */
			if ( \is_callable( array( $this->widget(), 'preConfig' ) ) )
			{
				$values = $this->widget()->preConfig( $values );
			}

			/* Special advanced builder stuff */
			if ( \in_array( 'IPS\Widget\Builder', class_implements( $this->widget() ) ) )
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

			/* Store config */
			foreach( $values as $k => $v )
			{
				if ( ! \in_array( $k, array( 'block_name', 'block_key', 'block_description', 'block_category', 'block_template_id', 'block_template_use_how', 'block_content', 'block_save_as_template', 'block_save_as_template_name' ) ) )
				{
					if ( \is_array( $v ) )
					{
						$theValue = NULL;
						foreach( $v as $eachKey => $eachValue )
						{
							if ( !( $eachValue instanceof \IPS\Node\Model ) AND $eachValue instanceof \IPS\Patterns\ActiveRecord )
							{
								$column     = $eachValue::$databaseColumnId;
								$theValue[] = $eachValue->$column;
							}
							elseif( $eachValue instanceof \IPS\Node\Model )
							{
								$theValue[ $eachKey ] = $eachValue;
							}
							elseif ( $eachKey === 'start' or $eachKey === 'end' )
							{
								/* date ranges */
								$theValue[ $eachKey ] = $eachValue;
							}
							else
							{
								$theValue[ $eachKey ] = $eachValue;
							}
						}
						$v = $theValue;
					}
					else if ( !( $v instanceof \IPS\Node\Model ) AND $v instanceof \IPS\Patterns\ActiveRecord )
					{
						$column = $v::$databaseColumnId;
						$v      = $v->$column;
					}
					else if ( $v instanceof \IPS\Http\Url )
					{
						$v = (string) $v;
					}

					$config[ $k ] = $v;

					unset( $values[ $k ] );
				}
			}

			$values['plugin']			= \IPS\Request::i()->block_plugin;
			$values['plugin_config']	= json_encode( $config );

			/* Are we using the template as-is? */
			if ( $values['block_template_use_how'] === 'use' )
			{
				$values['content'] = null;

				/* Not using default? */
				if ( $values['block_template_id'] != '_default_' )
				{
					$values['template'] = $values['block_template_id'];
				}
				else
				{
					$values['template'] = 0;
				}
			}
			else
			{
				/* We're using a copy */
				if ( isset( $values['block_save_as_template'] ) AND $values['block_save_as_template'] )
				{
					$templateArray = array(
						'desc' 		   => null,
						'content' 	   => $values['block_content'],
						'location' 	   => 'block',
						'group' 	   => \IPS\Request::i()->block_plugin,
						'container'    => null,
						'rel_id' 	   => 0,
						'user_created' => 1,
						'user_edited'  => 0,
					);

					if ( $values['block_template_id'] == '_default_' )
					{
						/* Find it from the normal template system */
						$plugin = $this->widget();

						$location = $plugin->getTemplateLocation();

						$templateBits  = \IPS\Theme::master()->getRawTemplates( $location['app'], $location['location'], $location['group'], \IPS\Theme::RETURN_ALL );
						$templateBit   = $templateBits[ $location['app'] ][ $location['location'] ][ $location['group'] ][ $location['name'] ];

						$templateArray['key']		= 'template_' . $templateBit['template_name'] . '.' . mt_rand();
						$templateArray['title']		= str_replace( '-', '_', \IPS\Http\Url\Friendly::seoTitle( $values['block_save_as_template_name'] ? $values['block_save_as_template_name'] : $templateBit['template_name'] . '_' . \IPS\Member::loggedIn()->language()->get('copy_noun') ) );
						$templateArray['params']	= $templateBit['template_data'];
					}
					else
					{
						try
						{
							$template = \IPS\cms\Templates::load( $values['block_template_id'] );

							$templateArray['key']		= 'template_' . $template->name . '.' . mt_rand();
							$templateArray['title']		= str_replace( '-', '_', \IPS\Http\Url\Friendly::seoTitle( $values['block_save_as_template_name'] ? $values['block_save_as_template_name'] : $template->name . '_' . \IPS\Member::loggedIn()->language()->get('copy_noun') ) );
							$templateArray['params']	= $template->params;
						}
						catch( \OutOfRangeException $ex )
						{
							throw new \LogicException('cms_error_no_template_found');
						}
					}

					/* Make sure template name is unique within the group */
					if( \IPS\Db::i()->select( 'COUNT(*)', 'cms_templates', array( 'template_title=? and template_location=? and template_group=?', $templateArray['title'], $templateArray['location'], $templateArray['group'] ) )->first() )
					{
						$templateArray['title'] = $templateArray['title'] . '_' . time();
					}

					/* Save */
					$newTemplate = \IPS\cms\Templates::add( $templateArray );

					$values['content']  = null;
					$values['template'] = $newTemplate->key;
				}
				else
				{
					/* Just use it this once */
					$values['content']  = $values['block_content'];
					$values['template'] = 0;
				}
			}
		}
		else if( isset( $values['block_content'] ) )
		{
			$values['template'] = 0;
			if ( \IPS\Request::i()->block_editor === 'editor' )
			{
				\IPS\Lang::saveCustom( 'cms', "cms_block_content_{$this->id}", $values['block_content'] );
				$values['block_content'] = NULL;
			}
		}

		if ( isset( $values['block_category'] ) AND ( ! empty( $values['block_category'] ) OR $values['block_category'] === 0 ) )
		{
			$values['block_category'] = ( $values['block_category'] === 0 ) ? 0 : $values['block_category'];

			if( isset( $values['block_category'] ) AND $values['block_category'] instanceof \IPS\Node\Model )
			{
				$values['block_category']	= $values['block_category']->_id;
			}
		}

		if( isset( \IPS\Request::i()->template_params ) )
		{
			$values['template_params'] = \IPS\Request::i()->template_params;
		}

		/* Config */
		if( isset( \IPS\Request::i()->block_editor ) )
		{
			$this->setConfig( 'editor', \IPS\Request::i()->block_editor );
		}

		foreach( array( 'block_name', 'block_description', 'block_save_as_template', 'block_template_id', 'block_template_use_how', 'block_save_as_template_name', 'block_editor', 'block_plugin_app', 'block_plugin' ) as $field )
		{
			if ( array_key_exists( $field, $values ) )
			{
				unset( $values[ $field ] );
			}
		}

		return $values;
	}

	/**
	 * Save data
	 *
	 * @return void
	 */
	public function save()
	{
		if ( $this->_config !== NULL )
		{
			$this->config = json_encode( $this->_config );
		}

		if ( $this->id )
		{
			static::deleteCompiled( $this->id );
			\IPS\cms\Widget::deleteCachesForBlocks( $this->key );
		}

		parent::save();
	}

	/**
	 * Returns the widget object associated with this custom block
	 *
	 * @return \IPS\Widget
	 */
	public function widget()
	{
		if ( $this->type === 'plugin' AND $this->plugin )
		{
			if ( mb_substr( $this->plugin, 0, 8 ) === 'db_feed_' )
			{
				$this->plugin = 'RecordFeed';
			}

			if ( empty( $this->widgetLoaded[ $this->orientation ] ) )
			{
				$this->widgetLoaded[ $this->orientation ]= \IPS\Widget::load( $this->plugin_app ? \IPS\Application::load( $this->plugin_app ) : \IPS\Plugin::load( $this->plugin_plugin ), $this->plugin, mt_rand(), $this->_plugin_config, NULL, $this->orientation );
			}

			return $this->widgetLoaded[ $this->orientation ];
		}

		throw new \OutOfRangeException;
	}

	/**
	 * [Node] Does the currently logged in user have permission to copy this node?
	 *
	 * @return	bool
	 */
	public function canCopy()
	{
		return FALSE;
	}
}
