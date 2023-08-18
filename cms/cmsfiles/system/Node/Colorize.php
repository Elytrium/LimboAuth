<?php
/**
 * @brief		Colorize Node Titles Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 April 2017
 */

namespace IPS\Node;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Colorize Trait
 */
trait Colorize
{
	/**
	 * @brief	Table column name that holds the feature color hex
	 */
	public static $featureColumnName = 'feature_color';
	
	/**
	 * @brief	Cache the calculated formatted title for this node
	 */
	protected $formattedTitle = NULL;
	
	/**
	 * @brief	Cache the formatted text colour for this node
	 */
	protected $featureTextColor = NULL;
	
	/**
	 * Get HTML formatted title. Allows apps or nodes to format the title, such as adding different colours, etc
	 *
	 * @return	string
	 */
	public function get__formattedTitle()
	{
		if ( $this->formattedTitle === NULL )
		{
			$columnName = static::$featureColumnName;

			if ( ! $this->$columnName )
			{
				$this->formattedTitle = $this->_title;
			}
			else
			{
		        $this->formattedTitle = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->formattedTitle( $this );
			}
		}
		
		return $this->formattedTitle;
	}
	
	/**
	 * Get the featured text color for use in CSS
	 *
	 * @return	string
	 */
	public function get__featureTextColor()
	{
		if ( $this->featureTextColor === NULL )
		{
			if ( ! $this->feature_color )
			{
				return NULL;
			}
			else
			{
				$columnName = static::$featureColumnName;
				$this->featureTextColor = static::featureTextColor( $this->$columnName );
			}
		}
		
		return $this->featureTextColor;
	}
	
	/**
	 * Static method to fetch the text colour based on the the contrast from the background color
	 *
	 * @return	string
	 */
	public static function featureTextColor( $featureColor )
	{
		$hexColor = str_replace( '#', '', $featureColor );
		$blackColor = '000000';
		$textColor = '#ffffff';
		
		/* Use luminosity contrast algorithm to determine font color */
		$r1 = hexdec( \substr( $hexColor, 0, 2 ) );
        $g1 = hexdec( \substr( $hexColor, 2, 2 ) );
        $b1 = hexdec( \substr( $hexColor, 4, 2 ) );

        $r2 = hexdec( \substr( $blackColor, 0, 2 ) );
        $g2 = hexdec( \substr( $blackColor, 2, 2 ) );
        $b2 = hexdec( \substr( $blackColor, 4, 2 ) );

        $l1 = 0.2126 * pow( $r1 / 255, 2.2 ) + 0.7152 * pow( $g1 / 255, 2.2 ) + 0.0722 * pow( $b1 / 255, 2.2 );
        $l2 = 0.2126 * pow( $r2 / 255, 2.2 ) + 0.7152 * pow( $g2 / 255, 2.2 ) + 0.0722 * pow( $b2 / 255, 2.2 );

        $contrastRatio = 0;
        if ( $l1 > $l2 )
        {
            $contrastRatio = (int) ( ( $l1 + 0.05 ) / ( $l2 + 0.05 ) );
        }
        else
        {
            $contrastRatio = (int) ( ( $l2 + 0.05 ) / ( $l1 + 0.05 ) );
        }

        if ( $contrastRatio > 5 )
        {
           $textColor = '#000000';
        }
        
        return $textColor;
	}

	
	/**
	 * Get title from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	bool		$escape			If the title should be escaped for HTML output. If FALSE, the feature color will not be used, because that requires HTML output
	 * @return	\IPS\Http\Url
	 */
	public static function titleFromIndexData( $indexData, $itemData, $containerData, $escape = TRUE )
	{
		if ( !$escape or !isset( $containerData[ static::$databasePrefix . static::$featureColumnName ] ) )
		{
			return parent::titleFromIndexData( $indexData, $itemData, $containerData, $escape );
		}
		
		$node = $containerData;
		$node['title'] = \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $indexData['index_container_id'], 'NULL', array( 'escape' => true ) );
		$node['text_color'] = static::featureTextColor( $node[ static::$databasePrefix . static::$featureColumnName ] );
		
		/* Normalize */
		$node['feature_color'] = $node[ static::$databasePrefix . static::$featureColumnName ];
		
		$title = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->formattedTitle( $node );
		
		if ( $indexData['index_club_id'] and isset( $containerData['_club'] ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'club_container_title', FALSE, array( 'sprintf' => array( $containerData['_club']['name'] ), 'htmlsprintf' => array( $title ) ) );
		}
		else
		{
			return $title;
		}
	}
}