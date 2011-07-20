<?php // $Id$

//  Displays a post, and all the posts below it.
//  If no post is given, displays all posts in a discussion

    require_once('../../config.php');

    $d      = required_param('d', PARAM_INT);                // Discussion ID
    $parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
    $mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
   // $move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another extendedforum
    $mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
    $postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

    if (!$discussion = get_record('extendedforum_discussions', 'id', $d)) {
        error("Discussion ID was incorrect or no longer exists");
    }

    if (!$course = get_record('course', 'id', $discussion->course)) {
        error("Course ID is incorrect - discussion is faulty");
    }

    if (!$extendedforum = get_record('extendedforum', 'id', $discussion->extendedforum)) {
        notify("Bad extendedforum ID stored in this discussion");
    }

    if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

    require_course_login($course, true, $cm);

/// Add ajax-related libs
    require_js(array('yui_yahoo', 'yui_event', 'yui_dom', 'yui_connection', 'yui_json'));
    require_js($CFG->wwwroot . '/mod/extendedforum/rate_ajax.js');

    // move this down fix for MDL-6926
    require_once('lib.php');

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/extendedforum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'extendedforum');

    if ($extendedforum->type == 'news') {
        if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
            || $discussion->timestart <= time())
            && ($discussion->timeend == 0 || $discussion->timeend > time())))) {
            error('Discussion ID was incorrect or no longer exists', "$CFG->wwwroot/mod/extendedforum/view.php?f=$extendedforum->id");
        }
    }

/// move discussion if requested
 /*   if ($move > 0 and confirm_sesskey()) {
        $return = $CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$discussion->id;

        require_capability('mod/extendedforum:movediscussions', $modcontext);

        if ($extendedforum->type == 'single') {
            error('Cannot move discussion from a simple single discussion extendedforum', $return);
        }

        if (!$extendedforumto = get_record('extendedforum', 'id', $move)) {
            error('You can\'t move to that extendedforum - it doesn\'t exist!', $return);
        }

        if (!$cmto = get_coursemodule_from_instance('extendedforum', $extendedforumto->id, $course->id)) {
            error('Target extendedforum not found in this course.', $return);
        }

        if (!coursemodule_visible_for_user($cmto)) {
            error('Forum not visible', $return);
        }

        require_capability('mod/extendedforum:startdiscussion',
            get_context_instance(CONTEXT_MODULE,$cmto->id));

        if (!extendedforum_move_attachments($discussion, $extendedforumto->id)) {
            notify("Errors occurred while moving attachment directories - check your file permissions");
        }
        set_field('extendedforum_discussions', 'extendedforum', $extendedforumto->id, 'id', $discussion->id);
        set_field('extendedforum_read', 'extendedforumid', $extendedforumto->id, 'discussionid', $discussion->id);
        add_to_log($course->id, 'extendedforum', 'move discussion', "discuss.php?d=$discussion->id", $discussion->id, $cmto->id);

        require_once($CFG->libdir.'/rsslib.php');
        require_once('rsslib.php');

        // Delete the RSS files for the 2 extendedforums because we want to force
        // the regeneration of the feeds since the discussions have been
        // moved.
        if (!extendedforum_rss_delete_file($extendedforum) || !extendedforum_rss_delete_file($extendedforumto)) {
            error('Could not purge the cached RSS feeds for the source and/or'.
                   'destination extendedforum(s) - check your file permissionsextendedforums', $return);
        }

        redirect($return.'&amp;moved=-1&amp;sesskey='.sesskey());
    }
     */
    $logparameters = "d=$discussion->id";
    if ($parent) {
        $logparameters .= "&amp;parent=$parent";
    }

    add_to_log($course->id, 'extendedforum', 'view discussion', "discuss.php?$logparameters", $discussion->id, $cm->id);

    unset($SESSION->fromdiscussion);

    if ($mode) {
        set_user_preference('extendedforum_displaymode', $mode);
    }

    $displaymode = get_user_preferences('extendedforum_displaymode', $CFG->extendedforum_displaymode);

    if ($parent) {
        // If flat AND parent, then force nested display this time
       // if ($displaymode == EXTENDEDFORUM_MODE_FLATOLDEST or $displaymode == EXTENDEDFORUM_MODE_FLATNEWEST) {
         //   $displaymode = EXTENDEDFORUM_MODE_NESTED;
        //}
    } else {
        $parent = $discussion->firstpost;
    }

    if (! $post = extendedforum_get_post_full($parent)) {
        error("Discussion no longer exists", "$CFG->wwwroot/mod/extendedforum/view.php?f=$extendedforum->id");
    }


    if (!extendedforum_user_can_view_post($post, $course, $cm, $extendedforum, $discussion)) {
        error('You do not have permissions to view this post', "$CFG->wwwroot/mod/extendedforum/view.php?id=$extendedforum->id");
    }

    if ($mark == 'read' or $mark == 'unread') {
        if ($CFG->extendedforum_usermarksread && extendedforum_tp_can_track_extendedforums($extendedforum) && extendedforum_tp_is_tracked($extendedforum)) {
            if ($mark == 'read') {
                extendedforum_tp_add_read_record($USER->id, $postid);
            } else {
                // unread
                extendedforum_tp_delete_read_records($USER->id, $postid);
            }
        }
    }

    $searchform = extendedforum_search_form($course);

    $navlinks = array();
    $navlinks[] = array('name' => format_string($discussion->name), 'link' => "discuss.php?d=$discussion->id", 'type' => 'title');
    if ($parent != $discussion->firstpost) {
        $navlinks[] = array('name' => format_string($post->subject), 'type' => 'title');
    }

    $navigation = build_navigation($navlinks, $cm);
   print_header("$course->shortname: ".format_string($discussion->name), $course->fullname,
                     $navigation, "", "", true, $searchform, navmenu($course, $cm));
    
   
    


