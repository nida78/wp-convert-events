<?php
/*
Plugin Name: WP Convert Events
Plugin URI: https://github.com/nida78/wp-convert-events
Description: Helps to convert events created by the old and no longer supported [Event Calendar Plugin](http://wpcal.firetree.net) to entries of the modern [Calendar Plugin](https://wordpress.org/plugins/calendar/).
Version: 1.1
Author: nida78
Author URI: http://n1da.net
License: GPLv2 or later
Text Domain: wp-convert-events
*/

load_plugin_textdomain( 'wp-convert-events', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

$wpce_version = "1.1";

add_action( 'admin_menu', 'wpce_init' );
add_action( 'wp_ajax_wpce_convert_event', 'wpce_ajax_convert_event' );
add_action( 'wp_ajax_nopriv_wpce_convert_event', 'wpce_ajax_convert_event' );

function wpce_init() {
    global $wpce_version;

    wp_enqueue_style( 'wp-convert-events-admin-css', plugins_url( 'static/css/wp-convert-events-admin.css', __FILE__ ), array(), $wpce_version, 'all' );

    wp_register_script( 'wp-convert-events-admin-js', plugins_url( 'static/js/wp-convert-events-admin.js', __FILE__ ), array( 'jquery' ), $wpce_version, false );
    wp_localize_script( 'wp-convert-events-admin-js', 'wp_convert_events_admin', array(
        'alert_convert_failed' => __( 'Sorry, but the convert of this event failed!', 'wp-convert-events' ),
        'wp_ajax_url' => admin_url( 'admin-ajax.php' )
    ));
    wp_enqueue_script( 'wp-convert-events-admin-js' );

    add_management_page(
        __('Convert Events', 'wp-convert-events'),
        __('Convert Events', 'wp-convert-events'),
        'manage_options',
        'wp_convert_events',
        'wpce_converter_page'
    );
}

function wpce_converter_page() {

    $cur_cat = array_key_exists( 'catid', $_REQUEST ) ? $_REQUEST[ 'catid' ] : -1;

    ?>
    <div class="wrap">
        <h2><?php _e( 'Convert Events', 'wp-convert-events' ) ?></h2>
        <form method="post">
            <p>
                <?php _e( 'Choose category to convert events from:', 'wp-convert-events' ); ?>
                <select id="catid" name="catid">
                    <option <?php selected( $cur_cat, -1 ); ?>><?php _e( '-- choose --', 'wp-convert-events' ); ?></option>
                    <?php
                        $categories = get_categories( array(
                            'orderby' => 'name',
                            'order'   => 'ASC'
                        ) );

                        foreach( $categories as $category ) {
                            echo '<option value="' . $category->term_id . '" ' . selected( $cur_cat, $category->term_id ) . '>' . $category->name  . ' (' . $category->count . ')</option>';
                        }
                    ?>
                </select>
                <?php submit_button( __( 'Load posts', 'wp-convert-events' ), 'primary', 'btn_submit', false ); ?>
            </p>
        </form>
        <?php
            if( $cur_cat > -1 ) :
                global $wpdb;
                $ec3_table = $wpdb->prefix . "ec3_schedule";
                $cal_table = $wpdb->prefix . "calendar";
                // $ec3_select = 'SELECT DATE( start ) AS start_date, TIME( start ) AS start_time, DATE( end ) AS end_date, allday, rpt FROM ' . $ec3_table . ' WHERE post_id=%d';
                $ec3_select  = 'SELECT DATE( ec3.start ) AS start_date, TIME( ec3.start ) AS start_time, DATE( ec3.end ) AS end_date, ec3.allday, ec3.rpt, cal.event_id, cal.event_category ';
                $ec3_select .= 'FROM ' . $ec3_table . ' ec3 ';
                $ec3_select .= 'LEFT JOIN ' . $cal_table . ' cal ON cal.event_begin = DATE( ec3.start ) AND cal.event_end = DATE( ec3.end ) AND cal.event_link = %s ';
                $ec3_select .= 'WHERE post_id = %d';

                $cal_cat_table = $wpdb->prefix . "calendar_categories";
                $cal_cat_select = 'SELECT category_id, category_name, category_colour FROM ' . $cal_cat_table;
                $cal_cats = $wpdb->get_results( $cal_cat_select );
                $cal_cat_options = "";
                foreach( $cal_cats as $cal_cat ) {
                    $cal_cat_options .= '<option value="' . $cal_cat->category_id . '" style="background-color:' . $cal_cat->category_colour . ';">' . $cal_cat->category_name . '</option>';
                }
        ?>
                <table border="1">
                    <tr>
                        <th><?php _e( 'ID', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Post Title', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Event Title', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Description', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Category', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Link', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Start', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'End', 'wp-convert-events' ); ?></th>
                        <th><?php _e( 'Time', 'wp-convert-events' ); ?></th>
                        <th colspan="2">
                            <?php _e( 'Action', 'wp-convert-events' ); ?>
                            <button id="btn-filter" value="-" title="<?php _e( 'filter already converted events', 'wp-convert-events' ); ?>" onclick="toggleFilter();">
                                <span class="dashicons dashicons-filter"></span>
                            </button>
                        </th>
                    </tr>
                    <?php
                        global $post;

                        $posts = get_posts( array(
                            'category' => $cur_cat,
                            'numberposts' => -1
                        ) );

                        if( $posts ) :
                            foreach ( $posts as $post ) :
                                setup_postdata( $post );
                                $ec3_dates = $wpdb->get_results( $wpdb->prepare( $ec3_select, get_permalink(), get_the_ID() ) );
                                $ec3_dates_count = count( $ec3_dates );
                    ?>
                                <tr id="row_<?php the_ID(); ?>_0">
                                    <td rowspan="<?php echo ( $ec3_dates_count == 0 ) ? 1 : $ec3_dates_count; ?>"><?php the_ID(); ?></td>
                                    <td rowspan="<?php echo ( $ec3_dates_count == 0 ) ? 1 : $ec3_dates_count; ?>"><?php the_title(); ?></td>
                                    <?php if( $ec3_dates_count == 0 ) { ?>
                                        <td colspan="9">&nbsp;</td>
                                    <?php } else { ?>
                                        <td><?php foreach( $ec3_dates as $i => $ec3_date ) { ?>
                                            <input type="text" name="cal_title_<?php the_ID(); echo '_' . $i; ?>" id="cal_title_<?php the_ID(); echo '_' . $i; ?>" size="40" maxlength="30" value="<?php echo substr( get_the_title(), 0, 30 ); ?>" /></td>
                                            <td><textarea name="cal_desc_<?php the_ID(); echo '_' . $i; ?>" id="cal_desc_<?php the_ID(); echo '_' . $i; ?>"><?php echo wp_strip_all_tags( get_the_excerpt(), true ); ?></textarea></td>
                                            <td><select name="cal_cat_<?php the_ID(); echo '_' . $i; ?>" id="cal_cat_<?php the_ID(); echo '_' . $i; ?>"><?php echo $cal_cat_options; ?></select></td>
                                            <td><input type="text" name="cal_link_<?php the_ID(); echo '_' . $i; ?>" id="cal_link_<?php the_ID(); echo '_' . $i; ?>" size="40" value="<?php the_permalink(); ?>" /></td>
                                            <td><input type="text" name="cal_start_<?php the_ID(); echo '_' . $i; ?>" id="cal_start_<?php the_ID(); echo '_' . $i; ?>" size="12" value="<?php echo $ec3_date->start_date; ?>" /></td>
                                            <td><input type="text" name="cal_end_<?php the_ID(); echo '_' . $i; ?>" id="cal_end_<?php the_ID(); echo '_' . $i; ?>" size="12" value="<?php echo $ec3_date->end_date; ?>" /></td>
                                            <td><input type="text" name="cal_time_<?php the_ID(); echo '_' . $i; ?>" id="cal_time_<?php the_ID(); echo '_' . $i; ?>" size="12" value="<?php echo ( ( $ec3_date->allday == 1 ) ? '00:00:00' : $ec3_date->start_time ); ?>" /></td>
                                            <td><input type="button" name="cal_action_convert_<?php the_ID(); echo '_' . $i; ?>" id="cal_action_convert_<?php the_ID(); echo '_' . $i; ?>" value="<?php _e( 'convert', 'wp-convert-events'); ?>" class="button" onclick="wpce_convert_event( '<?php the_ID(); echo '_' . $i; ?>', -1 );" /></td>
                                            <?php
                                                if( $ec3_date->event_id > 0 ) {
                                                    echo '<td class="btn-update"><input type="button" name="cal_action_update_' . get_the_ID() . '_' . $i . '" id="cal_action_update_' . get_the_ID() . '_' . $i . '" value="' . __( 'update', 'wp-convert-events' ) . '" class="button" onclick="wpce_convert_event( \'' . get_the_ID() . '_' . $i . '\', ' . $ec3_date->event_id . ' );" />';
                                                } else {
                                                    echo '<td>&nbsp;';
                                                }
                                                if( $ec3_date !== end( $ec3_dates ) ) {
                                                    echo '</td></tr><tr id="row_' . get_the_ID() . '_' . ( $i + 1 ) . '"><td>';
                                                }
                                            } ?></td>
                                    <?php } ?>
                                </tr>
                    <?php
                            endforeach;
                            wp_reset_postdata();
                        endif;
                    ?>
                </table>
            <?php endif; ?>
        </div>
    <?php
}

function wpce_ajax_convert_event() {

    $current_user = wp_get_current_user();

    global $wpdb;
    $cal_table = $wpdb->prefix . "calendar";

    $values = array(
        'event_begin'       => $_REQUEST[ 'start' ],
        'event_end'         => $_REQUEST[ 'end' ],
        'event_title'       => $_REQUEST[ 'title' ],
        'event_desc'        => $_REQUEST[ 'desc' ],
        'event_time'        => $_REQUEST[ 'time' ],
        'event_recur'       => 'S',
        'event_repeats'     => 0,
        'event_author'      => $current_user->ID,
        'event_category'    => $_REQUEST[ 'cat' ],
        'event_link'        => $_REQUEST[ 'link' ]
    );

    $types = array(
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%d',
        '%s'
    );

    if( $_REQUEST[ 'cid' ] == -1 ) {
        $dbres = $wpdb->insert(
            $cal_table,
            $values,
            $types
        );
    }else {
        $dbres = $wpdb->update(
            $cal_table,
            $values,
            array(
                'event_id'          => $_REQUEST[ 'cid' ]
            ),
            $types,
            array(
                '%d'
            )
        );
    }

    $result = array( 'code' => ( ( $dbres ) ? $_REQUEST[ 'eid' ] : -1 ) );
    echo json_encode( $result );
    wp_die();
}
