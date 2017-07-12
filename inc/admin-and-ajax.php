<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}

/* ---------------------------------------------------------------------------------------------- */
/* !I18N ======================================================================================== */
/* ---------------------------------------------------------------------------------------------- */

add_action( 'init', 'sftth_lang_init' );

function sftth_lang_init() {
	load_plugin_textdomain( 'sf-taxonomy-thumbnail', false, basename( dirname( SFTTH_FILE ) ) . '/languages/' );
}


/* !--------------------------------------------------------------------------------------------- */
/* !UPDATE ON FORM SUBMIT ======================================================================= */
/* ---------------------------------------------------------------------------------------------- */

/*
 * Used in the following cases:
 * - when we add a new term,
 * - if JavaScript is disabled while editing a term,
 * - if the update via ajax failed while editing a term.
 */

add_action( 'created_term', 'sftth_update_term_thumbnail_on_form_submit', 10, 3 );
add_action( 'edited_term',  'sftth_update_term_thumbnail_on_form_submit', 10, 3 );

function sftth_update_term_thumbnail_on_form_submit( $term_id, $term_taxonomy_id, $taxonomy ) {
	// The thumbnail is already set via ajax (or hasn't changed).
	if ( ! empty( $_POST['term-thumbnail-updated'] ) || ! isset( $_POST['thumbnail'] ) ) {
		return;
	}

	if ( empty( $_POST['action'] ) || ( 'add-tag' !== $_POST['action'] && 'editedtag' !== $_POST['action'] ) ) {
		return;
	}

	$thumbnail_id = absint( $_POST['thumbnail'] );

	if ( $thumbnail_id ) {
		set_term_thumbnail( $term_taxonomy_id, $thumbnail_id );
	}
	else {
		delete_term_thumbnail( $term_taxonomy_id );
	}
}


/* !--------------------------------------------------------------------------------------------- */
/* !TABLES COLUMN =============================================================================== */
/* ---------------------------------------------------------------------------------------------- */

add_action( 'admin_init', 'sftth_add_columns', 5 );
add_action( 'wp_ajax_add-tag', 'sftth_add_columns', 5 );

function sftth_add_columns() {
	global $taxnow;

	$taxonomy   = doing_ajax() && ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : $taxnow;
	$taxonomies = sftth_get_taxonomies();

	if ( $taxonomy && in_array( $taxonomy, $taxonomies ) ) {
		add_filter( 'manage_edit-' . $taxonomy . '_columns',  'sftth_add_column_header', PHP_INT_MAX );
		add_filter( 'manage_' . $taxonomy . '_custom_column', 'sftth_add_column_content', 10, 3 );
	}
}


function sftth_add_column_header( $columns ) {
	$default_column = sftth_get_primary_column( $columns );

	if ( $default_column ) {
		$out = array();

		foreach( $columns as $column => $label ) {
			$out[ $column ] = $label;

			if ( $column === $default_column ) {
				$out['term-thumbnail'] = __( 'Thumbnail' );
				$out = array_merge( $out, $columns );
				break;
			}
		}
	}
	else {
		$out = array_slice( $columns, 0, 2, true );
		$out['term-thumbnail'] = __( 'Thumbnail' );
		$out += $columns;
	}

	return $out;
}


function sftth_add_column_content( $content, $column_name, $term_id ) {
	global $taxnow;

	if ( 'term-thumbnail' !== $column_name ) {
		return $content;
	}

	$taxonomy         = doing_ajax() && ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : $taxnow;
	$term             = get_term( $term_id, $taxonomy );
	$term_taxonomy_id = absint( $term->term_taxonomy_id );
	$thumbnail_id     = get_term_thumbnail_id( $term_taxonomy_id );

	return $thumbnail_id ? get_term_thumbnail( $term_taxonomy_id, array( 80, 60 ) ) : '';
}


/* !--------------------------------------------------------------------------------------------- */
/* !TOOLS ======================================================================================= */
/* ---------------------------------------------------------------------------------------------- */

// Since WP 4.3+ there is a "primary" column concept.

function sftth_get_primary_column( $columns ) {
	global $current_screen;

	$default = '';

	foreach( $columns as $col => $column_name ) {
		if ( 'cb' === $col ) {
			continue;
		}

		$default = $col;
		break;
	}

	if ( ! isset( $columns[ $default ] ) ) {
		$default = isset( $columns['title'] ) ? 'title' : false;
	}

	$column = apply_filters( 'list_table_primary_column', $default, $current_screen->id );

	if ( empty( $column ) || ! isset( $columns[ $column ] ) ) {
		$column = $default;
	}

	return $column;
}

/**/