<?php // $Id: markposts.php,v 1.16.2.2 2008/04/13 19:10:36 skodak Exp $

      //  Set tracking option for the extendedforum.

    require_once("../../config.php");
    require_once("lib.php");

    $f          = required_param('f',PARAM_INT); // The extendedforum to mark
    $mark       = required_param('mark',PARAM_ALPHA); // Read or unread?
    $d          = optional_param('d',0,PARAM_INT); // Discussion to mark.
    $returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

    if (! $extendedforum = get_record("extendedforum", "id", $f)) {
        error("Forum ID was incorrect");
    }

    if (! $course = get_record("course", "id", $extendedforum->course)) {
        error("Forum doesn't belong to a course!");
    }

    if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
        error("Incorrect cm!");
    }

    $user = $USER;

    require_course_login($course, false, $cm);

    if ($returnpage == 'index.php') {
        $returnto = extendedforum_go_back_to($returnpage.'?id='.$course->id);
    } else {
        $returnto = extendedforum_go_back_to($returnpage.'?f='.$extendedforum->id);
    }

    if (isguest()) {   // Guests can't change extendedforum
        $wwwroot = $CFG->wwwroot.'/login/index.php';
        if (!empty($CFG->loginhttps)) {
            $wwwroot = str_replace('http:','https:', $wwwroot);
        }

        $navigation = build_navigation('', $cm);
        print_header($course->shortname, $course->fullname, $navigation, '', '', true, "", navmenu($course, $cm));
        notice_yesno(get_string('noguesttracking', 'extendedforum').'<br /><br />'.get_string('liketologin'),
                     $wwwroot, $returnto);
        print_footer($course);
        exit;
    }

    $info = new object();
    $info->name  = fullname($user);
    $info->extendedforum = format_string($extendedforum->name);

    if ($mark == 'read') {
        if (!empty($d)) {
            if (! $discussion = get_record('extendedforum_discussions', 'id', $d, 'extendedforum', $extendedforum->id)) {
                error("Discussion ID was incorrect");
            }

            if (extendedforum_tp_mark_discussion_read($user, $d)) {
                add_to_log($course->id, "discussion", "mark read", "view.php?f=$extendedforum->id", $d, $cm->id);
            }
        } else {
            if (extendedforum_tp_mark_extendedforum_read($user, $extendedforum->id)) {
                add_to_log($course->id, "extendedforum", "mark read", "view.php?f=$extendedforum->id", $extendedforum->id, $cm->id);
            }
        }

/// FUTURE - Add ability to mark them as unread.
//    } else { // subscribe
//        if (extendedforum_tp_start_tracking($extendedforum->id, $user->id)) {
//            add_to_log($course->id, "extendedforum", "mark unread", "view.php?f=$extendedforum->id", $extendedforum->id, $cm->id);
//            redirect($returnto, get_string("nowtracking", "extendedforum", $info), 1);
//        } else {
//            error("Could not start tracking that extendedforum", $_SERVER["HTTP_REFERER"]);
//        }
    }

    redirect($returnto);

?>
