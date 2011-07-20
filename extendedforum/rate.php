<?php // $Id: rate.php,v 1.24.2.5 2009/11/21 15:33:34 skodak Exp $

//  Collect ratings, store them, then return to where we came from

/// TODO: Centralise duplicate code in rate.php and rate_ajax.php

    require_once('../../config.php');
    require_once('lib.php');

    $extendedforumid = required_param('extendedforumid', PARAM_INT); // The extendedforum the rated posts are from

    if (!$extendedforum = get_record('extendedforum', 'id', $extendedforumid)) {
        error("Forum ID was incorrect");
    }

    if (!$course = get_record('course', 'id', $extendedforum->course)) {
        error("Course ID was incorrect");
    }

    if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id)) {
        error("Course Module ID was incorrect");
    } else {
        $extendedforum->cmidnumber = $cm->id; //MDL-12961
        }

    require_login($course, false, $cm);

    if (isguestuser()) {
        error("Guests are not allowed to rate entries.");
    }

    if (!$extendedforum->assessed) {
        error("Rating of items not allowed!");
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/extendedforum:rate', $context);

    if ($data = data_submitted() and confirm_sesskey()) {

        $discussionid = false;

    /// Calculate scale values
        $scale_values = make_grades_menu($extendedforum->scale);

        foreach ((array)$data as $postid => $rating) {
            if (!is_numeric($postid)) {
                continue;
            }

            // following query validates the submitted postid too
            $sql = "SELECT fp.*
                      FROM {$CFG->prefix}extendedforum_posts fp, {$CFG->prefix}extendedforum_discussions fd
                     WHERE fp.id = '$postid' AND fp.discussion = fd.id AND fd.extendedforum = $extendedforum->id";

            if (!$post = get_record_sql($sql)) {
                error("Incorrect postid - $postid");
            }

            $discussionid = $post->discussion;

            if ($extendedforum->assesstimestart and $extendedforum->assesstimefinish) {
                if ($post->created < $extendedforum->assesstimestart or $post->created > $extendedforum->assesstimefinish) {
                    // we can not rate this, ignore it - this should not happen anyway unless teacher changes setting
                    continue;
                }
            }

        /// Check rate is valid for for that extendedforum scale values
            if (!array_key_exists($rating, $scale_values) && $rating != EXTENDEDFORUM_UNSET_POST_RATING) {
                print_error('invalidrate', 'extendedforum', '', $rating);
            }

            if ($rating == EXTENDEDFORUM_UNSET_POST_RATING) {
                delete_records('extendedforum_ratings', 'post', $postid, 'userid', $USER->id);
                extendedforum_update_grades($extendedforum, $post->userid);

            } else if ($oldrating = get_record('extendedforum_ratings', 'userid', $USER->id, 'post', $post->id)) {
                if ($rating != $oldrating->rating) {
                    $oldrating->rating = $rating;
                    $oldrating->time   = time();
                    if (! update_record('extendedforum_ratings', $oldrating)) {
                        error("Could not update an old rating ($post->id = $rating)");
                    }
                    extendedforum_update_grades($extendedforum, $post->userid);
                }

            } else {
                $newrating = new object();
                $newrating->userid = $USER->id;
                $newrating->time   = time();
                $newrating->post   = $post->id;
                $newrating->rating = $rating;

                if (! insert_record('extendedforum_ratings', $newrating)) {
                    error("Could not insert a new rating ($postid = $rating)");
                }
                extendedforum_update_grades($extendedforum, $post->userid);
            }
        }

       // if ($extendedforum->type == 'single' or !$discussionid) {
            redirect("$CFG->wwwroot/mod/extendedforum/view.php?id=$cm->id", get_string('ratingssaved', 'extendedforum'));
     
         // } else {
          //  redirect("$CFG->wwwroot/mod/extendedforum/discuss.php?d=$discussionid", get_string('ratingssaved', 'extendedforum'));
        //}

    } else {
        error("This page was not accessed correctly");
    }

?>
