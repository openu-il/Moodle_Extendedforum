<?php // $Id: search.php,v 1.86.2.6 2008/04/14 08:50:58 skodak Exp $

    require_once('../../config.php');
    require_once('lib.php');

    $id = required_param('id', PARAM_INT);                  // course id
    $search = trim(optional_param('search', '', PARAM_NOTAGS));  // search string
    $page = optional_param('page', 0, PARAM_INT);   // which page to show
    $perpage = optional_param('perpage', 10, PARAM_INT);   // how many per page
    $showform = optional_param('showform', 0, PARAM_INT);   // Just show the form

    $user    = trim(optional_param('user', '', PARAM_NOTAGS));    // Names to search for
    $userid  = trim(optional_param('userid', 0, PARAM_INT));      // UserID to search for
    $extendedforumid = trim(optional_param('extendedforumid', 0, PARAM_INT));      // ForumID to search for
    $subject = trim(optional_param('subject', '', PARAM_NOTAGS)); // Subject
    $phrase  = trim(optional_param('phrase', '', PARAM_NOTAGS));  // Phrase
    $words   = trim(optional_param('words', '', PARAM_NOTAGS));   // Words
    $fullwords = trim(optional_param('fullwords', '', PARAM_NOTAGS)); // Whole words
    $notwords = trim(optional_param('notwords', '', PARAM_NOTAGS));   // Words we don't want

    $timefromrestrict = optional_param('timefromrestrict', 0, PARAM_INT); // Use starting date
    $fromday = optional_param('fromday', 0, PARAM_INT);      // Starting date
    $frommonth = optional_param('frommonth', 0, PARAM_INT);      // Starting date
    $fromyear = optional_param('fromyear', 0, PARAM_INT);      // Starting date
    $fromhour = optional_param('fromhour', 0, PARAM_INT);      // Starting date
    $fromminute = optional_param('fromminute', 0, PARAM_INT);      // Starting date
    if ($timefromrestrict) {
        $datefrom = make_timestamp($fromyear, $frommonth, $fromday, $fromhour, $fromminute);
    } else {
        $datefrom = optional_param('datefrom', 0, PARAM_INT);      // Starting date
    }

    $timetorestrict = optional_param('timetorestrict', 0, PARAM_INT); // Use ending date
    $today = optional_param('today', 0, PARAM_INT);      // Ending date
    $tomonth = optional_param('tomonth', 0, PARAM_INT);      // Ending date
    $toyear = optional_param('toyear', 0, PARAM_INT);      // Ending date
    $tohour = optional_param('tohour', 0, PARAM_INT);      // Ending date
    $tominute = optional_param('tominute', 0, PARAM_INT);      // Ending date
    if ($timetorestrict) {
        $dateto = make_timestamp($toyear, $tomonth, $today, $tohour, $tominute);
    } else {
        $dateto = optional_param('dateto', 0, PARAM_INT);      // Ending date
    }



    if (empty($search)) {   // Check the other parameters instead
        if (!empty($words)) {
            $search .= ' '.$words;
        }
        if (!empty($userid)) {
            $search .= ' userid:'.$userid;
        }
        if (!empty($extendedforumid)) {
            $search .= ' extendedforumid:'.$extendedforumid;
        }
        if (!empty($user)) {
            $search .= ' '.extendedforum_clean_search_terms($user, 'user:');
        }
        if (!empty($subject)) {
            $search .= ' '.extendedforum_clean_search_terms($subject, 'subject:');
        }
        if (!empty($fullwords)) {
            $search .= ' '.extendedforum_clean_search_terms($fullwords, '+');
        }
        if (!empty($notwords)) {
            $search .= ' '.extendedforum_clean_search_terms($notwords, '-');
        }
        if (!empty($phrase)) {
            $search .= ' "'.$phrase.'"';
        }
        if (!empty($datefrom)) {
            $search .= ' datefrom:'.$datefrom;
        }
        if (!empty($dateto)) {
            $search .= ' dateto:'.$dateto;
        }
        $individualparams = true;
    } else {
        $individualparams = false;
    }

    if ($search) {
        $search = extendedforum_clean_search_terms($search);
    }

    if (! $course = get_record("course", "id", $id)) {
        error("Course id is incorrect.");
    }

    require_course_login($course);

    add_to_log($course->id, "extendedforum", "search", "search.php?id=$course->id&amp;search=".urlencode($search), $search);

    $strextendedforums = get_string("modulenameplural", "extendedforum");
    $strsearch = get_string("search", "extendedforum");
    $strsearchresults = get_string("searchresults", "extendedforum");
    $strpage = get_string("page");

    if (!$search || $showform) {

        $navlinks = array();
        $navlinks[] = array('name' => $strextendedforums, 'link' => "index.php?id=$course->id", 'type' => 'activity');
        $navlinks[] = array('name' => $strsearch, 'link' => '', 'type' => 'title');
        $navigation = build_navigation($navlinks);

        print_header_simple("$strsearch", "", $navigation, 'search.words',
                  "", "", "&nbsp;", navmenu($course));

        extendedforum_print_big_search_form($course);
        print_footer($course);
        exit;
    }

