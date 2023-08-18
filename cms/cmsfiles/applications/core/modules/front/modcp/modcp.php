<?php
/**
 * @brief		Moderator Control Panel
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Oct 2013
 */

namespace IPS\core\modules\front\modcp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Control Panel
 */
class _modcp extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Is this for displaying "content"? Affects if advertisements may be shown
	 */
	public $isContentPage = FALSE;

	/**
	 * Dispatcher looks for methods to match do param, which may exist in the extension files, so this method
	 * prevents dispatcher throwing a 2S106/1 and allows the extensions to use the values
	 *
	 * @param	string	$method	Desired method
	 * @param	array	$args	Arguments
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		if ( \IPS\Settings::i()->core_datalayer_enabled )
		{
			\IPS\core\DataLayer::i()->addContextProperty( 'community_area', \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->addToStack('modcp'), true );
		}

		if ( isset( \IPS\Request::i()->do ) )
		{
			$activeTab	= \IPS\Request::i()->tab ?: 'overview';
			foreach ( \IPS\Application::allExtensions( 'core', 'ModCp', TRUE ) as $key => $extension )
			{
				if( method_exists( $extension, 'getTab' ) and mb_strtolower( $extension->getTab() ) == mb_strtolower( $activeTab ) )
				{
					if ( method_exists( $extension, \IPS\Request::i()->do ) )
					{
						return $this->manage();
					}
				}
			}			
		}
		
		/* Still here? */
		\IPS\Output::i()->error( 'page_not_found', '2C139/5', 404, '' );
	}
	
	/**
	 * Moderator Control Panel
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Check we're not a guest */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S194/1', 403, '' );
		}

		/* Make sure we are a moderator */
		if ( \IPS\Member::loggedIn()->modPermission() === FALSE )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S194/2', 403, '' );
		}
		
		/* Set up the tabs */
		$activeTab	= \IPS\Request::i()->tab ?: 'overview';
		$tabs		= array( 'reports' => array(), 'approval' => array() );
		$content	= '';

		$approvalQueueCount = 0;

		foreach ( \IPS\Application::allExtensions( 'core', 'ModCp', TRUE ) as $key => $extension )
		{
			if( method_exists( $extension, 'getTab' ) )
			{
				$tab = $extension->getTab();
								
				if ( $tab )
				{
					$tabs[ $tab ][] = $key;
				}

				/* Get the count for the approval queue from the extension */
				if( $tab == 'approval' )
				{
					$approvalQueueCount = $extension->getApprovalQueueCount( false );
				}
			}

			if( mb_strtolower( $extension->getTab() ) == mb_strtolower( $activeTab ) )
			{
				$method = ( \IPS\Request::i()->action and preg_match( '/^[a-zA-Z\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', \IPS\Request::i()->action ) ) ? \IPS\Request::i()->action : 'manage';

				if ( $method !== 'getTab' AND ( method_exists( $extension, $method ) or method_exists( $extension, '__call' ) ) )
				{
					$content = $extension->$method();
					if ( !$content )
					{
						$content = \IPS\Output::i()->output;
					}
				}
			}
		}
		$tabs = array_filter( $tabs, 'count' );
		
		/* Got a page? */
		if ( !$content )
		{
			foreach ( $tabs as $k => $data )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab={$k}", 'front', "modcp_{$k}" ) );
			}
		}
		
		/* Content Types */
		$types = $this->_getContentTypes();
		
		/* Display */
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/modcp.css' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/modcp_responsive.css' ) );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_modcp.js', 'core' ) );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $content;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'modcp' )->template( $content, $tabs, $activeTab, $types, $approvalQueueCount );
		}
	}

	/**
	 * Get hidden content types
	 *
	 * @return	array
	 */
	protected function _getContentTypes(): array
	{
		$types = array();
		foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
		{
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
			{
				if ( \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_content' ) or \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_' . $class::$title ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ) ] = $class;
				}
			}

			if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) and $class::supportsComments( \IPS\Member::loggedIn() ) and \in_array( 'IPS\Content\Hideable', class_implements( $class::$commentClass ) ) )
			{
				$commentClass = $class::$commentClass;
				if ( \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_content' ) or \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_' . $commentClass::$title ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $commentClass, 4 ) ) ) ] = $commentClass;
				}
			}

			if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) and $class::supportsReviews( \IPS\Member::loggedIn() ) and \in_array( 'IPS\Content\Hideable', class_implements( $class::$reviewClass ) ) )
			{
				$reviewClass = $class::$reviewClass;
				if ( \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_content' ) or \IPS\Member::loggedIn()->modPermission( 'can_view_hidden_' . $reviewClass::$title ) )
				{
					$types[ $class::$application . '_' . $class::$module ][ mb_strtolower( str_replace( '\\', '_', mb_substr( $reviewClass, 4 ) ) ) ] = $reviewClass;
				}
			}
		}

		return $types;
	}
}