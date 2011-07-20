<?php // $Id: rate_ajax.php,v 1.1.2.3 2009/06/03 19:59:16 skodak Exp $

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com     //
//           (C) 2001-3001 Eloy Lafuente (stronk7) http://contiento.com  //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/// Accept, process and reply to ajax calls to rate extendedforums

/// TODO: Centralise duplicate code in rate.php and rate_ajax.php

    require_once('../../config.php');
    require_once($CFG->dirroot . '/mod/extendedforum/lib.php');

/// In developer debug mode, when there is a debug=1 in the URL send as plain text
/// for easier debugging.
    if (debugging('', DEBUG_DEVELOPER) && optional_param('debug', false, PARAM_BOOL)) {
        header('Content-type: text/plain; charset=UTF-8');
        $debugmode = true;
    } else {
        header('Content-type: application/json');
        $debugmode = false;
    }

/// Here we maintain response contents
    $response = array('status'=> 'Error', 'message'=>'kk');

/// Check access.
    if (!isloggedin()) {
        print_error('mustbeloggedin');
    }
    if (isguestuser()) {
        print_error('noguestrate', 'extendedforum');
    }
    if (!confirm_sesskey()) {
        print_error('invalidsesskey');
    }


/// Check required params
    $postid = required_param('postid', PARAM_INT); // The postid to rate
    $rate   = required_param('rate', PARAM_INT); // The rate to apply

/// Check postid is valid
    if (!$post = get_record_sql("SELECT p.*,
                                        d.extendedforum AS extendedforumid
                                   FROM {$CFG->prefix}extendedforum_posts p
                                   JOIN {$CFG->prefix}extendedforum_discussions d ON p.discussion = d.id
                                  WHERE p.id = $postid")) {
        print_error('invalidpostid', 'extendedforum', '', $postid);
    }

/// Check extendedforum
    if (!$extendedforum = get_record('extendedforum', 'id', $post->extendedforumid)) {
        print_error('invalidextendedforumid', 'extendedforum');
    }

/// Check course
    if (!$course = get_record('course', 'id', $extendedforum->course)) {
        print_error('invalidcourseid');
    }

/// Check coursemodule
    if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id)) {
        print_error('invalidcoursemodule');
    } else {
        $extendedforum->cmidnumber = $cm->id; //MDL-12961
    }

/// Check extendedforum can be rated
    if (!$extendedforum->assessed) {
        print_error('norate', 'extendedforum');
    }

/// Check user can rate
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/extendedforum:rate', $context);

/// Check timed ratings
    if ($extendedforum->assesstimestart and $extendedforum->assesstimefinish) {
        if ($post->created < $extendedforum->assesstimestart or $post->created > $extendedforum->assesstimefinish) {
            // we can not rate this, ignore it - this should not happen anyway unless teacher changes setting
            print_error('norate', 'extendedforum');
        }
    }

/// Calculate scale values
    $scale_values = make_grades_menu($extendedforum->scale);

/// Check rate is valid for for that extendedforum scale values
    if (!array_key_exists($rate, $scale_values) && $rate != EXTENDEDFORUM_UNSET_POST_RATING) {
        print_error('invalidrate', 'extendedforum');
    }

/// Everything ready, process rate

/// Deleting rate
    if ($rate == EXTENDEDFORUM_UNSET_POST_RATING) {
        delete_records('extendedforum_ratings', 'post', $postid, 'userid', $USER->id);

/// Updating rate
    } else if ($oldrating = get_record('extendedforum_ratings', 'userid', $USER->id, 'post', $post->id)) {
        if ($rate != $oldrating->rating) {
            $oldrating->rating = $rate;
            $oldrating->time   = time();
            if (!update_record('extendedforum_ratings', $oldrating)) {
                error("Could not update an old rating ($post->id = $rate)");
            }
        }

/// Inserting rate
    } else {
        $newrating = new object();
        $newrating->userid = $USER->id;
        $newrating->time   = time();
        $newrating->post   = $post->id;
        $newrating->rating = $rate;

        if (!insert_record('extendedforum_ratings', $newrating)) {
            print_error('cannotinsertrate', 'error', '', (object)array('id'=>$postid, 'rating'=>$rate));
        }
    }

/// Update grades
    extendedforum_update_grades($extendedforum, $post->userid);

/// Check user can see any rate
    $canviewanyrating = has_capability('mod/extendedforum:viewanyrating', $context);

/// Decide if rates info is displayed
    $rateinfo = '';
    if ($canviewanyrating) {
        $rateinfo = extendedforum_print_ratings($postid, $scale_values, $extendedforum->assessed, true, NULL, true);
    }

/// Calculate response
    $response['status']  = 'Ok';
    $response['message'] = $rateinfo;
    echo json_encode($response);

?>
