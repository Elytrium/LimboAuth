<?php
/**
 * @brief		Related Content Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Apr 2014
 * @note		This widget is designed to be enabled on a page that displays a content item (e.g. a topic) to show related content based on tags
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Related Content Widget
 */
class _relatedContent extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'relatedContent';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Constructor
	 *
	 * @param	String				$uniqueKey				Unique key for this specific instance
	 * @param	array				$configuration			Widget custom configuration
	 * @param	null|string|array	$access					Array/JSON string of executable apps (core=sidebar only, content=IP.Content only, etc)
	 * @param	null|string			$orientation			Orientation (top, bottom, right, left)
	 * @return	void
	 */
	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		parent::__construct( $uniqueKey, $configuration, $access, $orientation );
		
		/* We can't run the URL related logic if we have no dispatcher because this class could also be initialized by the CLI cron job */
		if( \IPS\Dispatcher::hasInstance() )
		{
			/* Cache per item, not per block */
			$parts = parse_url( (string) \IPS\Request::i()->url()->setPage() );

			if ( \IPS\Settings::i()->htaccess_mod_rewrite )
			{
				$url = $parts['scheme'] . "://" . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $parts['path'];
			}
			else
			{
				$url = $parts['scheme'] . "://" . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
			}

			$this->cacheKey .= '_' . md5( $url );
		}
	}

	/**
	 * Ran before saving widget configuration
	 *
	 * @param	array	$values	Values from form
	 * @return	array
	 */
	public function preConfig( $values )
	{
		if ( !isset( $this->configuration['language_key'] ) )
		{
			$this->configuration['language_key'] = 'widget_title_' . md5( mt_rand() );
		}
		$values['language_key'] = $this->configuration['language_key'];

		\IPS\Lang::saveCustom( 'core', $this->configuration['language_key'], $values['widget_feed_title'] );
		unset( $values['widget_feed_title'] );

		return $values;
	}

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
		$form = parent::configuration( $form );

		/* Block title */
		$form->add( new \IPS\Helpers\Form\Translatable( 'widget_feed_title', isset( $this->configuration['language_key'] ) ? NULL : \IPS\Member::loggedIn()->language()->addToStack( 'block_relatedContent' ), FALSE, array( 'app' => 'core', 'key' => ( isset( $this->configuration['language_key'] ) ? $this->configuration['language_key'] : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'toshow', isset( $this->configuration['toshow'] ) ? $this->configuration['toshow'] : 5, TRUE ) );
		
		return $form;
 	}
 	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if( !( \IPS\Dispatcher::i()->dispatcherController instanceof \IPS\Content\Controller ) )
		{
			return '';
		}

		$limit = isset ( $this->configuration['toshow'] ) ? $this->configuration['toshow'] : 5;

		$related	= \IPS\Dispatcher::i()->dispatcherController->getSimilarContent( $limit );

		if( $related === NULL or !\count( $related ) )
		{
			return '';
		}

		if ( isset( $this->configuration['language_key'] ) )
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( $this->configuration['language_key'], FALSE, array( 'escape' => TRUE ) );
		}
		else
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'block_relatedContent' );
		}

		return $this->output( $related, $title );
	}
}