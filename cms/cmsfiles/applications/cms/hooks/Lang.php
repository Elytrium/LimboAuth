//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Lang extends _HOOK_CLASS_
{
	/**
	 * Set words
	 *
	 * @return	void
	 */
	public function languageInit()
	{
		/* Language, is it? */
		parent::languageInit();
		
		/* Don't do this during setup */
		if ( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation == 'setup' )
		{
			return;
		}

		/* Ensure applications set up correctly before task is executed. Pages, for example, needs to set up spl autoloaders first */
		\IPS\Application::applications();
		
		/* Add in the database specific language bits and bobs */
		foreach( \IPS\cms\Databases::getStore() as $database )
		{
			$this->words['__indefart_content_record_comments_title_' . $database['database_id'] ] = $this->addToStack( '__indefart_content_record_comments_title' );
			$this->words['__indefart_content_record_reviews_title_' . $database['database_id'] ] = $this->addToStack( '__indefart_content_record_reviews_title' );
			$this->words['__indefart_content_db_lang_su_' . $database['database_id'] ] = $this->addToStack( 'content_db_lang_ia_' . $database['database_id'] );
			$this->words['__defart_content_record_comments_title_' . $database['database_id'] ] = $this->addToStack( '__defart_content_record_comments_title' );
			$this->words['__defart_content_record_reviews_title_' . $database['database_id'] ] = $this->addToStack( '__defart_content_record_reviews_title' );
			$this->words['__defart_content_db_lang_su_' . $database['database_id'] ] = $this->addToStack( 'content_db_lang_sl_' . $database['database_id'] );

			$this->words['content_record_comments_title_' . $database['database_id'] ] = $this->addToStack( 'content_record_comment_title', FALSE, array( 'sprintf' => array( $this->recordWord( 1, TRUE, $database['database_id'] ) ) ) );
			$this->words['content_record_reviews_title_' . $database['database_id'] ] = $this->addToStack( 'content_record_review_title', FALSE, array( 'sprintf' => array( $this->recordWord( 1, TRUE, $database['database_id'] ) ) ) );
			$this->words['content_record_comments_title_' . $database['database_id'] . '_pl' ] = $this->addToStack( 'content_record_comments_title', FALSE, array( 'sprintf' => array( $this->recordWord( 1, TRUE, $database['database_id'] ) ) ) );
			$this->words['content_record_comments_title_' . $database['database_id'] . '_pl_lc' ] = $this->addToStack( 'content_record_comments_title_lc', FALSE, array( 'sprintf' => array( $this->recordWord( 1, FALSE, $database['database_id'] ) ) ) );
			$this->words['content_record_comments_title_' . $database['database_id'] . '_lc' ] = $this->addToStack( 'content_record_comments_title_lc', FALSE, array( 'sprintf' => array( $this->recordWord( 1, FALSE, $database['database_id'] ) ) ) );
			$this->words['content_record_reviews_title_' . $database['database_id'] . '_pl' ] = $this->addToStack( 'content_record_reviews_title', FALSE, array( 'sprintf' => array( $this->recordWord( 1, TRUE, $database['database_id'] ) ) ) );
			$this->words['content_record_reviews_title_' . $database['database_id'] . '_pl_lc' ] = $this->addToStack( 'content_record_reviews_title_lc', FALSE, array( 'sprintf' => array( $this->recordWord( 1, FALSE, $database['database_id'] ) ) ) );
			$this->words['content_record_reviews_title_' . $database['database_id'] . '_lc' ] = $this->addToStack( 'content_record_reviews_title_lc', FALSE, array( 'sprintf' => array( $this->recordWord( 1, FALSE, $database['database_id'] ) ) ) );

			$this->words['content_db_lang_su_' . $database['database_id'] . '_pl' ] =  $this->addToStack( 'content_db_lang_pu_' . $database['database_id'] );
			$this->words['content_db_lang_su_' . $database['database_id'] . '_pl_lc' ] =  $this->addToStack( 'content_db_lang_pl_' . $database['database_id'] );
			$this->words['content_db_lang_sl_' . $database['database_id'] . '_pl_lc' ] =  $this->addToStack( 'content_db_lang_pl_' . $database['database_id'] );

			$fieldsClass = '\IPS\cms\Fields' . $database['database_id'];
			$customFields = $fieldsClass::databaseFieldIds();

			foreach ( $customFields AS $id )
			{
				$this->words['sort_field_' . $id] = $this->addToStack( 'content_field_' . $id );
			}
		}
		
	}
	
	/**
	 * "Records" / "Record" word
	 *
	 * @param	int	    $number	Number
	 * @param   bool    $upper  ucfirst string
	 * @return	string
	 */
	public function recordWord( $number, $upper, $databaseId )
	{
		$case = $upper ? 'u' : 'l';
		return $number == 1 ? $this->addToStack("content_db_lang_s{$case}_{$databaseId}") : $this->addToStack("content_db_lang_p{$case}_{$databaseId}");
	}

}
