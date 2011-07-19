<?php  // $Id: view.php,v 1.106.2.20 2009/11/30 17:12:17 sam_marshall Exp $

    require_once('../../config.php');
    require_once('lib.php');
    require_once("$CFG->libdir/rsslib.php");

     
    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);      // If set, changes the layout of the thread
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '');             // search string
    $mark        = optional_param('mark', '', PARAM_ALPHA);  // Used for tracking read posts if user initiated.
    $postid      = optional_param('postid', 0, PARAM_INT);   // Used for tracking read posts if user initiated.
    $lockmessage = optional_param('on_top', 0, PARAM_INT);   //Used for locking a discussion
    
     
    $buttontext = '';

    if ($id) {
        if (! $cm = get_coursemodule_from_id('extendedforum', $id)) {
            error("Course Module ID was incorrect");
        }
        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }
        if (! $extendedforum = get_record("extendedforum", "id", $cm->instance)) {
            error("Forum ID was incorrect");
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strextendedforums = get_string("modulenameplural", "extendedforum");
        $strextendedforum = get_string("modulename", "extendedforum");
        $buttontext = update_module_button($cm->id, $course->id, $strextendedforum);

    } else if ($f) {

        if (! $extendedforum = get_record("extendedforum", "id", $f)) {
            error("Forum ID was incorrect or no longer exists");
        }
        if (! $course = get_record("course", "id", $extendedforum->course)) {
            error("Forum is misconfigured - don't know what course it's from");
        }

        if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
            error("Course Module missing");
        }

        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);

        $strextendedforums = get_string("modulenameplural", "extendedforum");
        $strextendedforum = get_string("modulename", "extendedforum");
        $buttontext = update_module_button($cm->id, $course->id, $strextendedforum);

    } else {
        error('Must specify a course module or a extendedforum ID');
    }

    if (!$buttontext) {
        $buttontext = extendedforum_search_form($course, $search);
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

     if ($mark === 'read' or $mark === 'unread') {
        if ($CFG->extendedforum_usermarksread && extendedforum_tp_can_track_extendedforums($extendedforum) && extendedforum_tp_is_tracked($extendedforum)) {
            if ($mark === 'read') {
                extendedforum_tp_add_read_record($USER->id, $postid);
            } else {
                // unread
                extendedforum_tp_delete_read_records($USER->id, $postid);
            }
        }
    }
/// Print header.
    
    /// Add ajax-related libs
    require_js(array('yui_yahoo', 'yui_event', 'yui_dom', 'yui_connection', 'yui_json'));
    require_js($CFG->wwwroot . '/mod/extendedforum/rate_ajax.js');
    
         
    $navigation = build_navigation('', $cm);
    print_header_simple(format_string($extendedforum->name), "",
                 $navigation, "", "", true, $buttontext, navmenu($course, $cm));
   
    $titleforplus = get_string('opendiscussionthread', 'extendedforum');
    $titleforminus = get_string('closediscussionthread', 'extendedforum') ;
    $global_title_clear_flag = get_string('markread', 'extendedforum');
    $global_title_set_flag =  get_string('marknew', 'extendedforum')  ;
    $alt_flag_on =  get_string('flagon', 'extendedforum') ;
    $alt_flag_off =  get_string('setflag', 'extendedforum') ;
    $global_mark_on = get_string('recommend', 'extendedforum')  ;
    $global_mark_off = get_string('undorecommend', 'extendedforum')  ;
    $global_theme_path = $CFG->themewww .'/'.current_theme() ;
     
   echo '<script type="text/javascript">extendedforum_init("'.  $CFG->wwwroot. '" , "'. $titleforplus . '", "' .
                            $titleforminus . '", "' . $global_title_clear_flag .
                             '" , "' . $global_title_set_flag .
                              '" , "' .  $alt_flag_on . '", "' . $alt_flag_off . '","'. $global_mark_on .'","' . $global_mark_off . '", "'. $global_theme_path .'" )  </script>  ';     
/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'extendedforum'));
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/extendedforum/view.php?id=' . $cm->id);
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

/// Okay, we can show the discussions. Log the extendedforum view.
    if ($cm->id) {
        add_to_log($course->id, "extendedforum", "view extendedforum", "view.php?id=$cm->id", "$extendedforum->id", $cm->id);
    } else {
        add_to_log($course->id, "extendedforum", "view extendedforum", "view.php?f=$extendedforum->id", "$extendedforum->id");
    }



/// Print settings and things across the top
    if ($mode) {
        set_user_preference('extendedforum_displaymode', $mode);
        
    }
     $displaymode = get_user_preferences('extendedforum_displaymode', $CFG->extendedforum_displaymode);
    
 

   
//    print_box_start('extendedforumcontrol clearfix');

