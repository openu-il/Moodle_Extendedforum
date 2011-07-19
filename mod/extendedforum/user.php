<?php // $Id: user.php,v 1.30.2.7 2008/07/05 14:53:31 skodak Exp $

// Display user activity reports for a course

    require_once('../../config.php');
    require_once('lib.php');
    // Course ID
    $course  = required_param('course', PARAM_INT);
    // User ID
    $id      = optional_param('id', 0, PARAM_INT);
    $mode    = optional_param('mode', 'posts', PARAM_ALPHA);
    $page    = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', 5, PARAM_INT);

    if (empty($id)) {         // See your own profile by default
        require_login();
        $id = $USER->id;
    }

    if (! $user = get_record("user", "id", $id)) {
        error("User ID is incorrect");
    }

    if (! $course = get_record("course", "id", $course)) {
        error("Course id is incorrect.");
    }

    $syscontext = get_context_instance(CONTEXT_SYSTEM);
    $usercontext   = get_context_instance(CONTEXT_USER, $id);

    // do not force parents to enrol
    if (!get_record('role_assignments', 'userid', $USER->id, 'contextid', $usercontext->id)) {
        require_course_login($course);
    }

    if ($user->deleted) {
        print_header();
        print_heading(get_string('userdeleted'));
        print_footer($course);
        die;
    }

    add_to_log($course->id, "extendedforum", "user report",
            "user.php?course=$course->id&amp;id=$user->id&amp;mode=$mode", "$user->id"); 

    $strextendedforumposts   = get_string('extendedforumposts', 'extendedforum');
    $strparticipants = get_string('participants');
    $strmode         = get_string($mode, 'extendedforum');
    $fullname        = fullname($user, has_capability('moodle/site:viewfullnames', $syscontext));

    $navlinks = array();
    if (has_capability('moodle/course:viewparticipants', get_context_instance(CONTEXT_COURSE, $course->id)) || has_capability('moodle/site:viewparticipants', $syscontext)) {
        $navlinks[] = array('name' => $strparticipants, 'link' => "$CFG->wwwroot/user/index.php?id=$course->id", 'type' => 'core');
    }
    $navlinks[] = array('name' => $fullname, 'link' => "$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id", 'type' => 'title');
    $navlinks[] = array('name' => $strextendedforumposts, 'link' => '', 'type' => 'title');
    $navlinks[] = array('name' => $strmode, 'link' => '', 'type' => 'title');

    $navigation = build_navigation($navlinks);

    print_header("$course->shortname: $fullname: $strmode", $course->fullname,$navigation);


    $currenttab = $mode;
    $showroles = 1;
    include($CFG->dirroot.'/user/tabs.php');   /// Prints out tabs as part of user page


    switch ($mode) {
        case 'posts' :
            $searchterms = array('userid:'.$user->id);
            $extrasql = '';
            break;

        default:
            $searchterms = array('userid:'.$user->id);
            $extrasql = 'AND p.parent = 0';
            break;
    }

    echo '<div class="user-content">';

    if ($course->id == SITEID) {
        if (empty($CFG->forceloginforprofiles) || isloggedin()) {
            // Search throughout the whole site.
            $searchcourse = 0;
        } else {
            $searchcourse = SITEID;
        }
    } else {
        // Search only for posts the user made in this course.
        $searchcourse = $course->id;
    }

    // Get the posts.
    if ($posts = extendedforum_search_posts($searchterms, $searchcourse, $page*$perpage, $perpage,
                                    $totalcount, $extrasql)) {

        print_paging_bar($totalcount, $page, $perpage,
                         "user.php?id=$user->id&amp;course=$course->id&amp;mode=$mode&amp;perpage=$perpage&amp;");

        $discussions = array();
        $extendedforums      = array();
        $cms         = array();

        foreach ($posts as $post) {

            if (!isset($discussions[$post->discussion])) {
                if (! $discussion = get_record('extendedforum_discussions', 'id', $post->discussion)) {
                    error('Discussion ID was incorrect');
                }
                $discussions[$post->discussion] = $discussion;
            } else {
                $discussion = $discussions[$post->discussion];
            }

            if (!isset($extendedforums[$discussion->extendedforum])) {
                if (! $extendedforum = get_record('extendedforum', 'id', $discussion->extendedforum)) {
                    error("Could not find extendedforum $discussion->extendedforum");
                }
                $extendedforums[$discussion->extendedforum] = $extendedforum;
            } else {
                $extendedforum = $extendedforums[$discussion->extendedforum];
            }

            $ratings = null;
            if ($extendedforum->assessed) {
                if ($scale = make_grades_menu($extendedforum->scale)) {
                    $ratings =new object();
                    $ratings->scale = $scale;
                    $ratings->assesstimestart = $extendedforum->assesstimestart;
                    $ratings->assesstimefinish = $extendedforum->assesstimefinish;
                    $ratings->allow = false;
                }
            }

            if (!isset($cms[$extendedforum->id])) {
                if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id)) {
                    error('Course Module ID was incorrect');
                }
                $cms[$extendedforum->id] = $cm;
                unset($cm); // do not use cm directly, it would break caching
            }

            $fullsubject = "<a href=\"view.php?f=$extendedforum->id\">".format_string($extendedforum->name,true)."</a>";
            if ($extendedforum->type != 'single') {
                $fullsubject .= " -> <a href=\"discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a>";
                if ($post->parent != 0) {
                    $fullsubject .= " -> <a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a>";
                }
            }

            if ($course->id == SITEID && has_capability('moodle/site:config', $syscontext)) {
                $postcoursename = get_field('course', 'shortname', 'id', $extendedforum->course);
                $fullsubject = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$extendedforum->course.'">'.$postcoursename.'</a> -> '. $fullsubject;
            }

            $post->subject = $fullsubject;

            $fulllink = "<a href=\"discuss.php?d=$post->discussion&amp;postid=$post->id\">".
                         get_string("postincontext", "extendedforum")."</a>";

            extendedforum_print_post($post, $discussion, $extendedforum, $cms[$extendedforum->id], $course, false, false, false, $ratings, $fulllink);
            echo "<br />";
        }

        print_paging_bar($totalcount, $page, $perpage,
                         "user.php?id=$user->id&amp;course=$course->id&amp;mode=$mode&amp;perpage=$perpage&amp;");
    } else {
        if ($mode == 'posts') {
            print_heading(get_string('noposts', 'extendedforum'));
        } else {
            print_heading(get_string('nodiscussionsstartedby', 'extendedforum'));
        }
    }
    echo '</div>';
    print_footer($course);

?>
