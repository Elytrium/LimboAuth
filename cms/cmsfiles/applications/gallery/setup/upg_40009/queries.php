<?php

$SQL = array();

/* Lets drop some old tables and fields */
if ( \IPS\Db::i()->checkForTable( 'gallery_form_fields' ) )
{
	$SQL[] = "DROP TABLE gallery_form_fields;";
}
if ( \IPS\Db::i()->checkForTable( 'gallery_media_types' ) )
{
	$SQL[] = "DROP TABLE gallery_media_types;";
}
if ( \IPS\Db::i()->checkForColumn( 'gallery_albums_main', 'album_g_password' ) )
{
	$SQL[] = "ALTER TABLE gallery_albums_main DROP album_g_password;";
}

/* This is for Gallery 5 - get rid of the categories table if it still exists to prevent an SQL error */
if ( \IPS\Db::i()->checkForTable( 'gallery_categories' ) )
{
	$SQL[] = "DROP TABLE gallery_categories;";
}

/* Moved here from 40007 */
if ( \IPS\Db::i()->checkForTable( 'gallery_image_views' ) )
{
	$SQL[] = "DROP TABLE gallery_image_views";
}

/* Need this query here to prevent driver errors upgrading from 4.0.x */
if ( !\IPS\Db::i()->checkForColumn( 'gallery_albums_main', 'album_position' ) )
{
	$SQL[] = "ALTER TABLE gallery_albums_main ADD album_position INT(10) NOT NULL DEFAULT 0;";
}

$SQL[] = "ALTER TABLE gallery_albums_main ADD album_detail_default INT(1) unsigned NOT NULL DEFAULT 0;";

/* Fix wrong ratings */
$SQL[] = "UPDATE gallery_images SET rating=ROUND(ratings_total/ratings_count) WHERE ratings_total>0 AND ratings_count>0 AND rating=0;";

/* Re-add watermark */
$SQL[] = "ALTER TABLE gallery_albums_main ADD album_watermark INT(1) unsigned NOT NULL DEFAULT 0;";
$SQL[] = "ALTER TABLE gallery_images ADD original_file_name varchar(255) NULL DEFAULT NULL;";
$SQL[] = "ALTER TABLE gallery_images_uploads ADD upload_file_name_original VARCHAR(255) NULL DEFAULT NULL;";


/* Sometimes g_gallery_use is missing from old upgrades */
if ( !\IPS\Db::i()->checkForColumn( 'core_groups', 'g_gallery_use' ) )
{
	$SQL[] = "ALTER TABLE core_groups ADD g_gallery_use TINYINT( 1 ) NOT NULL DEFAULT '1';";
}