//    print_box_start('subscription clearfix');
    echo '<div class="subscription">';

    if (!empty($USER->id) && !has_capability('moodle/legacy:guest', $context, NULL, false)) {
        $SESSION->fromdiscussion = "$FULLME";
        if (extendedforum_is_forcesubscribed($extendedforum)) {
            $streveryoneisnowsubscribed = get_string('everyoneisnowsubscribed', 'extendedforum');
            $strallowchoice = get_string('allowchoice', 'extendedforum');
            echo '<span class="helplink">' . get_string("forcessubscribe", 'extendedforum') . '</span><br />';
            helpbutton("subscription", $strallowchoice, "extendedforum");
            echo '&nbsp;<span class="helplink">';
            if (has_capability('mod/extendedforum:managesubscriptions', $context)) {
                echo "<a title=\"$strallowchoice\" href=\"subscribe.php?id=$extendedforum->id&amp;force=no\">$strallowchoice</a>";
            } else {
                echo $streveryoneisnowsubscribed;
            }
            echo '</span><br />';

        } else if ($extendedforum->forcesubscribe == EXTENDEDFORUM_DISALLOWSUBSCRIBE) {
            $strsubscriptionsoff = get_string('disallowsubscribe','extendedforum');
            echo $strsubscriptionsoff;
            helpbutton("subscription", $strsubscriptionsoff, "extendedforum");
        } else {
            $streveryonecannowchoose = get_string("everyonecannowchoose", "extendedforum");
            $strforcesubscribe = get_string("forcesubscribe", "extendedforum");
            $strshowsubscribers = get_string("showsubscribers", "extendedforum");
            echo '<span class="helplink">' . get_string("allowsallsubscribe", 'extendedforum') . '</span><br />';
            helpbutton("subscription", $strforcesubscribe, "extendedforum");
            echo '&nbsp;';

            if (has_capability('mod/extendedforum:managesubscriptions', $context)) {
                echo "<span class=\"helplink\"><a title=\"$strforcesubscribe\" href=\"subscribe.php?id=$extendedforum->id&amp;force=yes\">$strforcesubscribe</a></span>";
            } else {
                echo '<span class="helplink">'.$streveryonecannowchoose.'</span>';
            }

            if(has_capability('mod/extendedforum:viewsubscribers', $context)){
                echo "<br />";
                echo "<span class=\"helplink\"><a href=\"subscribers.php?id=$extendedforum->id\">$strshowsubscribers</a></span>";
            }

            echo '<div class="helplink" id="subscriptionlink">', extendedforum_get_subscribe_link($extendedforum, $context,
                    array('forcesubscribed' => '', 'cantsubscribe' => '')), '</div>';
        }

        if (extendedforum_tp_can_track_extendedforums($extendedforum)) {
            echo '<div class="helplink" id="trackinglink">'. extendedforum_get_tracking_link($extendedforum). '</div>';
        }

    }

    /// If rss are activated at site and extendedforum level and this extendedforum has rss defined, show link
    if (isset($CFG->enablerssfeeds) && isset($CFG->extendedforum_enablerssfeeds) &&
        $CFG->enablerssfeeds && $CFG->extendedforum_enablerssfeeds && $extendedforum->rsstype and $extendedforum->rssarticles) {

        if ($extendedforum->rsstype == 1) {
            $tooltiptext = get_string("rsssubscriberssdiscussions","extendedforum",format_string($extendedforum->name));
        } else {
            $tooltiptext = get_string("rsssubscriberssposts","extendedforum",format_string($extendedforum->name));
        }
        if (empty($USER->id)) {
            $userid = 0;
        } else {
            $userid = $USER->id;
        }
//        print_box_start('rsslink');
        echo '<span class="wrap rsslink">';
        rss_print_link($course->id, $userid, "extendedforum", $extendedforum->id, $tooltiptext);
        echo '</span>';
//        print_box_end(); // subscription

    }
//    print_box_end(); // subscription
    echo '</div>';

//    print_box_end();  // extendedforumcontrol

