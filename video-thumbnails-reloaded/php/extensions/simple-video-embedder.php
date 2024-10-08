<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*  Copyright 2014 Sutherland Boswell  (email : sutherland.boswell@gmail.com)
    Forked by Yehuda Hassine 2020
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function simple_video_embedder_video_thumbnail_markup_filter( $markup, $post_id ) {
	if ( function_exists( 'p75HasVideo' ) ) {
		if ( p75HasVideo( $post_id ) ) {
			$markup .= ' ' . p75GetVideo( $post_id );
		}
	}
	return $markup;
}

// Add filter to modify markup
add_filter( 'video_thumbnail_markup', 'simple_video_embedder_video_thumbnail_markup_filter', 10, 2 );

?>