/// We need to do a search now and print results

    $searchterms = str_replace('extendedforumid:', 'instance:', $search);
    $searchterms = explode(' ', $searchterms);

    $searchform = extendedforum_search_form($course, $search);

    $navlinks = array();
    $navlinks[] = array('name' => $strsearch, 'link' => "search.php?id=$course->id", 'type' => 'activityinstance');
    $navlinks[] = array('name' => s($search, true), 'link' => '', 'type' => 'link');
    $navigation = build_navigation($navlinks);


    if (!$posts = extendedforum_search_posts($searchterms, $course->id, $page*$perpage, $perpage, $totalcount)) {
        print_header_simple("$strsearchresults", "", $navigation, 'search.words', "", "", "&nbsp;", navmenu($course));
        print_heading(get_string("nopostscontaining", "extendedforum", $search));

        if (!$individualparams) {
            $words = $search;
        }

        extendedforum_print_big_search_form($course);

        print_footer($course);
        exit;
    }


    print_header_simple("$strsearchresults", "", $navigation, '', "", "",  $searchform, navmenu($course));

    echo '<div class="reportlink">';
    echo '<a href="search.php?id='.$course->id.
                             '&amp;user='.urlencode($user).
                             '&amp;userid='.$userid.
                             '&amp;extendedforumid='.$extendedforumid.
                             '&amp;subject='.urlencode($subject).
                             '&amp;phrase='.urlencode($phrase).
                             '&amp;words='.urlencode($words).
                             '&amp;fullwords='.urlencode($fullwords).
                             '&amp;notwords='.urlencode($notwords).
                             '&amp;dateto='.$dateto.
                             '&amp;datefrom='.$datefrom.
                             '&amp;showform=1'.
                             '">'.get_string('advancedsearch','extendedforum').'...</a>';
    echo '</div>';

    print_heading("$strsearchresults: $totalcount");

    print_paging_bar($totalcount, $page, $perpage, "search.php?search=".urlencode(stripslashes($search))."&amp;id=$course->id&amp;perpage=$perpage&amp;");

    //added to implement highlighting of search terms found only in HTML markup
    //fiedorow - 9/2/2005
    $strippedsearch = str_replace('user:','',$search);
    $strippedsearch = str_replace('subject:','',$strippedsearch);
    $strippedsearch = str_replace('&quot;','',$strippedsearch);
    $searchterms = explode(' ', $strippedsearch);    // Search for words independently
    foreach ($searchterms as $key => $searchterm) {
        if (preg_match('/^\-/',$searchterm)) {
            unset($searchterms[$key]);
        } else {
            $searchterms[$key] = preg_replace('/^\+/','',$searchterm);
        }
    }
    $strippedsearch = implode(' ', $searchterms);    // Rebuild the string

    foreach ($posts as $post) {

        // Replace the simple subject with the three items extendedforum name -> thread name -> subject
        // (if all three are appropriate) each as a link.
        if (! $discussion = get_record('extendedforum_discussions', 'id', $post->discussion)) {
            error('Discussion ID was incorrect');
        }
        if (! $extendedforum = get_record('extendedforum', 'id', "$discussion->extendedforum")) {
            error("Could not find extendedforum $discussion->extendedforum");
        }

        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id)) {
            error('Course Module ID was incorrect');
        }

        $post->subject = highlight($strippedsearch, $post->subject);
        $discussion->name = highlight($strippedsearch, $discussion->name);

        $fullsubject = "<a href=\"view.php?f=$extendedforum->id\">".format_string($extendedforum->name,true)."</a>";
        if ($extendedforum->type != 'single') {
            $fullsubject .= " -> <a href=\"discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a>";
            if ($post->parent != 0) {
                $fullsubject .= " -> <a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a>";
            }
        }

        $post->subject = $fullsubject;
        $post->subjectnoformat = true;

        // Identify search terms only found in HTML markup, and add a warning about them to
        // the start of the message text. However, do not do the highlighting here. extendedforum_print_post
        // will do it for us later.
        $missing_terms = "";

        $options = new object();
        $options->trusttext = true;
        $message = highlight($strippedsearch,
                        format_text($post->message, $post->format, $options, $course->id),
                        0, '<fgw9sdpq4>', '</fgw9sdpq4>');

        foreach ($searchterms as $searchterm) {
            if (preg_match("/$searchterm/i",$message) && !preg_match('/<fgw9sdpq4>'.$searchterm.'<\/fgw9sdpq4>/i',$message)) {
                $missing_terms .= " $searchterm";
            }
        }

        if ($missing_terms) {
            $strmissingsearchterms = get_string('missingsearchterms','extendedforum');
            $post->message = '<p class="highlight2">'.$strmissingsearchterms.' '.$missing_terms.'</p>'.$post->message;
        }

        // Prepare a link to the post in context, to be displayed after the extendedforum post.
        $fulllink = "<a href=\"discuss.php?d=$post->discussion#p$post->id\">".get_string("postincontext", "extendedforum")."</a>";

        // Now pring the post.
        extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false, false,
                $fulllink, $strippedsearch, -99, false);
    }

    print_paging_bar($totalcount, $page, $perpage, "search.php?search=".urlencode(stripslashes($search))."&amp;id=$course->id&amp;perpage=$perpage&amp;");

    print_footer($course);



