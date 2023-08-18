<?php
/**
 * @brief		Community Enhancements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancements
 */
class _enhancements extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief Enhancements plugins
	 */
	protected $enhancements = array();
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'enhancements_manage' );
		$this->enhancements = \IPS\Application::allExtensions( 'core', 'CommunityEnhancements' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Collect the enhancements into an array */
		$rows	= array();
				
		foreach( $this->enhancements as $key => $class )
		{
			if ( !method_exists( $class, 'isAvailable' ) or $class::isAvailable() )
			{
				$rows[ (int) $class->ips ][ $key ]	= array(
					'title'			=> "enhancements__{$key}",
					'description'	=> "enhancements__{$key}_desc",
					'app'			=> mb_substr( $key, 0, mb_strpos( $key, '_' ) ),
					'icon'			=> $class->icon,
					'enabled'		=> $class->enabled,
					'config'		=> $class->hasOptions ? TRUE : FALSE
				);
			}
		}
		
		/* Display */
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/enhancements.css', 'core', 'admin' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_applications_enhancements');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'applications' )->enhancements( $rows );
	}
	
	/**
	 * Edit
	 *
	 * @csrfChecked	Extension files use form helper 7 Oct 2019
	 * @return	void
	 */
	public function edit()
	{
		if ( isset( $this->enhancements[ \IPS\Request::i()->id ] ) and ( !method_exists( $this->enhancements[ \IPS\Request::i()->id ], 'isAvailable' ) or $this->enhancements[ \IPS\Request::i()->id ]::isAvailable() ) )
		{
			$langKey = 'enhancements__' . \IPS\Request::i()->id;
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $langKey );

			try
			{
				$this->enhancements[ \IPS\Request::i()->id ]->edit();
			}
			catch ( \DomainException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), $e->getCode() );
			}

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
		}
		else
		{
			\IPS\Output::i()->error( 'node_error', '2C115/1', 404, '' );
		}
	}
	
	/**
	 * Toggle
	 *
	 * @return	void
	 */
	public function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$this->enhancements[ \IPS\Request::i()->id ]->toggle( \IPS\Request::i()->status );
			\IPS\Session::i()->log( \IPS\Request::i()->status ? 'acplog__enhancements_enable' : 'acplog__enhancements_disable', array( 'enhancements__' . \IPS\Request::i()->id => TRUE ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=enhancements" ), \IPS\Request::i()->status ? \IPS\Member::loggedIn()->language()->addToStack('acplog__enhancements_enable', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'enhancements__' . \IPS\Request::i()->id ) ) ) ) : \IPS\Member::loggedIn()->language()->addToStack('acplog__enhancements_disable', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'enhancements__' . \IPS\Request::i()->id ) ) ) ) );
		}
		catch ( \LogicException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->error( $e->getMessage(), $e->getCode() );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=enhancements&do=edit&id=" . \IPS\Request::i()->id ) );
			}
		}
	}
}