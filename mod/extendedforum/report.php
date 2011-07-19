<?php // $Id: report.php,v 1.21 2007/06/03 16:17:39 skodak Exp $

//  For a given post, shows a report of all the ratings it has

    require_once("../../config.php");
    require_once("lib.php");

    $id   = required_param('id', PARAM_INT);
    $sort = optional_param('sort', '', PARAM_ALPHA);

    if (! $post = get_record('extendedforum_posts', 'id', $id)) {
        error("Post ID was incorrect");
    }

    if (! $discussion = get_record('extendedforum_discussions', 'id', $post->discussion)) {
        error("Discussion ID was incorrect");
    }

    if (! $extendedforum = get_record('extendedforum', 'id', $discussion->extendedforum)) {
        error("Forum ID was incorrect");
    }

    if (! $course = get_record('course', 'id', $extendedforum->course)) {
        error("Course ID was incorrect");
    }

    if (! $cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) {
        error("Course Module ID was incorrect");
    }

    require_login($course, false, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (!$extendedforum->assessed) {
        error("This activity does not use ratings");
    }

    if (!has_capability('mod/extendedforum:viewrating', $context)) {
        error("You do not have the capability to view post ratings");
    }
    if (!has_capability('mod/extendedforum:viewanyrating', $context) and $USER->id != $post->userid) {
        error("You can only look at results for posts that you made");
    }

    switch ($sort) {
        case 'firstname': $sqlsort = "u.firstname ASC"; break;
        case 'rating':    $sqlsort = "r.rating ASC"; break;
        default:          $sqlsort = "r.time ASC";
    }

    $scalemenu = make_grades_menu($extendedforum->scale);

    $strratings = get_string('ratings', 'extendedforum');
    $strrating  = get_string('rating', 'extendedforum');
    $strname    = get_string('name');
    $strtime    = get_string('time');

    print_header("$strratings: ".format_string($post->subject));

    if (!$ratings = extendedforum_get_ratings($post->id, $sqlsort)) {
        error("No ratings for this post: \"".format_string($post->subject)."\"");

    } else {
        echo "<table border=\"0\" cellpadding=\"3\" cellspacing=\"3\" class=\"generalbox\" style=\"width:100%\">";
        echo "<tr>";
        echo "<th class=\"header\" scope=\"col\">&nbsp;</th>";
        echo "<th class=\"header\" scope=\"col\"><a href=\"report.php?id=$post->id&amp;sort=firstname\">$strname</a></th>";
        echo "<th class=\"header\" scope=\"col\" style=\"width:100%\"><a href=\"report.php?id=$post->id&amp;sort=rating\">$strrating</a></th>";
        echo "<th class=\"header\" scope=\"col\"><a href=\"report.php?id=$post->id&amp;sort=time\">$strtime</a></th>";
        echo "</tr>";
        foreach ($ratings as $rating) {
            echo '<tr class="extendedforumpostheader">';
            echo "<td>";
            print_user_picture($rating->id, $extendedforum->course, $rating->picture);
            echo '</td><td>'.fullname($rating).'</td>';
            echo '<td style="white-space:nowrap" align="center" class="rating">'.$scalemenu[$rating->rating]."</td>";
            echo '<td style="white-space:nowrap" align="center" class="time">'.userdate($rating->time)."</td>";
            echo "</tr>\n";
        }
        echo "</table>";
        echo "<br />";
    }

    close_window_button();
    print_footer('none');
?>
