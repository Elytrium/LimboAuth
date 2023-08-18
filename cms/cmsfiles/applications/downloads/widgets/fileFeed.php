<?php
/**
 * @brief		Files Feed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		22 Jun 2015
 */

namespace IPS\downloads\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Files Entry Feed Widget
 */
class _fileFeed extends \IPS\Content\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'fileFeed';
	
	/**
	 * @brief	App
	 */
	public $app = 'downloads';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Class
	 */
	protected static $class = 'IPS\downloads\File';

	/**
	* Init the widget
	*
	* @return	void
	*/
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'downloads', 'front' ) );
		\IPS\downloads\Application::outputCss();
		parent::init();
	}

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		$form = parent::configuration( $form );

		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on )
		{
			$options = array(
				'free'		=> 'file_free',
				'paid'		=> 'file_paid',
				'any'		=> 'any'
			);

			$form->add( new \IPS\Helpers\Form\Radio( 'file_cost_type', isset( $this->configuration['file_cost_type'] ) ? $this->configuration['file_cost_type'] : 'any', TRUE, array( 'options'	=> $options ) ) );
		}

		return $form;
	}

	/**
	 * Get where clause
	 *
	 * @return	array
	 */
	protected function buildWhere()
	{
		$where = parent::buildWhere();

		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on )
		{
			if( isset( $this->configuration['file_cost_type'] ) )
			{
				switch( $this->configuration['file_cost_type'] )
				{
					case 'free':
						$where[] = array( "( ( file_cost='' OR file_cost IS NULL ) AND ( file_nexus='' OR file_nexus IS NULL ) )" );
						break;
					case 'paid':
						$where[] = array( "( file_cost<>'' OR file_nexus>0 )" );
						break;
				}
			}
		}

		return $where;
	}
}