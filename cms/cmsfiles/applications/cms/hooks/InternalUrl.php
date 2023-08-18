//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_InternalUrl extends _HOOK_CLASS_
{
	/**
	 * Get the friendly URL for this URL if there is one
	 *
	 * @return	mixed	The friendly URL if there is one, TRUE if there isn't, or NULL if not sure
	 */
	public function correctFriendlyUrl()
	{
		/* Check what the normal handling thinks... */
		$return = parent::correctFriendlyUrl();
		
		/* If it it thinks it belongs to "pages", we might be able to be more accurate */
		if ( $return instanceof \IPS\Http\Url\Friendly and $return->seoTemplate === 'content_page_path' and isset( $this->queryString['path'] ) )
		{
			/* Try to find a page */
			try
			{
				/* Create it */
				$correctUrl = \IPS\cms\Pages\Router::loadFromUrl( $this )->url();			
				
				/* Set extra stuff in our query string */
				$paramsToSet = array();
				foreach ( $this->queryString as $k => $v )
				{
					if ( !array_key_exists( $k, $correctUrl->queryString ) and !array_key_exists( $k, $correctUrl->hiddenQueryString ) )
					{
						$paramsToSet[ $k ] = $v;
					}
				}
				if ( \count( $paramsToSet ) )
				{
					$correctUrl = $correctUrl->setQueryString( $paramsToSet );
				}
				
				/* Return */
				return $correctUrl;
			}
			/* Couldn't find one? Don't accept responsibility */
			catch ( \OutOfRangeException $e ){}
		}
				
		/* Return */
		return $return;
	}
	
}