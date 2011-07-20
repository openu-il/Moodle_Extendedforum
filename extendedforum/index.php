<?php  // $Id: index.php,v 1.104.2.13 2009/03/08 23:49:59 poltawski Exp $

    require_once("../../config.php");
    require_once("lib.php");
    require_once("$CFG->libdir/rsslib.php");

    $id = optional_param('id', 0, PARAM_INT);                   // Course id
    $subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all extendedforums

    if ($id) {
        if (! $course = get_record('course', 'id', $id)) {
            error("Course ID is incorrect");
        }
    } else {
        if (! $course = get_site()) {
            error("Could not find a top-level course!");
        }
    }

    require_course_login($course);
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);


    unset($SESSION->fromdiscussion);

    add_to_log($course->id, 'extendedforum', 'view extendedforums', "index.php?id=$course->id");

    $strextendedforums       = get_string('extendedforums', 'extendedforum');
    $strextendedforum        = get_string('extendedforum', 'extendedforum');
    $strdescription  = get_string('description');
    $strdiscussions  = get_string('discussions', 'extendedforum');
    $strsubscribed   = get_string('subscribed', 'extendedforum');
    $strunreadposts  = get_string('unreadposts', 'extendedforum');
    $strtracking     = get_string('tracking', 'extendedforum');
    $strmarkallread  = get_string('markallread', 'extendedforum');
    $strtrackextendedforum   = get_string('trackextendedforum', 'extendedforum');
    $strnotrackextendedforum = get_string('notrackextendedforum', 'extendedforum');
    $strsubscribe    = get_string('subscribe', 'extendedforum');
    $strunsubscribe  = get_string('unsubscribe', 'extendedforum');
    $stryes          = get_string('yes');
    $strno           = get_string('no');
    $strrss          = get_string('rss');
    $strweek         = get_string('week');
    $strsection      = get_string('section');

    $searchform = extendedforum_search_form($course);


    // Start of the table for General Forums

    $generaltable->head  = array ($strextendedforum, $strdescription, $strdiscussions);
    $generaltable->align = array ('left', 'left', 'center');
    $generaltable->class="openutable"  ;
    if ($usetracking = extendedforum_tp_can_track_extendedforums()) {
        $untracked = extendedforum_tp_get_untracked_extendedforums($USER->id, $course->id);

        $generaltable->head[] = $strunreadposts;
        $generaltable->align[] = 'center';

        $generaltable->head[] = $strtracking;
        $generaltable->align[] = 'center';
    }

    $subscribed_extendedforums = extendedforum_get_subscribed_extendedforums($course);

    if ($can_subscribe = (!isguestuser() && has_capability('moodle/course:view', $coursecontext))) {
        $generaltable->head[] = $strsubscribed;
        $generaltable->align[] = 'center';
    }

    if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                     isset($CFG->enablerssfeeds) && isset($CFG->extendedforum_enablerssfeeds) &&
                     $CFG->enablerssfeeds && $CFG->extendedforum_enablerssfeeds)) {
        $generaltable->head[] = $strrss;
        $generaltable->align[] = 'center';
    }


    // Parse and organise all the extendedforums.  Most extendedforums are course modules but
    // some special ones are not.  These get placed in the general extendedforums
    // category with the extendedforums in section 0.

    $extendedforums = get_records('extendedforum', 'course', $course->id);

    $generalextendedforums  = array();
    $learningextendedforums = array();
    $modinfo =& get_fast_modinfo($course);

    if (!isset($modinfo->instances['extendedforum'])) {
        $modinfo->instances['extendedforum'] = array();
    }

    foreach ($modinfo->instances['extendedforum'] as $extendedforumid=>$cm) {
        if (!$cm->uservisible or !isset($extendedforums[$extendedforumid])) {
            continue;
        }

        $extendedforum = $extendedforums[$extendedforumid];

        if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
            continue;   // Shouldn't happen
        }

        if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
            continue;
        }

        // fill two type array - order in modinfo is the same as in course
        if ($extendedforum->type == 'news' or $extendedforum->type == 'social') {
            $generalextendedforums[$extendedforum->id] = $extendedforum;

        } else if ($course->id == SITEID or empty($cm->sectionnum)) {
            $generalextendedforums[$extendedforum->id] = $extendedforum;

        } else {
            $learningextendedforums[$extendedforum->id] = $extendedforum;
        }
    }
                                        
    /// Do course wide subscribe/unsubscribe
    if (!is_null($subscribe) and !isguestuser() and !isguest()) {
        foreach ($modinfo->instances['extendedforum'] as $extendedforumid=>$cm) {
            $extendedforum = $extendedforums[$extendedforumid];
            $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id); 
            $cansub = false;

            if (has_capability('mod/extendedforum:viewdiscussion', $modcontext)) {
                $cansub = true;
            }
            if ($cansub && $cm->visible == 0 &&
                !has_capability('mod/extendedforum:managesubscriptions', $modcontext)) 
            {
                $cansub = false;
            }
            if (!extendedforum_is_forcesubscribed($extendedforum)) {
                $subscribed = extendedforum_is_subscribed($USER->id, $extendedforum);
                if ((has_capability('moodle/course:manageactivities', $coursecontext, $USER->id) || $extendedforum->forcesubscribe != EXTENDEDFORUM_DISALLOWSUBSCRIBE) && $subscribe && !$subscribed && $cansub) {
                    extendedforum_subscribe($USER->id, $extendedforumid);
                } else if (!$subscribe && $subscribed) {
                    extendedforum_unsubscribe($USER->id, $extendedforumid);
                }
            }
        }
        $returnto = extendedforum_go_back_to("index.php?id=$course->id");
        if ($subscribe) {
            add_to_log($course->id, 'extendedforum', 'subscribeall', "index.php?id=$course->id", $course->id);
            redirect($returnto, get_string('nowallsubscribed', 'extendedforum', format_string($course->shortname)), 1);
        } else {
            add_to_log($course->id, 'extendedforum', 'unsubscribeall', "index.php?id=$course->id", $course->id);
            redirect($returnto, get_string('nowallunsubscribed', 'extendedforum', format_string($course->shortname)), 1);
        }
    }

    /// First, let's process the general extendedforums and build up a display

    $introoptions = new object();
    $introoptions->para = false;

    if ($generalextendedforums) {
        foreach ($generalextendedforums as $extendedforum) {
            $cm      = $modinfo->instances['extendedforum'][$extendedforum->id];
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);

            $count = extendedforum_count_discussions($extendedforum, $cm, $course);

            if ($usetracking) {
                if ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$extendedforum->id])) {
                            $unreadlink  = '-';
                    } else if ($unread = extendedforum_tp_count_extendedforum_unread_posts($cm, $course)) {
                            $unreadlink = '<span class="unread"><a href="view.php?f='.$extendedforum->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $extendedforum->id.'&amp;mark=read"><img src="'.$CFG->pixpath.'/t/clear.gif" alt="'.$strmarkallread.'" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_ON) {
                        $trackedlink = $stryes;

                    } else {
                        $options = array('id'=>$extendedforum->id);
                        if (!isset($untracked[$extendedforum->id])) {
                            $trackedlink = print_single_button($CFG->wwwroot.'/mod/extendedforum/settracking.php', $options, $strno, 'post', '_self', true, $strnotrackextendedforum);
                        } else {
                            $trackedlink = print_single_button($CFG->wwwroot.'/mod/extendedforum/settracking.php', $options,$stryes , 'post', '_self', true, $strtrackextendedforum);
                        }
                    }
                }
            }

            $extendedforum->intro = shorten_text(trim(format_text($extendedforum->intro, FORMAT_HTML, $introoptions)), $CFG->extendedforum_shortpost);
            $extendedforumname = format_string($extendedforum->name, true);;

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $extendedforumlink = "<a href=\"view.php?f=$extendedforum->id\" $style>".format_string($extendedforum->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$extendedforum->id\" $style>".$count."</a>";

            $row = array ($extendedforumlink, $extendedforum->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                if ($extendedforum->forcesubscribe != EXTENDEDFORUM_DISALLOWSUBSCRIBE) {
                    $row[] = extendedforum_get_subscribe_link($extendedforum, $context, array('subscribed' => $strno,
                            'unsubscribed' => $stryes, 'forcesubscribed' => $stryes,
                            'cantsubscribe' => '-'), false, false, true, $subscribed_extendedforums);
                } else {
                    $row[] = '-';
                }
            }

            //If this extendedforum has RSS activated, calculate it
            if ($show_rss) {
                if ($extendedforum->rsstype and $extendedforum->rssarticles) {
                    //Calculate the tolltip text
                    if ($extendedforum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'extendedforum', format_string($extendedforum->name));
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'extendedforum', format_string($extendedforum->name));
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($course->id, $USER->id, 'extendedforum', $extendedforum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $generaltable->data[] = $row;
        }
    }


    // Start of the table for Learning Forums
    $learningtable->head  = array ($strextendedforum, $strdescription, $strdiscussions);
    $learningtable->align = array ('left', 'left', 'center');

    if ($usetracking) {
        $learningtable->head[] = $strunreadposts;
        $learningtable->align[] = 'center';

        $learningtable->head[] = $strtracking;
        $learningtable->align[] = 'center';
    }

    if ($can_subscribe) {
        $learningtable->head[] = $strsubscribed;
        $learningtable->align[] = 'center';
    }

    if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                     isset($CFG->enablerssfeeds) && isset($CFG->extendedforum_enablerssfeeds) &&
                     $CFG->enablerssfeeds && $CFG->extendedforum_enablerssfeeds)) {
        $learningtable->head[] = $strrss;
        $learningtable->align[] = 'center';
    }

    /// Now let's process the learning extendedforums

    if ($course->id != SITEID) {    // Only real courses have learning extendedforums
        // Add extra field for section number, at the front
        if ($course->format == 'weeks' or $course->format == 'weekscss') {
            array_unshift($learningtable->head, $strweek);
        } else {
            array_unshift($learningtable->head, $strsection);
        }
        array_unshift($learningtable->align, 'center');


        if ($learningextendedforums) {
            $currentsection = '';
                foreach ($learningextendedforums as $extendedforum) {
                $cm      = $modinfo->instances['extendedforum'][$extendedforum->id];
                $context = get_context_instance(CONTEXT_MODULE, $cm->id);

                $count = extendedforum_count_discussions($extendedforum, $cm, $course);

                if ($usetracking) {
                    if ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_OFF) {
                        $unreadlink  = '-';
                        $trackedlink = '-';

                    } else {
                        if (isset($untracked[$extendedforum->id])) {
                            $unreadlink  = '-';
                        } else if ($unread = extendedforum_tp_count_extendedforum_unread_posts($cm, $course)) {
                            $unreadlink = '<span class="unread"><a href="view.php?f='.$extendedforum->id.'">'.$unread.'</a>';
                            $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                           $extendedforum->id.'&amp;mark=read"><img src="'.$CFG->pixpath.'/t/clear.gif" alt="'.$strmarkallread.'" /></a></span>';
                        } else {
                            $unreadlink = '<span class="read">0</span>';
                        }

                        if ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_ON) {
                            $trackedlink = $stryes;

                        } else {
                            $options = array('id'=>$extendedforum->id);
                            if (!isset($untracked[$extendedforum->id])) {
                                $trackedlink = print_single_button($CFG->wwwroot.'/mod/extendedforum/settracking.php', $options,$strno , 'post', '_self', true, $strnotrackextendedforum);
                            } else {
                                $trackedlink = print_single_button($CFG->wwwroot.'/mod/extendedforum/settracking.php', $options, $stryes , 'post', '_self', true, $strtrackextendedforum);
                            }
                        }
                    }
                }

                $introoptions->para=false;
                $extendedforum->intro = shorten_text(trim(format_text($extendedforum->intro, FORMAT_HTML, $introoptions)), $CFG->extendedforum_shortpost);

                if ($cm->sectionnum != $currentsection) {
                    $printsection = $cm->sectionnum;
                   // if ($currentsection) {
                     //   $learningtable->data[] = 'hr';
                    //}
                    $currentsection = $cm->sectionnum;
                } else {
                    $printsection = '';
                }

                $extendedforumname = format_string($extendedforum->name,true);;

                if ($cm->visible) {
                    $style = '';
                } else {
                    $style = 'class="dimmed"';
                }
                $extendedforumlink = "<a href=\"view.php?f=$extendedforum->id\" $style>".format_string($extendedforum->name,true)."</a>";
                $discussionlink = "<a href=\"view.php?f=$extendedforum->id\" $style>".$count."</a>";

                $row = array ($printsection, $extendedforumlink, $extendedforum->intro, $discussionlink);
                if ($usetracking) {
                    $row[] = $unreadlink;
                    $row[] = $trackedlink;    // Tracking.
                }

                if ($can_subscribe) {
                    if ($extendedforum->forcesubscribe != EXTENDEDFORUM_DISALLOWSUBSCRIBE) {
                        $row[] = extendedforum_get_subscribe_link($extendedforum, $context, array('subscribed' => $strno,
                            'unsubscribed' => $stryes, 'forcesubscribed' => $stryes,
                            'cantsubscribe' => '-'), false, false, true, $subscribed_extendedforums);
                    } else {
                        $row[] = '-';
                    }
                }

                //If this extendedforum has RSS activated, calculate it
                if ($show_rss) {
                    if ($extendedforum->rsstype and $extendedforum->rssarticles) {
                        //Calculate the tolltip text
                        if ($extendedforum->rsstype == 1) {
                            $tooltiptext = get_string('rsssubscriberssdiscussions', 'extendedforum', format_string($extendedforum->name));
                        } else {
                            $tooltiptext = get_string('rsssubscriberssposts', 'extendedforum', format_string($extendedforum->name));
                        }
                        //Get html code for RSS link
                        $row[] = rss_get_link($course->id, $USER->id, 'extendedforum', $extendedforum->id, $tooltiptext);
                    } else {
                        $row[] = '&nbsp;';
                    }
                }

                $learningtable->data[] = $row;
            }
        }
    }


    /// Output the page
    $navlinks = array();
    $navlinks[] = array('name' => $strextendedforums, 'link' => '', 'type' => 'activity');

    print_header("$course->shortname: $strextendedforums", $course->fullname,
                    build_navigation($navlinks),
                    "", "", true, $searchform, navmenu($course));

    if (!isguest()) {
        print_box_start('subscription');
        echo '<span class="helplink">';
        echo '<a href="index.php?id='.$course->id.'&amp;subscribe=1">'.get_string('allsubscribe', 'extendedforum').'</a>';
        echo '</span><br /><span class="helplink">';
        echo '<a href="index.php?id='.$course->id.'&amp;subscribe=0">'.get_string('allunsubscribe', 'extendedforum').'</a>';
        echo '</span>';
        print_box_end();
        print_box('&nbsp;', 'clearer');
    }

    if ($generalextendedforums) {
        print_heading(get_string('generalextendedforums', 'extendedforum'));
        extendedforum_print_ouil_table($generaltable);
    }

    if ($learningextendedforums) {
        print_heading(get_string('learningextendedforums', 'extendedforum'));
        extendedforum_print_ouil_table($learningtable);
    }

    print_footer($course);

?>