//    print_box('&nbsp;', 'clearer');

 
   if (!empty($extendedforum->blockafter) && !empty($extendedforum->blockperiod)) {
        $a->blockafter = $extendedforum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$extendedforum->blockperiod);
        notify(get_string('thisextendedforumisthrottled','extendedforum',$a));
    }

    if ($extendedforum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        notify(get_string('qandanotify','extendedforum'));
    }

    $extendedforum->intro = trim($extendedforum->intro);
   
    switch ($extendedforum->type) {
        case 'single':
            if (! $discussion = get_record("extendedforum_discussions", "extendedforum", $extendedforum->id)) {
                if ($discussions = get_records("extendedforum_discussions", "extendedforum", $extendedforum->id, "timemodified ASC")) {
                    notify("Warning! There is more than one discussion in this extendedforum - using the most recent");
                    $discussion = array_pop($discussions);
                } else {
                    error("Could not find the discussion in this extendedforum");
                }
            }
            if (! $post = extendedforum_get_post_full($discussion->firstpost)) {
                error("Could not find the first post in this extendedforum");
            }
            if ($mode) {
                set_user_preference("extendedforum_displaymode", $mode);
            }

            $canreply    = extendedforum_user_can_post($extendedforum, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/extendedforum:rate', $context);
            $displaymode = get_user_preferences("extendedforum_displaymode", $CFG->extendedforum_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
             extendedforum_print_latest_discussions($course, $extendedforum, -1, 'header', '', -1, -1, $page, $CFG->extendedforum_manydiscussions, $cm, $displaymode);
            //extendedforum_print_discussion($course, $cm, $extendedforum, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            if (!empty($extendedforum->intro)) {
                $options = new stdclass;
                $options->para = false;
                print_box(format_text($extendedforum->intro, FORMAT_MOODLE, $options), 'generalbox', 'intro');
            }
            echo '<p class="mdl-align">';
            if (extendedforum_user_can_post_discussion($extendedforum, null, -1, $cm)) {
                print_string("allowsdiscussions", "extendedforum");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                extendedforum_print_latest_discussions($course, $extendedforum, 0, 'header', '', -1, -1, -1, 0, $cm, $displaymode);
            } else {
                extendedforum_print_latest_discussions($course, $extendedforum, -1, 'header', '', -1, -1, $page, $CFG->extendedforum_manydiscussions, $cm, $displaymode);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                extendedforum_print_latest_discussions($course, $extendedforum, 0, 'header', '', -1, -1, -1, 0, $cm, $displaymode);
            } else {
                extendedforum_print_latest_discussions($course, $extendedforum, -1, 'header', '', -1, -1, $page, $CFG->extendedforum_manydiscussions, $cm, $displaymode);
            }
           
            break;

        default:
            if (!empty($extendedforum->intro)) {
                $options = new stdclass;
                $options->para = false;
                print_box(format_text($extendedforum->intro, FORMAT_MOODLE, $options), 'generalbox', 'intro');
            }
            echo '<br />';
            if (!empty($showall)) {
                extendedforum_print_latest_discussions($course, $extendedforum, 0, 'header', '', -1, -1, -1, 0, $cm, $displaymode);
                
            } else {
                extendedforum_print_latest_discussions($course, $extendedforum, -1, 'header', '', -1, -1, $page, $CFG->extendedforum_manydiscussions, $cm, $displaymode);
              }
             

            break;
    }
    echo '<div class="extendedforumicons" dir="' . get_string('thisdirection').  '">
     <img class="iconmap" width="18" height="18"  src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/new_white.jpg" alt = "' .get_string('newmessage_icons', 'extendedforum'). '">' . get_string('newmessage_icons', 'extendedforum'). 
      '<img class="iconmap" border="0" width="16" height="16" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/addcomment_white.gif"   alt = "'. get_string('replay_icons' , 'extendedforum') . '" />' .get_string('replay_icons' , 'extendedforum')   .
      '<img class="iconmap" border="0" width="16" height="16" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/print.gif" alt = "'. get_string('print' , 'extendedforum') .  '"/>' .   get_string('print' , 'extendedforum') .
        '<img  class="iconmap" border="0" width="15" height="12" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/send.jpg"   alt = "'. get_string('sendbymail' , 'extendedforum') . '"  />' .   get_string('sendbymail' , 'extendedforum')  .
      '<img class="iconmap" border="0" width="13" height="16" src="'  .  $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/simun-small.gif" alt = "'. get_string('personalflag', 'extendedforum') . '" />'   . get_string('personalflag', 'extendedforum')  .
      '<img class="iconmap" border="0" width="18" height="18" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/neiza.gif" alt = "'. get_string('pinnedmessage_icons', 'extendedforum'). '" /> ' .get_string('pinnedmessage_icons', 'extendedforum'). 
        '<img class="iconmap" border="0" width="13" height="13"  src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/hamlaza.gif" alt="' . get_string('teacher_recomend_icon', 'extendedforum') .'">' . get_string('teacher_recomend_icon', 'extendedforum')   .
     '<img class="iconmap" border="0" width="21" height="12" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/merakez_white.png" alt = "'. get_string('coursecoordinator', 'extendedforum') . '" />' . get_string('coursecoordinator', 'extendedforum') .
    '<img class="iconmap" border="0" width="21" height="12" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/manche_white.jpg" alt = "'. get_string('tutor', 'extendedforum')  . '"/>' . get_string('tutor', 'extendedforum') .
   '<br>' ;
    if(!empty($cm->cache->caps['mod/extendedforum:movemessage'])){
      echo '<span class="extendedforummap">' . get_string('extendedforummanagers', 'extendedforum' ) . ':</span>' .
      '<img class="iconmap" border="0" width="13" height="12" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/haavara_white.gif" alt = "'. get_string('movemessage', 'extendedforum') .'" />' .get_string('movemessage', 'extendedforum')  .
      '<img class="iconmap" border="0" width="11" height="11" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/delete_white.gif" alt = "' .get_string('delete', 'extendedforum') . '">' . get_string('delete', 'extendedforum') ;
    }
     echo '</div>'       ;
    print_footer($course);

?>
