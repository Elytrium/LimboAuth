<?php
/**
 * @brief		Internal Promotion
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 FEB 2017
 */

namespace IPS\Content\Promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Internal Promotion
 */
class _Internal extends PromoteAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'plus';
	
	/**
	 * @brief Default settings
	 */
	public $defaultSettings = array();
	
	/**
	 * Get image
	 *
	 * @return string
	 */
	public function getPhoto()
	{
		$shareLogos = \IPS\Settings::i()->icons_sharer_logo ? json_decode( \IPS\Settings::i()->icons_sharer_logo, true ) : array();

		if( \count( $shareLogos ) )
		{
			try
			{
				return (string) \IPS\File::get( 'core_Icons', $shareLogos[0] )->url->setScheme( ( \IPS\Request::i()->isSecure() ) ? 'https' : 'http' );
			}
			catch( \Exception $e )
			{
				return '';
			}
		}

		return \IPS\Theme::i()->settings['logo_front'];
	}
	
	/**
	 * Get name
	 *
	 * @param	string|NULL	$serviceId		Specific page/group ID
	 * @return string
	 */
	public function getName( $serviceId=NULL )
	{
		return \IPS\Settings::i()->board_name;
	}
	
	/**
	 * Get form elements for this share service
	 *
	 * @param	string		$text		Text for the text entry
	 * @param	string		$link		Short or full link (short when available)
	 * @param	string		$content	Additional text content (usually a comment, or the item content)
	 *
	 * @return array of form elements
	 */
	public function form( $text, $link=null, $content=null )
	{
		$title = NULL;
		if ( $this->promote and $this->promote->id )
		{
			if ( isset( $this->promote->form_data['internal'] ) AND $settings = $this->promote->form_data['internal'] )
			{
				$title = $settings['title'];
			}
		}
		
		return array(
			new \IPS\Helpers\Form\Text( 'promote_social_title_internal', $title ),
			new \IPS\Helpers\Form\TextArea( 'promote_social_content_internal', $content ?: $text, FALSE, array( 'maxLength' => 3000, 'rows' => 6 ) )
		);
	}

	/**
	 * Allow for any extra processing
	 *
	 * @param	array	$values	Values from the form isn't it though
	 * @return	mixed
	 */
	public function processPromoteForm( $values )
	{
		if ( isset( $values['promote_social_title_internal'] ) )
		{
			return array( 'title' => $values['promote_social_title_internal'] );
		}
		
		return NULL;
	}
	
	/**
	 * Post to internal
	 *
	 * @param	\IPS\Content\Promote	$promote 	Promote Object
	 * @return void
	 */
	public function post( $promote )
	{
		return time();
	}
	
	/**
	 * Return the published URL
	 *
	 * @param	array	$data	Data returned from a successful POST
	 * @return	\IPS\Http\Url
	 * @throws InvalidArgumentException
	 */
	public function getUrl( $data )
	{
		return NULL;
	}
}