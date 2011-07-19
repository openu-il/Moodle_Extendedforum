<?php

//  Set tracking option for the extendedforum.

    require_once("../../config.php");
    require_once("lib.php");

    $id         = required_param('id',PARAM_INT);                           // The extendedforum to subscribe or unsubscribe to
    $returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

    if (! $extendedforum = get_record("extendedforum", "id", $id)) {
        error("Forum ID was incorrect");
    }

    if (! $course = get_record("course", "id", $extendedforum->course)) {
        error("Forum doesn't belong to a course!");
    }

    if (! $cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
        error("Incorrect cm");
    }

    require_course_login($course, false, $cm);

    $returnto = extendedforum_go_back_to($returnpage.'?id='.$course->id.'&f='.$extendedforum->id);

    if (!extendedforum_tp_can_track_extendedforums($extendedforum)) {
        redirect($returnto);
    }

    $info = new object();
    $info->name  = fullname($USER);
    $info->extendedforum = format_string($extendedforum->name);
    if (extendedforum_tp_is_tracked($extendedforum) ) {
        if (extendedforum_tp_stop_tracking($extendedforum->id)) {
            add_to_log($course->id, "extendedforum", "stop tracking", "view.php?f=$extendedforum->id", $extendedforum->id, $cm->id);
            redirect($returnto, get_string("nownottracking", "extendedforum", $info), 1);
        } else {
            error("Could not stop tracking that extendedforum", $_SERVER["HTTP_REFERER"]);
        }

    } else { // subscribe
        if (extendedforum_tp_start_tracking($extendedforum->id)) {
            add_to_log($course->id, "extendedforum", "start tracking", "view.php?f=$extendedforum->id", $extendedforum->id, $cm->id);
            redirect($returnto, get_string("nowtracking", "extendedforum", $info), 1);
        } else {
            error("Could not start tracking that extendedforum", $_SERVER["HTTP_REFERER"]);
        }
    }

?>
