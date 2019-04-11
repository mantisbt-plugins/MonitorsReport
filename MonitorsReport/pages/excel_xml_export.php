<?php
# Copyright (c) 2019 Aantoly Kabakov (anatoly.kabakov.inbev@gmail.com)

# Based in excel_xml_export.php
# Download files for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 3 of the License, or (at your option) any later version.
#
# Download files plugin for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Inline column configuration plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.


# Prevent output of HTML in the content if errors occur
define( 'DISABLE_INLINE_ERROR_REPORTING', true );

require_once( 'core.php' );
require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'columns_api.php' );
require_api( 'config_api.php' );
require_api( 'excel_api.php' );
require_api( 'file_api.php' );
require_api( 'filter_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'print_api.php' );
require_api( 'utility_api.php' );
require_api( 'user_api.php' );

auth_ensure_user_authenticated();

$f_export = gpc_get_string( 'export', '' );

helper_begin_long_process();

$t_export_title = excel_get_default_filename();

$t_short_date_format = config_get( 'short_date_format' );

header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
header( 'Pragma: public' );
header( 'Content-Disposition: attachment; filename="' . urlencode( file_clean_name( $t_export_title ) ) . '.xml"' ) ;

echo excel_get_header($t_export_title );
echo excel_get_titles_row_my();

$f_bug_arr = explode( ',', $f_export );

$t_columns = excel_get_columns();

array_push($t_columns,plugin_lang_get("Field"));

# Get current filter
$t_filter = filter_get_bug_rows_filter(); 

# Get the query clauses
$t_query_clauses = filter_get_bug_rows_query_clauses( $t_filter );

# Get the total number of bugs that meet the criteria.
$p_bug_count = filter_get_bug_count( $t_query_clauses, /* pop_params */ false );

if( 0 == $p_bug_count ) {
	print_header_redirect( 'view_all_set.php?type=0&print=1' );
}

$t_end_of_results = false;
$t_offset = 0;
do {
	# Clear cache for next block
	bug_clear_cache_all();

	# select a new block
 	$t_result = filter_get_bug_rows_result( $t_query_clauses, EXPORT_BLOCK_SIZE, $t_offset,  false );
	$t_offset += EXPORT_BLOCK_SIZE;

	# Keep reading until reaching max block size or end of result set
	$t_read_rows = array();
	$t_count = 0;
	$t_bug_id_array = array();
	$t_unique_user_ids = array();
	while( $t_count < EXPORT_BLOCK_SIZE ) {
		$t_row = db_fetch_array( $t_result );
		if( false === $t_row ) {
			# a premature end indicates end of query results. Set flag as finished
			$t_end_of_results = true;
			break;
		} 
		# @TODO, the "export" bug list parameter functionality should be implemented in a more efficient way
		if( is_blank( $f_export ) || in_array( $t_row['id'], $f_bug_arr ) ) {
			$t_bug_id_array[] = (int)$t_row['id'];
			$t_read_rows[] = $t_row;
			$t_count++;
		}
	}
 
	# Max block size has been reached, or no more rows left to complete the block.
	# Either way, process what we have
	if( 0 === $t_count && !$t_end_of_results ) {
		continue;
	}
	if( 0 === $t_count && $t_end_of_results ) {
		break;
	}

	# convert and cache data
	$t_rows = filter_cache_result( $t_read_rows, $t_bug_id_array );
	bug_cache_columns_data( $t_rows, $t_columns );

	# Clear arrays that are not needed
	unset( $t_read_rows );
	unset( $t_unique_user_ids );
	unset( $t_bug_id_array );

	foreach ( $t_rows as $t_row ) {
		$relation_array=bug_get_monitors($t_row->id);
		if (!$relation_array) {
			excel_row_my( $t_columns,$t_row);
		}
		foreach ( $relation_array as $monitor) {
			excel_row_my( $t_columns,$t_row,user_get_realname($monitor));
			}
	}

} while ( false === $t_end_of_results );

echo excel_get_footer();

/**
 * Gets an Xml Row that contains all column titles
 * base in function excel_get_titles_row excel_api.php
 * @param string $p_style_id The optional style id.
 * @return string The xml row.
 */
function excel_get_titles_row_my( $p_style_id = '' ) {
	$t_columns = excel_get_columns();
	array_push($t_columns,plugin_lang_get("Field")); //add row
	$t_ret = excel_get_start_row( $p_style_id );
	foreach( $t_columns as $t_column ) {
		$t_ret .= excel_format_column_title( column_get_title( $t_column ) );
	}
	$t_ret .= '</Row>';
	return $t_ret;
}

/**
 * print document lines
 * base in function excel_get_titles_row excel_api.php
 * @param array $p_columns The optional style id.
 * @param array $p_row The optional style id.
 * @param string $p_str The optional style id.
 */
function excel_row_my( $p_columns=array(),$p_row=array(), $p_str="" ) {

	echo excel_get_start_row();

	foreach ( $p_columns as $t_column ) {
		$t_custom_field = column_get_custom_field_name( $t_column );
		if( $t_custom_field !== null ) {
			echo excel_format_custom_field( $p_row->id, $p_row->project_id, $t_custom_field );
		} else if( column_is_plugin_column( $t_column ) ) {
			echo excel_format_plugin_column_value( $t_column, $p_row );
		} else if( $t_column == plugin_lang_get("Field")) {
			echo excel_prepare_string($p_str);
		} else {
			$t_function = 'excel_format_' . $t_column;
			if( function_exists( $t_function ) ) {
				echo $t_function( $p_row );
			} else {
				# field is unknown
				echo '';
			}
		}
	}
	echo excel_get_end_row();
}

