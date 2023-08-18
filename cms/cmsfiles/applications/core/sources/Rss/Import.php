<?php
/**
 * @brief		Feed Import Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		4 Fed 2014
 */

namespace IPS\core\Rss;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Feed Import Node
 */
class _Import extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_rss_import';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'rss_import_';
				
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'rss_import';
	
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
		'app'		=> 'core',
		'module'	=> 'applications',
		'prefix'	=> 'rss_'
	);


	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->settings = array();
	}

	/**
	 * [Node] Get title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		if ( $this->class == 'IPS\\blog\\Entry' )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'blogs_blog_' . $this->node_id );
		}

		return $this->title;
	}
	
	/**
	 * [Node] Get description
	 *
	 * @return	string
	 */
	protected function get__description()
	{
		return $this->url;
	}
	
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return $this->enabled;
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		if ( $enabled )
		{
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'rssimport' ) );
		}
		$this->enabled = $enabled;
	}

	/**
	 * [Node] Get Node Icon
	 *
	 * @return	string
	 */
	protected function get__icon()
	{
		return $this->_application->_icon;
	}

	/**
	 * Get the class for this RSS entry
	 *
	 * @return \IPS\Content
	 */
	public function get__class()
	{
		$class = $this->class;
		return new $class;
	}

	/**
	 * Get the application for this RSS entry
	 *
	 * @return \IPS\Application
	 */
	public function get__application()
	{
		$class = $this->_class;
		return \IPS\Application::load( $class::$application );
	}

	/**
	 * Get the URL for where this imports into
	 *
	 * @return \IPS\Http\Url|Null
	 */
	public function get__importedIntoUrl()
	{
		try
		{
			$class = $this->_class;
			$containerClass = $class::$containerNodeClass;
			return $containerClass::load( $this->node_id )->url();
		}
		catch( \Exception $ex )
		{
			return NULL;
		}
	}

	/**
	 * Get the extension for this RSS entry
	 *
	 * @return \IPS\Application
	 */
	public function get__extension()
	{
		$classname = 'IPS\\' . $this->_application->directory . '\extensions\core\RssImport\RssImport';
		return new $classname;
	}

	/**
	 * Set the "settings" field
	 *
	 * @param string|array $value	Value
	 * @return void
	 */
	public function set_settings( $value )
	{
		$this->_data['settings'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}

	/**
	 * Get the "settings" field
	 *
	 * @return array
	 */
	public function get_settings()
	{
		return ( \is_array( $this->_data['settings'] ) ) ? $this->_data['settings'] : json_decode( $this->_data['settings'], TRUE );
	}
	
	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	 	array(
	 		array(
	 			'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	 			'title'	=> 'foo',		// Language key to use for button's title parameter
	 			'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	 			'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	 		),
	 		...							// Additional buttons
	 	);
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction('rss_run') )
		{
			$buttons = array_merge( array( 'run' => array(
				'icon'	=> 'refresh',
				'title'	=> 'rss_run',
				'link'	=> $url->setQueryString( array( 'do' => 'run', 'id' => $this->_id ) )->csrf()
			) ), $buttons );
		}
		
		return $buttons;
	}
	
	/**
	 * [Node] Edit Form (add form is a Wizard in core/modules/admin/applications/rss.php)
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$class = $this->_class;

		$form->addHeader('rss_import_url');
		$form->add( new \IPS\Helpers\Form\Url( 'rss_import_url', $this->url, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'rss_import_auth_user', $this->auth_user ) );
		$form->add( new \IPS\Helpers\Form\Password( 'rss_import_auth_pass', $this->auth_pass ) );
		$form->addHeader('rss_import_details');
		$form->add( new \IPS\Helpers\Form\Node( 'rss_import_node_id', $this->node_id, TRUE, $this->_extension->nodeSelectorOptions( $this ) ) );
		$form->add( new \IPS\Helpers\Form\Member( 'rss_import_member', \IPS\Member::load( $this->member ), TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'rss_import_showlink', $this->showlink ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'rss_import_enclosures', $this->enclosures, FALSE, array( 'options' => array(
			'import'	=> "rss_import_enclosures_import",
			'hotlink'	=> "rss_import_enclosures_hotlink",
			'ignore'	=> "rss_import_enclosures_ignore",
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'rss_import_topic_pre', $this->topic_pre, FALSE, array( 'trim' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'rss_import_auto_follow', $this->auto_follow, FALSE, array(), NULL, NULL, NULL, 'rss_import_auto_follow' ) );
		$this->_extension->form( $form, $this );

		\IPS\Member::loggedIn()->language()->words['rss_import_auto_follow'] = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_auto_follow_lang', FALSE, array( 'sprintf' => array( $class::_definiteArticle() ) ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( isset( $values['rss_import_url'] ) )
		{
			try
			{
				$request = $values['rss_import_url']->request();
				
				if ( $values['rss_import_auth_user'] or $values['rss_import_auth_pass'] )
				{
					$request = $request->login( $values['rss_import_auth_user'], $values['rss_import_auth_pass'] );
				}
				
				$response = $request->get();
				
				if ( $response->httpResponseCode == 401 )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_auth' ) );
				}
				
				$response = $response->decodeXml();
				if ( !( $response instanceof \IPS\Xml\Rss ) and !( $response instanceof \IPS\Xml\Atom ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' ) );
				}
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad' ) );
			}
			catch( \RuntimeException $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' ) );
			}
			catch ( \ErrorException $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' ) );
			}

			$values['title'] = (string) $response->channel->title;

			/* Reset the has_enclosures flag */
			foreach( $response->articles() as $article )
			{
				if ( isset( $article['enclosure'] ) and isset( $article['enclosure']['url'] ) )
				{
					$this->has_enclosures = true;
					break;
				}
			}
		}
		
		if( isset( $values['rss_import_node_id'] ) )
		{
			$values['node_id'] = $values['rss_import_node_id']->_id;
			unset( $values['rss_import_node_id'] );
		}

		if( isset( $values['rss_import_member'] ) )
		{
			$values['member'] = $values['rss_import_member']->member_id;
			unset( $values['rss_import_member'] );
		}

		if( isset( $values['rss_import_auto_follow'] ) )
		{
			$values['auto_follow'] =$values['rss_import_auto_follow'];
			unset( $values['rss_import_auto_follow'] );
		}

		$this->enclosures = $values['rss_import_enclosures'];
		unset( $values['rss_import_enclosures'] );

		/* If we are ignoring enclosures, reset the has_enclosures flag */
		if( $this->enclosures == 'ignore' )
		{
			$this->has_enclosures = false;
		}

		if ( $settings = $this->_extension->saveForm( $values, $this ) )
		{
			$values['settings'] = $settings;
		}
		
		return $values;
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		$this->run();
	}

	/**
	 * Run Import
	 *
	 * @return	void
	 * @throws	\IPS\Http\Url\Exception
	 */
	public function run()
	{
		/* Skip this if the member is restricted from posting */
		if( \IPS\Member::load( $this->member )->restrict_post or \IPS\Member::load( $this->member )->members_bitoptions['unacknowledged_warnings'] )
		{
			return;
		}

		$previouslyImportedGuids = iterator_to_array( \IPS\Db::i()->select( 'rss_imported_guid', 'core_rss_imported', array( 'rss_imported_import_id=?', $this->id ) ) );
		
		$request = \IPS\Http\Url::external( $this->url )->request();
		if ( $this->auth_user or $this->auth_pass )
		{
			$request = $request->login( $this->auth_user, $this->auth_pass );
		}
		$request = $request->get();

		$class = $this->_class;
		$containerClass = $class::$containerNodeClass;
		$container = $containerClass::load( $this->node_id );
		$member = \IPS\Member::load( $this->member );

		$i = 0;
		/* Enclosures can cause a lot more processing time so we need to import less */
		$max = ( $this->has_enclosures ) ? 5 : 10;

		$inserts = array();
		$request = $request->decodeXml();

		if( !( $request instanceof \IPS\Xml\Rss1 ) AND !( $request instanceof \IPS\Xml\Rss ) AND !( $request instanceof \IPS\Xml\Atom ) )
		{
			throw new \RuntimeException( 'rss_import_invalid' );
		}
		
		$post = NULL;
		foreach ( $request->articles( $this->id ) as $guid => $article )
		{
			if ( !\in_array( $guid, $previouslyImportedGuids ) )
			{
				/* Don't post future date content - this breaks things like marking as read */
				$timeNow = \IPS\DateTime::create();
				if( $article['date'] > $timeNow )
				{
					$article['date'] = \IPS\DateTime::create();
				}

				$article['guid'] = $guid;

				$readMoreLink = '';
				if ( $article['link'] and $this->showlink )
				{
					$rel = array();

					if( \IPS\Settings::i()->posts_add_nofollow )
					{
						$rel['nofollow'] = 'nofollow';
					}

					if( \IPS\Settings::i()->links_external )
					{
						$rel['external'] = 'external';
					}

					$linkRelPart = '';
					if ( \count( $rel ) )
					{
						$linkRelPart = 'rel="' .  implode( ' ', $rel ) . '"';
					}

					$readMoreLink = "<p><a href='{$article['link']}' {$linkRelPart}>{$this->showlink}</a></p>";
				}

				/* Parse the article body now, in case we need to add attachments to it later */
				$articleContent = \IPS\Text\Parser::parseStatic( $article['content'] . $readMoreLink, TRUE, NULL, $member, $this->_extension->fileStorage, TRUE, !(bool) $member->group['g_dohtml'] );

				$imageHtml = '';
				if ( $this->enclosures != 'ignore' and isset( $article['enclosure'] ) and isset( $article['enclosure']['url'] ) and isset( $article['enclosure']['length'] ) and isset( $article['enclosure']['type'] ) )
				{
					/* Limit imports to around 2mb otherwise timeouts and memory issues can occur when processing with GD */
					if ( mb_substr( $article['enclosure']['type'], 0, 6 ) == 'image/' and ( $this->enclosures == 'hotlink' or $article['enclosure']['length'] <= 1572864 or \IPS\Settings::i()->image_suite == 'imagemagick' ) )
					{
						/* Are we remotely linking to the image? */
						if( $this->enclosures == 'hotlink' and !$this->_enclosureEmbedded( $article['enclosure']['url'], $articleContent ) )
						{
							$imageName = basename( parse_url( $article['enclosure']['url'], PHP_URL_PATH ) );
							$imageHtml = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->linkedImage( $article['enclosure']['url'], $imageName ?: 'image' );
						}
						elseif( $this->enclosures == 'import' )
						{
							try
							{
								$response = \IPS\Http\Url::external( $article['enclosure']['url'] )->request()->get();

								$match = FALSE;
								$contentType = ( isset( $response->httpHeaders['Content-Type'] ) ) ? $response->httpHeaders['Content-Type'] : ( ( isset( $response->httpHeaders['content-type'] ) ) ? $response->httpHeaders['content-type'] : NULL );
								if ( $contentType )
								{
									foreach ( \IPS\Image::$imageMimes as $mime )
									{
										if ( preg_match( '/^' . str_replace( '~~', '.+', preg_quote( str_replace( '*', '~~', $mime ), '/' ) ) . '$/i', $contentType ) )
										{
											$match = TRUE;
											break;
										}
									}
								}

								/* Does it match? */
								if ( $match )
								{
									$processAttachment = TRUE;
									\IPS\Image::$exifEnabled = FALSE;
									if ( method_exists( $this->_extension, 'processEnclosure' ) )
									{
										if ( $this->_extension->processEnclosure( $this, $response, $article ) !== FALSE )
										{
											$processAttachment = FALSE;
										}
									}

									if ( $processAttachment )
									{
										try
										{
											$image = \IPS\Image::create( $response );
											$maxImageSizes = NULL;

											if ( \IPS\Settings::i()->attachment_resample_size )
											{
												$maxImageSizes = explode( 'x', \IPS\Settings::i()->attachment_resample_size );

												/* If the dimensions were 0x0 then correct... */
												if ( !$maxImageSizes[0] or !$maxImageSizes[1] )
												{
													$maxImageSizes = NULL;
												}
											}
											if ( $maxImageSizes !== NULL )
											{
												$image->resizeToMax( $maxImageSizes[0], $maxImageSizes[1] );
											}

											$newFile = \IPS\File::create( 'core_Attachment', 'rssImage-' . $guid . '.' . $image->type, (string)$image );
											$attachment = $newFile->makeAttachment( md5( $guid . ':' . session_id() ), $member );
											$article['attachment'] = $attachment;

											/* If the image is already in the body of the article, replace it with our embed instead */
											if( !$this->_enclosureReplace( $article['enclosure']['url'], $attachment, $articleContent ) )
											{
												$imageHtml = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedImage( $attachment['attach_location'], $attachment['attach_thumb_location'] ? $attachment['attach_thumb_location'] : $attachment['attach_location'], $attachment['attach_file'], $attachment['attach_id'], $attachment['attach_thumb_location'] ? $attachment['attach_img_width'] : $attachment['attach_location'] );
											}
										}
										catch ( \Exception $e ){}
									}
								}
							}
							catch ( \IPS\Http\Request\Exception $e )
							{
							}
						}
					}
				}

				$content = $imageHtml . $articleContent;
				$item = $this->_extension->create( $this, $article, $container, $content );
				$idColumn = $class::$databaseColumnId;
				$followArea = mb_strtolower( mb_substr( \get_class( $class ), mb_strrpos( \get_class( $class ), '\\' ) + 1 ) );

				if ( $item )
				{
					\IPS\Db::i()->insert( 'core_rss_imported', array(
						'rss_imported_guid' => $guid,
						'rss_imported_content_id' => $item->$idColumn,
						'rss_imported_import_id' => $this->id
					), TRUE );

					if ( $this->auto_follow )
					{
						\IPS\Db::i()->insert( 'core_follow', array(
							'follow_id' => md5( $class::$application . ';' . $followArea . ';' . $item->$idColumn . ';' . $member->member_id ),
							'follow_app' => $class::$application,
							'follow_area' => $followArea,
							'follow_rel_id' => $item->$idColumn,
							'follow_member_id' => $member->member_id,
							'follow_is_anon' => 0,
							'follow_added' => time(),
							'follow_notify_do' => 1,
							'follow_notify_freq' => 'immediate',
							'follow_visible' => 1,
						) );
					}

					/* Re-index to pick up any changes to hidden, etc */
					if ( $item instanceof \IPS\Content\Searchable )
					{
						\IPS\Content\Search\Index::i()->index( $item );
					}
				}
				else
				{
					/* Something went wrong, but log the guid so it doesn't get stuck in a loop forever */
					\IPS\Db::i()->insert( 'core_rss_imported', array(
						'rss_imported_guid' => $guid,
						'rss_imported_content_id' => 0,
						'rss_imported_import_id' => $this->id
					), TRUE );
				}

				$i++;
				
				if ( $i >= $max )
				{
					break;
				}
			}
		}

		$container->setLastComment();
		$container->save();

		$this->last_import = time();
		$this->save();
	}

	/**
	 * Check if the enclosure URL is already embedded in the content we are parsing and if so, replace it
	 *
	 * @param	string	$url		Enclosure URL
	 * @param	array	$attachment	Attachment details
	 * @param	string	$content	Body of article
	 * @return	bool
	 */
	protected function _enclosureReplace( $url, $attachment, &$content )
	{
		/* Load source */
		$source = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
		$source->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $content ) );
		
		/* Look for the image URL and replace it with the attachment if found */
		$replaced = FALSE;

		$contentImages = $source->getElementsByTagName( 'img' );
		foreach( $contentImages as $element )
		{
			$srcAttribute = $element->hasAttribute('data-src') ? 'data-src' : 'src';
			if ( $element->hasAttribute( $srcAttribute ) AND $element->getAttribute( $srcAttribute ) == $url )
			{
				$replaced = true;
				
				$element->setAttribute( 'data-fileid', $attachment['attach_id'] );
				$element->setAttribute( $srcAttribute, '{fileStore.core_Attachment}/' . ( $attachment['attach_thumb_location'] ? $attachment['attach_thumb_location'] : $attachment['attach_location'] ) );
			}
		}

		if( $replaced )
		{
			$content = \IPS\Text\DOMParser::getDocumentBodyContents( $source );
			$content = \IPS\Text\Parser::replaceFileStoreTags( $content );

			/* Set lazy loading and wrapping link on the content */
			if( $rebuilt = \IPS\Text\Parser::rebuildAttachmentUrls( $content ) )
			{
				$content = $rebuilt;
			}

			$content = \IPS\Text\Parser::parseLazyLoad( $content, \IPS\Settings::i()->lazy_load_enabled );

			/* And then finally, we need to reparse to make sure our replacement tags are in place */
			$source = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$source->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $content ) );

			$tags = array( 'a', 'img' );

			foreach( $tags as $tag )
			{
				$elements = $source->getElementsByTagName( $tag );
				foreach( $elements as $element )
				{
					/* Anything which has a URL may need swapping out */
					foreach ( array( 'href', 'src', 'data-src', 'data-video-src', 'data-embed-src', 'srcset', 'data-ipshover-target', 'data-fileid', 'cite', 'action', 'longdesc', 'usemap', 'poster' ) as $attribute )
					{
						if ( $element->hasAttribute( $attribute ) )
						{				
							if ( preg_match( '#^(https?:)?//(' . preg_quote( rtrim( str_replace( array( 'http://', 'https://' ), '', \IPS\Settings::i()->base_url ), '/' ), '#' ) . ')/(.+?)$#', $element->getAttribute( $attribute ), $matches ) )
							{
								$element->setAttribute( $attribute, '%7B___base_url___%7D/' . $matches[3] );
							}
						}
					}
					foreach ( array( 'srcset', 'style' ) as $attribute )
					{
						if ( $element->hasAttribute( $attribute ) )
						{
							if ( mb_strpos( $element->getAttribute( $attribute ), \IPS\Settings::i()->base_url ) )
							{
								$element->setAttribute( $attribute, str_replace( \IPS\Settings::i()->base_url, '%7B___base_url___%7D/', $element->getAttribute( $attribute ) ) );
							}
						}
					}
				}
			}


			$content = \IPS\Text\DOMParser::getDocumentBodyContents( $source );

			/* Replace file storage tags */
			$content = preg_replace( '/&lt;fileStore\.([\d\w\_]+?)&gt;/i', '<fileStore.$1>', $content );
			$content = str_replace( array( '&lt;___base_url___&gt;', '%7B___base_url___%7D' ), '<___base_url___>', $content );
		}

		return $replaced;
	}

	/**
	 * Check if the enclosure URL is already embedded in the content we are parsing
	 *
	 * @param	string	$url		Enclosure URL
	 * @param	string	$content	Body of article
	 * @return	bool
	 */
	protected function _enclosureEmbedded( $url, $content )
	{
		/* Load source */
		$source = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
		$source->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $content ) );
		
		/* Look for the image URL */
		$contentImages = $source->getElementsByTagName( 'img' );
		foreach( $contentImages as $element )
		{
			if ( $element->hasAttribute('src') AND $element->getAttribute('src') == $url )
			{
				return true;
			}
		}

		return false;
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'core_rss_imported', array( "rss_imported_import_id=?", $this->id ) );		
		return parent::delete();
	}

	/**
	 * Search
	 *
	 * @param	string		$column	Column to search
	 * @param	string		$query	Search query
	 * @param	string|null	$order	Column to order by
	 * @param	mixed		$where	Where clause
	 * @return	array
	 */
	public static function search( $column, $query, $order=NULL, $where=array() )
	{	
		if ( $column === '_title' )
		{
			$column = 'rss_import_title';
		}
		if ( $order === '_title' )
		{
			$order = 'rss_import_title';
		}
		return parent::search( $column, $query, $order, $where );
	}
}