/// Check to see if groups are being used in this extendedforum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

    if (isguestuser() or !isloggedin() or has_capability('moodle/legacy:guest', $modcontext, NULL, false)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        $canreply = ($extendedforum->type != 'news'); // no reply in news extendedforums

    } else {
        $canreply = extendedforum_user_can_post($extendedforum, $discussion, $USER, $cm, $course, $modcontext);
    }

/// Print the controls across the top

    echo '<table width="100%" class="discussioncontrols"><tr><td>';

    // groups selector not needed here

    echo "</td><td>";
    extendedforum_print_mode_form('discuss.php', "d=$discussion->id", $displaymode, $extendedforum->type );
    echo "</td><td>";

    if ($extendedforum->type != 'single'
                && has_capability('mod/extendedforum:movediscussions', $modcontext)) {

        // Popup menu to move discussions to other extendedforums. The discussion in a
        // single discussion extendedforum can't be moved.
        $modinfo = get_fast_modinfo($course);
        if (isset($modinfo->instances['extendedforum'])) {
            if ($course->format == 'weeks') {
                $strsection = get_string("week");
            } else {
                $strsection = get_string("topic");
            }
            $section = -1;
            $extendedforummenu = array();
            foreach ($modinfo->instances['extendedforum'] as $extendedforumcm) {
                if (!$extendedforumcm->uservisible || !has_capability('mod/extendedforum:startdiscussion',
                    get_context_instance(CONTEXT_MODULE,$extendedforumcm->id))) {
                    continue;
                }

                if (!empty($extendedforumcm->sectionnum) and $section != $extendedforumcm->sectionnum) {
                    $extendedforummenu[] = "-------------- $strsection $extendedforumcm->sectionnum --------------";
                }
                $section = $extendedforumcm->sectionnum;
                if ($extendedforumcm->instance != $extendedforum->id) {
                    $url = "discuss.php?d=$discussion->id&amp;move=$extendedforumcm->instance&amp;sesskey=".sesskey();
                    $extendedforummenu[$url] = format_string($extendedforumcm->name);
                }
            }
          
        }
    }
    echo "</td></tr></table>";

    if (!empty($extendedforum->blockafter) && !empty($extendedforum->blockperiod)) {
        $a = new object();
        $a->blockafter  = $extendedforum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$extendedforum->blockperiod);
        notify(get_string('thisextendedforumisthrottled','extendedforum',$a));
    }

    if ($extendedforum->type == 'qanda' && !has_capability('mod/extendedforum:viewqandawithoutposting', $modcontext) &&
                !extendedforum_user_has_posted($extendedforum->id,$discussion->id,$USER->id)) {
        notify(get_string('qandanotify','extendedforum'));
    }

//    if ($move == -1 and confirm_sesskey()) {
    //    notify(get_string('discussionmoved', 'extendedforum', format_string($extendedforum->name,true)));
   // }

    $canrate = has_capability('mod/extendedforum:rate', $modcontext);
  
    extendedforum_print_discussion($course, $cm, $extendedforum, $discussion, $post, $displaymode, $canreply, $canrate);
    
    if($postid)
    {
     //now open the message itself
     echo('
    <script type="text/javascript">
   getPost("postthread' . $postid . '"  , "imgflag' . $postid . '_box", '.  $postid . ') ;
    </script>
     ');
     }
    print_footer($course);


?>
