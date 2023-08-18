<?php
/**
 * @brief		Feeds
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		04 Feb 2014
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * rss
 */
class _rss extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\Rss\Import';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'rss_manage' );
		parent::execute();
	}

	/**
	 * Get Root Rows
	 *
	 * @return	array
	 */
	public function _getRoots()
	{
		$nodeClass = $this->nodeClass;
		$rows = array();

		$classes = array();
		foreach( \IPS\Application::allExtensions( 'core', 'RssImport' ) as $key => $class )
		{
			foreach( $class->classes as $_class )
			{
				$classes[ $_class ] = $class;
			}
		}

		foreach( $nodeClass::roots( NULL ) as $node )
		{
			/* Don't show user-added blog entries */
			if ( isset( $classes[ $node->class ] ) and $classes[ $node->class ]->showInAdminCp( $node->class ) )
			{
				$rows[$node->_id] = $this->_getRow( $node );
			}
		}

		return $rows;
	}

	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$buttons = parent::_getRootButtons();

		if ( isset( \IPS\Output::i()->sidebar['actions']['add'] ) )
		{
			\IPS\Output::i()->sidebar['actions']['add']['link'] = \IPS\Output::i()->sidebar['actions']['add']['link']->setQueryString( '_new', TRUE );
		}
		
		return $buttons;
	}

	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		return \IPS\Theme::i()->getTemplate( 'feeds' )->importRow( $node );
	}

	/**
	 * Add/Edit Form
	 *
	 * @return void
	 */
	protected function form()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('rss_import_untrusted_url'), 'warning' );

		$nodeClass = $this->nodeClass;

		if ( !\IPS\Request::i()->id and $nodeClass::canAddRoot() )
		{
			\IPS\Output::i()->output .= (string) new \IPS\Helpers\Wizard( array(
				'rss_import_url' => function( $data )
				{
					$form = new \IPS\Helpers\Form( 'form', 'continue' );
					$form->add( new \IPS\Helpers\Form\Url( 'rss_import_url', NULL, TRUE ) );
					$form->add( new \IPS\Helpers\Form\Text( 'rss_import_auth_user' ) );
					$form->add( new \IPS\Helpers\Form\Password( 'rss_import_auth_pass' ) );

					if ( $values = $form->values() )
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
								$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_auth' );
								return $form;
							}
							
							$response = $response->decodeXml();
							if ( !( $response instanceof \IPS\Xml\Rss ) and !( $response instanceof \IPS\Xml\Atom ) )
							{
								$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' );
								return $form;
							}
						}
						catch ( \IPS\Http\Request\Exception $e )
						{
							$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad' );
							return $form;
						}
						catch ( \Exception $e )
						{
							$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' );
							return $form;
						}
						
						return $values;
					}
					return $form;
				},
				'rss_import_preview' => function( $data )
				{
					if ( isset( \IPS\Request::i()->continue ) )
					{
						return $data;
					}
					
					$request = \IPS\Http\Url::external( $data['rss_import_url'] )->request();
					if ( $data['rss_import_auth_user'] or $data['rss_import_auth_pass'] )
					{
						$request = $request->login( $data['rss_import_auth_user'], $data['rss_import_auth_pass'] );
					}

					$preview = array();
					foreach( $request->get()->decodeXml()->articles() as $article )
					{
						if ( \count( $preview ) > 9 )
						{
							break;
						}

						$preview[] = $article;
					}
					return \IPS\Theme::i()->getTemplate( 'feeds' )->importPreview( $preview );
				},
				'rss_import_app' => function ( $data ) {
					$form = new \IPS\Helpers\Form( 'form', 'continue' );
					$options = array();

					foreach( \IPS\Application::allExtensions( 'core', 'RssImport' ) as $key => $class )
					{
						$options = array_merge( $options, $class->availableOptions() );
					}

					$form->add( new \IPS\Helpers\Form\Radio( 'rss_import_app_areas', NULL, FALSE, array( 'options' => $options ) ) );

					if ( $values = $form->values() )
					{
						return $values;
					}

					return $form;
				},
				'rss_import_details' => function ( $data )
				{
					$import = new \IPS\core\Rss\Import;
					$rss_import_app_areas = isset( \IPS\Request::i()->rss_import_app_areas ) ? \IPS\Request::i()->rss_import_app_areas : $data['rss_import_app_areas'];
					$import->class = $rss_import_app_areas;
					$extension = $import->_extension;

					$request = \IPS\Http\Url::external( $data['rss_import_url'] )->request();
					if ( $data['rss_import_auth_user'] or $data['rss_import_auth_pass'] )
					{
						$request = $request->login( $data['rss_import_auth_user'], $data['rss_import_auth_pass'] );
					}

					foreach( $request->get()->decodeXml()->articles() as $article )
					{
						if ( isset( $article['enclosure'] ) and isset( $article['enclosure']['url'] ) )
						{
							$import->has_enclosures = true;
							break;
						}
					}

					$form = new \IPS\Helpers\Form;

					if ( ! empty( $data['rss_import_app_areas'] ) )
					{
						$form->hiddenValues['rss_import_app_areas'] = $data['rss_import_app_areas'];
					}

					$form->add( new \IPS\Helpers\Form\Node( 'rss_import_node_id', NULL, TRUE, $extension->nodeSelectorOptions( $import ) ) );
					$form->add( new \IPS\Helpers\Form\Member( 'rss_import_member', \IPS\Member::loggedIn(), TRUE, array(), function( $val ){
						if( !\is_array( $val ) )
						{
							$val = array( $val );
						}

						foreach( $val as $member )
						{
							if( $member instanceof \IPS\Member )
							{
								if( !$member->member_id )
								{
									throw new \InvalidArgumentException( 'form_member_bad' );
								}
							}
							else
							{
								$testMember = \IPS\Member::load( $member );

								if( !$testMember->member_id )
								{
									throw new \InvalidArgumentException( 'form_member_bad' );
								}
							}
						}
					} ) );
					$form->add( new \IPS\Helpers\Form\Text( 'rss_import_showlink',  \IPS\Member::loggedIn()->language()->addToStack('rss_import_showlink_default') ) );
					$form->add( new \IPS\Helpers\Form\Radio( 'rss_import_enclosures', 'import', FALSE, array( 'options' => array(
						'import'	=> "rss_import_enclosures_import",
						'hotlink'	=> "rss_import_enclosures_hotlink",
						'ignore'	=> "rss_import_enclosures_ignore",
					) ) ) );
					$form->add( new \IPS\Helpers\Form\Text( 'rss_import_topic_pre', NULL, FALSE, array( 'trim' => FALSE ) ) );
					$form->add( new \IPS\Helpers\Form\YesNo( 'rss_import_auto_follow', NULL, FALSE, array(), NULL, NULL, NULL, 'rss_import_auto_follow' ) );
					$extension->form( $form, $import );

					if ( $values = $form->values() )
					{
						$import->enabled = TRUE;
						$import->title = $request->get()->decodeXml()->title();
						$import->url = (string) $data['rss_import_url'];
						$import->node_id = $values['rss_import_node_id']->_id;
						$import->member = $values['rss_import_member']->member_id;
						$import->showlink = $values['rss_import_showlink'];
						$import->topic_pre = $values['rss_import_topic_pre'];
						$import->auth_user = ( $data['rss_import_auth_user'] or $data['rss_import_auth_pass'] ) ? $data['rss_import_auth_user'] : NULL;
						$import->auth_pass = ( $data['rss_import_auth_user'] or $data['rss_import_auth_pass'] ) ? $data['rss_import_auth_pass'] : NULL;
						$import->auto_follow = $values['rss_import_auto_follow'];
						$import->enclosures = $values['rss_import_enclosures'];

						/* If we are ignoring enclosures, reset the has_enclosures flag */
						if( $import->enclosures == 'ignore' )
						{
							$import->has_enclosures = false;
						}

						if ( $settings = $extension->saveForm( $values, $import ) )
						{
							$import->settings = $settings;
						}

						$import->save();
						try
						{
							$import->run();
						}
						catch( \Exception $e )
						{
							\IPS\Output::i()->error( 'rss_run_error', '3F181/3', 500, '' );
						}
						\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'rssimport' ) );
						\IPS\Session::i()->log( 'acplog__node_created', array( (string) $import->title => TRUE, (string) $import->title => FALSE ) );
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=applications&controller=rss' ) );
					}
					
					return $form;
				}
			), \IPS\Http\Url::internal('app=core&module=applications&controller=rss&do=form') );
		}
		else
		{
			return parent::form();
		}
	}
	
	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'rss_run' );
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$feed = \IPS\core\Rss\Import::load( \IPS\Request::i()->id );
			$feed->run();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F181/1', 404, '' );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'rss_run_error', '3F181/2', 500, '' );
		}
		
		\IPS\Session::i()->log( 'acplog__rss_ran', array( $feed->title => FALSE ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=applications&controller=rss' ) );
	}
}