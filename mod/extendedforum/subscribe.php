<?php

//  Subscribe to or unsubscribe from a extendedforum.

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);      // The extendedforum to subscribe or unsubscribe to
    $force = optional_param('force','',PARAM_ALPHA);  // Force everyone to be subscribed to this extendedforum?
    $user = optional_param('user',0,PARAM_INT);
    $sesskey = optional_param('sesskey', null, PARAM_RAW);

    if (! $extendedforum = get_record("extendedforum", "id", $id)) {
        error("Forum ID was incorrect");
    }

    if (! $course = get_record("course", "id", $extendedforum->course)) {
        error("Forum doesn't belong to a course!");
    }

    if ($cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    } else {
        $cm->id = 0;
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    }

    if ($user) {
        require_sesskey();
        if (!has_capability('mod/extendedforum:managesubscriptions', $context)) {
            error('You do not have the permission to subscribe/unsubscribe other people!');
        }
        if (!$user = get_record("user", "id", $user)) {
            error("User ID was incorrect");
        }
    } else {
        $user = $USER;
    }

    if (groupmode($course, $cm)
                and !extendedforum_is_subscribed($user->id, $extendedforum)
                and !has_capability('moodle/site:accessallgroups', $context)) {
        if (!mygroupid($course->id)) {
            error('Sorry, but you must be a group member to subscribe.');
        }
    }

    require_login($course->id, false, $cm);

    if (isguest()) {   // Guests can't subscribe
        $wwwroot = $CFG->wwwroot.'/login/index.php';
        if (!empty($CFG->loginhttps)) {
            $wwwroot = str_replace('http:','https:', $wwwroot);
        }
        
        $navigation = build_navigation('', $cm);
        print_header($course->shortname, $course->fullname, $navigation, '', '', true, "", navmenu($course, $cm));
        
        notice_yesno(get_string('noguestsubscribe', 'extendedforum').'<br /><br />'.get_string('liketologin'),
                     $wwwroot, $_SERVER['HTTP_REFERER']);
        print_footer($course);
        exit;
    }

    $returnto = optional_param('backtoindex',0,PARAM_INT) 
        ? "index.php?id=".$course->id 
        : "view.php?f=$id";

    if ($force and has_capability('mod/extendedforum:managesubscriptions', $context)) {
        require_sesskey();
        if (extendedforum_is_forcesubscribed($extendedforum)) {
            extendedforum_forcesubscribe($extendedforum->id, 0);
            redirect($returnto, get_string("everyonecannowchoose", "extendedforum"), 1);
        } else {
            extendedforum_forcesubscribe($extendedforum->id, 1);
            redirect($returnto, get_string("everyoneisnowsubscribed", "extendedforum"), 1);
        }
    }

    if (extendedforum_is_forcesubscribed($extendedforum)) {
        redirect($returnto, get_string("everyoneisnowsubscribed", "extendedforum"), 1);
    }

    $info->name  = fullname($user);
    $info->extendedforum = format_string($extendedforum->name);

    if ($user->id == $USER->id) {
        $selflink = 'subscribe.php?id='.$id.'&amp;sesskey='.sesskey();
    } else {
        $selflink = 'subscribe.php?id='.$id.'&amp;user='.$user->id.'&amp;sesskey='.sesskey();
    }

    if (extendedforum_is_subscribed($user->id, $extendedforum->id)) {
        if (is_null($sesskey)) {    // we came here via link in email
            $navigation = build_navigation('', $cm);
            print_header($course->shortname, $course->fullname, $navigation, '', '', true, '', navmenu($course, $cm));
            notice_yesno(get_string('confirmunsubscribe', 'extendedforum', format_string($extendedforum->name)), $selflink, $returnto);
            print_footer($course);
            exit;
        }
        if (extendedforum_unsubscribe($user->id, $extendedforum->id)) {
            add_to_log($course->id, "extendedforum", "unsubscribe", "view.php?f=$extendedforum->id", $extendedforum->id, $cm->id);
            redirect($returnto, get_string("nownotsubscribed", "extendedforum", $info), 1);
        } else {
            error("Could not unsubscribe you from that extendedforum", $_SERVER["HTTP_REFERER"]);
        }

    } else {  // subscribe
        if ($extendedforum->forcesubscribe == EXTENDEDFORUM_DISALLOWSUBSCRIBE &&
                    !has_capability('mod/extendedforum:managesubscriptions', $context)) {
            print_error('disallowsubscribe', 'extendedforum', $_SERVER["HTTP_REFERER"]);
        }
        if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
            error("Could not subscribe you to that extendedforum", $_SERVER["HTTP_REFERER"]);
        }
        if (is_null($sesskey)) {    // we came here via link in email
            $navigation = build_navigation('', $cm);
            print_header($course->shortname, $course->fullname, $navigation, '', '', true, '', navmenu($course, $cm));
            notice_yesno(get_string('confirmsubscribe', 'extendedforum', format_string($extendedforum->name)), $selflink, $returnto);
            print_footer($course);
            exit;
        }
        if (extendedforum_subscribe($user->id, $extendedforum->id) ) {
            add_to_log($course->id, "extendedforum", "subscribe", "view.php?f=$extendedforum->id", $extendedforum->id, $cm->id);
            redirect($returnto, get_string("nowsubscribed", "extendedforum", $info), 1);
        } else {
            error("Could not subscribe you to that extendedforum", $_SERVER["HTTP_REFERER"]);
        }
    }

?>
