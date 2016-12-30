/**
 * Created by nida78 on 24.10.2015.
 */

var $wpcej = jQuery.noConflict();

function wpce_convert_event( eid, cid ) {
    var request_data = {
        'action': 'wpce_convert_event',
        'eid': eid,
        'cid': cid,
        'title': $wpcej( "#cal_title_" + eid ).val(),
        'desc': $wpcej( "#cal_desc_" + eid ).val(),
        'cat': $wpcej( "#cal_cat_" + eid ).val(),
        'link': $wpcej( "#cal_link_" + eid).val(),
        'start': $wpcej( "#cal_start_" + eid).val(),
        'end': $wpcej( "#cal_end_" + eid).val(),
        'time': $wpcej( "#cal_time_" + eid).val()
    };

    $wwtj.post( wp_convert_events_admin.wp_ajax_url, request_data, function( response_data ) {
        data = $wwtj.parseJSON( response_data );
        if( data[ 'code' ] == -1 ) {
            alert( wp_wiki_tooltip_admin.alert_test_failed );
        } else {
            $wpcej( "#row-" + data[ 'code' ] ).css( "background-color", "#090" );
        }
    });
}

var $wpceFilter = -1;

function toggleFilter() {
    if( ( $wpceFilter == -1 ) ) {
        $wpcej( "td.btn-update").parent().css( "display", "none" );
    } else {
        $wpcej( "td.btn-update").parent().css( "display", "table-row" );
    }
    $wpceFilter *= -1;
}