/**
 * @todo Document this function
 */
function extendedforum_print_big_search_form($course) {
    global $CFG, $words, $subject, $phrase, $user, $userid, $fullwords, $notwords, $datefrom, $dateto;

    print_simple_box(get_string('searchextendedforumintro', 'extendedforum'), 'center', '', '', 'searchbox', 'intro');

    print_simple_box_start("center");

    echo "<script type=\"text/javascript\">\n";
    echo "var timefromitems = ['fromday','frommonth','fromyear','fromhour', 'fromminute'];\n";
    echo "var timetoitems = ['today','tomonth','toyear','tohour','tominute'];\n";
    echo "</script>\n";

    echo '<form id="searchform" action="search.php" method="get">';
    echo '<table cellpadding="10" class="searchbox" id="form">';

    echo '<tr>';
    echo '<td class="c0"><label for="words">'.get_string('searchwords', 'extendedforum').'</label>';
    echo '<input type="hidden" value="'.$course->id.'" name="id" alt="" /></td>';
    echo '<td class="c1"><input type="text" size="35" name="words" id="words"value="'.s($words, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="phrase">'.get_string('searchphrase', 'extendedforum').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="phrase" id="phrase" value="'.s($phrase, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="notwords">'.get_string('searchnotwords', 'extendedforum').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="notwords" id="notwords" value="'.s($notwords, true).'" alt="" /></td>';
    echo '</tr>';

    if ($CFG->dbfamily == 'mysql' || $CFG->dbfamily == 'postgres') {
        echo '<tr>';
        echo '<td class="c0"><label for="fullwords">'.get_string('searchfullwords', 'extendedforum').'</label></td>';
        echo '<td class="c1"><input type="text" size="35" name="fullwords" id="fullwords" value="'.s($fullwords, true).'" alt="" /></td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td class="c0">'.get_string('searchdatefrom', 'extendedforum').'</td>';
    echo '<td class="c1">';
    if (empty($datefrom)) {
        $datefromchecked = '';
        $datefrom = make_timestamp(2000, 1, 1, 0, 0, 0);
    }else{
        $datefromchecked = 'checked="checked"';
    }

    echo '<input name="timefromrestrict" type="checkbox" value="1" alt="'.get_string('searchdatefrom', 'extendedforum').'" onclick="return lockoptions(\'searchform\', \'timefromrestrict\', timefromitems)" '.  $datefromchecked . ' /> ';
    print_date_selector('fromday', 'frommonth', 'fromyear', $datefrom);
    print_time_selector('fromhour', 'fromminute', $datefrom);

    echo '<input type="hidden" name="hfromday" value="0" />';
    echo '<input type="hidden" name="hfrommonth" value="0" />';
    echo '<input type="hidden" name="hfromyear" value="0" />';
    echo '<input type="hidden" name="hfromhour" value="0" />';
    echo '<input type="hidden" name="hfromminute" value="0" />';

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0">'.get_string('searchdateto', 'extendedforum').'</td>';
    echo '<td class="c1">';
    if (empty($dateto)) {
        $datetochecked = '';
        $dateto = time()+3600;
    }else{
        $datetochecked = 'checked="checked"';
    }

    echo '<input name="timetorestrict" type="checkbox" value="1" alt="'.get_string('searchdateto', 'extendedforum').'" onclick="return lockoptions(\'searchform\', \'timetorestrict\', timetoitems)" ' .$datetochecked. ' /> ';
    print_date_selector('today', 'tomonth', 'toyear', $dateto);
    print_time_selector('tohour', 'tominute', $dateto);

    echo '<input type="hidden" name="htoday" value="0" />';
    echo '<input type="hidden" name="htomonth" value="0" />';
    echo '<input type="hidden" name="htoyear" value="0" />';
    echo '<input type="hidden" name="htohour" value="0" />';
    echo '<input type="hidden" name="htominute" value="0" />';

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="menuextendedforumid">'.get_string('searchwhichextendedforums', 'extendedforum').'</label></td>';
    echo '<td class="c1">';
    choose_from_menu(extendedforum_menu_list($course), 'extendedforumid', '', get_string('allextendedforums', 'extendedforum'), '');
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="subject">'.get_string('searchsubject', 'extendedforum').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="subject" id="subject" value="'.s($subject, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="user">'.get_string('searchuser', 'extendedforum').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="user" id="user" value="'.s($user, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="submit" colspan="2" align="center">';
    echo '<input type="submit" value="'.get_string('searchextendedforums', 'extendedforum').'" alt="" /></td>';
    echo '</tr>';

    echo '</table>';
    echo '</form>';

    echo "<script type=\"text/javascript\">";
    echo "lockoptions('searchform','timefromrestrict', timefromitems);";
    echo "lockoptions('searchform','timetorestrict', timetoitems);";
    echo "</script>\n";

    print_simple_box_end();
}

/**
 * This function takes each word out of the search string, makes sure they are at least
 * two characters long and returns an array containing every good word.
 *
 * @param string $words String containing space-separated strings to search for
 * @param string $prefix String to prepend to the each token taken out of $words
 * @returns array
 * @todo Take the hardcoded limit out of this function and put it into a user-specified parameter
 */
function extendedforum_clean_search_terms($words, $prefix='') {
    $searchterms = explode(' ', $words);
    foreach ($searchterms as $key => $searchterm) {
        if (strlen($searchterm) < 2) {
            unset($searchterms[$key]);
        } else if ($prefix) {
            $searchterms[$key] = $prefix.$searchterm;
        }
    }
    return trim(implode(' ', $searchterms));
}

/**
 * @todo Document this function
 */
function extendedforum_menu_list($course)  {

    $menu = array();

    $modinfo = get_fast_modinfo($course);

    if (empty($modinfo->instances['extendedforum'])) {
        return $menu;
    }

    foreach ($modinfo->instances['extendedforum'] as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
            continue;
        }
        $menu[$cm->instance] = format_string($cm->name);
    }

    return $menu;
}

?>
