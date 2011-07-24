<?php  // $Id$

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot . '/mod/extendedforum/weblib_ext.php')   ;

/// CONSTANTS ///////////////////////////////////////////////////////////

define('EXTENDEDFORUM_MODE_FLATOLDEST', 1);
define('EXTENDEDFORUM_MODE_FLATNEWEST', -1);
define('EXTENDEDFORUM_MODE_THREADED', 2);
define('EXTENDEDFORUM_MODE_NESTED', 3);
define('EXTENDEDFORUM_MODE_ONLY_DISCUSSION', 4);
define ('EXTENDEDFORUM_MODE_ALL', 5) ;

define('EXTENDEDFORUM_FORCESUBSCRIBE', 1);
define('EXTENDEDFORUM_INITIALSUBSCRIBE', 2);
define('EXTENDEDFORUM_DISALLOWSUBSCRIBE',3);

define('EXTENDEDFORUM_TRACKING_OFF', 0);
define('EXTENDEDFORUM_TRACKING_OPTIONAL', 1);
define('EXTENDEDFORUM_TRACKING_ON', 2);

define('EXTENDEDFORUM_UNSET_POST_RATING', -999);

define ('EXTENDEDFORUM_AGGREGATE_NONE', 0); //no ratings
define ('EXTENDEDFORUM_AGGREGATE_AVG', 1);
define ('EXTENDEDFORUM_AGGREGATE_COUNT', 2);
define ('EXTENDEDFORUM_AGGREGATE_MAX', 3);
define ('EXTENDEDFORUM_AGGREGATE_MIN', 4);
define ('EXTENDEDFORUM_AGGREGATE_SUM', 5);
define ('EMAIL_DIVIDER',
      '---------------------------------------------------------------------\n');
define ('MAX_INDENT' , 6);

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 * @param object $extendedforum add extendedforum instance (with magic quotes)
 * @return int intance id
 */
function extendedforum_add_instance($extendedforum) {
    global $CFG;
    
      if (empty($extendedforum->assessed)) {
          $extendedforum->assessed = 0;
      }

      if (empty($extendedforum->assessed)) {
          $extendedforum->assessed = 0;
      }
    $extendedforum->timemodified = time();
          // Checkboxes aren't sent when they are unchecked :(
    if (empty($extendedforum->multiattach) ) {
	     $extendedforum->multiattach = 0;
    }

    if (empty($extendedforum->assessed)) {
        $extendedforum->assessed = 0;
    }

    if (empty($extendedforum->ratingtime) or empty($extendedforum->assessed)) {
        $extendedforum->assesstimestart  = 0;
        $extendedforum->assesstimefinish = 0;
    }

    if (!$extendedforum->id = insert_record('extendedforum', $extendedforum)) {
        return false;
    }

    if ($extendedforum->type == 'single') {  // Create related discussion.
        $discussion = new object();
        $discussion->course   = $extendedforum->course;
        $discussion->extendedforum    = $extendedforum->id;
        $discussion->name     = $extendedforum->name;
        $discussion->intro    = $extendedforum->intro;
        $discussion->assessed = $extendedforum->assessed;
        $discussion->format   = $extendedforum->type;
        $discussion->mailnow  = false;
        $discussion->groupid  = -1;

        if (! extendedforum_add_discussion($discussion, $discussion->intro)) {
            error('Could not add the discussion for this extendedforum');
        }
    }

    if ($extendedforum->forcesubscribe == EXTENDEDFORUM_INITIALSUBSCRIBE) {
    /// all users should be subscribed initially
    /// Note: extendedforum_get_potential_subscribers should take the extendedforum context,
    /// but that does not exist yet, becuase the extendedforum is only half build at this
    /// stage. However, because the extendedforum is brand new, we know that there are
    /// no role assignments or overrides in the extendedforum context, so using the
    /// course context gives the same list of users.
        $users = extendedforum_get_potential_subscribers(get_context_instance(CONTEXT_COURSE, $extendedforum->course), 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            extendedforum_subscribe($user->id, $extendedforum->id);
        }
    }

    $extendedforum = stripslashes_recursive($extendedforum);
    extendedforum_grade_item_update($extendedforum);

    return $extendedforum->id;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 * @param object $extendedforum extendedforum instance (with magic quotes)
 * @return bool success
 */
function extendedforum_update_instance($extendedforum) {
    global $USER;

    $extendedforum->timemodified = time();
    $extendedforum->id           = $extendedforum->instance;
    
    // Checkboxes aren't sent when they are unchecked :(
    if (empty($extendedforum->multiattach) ) {
	    $extendedforum->multiattach = 0;    
   }
   
    if(empty($extendedforum->hideauthor) )
    {
       $extendedforum->hideauthor = 0;
    }
    if (empty($extendedforum->assessed)) {
        $extendedforum->assessed = 0;
    }

    if (empty($extendedforum->ratingtime) or empty($extendedforum->assessed)) {
        $extendedforum->assesstimestart  = 0;
        $extendedforum->assesstimefinish = 0;
    }

    $oldextendedforum = get_record('extendedforum', 'id', $extendedforum->id);

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire extendedforum
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldextendedforum->assessed<>$extendedforum->assessed) or ($oldextendedforum->scale<>$extendedforum->scale)) {
        extendedforum_update_grades($extendedforum); // recalculate grades for the extendedforum
    }

    if ($extendedforum->type == 'single') {  // Update related discussion and post.
        if (! $discussion = get_record('extendedforum_discussions', 'extendedforum', $extendedforum->id)) {
            if ($discussions = get_records('extendedforum_discussions', 'extendedforum', $extendedforum->id, 'timemodified ASC')) {
                notify('Warning! There is more than one discussion in this extendedforum - using the most recent');
                $discussion = array_pop($discussions);
            } else {
                // try to recover by creating initial discussion - MDL-16262
                $discussion = new object();
                $discussion->course   = $extendedforum->course;
                $discussion->extendedforum    = $extendedforum->id;
                $discussion->name     = $extendedforum->name;
                $discussion->intro    = $extendedforum->intro;
                $discussion->assessed = $extendedforum->assessed;
                $discussion->format   = $extendedforum->type;
                $discussion->mailnow  = false;
                $discussion->groupid  = -1;

                extendedforum_add_discussion($discussion, $discussion->intro);

                if (! $discussion = get_record('extendedforum_discussions', 'extendedforum', $extendedforum->id)) {
                    error('Could not add the discussion for this extendedforum');
                }

            }
        }
        if (! $post = get_record('extendedforum_posts', 'id', $discussion->firstpost)) {
            error('Could not find the first post in this extendedforum discussion');
        }

        $post->subject  = $extendedforum->name;
        $post->message  = $extendedforum->intro;
        $post->modified = $extendedforum->timemodified;
        $post->userid   = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities

        if (! update_record('extendedforum_posts', ($post))) {
            error('Could not update the first post');
        }

        $discussion->name = $extendedforum->name;

        if (! update_record('extendedforum_discussions', ($discussion))) {
            error('Could not update the discussion');
        }
    }

    if (!update_record('extendedforum', $extendedforum)) {
        error('Can not update extendedforum');
    }

    $extendedforum = stripslashes_recursive($extendedforum);
    extendedforum_grade_item_update($extendedforum);

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 * @param int extendedforum instance id
 * @return bool success
 */
function extendedforum_delete_instance($id) {

    if (!$extendedforum = get_record('extendedforum', 'id', $id)) {
        return false;
    }

    $result = true;

    if ($discussions = get_records('extendedforum_discussions', 'extendedforum', $extendedforum->id)) {
        foreach ($discussions as $discussion) {
            if (!extendedforum_delete_discussion($discussion, true)) {
                $result = false;
            }
        }
    }

    if (!delete_records('extendedforum_subscriptions', 'extendedforum', $extendedforum->id)) {
        $result = false;
    }

    extendedforum_tp_delete_read_records(-1, -1, -1, $extendedforum->id);

    if (!delete_records('extendedforum', 'id', $extendedforum->id)) {
        $result = false;
    }

    extendedforum_grade_item_delete($extendedforum);

    return $result;
}


/**
 * Function to be run periodically according to the moodle cron
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers
 * @return void
 */
function extendedforum_cron() {
    global $CFG, $USER;

    $cronuser = clone($USER);
    $site = get_site();

    // all users that are subscribed to any post that needs sending
    $users = array();

    // status arrays
    $mailcount  = array();
    $errorcount = array();

    // caches
    $discussions     = array();
    $extendedforums          = array();
    $courses         = array();
    $coursemodules   = array();
    $subscribedusers = array();


    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier

    if ($posts = extendedforum_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!extendedforum_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = get_record('extendedforum_discussions', 'id', $post->discussion)) {
                    $discussions[$discussionid] = $discussion;
                } else {
                    mtrace('Could not find discussion '.$discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $extendedforumid = $discussions[$discussionid]->extendedforum;
            if (!isset($extendedforums[$extendedforumid])) {
                if ($extendedforum = get_record('extendedforum', 'id', $extendedforumid)) {
                    $extendedforums[$extendedforumid] = $extendedforum;
                } else {
                    mtrace('Could not find extendedforum '.$extendedforumid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $extendedforums[$extendedforumid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = get_record('course', 'id', $courseid)) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$extendedforumid])) {
                if ($cm = get_coursemodule_from_instance('extendedforum', $extendedforumid, $courseid)) {
                    $coursemodules[$extendedforumid] = $cm;
                } else {
                    mtrace('Could not course module for extendedforum '.$extendedforumid);
                    unset($posts[$pid]);
                    continue;
                }
            }


            // caching subscribed users of each extendedforum
            if (!isset($subscribedusers[$extendedforumid])) {
                $modcontext = get_context_instance(CONTEXT_MODULE, $coursemodules[$extendedforumid]->id);
                if ($subusers = extendedforum_subscribed_users($courses[$courseid], $extendedforums[$extendedforumid], 0, $modcontext)) {
                    foreach ($subusers as $postuser) {
                        // do not try to mail users with stopped email
                        if ($postuser->emailstop) {
                            if (!empty($CFG->extendedforum_logblocked)) {
                                add_to_log(SITEID, 'extendedforum', 'mail blocked', '', '', 0, $postuser->id);
                            }
                            continue;
                        }
                        // this user is subscribed to this extendedforum
                        $subscribedusers[$extendedforumid][$postuser->id] = $postuser->id;
                        // this user is a user we have to process later
                        $users[$postuser->id] = $postuser;
                    }
                    unset($subusers); // release memory
                }
            }

            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {

            @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

            // set this so that the capabilities are cached, and environment matches receiving user
            $USER = $userto;

            mtrace('Processing user '.$userto->id);

            // init caches
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();
            $userto->enrolledin    = array();

            // reset the caches
            foreach ($coursemodules as $extendedforumid=>$unused) {
                $coursemodules[$extendedforumid]->cache       = new object();
                $coursemodules[$extendedforumid]->cache->caps = array();
                unset($coursemodules[$extendedforumid]->uservisible);
            }

            foreach ($posts as $pid => $post) {

                // Set up the environment for the post, discussion, extendedforum, course
                $discussion = $discussions[$post->discussion];
                $extendedforum      = $extendedforums[$discussion->extendedforum];
                $course     = $courses[$extendedforum->course];
                $cm         =& $coursemodules[$extendedforum->id];

               
                // Do some checks  to see if we can bail out now
                if (!isset($subscribedusers[$extendedforum->id][$userto->id])) {
                    continue; // user does not subscribe to this extendedforum
                }

                // Verify user is enrollend in course - if not do not send any email
                if (!isset($userto->enrolledin[$course->id])) {
                    $userto->enrolledin[$course->id] = has_capability('moodle/course:view', get_context_instance(CONTEXT_COURSE, $course->id));
                }
                if (!$userto->enrolledin[$course->id]) {
                    // oops - this user should not receive anything from this course
                    continue;
                }

                // Get info about the sending user
                if (array_key_exists($post->userid, $users)) { // we might know him/her already
                    $userfrom = $users[$post->userid];
                } else if ($userfrom = get_record('user', 'id', $post->userid)) {
                    $users[$userfrom->id] = $userfrom; // fetch only once, we can add it to user list, it will be skipped anyway
                } else {
                    mtrace('Could not find user '.$post->userid);
                    continue;
                }

                // setup global $COURSE properly - needed for roles and languages
                course_setup($course);   // More environment

                // Fill caches
                if (!isset($userto->viewfullnames[$extendedforum->id])) {
                    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                    $userto->viewfullnames[$extendedforum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                    $userto->canpost[$discussion->id] = extendedforum_user_can_post($extendedforum, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$extendedforum->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        $users[$userfrom->id]->groups = array();
                    }
                    $userfrom->groups[$extendedforum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    $users[$userfrom->id]->groups[$extendedforum->id] = $userfrom->groups[$extendedforum->id];
                }

                // Make sure groups allow this user to see this email
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
                    if (!groups_group_exists($discussion->groupid)) { // Can't find group
                        continue;                           // Be safe and don't send it to anyone
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
                        continue;
                    }
                }

                // Make sure we're allowed to see it...
                if (!extendedforum_user_can_see_post($extendedforum, $discussion, $post, NULL, $cm)) {
                    mtrace('user '.$userto->id. ' can not see '.$post->id);
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                if ($userto->maildigest > 0) {
                    // This user wants the mails to be in digest form
                    $queue = new object();
                    $queue->userid       = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid       = $post->id;
                    $queue->timemodified = $post->created;
                    if (!insert_record('extendedforum_queue', $queue)) {
                        mtrace("Error: mod/extendedforum/cron.php: Could not queue for digest mail for id $post->id to user $userto->id ($userto->email) .. not trying again.");
                    }
                    continue;
                }


                // Prepare to actually send the post now, and build up the content

                $cleanextendedforumname = str_replace('"', "'", strip_tags(format_string($extendedforum->name)));

                $userfrom->customheaders = array (  // Headers to make emails easier to track
                           'Precedence: Bulk',
                           'List-Id: "'.$cleanextendedforumname.'" <moodleextendedforum'.$extendedforum->id.'@'.$hostname.'>',
                           'List-Help: '.$CFG->wwwroot.'/mod/extendedforum/view.php?f='.$extendedforum->id,
                           'Message-ID: <moodlepost'.$post->id.'@'.$hostname.'>',
                           'In-Reply-To: <moodlepost'.$post->parent.'@'.$hostname.'>',
                           'References: <moodlepost'.$post->parent.'@'.$hostname.'>',
                           'X-Course-Id: '.$course->id,
                           'X-Course-Name: '.format_string($course->fullname, true)
                );

                 if($extendedforum->hideauthor)
                        {
                         $userfrom->role = extendedforum_get_user_main_role($post->userid, $course->id) ;
                       
                         }
                $postsubject = "$course->shortname: ".format_string($post->subject,true);
                $posttext = extendedforum_make_mail_text($course, $extendedforum, $discussion, $post, $userfrom, $userto);
                $posthtml = extendedforum_make_mail_html($course, $extendedforum, $discussion, $post, $userfrom, $userto);

                // Send the post now!

                mtrace('Sending ', '');

                if (!$mailresult = email_to_user($userto, $userfrom, $postsubject, $posttext,
                                                 $posthtml, '', '', $CFG->extendedforum_replytouser)) {
                    mtrace("Error: mod/extendedforum/cron.php: Could not send out mail for id $post->id to user $userto->id".
                         " ($userto->email) .. not trying again.");
                    add_to_log($course->id, 'extendedforum', 'mail error', "discuss.php?d=$discussion->id#p$post->id",
                               substr(format_string($post->subject,true),0,30), $cm->id, $userto->id);
                    $errorcount[$post->id]++;
                } else if ($mailresult === 'emailstop') {
                    // should not be reached anymore - see check above
                } else {
                    $mailcount[$post->id]++;

                // Mark post as read if extendedforum_usermarksread is set off
                    if (!$CFG->extendedforum_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post '.$post->id. ': '.$post->subject);
            }

            // mark processed posts as read
            extendedforum_tp_mark_posts_read($userto, $userto->markposts);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id]." users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                set_field("extendedforum_posts", "mailed", "2", "id", "$post->id");
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    $USER = clone($cronuser);
    course_setup(SITEID);

    $sitetimezone = $CFG->timezone;

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    @set_time_limit(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->digestmailtimelast)) {    // To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    delete_records_select('extendedforum_queue', "timemodified < $weekago");
    mtrace ('Cleaned old digest records');

    if ($CFG->digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending extendedforum digests: '.userdate($timenow, '', $sitetimezone));

        $digestposts_rs = get_recordset_select('extendedforum_queue', "timemodified < $digesttime");

        if (!rs_EOF($digestposts_rs)) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            while ($digestpost = rs_fetch_next_record($digestposts_rs)) {
                if (!isset($users[$digestpost->userid])) {
                    if ($user = get_record('user', 'id', $digestpost->userid)) {
                        $users[$digestpost->userid] = $user;
                    } else {
                        continue;
                    }
                }
                $postuser = $users[$digestpost->userid];
                if ($postuser->emailstop) {
                    if (!empty($CFG->extendedforum_logblocked)) {
                        add_to_log(SITEID, 'extendedforum', 'mail blocked', '', '', 0, $postuser->id);
                    }
                    continue;
                }

                if (!isset($posts[$digestpost->postid])) {
                    if ($post = get_record('extendedforum_posts', 'id', $digestpost->postid)) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = get_record('extendedforum_discussions', 'id', $discussionid)) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $extendedforumid = $discussions[$discussionid]->extendedforum;
                if (!isset($extendedforums[$extendedforumid])) {
                    if ($extendedforum = get_record('extendedforum', 'id', $extendedforumid)) {
                        $extendedforums[$extendedforumid] = $extendedforum;
                    } else {
                        continue;
                    }
                }

                $courseid = $extendedforums[$extendedforumid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = get_record('course', 'id', $courseid)) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$extendedforumid])) {
                    if ($cm = get_coursemodule_from_instance('extendedforum', $extendedforumid, $courseid)) {
                        $coursemodules[$extendedforumid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            rs_close($digestposts_rs); /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

                $USER = $cronuser;
                course_setup(SITEID); // reset cron user language, theme and timezone settings

                mtrace(get_string('processingdigest', 'extendedforum', $userid), '... ');

                // First of all delete all the queue entries for this user
                delete_records_select('extendedforum_queue', "userid = $userid AND timemodified < $digesttime");
                $userto = $users[$userid];

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                $USER = $userto;
                course_setup(SITEID);

                // init caches
                $userto->viewfullnames = array();
                $userto->canpost       = array();
                $userto->markposts     = array();

                $postsubject = get_string('digestmailsubject', 'extendedforum', format_string($site->shortname, true));

                $headerdata = new object();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot.'/user/edit.php?id='.$userid.'&amp;course='.$site->id;

                $posttext = get_string('digestmailheader', 'extendedforum', $headerdata)."\n\n";
                $headerdata->userprefs = '<a target="_blank" href="'.$headerdata->userprefs.'">'.get_string('digestmailprefs', 'extendedforum').'</a>';

                $posthtml = "<head>";
                foreach ($CFG->stylesheets as $stylesheet) {
                    $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                }
                $posthtml .= "</head>\n<body id=\"email\">\n";
                $posthtml .= '<p>'.get_string('digestmailheader', 'extendedforum', $headerdata).'</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    @set_time_limit(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $extendedforum      = $extendedforums[$discussion->extendedforum];
                    $course     = $courses[$extendedforum->course];
                    $cm         = $coursemodules[$extendedforum->id];

                    //override language
                    course_setup($course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$extendedforum->id])) {
                        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                        $userto->viewfullnames[$extendedforum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                        $userto->canpost[$discussion->id] = extendedforum_user_can_post($extendedforum, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strextendedforums      = get_string('extendedforums', 'extendedforum');
                    $canunsubscribe = ! extendedforum_is_forcesubscribed($extendedforum);
                    $canreply       = $userto->canpost[$discussion->id];

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$course->shortname -> $strextendedforums -> ".format_string($extendedforum->name,true);
                    if ($discussion->name != $extendedforum->name) {
                        $posttext  .= " -> ".format_string($discussion->name,true);
                    }
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/extendedforum/index.php?id=$course->id\">$strextendedforums</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/extendedforum/view.php?f=$extendedforum->id\">".format_string($extendedforum->name,true)."</a>";
                    if ($discussion->name == $extendedforum->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/extendedforum/discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                        } else if ($userfrom = get_record('user', 'id', $post->userid)) {
                            $users[$userfrom->id] = $userfrom; // fetch only once, we can add it to user list, it will be skipped anyway
                        } else {
                            mtrace('Could not find user '.$post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$extendedforum->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                $users[$userfrom->id]->groups = array();
                            }
                            $userfrom->groups[$extendedforum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            $users[$userfrom->id]->groups[$extendedforum->id] = $userfrom->groups[$extendedforum->id];
                        }

                        $userfrom->customheaders = array ("Precedence: Bulk");
                        if($extendedforum->hideauthor)
                        {
                         $userform->role = extendedforum_get_user_main_role($post->userid, $course->id) ;
                         }
                        if ($userto->maildigest == 2) {
                            // Subjects only
                            $by = new object();
                            $by->name = fullname($userfrom);
                            $by->date = userdate($post->modified);
                            $posttext .= "\n".format_string($post->subject,true).' '.get_string("bynameondate", "extendedforum", $by);
                            $posttext .= "\n---------------------------------------------------------------------";

                            $by->name = "<a target=\"_blank\" href=\"$CFG->wwwroot/user/view.php?id=$userfrom->id&amp;course=$course->id\">$by->name</a>";
                            $posthtml .= '<div><a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$discussion->id.'#p'.$post->id.'">'.format_string($post->subject,true).'</a> '.get_string("bynameondate", "extendedforum", $by).'</div>';

                        } else {
                            // The full treatment
                            $posttext .= extendedforum_make_mail_text($course, $extendedforum, $discussion, $post, $userfrom, $userto, true);
                            $posthtml .= extendedforum_make_mail_post($course, $extendedforum, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

                        // Create an array of postid's for this user to mark as read.
                            if (!$CFG->extendedforum_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                    }
                    if ($canunsubscribe) {
                        $posthtml .= "\n<div class='mdl-right'><font size=\"1\"><a href=\"$CFG->wwwroot/mod/extendedforum/subscribe.php?id=$extendedforum->id\">".get_string("unsubscribe", "extendedforum")."</a></font></div>";
                    } else {
                        $posthtml .= "\n<div class='mdl-right'><font size=\"1\">".get_string("everyoneissubscribed", "extendedforum")."</font></div>";
                    }
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if ($userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                if (!$mailresult =  email_to_user($userto, $site->shortname, $postsubject, $posttext, $posthtml,
                                                  '', '', $CFG->extendedforum_replytouser)) {
                    mtrace("ERROR!");
                    echo "Error: mod/extendedforum/cron.php: Could not send out digest mail to user $userto->id ($userto->email)... not trying again.\n";
                    add_to_log($course->id, 'extendedforum', 'mail digest error', '', '', $cm->id, $userto->id);
                } else if ($mailresult === 'emailstop') {
                    // should not happen anymore - see check above
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if extendedforum_usermarksread is set off
                    extendedforum_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
    /// We have finishied all digest emails, update $CFG->digestmailtimelast
        set_config('digestmailtimelast', $timenow);
    }

    $USER = $cronuser;
    course_setup(SITEID); // reset cron user language, theme and timezone settings

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'extendedforum', $usermailcount));
    }

    if (!empty($CFG->extendedforum_lastreadclean)) {
        $timenow = time();
        if ($CFG->extendedforum_lastreadclean + (24*3600) < $timenow) {
            set_config('extendedforum_lastreadclean', $timenow);
            mtrace('Removing old extendedforum read tracking info...');
            extendedforum_tp_clean_read_records();
        }
    } else {
        set_config('extendedforum_lastreadclean', time());
    }


    return true;
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @param object $course
 * @param object $extendedforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @return string The email body in plain text format.
 */
function extendedforum_make_mail_text($course, $extendedforum, $discussion, $post, $userfrom, $userto, $bare = false) {
    global $CFG, $USER;

    if (!isset($userto->viewfullnames[$extendedforum->id])) {
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) {
            error('Course Module ID was incorrect');
        }
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$extendedforum->id];
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        $canreply = extendedforum_user_can_post($extendedforum, $discussion, $userto, $cm, $course, $modcontext);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $by = New stdClass;
    $by->name = fullname($userfrom, $viewfullnames);
    $by->date = userdate($post->modified, "", $userto->timezone);

    $strbynameondate = get_string('bynameondate', 'extendedforum', $by);

    $strextendedforums = get_string('extendedforums', 'extendedforum');

    $canunsubscribe = ! extendedforum_is_forcesubscribed($extendedforum);

    $posttext = '';

    if (!$bare) {
        $posttext  = "$course->shortname -> $strextendedforums -> ".format_string($extendedforum->name,true);

        if ($discussion->name != $extendedforum->name) {
            $posttext  .= " -> ".format_string($discussion->name,true);
        }
    }

    $posttext .= "\n---------------------------------------------------------------------\n";
    $posttext .= format_string($post->subject,true);
    if ($bare) {
        $posttext .= " ($CFG->wwwroot/mod/extendedforum/discuss.php?d=$discussion->id#p$post->id)";
    }
    $posttext .= "\n".$strbynameondate."SSSSSSSSS\n";
    $posttext .= "---------------------------------------------------------------------\n";
    $posttext .= format_text_email(trusttext_strip($post->message), $post->format);
    $posttext .= "\n\n";
    if ($post->attachment) {
        $post->course = $course->id;
        $post->extendedforum = $extendedforum->id;
        $posttext .= extendedforum_print_attachments($post, "text");
    }
    if (!$bare && $canreply) {
        $posttext .= "---------------------------------------------------------------------\n";
        $posttext .= get_string("postmailinfo", "extendedforum", $course->shortname)."\n";
        $posttext .= "$CFG->wwwroot/mod/extendedforum/post.php?reply=$post->id\n";
    }
    if (!$bare && $canunsubscribe) {
        $posttext .= "\n---------------------------------------------------------------------\n";
        $posttext .= get_string("unsubscribe", "extendedforum");
        $posttext .= ": $CFG->wwwroot/mod/extendedforum/subscribe.php?id=$extendedforum->id\n";
    }

    return $posttext;
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @param object $course
 * @param object $extendedforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @return string The email text in HTML format
 */
function extendedforum_make_mail_html($course, $extendedforum, $discussion, $post, $userfrom, $userto) {
    global $CFG;

    if ($userto->mailformat != 1) {  // Needs to be HTML
        return '';
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = extendedforum_user_can_post($extendedforum, $discussion, $userto);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $strextendedforums = get_string('extendedforums', 'extendedforum');
    $canunsubscribe = ! extendedforum_is_forcesubscribed($extendedforum);

    $posthtml = '<head>';
    foreach ($CFG->stylesheets as $stylesheet) {
        $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
    }
    $posthtml .= '</head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $posthtml .= '<div class="navbar">'.
    '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/index.php?id='.$course->id.'">'.$strextendedforums.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/view.php?f='.$extendedforum->id.'">'.format_string($extendedforum->name,true).'</a>';
    if ($discussion->name == $extendedforum->name) {
        $posthtml .= '</div>';
    } else {
        $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$discussion->id.'">'.
                     format_string($discussion->name,true).'</a></div>';
    }
    $posthtml .= extendedforum_make_mail_post($course, $extendedforum, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

    if ($canunsubscribe) {
        $posthtml .= '<hr /><div class="mdl-align unsubscribelink">
                      <a href="'.$CFG->wwwroot.'/mod/extendedforum/subscribe.php?id='.$extendedforum->id.'">'.get_string('unsubscribe', 'extendedforum').'</a>&nbsp;
                      <a href="'.$CFG->wwwroot.'/mod/extendedforum/unsubscribeall.php">'.get_string('unsubscribeall', 'extendedforum').'</a></div>';
    }

    $posthtml .= '</body>';

    return $posthtml;
}


/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $extendedforum
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function extendedforum_user_outline($course, $user, $mod, $extendedforum) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'extendedforum', $extendedforum->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = extendedforum_count_user_posts($extendedforum->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new object();
        $result->info = get_string("numposts", "extendedforum", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new object();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }
    return NULL;
}


/**
 *
 */
function extendedforum_user_complete($course, $user, $mod, $extendedforum) {
    global $CFG,$USER;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'extendedforum', $extendedforum->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo '<p>'.get_string('grade').': '.$grade->str_long_grade.'</p>';
        if ($grade->str_feedback) {
            echo '<p>'.get_string('feedback').': '.$grade->str_feedback.'</p>';
        }
    }

    if ($posts = extendedforum_get_user_posts($extendedforum->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) {
            error('Course Module ID was incorrect');
        }
        $discussions = extendedforum_get_user_involved_discussions($extendedforum->id, $user->id);

        // preload all user ratings for these discussions - one query only and minimal memory
        $cm->cache->ratings = array();
        $cm->cache->myratings = array();
        if ($postratings = extendedforum_get_all_user_ratings($user->id, $discussions)) {
            foreach ($postratings as $pr) {
                if (!isset($cm->cache->ratings[$pr->postid])) {
                    $cm->cache->ratings[$pr->postid] = array();
                }
                $cm->cache->ratings[$pr->postid][$pr->id] = $pr->rating;

                if ($pr->userid == $USER->id) {
                    $cm->cache->myratings[$pr->postid] = $pr->rating;
                }
            }
            unset($postratings);
        }

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];
            
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

            extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false, $ratings);

        }
    } else {
        echo "<p>".get_string("noposts", "extendedforum")."</p>";
    }
}


/**
 *
 */
function extendedforum_print_overview($courses,&$htmlarray) {
    global $USER, $CFG;
    //$LIKE = sql_ilike();//no longer using like in queries. MDL-20578

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$extendedforums = get_all_instances_in_courses('extendedforum',$courses)) {
        return;
    }


    // get all extendedforum logs in ONE query (much better!)
    $sql = "SELECT instance,cmid,l.course,COUNT(l.id) as count FROM {$CFG->prefix}log l "
        ." JOIN {$CFG->prefix}course_modules cm ON cm.id = cmid "
        ." WHERE (";
    foreach ($courses as $course) {
        $sql .= '(l.course = '.$course->id.' AND l.time > '.$course->lastaccess.') OR ';
    }
    $sql = substr($sql,0,-3); // take off the last OR

    $sql .= ") AND l.module = 'extendedforum' AND action = 'add post' "
        ." AND userid != ".$USER->id." GROUP BY cmid,l.course,instance";

    if (!$new = get_records_sql($sql)) {
        $new = array(); // avoid warnings
    }

    // also get all extendedforum tracking stuff ONCE.
    $trackingextendedforums = array();
    foreach ($extendedforums as $extendedforum) {
        if (extendedforum_tp_can_track_extendedforums($extendedforum)) {
            $trackingextendedforums[$extendedforum->id] = $extendedforum;
        }
    }

    if (count($trackingextendedforums) > 0) {
        $cutoffdate = isset($CFG->extendedforum_oldpostdays) ? (time() - ($CFG->extendedforum_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.extendedforum,d.course,COUNT(p.id) AS count '.
            ' FROM '.$CFG->prefix.'extendedforum_posts p '.
            ' JOIN '.$CFG->prefix.'extendedforum_discussions d ON p.discussion = d.id '.
            ' LEFT JOIN '.$CFG->prefix.'extendedforum_read r ON r.postid = p.id AND r.userid = '.$USER->id.' WHERE (';
        foreach ($trackingextendedforums as $track) {
            $sql .= '(d.extendedforum = '.$track->id.' AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = '.get_current_group($track->course).')) OR ';
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= '.$cutoffdate.' AND r.id is NULL GROUP BY d.extendedforum,d.course';

        if (!$unread = get_records_sql($sql)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($new)) {
        return;
    }

    $strextendedforum = get_string('modulename','extendedforum');
    $strnumunread = get_string('overviewnumunread','extendedforum');
    $strnumpostssince = get_string('overviewnumpostssince','extendedforum');

    foreach ($extendedforums as $extendedforum) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($extendedforum->id, $new) && !empty($new[$extendedforum->id])) {
            $count = $new[$extendedforum->id]->count;
        }
        if (array_key_exists($extendedforum->id,$unread)) {
            $thisunread = $unread[$extendedforum->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview extendedforum"><div class="name">'.$strextendedforum.': <a title="'.$strextendedforum.'" href="'.$CFG->wwwroot.'/mod/extendedforum/view.php?f='.$extendedforum->id.'">'.
                $extendedforum->name.'</a></div>';
            $str .= '<div class="info">';
            $str .= $count.' '.$strnumpostssince;
            if (!empty($showunread)) {
                $str .= '<br />'.$thisunread .' '.$strnumunread;
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($extendedforum->course,$htmlarray)) {
                $htmlarray[$extendedforum->course] = array();
            }
            if (!array_key_exists('extendedforum',$htmlarray[$extendedforum->course])) {
                $htmlarray[$extendedforum->course]['extendedforum'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$extendedforum->course]['extendedforum'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function extendedforum_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    if (!$posts = get_records_sql("SELECT p.*, f.type AS extendedforumtype, d.extendedforum, d.groupid,
                                          d.timestart, d.timeend, d.userid AS duserid,
                                          u.firstname, u.lastname, u.email, u.picture
                                     FROM {$CFG->prefix}extendedforum_posts p
                                          JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                                          JOIN {$CFG->prefix}extendedforum f             ON f.id = d.extendedforum
                                          JOIN {$CFG->prefix}user u              ON u.id = p.userid
                                    WHERE p.created > $timestart AND f.course = {$course->id}
                                 ORDER BY p.id ASC")) { // order by initial posting date
         return false;
    }

    $modinfo =& get_fast_modinfo($course);

    $groupmodes = array();
    $cms    = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['extendedforum'][$post->extendedforum])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['extendedforum'][$post->extendedforum];
        if (!$cm->uservisible) {
            continue;
        }
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->extendedforum_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/extendedforum:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $context)) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (is_null($modinfo->groups)) {
                    $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
                }

                if (!array_key_exists($post->groupid, $modinfo->groups[0])) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    print_headline(get_string('newextendedforumposts', 'extendedforum').':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">'.
               '<div class="date">'.userdate($post->modified, $strftimerecent).'</div>'.
               '<div class="name">'.fullname($post, $viewfullnames).'</div>'.
             '</div>';
        echo '<div class="info'.$subjectclass.'">';
        if (empty($post->parent)) {
            echo '"<a href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$post->discussion.'">';
        } else {
            echo '"<a href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$post->discussion.'&amp;parent='.$post->parent.'#p'.$post->id.'">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param int $extendedforumid id of extendedforum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function extendedforum_get_user_grades($extendedforum, $userid=0) {
    global $CFG;

    $user = $userid ? "AND u.id = $userid" : "";

    $aggtype = $extendedforum->assessed;
    switch ($aggtype) {
        case EXTENDEDFORUM_AGGREGATE_COUNT :
            $sql = "SELECT u.id, u.id AS userid, COUNT(fr.rating) AS rawgrade
                      FROM {$CFG->prefix}user u, {$CFG->prefix}extendedforum_posts fp,
                           {$CFG->prefix}extendedforum_ratings fr, {$CFG->prefix}extendedforum_discussions fd
                     WHERE u.id = fp.userid AND fp.discussion = fd.id AND fr.post = fp.id
                           AND fr.userid != u.id AND fd.extendedforum = $extendedforum->id
                           $user
                  GROUP BY u.id";
            break;
        case EXTENDEDFORUM_AGGREGATE_MAX :
            $sql = "SELECT u.id, u.id AS userid, MAX(fr.rating) AS rawgrade
                      FROM {$CFG->prefix}user u, {$CFG->prefix}extendedforum_posts fp,
                           {$CFG->prefix}extendedforum_ratings fr, {$CFG->prefix}extendedforum_discussions fd
                     WHERE u.id = fp.userid AND fp.discussion = fd.id AND fr.post = fp.id
                           AND fr.userid != u.id AND fd.extendedforum = $extendedforum->id
                           $user
                  GROUP BY u.id";
            break;
        case EXTENDEDFORUM_AGGREGATE_MIN :
            $sql = "SELECT u.id, u.id AS userid, MIN(fr.rating) AS rawgrade
                      FROM {$CFG->prefix}user u, {$CFG->prefix}extendedforum_posts fp,
                           {$CFG->prefix}extendedforum_ratings fr, {$CFG->prefix}extendedforum_discussions fd
                     WHERE u.id = fp.userid AND fp.discussion = fd.id AND fr.post = fp.id
                           AND fr.userid != u.id AND fd.extendedforum = $extendedforum->id
                           $user
                  GROUP BY u.id";
            break;
        case EXTENDEDFORUM_AGGREGATE_SUM :
            $sql = "SELECT u.id, u.id AS userid, SUM(fr.rating) AS rawgrade
                     FROM {$CFG->prefix}user u, {$CFG->prefix}extendedforum_posts fp,
                          {$CFG->prefix}extendedforum_ratings fr, {$CFG->prefix}extendedforum_discussions fd
                    WHERE u.id = fp.userid AND fp.discussion = fd.id AND fr.post = fp.id
                          AND fr.userid != u.id AND fd.extendedforum = $extendedforum->id
                          $user
                 GROUP BY u.id";
            break;
        default : //avg
            $sql = "SELECT u.id, u.id AS userid, AVG(fr.rating) AS rawgrade
                      FROM {$CFG->prefix}user u, {$CFG->prefix}extendedforum_posts fp,
                           {$CFG->prefix}extendedforum_ratings fr, {$CFG->prefix}extendedforum_discussions fd
                     WHERE u.id = fp.userid AND fp.discussion = fd.id AND fr.post = fp.id
                           AND fr.userid != u.id AND fd.extendedforum = $extendedforum->id
                           $user
                  GROUP BY u.id";
            break;
    }

    if ($results = get_records_sql($sql)) {
        // it could throw off the grading if count and sum returned a rawgrade higher than scale
        // so to prevent it we review the results and ensure that rawgrade does not exceed the scale, if it does we set rawgrade = scale (i.e. full credit)
        foreach ($results as $rid=>$result) {
            if ($extendedforum->scale >= 0) {
                //numeric
                if ($result->rawgrade > $extendedforum->scale) {
                    $results[$rid]->rawgrade = $extendedforum->scale;
                }
            } else {
                //scales
                if ($scale = get_record('scale', 'id', -$extendedforum->scale)) {
                    $scale = explode(',', $scale->scale);
                    $max = count($scale);
                    if ($result->rawgrade > $max) {
                        $results[$rid]->rawgrade = $max;
                    }
                }
            }
        }
    }

    return $results;
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $extendedforum null means all extendedforums
 * @param int $userid specific user only, 0 mean all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function extendedforum_update_grades($extendedforum=null, $userid=0, $nullifnone=true) {
    global $CFG;

    if ($extendedforum != null) {
        require_once($CFG->libdir.'/gradelib.php');
        if ($grades = extendedforum_get_user_grades($extendedforum, $userid)) {
            extendedforum_grade_item_update($extendedforum, $grades);

        } else if ($userid and $nullifnone) {
            $grade = new object();
            $grade->userid   = $userid;
            $grade->rawgrade = NULL;
            extendedforum_grade_item_update($extendedforum, $grade);

        } else {
            extendedforum_grade_item_update($extendedforum);
        }

    } else {
        $sql = "SELECT f.*, cm.idnumber as cmidnumber
                  FROM {$CFG->prefix}extendedforum f, {$CFG->prefix}course_modules cm, {$CFG->prefix}modules m
                 WHERE m.name='extendedforum' AND m.id=cm.module AND cm.instance=f.id";
        if ($rs = get_recordset_sql($sql)) {
            while ($extendedforum = rs_fetch_next_record($rs)) {
                if ($extendedforum->assessed) {
                    extendedforum_update_grades($extendedforum, 0, false);
                } else {
                    extendedforum_grade_item_update($extendedforum);
                }
            }
            rs_close($rs);
        }
    }
}

/**
 * Create/update grade item for given extendedforum
 *
 * @param object $extendedforum object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function extendedforum_grade_item_update($extendedforum, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname'=>$extendedforum->name, 'idnumber'=>$extendedforum->cmidnumber);

    if (!$extendedforum->assessed or $extendedforum->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($extendedforum->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $extendedforum->scale;
        $params['grademin']  = 0;

    } else if ($extendedforum->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$extendedforum->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/extendedforum', $extendedforum->course, 'mod', 'extendedforum', $extendedforum->id, 0, $grades, $params);
}

/**
 * Delete grade item for given extendedforum
 *
 * @param object $extendedforum object
 * @return object grade_item
 */
function extendedforum_grade_item_delete($extendedforum) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/extendedforum', $extendedforum->course, 'mod', 'extendedforum', $extendedforum->id, 0, NULL, array('deleted'=>1));
}


/**
 * Returns the users with data in one extendedforum
 * (users with records in extendedforum_subscriptions, extendedforum_posts and extendedforum_ratings, students)
 * @param int $extendedforumid
 * @return mixed array or false if none
 */
function extendedforum_get_participants($extendedforumid) {

    global $CFG;

    //Get students from extendedforum_subscriptions
    $st_subscriptions = get_records_sql("SELECT DISTINCT u.id, u.id
                                         FROM {$CFG->prefix}user u,
                                              {$CFG->prefix}extendedforum_subscriptions s
                                         WHERE s.extendedforum = '$extendedforumid' and
                                               u.id = s.userid");
    //Get students from extendedforum_posts
    $st_posts = get_records_sql("SELECT DISTINCT u.id, u.id
                                 FROM {$CFG->prefix}user u,
                                      {$CFG->prefix}extendedforum_discussions d,
                                      {$CFG->prefix}extendedforum_posts p
                                 WHERE d.extendedforum = '$extendedforumid' and
                                       p.discussion = d.id and
                                       u.id = p.userid");

    //Get students from extendedforum_ratings
    $st_ratings = get_records_sql("SELECT DISTINCT u.id, u.id
                                   FROM {$CFG->prefix}user u,
                                        {$CFG->prefix}extendedforum_discussions d,
                                        {$CFG->prefix}extendedforum_posts p,
                                        {$CFG->prefix}extendedforum_ratings r
                                   WHERE d.extendedforum = '$extendedforumid' and
                                         p.discussion = d.id and
                                         r.post = p.id and
                                         u.id = r.userid");

    //Add st_posts to st_subscriptions
    if ($st_posts) {
        foreach ($st_posts as $st_post) {
            $st_subscriptions[$st_post->id] = $st_post;
        }
    }
    //Add st_ratings to st_subscriptions
    if ($st_ratings) {
        foreach ($st_ratings as $st_rating) {
            $st_subscriptions[$st_rating->id] = $st_rating;
        }
    }
    //Return st_subscriptions array (it contains an array of unique users)
    return ($st_subscriptions);
}

/**
 * This function returns if a scale is being used by one extendedforum
 * @param int $extendedforumid
 * @param int $scaleid negative number
 * @return bool
 */
function extendedforum_scale_used ($extendedforumid,$scaleid) {

    $return = false;

    $rec = get_record("extendedforum","id","$extendedforumid","scale","-$scaleid");

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of extendedforum
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any extendedforum
 */
function extendedforum_scale_used_anywhere($scaleid) {
    if ($scaleid and record_exists('extendedforum', 'scale', -$scaleid)) {
        return true;
    } else {
        return false;
    }
}


// SQL FUNCTIONS ///////////////////////////////////////////////////////////
 
           
 /**
  *   get a list of extendedforums in course
  *   @param obj course
  *
  */
  function   get_extendedforums_in_course($course) 
  {
     global $CFG;
     
      $sql = "SELECT f.id, f.name
                            FROM {$CFG->prefix}modules m,
                                 {$CFG->prefix}course_modules cm,
                                 {$CFG->prefix}extendedforum f
                            WHERE cm.course = $course->id
                            AND cm.module = m.id AND cm.visible = 1
                            and m.name = 'extendedforum'
                            and cm.instance = f.id
                            and f.type in ('general', 'qanda','news', 'eachuser') 
                            order by f.name asc";
                            
      return get_records_sql($sql)  ;
  
  }   
/**
 * Gets a post with all info ready for extendedforum_print_post
 * Most of these joins are just to get the extendedforum id
 * @param int $postid
 * @return mixed array of posts or false
 */
function extendedforum_get_post_full($postid, $tracking=0, $hideauthor=0 ) {
    global $CFG;
    global $USER;
      
      $post_flag_sl = ", pf.id as postflag";
     $post_flag_join = "LEFT JOIN {$CFG->prefix}extendedforum_flags pf ON (pf.postid = p.id AND pf.userid = $USER->id) ";
      
      $tr_sel = '';
      $tr_join = '';
      
     if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG->extendedforum_oldpostdays * 24 * 3600);
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {$CFG->prefix}extendedforum_read fr ON (fr.postid = p.id AND fr.userid = $USER->id)";
    }
   
   if(!$hideauthor)
    {
      $sql = "SELECT p.*, d.extendedforum, u.firstname, u.lastname, u.email, u.picture, ra.roleid as role, r.name as rolename,  u.imagealt   $tr_sel   $post_flag_sl
                             FROM {$CFG->prefix}extendedforum_posts p
                                  JOIN {$CFG->prefix}extendedforum_discussions d ON p.discussion = d.id
                                  LEFT JOIN {$CFG->prefix}user u ON p.userid = u.id
                                    LEFT JOIN {$CFG->prefix}role_assignments ra ON ( ra.userid = p.userid )
                                   LEFT JOIN {$CFG->prefix}role r ON ra.roleid = r.id
                                  $tr_join
                                   $post_flag_join
                            WHERE p.id = '$postid'
                              AND r.sortorder IN (
     SELECT min( r2.sortorder )
     FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c, {$CFG->prefix}role r2
     WHERE ra2.userid = p.userid AND ra2.contextid = c.id
      AND c.instanceid IN ( d.course, 0 ) AND c.contextlevel IN ( 50, 10 )
      AND c.contextlevel = ( SELECT max( contextlevel )
     FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c, {$CFG->prefix}role r2
     WHERE ra2.userid =p.userid AND ra2.contextid = c.id
       AND c.instanceid IN ( d.course, 0 ) AND c.contextlevel IN ( 50, 10 ) )
       AND ra2.roleid = r2.id )

     AND ra.contextid in (select max( c2.id )
    FROM {$CFG->prefix}role_assignments ra3 , {$CFG->prefix}context c2
     WHERE ra3.userid = p.userid and ra3.contextid = c2.id
        AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) )
                             ";
                         
    
    }
    else //add the role of the user who posted the message
    {
      $sql =  "SELECT p.*, d.extendedforum, r.name AS role , r.name as rolename   $post_flag_sl
               FROM {$CFG->prefix}extendedforum_posts p 
               JOIN {$CFG->prefix}extendedforum_discussions d ON p.discussion = d.id
              LEFT JOIN {$CFG->prefix}role_assignments ra ON ( ra.userid = p.userid )
             LEFT JOIN {$CFG->prefix}role r ON ra.roleid = r.id
               $tr_join
                $post_flag_join
            WHERE p.id = '$postid'
           AND r.sortorder IN (
     SELECT min( r2.sortorder )
     FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c, {$CFG->prefix}role r2
     WHERE ra2.userid = p.userid AND ra2.contextid = c.id
      AND c.instanceid IN ( d.course, 0 ) AND c.contextlevel IN ( 50, 10 )
      AND c.contextlevel = ( SELECT max( contextlevel )
     FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c, {$CFG->prefix}role r2
     WHERE ra2.userid =p.userid AND ra2.contextid = c.id
       AND c.instanceid IN ( d.course, 0 ) AND c.contextlevel IN ( 50, 10 ) )
       AND ra2.roleid = r2.id )

     AND ra.contextid in (select max( c2.id )
    FROM {$CFG->prefix}role_assignments ra3 , {$CFG->prefix}context c2
     WHERE ra3.userid = p.userid and ra3.contextid = c2.id
        AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) )" ;
   
    }
    
  
    return get_record_sql($sql);
}

/**
 * Gets posts with all info ready for extendedforum_print_post
 * We pass extendedforumid in because we always know it so no need to make a
 * complicated join to find it out.
 * @return mixed array of posts or false
 */
function extendedforum_get_discussion_posts($discussion, $sort, $extendedforumid) {
    global $CFG;

    return get_records_sql("SELECT p.*, $extendedforumid AS extendedforum, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                              FROM {$CFG->prefix}extendedforum_posts p
                         LEFT JOIN {$CFG->prefix}user u ON p.userid = u.id
                             WHERE p.discussion = $discussion
                               AND p.parent > 0 $sort");
}

/**
 * Gets all posts in discussion including top parent.
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the extendedforum?
 * @return array of posts
 */
function extendedforum_get_all_discussion_posts($discussionid, $sort, $tracking=false, $hideauthor=0) {
    global $CFG, $USER;

    
    $tr_sel  = "";
    $tr_join = "";

    if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG->extendedforum_oldpostdays * 24 * 3600);
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {$CFG->prefix}extendedforum_read fr ON (fr.postid = p.id AND fr.userid = $USER->id)";
    }

    if(!$hideauthor)
    {
        $sql =    "SELECT p.*, u.firstname, u.lastname, u.email, u.picture, u.imagealt $tr_sel, pf.id as postflag , ra.roleid AS role , r.name as rolename
                                     FROM {$CFG->prefix}extendedforum_posts p
                                          LEFT JOIN {$CFG->prefix}user u ON p.userid = u.id
                                          LEFT JOIN {$CFG->prefix}extendedforum_flags pf ON (pf.postid = p.id AND pf.userid = $USER->id)
                                           INNER JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                                           LEFT JOIN {$CFG->prefix}context c ON ( c.instanceid = d.course
                                          OR c.instanceid =0 ) 
                                         LEFT JOIN {$CFG->prefix}role_assignments ra ON ra.userid = p.userid
                                        INNER JOIN {$CFG->prefix}role r ON ra.roleid = r.id
                                          $tr_join
                                    WHERE p.discussion = $discussionid
                               AND c.contextlevel
                                   IN ( 50, 10 ) 
                                  AND ra.contextid = c.id
                                  AND r.sortorder
                                  IN (
                                 SELECT min( r2.sortorder ) 
                               FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c2, {$CFG->prefix}role r2
                               WHERE ra2.userid = p.userid
                              AND ra2.contextid = c2.id
                                AND c2.instanceid
                                    IN ( d.course, 0 ) 
                                   AND c2.contextlevel
                                    IN ( 50, 10 ) 
                                AND c2.contextlevel = (

                                SELECT max( contextlevel ) 
                               FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c2, {$CFG->prefix}role r2
                                 WHERE ra2.userid =p.userid
                                  AND ra2.contextid = c2.id
                                AND c2.instanceid
                                     IN ( d.course, 0 ) 
                                   AND c2.contextlevel
                                IN ( 50, 10 ) )
                               AND ra2.roleid = r2.id  
                 
                              AND  ra.contextid in (select max( c2.id )
                              FROM {$CFG->prefix}role_assignments ra3 , {$CFG->prefix}context c2
                               where ra3.userid = p.userid and ra3.contextid = c2.id
                                AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) )
         )
                                 ORDER BY $sort";
       
       
      
      
       if (!$posts = get_records_sql($sql)) {
        return array();
      }
    }
    else
    {
       $sqlhideauthor = "SELECT p.* $tr_sel, r.name AS role ,  r.name as rolename , pf.id as postflag
        FROM {$CFG->prefix}extendedforum_posts p
        INNER JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
        LEFT JOIN {$CFG->prefix}context c ON ( c.instanceid = d.course
        OR c.instanceid =0 ) 
        LEFT JOIN {$CFG->prefix}role_assignments ra ON ra.userid = p.userid
         INNER JOIN {$CFG->prefix}role r ON ra.roleid = r.id
         $tr_join
          LEFT JOIN {$CFG->prefix}extendedforum_flags pf ON (pf.postid = p.id AND pf.userid = $USER->id)                                                                                     
        WHERE p.discussion = $discussionid
         AND c.contextlevel
         IN ( 50, 10 ) 
         AND ra.contextid = c.id
         AND r.sortorder
         IN (
            SELECT min( r2.sortorder ) 
             FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c2, {$CFG->prefix}role r2
             WHERE ra2.userid = p.userid
             AND ra2.contextid = c2.id
             AND c2.instanceid
              IN ( d.course, 0 ) 
              AND c2.contextlevel
              IN ( 50, 10 ) 
              AND c2.contextlevel = (

                 SELECT max( contextlevel ) 
                 FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c2, {$CFG->prefix}role r2
                  WHERE ra2.userid =p.userid
                  AND ra2.contextid = c2.id
                  AND c2.instanceid
                  IN ( d.course, 0 ) 
                  AND c2.contextlevel
                IN ( 50, 10 ) )
                 AND ra2.roleid = r2.id  
                 
                AND  ra.contextid in (select max( c2.id )
                FROM {$CFG->prefix}role_assignments ra3 , {$CFG->prefix}context c2
                    where ra3.userid = p.userid and ra3.contextid = c2.id
                 AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) )

         )
         ORDER BY $sort  ";
         
       
       if(!$posts = get_records_sql($sqlhideauthor) )
       {
       
        return array();
       }
    
    }
  
    foreach ($posts as $pid=>$p) {
        if ($tracking) {
            if (extendedforum_tp_is_post_old($p)) {
                 $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    return $posts;
}

/**
 * Gets posts with all info ready for extendedforum_print_post
 * We pass extendedforumid in because we always know it so no need to make a
 * complicated join to find it out.
 */
function extendedforum_get_child_posts($parent, $extendedforumid) {
    global $CFG;

    return get_records_sql("SELECT p.*, $extendedforumid AS extendedforum, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                              FROM {$CFG->prefix}extendedforum_posts p
                         LEFT JOIN {$CFG->prefix}user u ON p.userid = u.id
                             WHERE p.parent = '$parent'
                          ORDER BY p.created ASC");
}

/**
 * An array of extendedforum objects that the user is allowed to read/search through.
 * @param $userid
 * @param $courseid - if 0, we look for extendedforums throughout the whole site.
 * @return array of extendedforum objects, or false if no matches
 *         Forum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function extendedforum_get_readable_extendedforums($userid, $courseid=0) {

    global $CFG, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    if (!$extendedforummod = get_record('modules', 'name', 'extendedforum')) {
        error('The extendedforum module is not installed');
    }

    if ($courseid) {
        $courses = get_records('course', 'id', $courseid);
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        // And admins can see all courses, so pass the $doanything flag enabled
        $courses1 = get_records('course', 'id', SITEID);
        $courses2 = get_my_courses($userid, null, null, true);
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readableextendedforums = array();

    foreach ($courses as $course) {

        $modinfo =& get_fast_modinfo($course);
        if (is_null($modinfo->groups)) {
            $modinfo->groups = groups_get_user_groups($course->id, $userid);
        }

        if (empty($modinfo->instances['extendedforum'])) {
            // hmm, no extendedforums?
            continue;
        }

        $courseextendedforums = get_records('extendedforum', 'course', $course->id);

        foreach ($modinfo->instances['extendedforum'] as $extendedforumid => $cm) {
            if (!$cm->uservisible or !isset($courseextendedforums[$extendedforumid])) {
                continue;
            }
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            $extendedforum = $courseextendedforums[$extendedforumid];

            if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
                continue;
            }

         /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                if (is_null($modinfo->groups)) {
                    $modinfo->groups = groups_get_user_groups($course->id, $USER->id);
                }
                if (empty($CFG->enablegroupings)) {
                    $extendedforum->onlygroups = $modinfo->groups[0];
                    $extendedforum->onlygroups[] = -1;
                } else if (isset($modinfo->groups[$cm->groupingid])) {
                    $extendedforum->onlygroups = $modinfo->groups[$cm->groupingid];
                    $extendedforum->onlygroups[] = -1;
                } else {
                    $extendedforum->onlygroups = array(-1);
                }
            }

        /// hidden timed discussions
            $extendedforum->viewhiddentimedposts = true;
            if (!empty($CFG->extendedforum_enabletimedposts)) {
                if (!has_capability('mod/extendedforum:viewhiddentimedposts', $context)) {
                    $extendedforum->viewhiddentimedposts = false;
                }
            }

        /// qanda access
            if ($extendedforum->type == 'qanda'
                    && !has_capability('mod/extendedforum:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda extendedforum.
                $extendedforum->onlydiscussions = array();  // Holds discussion ids for the discussions
                                                    // the user is allowed to see in this extendedforum.
                if ($discussionspostedin = extendedforum_discussions_user_has_posted_in($extendedforum->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $extendedforum->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readableextendedforums[$extendedforum->id] = $extendedforum;
        }

        unset($modinfo);

    } // End foreach $courses

    //print_object($courses);
    //print_object($readableextendedforums);

    return $readableextendedforums;
}

/**
 * Returns a list of posts found using an array of search terms.
 * @param $searchterms - array of search terms, e.g. word +word -word
 * @param $courseid - if 0, we search through the whole site
 * @param $page
 * @param $recordsperpage=50
 * @param &$totalcount
 * @param $extrasql
 * @return array of posts found
 */
function extendedforum_search_posts($searchterms, $courseid=0, $limitfrom=0, $limitnum=50,
                            &$totalcount, $extrasql='') {
    global $CFG, $USER;
    require_once($CFG->libdir.'/searchlib.php');

   
    $extendedforums = extendedforum_get_readable_extendedforums($USER->id, $courseid);

    if (count($extendedforums) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();

    foreach ($extendedforums as $extendedforumid => $extendedforum) {
        $select = array();

        if (!$extendedforum->viewhiddentimedposts) {
            $select[] = "(d.userid = {$USER->id} OR (d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)))";
        }

        $cm = get_coursemodule_from_instance('extendedforum', $extendedforumid);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        if ($extendedforum->type == 'qanda'
            && !has_capability('mod/extendedforum:viewqandawithoutposting', $context)) {
            if (!empty($extendedforum->onlydiscussions)) {
                $discussionsids = implode(',', $extendedforum->onlydiscussions);
                $select[] = "(d.id IN ($discussionsids) OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($extendedforum->onlygroups)) {
            $groupids = implode(',', $extendedforum->onlygroups);
            $select[] = "d.groupid IN ($groupids)";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.extendedforum = $extendedforumid AND $selects)";
        } else {
            $fullaccess[] = $extendedforumid;
        }
    }

    if ($fullaccess) {
        $fullids = implode(',', $fullaccess);
        $where[] = "(d.extendedforum IN ($fullids))";
    }

    $selectdiscussion = "(".implode(" OR ", $where).")";

    // Some differences SQL
    $LIKE = sql_ilike();
    $NOTLIKE = 'NOT ' . $LIKE;
    if ($CFG->dbfamily == 'postgres') {
        $REGEXP = '~*';
        $NOTREGEXP = '!~*';
    } else {
        $REGEXP = 'REGEXP';
        $NOTREGEXP = 'NOT REGEXP';
    }

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach($searchterms as $searchterm){
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
       
        $searchstring .= $searchterm;
    }
  
    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"","\"",$searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
    // Experimental feature under 1.8! MDL-8830
    // Use alternative text searches if defined
    // This feature only works under mysql until properly implemented for other DBs
    // Requires manual creation of text index for extendedforum_posts before enabling it:
    // CREATE FULLTEXT INDEX foru_post_tix ON [prefix]extendedforum_posts (subject, message)
    // Experimental feature under 1.8! MDL-8830
        if (!empty($CFG->extendedforum_usetextsearches)) {
            $messagesearch = search_generate_text_SQL($parsearray, 'p.message', 'p.subject',
                                                 'p.userid', 'u.id', 'u.firstname',
                                                 'u.lastname', 'p.modified', 'd.extendedforum');
        } else {
            $messagesearch = search_generate_SQL($parsearray, 'p.message', 'p.subject',
                                                 'p.userid', 'u.id', 'u.firstname',
                                                 'u.lastname', 'p.modified', 'd.extendedforum');
        }
    }

    $fromsql = "{$CFG->prefix}extendedforum_posts p,
                  {$CFG->prefix}extendedforum_discussions d,
                  {$CFG->prefix}user u";

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

               
   /* $searchsql = "SELECT p.*,
                         d.extendedforum,
                         u.firstname,
                         u.lastname,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";
       */         
    
      //add role to searchsql to support anonymous forms
    $searchsql = "SELECT p.*, d.extendedforum, u.firstname, u.lastname, u.email, u.picture, u.imagealt, r.name AS role 
                  FROM {$CFG->prefix}extendedforum_posts p  INNER JOIN   {$CFG->prefix}extendedforum_discussions d ON  p.discussion = d.id
                  INNER JOIN {$CFG->prefix}user u ON p.userid = u.id
                   LEFT JOIN {$CFG->prefix}context c ON ( c.instanceid = d.course OR c.instanceid =0 )
                   LEFT JOIN {$CFG->prefix}role_assignments ra ON ra.userid = p.userid 
                   INNER JOIN {$CFG->prefix}role r ON ra.roleid = r.id

                   WHERE $messagesearch AND $selectdiscussion   $extrasql
                   AND c.contextlevel IN ( 50, 10 ) 
                   AND ra.contextid = c.id 
                   AND r.sortorder IN ( SELECT min( r2.sortorder ) 
                   FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c2, {$CFG->prefix}role r2 
                   WHERE ra2.userid = p.userid AND ra2.contextid = c2.id 
                   AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) 
                   AND c2.contextlevel = ( SELECT max( contextlevel ) 
                   FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c2, {$CFG->prefix}role r2 
                   WHERE ra2.userid =p.userid AND ra2.contextid = c2.id 
                   AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) ) 
                   AND ra2.roleid = r2.id ) 
          
ORDER BY p.modified DESC"  ;  

    $totalcount = count_records_sql($countsql);

    return get_records_sql($searchsql, $limitfrom, $limitnum);
}

/**
 * Returns a list of ratings for all posts in discussion
 * @param object $discussion
 * @return array of ratings or false
 */
function extendedforum_get_all_discussion_ratings($discussion) {
    global $CFG;
    return get_records_sql("SELECT r.id, r.userid, p.id AS postid, r.rating
                              FROM {$CFG->prefix}extendedforum_ratings r,
                                   {$CFG->prefix}extendedforum_posts p
                             WHERE r.post = p.id AND p.discussion = $discussion->id
                             ORDER BY p.id ASC");
}

/**
 * Returns a list of ratings for one specific user for all posts in discussion
 * @global object $CFG
 * @param object $discussions the discussions for which we return all ratings
 * @param int $userid the user for who we return all ratings
 * @return object
 */
function extendedforum_get_all_user_ratings($userid, $discussions) {
    global $CFG;


    foreach ($discussions as $discussion) {
     if (!isset($discussionsid)){
         $discussionsid = $discussion->id;
     }
     else {
         $discussionsid .= ",".$discussion->id;
     }
    }

    $sql = "SELECT r.id, r.userid, p.id AS postid, r.rating
                              FROM {$CFG->prefix}extendedforum_ratings r,
                                   {$CFG->prefix}extendedforum_posts p
                             WHERE r.post = p.id AND p.userid = $userid";
    //postgres compability
    if (!isset($discussionsid)) {
       $sql .=" AND p.discussion IN (".$discussionsid.")";
    }
    $sql .=" ORDER BY p.id ASC";

    return get_records_sql($sql);
    

}

/**
 * Returns a list of ratings for a particular post - sorted.
 * @param int $postid
 * @param string $sort
 * @return array of ratings or false
 */
function extendedforum_get_ratings($postid, $sort="u.firstname ASC") {
    global $CFG;
    return get_records_sql("SELECT u.*, r.rating, r.time
                              FROM {$CFG->prefix}extendedforum_ratings r,
                                   {$CFG->prefix}user u
                             WHERE r.post = '$postid'
                               AND r.userid = u.id
                             ORDER BY $sort");
}

/**
 * Returns a list of all new posts that have not been mailed yet
 * @param int $starttime - posts created after this time
 * @param int $endtime - posts created before this
 * @param int $now - used for timed discussions only
 */
function extendedforum_get_unmailed_posts($starttime, $endtime, $now=null) {
    global $CFG;

    if (!empty($CFG->extendedforum_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $timedsql = "AND (d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now))";
    } else {
        $timedsql = "";
    }
    
                    
    return get_records_sql("SELECT p.*, d.course, d.extendedforum
                              FROM {$CFG->prefix}extendedforum_posts p
                                   JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                             WHERE p.mailed = 0
                                   AND p.created >= $starttime
                                   AND (p.created < $endtime OR p.mailnow = 1)
                                   $timedsql
                          ORDER BY p.modified ASC");
                          
                          
                          
}

/**
 * Marks posts before a certain time as being mailed already
 */
function extendedforum_mark_old_posts_as_mailed($endtime, $now=null) {
    global $CFG;
    if (empty($now)) {
        $now = time();
    }

    if (empty($CFG->extendedforum_enabletimedposts)) {
        return execute_sql("UPDATE {$CFG->prefix}extendedforum_posts
                               SET mailed = '1'
                             WHERE (created < $endtime OR mailnow = 1)
                                   AND mailed = 0", false);

    } else {
        return execute_sql("UPDATE {$CFG->prefix}extendedforum_posts
                               SET mailed = '1'
                             WHERE discussion NOT IN (SELECT d.id
                                                        FROM {$CFG->prefix}extendedforum_discussions d
                                                       WHERE d.timestart > $now)
                                   AND (created < $endtime OR mailnow = 1)
                                   AND mailed = 0", false);
    }
}

/**
 * Get all the posts for a user in a extendedforum suitable for extendedforum_print_post
 */
function extendedforum_get_user_posts($extendedforumid, $userid) {
    global $CFG;

    $timedsql = "";
    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('extendedforum', $extendedforumid);
        if (!has_capability('mod/extendedforum:viewhiddentimedposts' , get_context_instance(CONTEXT_MODULE, $cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now))";
        }
    }

    return get_records_sql("SELECT p.*, d.extendedforum, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                              FROM {$CFG->prefix}extendedforum f
                                   JOIN {$CFG->prefix}extendedforum_discussions d ON d.extendedforum = f.id
                                   JOIN {$CFG->prefix}extendedforum_posts p       ON p.discussion = d.id
                                   JOIN {$CFG->prefix}user u              ON u.id = p.userid
                             WHERE f.id = $extendedforumid
                                   AND p.userid = $userid
                                   $timedsql
                          ORDER BY p.modified ASC");
}

/**
 * Get all the discussions user participated in
 * @param int $extendedforumid
 * @param int $userid
 * @return array or false
 */
function extendedforum_get_user_involved_discussions($extendedforumid, $userid) {
    global $CFG;

    $timedsql = "";
    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('extendedforum', $extendedforumid);
        if (!has_capability('mod/extendedforum:viewhiddentimedposts' , get_context_instance(CONTEXT_MODULE, $cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now))";
        }
    }

    return get_records_sql("SELECT DISTINCT d.*
                              FROM {$CFG->prefix}extendedforum f
                                   JOIN {$CFG->prefix}extendedforum_discussions d ON d.extendedforum = f.id
                                   JOIN {$CFG->prefix}extendedforum_posts p       ON p.discussion = d.id
                             WHERE f.id = $extendedforumid
                                   AND p.userid = $userid
                                   $timedsql");
}

/**
 * Get all the posts for a user in a extendedforum suitable for extendedforum_print_post
 * @param int $extendedforumid
 * @param int $userid
 * @return array of counts or false
 */
function extendedforum_count_user_posts($extendedforumid, $userid) {
    global $CFG;

    $timedsql = "";
    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('extendedforum', $extendedforumid);
        if (!has_capability('mod/extendedforum:viewhiddentimedposts' , get_context_instance(CONTEXT_MODULE, $cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now))";
        }
    }

    return get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {$CFG->prefix}extendedforum f
                                  JOIN {$CFG->prefix}extendedforum_discussions d ON d.extendedforum = f.id
                                  JOIN {$CFG->prefix}extendedforum_posts p       ON p.discussion = d.id
                                  JOIN {$CFG->prefix}user u              ON u.id = p.userid
                            WHERE f.id = $extendedforumid
                                  AND p.userid = $userid
                                  $timedsql");
}

/**
 * Given a log entry, return the extendedforum post details for it.
 */
function extendedforum_get_post_from_log($log) {
    global $CFG;

    if ($log->action == "add post") {

        return get_record_sql("SELECT p.*, f.type AS extendedforumtype, d.extendedforum, d.groupid,
                                           u.firstname, u.lastname, u.email, u.picture
                                 FROM {$CFG->prefix}extendedforum_discussions d,
                                      {$CFG->prefix}extendedforum_posts p,
                                      {$CFG->prefix}extendedforum f,
                                      {$CFG->prefix}user u
                                WHERE p.id = '$log->info'
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.extendedforum");


    } else if ($log->action == "add discussion") {

        return get_record_sql("SELECT p.*, f.type AS extendedforumtype, d.extendedforum, d.groupid,
                                           u.firstname, u.lastname, u.email, u.picture
                                 FROM {$CFG->prefix}extendedforum_discussions d,
                                      {$CFG->prefix}extendedforum_posts p,
                                      {$CFG->prefix}extendedforum f,
                                      {$CFG->prefix}user u
                                WHERE d.id = '$log->info'
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.extendedforum");
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 */
function extendedforum_get_firstpost_from_discussion($discussionid) {
    global $CFG;
        
                                          
    return get_record_sql("SELECT p.*
                             FROM {$CFG->prefix}extendedforum_discussions d,
                                  {$CFG->prefix}extendedforum_posts p
                            WHERE d.id = '$discussionid'
                              AND d.firstpost = p.id ");
}

/**
 * Returns an array of counts of replies to each discussion
 */
function extendedforum_count_discussion_replies($extendedforumid, $extendedforumsort="", $limit=-1, $page=-1, $perpage=0) {
    global $CFG;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    if ($extendedforumsort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $extendedforumsort";
        $groupby = ", ".strtolower($extendedforumsort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $extendedforumsort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {$CFG->prefix}extendedforum_posts p
                       JOIN {$CFG->prefix}extendedforum_discussions d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.extendedforum = $extendedforumid
              GROUP BY p.discussion";
        return get_records_sql($sql);

    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {$CFG->prefix}extendedforum_posts p
                       JOIN {$CFG->prefix}extendedforum_discussions d ON p.discussion = d.id
                 WHERE d.extendedforum = $extendedforumid
              GROUP BY p.discussion $groupby
              $orderby";
        return get_records_sql("SELECT * FROM ($sql) sq", $limitfrom, $limitnum);
    }
}

function extendedforum_count_discussions($extendedforum, $cm, $course) {
    global $CFG, $USER;

    static $cache = array();

    $now = round(time(), -2); // db cache friendliness

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->extendedforum_enabletimedposts)) {
            $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {$CFG->prefix}extendedforum f
                       JOIN {$CFG->prefix}extendedforum_discussions d ON d.extendedforum = f.id
                 WHERE f.course = $course->id
                       $timedsql
              GROUP BY f.id";

        if ($counts = get_records_sql($sql)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$extendedforum->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$extendedforum->id];
    }

    if (has_capability('moodle/site:accessallgroups', get_context_instance(CONTEXT_MODULE, $cm->id))) {
        return $cache[$course->id][$extendedforum->id];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo =& get_fast_modinfo($course);
    if (is_null($modinfo->groups)) {
        $modinfo->groups = groups_get_user_groups($course->id, $USER->id);
    }

    if (empty($CFG->enablegroupings)) {
        $mygroups = $modinfo->groups[0];
    } else {
        $mygroups = $modinfo->groups[$cm->groupingid];
    }

    // add all groups posts
    if (empty($mygroups)) {
        $mygroups = array(-1=>-1);
    } else {
        $mygroups[-1] = -1;
    }
    $mygroups = implode(',', $mygroups);

    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {$CFG->prefix}extendedforum_discussions d
             WHERE d.extendedforum = $extendedforum->id AND d.groupid IN ($mygroups)
                   $timedsql";

    return get_field_sql($sql);
}

/**
 * How many unrated posts are in the given discussion for a given user?
 */
function extendedforum_count_unrated_posts($discussionid, $userid) {
    global $CFG;
    if ($posts = get_record_sql("SELECT count(*) as num
                                   FROM {$CFG->prefix}extendedforum_posts
                                  WHERE parent > 0
                                    AND discussion = '$discussionid'
                                    AND userid <> '$userid' ")) {

        if ($rated = get_record_sql("SELECT count(*) as num
                                       FROM {$CFG->prefix}extendedforum_posts p,
                                            {$CFG->prefix}extendedforum_ratings r
                                      WHERE p.discussion = '$discussionid'
                                        AND p.id = r.post
                                        AND r.userid = '$userid'")) {
            $difference = $posts->num - $rated->num;
            if ($difference > 0) {
                return $difference;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}

/**
 * Get all discussions and their posts in a extendedforum
 */
function extendedforum_get_discussions($cm, $extendedforumsort="d.timemodified DESC", $fullpost=true, $unused=-1, $limit=-1, $userlastmodified=false, $page=-1, $perpage=0) {
    global $CFG, $USER;

    $timelimit = '';

    $modcontext = null;

    $now = round(time(), -2);

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (!has_capability('mod/extendedforum:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG->extendedforum_enabletimedposts)) { /// Users must fulfill timed posts

        if (!has_capability('mod/extendedforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= $now AND (d.timeend = 0 OR d.timeend > $now))";
            if (isloggedin()) {
                $timelimit .= " OR d.userid = $USER->id";
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (empty($modcontext)) {
            $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }


    if (empty($extendedforumsort)) {
        $extendedforumsort = "d.timemodified DESC";
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable  = "";
    } else {
        $umfields = ", um.firstname AS umfirstname, um.lastname AS umlastname";
        $umtable  = " LEFT JOIN {$CFG->prefix}user um ON (d.usermodified = um.id)";
    }

  
    $sql = "SELECT $postdata, d.extendedforum, d.course,  d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, d.on_top, 
                   u.firstname, u.lastname, u.email, u.picture, u.imagealt, ra.roleid as role, r.name as rolename $umfields
              FROM {$CFG->prefix}extendedforum_discussions d
                   JOIN {$CFG->prefix}extendedforum_posts p ON p.discussion = d.id
                   JOIN {$CFG->prefix}user u ON p.userid = u.id
                   $umtable
                    LEFT JOIN {$CFG->prefix}role_assignments ra ON ( ra.userid = u.id )
                    LEFT JOIN {$CFG->prefix}role r ON ra.roleid = r.id 
             WHERE d.extendedforum = {$cm->instance} AND p.parent = 0
             AND r.sortorder IN ( SELECT MIN( r2.sortorder ) 
                FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c, {$CFG->prefix}role r2 
            WHERE ra2.userid = p.userid AND ra2.contextid = c.id AND c.instanceid IN ( d.course, 0 ) 
AND c.contextlevel IN ( 50, 10 ) AND c.contextlevel = ( SELECT MAX( contextlevel ) 
FROM {$CFG->prefix}role_assignments ra2, {$CFG->prefix}context c, {$CFG->prefix}role r2 
WHERE ra2.userid =p.userid AND ra2.contextid = c.id AND c.instanceid IN ( d.course, 0 ) 
AND c.contextlevel IN ( 50, 10 ) ) AND ra2.roleid = r2.id ) AND ra.contextid IN 
(SELECT MAX( c2.id ) FROM {$CFG->prefix}role_assignments ra3 , {$CFG->prefix}context c2 
WHERE ra3.userid = p.userid AND ra3.contextid = c2.id 
AND c2.instanceid IN ( d.course, 0 ) AND c2.contextlevel IN ( 50, 10 ) )
                   $timelimit $groupselect
          ORDER BY  on_top desc , $extendedforumsort ";
    
  
    return get_records_sql($sql, $limitfrom, $limitnum);
}

/***
 *
 *   Get all the discussions that has a post that is recommanded
 *
 */
 function extendedforum_get_discussions_recommanded($cm) {
 
     global $CFG, $USER;
     
     $sql = 
   "SELECT d.id,  p.id AS recommand
   FROM {$CFG->prefix}extendedforum_discussions d
   JOIN {$CFG->prefix}extendedforum_posts p ON p.discussion = d.id
  WHERE d.extendedforum = {$cm->instance}
    AND mark = 1
    ";
    
     if ($recommand_array = get_records_sql($sql)) {
        foreach ($recommand_array as $recommand_record) {
            $recommand_array[$recommand_record->id] = $recommand_record->recommand;
        }
        
      
        return $recommand_array;
    } else {
        return array();
    }
 
 
 
 } 
/**
*  Get all the discussions that has a post that is flaged (in extendedforum_flags)
*
**/
function extendedforum_get_discussions_flaged($cm)   {
   global $CFG, $USER;
   
   
   $sql = 
   "SELECT d.id,  s.id AS flaged
   FROM {$CFG->prefix}extendedforum_discussions d
   JOIN {$CFG->prefix}extendedforum_posts p ON p.discussion = d.id
    JOIN {$CFG->prefix}extendedforum_flags s
    ON (s.postid = p.id AND s.userid = $USER->id)
    WHERE d.extendedforum = {$cm->instance}";

   
     if ($flaged_array = get_records_sql($sql)) {
        foreach ($flaged_array as $flag_record) {
            $flaged_array[$flag_record->id] = $flag_record->flaged;
        }
        
      
        return $flaged_array;
    } else {
        return array();
    }

}
function extendedforum_get_discussions_unread($cm) {
    global $CFG, $USER;

    $now = round(time(), -2);

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG->extendedforum_oldpostdays*24*60*60);

    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
    } else {
        $timedsql = "";
    }

    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {$CFG->prefix}extendedforum_discussions d
                   JOIN {$CFG->prefix}extendedforum_posts p     ON p.discussion = d.id
                   LEFT JOIN {$CFG->prefix}extendedforum_read r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.extendedforum = {$cm->instance}
                   AND p.modified >= $cutoffdate AND r.id is NULL
                   $timedsql
                   $groupselect
          GROUP BY d.id";
    
          
    if ($unreads = get_records_sql($sql)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

function extendedforum_get_discussions_count($cm) {
    global $CFG, $USER;

    $now = round(time(), -2);

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = $currentgroup OR d.groupid = -1)";
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG->extendedforum_oldpostdays*24*60*60);

    $timelimit = "";

    if (!empty($CFG->extendedforum_enabletimedposts)) {

        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

        if (!has_capability('mod/extendedforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= $now AND (d.timeend = 0 OR d.timeend > $now))";
            if (isloggedin()) {
                $timelimit .= " OR d.userid = $USER->id";
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {$CFG->prefix}extendedforum_discussions d
                   JOIN {$CFG->prefix}extendedforum_posts p ON p.discussion = d.id
             WHERE d.extendedforum = {$cm->instance} AND p.parent = 0
                   $timelimit $groupselect";

    return get_field_sql($sql);
}


/**
 * Get all discussions started by a particular user in a course (or group)
 * This function no longer used ...
 */
function extendedforum_get_user_discussions($courseid, $userid, $groupid=0) {
    global $CFG;

    if ($groupid) {
        $groupselect = " AND d.groupid = '$groupid' ";
    } else  {
        $groupselect = "";
    }

    return get_records_sql("SELECT p.*, d.groupid, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                                   f.type as extendedforumtype, f.name as extendedforumname, f.id as extendedforumid
                              FROM {$CFG->prefix}extendedforum_discussions d,
                                   {$CFG->prefix}extendedforum_posts p,
                                   {$CFG->prefix}user u,
                                   {$CFG->prefix}extendedforum f
                             WHERE d.course = '$courseid'
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = '$userid'
                               AND d.extendedforum = f.id $groupselect
                          ORDER BY p.created DESC");
}

/**
 * Get the list of potential subscribers to a extendedforum. 
 *
 * @param object $extendedforumcontext the extendedforum context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 */
function extendedforum_get_potential_subscribers($extendedforumcontext, $groupid, $fields, $sort) {
    return get_users_by_capability($extendedforumcontext, 'mod/extendedforum:initialsubscriptions', $fields, $sort, '', '', $groupid, '', false, true);
}

/**
 * Returns list of user objects that are subscribed to this extendedforum
 *
 * @param object $course the course
 * @param extendedforum $extendedforum the extendedforum
 * @param integer $groupid group id, or 0 for all.
 * @param object $context the extendedforum context, to save re-fetching it where possible.
 * @return array list of users.
 */
function extendedforum_subscribed_users($course, $extendedforum, $groupid=0, $context = NULL) {
    global $CFG;

    if ($groupid) {
        $grouptables = ", {$CFG->prefix}groups_members gm ";
        $groupselect = "AND gm.groupid = $groupid AND u.id = gm.userid";

    } else  {
        $grouptables = '';
        $groupselect = '';
    }

    if (extendedforum_is_forcesubscribed($extendedforum)) {
        if (empty($context)) {
            $cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id);
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        }
        $sort = "u.email ASC";
        $fields ="u.id, u.username, u.firstname, u.lastname, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt,
                  u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture, u.timezone, u.theme, u.lang, u.trackextendedforums, u.mnethostid";
        $results = extendedforum_get_potential_subscribers($context, $groupid, $fields, $sort);
    } else {
        $results = get_records_sql("SELECT u.id, u.username, u.firstname, u.lastname, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt,
                                   u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture, u.timezone, u.theme, u.lang, u.trackextendedforums, u.mnethostid
                              FROM {$CFG->prefix}user u,
                                   {$CFG->prefix}extendedforum_subscriptions s $grouptables
                             WHERE s.extendedforum = '$extendedforum->id'
                               AND s.userid = u.id
                               AND u.deleted = 0  $groupselect
                          ORDER BY u.email ASC");
    }

    static $guestid = null;

    if (is_null($guestid)) {
        if ($guest = guest_user()) {
            $guestid = $guest->id;
        } else {
            $guestid = 0;
        }
    }

    // Guest user should never be subscribed to a extendedforum.
    unset($results[$guestid]);

    return $results;
}

/***
 *
 *     count the number of posts in a discussion that are recommanded ($post->mark)
 */
 function count_recommanded_posts($discussionid) 
 {
     global $CFG, $USER;
     
   $sql = "select  count(p.discussion)  AS countme
         FROM  {$CFG->prefix}extendedforum_posts p
         WHERE p.discussion  = $discussionid
          AND mark = 1 "  ;

   $count = get_record_sql ($sql)  ;
 
    return $count->countme; 
 
 } 
/**
 *  count the number of posts in a discussion that are flaged (extendedforum_flags) 
 */
 function count_flaged_posts($discussionid) 
 {
    global $CFG, $USER;
    
    $sql = "select  count(f.id)  as count
         from {$CFG->prefix}extendedforum_flags f
         JOIN {$CFG->prefix}extendedforum_posts p on ( f.postid = p.id AND f.userid = $USER->id)
          JOIN {$CFG->prefix}extendedforum_discussions d on d.id = p.discussion
            where  d.id  = $discussionid"       ;
 
        $count = get_record_sql ($sql)  ;
 
 return $count->count;
 }


  /**
  *
  *  remove the flag from all post in the discussion  for a user
  *  @param obj discussion id 
  *  @param int user id   
  */
       
function delete_all_flags($discussion, $userid)
{
global $CFG;
$where_sql = "userid = $userid and postid in (select id from {$CFG->prefix}extendedforum_posts where discussion = $discussion->id)"    ;
   
   
   delete_records_select('extendedforum_flags',  $where_sql );


}
 /**
  *  recommand post message
  *  @param obj  post
  *  @param int userid    
  *
  *
  **/       
function recommend_post($post, $userid)
{
     $post->mark = 1;
     $post->modified = $userid;
     
    return update_record('extendedforum_posts', $post);
}

/**
 *  unrecommend post message
 *  @param obj post
 *  @paran int userid  
 *
 *
 *
 */    
function undorecommend_post($post, $userid)
{
     $post->mark = 0;
     $post->modified = $userid;
     
    return update_record('extendedforum_posts', $post);

}
/**
 *
 *    add a flag for a post (extendedforum_flags)
 *    @param obj post
 *    @param int userid  
 *
 */   
function add_post_flag( $post, $userid)
{
     $now = time();
   
    
    $extendedforum_flag = new object();
    $extendedforum_flag->userid = $userid;
    $extendedforum_flag->postid = $post->id;
    $extendedforum_flag->flagged_date = $now;
    
    insert_record('extendedforum_flags', $extendedforum_flag); 
}

/**
*   remove a flag from a post (extendedforum_flags)
*   @param obj post
*   @param userid
*/

function delete_flag($post, $userid)
{

  
   delete_records_select('extendedforum_flags', "userid = $userid and postid = $post->id ");    
    
}
// OTHER FUNCTIONS ///////////////////////////////////////////////////////////


function extendedforum_get_course_extendedforum($courseid, $type) {
// How to set up special 1-per-course extendedforums
    global $CFG;

    if ($extendedforums = get_records_select("extendedforum", "course = '$courseid' AND type = '$type'", "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($extendedforums as $extendedforum) {
            return $extendedforum;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $extendedforum->course = $courseid;
    $extendedforum->type = "$type";
    switch ($extendedforum->type) {
        case "news":
            $extendedforum->name  = addslashes(get_string("namenews", "extendedforum"));
            $extendedforum->intro = addslashes(get_string("intronews", "extendedforum"));
            $extendedforum->forcesubscribe = EXTENDEDFORUM_FORCESUBSCRIBE;
            $extendedforum->assessed = 0;
            if ($courseid == SITEID) {
                $extendedforum->name  = get_string("sitenews");
                $extendedforum->forcesubscribe = 0;
            }
            break;
        case "social":
            $extendedforum->name  = addslashes(get_string("namesocial", "extendedforum"));
            $extendedforum->intro = addslashes(get_string("introsocial", "extendedforum"));
            $extendedforum->assessed = 0;
            $extendedforum->forcesubscribe = 0;
            break;
        default:
            notify("That extendedforum type doesn't exist!");
            return false;
            break;
    }

    $extendedforum->timemodified = time();
    $extendedforum->id = insert_record("extendedforum", $extendedforum);

    if (! $module = get_record("modules", "name", "extendedforum")) {
        notify("Could not find extendedforum module!!");
        return false;
    }
    $mod = new object();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $extendedforum->id;
    $mod->section = 0;
    if (! $mod->coursemodule = add_course_module($mod) ) {   // assumes course/lib.php is loaded
        notify("Could not add a new course module to the course '" . format_string($course->fullname) . "'");
        return false;
    }
    if (! $sectionid = add_mod_to_section($mod) ) {   // assumes course/lib.php is loaded
        notify("Could not add the new course module to that section");
        return false;
    }
    if (! set_field("course_modules", "section", $sectionid, "id", $mod->coursemodule)) {
        notify("Could not update the course module with the correct section");
        return false;
    }
    include_once("$CFG->dirroot/course/lib.php");
    rebuild_course_cache($courseid);

    return get_record("extendedforum", "id", "$extendedforum->id");
}


/**
* Given the data about a posting, builds up the HTML to display it and
* returns the HTML in a string.  This is designed for sending via HTML email.
*/
function extendedforum_make_mail_post($course, $extendedforum, $discussion, $post, $userfrom, $userto,
                              $ownpost=false, $reply=false, $link=false, $rate=false, $footer="") {

    global $CFG;
    $cm = 0;
    $output = '';
    
    if (!isset($userto->viewfullnames[$extendedforum->id])) {
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) {
            error('Course Module ID was incorrect');
        }
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$extendedforum->id];
    }

    
    $formattedtext = get_textonly_postmessage($post, $course, $extendedforum, $userfrom, $cm, $viewfullnames);
   
    if ($post->attachment) {
        $post->course = $course->id;
        $output .= '<div class="attachments">';
        $output .= extendedforum_print_attachments($post, 'html');
        $output .= "</div>";
    }

    $output .= $formattedtext;

// Commands
    $commands = array();

    if ($post->parent) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.
                      $post->discussion.'&amp;parent='.$post->parent.'">'.get_string('parent', 'extendedforum').'</a>';
    }

    if ($reply) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/post.php?reply='.$post->id.'">'.
                      get_string('reply', 'extendedforum').'</a>';
    }

    $output .= '<div class="commands">';
    $output .= implode(' | ', $commands);
    $output .= '</div>';

// Context link to post if required
    if ($link) {
        $output .= '<div class="link">';
        $output .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$post->discussion.'&postid='.$post->id.'">'.
                     get_string('postincontext', 'extendedforum').'</a>';
        $output .= '</div>';
    }

    if ($footer) {
        $output .= '<div class="footer">'.$footer.'</div>';
    }
    $output .= '</td></tr></table>'."\n\n";

    return $output;
}

/* gets the post's' message to send by mail
* @param object $post
* @param object $course
* @param object $extendedforum
* @param object $userfrom
* @param boolean $viewfullnames
* @param boolean inmoodle = true - flag if the message is sent to a user in moodle or outside
*                           moodle
*/
  function get_textonly_postmessage($post, $course, $extendedforum, $userfrom, $cm, $viewfullnames)
  {
     global $CFG;
     
     $hideauthor = $extendedforum->hideauthor;
  
// format the post body
    $options = new object();
    $options->para = true;
    $formattedtext = format_text(trusttext_strip($post->message), $post->format, $options, $course->id);
    
    $output =  format_string($post->subject);
      
    $by = new object();
    $by->date = userdate($post->modified, '', $userfrom->timezone);
    
  
     if(!$hideauthor)
    {
      $fullname =  fullname($userfrom, $viewfullnames);;
    
     }
     else
     {
       
      $by->name  = $post->role;
      $fullname =   $post->role;
     }
     
           
       $by->name =  $fullname;  
       $output .= "<br>". get_string('bynameondate', 'extendedforum', $by) . "<br>" ;
     
       
    $output .= $formattedtext;
    
    
    return $output;
  }

 
 
/**
 * Print a extendedforum post
 *
 * @param object $post The post to print.
 * @param integer $courseid The course this post belongs to.
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param object $ratings -- I don't really know --
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all. 
 * @param boolean $dummyifcantsee When extendedforum_user_can_see_post says that
 * @param boolean $istracked is the user tracking messages 
 * @param boolean $can_update_flag can the user update the mark flag of a post 
 * @param int $enableajax     do we enable ajax , if we do we will allow to change a flag
 * @param int $showcommands do we show the commands (print, send , mark etc)
 * @param int $linksubject  do we print a subject line as a linked message above the post box
 * @param int $indent  Indent level  -- Shimon Nagar    1823
 * @param string  oddOReven       even / odd    -- Shimon issue   1823     
 *  
 *  
 *  
 */
function extendedforum_print_post($post, $discussion, $extendedforum, &$cm, $course, $ownpost=false, 
						  $reply=false, $link=false,$ratings=NULL, $footer="", 
						  $highlight="", $post_read=null,$dummyifcantsee=true, 
						  $istracked=null, $can_update_flag = false, $enableajax = 0, 
						  $showcommands = 1, $linksubject=0 ,$indent=0
                          )
    {
    
	//write_log("Hello");
 
    global $USER, $CFG;
	global $discussion_class;	//get Odd or Even
    
	//$oddOReven='odd';
	$oddOReven = $discussion_class; //set Odd or Even
    
    static $stredit, $strdelete, $strreply, $strparent, $strprune, $strprint, $strsend, $strsave,  $strmarknew ,
           $streditmessage ,  $strlock, $strunlock, $strrecommand, $strremoverecommand; 
    static $strpruneheading, $displaymode;
    static $strmarkread, $strmarkunread, $strremoverecommad, $strmovemessage;
     $ratingsmenuused = false;
     
    $post->course = $course->id;
    $post->extendedforum  = $extendedforum->id;
    $hideauthor = $extendedforum->hideauthor;
    $pre_subject = '';
    $td_title_class = '';
    $editcommand = '';
    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new object();
    }
       
    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        $cm->cache->caps['mod/extendedforum:viewdiscussion']   = has_capability('mod/extendedforum:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/extendedforum:editanypost']      = has_capability('mod/extendedforum:editanypost', $modcontext);
        $cm->cache->caps['mod/extendedforum:splitdiscussions'] = has_capability('mod/extendedforum:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/extendedforum:deleteownpost']    = has_capability('mod/extendedforum:deleteownpost', $modcontext);
        $cm->cache->caps['mod/extendedforum:deleteanypost']    = has_capability('mod/extendedforum:deleteanypost', $modcontext);
        $cm->cache->caps['mod/extendedforum:viewanyrating']    = has_capability('mod/extendedforum:viewanyrating', $modcontext);
        $cm->cache->caps['mod/extendedforum:lockmessage']      = has_capability('mod/extendedforum:lockmessage', $modcontext)    ;
        $cm->cache->caps['mod/extendedforum:markmessage']      = has_capability('mod/extendedforum:markmessage', $modcontext)    ;
        $cm->cache->caps['mod/extendedforum:movemessage']      = has_capability('mod/extendedforum:movemessage',  $modcontext)   ;
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = coursemodule_visible_for_user($cm);
    }

    if ($post->parent) { 
         $td_title_class = 'topic'  ;
    } else {
         $td_title_class = 'topic starter';
    }

    //user cannot see post
    if (!extendedforum_user_can_see_post($extendedforum, $discussion, $post, NULL, $cm)) {
        if (!$dummyifcantsee) {
            return;
        }
        
        
        echo '<a id="p'.$post->id.'" name = "p' . $post->id . '" ></a>';
        
        echo "<!--Post indent Level $indent -->";
        echo "\n <!-- start table from function extendedforum_print_post  -->\n" ;
        echo '<table cellspacing="0" border="0" class="extendedforumpost" >';
        echo '<tr class="header">';
       
        if (!$post->parent) { 
            $pre_subject = get_string('discussiontitle','extendedforum'); 
        } 
        
        echo '<td class="'. $td_title_class . '">'    ;
        echo '<div class="subject">'.get_string('extendedforumsubjecthidden','extendedforum').'</div>';
        echo '<div class="author">';
        print_string('extendedforumauthorhidden','extendedforum');
        echo '</div></td></tr>';

        echo '<tr><td class="left side">';
        echo '&nbsp;';

        // Actual content

        echo '</td><td class="content extendedforumcontent">'."\n";
        echo get_string('extendedforumbodyhidden','extendedforum');
        echo '</td></tr></table>';
        return;
    }
    
//user can see the post     
    if(!$post->parent){
      $pre_subject = get_string('discussiontitle','extendedforum');
    }
    
	if (empty($stredit)) {
        $stredit         = get_string('edit', 'extendedforum');
        $strdelete       = get_string('delete', 'extendedforum');
        $strreply        = get_string('reply', 'extendedforum');
        $strparent       = get_string('parent', 'extendedforum');
        $strpruneheading = get_string('pruneheading', 'extendedforum');
        $strprune        = get_string('prune', 'extendedforum');
        $displaymode     = get_user_preferences('extendedforum_displaymode', $CFG->extendedforum_displaymode);
        $strmarkread     = get_string('markread', 'extendedforum');
        $strmarkunread   = get_string('markunread', 'extendedforum');
        $strprint        = get_string('print', 'extendedforum')   ;
        $strsend         = get_string('mailmessage', 'extendedforum')  ;
        $strsave         = get_string('save', 'extendedforum')   ;
        $streditmessage  = get_string('editmessage', 'extendedforum') ;
        $strmarknew      = get_string('marknew', 'extendedforum')      ;
        $strlock         = get_string('lockmessage', 'extendedforum')     ;
        $strunlock       = get_string('unlockmessage', 'extendedforum' );
        $strrecommand    = get_string('recommend', 'extendedforum')  ;
        $strremoverecommad = get_string('undorecommend', 'extendedforum')  ;
        $strmovemessage    = get_string('movemessage', 'extendedforum')     ;
    }
    
      //mark - recommanded
      if($post->mark){
          $class = "marked";
       }else{
          $class = "unmarked"     ;
        }
        
        
    $mark_recommand = get_recommended_img($post, true, $class);
    
    //flag
    $flag_option = ''; 
      if($can_update_flag  == 1 && $enableajax == 1){
        $class = 'unmarked'  ;
        if(isset($post->postflag) ){
           $class = 'marked'     ;
        }
        $flag_option = get_flag_option($post, $class,  true, $enableajax) ;
     }
   $img_ontop = '';
   if($post->parent == 0 ){
    if(isset($discussion->on_top)  ){
        if($discussion->on_top == 1){
            $img_ontop = '<img width="18" height="18"  src="' . $CFG->wwwroot . '/mod/extendedforum/pix/neiza.gif" alt = "'. get_string('lockmessage_alt', 'extendedforum') .  '" />';
        }
    }
   }
   
   //Shimon #1823
    echo "<!--start msg-->";
    //  echo  "<div class=\"msgContainer\" >"; //for later use
    //calculate margin-right #1823
	if ($indent > MAX_INDENT){
        $rindent = ((MAX_INDENT*20)*-1);//   for Minus indent
		$prindent=(MAX_INDENT*20) ;     //  for pluse indent 
      }	else{
    	$rindent=(($indent*20)*-1)   ; //   for Minus indent
		$prindent= ($indent*20)      ;//  for pluse indent 
      }

    //Shimon #1924  add lang dir marging depend
    $currlang = current_language();
    if($currlang=='he_utf8' || $currlang=='ar_utf8'){
   	 	$rindent = "style=\"margin-right: $rindent".'px'.";\"";
		$prindent="style=\"margin-right: $prindent".'px'.";\"" ; 
    }else{
    	$rindent = "style=\"margin-left: $rindent".'px'.";\"";
		$prindent="style=\"margin-left: $prindent".'px'.";\"" ; 
    }
    
    echo "<div id= \"div$post->id\" class=\"message\" $rindent >" ;
    echo '<a id="p'.$post->id.'" name ="p' . $post->id . '"></a>';
    //lala $indent
    echo "<table cellspacing=\"0\" border=0 class=\"$oddOReven $class extendedforumpost\">";
    
    echo "<tr class=\"$oddOReven $class header\">";
    
    if(!$hideauthor){

	echo "<td class=\"$oddOReven $class picture extendedforumpicture\" nowrap=\"nowrap\" valign=\"top\">"; 
      
    $postuser = new object();
    
    $postuser->id        = $post->userid;
    $postuser->firstname = $post->firstname;
    $postuser->lastname  = $post->lastname;
    $postuser->imagealt  = $post->imagealt;
    $postuser->picture   = $post->picture;
    $fullname = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    
    $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.
                $post->userid.'&amp;course='.$course->id.'">'.$fullname.'</a>';
     
     echo "<div class=\"username\" $prindent> <span class='subtitle'>$by->name </span></div>"  ;
    
     // Picture
     echo "<div $prindent>"     ;
        print_user_picture($postuser, $course->id, NULL, 50);
     echo "</div>"      ;
     echo "</td>";
      echo "\t<td class=\"$oddOReven content extendedforumcontent\"  valign=\"top\">";
     }
      else
      {
         echo "\t<td class=\"$oddOReven content extendedforumcontent\"  valign=\"top\" colspan=\"2\">";
      }
    // // we do not show group pic
    //if (isset($cm->cache->usersgroups)) {
    //    $groups = array();
      //  if (isset($cm->cache->usersgroups[$post->userid])) {
      //      foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
         //       $groups[$gid] = $cm->cache->groups[$gid];
        //    }
       // }
   // } else {
       // $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
   // }

    //if ($groups) {
     //   print_group_picture($groups, $course->id, false, false, true);
   // } else {
      //  echo '&nbsp;';
  //  }
    //


     $subject =   $post->subject  ;
    
      $by = new object();
       $by->date = userdate($post->modified, get_string('strftimedaydatetimemodified', 'extendedforum'));
   
      // Actual content
   
    if( ($post->message == '<p></p>') || ( $post->message== '') )
    {
         $subject .= '&nbsp;' . get_string('nocontent', 'extendedforum')  ;
    }
   
   //results are coming from search
    if (!empty($post->subjectnoformat)) {
     $final_subject =  $pre_subject  . $subject . '&nbsp;' . $by->date .  $img_ontop .  $mark_recommand .  $flag_option ;

      echo '<div class="subject">'. $final_subject . '</div>';
        
    } else {
      
         $final_subject =  format_string($pre_subject)   .format_string($subject) . '&nbsp;&nbsp;' . $by->date.  $img_ontop .  $mark_recommand .   $flag_option ;
     
         if($post->parent > 0 && $linksubject == 1) {
        $final_subject = '<a name="postname' . $post->id. '"></a>'.
                      "<a href=\"javascript:void(getPost('postthread" .$post->id . "','imgflag" .$post->id . "_box'," . $post->id . "))\">"   
                      .$final_subject."</a> ";
           }
      
        echo '<div class="subject">' .$final_subject .  '</div>';
        
    }
    
    
    $options = new object();
    $options->para      = false;
    $options->trusttext = true;
    if ($link and (strlen(strip_tags($post->message)) > $CFG->extendedforum_longpost)) {
        // Print shortened version
        echo format_text(extendedforum_shorten_post($post->message), $post->format, $options, $course->id);
        $numwords = count_words(strip_tags($post->message));
        echo '<div class="posting"><a href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$post->discussion.'">';
        echo get_string('readtherest', 'extendedforum');
        echo '</a> ('.get_string('numwords', '', $numwords).')...</div>';
    } else {
        // Print whole message
        echo "<div class=\"$oddOReven posting\">";
        
        if ($highlight) {
            echo highlight($highlight, format_text($post->message, $post->format, $options, $course->id));
        } else {
            echo format_text($post->message, $post->format, $options, $course->id);
        }
        echo '</div>';
    } 
    
  
    if ($post->attachment ) {
       echo '<div >';
        $attachements = extendedforum_print_attachments($post, 'html');
        echo      $attachements ;
        echo '</div>';
    } 
   
   // Ratings

   // $ratingsmenuused = false;
    if (!empty($ratings) and isloggedin()) {
        echo '<div class="ratings">';
        $useratings = true;
        if ($ratings->assesstimestart and $ratings->assesstimefinish) {
            if ($post->created < $ratings->assesstimestart or $post->created > $ratings->assesstimefinish) {
                $useratings = false;
            }
        }
        if ($useratings) {
            $mypost = ($USER->id == $post->userid);

            $canviewallratings = $cm->cache->caps['mod/extendedforum:viewanyrating'];

            if (isset($cm->cache->ratings)) {
                if (isset($cm->cache->ratings[$post->id])) {
                    $allratings = $cm->cache->ratings[$post->id];
                } else {
                    $allratings = array(); // no reatings present yet
                }
            } else {
                $allratings = NULL; // not preloaded
            }

            if (isset($cm->cache->myratings)) {
                if (isset($cm->cache->myratings[$post->id])) {
                    $myrating = $cm->cache->myratings[$post->id];
                } else {
                    $myrating = EXTENDEDFORUM_UNSET_POST_RATING; // no reatings present yet
                }
            } else {
                $myrating = NULL; // not preloaded
            }

            if ($canviewallratings and !$mypost) {
                echo '<span class="extendedforumpostratingtext">' .
                     extendedforum_print_ratings($post->id, $ratings->scale, $extendedforum->assessed, $canviewallratings, $allratings, true) .
                     '</span>';
                if (!empty($ratings->allow)) {
                    echo '&nbsp;';
                    extendedforum_print_rating_menu($post->id, $USER->id, $ratings->scale, $myrating);
                    $ratingsmenuused = true;
                }

            } else if ($mypost) {
                echo '<span class="extendedforumpostratingtext">' .
                     extendedforum_print_ratings($post->id, $ratings->scale, $extendedforum->assessed, true, $allratings, true) .
                     '</span>';

            } else if (!empty($ratings->allow) ) {
                extendedforum_print_rating_menu($post->id, $USER->id, $ratings->scale, $myrating);
                $ratingsmenuused = true;
            }
        }
        echo '</div>';
    }

// Link to post if required

    if ($link) {
        echo '<div class="link">';
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'extendedforum', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'extendedforum', $post->replies);
        }
        echo '<a href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$post->discussion.'">'.
             get_string('discussthistopic', 'extendedforum').'</a>&nbsp;('.$replystring.')';
        echo '</div>';
    }

    if ($footer) {
        echo '<div class="footer">'.$footer.'</div>';
    }
    echo '</td></tr>' ;
     echo "<tr><td class=\"$oddOReven content extendedforumcommands\">"  ;
    
    //print course teacher icon if posted by course teacher
   
     $img_teaching =  extendedforum_get_teacher_img($post->role, 'darkgray')  ;
      echo     $img_teaching ;
    
    
     echo  '</td> ';
    if ($showcommands == 1){
        echo "<td class=\"$oddOReven content extendedforumcommands\" valign=\"top\"  nowrap=\"nowrap\">";
        $age = time() - $post->created;
          // Hack for allow to edit news posts those are not displayed yet until they are displayed
           if (!$post->parent and $extendedforum->type == 'news' and $discussion->timestart > time()) {
              $age = 0;
           }
          //edit command, we allow to edit a message for several minutes after posting
          $editanypost = $cm->cache->caps['mod/extendedforum:editanypost'];
          if ($ownpost ) {
              if (($age < $CFG->maxeditingtime)) {
                 echo  '<a href="'.$CFG->wwwroot.'/mod/extendedforum/post.php?edit='.$post->id.'">'.$stredit.'</a>&nbsp;'  ;
              }
          }

       print_user_commands($discussion, $post, $enableajax, $can_update_flag, $strsend, $strprint, $reply, $strreply, $strmarkread, $strmarknew);
       //admin commands
       print_post_admin_commands($extendedforum, $post, $cm, $discussion , $enableajax , $strunlock, $strlock, $strremoverecommad, $strrecommand, $strmovemessage,
                                      $strdelete)    ;
          
      echo '</td>'    ;
    }
    else
    {
      echo   '<td class="content extendedforumcommands">&nbsp; </td>';
    }
   echo '</tr></table></div><!-- end message -->';
//   echo "</div> <!--close msgcontainer -->\n\n";
 if($istracked && !$post_read )
 {
    echo '<input type = "hidden" id="post_' . $post->id . '" name = "post_read' . $post->id .
           '" value="' . $post_read . '" />'  ;
    
  }         
    

 
    return $ratingsmenuused;
}

/***
 *  prints user command in post for replay, print etc
 *
 *
 */   
function print_user_commands($discussion, $post, $enableajax, $can_update_flag, $strsend, $strprint, $reply, $strreply, $strmarkread, $strmarknew)
{
  global $CFG;
   if(isset($discussion->page)){
              $page =  $discussion->page;
         }
          if(empty($page)  ){
                  $page = 0;
         }
         
        $sendmail = '<a href="forward.php?f=' .$post->extendedforum . '&amp;forward=' . $post->id . '&amp;page=' . $page . '">' . $strsend . '</a>';
        $printimg = '<img border="0" width="16" height="16" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/print.gif" alt = "" title = "" />' ;
        $printmessage  = '<a href="javascript:printObject(\'postview.php?f='. $post->extendedforum. '&amp;view=' . $post->id . '&amp;discussion=' . $post->discussion . '&amp;print=1\')" >' . $printimg . '&nbsp;' .  $strprint . '</a> '; 
        $separator = $CFG->wwwroot . '/mod/extendedforum/pix/blue-seperator.gif'  ;
        $separator_img =   '<img border="0" width="9" height="9" src="' .  $separator. '" alt= "" title = ""/>&nbsp;'     ;
       
          if ($reply) {
            echo  '<a href="'.$CFG->wwwroot.'/mod/extendedforum/post.php?reply='.$post->id. '&amp;page=' . $page . '">';
			echo  '<img border="0" width="16" height="16" src="' . $CFG->themewww  .'/'.current_theme(). '/pix/mod/extendedforum/addcomment.gif"   alt = "" title = "" />&nbsp;' ;
			echo  $strreply.'</a>&nbsp;';
         }
          
        
          echo  $printmessage; 
          echo    '<img border="0" width="15" height="12" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/send.jpg"   alt = "" title = "" />&nbsp;' ;
          echo $sendmail;
          if($can_update_flag && $enableajax) {
              $imgid  = 'imgflag'    . $post->id . '_box' ;
              $flagmark = '<img border="0" width="13" height="16" src="'  .  $CFG->wwwroot . '/mod/extendedforum/pix/simun-small.gif" alt = "" title = "" />' ;
             if($post->postflag){
                echo '&nbsp;<a id="aflag' . $post->id . '" href="javascript:void(change_flag('. $post->id . ','. $post->discussion . ',\'' . $imgid . '\','. $enableajax. '))">' . $flagmark . '&nbsp;' . $strmarkread . '</a>';
             }
              else{
                echo '&nbsp;<a  id="aflag' . $post->id . '" href="javascript:void(change_flag('. $post->id . ','. $post->discussion . ',\'' . $imgid . '\',' .$enableajax. '))">' . $flagmark . '&nbsp;' .  $strmarknew . '</a>';
              }
           }
           
           echo '&nbsp;&nbsp;&nbsp;';

}
 /****
  *  prints admin commands in post for recommand, lock etc
  *
  */     
function  print_post_admin_commands($extendedforum, $post, $cm, $discussion , $enableajax , $strunlock, $strlock, $strremoverecommad, $strrecommand, $strmovemessage,
                                      $strdelete)
{
  global $CFG;
  
   $separator = $CFG->wwwroot . '/mod/extendedforum/pix/red-separator.gif'  ;
   $separator_img =   '<img border="0" width="9" height="9" src="' .  $separator. '" alt= "" title = ""/>&nbsp;'     ;
        
  
 if(!$post->parent){  //lock message for top messages only
                if($cm->cache->caps['mod/extendedforum:lockmessage']) {
                $imageneiza = '<img border="0" width="18" height="18" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/neiza.gif" alt = "" title = "" /> ';
                     if ($discussion->on_top==1) {
                        echo  '<a href="'   . $CFG->wwwroot . '/mod/extendedforum/post.php?on_top=-1&amp;discussionid=' .$post->discussion . '&amp;extendedforumid='. $extendedforum->id .'">' . $imageneiza  . '&nbsp;' . $strunlock.'</a>&nbsp;';
                    }
                       else
                    {
                       echo '<a href="'   . $CFG->wwwroot . '/mod/extendedforum/post.php?on_top=1&amp;discussionid=' .$post->discussion . '&amp;extendedforumid='. $extendedforum->id .'">'. $imageneiza .  '&nbsp;' .  $strlock.'</a>&nbsp;';
                   }
        
                }
    }
               
  
  
   //recommand message option only with ajax
     if($enableajax)
     {
       if($cm->cache->caps['mod/extendedforum:markmessage'])
       {
         $recommanding = '<img border="0" width="13" height="13" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/hamlaza_brightback.gif" alt = "" title = "" />';
         if($post->mark == 1)
         {
           echo  '&nbsp;'  . $recommanding .'&nbsp;<a href="javascript:void(recommend('. $post->id .',1,'. $post->discussion . '))" id ="anchor_recommend_' . $post->id . '">'.   $strremoverecommad.'</a>&nbsp;';
         }
         else
         {
            echo '&nbsp;' . $recommanding  .'&nbsp;<a href="javascript:void(recommend('. $post->id .',1,' . $post->discussion . '))" id ="anchor_recommend_' . $post->id . '">'. $strrecommand.'</a>&nbsp;';
         }
       }
     }
  
  //move message
   if ($extendedforum->type == 'single' && $post->parent == 0  )
     {
         //do nothing, do not delete or move the top discussion
     }
     else
     {
       if($cm->cache->caps['mod/extendedforum:movemessage']) {
       $movemessageimg =  '&nbsp;<img border="0" width="13" height="12" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/haavara.gif" alt = "" title = "" />'; 
       echo $movemessageimg . '&nbsp;<a href = "'.$CFG->wwwroot .'/mod/extendedforum/movemessage.php?p='. $post->id . '&amp;f=' . $post->extendedforum. '">' . $strmovemessage . '</a>'  ;
       }
       
        //delete message  
     if( $cm->cache->caps['mod/extendedforum:deleteanypost']) {
        $deleteimg =  '&nbsp;<img border="0" width="13" height="14" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/delete.gif" alt = "" title = "" />'; 
        echo   $deleteimg. '&nbsp;<a href="'.$CFG->wwwroot.'/mod/extendedforum/post.php?delete='.$post->id.'">'.$strdelete.'</a>';
     } 
     
     
     
    }


}


/***
 *
 *
 *
 */
 function get_discussion_recomand($discussionid, $enableajax = 0){
 
    global $CFG;
     
     $discussionflag = '<img width="13" height="13"  src="' . $CFG->wwwroot . '/mod/extendedforum/pix/hamlaza.gif" '.
         ' alt = "'. get_string('recommend_alt', 'extendedforum') .  '" />';
         
         
      return $discussionflag;
 
 }   
/**
 *
 *
 */
 function get_discussion_flag ($discussionid, $enableajax = 0)
 {
      global $CFG;
     
     $discussionflag = '<img width="17" height="20"  src="' . $CFG->wwwroot . '/mod/extendedforum/pix/simun.gif" '.
         ' alt = "'. get_string('flagon', 'extendedforum') .  '" />';
        
        //onClick="remove_discussion_flag(' . $discussionid . ', \'flag_post' . $discussionid . '\', \'flag'. $discussionid . '\','. $enableajax . ' )"/>';
        //class="imageaction"
        
        
     return $discussionflag;
 }
 
 
 /***
  *
  *
  *
  */
  function get_recommended_img($post, $message_box, $class)
  {
        global  $CFG;
        $alt = get_string('recommend_alt', 'extendedforum') ;
        
        $spanid= "marked"   ;
        $imageid = "recommend_"; 
        if($message_box)
        {
          $spanid  =     $spanid . "box"  ;
          $imageid =    $imageid . "box"   ;
        }
       $recommended = '<span id="'. $spanid . $post->id. '" class="'. $class . '"><img src = "'. $CFG->wwwroot . '/mod/extendedforum/pix/hamlaza.gif" alt = "' . $alt . '" id = "' . $imageid . $post->id . '" border="" width="13" height="13" /></span>' ;
       
       return  $recommended;
  
  }       
/**
*  retrun a string to print the flag options of a post message
*  @param object   post
*   @returns a string
* 
*/
  function get_flag_option($post, $class,  $message_box = false, $enableajax = 0 )
  {
       global  $CFG, $USER; 
      
      
       $user_id = $USER->id;
       $post_id = $post->id;
       
       $imgid  = 'imgflag'    . $post_id ;     //input id in message  subject
        $spanid = 'spanflag'  . $post_id ;
       if($message_box)
       {
         $imgid .= '_box' ; //img id in the posted message
         $spanid  .= '_box' ;
       }
    
        
      $flag_option =  '<span id = "' . $spanid  . '" class = "' . $class . '"> <img width="17" height="20" src="'. $CFG->wwwroot . '/mod/extendedforum/pix/simun.gif" border="0" id = "' .  $imgid . '" /></span>' ; 
                    
                 /*   '<img  onClick="change_flag('. $post->id . ','. $post->discussion . ',\'' . $imgid . '\','. $enableajax . ')" class="imageaction flag_post' . $post->discussion . '" id = "' .  $imgid . '"  title="' . get_string(
                        $post->postflag ? 'markread' : 'setflag', 'extendedforum') . 
                    '" src="' . $CFG->wwwroot . '/mod/extendedforum/pix/flag_' . 
                        ($post->postflag ? 'on' : 'off') . '.png" alt="' . 
                        get_string($post->postflag ? 'flagon' : 'flagoff', 
                            'extendedforum') . 
                    '"  />';
                    */
      
    
    return $flag_option ;  
  }
  
  /***
   *   This function returns an objects that represents the discussion header
   *   @param obj post 
   *   @param obj extendedforum
   *   @param obj cm
   *   @param obj modcontext 
   *   @param refrence obj discussionheaderobj           
   *
   *
   *
   */              
  function extendedforum_get_discussion_header_obj(&$post, $extendedforum, $cm, $modcontext, &$discussionheaderobj)
  {
      global $USER, $CFG;
       $hideauthor = $extendedforum->hideauthor;
        
        
    $post->subject = format_string($post->subject,true);
    
    $discussionheaderobj->subject =     $post->subject  ;
    
     $by = new object();
     if(!$hideauthor)
     {
        $by->name =  fullname($post,has_capability('moodle/site:viewfullnames', $modcontext));
        
     }
     else
    {
     
       $by->name =extendedforum_get_user_main_role($post->userid, $extendedforum->course) ;
    }
     $by->date = userdate($post->modified);

    $userdate = get_string ("bynameondate", "extendedforum", $by);
     
  
     $discussionheaderobj->userdate =  $userdate      ;
     $discussionheaderobj->postid = $post->id;
   
  }
/**
 * This function prints the overview of a discussion in the extendedforum listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: extendedforum_print_latest_discussions()
 *
 * @param object $post The post object (passed by reference for speed).
 * @param object $extendedforum The extendedforum object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this extendedforum.
 * @param boolean $extendedforumtracked Is the user tracking this extendedforum.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 * @param i
 */
function extendedforum_print_discussion_header(&$post, $extendedforum, $group=-1, $datestring="",
                                        $cantrack=true, $extendedforumtracked=true, $canviewparticipants=true, 
                                        $modcontext=NULL, $mode=NULL, $cm=NULL, $canupdateflag = false, 
                                        $ajaxenable = 0, $withtitle = 0)
           {

    
    global $USER, $CFG;
    global $discussion_class;
    
    static $rowcount;
    static $strmarkalldread;
    static $strnewmessage;
    
    $hideauthor = $extendedforum->hideauthor;
    $markunread = '';
    $readunread_class = '';
   
      if (!isset($strnewmessage)) {
        $strnewmessage  = get_string('newmessage', 'extendedforum');
      }
   
    $img_new = '';
    if ($extendedforumtracked) {
             if( $post->unread > 0 )
             {
              
              $img_new = '<img width="18" height="18"  src="' . $CFG->wwwroot . '/mod/extendedforum/pix/new.jpg" alt = "' . $strnewmessage . '">' ;
              
             }
        
      }
      
      $flaged_img = '';
      $recommande_img = '';
      
      if($post->flaged)  //if any of the posted messages has a flag we display a flag
      {
         $flag_discussion_option  = get_discussion_flag($post->discussion, $ajaxenable);
         
           $flaged_img = '<span id="flag'. $post->discussion . '">'  . $flag_discussion_option . ' </span>';                
      }
      else  //if no post has a flag we create an empty erea to store the flag as needed by ajax
      {
         $flaged_img =  '<span id="flag'. $post->discussion . '"></span>'; 
      }
      if($post->recommand) {
        $recommand_discussion_option = get_discussion_recomand($post->discussion, $ajaxenable);
        $recommande_img = '<span id="recommand'. $post->discussion . '">'   . $recommand_discussion_option  . ' </span>';
      
      }else{ //an empty area to recommondation
        $recommande_img = '<span id="recommand'. $post->discussion . '"></span>'; 
      }
    if (empty($modcontext)) {
         
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    }
    
     if (!$cm) {

        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'extendedforum');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }
    
    
    if (isguestuser() or !isloggedin() or has_capability('moodle/legacy:guest', $modcontext, NULL, false)) {
    
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        $canreply = ($extendedforum->type != 'news'); // no reply in news extendedforums

    } else {
   
        $canreply = extendedforum_user_can_post($extendedforum, $post, $USER, $cm, $extendedforum->course, $modcontext);
         
    }
   
 
    $canrate = has_capability('mod/extendedforum:rate', $modcontext);
   
    if (!$course = get_record('course', 'id', $extendedforum->course)) {
        error("Course ID is incorrect - discussion is faulty");
    }
    
    $post->subject = format_string($post->subject,true);
    $replies = '';
     if (has_capability('mod/extendedforum:viewdiscussion', $modcontext)) { 
         $replies = '(' . $post->replies . ')'; 
      }
    
    
   
    echo "<table class=\"$discussion_class extendedforumtable\">" ; //shimon 1823
    if($withtitle == 1)
    {
        echo '<thead>';
          echo '<tr>';
          echo '<th style="text-align:'. fix_align_rtl('left') . '" class=" topic extendedforumtabletitle" >&nbsp;</th>';
          echo '<th style="text-align:'. fix_align_rtl('left') . '" class=" topic extendedforumtabletitle" scope="col" >'.  get_string('discussion', 'extendedforum').'</th>';
         
          echo '<th  style="text-align:'. fix_align_rtl('left') . '" class=" author extendedforumtabletitle"  scope="col">'.get_string('startedby', 'extendedforum').'</th>';
     
          echo '<th  style="text-align:'. fix_align_rtl('left') . '" class=" lastpost extendedforumtabletitle" scope="col">'.get_string('lastpost', 'extendedforum').'</th>';
          echo '</tr>';
          echo '</thead>';
       
    }
    echo'<tr class="extendedforumdiscussionrow discussion r'.$rowcount. ' ' . $readunread_class . '">';

    //action button (open and close thread)
      

    $actionimage = $CFG->wwwroot . '/mod/extendedforum/pix/plus.gif';
    $imagealt = get_string('opendiscussionthread', 'extendedforum');
    
    if( $mode == EXTENDEDFORUM_MODE_ALL)
    {
          $actionimage = $CFG->wwwroot . '/mod/extendedforum/pix/minus.gif'  ;
          $imagealt = get_string('closediscussionthread', 'extendedforum');
    }
    $img_ontop = '';
    
    if(isset($post->on_top)  )
    {
        if($post->on_top == 1)
        {
            $img_ontop = '<img width="19" height="18"  src="' . $CFG->wwwroot . '/mod/extendedforum/pix/neiza_back.png" '.
         ' alt = "'. get_string('lockmessage_alt', 'extendedforum') .  '" />';
        
        }
    }
    $img_teachingteam = '';
    //print course teacher icon if posted by course teacher
   
  
     $img_teacher = extendedforum_get_teacher_img($post->role, 'gray');
            
    echo '<td class="actionbutton openutable '. $readunread_class . '"><a href="javascript:void(openCloseThread('. $post->id . ',\'discussion' . $post->id . '\',\'image' . $post->id . '\','. $post->discussion . ',\'flag_post' . $post->discussion . '\'))"><img src="'.  $actionimage . '" id = "image' . $post->id .  '" title = "'. $imagealt . '" alt = "' . $imagealt . '"/></a>';
   // echo '<a name="discussion'. $post->discussion .'" ></a>';
    echo ' </td>' ;
    
    // Topic
    echo '<td class="topic starter tdsubject openutable '. $readunread_class . ' " id = "td' .$post->discussion . '" >'; //nowrap="nowrap" removed by Shimon # 1720
    echo '<a href="javascript:void(openCloseDiscussion(\'discussion' . $post->id . '\',' . $post->id . ','. $post->discussion .','. $cm->id . '))">'. format_text($post->subject, FORMAT_HTML, NULL, $course->id ). '</a> &nbsp;' . $replies ;
    echo   $img_ontop . '&nbsp;' . $recommande_img  . '&nbsp;' . $flaged_img . $img_teacher . $img_new;
     
    echo "</td>\n";

   // echo '<td align="left" valign  = "top" class="formumcontrols" >' . $markunread . '&nbsp;' . $flaged_img . ' </td>';
       //if group

     if(!$hideauthor)
     {
       // User name
       $fullname = fullname($post, has_capability('moodle/site:viewfullnames', $modcontext));
       echo '<td class="author tdauthorname openutable ' . $readunread_class  . '">';
       echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->userid.'&amp;course='.$extendedforum->course.'">'.$fullname.'</a>';
       echo "</td>\n";
     }
     else
     {
     
     
     $role = extendedforum_get_user_main_role($post->userid, $extendedforum->course) ;
     echo '<td class="author openutable ' . $readunread_class .'">';
       echo "$role" ;
      echo '</td>';
     }
  
      $class = 'posts';
      if( $mode == EXTENDEDFORUM_MODE_ALL){
          $class = 'postView';
      }
      
      $colspan = 3;
    /*  // we do not show group pic  
         // Group 
  
   if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        $colspan = 5;
       echo '<td class="picture group">';
       if (!empty($group->picture) and empty($group->hidepicture)) {
            print_group_picture($group, $extendedforum->course, false, false, true);
        } else if (isset($group->id)) {
            if($canviewparticipants) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$extendedforum->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }
    */
       
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    echo '<td class="tddate openutable '. $readunread_class . '">' . userdate($usedate, $datestring) . '</td>'  ;
    echo "</tr>"; 
    echo ' </table> '  ;
 
    echo '<div class="' .$discussion_class.' '.$class . '" id="discussion' . $post->id . '">'   ;
    
     
      $discussion = get_record('extendedforum_discussions', 'id', $post->discussion);  
        
 //shimon #1328    
      extendedforum_print_discussion($course, $cm, $extendedforum, $discussion, $post, $mode, $canreply, $canrate, $canupdateflag, $ajaxenable,$class);
   
     
      
    echo '</div><!-- end '. $class . '-->'."\n";
   
   
   
    
   
}


/**
 * Given a post object that we already know has a long message
 * this function truncates the message nicely to the first
 * sane place between $CFG->extendedforum_longpost and $CFG->extendedforum_shortpost
 */
function extendedforum_shorten_post($message) {

   global $CFG;

   $i = 0;
   $tag = false;
   $length = strlen($message);
   $count = 0;
   $stopzone = false;
   $truncate = 0;

   for ($i=0; $i<$length; $i++) {
       $char = $message[$i];

       switch ($char) {
           case "<":
               $tag = true;
               break;
           case ">":
               $tag = false;
               break;
           default:
               if (!$tag) {
                   if ($stopzone) {
                       if ($char == ".") {
                           $truncate = $i+1;
                           break 2;
                       }
                   }
                   $count++;
               }
               break;
       }
       if (!$stopzone) {
           if ($count > $CFG->extendedforum_shortpost) {
               $stopzone = true;
           }
       }
   }

   if (!$truncate) {
       $truncate = $i;
   }

   return substr($message, 0, $truncate);
}


/**
 * Print the multiple ratings on a post given to the current user by others.
 * Forumid prevents the double lookup of the extendedforumid in discussion to determine the aggregate type
 * Scale is an array of ratings
 */
function extendedforum_print_ratings($postid, $scale, $aggregatetype, $link=true, $ratings=null, $return=false) {

    $strratings = '';

    switch ($aggregatetype) {
        case EXTENDEDFORUM_AGGREGATE_AVG :
            $agg        = extendedforum_get_ratings_mean($postid, $scale, $ratings);
            $strratings = get_string("aggregateavg", "extendedforum");
            break;
        case EXTENDEDFORUM_AGGREGATE_COUNT :
            $agg        = extendedforum_get_ratings_count($postid, $scale, $ratings);
            $strratings = get_string("aggregatecount", "extendedforum");
            break;
        case EXTENDEDFORUM_AGGREGATE_MAX :
            $agg        = extendedforum_get_ratings_max($postid, $scale, $ratings);
            $strratings = get_string("aggregatemax", "extendedforum");
            break;
        case EXTENDEDFORUM_AGGREGATE_MIN :
            $agg        = extendedforum_get_ratings_min($postid, $scale, $ratings);
            $strratings = get_string("aggregatemin", "extendedforum");
            break;
        case EXTENDEDFORUM_AGGREGATE_SUM :
            $agg        = extendedforum_get_ratings_sum($postid, $scale, $ratings);
            $strratings = get_string("aggregatesum", "extendedforum");
            break;
    }

    if ($agg !== "") {

        if (empty($strratings)) {
            $strratings = get_string("ratings", "extendedforum");
        }

        $strratings .= ': ';

        if ($link) {
            $strratings .= link_to_popup_window ("/mod/extendedforum/report.php?id=$postid", "ratings", $agg, 400, 600, null, null, true);
        } else {
            $strratings .= "$agg ";
        }

        if ($return) {
            return $strratings;
        } else {
            echo $strratings;
        }
    }
}


/**
 * Return the mean rating of a post given to the current user by others.
 * Scale is an array of possible ratings in the scale
 * Ratings is an optional simple array of actual ratings (just integers)
 * Forumid is the extendedforum id field needed - passing it avoids a double query of lookup up the discusion and then the extendedforum id to get the aggregate type
 */
function extendedforum_get_ratings_mean($postid, $scale, $ratings=NULL) {

    if (is_null($ratings)) {
        $ratings = array();
        if ($rates = get_records("extendedforum_ratings", "post", $postid)) {
            foreach ($rates as $rate) {
                $ratings[] = $rate->rating;
            }
        }
    }

    $count = count($ratings);

    if ($count == 0 ) {
        return "";

    } else if ($count == 1) {
        $rating = reset($ratings);
        return $scale[$rating];

    } else {
        $total = 0;
        foreach ($ratings as $rating) {
            $total += $rating;
        }
        $mean = round( ((float)$total/(float)$count) + 0.001);  // Little fudge factor so that 0.5 goes UP

        if (isset($scale[$mean])) {
            return $scale[$mean]." ($count)";
        } else {
            return "$mean ($count)";    // Should never happen, hopefully
        }
    }
}

/**
 * Return the count of the ratings of a post given to the current user by others.
 *
 * For numerical grades, the scale index is the same as the real grade value from interval {0..n}
 * and $scale looks like Array( 0 => '0/n', 1 => '1/n', ..., n => 'n/n' )
 *
 * For scales, the index is the order of the scale item {1..n}
 * and $scale looks like Array( 1 => 'poor', 2 => 'weak', 3 => 'good' )
 * In case of no ratings done yet, we have nothing to display.
 *
 * @param int $postid
 * @param array $scale Possible ratings in the scale - the end of the scale is the highest or max grade
 * @param array $ratings An optional simple array of actual ratings (just integers)
 */
function extendedforum_get_ratings_count($postid, $scale, $ratings=NULL) {

    if (is_null($ratings)) {
        $ratings = array();
        if ($rates = get_records("extendedforum_ratings", "post", $postid)) {
            foreach ($rates as $rate) {
                $ratings[] = $rate->rating;
            }
        }
    }

    $count = count($ratings);
    if (! array_key_exists(0, $scale)) {
        $scaleused = true;
    } else {
        $scaleused = false;
    }

    if ($count == 0) {
        if ($scaleused) {    // If no rating given yet and we use a scale
            return get_string('noratinggiven', 'extendedforum');
        } else {
            return '';
        }
    }

    $maxgradeidx = max(array_keys($scale)); // For numerical grades, the index is the same as the real grade value {0..n}
                                            // and $scale looks like Array( 0 => '0/n', 1 => '1/n', ..., n => 'n/n' )
                                            // For scales, the index is the order of the scale item {1..n}
                                            // and $scale looks like Array( 1 => 'poor', 2 => 'weak', 3 => 'good' )

    if ($count > $maxgradeidx) {      // The count exceeds the max grade
        $a = new stdClass();
        $a->count = $count;
        $a->grade = $scale[$maxgradeidx];
        return get_string('aggregatecountformat', 'extendedforum', $a);
    } else {                                // Display the count and the aggregated grade for this post
        $a = new stdClass();
        $a->count = $count;
        $a->grade = $scale[$count];
        return get_string('aggregatecountformat', 'extendedforum', $a);
    }
}

/**
 * Return the max rating of a post given to the current user by others.
 * Scale is an array of possible ratings in the scale
 * Ratings is an optional simple array of actual ratings (just integers)
 */
function extendedforum_get_ratings_max($postid, $scale, $ratings=NULL) {

    if (is_null($ratings)) {
        $ratings = array();
        if ($rates = get_records("extendedforum_ratings", "post", $postid)) {
            foreach ($rates as $rate) {
                $ratings[] = $rate->rating;
            }
        }
    }

    $count = count($ratings);

    if ($count == 0 ) {
        return "";

    } else if ($count == 1) { //this works for max
        $rating = reset($ratings);
        return $scale[$rating];

    } else {
        $max = max($ratings);

        if (isset($scale[$max])) {
            return $scale[$max]." ($count)";
        } else {
            return "$max ($count)";    // Should never happen, hopefully
        }
    }
}

/**
 * Return the min rating of a post given to the current user by others.
 * Scale is an array of possible ratings in the scale
 * Ratings is an optional simple array of actual ratings (just integers)
 */
function extendedforum_get_ratings_min($postid, $scale,  $ratings=NULL) {

    if (is_null($ratings)) {
        $ratings = array();
        if ($rates = get_records("extendedforum_ratings", "post", $postid)) {
            foreach ($rates as $rate) {
                $ratings[] = $rate->rating;
            }
        }
    }

    $count = count($ratings);

    if ($count == 0 ) {
        return "";

    } else if ($count == 1) {
        $rating = reset($ratings);
        return $scale[$rating]; //this works for min

    } else {
        $min = min($ratings);

        if (isset($scale[$min])) {
            return $scale[$min]." ($count)";
        } else {
            return "$min ($count)";    // Should never happen, hopefully
        }
    }
}


/**
 * Return the sum or total of ratings of a post given to the current user by others.
 * Scale is an array of possible ratings in the scale
 * Ratings is an optional simple array of actual ratings (just integers)
 */
function extendedforum_get_ratings_sum($postid, $scale, $ratings=NULL) {

    if (is_null($ratings)) {
        $ratings = array();
        if ($rates = get_records("extendedforum_ratings", "post", $postid)) {
            foreach ($rates as $rate) {
                $ratings[] = $rate->rating;
            }
        }
    }

    $count = count($ratings);
    $scalecount = count($scale)-1; //this should give us the last element of the scale aka the max grade with  $scale[$scalecount]

    if ($count == 0 ) {
        return "";

    } else if ($count == 1) { //this works for max.
        $rating = reset($ratings);
        return $scale[$rating];

    } else {
        $total = 0;
        foreach ($ratings as $rating) {
            $total += $rating;
        }
        if ($total > $scale[$scalecount]) { //if the total exceeds the max grade then set it to the max grade
            $total = $scale[$scalecount];
        }
        if (isset($scale[$total])) {
            return $scale[$total]." ($count)";
        } else {
            return "$total ($count)";    // Should never happen, hopefully
        }
    }
}

/**
 * Return a summary of post ratings given to the current user by others.
 * Scale is an array of possible ratings in the scale
 * Ratings is an optional simple array of actual ratings (just integers)
 */
function extendedforum_get_ratings_summary($postid, $scale, $ratings=NULL) {

    if (is_null($ratings)) {
        $ratings = array();
        if ($rates = get_records("extendedforum_ratings", "post", $postid)) {
            foreach ($rates as $rate) {
                $rating[] = $rate->rating;
            }
        }
    }


    if (!$count = count($ratings)) {
        return "";
    }


    foreach ($scale as $key => $scaleitem) {
        $sumrating[$key] = 0;
    }

    foreach ($ratings as $rating) {
        $sumrating[$rating]++;
    }

    $summary = "";
    foreach ($scale as $key => $scaleitem) {
        $summary = $sumrating[$key].$summary;
        if ($key > 1) {
            $summary = "/$summary";
        }
    }
    return $summary;
}

/**
 * Print the menu of ratings as part of a larger form.
 * If the post has already been - set that value.
 * Scale is an array of ratings
 */
function extendedforum_print_rating_menu($postid, $userid, $scale, $myrating=NULL) {

    static $strrate;

    if (is_null($myrating)) {
        if (!$rating = get_record("extendedforum_ratings", "userid", $userid, "post", $postid)) {
            $myrating = EXTENDEDFORUM_UNSET_POST_RATING;
        } else {
            $myrating = $rating->rating;
        }
    }

    if (empty($strrate)) {
        $strrate = get_string("rate", "extendedforum");
    }
    $scale = array(EXTENDEDFORUM_UNSET_POST_RATING => $strrate.'...') + $scale;
    choose_from_menu($scale, $postid, $myrating, '', '', '0', false, false, 0, '', false, false, 'extendedforumpostratingmenu');
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 * @param $id - extendedforum id if $extendedforumtype is 'single',
 *              discussion id for any other extendedforum type
 * @param $mode - extendedforum layout mode
 * @param $extendedforumtype - optional
 */

 function extendedforum_print_mode_form($page, $id , $mode, $extendedforumtype='')  {
    if($extendedforumtype == 'news')
    {
      //popup_form("discuss.php?d=$id&amp;mode=", extendedforum_get_layout_modes(), "mode", $mode, "");
        popup_form("$page?$id&amp;mode=", extendedforum_get_layout_modes(), "mode", $mode, "");
    }
    else
    {
    //  echo '<div class="extendedforummode">';
        popup_form("$page?$id&amp;mode=", extendedforum_get_layout_modes(), "mode", $mode, "");
      //  echo '</div>';
        }
    
}

/**
 *
 */
function extendedforum_search_form($course, $search='') {
    global $CFG;

    $output  = '<div class="extendedforumsearch">';
    $output .= '<form action="'.$CFG->wwwroot.'/mod/extendedforum/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= helpbutton('search', get_string('search'), 'moodle', true, false, '', true);
    $output .= '<input name="search" type="text" size="18" value="'.s($search, true).'" alt="search" />';
    $output .= '<input value="'.get_string('searchextendedforums', 'extendedforum').'" type="submit" />';
    $output .= '<input name="id" type="hidden" value="'.$course->id.'" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}


/**
 *
 */
function extendedforum_set_return() {
    global $CFG, $SESSION;

    if (! isset($SESSION->fromdiscussion)) {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        } else {
            $referer = "";
        }
        // If the referer is NOT a login screen then save it.
        if (! strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $_SERVER["HTTP_REFERER"];
        }
    }
}


/**
 *
 */
function extendedforum_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Creates a directory file name, suitable for make_upload_directory()
 */
function extendedforum_file_area_name($post) {
    global $CFG;

    if (!isset($post->extendedforum) or !isset($post->course)) {
        debugging('missing extendedforum or course', DEBUG_DEVELOPER);
        if (!$discussion = get_record('extendedforum_discussions', 'id', $post->discussion)) {
            return false;
        }
        if (!$extendedforum = get_record('extendedforum', 'id', $discussion->extendedforum)) {
            return false;
        }
        $extendedforumid  = $extendedforum->id;
        $courseid = $extendedforum->course;
    } else {
        $extendedforumid  = $post->extendedforum;
        $courseid = $post->course;
    }

    return "$courseid/$CFG->moddata/extendedforum/$extendedforumid/$post->id";
}

/**
 *
 */
function extendedforum_file_area($post) {
    $path = extendedforum_file_area_name($post);
    if ($path) {
        return make_upload_directory($path);
    } else {
        return false;
    }
}

/**
 *
 */
function extendedforum_delete_old_attachments($post, $exception="") {

/**
 * Deletes all the user files in the attachments area for a post
 * EXCEPT for any file named $exception
 */
    if ($basedir = extendedforum_file_area($post)) {
        if ($files = get_directory_list($basedir)) {
            foreach ($files as $file) {
                if ($file != $exception) {
                    unlink("$basedir/$file");
                   // notify("Existing file '$file' has been deleted!");
                }
            }
        }
        if (!$exception) {  // Delete directory as well, if empty
            rmdir("$basedir");
        }
    }
}

/**
 * 
 * Deletes all the user files in the attachments area for a post
 * EXCEPT for any files located in $exception array
 * @param object $post
 * @param array $exceptions
 */
function extendedforum_delete_old_multiple_attachments($post, $exceptions=array()) 
{
    if ($basedir = extendedforum_file_area($post)) {
        if ($files = get_directory_list($basedir)) {
            foreach ($files as $file) {
                if (!in_array($file, $exceptions)) {
                    unlink("$basedir/$file");
                    delete_records_select('ou_files', "fullpath LIKE '%{$post->extendedforum}/{$post->id}/{$file}'");
                }
            }
        }
        if (!count($exceptions)) {  // Delete directory as well, if empty
            rmdir("$basedir");
            $post->attachment = '';
            update_record('extendedforum_posts', $post);            
        }
    }
}



 /****
  * Given a discussion object that is now copeid into $extendedforumid
  *  this function checks all posts in that discussion
  *  for attachments, and if any are found, these are
  * copied to the new extendedforum directory. 
  *
  *
  */       
function extendedforum_copy_attachments($discussion, $extendedforumid, $postmapping) {
     global $CFG;
    $return = true;
    
    if ($frompostlist = get_records_select("extendedforum_posts", "discussion = '$discussion->id' AND attachment <> ''")) {
        foreach ($frompostlist as $frompost) {
               $frompost->course = $discussion->course;
              $frompost->extendedforum = $discussion->extendedforum;
               $frompostdir = "$CFG->dataroot".extendedforum_file_area_name($frompost);
               
                if (is_dir($frompostdir)) {
                   $targetpost = $postmapping[$frompost->id];
                    $targetpost->extendedforum = $extendedforumid;
                    $targetpost->course = $discussion->course;
                    $targetpostdir = extendedforum_file_area_name(  $targetpost);
                   
                  
                      make_upload_directory($targetpostdir);
                    $targetpostdir = $CFG->dataroot.extendedforum_file_area_name($targetpost);
                     $files = get_directory_list($frompostdir);
                      foreach ($files as $file) {
                         $source = $frompostdir . '/' . $file;
                         $target =      $targetpostdir . '/' . $file;
                        
                         if (! @copy( $frompostdir . '/' . $file , $targetpostdir . '/' . $file )) {
                             $return = false;
                          }
                         // now add it to the log (this is important so we know who to notify if a virus is found later on)
                        clam_log_upload($targetpostdir.'/'.$file);
                      
                    }//end foreach
                   
                }  //end dir
                
        
        }  //end foreach post
        
        
  }
  return $return;

}
/**
 * Given a discussion object that is being moved to extendedforumid,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new extendedforum directory.
 */
function extendedforum_move_attachments($discussion, $extendedforumid) {

    global $CFG;

    require_once($CFG->dirroot.'/lib/uploadlib.php');

    $return = true;

    if ($posts = get_records_select("extendedforum_posts", "discussion = '$discussion->id' AND attachment <> ''")) {
        foreach ($posts as $oldpost) {
            $oldpost->course = $discussion->course;
            $oldpost->extendedforum = $discussion->extendedforum;
            $oldpostdir = "$CFG->dataroot".extendedforum_file_area_name($oldpost);
            if (is_dir($oldpostdir)) {
                $newpost = $oldpost;
                $newpost->extendedforum = $extendedforumid;
                $newpostdir = extendedforum_file_area_name($newpost);
                // take off the last directory because otherwise we're renaming to a directory that already exists
                // and this is unhappy in certain situations, eg over an nfs mount and potentially on windows too.
                make_upload_directory(substr($newpostdir,0,strrpos($newpostdir,'/')));
                $newpostdir = $CFG->dataroot.extendedforum_file_area_name($newpost);
                $files = get_directory_list($oldpostdir); // get it before we rename it.
                if (! @rename($oldpostdir, $newpostdir)) {
                    $return = false;
                }
                foreach ($files as $file) {
                    clam_change_log($oldpostdir.'/'.$file,$newpostdir.'/'.$file);
                }
            }
        }
    }
    return $return;
}

/**
 * if return=html, then return a html string.
 * if return=text, then return a text-only string.
 * otherwise, print HTML for non-images, and return image HTML
 */
function extendedforum_print_attachments($post, $return=NULL) {

    global $CFG;

    $filearea = extendedforum_file_area_name($post);
   
    $output = "";

    if ($basedir = extendedforum_file_area($post)) {
      
        if ($files = get_directory_list($basedir)) {
            $strattachment = get_string("attachment", "extendedforum");
            
              if ($return == "html") {
               $output .= '<ul>';
               }
            foreach ($files as $file) {
             
                $icon = mimeinfo("icon", $file);
                $type = mimeinfo("type", $file);
                $ffurl = get_file_url("$filearea/$file");
                
                 
                $image = "<img src=\"$CFG->pixpath/f/$icon\" class=\"icon\" alt=\"\" />";
                  
                /////////////< Files Hebrew upload
				$ou_file_name = get_record('ou_files', 'fullpath' ,str_replace("//","/" ,$CFG->dataroot.'/'.$filearea.'/'.$file));
				$file = ($ou_file_name) ? $ou_file_name->display_name : '';
				////////////>
                
                if ($return == "html") {
               
                   $output .= "<li> <a href=\"$ffurl\">$image</a> ";
                    $output .= "<a href=\"$ffurl\">$file</a></li>";
                
                } else if ($return == "text") {
                     
                    $output .= "$strattachment $file:\n$ffurl\n";

                } 
                //we do not want the picture as an inline image, so we do not
                //need this option 
                //else {
                   
                   
                  //  if (in_array($type, array('image/gif', 'image/jpeg', 'image/png'))) {    // Image attachments don't get printed as links
                     //   $output .= "<br /><img src=\"$ffurl\" alt=\"\" />";
                        
                   // } else {
                         //'<a href="' . $ffurl. '">' . $image . ' </a>' .
                      //$output .=   '<li> ' . filter_text("<a href=\"$ffurl\">$file</a>") . ' <a href="' . $ffurl. '">' . $image . ' </a></li>';
                        
                   // }
                //}
            }
        }
    }

     if($return == "html" )
      {
               $output .= '</ul>';
      }
    return $output;

}



/**
 * if return=html, then return a html string.
 * if return=text, then return a text-only string.
 * otherwise, print HTML for non-images, and return image HTML
 */
function extendedforum_print_edit_attachments($mform, $post) {

    global $CFG;

    $filearea = extendedforum_file_area_name($post);
   
    if ($basedir = extendedforum_file_area($post)) {
        if ($files = get_directory_list($basedir)) {
            $strattachment = get_string("attachment", "extendedforum");
            foreach ($files as $file_id => $file) 
            {
                $icon = mimeinfo("icon", $file);
                $type = mimeinfo("type", $file);
                $ffurl = get_file_url("$filearea/$file");
                $rfile = array_pop(get_records_select('ou_files', "fullpath LIKE '%{$file}'", 'display_name', '*'));
                $image = "<img src=\"$CFG->pixpath/f/$icon\" class=\"icon\" alt=\"\" />";
                $mform->addElement('advcheckbox', "removeattachment[{$file}]", get_string('removeattachment'), "<a href='{$ffurl}'>{$image}</a><a href='{$ffurl}'>{$rfile->display_name}</a>", array('group' => 1), array(0, 1));
        	    $mform->setType("removeattachment", PARAM_INT);                
            }
        }
    }
}


/**
 *
 */
function extendedforum_add_new_post($post,&$message) {

    global $USER, $CFG;

   
    
    $discussion = get_record('extendedforum_discussions', 'id', $post->discussion);
    $extendedforum      = get_record('extendedforum', 'id', $discussion->extendedforum);

    $post->created    = $post->modified = time();
    $post->mailed     = "0";
    $post->userid     = $USER->id;
    $post->attachment = "";
    $post->extendedforum      = $extendedforum->id;     // speedup
    $post->course     = $extendedforum->course; // speedup

    if (! $post->id = insert_record("extendedforum_posts", $post)) {
        return false;
    }

   // if ($post->attachment = extendedforum_add_attachment($post, 'attachment',$message)) {
     //   set_field("extendedforum_posts", "attachment", $post->attachment, "id", $post->id);
    //}

    // Update discussion modified date
    set_field("extendedforum_discussions", "timemodified", $post->modified, "id", $post->discussion);
    set_field("extendedforum_discussions", "usermodified", $post->userid, "id", $post->discussion);

    if (extendedforum_tp_can_track_extendedforums($extendedforum) && extendedforum_tp_is_tracked($extendedforum)) {
        extendedforum_tp_mark_post_read($post->userid, $post, $post->extendedforum);
    }

    return $post->id;
}

/**
 *
 */
function extendedforum_update_post($post,&$message) {

    global $USER, $CFG;

    $extendedforum = get_record('extendedforum', 'id', $post->extendedforum);

    $post->modified = time();

    $updatediscussion = new object();
    $updatediscussion->id           = $post->discussion;
    $updatediscussion->timemodified = $post->modified; // last modified tracking
    $updatediscussion->usermodified = $post->userid;   // last modified tracking

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $updatediscussion->name      = $post->subject;
        $updatediscussion->timestart = $post->timestart;
        $updatediscussion->timeend   = $post->timeend;
    }

    if (!update_record('extendedforum_discussions', $updatediscussion)) {
        return false;
    }

  //  if ($newfilename = extendedforum_add_attachment($post, 'attachment',$message)) {
       // $post->attachment = $newfilename;
   // } else {
       // unset($post->attachment);
    //}

    if (extendedforum_tp_can_track_extendedforums($extendedforum) && extendedforum_tp_is_tracked($extendedforum)) {
        extendedforum_tp_mark_post_read($post->userid, $post, $post->extendedforum);
    }

    return update_record('extendedforum_posts', $post);
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 */
function extendedforum_add_discussion($discussion,&$message) {

    global $USER, $CFG;

    $timenow = time();

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $extendedforum = get_record('extendedforum', 'id', $discussion->extendedforum);

    $post = new object();
    $post->discussion  = 0;
    $post->parent      = 0;
    $post->userid      = $USER->id;
    $post->created     = $timenow;
    $post->modified    = $timenow;
    $post->mailed      = 0;
    $post->subject     = $discussion->name;
    $post->message     = $discussion->intro;
    $post->attachment  = "";
    $post->extendedforum       = $extendedforum->id;     // speedup
    $post->course      = $extendedforum->course; // speedup
    $post->format      = $discussion->format;
    $post->mailnow     = $discussion->mailnow;

    if (! $post->id = insert_record("extendedforum_posts", $post) ) {
        return 0;
    }

   // if ($post->attachment = extendedforum_add_attachment($post, 'attachment',$message)) {
      //  set_field("extendedforum_posts", "attachment", $post->attachment, "id", $post->id); //ignore errors
    //}

    // Now do the main entry for the discussion,
    // linking to this first post

    $discussion->firstpost    = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid = $USER->id;

    if (! $post->discussion = insert_record("extendedforum_discussions", $discussion) ) {
        delete_records("extendedforum_posts", "id", $post->id);
        return 0;
    }

    // Finally, set the pointer on the post.
    if (! set_field("extendedforum_posts", "discussion", $post->discussion, "id", $post->id)) {
        delete_records("extendedforum_posts", "id", $post->id);
        delete_records("extendedforum_discussions", "id", $post->discussion);
        return 0;
    }

    if (extendedforum_tp_can_track_extendedforums($extendedforum) && extendedforum_tp_is_tracked($extendedforum)) {
        extendedforum_tp_mark_post_read($post->userid, $post, $post->extendedforum);
    }

    return $post;
}


/**
 *
 */
function extendedforum_delete_discussion($discussion, $fulldelete=false) {
// $discussion is a discussion record object

    $result = true;

    if ($posts = get_records("extendedforum_posts", "discussion", $discussion->id)) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->extendedforum  = $discussion->extendedforum;
            if (! delete_records("extendedforum_ratings", "post", "$post->id")) {
                $result = false;
            }
            if (! extendedforum_delete_post($post, $fulldelete)) {
                $result = false;
            }
        }
    }

    extendedforum_tp_delete_read_records(-1, -1, $discussion->id);

    if (! delete_records("extendedforum_discussions", "id", "$discussion->id")) {
        $result = false;
    }

    return $result;
}


/**
 *
 */
function extendedforum_delete_post($post, $children=false) {
   if ($childposts = get_records('extendedforum_posts', 'parent', $post->id)) {
       if ($children) {
           foreach ($childposts as $childpost) {
               extendedforum_delete_post($childpost, true);
           }
       } else {
           return false;
       }
   }
   if (delete_records("extendedforum_posts", "id", $post->id)) {
       delete_records("extendedforum_ratings", "post", $post->id);  // Just in case

       extendedforum_tp_delete_read_records(-1, $post->id);

       if ($post->attachment) {
           $discussion = get_record("extendedforum_discussions", "id", $post->discussion);
           $post->course = $discussion->course;
           $post->extendedforum  = $discussion->extendedforum;
           extendedforum_delete_old_attachments($post);
       }

   // Just in case we are deleting the last post
       extendedforum_discussion_update_last_post($post->discussion);

       return true;
   }
   return false;
}

/**
 *
 */
function extendedforum_count_replies($post, $children=true) {
    $count = 0;

    if ($children) {
        if ($childposts = get_records('extendedforum_posts', 'parent', $post->id)) {
           foreach ($childposts as $childpost) {
               $count ++;                   // For this child
               $count += extendedforum_count_replies($childpost, true);
           }
        }
    } else {
        $count += count_records('extendedforum_posts', 'parent', $post->id);
    }

    return $count;
}


/**
 *
 */
function extendedforum_forcesubscribe($extendedforumid, $value=1) {
    return set_field("extendedforum", "forcesubscribe", $value, "id", $extendedforumid);
}

/**
 *
 */
function extendedforum_is_forcesubscribed($extendedforum) {
    if (isset($extendedforum->forcesubscribe)) {    // then we use that
        return ($extendedforum->forcesubscribe == EXTENDEDFORUM_FORCESUBSCRIBE);
    } else {   // Check the database
       return (get_field('extendedforum', 'forcesubscribe', 'id', $extendedforum) == EXTENDEDFORUM_FORCESUBSCRIBE);
    }
}

/**
 *
 */
function extendedforum_is_subscribed($userid, $extendedforum) {
    if (is_numeric($extendedforum)) {
        $extendedforum = get_record('extendedforum', 'id', $extendedforum);
    }
    if (extendedforum_is_forcesubscribed($extendedforum)) {
        return true;
    }
    return record_exists("extendedforum_subscriptions", "userid", $userid, "extendedforum", $extendedforum->id);
}

function extendedforum_get_subscribed_extendedforums($course) {
    global $USER, $CFG;
    $sql = "SELECT f.id
              FROM {$CFG->prefix}extendedforum f
                   LEFT JOIN {$CFG->prefix}extendedforum_subscriptions fs ON (fs.extendedforum = f.id AND fs.userid = $USER->id)
             WHERE f.forcesubscribe <> ".EXTENDEDFORUM_DISALLOWSUBSCRIBE."
                   AND (f.forcesubscribe = ".EXTENDEDFORUM_FORCESUBSCRIBE." OR fs.id IS NOT NULL)";
    if ($subscribed = get_records_sql($sql)) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Adds user to the subscriber list
 */
function extendedforum_subscribe($userid, $extendedforumid) {

    if (record_exists("extendedforum_subscriptions", "userid", $userid, "extendedforum", $extendedforumid)) {
        return true;
    }

    $sub = new object();
    $sub->userid  = $userid;
    $sub->extendedforum = $extendedforumid;

    return insert_record("extendedforum_subscriptions", $sub);
}

/**
 * Removes user from the subscriber list
 */
function extendedforum_unsubscribe($userid, $extendedforumid) {
    return delete_records("extendedforum_subscriptions", "userid", $userid, "extendedforum", $extendedforumid);
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 */
function extendedforum_post_subscription($post, $extendedforum) {

    global $USER;
    
    $action = '';
    $subscribed = extendedforum_is_subscribed($USER->id, $extendedforum);
    
    if ($extendedforum->forcesubscribe == EXTENDEDFORUM_FORCESUBSCRIBE) { // database ignored
        return "";

    } elseif (($extendedforum->forcesubscribe == EXTENDEDFORUM_DISALLOWSUBSCRIBE)
        && !has_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $extendedforum->course), $USER->id)) {
        if ($subscribed) {
            $action = 'unsubscribe'; // sanity check, following MDL-14558
        } else {
            return "";
        }

    } else { // go with the user's choice
        if (isset($post->subscribe)) {
            // no change
            if ((!empty($post->subscribe) && $subscribed)
                || (empty($post->subscribe) && !$subscribed)) {
                return "";

            } elseif (!empty($post->subscribe) && !$subscribed) {
                $action = 'subscribe';

            } elseif (empty($post->subscribe) && $subscribed) {
                $action = 'unsubscribe';
            }
        }
    }

    $info = new object();
    $info->name  = fullname($USER);
    $info->extendedforum = format_string($extendedforum->name);

    switch ($action) {
        case 'subscribe':
            extendedforum_subscribe($USER->id, $post->extendedforum);
            return "<p>".get_string("nowsubscribed", "extendedforum", $info)."</p>";
        case 'unsubscribe':
            extendedforum_unsubscribe($USER->id, $post->extendedforum);
            return "<p>".get_string("nownotsubscribed", "extendedforum", $info)."</p>";
    }
}

/**
 * Generate and return the subscribe or unsubscribe link for a extendedforum.
 * @param object $extendedforum the extendedforum. Fields used are $extendedforum->id and $extendedforum->forcesubscribe.
 * @param object $context the context object for this extendedforum.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param
 */
function extendedforum_get_subscribe_link($extendedforum, $context, $messages = array(), $cantaccessagroup = false, $fakelink=true, $backtoindex=false, $subscribed_extendedforums=null) {
    global $CFG, $USER;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'extendedforum'),
        'unsubscribed' => get_string('subscribe', 'extendedforum'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'extendedforum'),
        'cantsubscribe' => get_string('disallowsubscribe','extendedforum')
    );
    $messages = $messages + $defaultmessages;

    if (extendedforum_is_forcesubscribed($extendedforum)) {
        return $messages['forcesubscribed'];
    } else if ($extendedforum->forcesubscribe == EXTENDEDFORUM_DISALLOWSUBSCRIBE && !has_capability('mod/extendedforum:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (is_null($subscribed_extendedforums)) {
            $subscribed = extendedforum_is_subscribed($USER->id, $extendedforum);
        } else {
            $subscribed = !empty($subscribed_extendedforums[$extendedforum->id]);
        }
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'extendedforum');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'extendedforum');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $link .= <<<EOD
<script type="text/javascript">
//<![CDATA[
var subs_link = document.getElementById("subscriptionlink");
if(subs_link){
    subs_link.innerHTML = "<a title=\"$linktitle\" href='$CFG->wwwroot/mod/extendedforum/subscribe.php?id={$extendedforum->id}{$backtoindexlink}'>$linktext<\/a>";
}
//]]>
</script>
<noscript>
EOD;
        }
        $options ['id'] = $extendedforum->id;
        $link .= print_single_button($CFG->wwwroot . '/mod/extendedforum/subscribe.php',
                $options, $linktext, 'post', '_self', true, $linktitle);
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}


/**
 * Generate and return the track or no track link for a extendedforum.
 * @param object $extendedforum the extendedforum. Fields used are $extendedforum->id and $extendedforum->forcesubscribe.
 */
function extendedforum_get_tracking_link($extendedforum, $messages=array(), $fakelink=true) {
    global $CFG, $USER;

    static $strnotrackextendedforum, $strtrackextendedforum;

    if (isset($messages['trackextendedforum'])) {
         $strtrackextendedforum = $messages['trackextendedforum'];
    }
    if (isset($messages['notrackextendedforum'])) {
         $strnotrackextendedforum = $messages['notrackextendedforum'];
    }
    if (empty($strtrackextendedforum)) {
        $strtrackextendedforum = get_string('trackextendedforum', 'extendedforum');
    }
    if (empty($strnotrackextendedforum)) {
        $strnotrackextendedforum = get_string('notrackextendedforum', 'extendedforum');
    }

    if (extendedforum_tp_is_tracked($extendedforum)) {
        $linktitle = $strnotrackextendedforum;
        $linktext = $strnotrackextendedforum;
    } else {
        $linktitle = $strtrackextendedforum;
        $linktext = $strtrackextendedforum;
    } 

    $link = '';
    if ($fakelink) {
        $link .= '<script type="text/javascript">';
        $link .= '//<![CDATA['."\n";
        $link .= 'document.getElementById("trackinglink").innerHTML = "<a title=\"' . $linktitle . '\" href=\"' . $CFG->wwwroot .
           '/mod/extendedforum/settracking.php?id=' . $extendedforum->id . '\">' . $linktext . '<\/a>";'."\n";
        $link .= '//]]>'."\n";
        $link .= '</script>';
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $link .= print_single_button($CFG->wwwroot . '/mod/extendedforum/settracking.php?id=' . $extendedforum->id,
            '', $linktext, 'post', '_self', true, $linktitle);
    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}



/**
 * Returns true if user created new discussion already
 * @param int $extendedforumid
 * @param int $userid
 * @return bool
 */
function extendedforum_user_has_posted_discussion($extendedforumid, $userid) {
    global $CFG;

    $sql = "SELECT 'x'
              FROM {$CFG->prefix}extendedforum_discussions d, {$CFG->prefix}extendedforum_posts p
             WHERE d.extendedforum = $extendedforumid AND p.discussion = d.id AND p.parent = 0 and p.userid = $userid";

    return record_exists_sql($sql);
}

/**
 *
 */
function extendedforum_discussions_user_has_posted_in($extendedforumid, $userid) {
    global $CFG;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {$CFG->prefix}extendedforum_posts p,
                            {$CFG->prefix}extendedforum_discussions d
                      WHERE p.discussion = d.id
                        AND d.extendedforum = $extendedforumid
                        AND p.userid = $userid";

    return get_records_sql($haspostedsql);
}

/**
 *
 */
function extendedforum_user_has_posted($extendedforumid, $did, $userid) {
    global $CFG;

    if (empty($did)) {
        // posted in any extendedforum discussion?
        $sql = "SELECT 'x'
                  FROM {$CFG->prefix}extendedforum_posts p
                  JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                 WHERE p.userid = $userid AND d.extendedforum = $extendedforumid";
        return record_exists_sql($sql);
    } else {
        // started discussion?
        return record_exists('extendedforum_posts','discussion',$did,'userid',$userid);
    }
}

/**
 *
 */
function extendedforum_user_can_post_discussion($extendedforum, $currentgroup=null, $unused=-1, $cm=NULL, $context=NULL) {
// $extendedforum is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
    }

    if (!$context) {
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($extendedforum->type == 'news') {
        $capname = 'mod/extendedforum:addnews';
    } else {
        $capname = 'mod/extendedforum:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($extendedforum->type == 'eachuser') {
        if (extendedforum_user_has_posted_discussion($extendedforum->id, $USER->id)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }
  
      
    if ($currentgroup) {
        $ismemmber = groups_is_member($currentgroup);
       
        return    $ismemmber;
       
        
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a extendedforum
 * discussion. Use extendedforum_user_can_post_discussion() to check whether the user
 * can start dicussions.
 * @param $extendedforum - extendedforum object
 * @param $user - user object
 */
function extendedforum_user_can_post($extendedforum, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = get_record('course', 'id', $extendedforum->course)) {
            error('Incorrect course id');
        }
    }

    if (!$context) {
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    }

    // normal users with temporary guest access can not post
    if (has_capability('moodle/legacy:guest', $context, $user->id, false)) {
        return false;
    }

    if ($extendedforum->type == 'news') {
        $capname = 'mod/extendedforum:replynews';
    } else {
        $capname = 'mod/extendedforum:replypost';
    }

    if (!has_capability($capname, $context, $user->id, false)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        
       
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}


//checks to see if a user can view a particular post
function extendedforum_user_can_view_post($post, $course, $cm, $extendedforum, $discussion, $user=NULL){

    global $CFG, $USER;

    if (!$user){
        $user = $USER;
    }

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (!has_capability('mod/extendedforum:viewdiscussion', $modcontext)) {
        return false;
    }

// If it's a grouped discussion, make sure the user is a member
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $modcontext);
        }
    }
    return true;
}


/**
 *
 */
function extendedforum_user_can_see_discussion($extendedforum, $discussion, $context, $user=NULL) {
    global $USER;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    
    // retrieve objects (yuk)
    if (is_numeric($extendedforum)) {
        debugging('missing full extendedforum', DEBUG_DEVELOPER);
        if (!$extendedforum = get_record('extendedforum','id',$extendedforum)) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = get_record('extendedforum_discussions','id',$discussion)) {
            return false;
        }
    }

    if (!has_capability('mod/extendedforum:viewdiscussion', $context)) {
        return false;
    }

    if ($extendedforum->type == 'qanda' &&
            !extendedforum_user_has_posted($extendedforum->id, $discussion->id, $user->id) &&
            !has_capability('mod/extendedforum:viewqandawithoutposting', $context)) {
        return false;
    }
    return true;
}


/**
 *
 */
function extendedforum_user_can_see_post($extendedforum, $discussion, $post, $user=NULL, $cm=NULL) {
    global $USER;

   
   
    // retrieve objects (yuk)
    if (is_numeric($extendedforum)) {
        debugging('missing full extendedforum', DEBUG_DEVELOPER);
        if (!$extendedforum = get_record('extendedforum','id',$extendedforum)) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = get_record('extendedforum_discussions','id',$discussion)) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = get_record('extendedforum_posts','id',$post)) {
            return false;
        }
    }
    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    if (isset($cm->cache->caps['mod/extendedforum:viewdiscussion'])) {
        if (!$cm->cache->caps['mod/extendedforum:viewdiscussion']) {
            return false;
        }
    } else {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        if (!has_capability('mod/extendedforum:viewdiscussion', $modcontext, $user->id)) {
            return false;
        }
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!coursemodule_visible_for_user($cm, $user->id)) {
            return false;
        }
    }

    if ($extendedforum->type == 'qanda') {
      
        $firstpost = extendedforum_get_firstpost_from_discussion($discussion->id);
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
      
        return (extendedforum_user_has_posted($extendedforum->id,$discussion->id,$user->id) ||
                $firstpost->id == $post->id ||
                has_capability('mod/extendedforum:viewqandawithoutposting', $modcontext, $user->id, false));
    }
    return true;
}


/**
 * Prints the discussion view screen for a extendedforum.
 *
 * @param object $course The current course object.
 * @param object $extendedforum Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the extendedforum (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int perpage The maximum number of discussions per page(optional)
 *
 */
function extendedforum_print_latest_discussions($course, $extendedforum, $maxdiscussions=-1, $displayformat='plain', $sort='',
                                        $currentgroup=-1, $groupmode=-1, $page=-1, $perpage=100, $cm=NULL, $displaymode=NULL) {
    global $CFG, $USER;
    global  $discussion_class; // odd or even shimon for issue 1823
   
    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
    }
    
     $ajaxenable = 0;
     
     if (ajaxenabled() && !empty($CFG->extendedforum_ajaxrating) ){
       $ajaxenable = 1;
     }
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (empty($sort)) {
        $sort = "d.timemodified DESC";
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions === 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }

    
// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }
      echo '<input type ="hidden" id ="extendedforum_id" value ="'. $extendedforum->id. '" />'    ;
      echo '<table   class="formheader"  ><tr>'    ;
     // If we want paging
         echo '<td>';
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = extendedforum_get_discussions_count($cm);

        ///Show the paging bar
      //  echo '<div class="paging">';
        echo '<table class="pagebar"><tr>' ;
        extendedforum_print_formated_page_bar($numdiscussions, $page, $perpage, "view.php?f=$extendedforum->id&amp;");
       
     
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large extendedforums
            $replies = extendedforum_count_discussion_replies($extendedforum->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = extendedforum_count_discussion_replies($extendedforum->id);
        }

    } else {
        $replies = extendedforum_count_discussion_replies($extendedforum->id);

       // if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
          //  $olddiscussionlink = true;
        //}
    }
    
// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.
    
    print_new_message_button($extendedforum, $currentgroup, $groupmode, $cm, $context);
    echo '</td>';
    echo '</tr></table>'  ; //end paging bar table
  
// Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');
   
    if (! $discussions = extendedforum_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage) ) {
        echo '<div class="extendedforumnodiscuss">';
        if ($extendedforum->type == 'news') {
            echo '('.get_string('nonews', 'extendedforum').')';
        } else if ($extendedforum->type == 'qanda') {
            echo '('.get_string('noquestions','extendedforum').')';
        } else {
            echo '('.get_string('nodiscussions', 'extendedforum').')';
        }
         echo '</tr></table>'    ;
        echo "</div>\n";
        return;
    }


    
  
   echo '<td align="left">'    ;
      extendedforum_print_mode_form('view.php', "id=$cm->id", $displaymode);
      echo '</td>'  ;
  
    
   echo '</tr></table>'    ;
    //end paging and message format header
    
    
     $canrate = has_capability('mod/extendedforum:rate', $context); 
     $ratings = NULL;
     $ratingsformused = false;
    if ($extendedforum->assessed and isloggedin()) {
        if ($scale = make_grades_menu($extendedforum->scale)) {
            $ratings =new object();
            $ratings->scale = $scale;
            $ratings->assesstimestart = $extendedforum->assesstimestart;
            $ratings->assesstimefinish = $extendedforum->assesstimefinish;
            $ratings->allow = $canrate;

            
       }
  
    if (  is_object ($ratings) && isset($ratings->allow) ){
       if($ratings->allow) {
            echo '<form id="form" method="post" action="rate.php"><!-- start form ratings -->';
                echo '<input type="hidden" name="extendedforumid" value="'.$extendedforum->id.'" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                }
       } 
     }          
    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);
    $canupdateflag = has_capability('mod/extendedforum:updateflag', $context)  ;
    $strdatestring =  get_string('strftimedaydatetimemodified', 'extendedforum') ; //get_string('strftimerecentfull');
    

    // Check if the extendedforum is tracked.
    if ($cantrack = extendedforum_tp_can_track_extendedforums($extendedforum)) {
        $extendedforumtracked = extendedforum_tp_is_tracked($extendedforum);
    } else {
        $extendedforumtracked = false;
    }

    if ($extendedforumtracked) {
        $unreads = extendedforum_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }
    
    $flaged = extendedforum_get_discussions_flaged($cm);
    $recommanded = extendedforum_get_discussions_recommanded($cm);
    
      
     echo '<input type  = "hidden" name = "numofdiscussions" value ="'. count($discussions) . '" id= "numdiscussion"/>' ;
  
     
         $i = 0;
    foreach ($discussions as $discussion) {
        $i++;
        
        $discussion_class= ($i % 2==0) ? 'even':'odd';  //shimon #1823
        
        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }
        
        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$extendedforumtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
              
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if(empty ($flaged[$discussion->discussion]))  {
              $discussion->flaged = 0;
        }  else  {
            $discussion->flaged = 'flaged' ;
        }
        
        if(empty($recommanded[$discussion->discussion]))  {
               $discussion->recommand = 0;
        }else {
             $discussion->recommand = 'recommand' ;    
        }
        if (!empty($USER->id)) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;
         
        $discussion->page = $page;
       
      //  switch ($displayformat) {
          //  case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
               
               extendedforum_print_discussion_header($discussion, $extendedforum, $group, $strdatestring, $cantrack, $extendedforumtracked,
                    $canviewparticipants, $context, $displaymode, $cm,  $canupdateflag, $ajaxenable, $i );
                 
                  
          
    }
 
     if ($page != -1) {
     //end table display paging
        echo '<table   class="formheader"  ><tr>'    ;
         echo '<td>';
        ///Get the number of discussions found
        $numdiscussions = extendedforum_get_discussions_count($cm);

        ///Show the paging bar
      //  echo '<div class="paging">';
        echo '<table class="pagebar secondpagebar"><tr>' ;
        extendedforum_print_formated_page_bar($numdiscussions, $page, $perpage, "view.php?f=$extendedforum->id&amp;");
            echo '</tr></table>'  ; //end paging bar table 
       echo '</td>' ;   
       print_new_message_button($extendedforum, $currentgroup, $groupmode, $cm, $context );
       echo '</td></tr></table>';
       }

    if ($olddiscussionlink) {
        if ($extendedforum->type == 'news') {
            $strolder = get_string('oldertopics', 'extendedforum');
        } else {
            $strolder = get_string('olderdiscussions', 'extendedforum');
        }
        echo '<div class="extendedforumolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/extendedforum/view.php?f='.$extendedforum->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }
     
       $canrate = has_capability('mod/extendedforum:rate', $context); 
   
    if (  is_object ($ratings) && isset($ratings->allow) ){
    if ($ratings->allow) {
              echo '<div class="ratingsubmit" id="ratingsubmit">';
                
              echo '<input type="submit" id="extendedforumpostratingsubmit" value="'.get_string('sendinratings', 'extendedforum').'" />'; 
            
             if($ajaxenable)   /// AJAX enabled, standard submission form
             {
                $rate_ajax_config_settings = array("pixpath"=>$CFG->pixpath, "wwwroot"=>$CFG->wwwroot, "sesskey"=>sesskey());
                echo "<script type=\"text/javascript\">//<![CDATA[\n".
                     "var rate_ajax_config = " . json_encode($rate_ajax_config_settings) . ";\n".
                     "init_rate_ajax();\n".
                     "//]]></script>\n";
            }
            else{
            if ($extendedforum->scale < 0) {
              if ($scale = get_record("scale", "id", abs($extendedforum->scale))) {
                    print_scale_menu_helpbutton($course->id, $scale );
                    
                }
            echo '</div>';
            }
          }
        echo '</div>';
        echo '</form><!-- end extendedforum rating -->';
   
   } 
  } 
}

/****
 *
 *
 */  
function print_new_message_button($extendedforum, $currentgroup, $groupmode, $cm, $context){
    global $CFG;
     
    if (   extendedforum_user_can_post_discussion($extendedforum, $currentgroup, $groupmode, $cm, $context) ||
        ($extendedforum->type != 'news' 
         and (isguestuser() or !isloggedin() or has_capability('moodle/legacy:guest', $context, NULL, false)))  ) {

         if($extendedforum->type != 'single')
         {
        
         
          echo '<td align="left" class="afterpaging " >';
         
       // echo '<div class="singlebutton extendedforumaddnew">';
        
        echo "<form class='sameline'  method=\"get\" action=\"$CFG->wwwroot/mod/extendedforum/post.php\">";
       // echo '<div>';
     
        echo "<input type=\"hidden\" name=\"extendedforum\" value=\"$extendedforum->id\"  />";
      echo '<input  class="pagebarbutton" type="submit" value="';
        echo ($extendedforum->type == 'news') ? get_string('addanewtopic', 'extendedforum')
           : (($extendedforum->type == 'qanda')
               ? get_string('addanewquestion','extendedforum')
               : get_string('addanewdiscussion', 'extendedforum'));
        echo '"/> </form>' ;
        //</div>'; 
       // echo '</td>';
      //  echo '</div>';
       
       
       // echo "</div>\n";
        }

    } else if (isguestuser() or !isloggedin() or $extendedforum->type == 'news') {
        // no button and no info

    } else if ($groupmode and has_capability('mod/extendedforum:startdiscussion', $context)) {
        // inform users why they can not post new discussion
        if ($currentgroup) {
            notify(get_string('cannotadddiscussion', 'extendedforum'));
        } else {
            notify(get_string('cannotadddiscussionall', 'extendedforum'));
        }
    }
   
 
  
    
   
}
/**
 *@param  $class odd / even shimon 1823
 */
function extendedforum_print_discussion($course, $cm, $extendedforum, $discussion, $post, $mode, $canreply=NULL, 
								$canrate=false, $canupdateflag = false, $enableajax = 0 ) 
								{
   
    global $USER, $CFG;
    global $discussion_class; 
    
    $page = 0;
    if (!empty($USER->id)) {
        $ownpost = ($USER->id == $post->userid);
    } else {
        $ownpost = false;
    }
    if ($canreply === NULL) {
        $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
        $reply = extendedforum_user_can_post($extendedforum, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    if(isset($post->page)){
      $page = $post->page;
    }
    
    // $cm holds general cache for extendedforum functions
    $cm->cache = new object();
    $cm->cache->groups      = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

   
    $sort = "p.created ASC";  
    $extendedforumtracked = extendedforum_tp_is_tracked($extendedforum);
    
          
    $posts = extendedforum_get_all_discussion_posts($post->discussion, $sort, $extendedforumtracked, $extendedforum->hideauthor);
        
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    $ratings = NULL;
    $ratingsmenuused = false;
    $ratingsformused = false;
    if ($extendedforum->assessed and isloggedin()) {
        if ($scale = make_grades_menu($extendedforum->scale)) {
            $ratings =new object();
            $ratings->scale = $scale;
            $ratings->assesstimestart = $extendedforum->assesstimestart;
            $ratings->assesstimefinish = $extendedforum->assesstimefinish;
            $ratings->allow = $canrate;

            if ($ratings->allow) {
                //echo '<form id="form" method="post" action="rate.php">';
               // echo '<div class="ratingform">';
               // echo '<input type="hidden" name="extendedforumid" value="'.$extendedforum->id.'" />';
               // echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                $ratingsformused = true;
            }

            // preload all ratings - one query only and minimal memory
            $cm->cache->ratings = array();
            $cm->cache->myratings = array();
            if ($postratings = extendedforum_get_all_discussion_ratings($discussion)) {
                foreach ($postratings as $pr) {
                    if (!isset($cm->cache->ratings[$pr->postid])) {
                        $cm->cache->ratings[$pr->postid] = array();
                    }
                    $cm->cache->ratings[$pr->postid][$pr->id] = $pr->rating;
                    if ($pr->userid == $USER->id) {
                        $cm->cache->myratings[$pr->postid] = $pr->rating;
                    }
                }
                unset($postratings);
            }
        }

    }

    $post->extendedforum = $extendedforum->id;   // Add the extendedforum id to the post object, later used by extendedforum_print_post
    $post->extendedforumtype = $extendedforum->type;

    $post->subject = format_string($post->subject);
    
    $postread = !empty($post->postread);
      
    $discussion->page = $page; 
   $indent=0;
   $class='';
 //call print post
 //added $indent $class
 
    if( extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, $ownpost, $reply, false, $ratings,
                         '', '', $postread, true, $extendedforumtracked,  $canupdateflag, $enableajax)) {
        $ratingsmenuused = true;
    }

    //mark read the first post
     $postread = '';
    if(isset($post->postread) )
    {   $postread = $post->postread;
    
    }
   
     if ($extendedforumtracked && !$CFG->extendedforum_usermarksread && !$postread) {
                   
                        extendedforum_tp_mark_post_read($USER->id, $post, $extendedforum->id);
      }
     
    switch ($mode) {
         
        case EXTENDEDFORUM_MODE_ONLY_DISCUSSION:
          
            //here $posts is array that include all posts of  a discution     
            if (extendedforum_print_posts_threaded($course, $cm, $extendedforum, $discussion, $post, 0, $ratings, $reply, $extendedforumtracked, 
            								$posts, $canupdateflag, $enableajax)) {
                $ratingsmenuused = true;
            }
          
            break;
          case EXTENDEDFORUM_MODE_ALL:
            if (extendedforum_print_posts_nested($course, $cm, $extendedforum, $discussion, $post, $ratings, $reply, $extendedforumtracked, $posts,
                                            $canupdateflag, $enableajax)) {
                $ratingsmenuused = true;
            }
            break;
    }
   
}


/**
 *
 */
function extendedforum_print_posts_flat($course, &$cm, $extendedforum, $discussion, $post, $mode, $ratings, $reply, $extendedforumtracked, $posts) {
    global $USER, $CFG;

    $link  = false;
    $ratingsmenuused = false;

    if ($mode == EXTENDEDFORUM_MODE_FLATNEWEST) {
        $sort = "ORDER BY created DESC";
    } else {
        $sort = "ORDER BY created ASC";
    }

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        
        if (extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, $ownpost, $reply, $link, $ratings,
                             '', '', $postread, true, $extendedforumtracked)) {
            $ratingsmenuused = true;
        }
    }

    return $ratingsmenuused;
}

/***
 *     
 *     return the posts in array with their html threaded format 
 *     
 *     @param object cm
 *     @param object extendedforum
 *     @param object discussion
 *     @paramn object parent first post in the discussion
 *     @param         depth
 *     @param array   posts all posts   
 *     @param array ref $threaded       
 *
 *
 */
 
 	function extendedforum_get_posts_threaded($cm, $modcontext , $extendedforum, $discussion, $parent, $depth, $posts, &$threaded)
  {
     global    $USER, $CFG   ;
      $hideauthor = $extendedforum->hideauthor;
    
      
        $continue = false;
       if (!empty($posts[$parent->id]->children)) {
           $posts = $posts[$parent->id]->children;
           
           $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext)  ;
             
           foreach ($posts as $post) {
            
             
             $postobj =  new stdClass;
             $postobj->id = $post->id;
             $postobj->html =      'SSSS<div class="indent"  >' ;
             // $postobj->html = '<div>'    ;
          
            if (!extendedforum_user_can_see_post($extendedforum, $discussion, $post, NULL, $cm)) {
                 
                   $postobj->html .=   '</div>\n'  ;
               
                    continue;
                }
              $by = new object();
                if(!$hideauthor)
                {
                  $by->name = fullname($post, $canviewfullnames);
                 
                }
                else
                {
                   $by->name = $post->role;
                }
                $by->date = userdate($post->modified);

                // $postobj->text = '<a href="postview.php?f=' . $extendedforum->id . '&amp;view=' . $post->id .
                                 //   '&amp;discussion='. $post->discussion . '" target="_blank"> ' .   format_string($post->subject,true) . '</a>&nbsp;'   ;
             
                  $postobj->text = '<a href="javascript:void(viewpost_ajax(' . $extendedforum->id . ','.   $post->id . ',' . $post->discussion . '))">'   .   format_string($post->subject,true) . '</a>&nbsp;'   ;
                 $postobj->nameanddate = get_string ("bynameondate", "extendedforum", $by);
                
                $threaded[$post->id] =  $postobj;
              
              if (extendedforum_get_posts_threaded($cm, $modcontext , $extendedforum, $discussion, $post,$depth-1, $posts,$threaded)){
                 $continue = true;
                }
              
             
                $threaded[$post->id . -1 ] = "</div><!-- now extendedforum_print_posts_threaded -->";
               
       }  //end for each post
    
    }  //!empty posts children
  
  return $continue;
  }
/**
 * TODO document
 * $param class odd / even 1823 consider using global
 */
 
function extendedforum_print_posts_threaded($course, &$cm, $extendedforum, $discussion, $parent, $depth, $ratings, $reply, $extendedforumtracked, 
									$posts, $can_update_flag = false, $enableajax = 0, $showcommands = 1,$indent = 0) {
    global $USER, $CFG;
  
    static $strnewmessage;
    $indent++;
    $link  = false;
    $ratingsmenuused = false;
    $hideauthor = $extendedforum->hideauthor;
    $class = 'indent'." ".$indent     ; 
	
      if ($indent > MAX_INDENT){
        $class = '';
      }
      
       if (!isset($strnewmessage)) {
        $strnewmessage  = get_string('newmessage', 'extendedforum');
      }
     $linksubject=1 ; //in post message display each message as a link
     
    if (!empty($posts[$parent->id]->children)) {
    	//$posts is  an array of all postes that have children level 1 
        $posts = $posts[$parent->id]->children;
       
        $modcontext       = get_context_instance(CONTEXT_MODULE, $cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);
      
        foreach ($posts as $post) {
  
              echo "<div class=\"$class\"> <!--line  start extendedforum_print_posts_threaded $indent -->";
             // echo '<div>'  ;
           
            if ($depth > 0) {
                
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);
                
               //Shimon Check 
                if (extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, $ownpost, $reply, $link, $ratings,
                                     '', '', $postread, true, $extendedforumtracked, $can_update_flag, $enableajax, $showcommands , $linksubject, $indent)) {
									 
                    $ratingsmenuused = true;
                }
                
               
            } else {
                 
                if (!extendedforum_user_can_see_post($extendedforum, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
              
                $by = new object();
                if(!$hideauthor)
                {
                  $by->name = fullname($post, $canviewfullnames);
                }
                else
                {
                   $by->name = $post->role;
                }
                $by->date = userdate($post->modified);
              
                $style = '<div id="divpostsubject' . $post->id . '" class="threadhidemesubject'. $post->discussion . ' ">' ;
                $style .= '<span class="extendedforumthread ' . '" id = "postsubject' . $post->id .  '">';
                $subject  = $post->subject;
                   if( ($post->message == '<p></p>') || ( $post->message== '') ){
                     $subject .= '&nbsp;' . get_string('nocontent', 'extendedforum')  ;
                   }
                echo $style.'<a name="threadedpostname' . $post->id. '"></a>'.
                      "<a href=\"javascript:void(getPost('postthread" .$post->id . "','imgflag" .$post->id . "_box'," . $post->id . "))\">"   .format_text(format_string($subject,true), FORMAT_HTML, NULL, $course->id )."</a> ";
                     
                print_string("bynameondate", "extendedforum", $by);
               
                //add message images such as flag, new ...
                $img_new = '';
                 $postread = '';
                if($extendedforumtracked) {
                   $postread = !empty($post->postread);
				   if(!$postread)  {
						 $img_new = '<img width="18" height="18"  src="' . $CFG->wwwroot . '/mod/extendedforum/pix/new_white.jpg" alt = "' . $strnewmessage . '">' ;
					}
				   echo ($img_new)  ;
                 
                 }
                 
                //mark - recommanded
                
                if($post->mark)
               {
                  $class= 'marked markedbox' . $post->discussion;
                }
                else{
                   $class = 'unmarked unmarkedbox'  ;
                }
                $mark_recommand = get_recommended_img($post , false,  $class);
                
                echo  '&nbsp' . $mark_recommand;
                 if($can_update_flag && $enableajax)
                    {
                        //$class = 'unmarked';
                        $class = 'indent';
                        if (isset($post->postflag) )
                        {
                          $class = 'marked';
                        }
                        $flag_option = get_flag_option($post, $class,  false ,$enableajax ) ;
                         echo "&nbsp;"    ;
                         echo ($flag_option)  ;
                    }
                
                //print course teacher icon if posted by course teacher
                
                $img_teacher = extendedforum_get_teacher_img($post->role, 'white');
                echo $img_teacher;
                echo  '</span> </div>'; //end divpostsubject
               				
                echo '<div id = "postthread' . $post->id . '" class= "threadhideme' . $post->discussion . '  threadhideme" >';
                
                     if (empty($USER->id)) {
                     $ownpost = false;
                     }  else {
                             $ownpost = ($USER->id == $post->userid);
                     }
                    
                   if (extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, $ownpost, $reply, $link, $ratings,
                                 '', '', $postread, true, $extendedforumtracked, $can_update_flag, $enableajax,  $showcommands, $linksubject,$indent)) {
                   $ratingsmenuused = true;
                 }
              
               echo  '</div>';
               
            }  //end depth if
             
            if (extendedforum_print_posts_threaded($course, $cm, $extendedforum, $discussion, $post, $depth-1, $ratings, $reply, $extendedforumtracked, $posts,  $can_update_flag,$enableajax, $showcommands, $indent )) {
                $ratingsmenuused = true;
            }
         echo "\n</div><!-- extendedforum_print_posts_threaded -->\n";   
        } //end for each post
     echo "\n<!-- Shimon End Disccussion?-->";
    }//end childern
    return $ratingsmenuused;
}

/**
 *
 */
function extendedforum_print_posts_nested($course, &$cm, $extendedforum, $discussion, $parent, $ratings, $reply, $extendedforumtracked, 
                                  $posts, $canupdateflag, $enableajax = 0, $commands = 1, $indent = 0) {
    
    global $USER, $CFG;
    
    $indent++;
    $link  = false;
    $ratingsmenuused = false;
    $class = 'indent'; 
      if ($indent > MAX_INDENT){
        $class = '';
      }
    
    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {
        
            echo '<div class="' . $class . '"  > <!--start extendedforum_print_posts_nested -->';
          //  echo '<div>'  ;
            if (empty($USER->id)) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);
            //1206
            if (extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, $ownpost, $reply, $link, $ratings,
                                 '', '', $postread, true, $extendedforumtracked, $canupdateflag, $enableajax, $commands,null,$indent)) {
                $ratingsmenuused = true;
                
            }
                
                if ($extendedforumtracked && !$CFG->extendedforum_usermarksread && !$postread) {
                   
                         extendedforum_tp_mark_post_read($USER->id, $post, $extendedforum->id);
                 }
            if (extendedforum_print_posts_nested($course, $cm, $extendedforum, $discussion, $post, $ratings, $reply, $extendedforumtracked, $posts, $canupdateflag, $enableajax , $commands, $indent)) {
                $ratingsmenuused = true;
            }
           echo "</div><!--extendedforum_print_posts_nested --> \n";
        }
    }
    return $ratingsmenuused;
}

/**
 * Returns all extendedforum posts since a given time in specified extendedforum.
 */
function extendedforum_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = get_record('course', 'id', $courseid);
    }

    $modinfo =& get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    if ($userid) {
        $userselect = "AND u.id = $userid";
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND gm.groupid = $groupid";
        $groupjoin   = "JOIN {$CFG->prefix}groups_members gm ON  gm.userid=u.id";
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    if (!$posts = get_records_sql("SELECT p.*, f.type AS extendedforumtype, d.extendedforum, d.groupid,
                                          d.timestart, d.timeend, d.userid AS duserid,
                                          u.firstname, u.lastname, u.email, u.picture, u.imagealt
                                     FROM {$CFG->prefix}extendedforum_posts p
                                          JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                                          JOIN {$CFG->prefix}extendedforum f             ON f.id = d.extendedforum
                                          JOIN {$CFG->prefix}user u              ON u.id = p.userid
                                          $groupjoin
                                    WHERE p.created > $timestart AND f.id = $cm->instance
                                          $userselect $groupselect
                                 ORDER BY p.id ASC")) { // order by initial posting date
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = get_context_instance(CONTEXT_MODULE, $cm->id);
    $viewhiddentimed = has_capability('mod/extendedforum:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    if (is_null($modinfo->groups)) {
        $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
    }

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->extendedforum_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!array_key_exists($post->groupid, $modinfo->groups[0])) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name,true);

    foreach ($printposts as $post) {
        $tmpactivity = new object();

        $tmpactivity->type         = 'extendedforum';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $post->modified;

        $tmpactivity->content = new object();
        $tmpactivity->content->id         = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject    = format_string($post->subject);
        $tmpactivity->content->parent     = $post->parent;

        $tmpactivity->user = new object();
        $tmpactivity->user->id        = $post->userid;
        $tmpactivity->user->firstname = $post->firstname;
        $tmpactivity->user->lastname  = $post->lastname;
        $tmpactivity->user->picture   = $post->picture;
        $tmpactivity->user->imagealt  = $post->imagealt;
        $tmpactivity->user->email     = $post->email;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 *
 */
function extendedforum_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG;

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="extendedforum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    print_user_picture($activity->user, $courseid);
    echo "</td><td class=\"$class\">";

    echo '<div class="title">';
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"$CFG->modpixpath/$activity->type/icon.gif\" ".
             "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/extendedforum/discuss.php?d={$activity->content->discussion}"
         ."#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';
      echo "</td></tr></table>";

    return;
}

/**
 *
 *   moves the post to a different post in the same extendedforum
 *   @param obj post
 *   @param int discussion 
 *   @param obj source_extendedforum 
 *   @param obj cm 
 *
 */    
function extendedforum_move_post_sameextendedforum($post, $targetpostid, $source_extendedforum, $cm)
{
    global $CFG;
     if (! $targetpost = get_record('extendedforum_posts', 'id', $targetpostid)) {
            error("destination post does not exist");
        }
     
     if(! $target_discussion = get_record('extendedforum_discussions' , 'id' , $targetpost->discussion) ){
       error ("destination post's discussion does not exist");
     }
     
     if(!$target_extendedforum = get_record('extendedforum', 'id', $target_discussion->extendedforum) ){
        error("destination post's extendedforum does not exist")   ;
     }
     
    set_field('extendedforum_posts', 'parent', $targetpostid, 'id', $post->id);
   
   extendedforum_change_discussionid($post->id, $target_discussion->id) ;
      require_once($CFG->libdir.'/rsslib.php');
      require_once('rsslib.php');
       // Delete the RSS files for the 2 extendedforums because we want to force
       // the regeneration of the feeds since the post have been
        // moved.
       if (!extendedforum_rss_delete_file($source_extendedforum) || !extendedforum_rss_delete_file($target_extendedforum)) {
                         error('Could not purge the cached RSS feeds for the source and/or'.
                        'destination extendedforum(s) - check your file permissionsextendedforums', 0);
      }
       add_to_log($target_discussion->course, "extendedforum", "move post to a discussion same extendedforum",
                           "discuss.php?d=$target_discussion->id", "$post->id", $cm->id);

}
/**
 *   function move_post
 *   moves a post to a different extendedforum
 *   @param obj $post
 *   @param obj $from_extendedforum
 *   @param obj $target_extendedforum   
 *   @param obj $cm  
 *
 *
 */
 function  move_post($post, $from_extendedforum, $from_discussion, $target_extendedforum, $return, $cm) 
 {
    global $CFG;
      
      if ($post->parent) { //create a new discussion for this post
            $newdiscussion = new object();
            $newdiscussion->course       =  addslashes ($from_discussion->course)  ;
            $newdiscussion->extendedforum        = $from_discussion->extendedforum;
            $newdiscussion->name         = addslashes($post->subject) ;
            $newdiscussion->firstpost    = $post->id;
            $newdiscussion->userid       = $from_discussion->userid;
            $newdiscussion->groupid      = $from_discussion->groupid;
            $newdiscussion->assessed     = $from_discussion->assessed;
            $newdiscussion->usermodified = $post->userid;
            $newdiscussion->timestart    = $from_discussion->timestart;
           $newdiscussion->timeend      = $from_discussion->timeend;
          
           if (!$discussionid = insert_record('extendedforum_discussions', $newdiscussion)) {
                            error('Could not create new discussion');
          }
          $newdiscussion->id =  $discussionid ;
          $newpost = new object();
          $newpost->id      = $post->id;
          $newpost->parent  = 0;
          $newpost->subject = addslashes($post->subject) ; 

           if (!update_record("extendedforum_posts", $newpost)) {
                  error('Could not update the original post');
          }
        extendedforum_change_discussionid($post->id, $discussionid);
        extendedforum_discussion_update_last_post($from_discussion->id); //previous discussion
        extendedforum_discussion_update_last_post($discussionid);   //current discussion
      
        extendedforum_change_read_discussionid($post->id, $discussionid)    ;
      
      }
      else  {    //move the whole discussion
          $discussionid = $post->parent;
          $newdiscussion = $from_discussion;
          
           set_field('extendedforum_read', 'extendedforumid', $target_extendedforum->id, 'discussionid', $from_discussion->id);
      }            
       //now move the discussion
      if (!extendedforum_move_attachments($newdiscussion, $target_extendedforum->id)) {
                           notify("Errors occurred while moving attachment directories - check your file permissions");
      }
     //update the extendedforum id for the discussion
     set_field('extendedforum_discussions', 'extendedforum', $target_extendedforum->id, 'id', $newdiscussion->id);
    
                    
      require_once($CFG->libdir.'/rsslib.php');
      require_once('rsslib.php');
       // Delete the RSS files for the 2 extendedforums because we want to force
       // the regeneration of the feeds since the discussions have been
        // moved.
       if (!extendedforum_rss_delete_file($from_extendedforum) || !extendedforum_rss_delete_file($target_extendedforum)) {
                         error('Could not purge the cached RSS feeds for the source and/or'.
                        'destination extendedforum(s) - check your file permissionsextendedforums', $return);
      }
       add_to_log($from_discussion->course, "extendedforum", "move post",
                           "discuss.php?d=$discussionid", "$post->id", $cm->id);
 
 }  
 
/**
 *   extendedforum_copy_posts
 *   creates a new copy of post to extendedforum
 *   @param obj $post
 *   @param obj $from_extendedforum
 *   @param obj $target_extendedforum   
 *   @param obj $cm 
 *
 */
 function extendedforum_copy_posts($post, $from_extendedforum, $from_disucssion, $target_extendedforum , $cm )  
 {
     global $CFG;
     $prevdiscussion = $post->discussion;
      $errormessage = '';
     $timenow = time();
     
     //get all the post that belong to the post discussion
      //first get the top post and create it
      $firstpost = get_record('extendedforum_posts', 'discussion',$prevdiscussion, 'parent', 0 ) ;
       if(!$firstpost)
       {
         error("cannot copy posts, parent post does not exist")  ;
       
       }
       
       $firstnewpost = new object();
       $firstnewpost->discussion = 0;
       $firstnewpost->parent = 0;
       $firstnewpost->userid = $firstpost->userid;
       $firstnewpost->created = $timenow;
       $firstnewpost->modified = $timenow;
       $firstnewpost->subject = addslashes($firstpost->subject);
       $firstnewpost->message = addslashes($firstpost->message);
       $firstnewpost->format = $firstpost->format;
       $firstnewpost->attachment = $firstpost->attachment;
       $firstnewpost->totalscore = $firstpost->totalscore;
       $firstnewpost->mailnow = 0;
       $firstnewpost->mark = $firstpost->mark;
       
       if (! $firstnewpost->id = insert_record("extendedforum_posts", $firstnewpost) ) {
        error("error copying parent post")       ;
       }
       
         //now create a new discussion
        $newdiscussion = new object();
        $newdiscussion->course       =  addslashes ($target_extendedforum->course)  ;
        $newdiscussion->extendedforum        = $target_extendedforum->id;
        $newdiscussion->name         = addslashes($firstnewpost->subject) ;
        $newdiscussion->firstpost    = $firstnewpost->id;
        $newdiscussion->userid       = $from_disucssion->userid;
        $newdiscussion->groupid      = $from_disucssion->groupid;
        $newdiscussion->assessed     = $from_disucssion->assessed;
        $newdiscussion->usermodified = $firstnewpost->userid;
        $newdiscussion->timestart    = $from_disucssion->timestart;
        $newdiscussion->timeend      = $from_disucssion->timeend;
        $newdiscussion->timemodified =   $timenow;
        if (!$newdiscussionid = insert_record('extendedforum_discussions', $newdiscussion)) {
                            error('Could not create new discussion');
        }
        //now assign the new id
        $newdiscussion->id  =  $newdiscussionid;
        //update firstpost discussion
           if (! set_field("extendedforum_posts", "discussion", $newdiscussionid, "id", $firstnewpost->id )) {
        delete_records("extendedforum_posts", "id", $firstnewpost->id );
        delete_records("extendedforum_discussions", "id", $newdiscussionid);
        return 0;
    }
        
        //to be able to assign the new parent ids
       //map the previous post ids with the new post id
      $post_id_mapping = array();
      $post_id_mapping[$firstpost->id] =  $firstnewpost ;
      
      //update read for firstpost
         if (extendedforum_tp_can_track_extendedforums($target_extendedforum) && extendedforum_tp_is_tracked($target_extendedforum)) {
             extendedforum_tp_mark_post_read($post->userid, $firstnewpost, $target_extendedforum->id);
        }   
      if ($rs = get_recordset('extendedforum_posts', 'discussion', $prevdiscussion, 'id')) {
       while ($prevpost = rs_fetch_next_record($rs)) {
        
          if($prevpost->parent)
          {  
           $newpost = new object();
           $newpost->discussion =  $newdiscussion->id ;
           $parentpost = $post_id_mapping[$prevpost->parent]  ;
           $newpost->parent= $parentpost->id;
           $newpost->userid = $prevpost->userid;
           $newpost->created = $timenow;
           $newpost->modified = $timenow;
           $newpost->subject = addslashes($prevpost->subject);
           $newpost->message = addslashes($prevpost->message);
           $newpost->format = $prevpost->format;
           $newpost->attachment = $prevpost->attachment;
           $newpost->totalscore = $prevpost->totalscore;
           $newpost->mailnow = 0;
           $newpost->mark = $prevpost->mark;
           
           
            if ( $newpost->id = insert_record("extendedforum_posts", $newpost) ) {
            $post_id_mapping[$prevpost->id] =  $newpost ; 
            
           //now update read
           //update read for firstpost
              if (extendedforum_tp_can_track_extendedforums($target_extendedforum) && extendedforum_tp_is_tracked($target_extendedforum)) {
                     extendedforum_tp_mark_post_read($newpost->userid,  $newpost, $target_extendedforum->id);
              }  
           
           
       
           }
           else
           {
          
            debugging('failed to create new post for post id' . $prevpost->id , DEBUG_DEVELOPER);
              continue;

           }
           
          } //end with parent
        
        } //end while
      }  //end rs
          
           
      //now handle the attachments
        
      if (!extendedforum_copy_attachments($from_disucssion, $target_extendedforum->id, $post_id_mapping)) {
                           notify("Errors occurred while coping attachment directories - check your file permissions");
      }
      require_once($CFG->libdir.'/rsslib.php');
      require_once('rsslib.php');
       // Delete the RSS files for the  extendedforum because we want to force
       // the regeneration of the feeds since the discussions have been
        // copied.
         if ( !extendedforum_rss_delete_file($target_extendedforum)) {
              error('Could not purge the cached RSS feeds for the '.
            'destination extendedforum(s) - check your file permissionsextendedforums', 0);
      }
        
   add_to_log($target_extendedforum->course, "extendedforum", "copy post",
                           "discuss.php?d=$post->discussion", "$post->id", $cm->id);
 }
 
 /**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * in extendedforum_post_read 
 * used when moving a post
 */
 
 function extendedforum_change_read_discussionid($postid, $discussionid)
 {
    set_field('extendedforum_read', 'discussionid', $discussionid, 'postid', $postid);
    if ($posts = get_records('extendedforum_posts', 'parent', $postid)) {
        foreach ($posts as $post) {
            extendedforum_change_read_discussionid($post->id, $discussionid);
        }
    }
    return true;
 
 }                   
/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 */
function extendedforum_change_discussionid($postid, $discussionid) {
    set_field('extendedforum_posts', 'discussion', $discussionid, 'id', $postid);
    if ($posts = get_records('extendedforum_posts', 'parent', $postid)) {
        foreach ($posts as $post) {
            extendedforum_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 */
function extendedforum_update_subscriptions_button($courseid, $extendedforumid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form $CFG->frametarget method=\"get\" action=\"$CFG->wwwroot/mod/extendedforum/subscribers.php\">".
           "<input type=\"hidden\" name=\"id\" value=\"$extendedforumid\" />".
           "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />".
           "<input type=\"submit\" value=\"$string\" /></form>";
}

/*
 * This function gets run whenever a role is assigned to a user in a context
 *
 * @param integer $userid
 * @param object $context
 * @return bool
 */
function extendedforum_role_assign($userid, $context, $roleid) {
    // check to see if this role comes with mod/extendedforum:initialsubscriptions
    $cap = role_context_capabilities($roleid, $context, 'mod/extendedforum:initialsubscriptions');
    $cap1 = role_context_capabilities($roleid, $context, 'moodle/course:view');
    // we are checking the role because has_capability() will pull this capability out
    // from other roles this user might have and resolve them, which is no good
    // the role needs course view to
    if (isset($cap['mod/extendedforum:initialsubscriptions']) && $cap['mod/extendedforum:initialsubscriptions'] == CAP_ALLOW &&
        isset($cap1['moodle/course:view']) && $cap1['moodle/course:view'] == CAP_ALLOW) {
        return extendedforum_add_user_default_subscriptions($userid, $context);
    } else {
        // MDL-8981, do not subscribe to extendedforum
        return true;
    }
}


/**
 * This function gets run whenever a role is assigned to a user in a context
 *
 * @param integer $userid
 * @param object $context
 * @return bool
 */
function extendedforum_role_unassign($userid, $context) {
    if (empty($context->contextlevel)) {
        return false;
    }

    extendedforum_remove_user_subscriptions($userid, $context);
    extendedforum_remove_user_tracking($userid, $context);

    return true;
}


/**
 * Add subscriptions for new users
 */
function extendedforum_add_user_default_subscriptions($userid, $context) {

    if (empty($context->contextlevel)) {
        return false;
    }

    switch ($context->contextlevel) {

        case CONTEXT_SYSTEM:   // For the whole site
             $rs = get_recordset('course', '', '', '', 'id');
             while ($course = rs_fetch_next_record($rs)) {
                 $subcontext = get_context_instance(CONTEXT_COURSE, $course->id);
                 extendedforum_add_user_default_subscriptions($userid, $subcontext);
             }
             rs_close($rs);
             break;

        case CONTEXT_COURSECAT:   // For a whole category
            $rs = get_recordset('course', 'category', $context->instanceid, '', 'id');
            while ($course = rs_fetch_next_record($rs)) {
                $subcontext = get_context_instance(CONTEXT_COURSE, $course->id);
                extendedforum_add_user_default_subscriptions($userid, $subcontext);
            }
            rs_close($rs);
             if ($categories = get_records('course_categories', 'parent', $context->instanceid)) {
                 foreach ($categories as $category) {
                     $subcontext = get_context_instance(CONTEXT_COURSECAT, $category->id);
                     extendedforum_add_user_default_subscriptions($userid, $subcontext);
                 }
             }
             break;


        case CONTEXT_COURSE:   // For a whole course
             if ($course = get_record('course', 'id', $context->instanceid)) {
                 if ($extendedforums = get_all_instances_in_course('extendedforum', $course, $userid, false)) {
                     foreach ($extendedforums as $extendedforum) {
                         if ($extendedforum->forcesubscribe != EXTENDEDFORUM_INITIALSUBSCRIBE) {
                             continue;
                         }
                         if ($modcontext = get_context_instance(CONTEXT_MODULE, $extendedforum->coursemodule)) {
                             if (has_capability('mod/extendedforum:viewdiscussion', $modcontext, $userid)) {
                                 extendedforum_subscribe($userid, $extendedforum->id);
                             }
                         }
                     }
                 }
             }
             break;

        case CONTEXT_MODULE:   // Just one extendedforum
             if ($cm = get_coursemodule_from_id('extendedforum', $context->instanceid)) {
                 if ($extendedforum = get_record('extendedforum', 'id', $cm->instance)) {
                     if ($extendedforum->forcesubscribe != EXTENDEDFORUM_INITIALSUBSCRIBE) {
                         continue;
                     }
                     if (has_capability('mod/extendedforum:viewdiscussion', $context, $userid)) {
                         extendedforum_subscribe($userid, $extendedforum->id);
                     }
                 }
             }
             break;
    }

    return true;
}


/**
 * Remove subscriptions for a user in a context
 */
function extendedforum_remove_user_subscriptions($userid, $context) {

    global $CFG;

    if (empty($context->contextlevel)) {
        return false;
    }

    switch ($context->contextlevel) {

        case CONTEXT_SYSTEM:   // For the whole site
            //if ($courses = get_my_courses($userid)) {
            // find all courses in which this user has a extendedforum subscription
            if ($courses = get_records_sql("SELECT c.id
                                              FROM {$CFG->prefix}course c,
                                                   {$CFG->prefix}extendedforum_subscriptions fs,
                                                   {$CFG->prefix}extendedforum f
                                                   WHERE c.id = f.course AND f.id = fs.extendedforum AND fs.userid = $userid
                                                   GROUP BY c.id")) {

                foreach ($courses as $course) {
                    $subcontext = get_context_instance(CONTEXT_COURSE, $course->id);
                    extendedforum_remove_user_subscriptions($userid, $subcontext);
                }
            }
            break;

        case CONTEXT_COURSECAT:   // For a whole category
             if ($courses = get_records('course', 'category', $context->instanceid, '', 'id')) {
                 foreach ($courses as $course) {
                     $subcontext = get_context_instance(CONTEXT_COURSE, $course->id);
                     extendedforum_remove_user_subscriptions($userid, $subcontext);
                 }
             }
             if ($categories = get_records('course_categories', 'parent', $context->instanceid, '', 'id')) {
                 foreach ($categories as $category) {
                     $subcontext = get_context_instance(CONTEXT_COURSECAT, $category->id);
                     extendedforum_remove_user_subscriptions($userid, $subcontext);
                 }
             }
             break;

        case CONTEXT_COURSE:   // For a whole course
             if ($course = get_record('course', 'id', $context->instanceid, '', '', '', '', 'id')) {
                // find all extendedforums in which this user has a subscription, and its coursemodule id
                if ($extendedforums = get_records_sql("SELECT f.id, cm.id as coursemodule
                                                 FROM {$CFG->prefix}extendedforum f,
                                                      {$CFG->prefix}modules m,
                                                      {$CFG->prefix}course_modules cm,
                                                      {$CFG->prefix}extendedforum_subscriptions fs
                                                WHERE fs.userid = $userid AND f.course = $context->instanceid
                                                      AND fs.extendedforum = f.id AND cm.instance = f.id
                                                      AND cm.module = m.id AND m.name = 'extendedforum'")) {

                     foreach ($extendedforums as $extendedforum) {
                         if ($modcontext = get_context_instance(CONTEXT_MODULE, $extendedforum->coursemodule)) {
                             if (!has_capability('mod/extendedforum:viewdiscussion', $modcontext, $userid)) {
                                 extendedforum_unsubscribe($userid, $extendedforum->id);
                             }
                         }
                     }
                 }
             }
             break;

        case CONTEXT_MODULE:   // Just one extendedforum
             if ($cm = get_coursemodule_from_id('extendedforum', $context->instanceid)) {
                 if ($extendedforum = get_record('extendedforum', 'id', $cm->instance)) {
                     if (!has_capability('mod/extendedforum:viewdiscussion', $context, $userid)) {
                         extendedforum_unsubscribe($userid, $extendedforum->id);
                     }
                 }
             }
             break;
    }

    return true;
}

// Functions to do with read tracking.

/**
 * Remove post tracking for a user in a context
 */
function extendedforum_remove_user_tracking($userid, $context) {

    global $CFG;

    if (empty($context->contextlevel)) {
        return false;
    }

    switch ($context->contextlevel) {

        case CONTEXT_SYSTEM:   // For the whole site
            // find all courses in which this user has tracking info
            $allcourses = array();
            if ($courses = get_records_sql("SELECT c.id
                                              FROM {$CFG->prefix}course c,
                                                   {$CFG->prefix}extendedforum_read fr,
                                                   {$CFG->prefix}extendedforum f
                                                   WHERE c.id = f.course AND f.id = fr.extendedforumid AND fr.userid = $userid
                                                   GROUP BY c.id")) {

                $allcourses = $allcourses + $courses;
            }
            if ($courses = get_records_sql("SELECT c.id
                                              FROM {$CFG->prefix}course c,
                                                   {$CFG->prefix}extendedforum_track_prefs ft,
                                                   {$CFG->prefix}extendedforum f
                                             WHERE c.id = f.course AND f.id = ft.extendedforumid AND ft.userid = $userid")) {

                $allcourses = $allcourses + $courses;
            }
            foreach ($allcourses as $course) {
                $subcontext = get_context_instance(CONTEXT_COURSE, $course->id);
                extendedforum_remove_user_tracking($userid, $subcontext);
            }
            break;

        case CONTEXT_COURSECAT:   // For a whole category
             if ($courses = get_records('course', 'category', $context->instanceid, '', 'id')) {
                 foreach ($courses as $course) {
                     $subcontext = get_context_instance(CONTEXT_COURSE, $course->id);
                     extendedforum_remove_user_tracking($userid, $subcontext);
                 }
             }
             if ($categories = get_records('course_categories', 'parent', $context->instanceid, '', 'id')) {
                 foreach ($categories as $category) {
                     $subcontext = get_context_instance(CONTEXT_COURSECAT, $category->id);
                     extendedforum_remove_user_tracking($userid, $subcontext);
                 }
             }
             break;

        case CONTEXT_COURSE:   // For a whole course
             if ($course = get_record('course', 'id', $context->instanceid, '', '', '', '', 'id')) {
                // find all extendedforums in which this user has reading tracked
                if ($extendedforums = get_records_sql("SELECT f.id, cm.id as coursemodule
                                                 FROM {$CFG->prefix}extendedforum f,
                                                      {$CFG->prefix}modules m,
                                                      {$CFG->prefix}course_modules cm,
                                                      {$CFG->prefix}extendedforum_read fr
                                                WHERE fr.userid = $userid AND f.course = $context->instanceid
                                                      AND fr.extendedforumid = f.id AND cm.instance = f.id
                                                      AND cm.module = m.id AND m.name = 'extendedforum'")) {

                     foreach ($extendedforums as $extendedforum) {
                         if ($modcontext = get_context_instance(CONTEXT_MODULE, $extendedforum->coursemodule)) {
                             if (!has_capability('mod/extendedforum:viewdiscussion', $modcontext, $userid)) {
                                extendedforum_tp_delete_read_records($userid, -1, -1, $extendedforum->id);
                             }
                         }
                     }
                 }

                // find all extendedforums in which this user has a disabled tracking
                if ($extendedforums = get_records_sql("SELECT f.id, cm.id as coursemodule
                                                 FROM {$CFG->prefix}extendedforum f,
                                                      {$CFG->prefix}modules m,
                                                      {$CFG->prefix}course_modules cm,
                                                      {$CFG->prefix}extendedforum_track_prefs ft
                                                WHERE ft.userid = $userid AND f.course = $context->instanceid
                                                      AND ft.extendedforumid = f.id AND cm.instance = f.id
                                                      AND cm.module = m.id AND m.name = 'extendedforum'")) {

                     foreach ($extendedforums as $extendedforum) {
                         if ($modcontext = get_context_instance(CONTEXT_MODULE, $extendedforum->coursemodule)) {
                             if (!has_capability('mod/extendedforum:viewdiscussion', $modcontext, $userid)) {
                                delete_records('extendedforum_track_prefs', 'userid', $userid, 'extendedforumid', $extendedforum->id);
                             }
                         }
                     }
                 }
             }
             break;

        case CONTEXT_MODULE:   // Just one extendedforum
             if ($cm = get_coursemodule_from_id('extendedforum', $context->instanceid)) {
                 if ($extendedforum = get_record('extendedforum', 'id', $cm->instance)) {
                     if (!has_capability('mod/extendedforum:viewdiscussion', $context, $userid)) {
                        delete_records('extendedforum_track_prefs', 'userid', $userid, 'extendedforumid', $extendedforum->id);
                        extendedforum_tp_delete_read_records($userid, -1, -1, $extendedforum->id);
                     }
                 }
             }
             break;
    }

    return true;
}

/**
 * Mark posts as read.
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function extendedforum_tp_mark_posts_read($user, $postids) {
    global $CFG;

    if (!extendedforum_tp_can_track_extendedforums(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->extendedforum_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = extendedforum_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    $sql = "SELECT id
              FROM {$CFG->prefix}extendedforum_read
             WHERE userid = $user->id AND postid IN (".implode(',', $postids).")";
    if ($existing = get_records_sql($sql)) {
        $existing = array_keys($existing);
    } else {
        $existing = array();
    }

    $new = array_diff($postids, $existing);

    if ($new) {
        $sql = "INSERT INTO {$CFG->prefix}extendedforum_read (userid, postid, discussionid, extendedforumid, firstread, lastread)

                SELECT $user->id, p.id, p.discussion, d.extendedforum, $now, $now
                  FROM {$CFG->prefix}extendedforum_posts p
                       JOIN {$CFG->prefix}extendedforum_discussions d       ON d.id = p.discussion
                       JOIN {$CFG->prefix}extendedforum f                   ON f.id = d.extendedforum
                       LEFT JOIN {$CFG->prefix}extendedforum_track_prefs tf ON (tf.userid = $user->id AND tf.extendedforumid = f.id)
                 WHERE p.id IN (".implode(',', $new).")
                       AND p.modified >= $cutoffdate
                       AND (f.trackingtype = ".EXTENDEDFORUM_TRACKING_ON."
                            OR (f.trackingtype = ".EXTENDEDFORUM_TRACKING_OPTIONAL." AND tf.id IS NULL))";
        $status = execute_sql($sql, false) && $status;
    }

    if ($existing) {
        $sql = "UPDATE {$CFG->prefix}extendedforum_read
                   SET lastread = $now
                 WHERE userid = $user->id AND postid IN (".implode(',', $existing).")";
        $status = execute_sql($sql, false) && $status;
    }

    return $status;
}

/**
 * Mark post as read.
 */
function extendedforum_tp_add_read_record($userid, $postid) {
    global $CFG;

    $now = time();
    $cutoffdate = $now - ($CFG->extendedforum_oldpostdays * 24 * 3600);

    if (!record_exists('extendedforum_read', 'userid', $userid, 'postid', $postid)) {
        $sql = "INSERT INTO {$CFG->prefix}extendedforum_read (userid, postid, discussionid, extendedforumid, firstread, lastread)

                SELECT $userid, p.id, p.discussion, d.extendedforum, $now, $now
                  FROM {$CFG->prefix}extendedforum_posts p
                       JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                 WHERE p.id = $postid AND p.modified >= $cutoffdate";
        return execute_sql($sql, false);

    } else {
        $sql = "UPDATE {$CFG->prefix}extendedforum_read
                   SET lastread = $now
                 WHERE userid = $userid AND postid = $userid";
        return execute_sql($sql, false);
    }
}

/**
 * Returns all records in the 'extendedforum_read' table matching the passed keys, indexed
 * by userid.
 */
function extendedforum_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $extendedforumid=-1) {
    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = \''.$userid.'\'';
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = \''.$postid.'\'';
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = \''.$discussionid.'\'';
    }
    if ($extendedforumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'extendedforumid = \''.$extendedforumid.'\'';
    }

    return get_records_select('extendedforum_read', $select);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 */
function extendedforum_tp_get_discussion_read_records($userid, $discussionid) {
    $select = 'userid = \''.$userid.'\' AND discussionid = \''.$discussionid.'\'';
    $fields = 'postid, firstread, lastread';
    return get_records_select('extendedforum_read', $select, '', $fields);
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 */
function extendedforum_tp_mark_post_read($userid, $post, $extendedforumid) {
    if (!extendedforum_tp_is_post_old($post)) {
        return extendedforum_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole extendedforum as read, for a given user
 */
function extendedforum_tp_mark_extendedforum_read($user, $extendedforumid, $groupid=false) {
    global $CFG;

    $cutoffdate = time() - ($CFG->extendedforum_oldpostdays*24*60*60);

    $groupsel = "";
    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = $groupid OR d.groupid = -1)";
    }

    $sql = "SELECT p.id
              FROM {$CFG->prefix}extendedforum_posts p
                   LEFT JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                   LEFT JOIN {$CFG->prefix}extendedforum_read r        ON (r.postid = p.id AND r.userid = $user->id)
             WHERE d.extendedforum = $extendedforumid
                   AND p.modified >= $cutoffdate AND r.id is NULL
                   $groupsel";

    if ($posts = get_records_sql($sql)) {
        $postids = array_keys($posts);
        return extendedforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 */
function extendedforum_tp_mark_discussion_read($user, $discussionid) {
    global $CFG;

    $cutoffdate = time() - ($CFG->extendedforum_oldpostdays*24*60*60);

    $sql = "SELECT p.id
              FROM {$CFG->prefix}extendedforum_posts p
                   LEFT JOIN {$CFG->prefix}extendedforum_read r ON (r.postid = p.id AND r.userid = $user->id)
             WHERE p.discussion = $discussionid
                   AND p.modified >= $cutoffdate AND r.id is NULL";

    if ($posts = get_records_sql($sql)) {
        $postids = array_keys($posts);
        return extendedforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 *
 */
function extendedforum_tp_is_post_read($userid, $post) {
    return (extendedforum_tp_is_post_old($post) ||
            record_exists('extendedforum_read', 'userid', $userid, 'postid', $post->id));
}

/**
 *
 */
function extendedforum_tp_is_post_old($post, $time=null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    
    
    return ($post->modified < ($time - ($CFG->extendedforum_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and discussion.
 */
function extendedforum_tp_count_discussion_read_records($userid, $discussionid) {
    global $CFG;

    $cutoffdate = isset($CFG->extendedforum_oldpostdays) ? (time() - ($CFG->extendedforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM '.$CFG->prefix.'extendedforum_discussions d '.
           'LEFT JOIN '.$CFG->prefix.'extendedforum_read r ON d.id = r.discussionid AND r.userid = '.$userid.' '.
           'LEFT JOIN '.$CFG->prefix.'extendedforum_posts p ON p.discussion = d.id '.
                'AND (p.modified < '.$cutoffdate.' OR p.id = r.postid) '.
           'WHERE d.id = '.$discussionid;

    return (count_records_sql($sql));
}

/**
 * Returns the count of records for the provided user and discussion.
 */
function extendedforum_tp_count_discussion_unread_posts($userid, $discussionid) {
    global $CFG;

    $cutoffdate = isset($CFG->extendedforum_oldpostdays) ? (time() - ($CFG->extendedforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM '.$CFG->prefix.'extendedforum_posts p '.
           'LEFT JOIN '.$CFG->prefix.'extendedforum_read r ON r.postid = p.id AND r.userid = '.$userid.' '.
           'WHERE p.discussion = '.$discussionid.' '.
                'AND p.modified >= '.$cutoffdate.' AND r.id is NULL';

    return (count_records_sql($sql));
}

/**
 * Returns the count of posts for the provided extendedforum and [optionally] group.
 */
function extendedforum_tp_count_extendedforum_posts($extendedforumid, $groupid=false) {
    global $CFG;

    $sql = 'SELECT COUNT(*) '.
           'FROM '.$CFG->prefix.'extendedforum_posts fp,'.$CFG->prefix.'extendedforum_discussions fd '.
           'WHERE fd.extendedforum = '.$extendedforumid.' AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = '.$groupid.' OR fd.groupid = -1)';
    }
    $count = count_records_sql($sql);


    return $count;
}

/**
 * Returns the count of records for the provided user and extendedforum and [optionally] group.
 */
function extendedforum_tp_count_extendedforum_read_records($userid, $extendedforumid, $groupid=false) {
    global $CFG;

    $cutoffdate = time() - ($CFG->extendedforum_oldpostdays*24*60*60);

    $groupsel = '';
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = $groupid OR d.groupid = -1)";
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {$CFG->prefix}extendedforum_posts p
                    JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
                    LEFT JOIN {$CFG->prefix}extendedforum_read r   ON (r.postid = p.id AND r.userid= $userid)
              WHERE d.extendedforum = $extendedforumid
                    AND (p.modified < $cutoffdate OR (p.modified >= $cutoffdate AND r.id IS NOT NULL))
                    $groupsel";

    return get_field_sql($sql);
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 */
function extendedforum_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG;

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->extendedforum_oldpostdays*24*60*60);

    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
    } else {
        $timedsql = "";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {$CFG->prefix}extendedforum_posts p
                   JOIN {$CFG->prefix}extendedforum_discussions d       ON d.id = p.discussion
                   JOIN {$CFG->prefix}extendedforum f                   ON f.id = d.extendedforum
                   JOIN {$CFG->prefix}course c                  ON c.id = f.course
                   LEFT JOIN {$CFG->prefix}extendedforum_read r         ON (r.postid = p.id AND r.userid = $userid)
                   LEFT JOIN {$CFG->prefix}extendedforum_track_prefs tf ON (tf.userid = $userid AND tf.extendedforumid = f.id)
             WHERE f.course = $courseid
                   AND p.modified >= $cutoffdate AND r.id is NULL
                   AND (f.trackingtype = ".EXTENDEDFORUM_TRACKING_ON."
                        OR (f.trackingtype = ".EXTENDEDFORUM_TRACKING_OPTIONAL." AND tf.id IS NULL))
                   $timedsql
          GROUP BY f.id";

          
    if ($return = get_records_sql($sql)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and extendedforum and [optionally] group.
 */
function extendedforum_tp_count_extendedforum_unread_posts($cm, $course) {
    global $CFG, $USER;

    static $readcache = array();

    $extendedforumid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = extendedforum_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$extendedforumid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$extendedforumid];
    }

    if (has_capability('moodle/site:accessallgroups', get_context_instance(CONTEXT_MODULE, $cm->id))) {
        return $readcache[$course->id][$extendedforumid];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo =& get_fast_modinfo($course);
    if (is_null($modinfo->groups)) {
        $modinfo->groups = groups_get_user_groups($course->id, $USER->id);
    }

    if (empty($CFG->enablegroupings)) {
        $mygroups = $modinfo->groups[0];
    } else {
        if (array_key_exists($cm->groupingid, $modinfo->groups)) {
            $mygroups = $modinfo->groups[$cm->groupingid];
        } else {
            $mygroups = false; // Will be set below
        }
    }

    // add all groups posts
    if (empty($mygroups)) {
        $mygroups = array(-1=>-1);
    } else {
        $mygroups[-1] = -1;
    }
    $mygroups = implode(',', $mygroups);


    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->extendedforum_oldpostdays*24*60*60);

    if (!empty($CFG->extendedforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(p.id)
              FROM {$CFG->prefix}extendedforum_posts p
                   JOIN {$CFG->prefix}extendedforum_discussions d ON p.discussion = d.id
                   LEFT JOIN {$CFG->prefix}extendedforum_read r   ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.extendedforum = $extendedforumid
                   AND p.modified >= $cutoffdate AND r.id is NULL
                   $timedsql
                   AND d.groupid IN ($mygroups)";

    return get_field_sql($sql);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 */
function extendedforum_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $extendedforumid=-1) {
    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = \''.$userid.'\'';
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = \''.$postid.'\'';
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = \''.$discussionid.'\'';
    }
    if ($extendedforumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'extendedforumid = \''.$extendedforumid.'\'';
    }
    
   
    if ($select == '') {
        return false;
    }
    else {
        return delete_records_select('extendedforum_read', $select);
    }
}
/**
 * Get a list of extendedforums not tracked by the user.
 *
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by extendedforum id, or false.
 */
function extendedforum_tp_get_untracked_extendedforums($userid, $courseid) {
    global $CFG;

    $sql = "SELECT f.id
              FROM {$CFG->prefix}extendedforum f
                   LEFT JOIN {$CFG->prefix}extendedforum_track_prefs ft ON (ft.extendedforumid = f.id AND ft.userid = $userid)
             WHERE f.course = $courseid
                   AND (f.trackingtype = ".EXTENDEDFORUM_TRACKING_OFF."
                        OR (f.trackingtype = ".EXTENDEDFORUM_TRACKING_OPTIONAL." AND ft.id IS NOT NULL))";

    if ($extendedforums = get_records_sql($sql)) {
        foreach ($extendedforums as $extendedforum) {
            $extendedforums[$extendedforum->id] = $extendedforum;
        }
        return $extendedforums;

    } else {
        return array();
    }
}

/**
 * Determine if a user can track extendedforums and optionally a particular extendedforum.
 * Checks the site settings, the user settings and the extendedforum settings (if
 * requested).
 *
 * @param mixed $extendedforum The extendedforum object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function extendedforum_tp_can_track_extendedforums($extendedforum=false, $user=false) {
    global $USER, $CFG;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->extendedforum_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($extendedforum === false) {
        // general abitily to track extendedforums
        return (bool)$user->trackforums;
    }
    

    // Work toward always passing an object...
    if (is_numeric($extendedforum)) {
        debugging('Better use proper extendedforum object.', DEBUG_DEVELOPER);
        $extendedforum = get_record('extendedforum', 'id', $extendedforum, '','','','', 'id,trackingtype');
    }

    $extendedforumallows = ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_OPTIONAL);
    $extendedforumforced = ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_ON);
    
  
    return ($extendedforumforced || $extendedforumallows)  && !empty($user->trackforums);
}

/**
 * Tells whether a specific extendedforum is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @param mixed $extendedforum If int, the id of the extendedforum being checked; if object, the extendedforum object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function extendedforum_tp_is_tracked($extendedforum, $user=false) {
    global $USER, $CFG;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($extendedforum)) {
        debugging('Better use proper extendedforum object.', DEBUG_DEVELOPER);
        $extendedforum = get_record('extendedforum', 'id', $extendedforum);
    }

    if (!extendedforum_tp_can_track_extendedforums($extendedforum, $user)) {
   
        return false;
    }

    $extendedforumallows = ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_OPTIONAL);
    $extendedforumforced = ($extendedforum->trackingtype == EXTENDEDFORUM_TRACKING_ON);

    return $extendedforumforced ||
           ($extendedforumallows && get_record('extendedforum_track_prefs', 'userid', $user->id, 'extendedforumid', $extendedforum->id) === false);
}

/**
 *
 */
function extendedforum_tp_start_tracking($extendedforumid, $userid=false) {
    global $USER;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return delete_records('extendedforum_track_prefs', 'userid', $userid, 'extendedforumid', $extendedforumid);
}

/**
 *
 */
function extendedforum_tp_stop_tracking($extendedforumid, $userid=false) {
    global $USER;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!record_exists('extendedforum_track_prefs', 'userid', $userid, 'extendedforumid', $extendedforumid)) {
        $track_prefs = new object();
        $track_prefs->userid = $userid;
        $track_prefs->extendedforumid = $extendedforumid;
        insert_record('extendedforum_track_prefs', $track_prefs);
    }

    return extendedforum_tp_delete_read_records($userid, -1, -1, $extendedforumid);
}


/**
 * Clean old records from the extendedforum_read table.
 */
function extendedforum_tp_clean_read_records() {
    global $CFG;

    if (!isset($CFG->extendedforum_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the extendedforum_read table.
    $cutoffdate = time() - ($CFG->extendedforum_oldpostdays*24*60*60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {$CFG->prefix}extendedforum_posts fp
                   JOIN {$CFG->prefix}extendedforum_read fr ON fr.postid=fp.id";
    if (!$first = get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {$CFG->prefix}extendedforum_read
             WHERE postid IN (SELECT fp.id
                                FROM {$CFG->prefix}extendedforum_posts fp
                               WHERE fp.modified >= $first AND fp.modified < $cutoffdate)";
    execute_sql($sql, false);
}

/**
 * Sets the last post for a given discussion
 **/
function extendedforum_discussion_update_last_post($discussionid) {
    global $CFG, $db;

// Check the given discussion exists
    if (!record_exists('extendedforum_discussions', 'id', $discussionid)) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = 'SELECT id, userid, modified '.
           'FROM '.$CFG->prefix.'extendedforum_posts '.
           'WHERE discussion='.$discussionid.' '.
           'ORDER BY modified DESC ';

// Lets go find the last post
    if (($lastpost = get_record_sql($sql, true))) {
        $discussionobject = new Object;
        $discussionobject->id = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        if (update_record('extendedforum_discussions', $discussionobject)) {
            return $lastpost->id;
        }
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}


/**
 *
 */
function extendedforum_get_view_actions() {
    return array('view discussion','search','extendedforum','extendedforums','subscribers');
}

/**
 *
 */
function extendedforum_get_post_actions() {
    //return array('add discussion','add post','delete discussion','delete post','move discussion','prune post','update post');
    return array('add discussion','add post','delete discussion','delete post','move discussion','update post');
}

/**
 * this function returns all the separate extendedforum ids, given a courseid
 * @param int $courseid
 * @return array
 */
function extendedforum_get_separate_modules($courseid) {

    global $CFG,$db;
    $extendedforummodule = get_record("modules", "name", "extendedforum");

    $sql = 'SELECT f.id, f.id FROM '.$CFG->prefix.'extendedforum f, '.$CFG->prefix.'course_modules cm WHERE
           f.id = cm.instance AND cm.module ='.$extendedforummodule->id.' AND cm.visible = 1 AND cm.course = '.$courseid.'
           AND cm.groupmode ='.SEPARATEGROUPS;

    return get_records_sql($sql);

}

/**
 *
 */
function extendedforum_check_throttling($extendedforum, $cm=null) {
    global $USER, $CFG;

    if (is_numeric($extendedforum)) {
        $extendedforum = get_record('extendedforum','id',$extendedforum);
    }
    if (!is_object($extendedforum)) {
        return false;  // this is broken.
    }

    if (empty($extendedforum->blockafter)) {
        return true;
    }

    if (empty($extendedforum->blockperiod)) {
        return true;
    }

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $extendedforum->course)) {
            error('Course Module ID was incorrect');
        }
    }

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    if(!has_capability('mod/extendedforum:throttlingapplies', $modcontext)) {
        return true;
    }

    // get the number of posts in the last period we care about
    $timenow = time();
    $timeafter = $timenow - $extendedforum->blockperiod;

    $numposts = count_records_sql('SELECT COUNT(p.id) FROM '.$CFG->prefix.'extendedforum_posts p'
                                  .' JOIN '.$CFG->prefix.'extendedforum_discussions d'
                                  .' ON p.discussion = d.id WHERE d.extendedforum = '.$extendedforum->id
                                  .' AND p.userid = '.$USER->id.' AND p.created > '.$timeafter);

    $a = new object();
    $a->blockafter = $extendedforum->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime'.$extendedforum->blockperiod);

    if ($extendedforum->blockafter <= $numposts) {
        print_error('extendedforumblockingtoomanyposts', 'error', $CFG->wwwroot.'/mod/extendedforum/view.php?f='.$extendedforum->id, $a);
    }
    if ($extendedforum->warnafter <= $numposts) {
        notify(get_string('extendedforumblockingalmosttoomanyposts','extendedforum',$a));
    }


}


/**
 * Removes all grades from gradebook
 * @param int $courseid
 * @param string optional type
 */
function extendedforum_reset_gradebook($courseid, $type='') {
    global $CFG;

    $type = $type ? "AND f.type='$type'" : '';

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {$CFG->prefix}extendedforum f, {$CFG->prefix}course_modules cm, {$CFG->prefix}modules m
             WHERE m.name='extendedforum' AND m.id=cm.module AND cm.instance=f.id AND f.course=$courseid $type";

    if ($extendedforums = get_records_sql($sql)) {
        foreach ($extendedforums as $extendedforum) {
            extendedforum_grade_item_update($extendedforum, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified extendedforum
 * and clean up any related data.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function extendedforum_reset_userdata($data) {
    global $CFG;
    require_once($CFG->libdir.'/filelib.php');

    $componentstr = get_string('modulenameplural', 'extendedforum');
    $status = array();

    $removeposts = false;
    if (!empty($data->reset_extendedforum_all)) {
        $removeposts = true;
        $typesql     = "";
        $typesstr    = get_string('resetextendedforumsall', 'extendedforum');
        $types       = array();

    } else if (!empty($data->reset_extendedforum_types)){
        $removeposts = true;
        $typesql     = "";
        $types       = array();
        $extendedforum_types_all = extendedforum_get_extendedforum_types_all();
        foreach ($data->reset_extendedforum_types as $type) {
            if (!array_key_exists($type, $extendedforum_types_all)) {
                continue;
            }
            $typesql .= " AND f.type='$type'";
            $types[] = $extendedforum_types_all[$type];
        }
        $typesstr = get_string('resetextendedforums', 'extendedforum').': '.implode(', ', $types);

    }

    $alldiscussionssql = "SELECT fd.id
                            FROM {$CFG->prefix}extendedforum_discussions fd, {$CFG->prefix}extendedforum f
                           WHERE f.course={$data->courseid} AND f.id=fd.extendedforum";

    $allextendedforumssql      = "SELECT f.id
                            FROM {$CFG->prefix}extendedforum f
                           WHERE f.course={$data->courseid}";

    $allpostssql       = "SELECT fp.id
                            FROM {$CFG->prefix}extendedforum_posts fp, {$CFG->prefix}extendedforum_discussions fd, {$CFG->prefix}extendedforum f
                           WHERE f.course={$data->courseid} AND f.id=fd.extendedforum AND fd.id=fp.discussion";

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $extendedforumssql      = "$allextendedforumssql $typesql";
        $postssql       = "$allpostssql $typesql";

        // first delete all read flags
        delete_records_select('extendedforum_read', "extendedforumid IN ($extendedforumssql)");

        // remove tracking prefs
        delete_records_select('extendedforum_track_prefs', "extendedforumid IN ($extendedforumssql)");

        // remove posts from queue
        delete_records_select('extendedforum_queue', "discussionid IN ($discussionssql)");

        // remove ratings
        delete_records_select('extendedforum_ratings', "post IN ($postssql)");

        // all posts - initial posts must be kept in single simple discussion extendedforums
        delete_records_select('extendedforum_posts', "discussion IN ($discussionssql) AND parent <> 0"); // first all children
        delete_records_select('extendedforum_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0"); // now the initial posts for non single simple

        // finally all discussions except single simple extendedforums
        delete_records_select('extendedforum_discussions', "extendedforum IN ($extendedforumssql AND f.type <> 'single')");

        // now get rid of all attachments
        if ($extendedforums = get_records_sql($extendedforumssql)) {
            foreach ($extendedforums as $extendedforumid=>$unused) {
                fulldelete($CFG->dataroot.$data->courseid.'/moddata/extendedforum/'.$extendedforumid);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                extendedforum_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    extendedforum_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

    // remove all ratings
    if (!empty($data->reset_extendedforum_ratings)) {
        delete_records_select('extendedforum_ratings', "post IN ($allpostssql)");
        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            extendedforum_reset_gradebook($data->courseid);
        }
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_extendedforum_subscriptions)) {
        delete_records_select('extendedforum_subscriptions', "extendedforum IN ($allextendedforumssql)");
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resetsubscriptions','extendedforum'), 'error'=>false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_extendedforum_track_prefs)) {
        delete_records_select('extendedforum_track_prefs', "extendedforumid IN ($allextendedforumssql)");
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resettrackprefs','extendedforum'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('extendedforum', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 * @param $mform form passed by reference
 */
function extendedforum_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'extendedforumheader', get_string('modulenameplural', 'extendedforum'));

    $mform->addElement('checkbox', 'reset_extendedforum_all', get_string('resetextendedforumsall','extendedforum'));

    $mform->addElement('select', 'reset_extendedforum_types', get_string('resetextendedforums', 'extendedforum'), extendedforum_get_extendedforum_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_extendedforum_types');
    $mform->disabledIf('reset_extendedforum_types', 'reset_extendedforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_extendedforum_subscriptions', get_string('resetsubscriptions','extendedforum'));
    $mform->setAdvanced('reset_extendedforum_subscriptions');

    $mform->addElement('checkbox', 'reset_extendedforum_track_prefs', get_string('resettrackprefs','extendedforum'));
    $mform->setAdvanced('reset_extendedforum_track_prefs');
    $mform->disabledIf('reset_extendedforum_track_prefs', 'reset_extendedforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_extendedforum_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_extendedforum_ratings', 'reset_extendedforum_all', 'checked');
}

/**
 * Course reset form defaults.
 */
function extendedforum_reset_course_form_defaults($course) {
    return array('reset_extendedforum_all'=>1, 'reset_extendedforum_subscriptions'=>0, 'reset_extendedforum_track_prefs'=>0, 'reset_extendedforum_ratings'=>1);
}

/**
 * Converts a extendedforum to use the Roles System
 * @param $extendedforum        - a extendedforum object with the same attributes as a record
 *                        from the extendedforum database table
 * @param $extendedforummodid   - the id of the extendedforum module, from the modules table
 * @param $teacherroles - array of roles that have moodle/legacy:teacher
 * @param $studentroles - array of roles that have moodle/legacy:student
 * @param $guestroles   - array of roles that have moodle/legacy:guest
 * @param $cmid         - the course_module id for this extendedforum instance
 * @return boolean      - extendedforum was converted or not
 */
function extendedforum_convert_to_roles($extendedforum, $extendedforummodid, $teacherroles=array(),
                                $studentroles=array(), $guestroles=array(), $cmid=NULL) {

    global $CFG;

    if (!isset($extendedforum->open) && !isset($extendedforum->assesspublic)) {
        // We assume that this extendedforum has already been converted to use the
        // Roles System. Columns extendedforum.open and extendedforum.assesspublic get dropped
        // once the extendedforum module has been upgraded to use Roles.
        return false;
    }

    if ($extendedforum->type == 'teacher') {

        // Teacher extendedforums should be converted to normal extendedforums that
        // use the Roles System to implement the old behavior.
        // Note:
        //   Seems that teacher extendedforums were never backed up in 1.6 since they
        //   didn't have an entry in the course_modules table.
        require_once($CFG->dirroot.'/course/lib.php');

        if (count_records('extendedforum_discussions', 'extendedforum', $extendedforum->id) == 0) {
            // Delete empty teacher extendedforums.
            delete_records('extendedforum', 'id', $extendedforum->id);
        } else {
            // Create a course module for the extendedforum and assign it to
            // section 0 in the course.
            $mod = new object;
            $mod->course = $extendedforum->course;
            $mod->module = $extendedforummodid;
            $mod->instance = $extendedforum->id;
            $mod->section = 0;
            $mod->visible = 0;     // Hide the extendedforum
            $mod->visibleold = 0;  // Hide the extendedforum
            $mod->groupmode = 0;

            if (!$cmid = add_course_module($mod)) {
                error('Could not create new course module instance for the teacher extendedforum');
            } else {
                $mod->coursemodule = $cmid;
                if (!$sectionid = add_mod_to_section($mod)) {
                    error('Could not add converted teacher extendedforum instance to section 0 in the course');
                } else {
                    if (!set_field('course_modules', 'section', $sectionid, 'id', $cmid)) {
                        error('Could not update course module with section id');
                    }
                }
            }

            // Change the extendedforum type to general.
            $extendedforum->type = 'general';
            if (!update_record('extendedforum', $extendedforum)) {
                error('Could not change extendedforum from type teacher to type general');
            }

            $context = get_context_instance(CONTEXT_MODULE, $cmid);

            // Create overrides for default student and guest roles (prevent).
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/extendedforum:viewdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:viewhiddentimedposts', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:replypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:viewrating', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:viewanyrating', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:rate', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:createattachment', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:deleteownpost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:deleteanypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:splitdiscussions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:movediscussions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:editanypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:viewqandawithoutposting', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:viewsubscribers', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:managesubscriptions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/extendedforum:throttlingapplies', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($guestroles as $guestrole) {
                assign_capability('mod/extendedforum:viewdiscussion', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:viewhiddentimedposts', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:startdiscussion', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:replypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:viewrating', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:viewanyrating', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:rate', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:createattachment', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:deleteownpost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:deleteanypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:splitdiscussions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:movediscussions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:editanypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:viewqandawithoutposting', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:viewsubscribers', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:managesubscriptions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/extendedforum:throttlingapplies', CAP_PREVENT, $guestrole->id, $context->id);
            }
        }
    } else {
        // Non-teacher extendedforum.

        if (empty($cmid)) {
            // We were not given the course_module id. Try to find it.
            if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id)) {
                notify('Could not get the course module for the extendedforum');
                return false;
            } else {
                $cmid = $cm->id;
            }
        }
        $context = get_context_instance(CONTEXT_MODULE, $cmid);

        // $extendedforum->open defines what students can do:
        //   0 = No discussions, no replies
        //   1 = No discussions, but replies are allowed
        //   2 = Discussions and replies are allowed
        switch ($extendedforum->open) {
            case 0:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                    assign_capability('mod/extendedforum:replypost', CAP_PREVENT, $studentrole->id, $context->id);
                }
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                    assign_capability('mod/extendedforum:replypost', CAP_ALLOW, $studentrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:startdiscussion', CAP_ALLOW, $studentrole->id, $context->id);
                    assign_capability('mod/extendedforum:replypost', CAP_ALLOW, $studentrole->id, $context->id);
                }
                break;
        }

        // $extendedforum->assessed defines whether extendedforum rating is turned
        // on (1 or 2) and who can rate posts:
        //   1 = Everyone can rate posts
        //   2 = Only teachers can rate posts
        switch ($extendedforum->assessed) {
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:rate', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/extendedforum:rate', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:rate', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/extendedforum:rate', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }

        // $extendedforum->assesspublic defines whether students can see
        // everybody's ratings:
        //   0 = Students can only see their own ratings
        //   1 = Students can see everyone's ratings
        switch ($extendedforum->assesspublic) {
            case 0:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:viewanyrating', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/extendedforum:viewanyrating', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/extendedforum:viewanyrating', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/extendedforum:viewanyrating', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }

        if (empty($cm)) {
            $cm = get_record('course_modules', 'id', $cmid);
        }

        // $cm->groupmode:
        // 0 - No groups
        // 1 - Separate groups
        // 2 - Visible groups
        switch ($cm->groupmode) {
            case 0:
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }
    }
    return true;
}

/**
 * Returns array of extendedforum aggregate types
 */
function extendedforum_get_aggregate_types() {
    return array (EXTENDEDFORUM_AGGREGATE_NONE  => get_string('aggregatenone', 'extendedforum'),
                  EXTENDEDFORUM_AGGREGATE_AVG   => get_string('aggregateavg', 'extendedforum'),
                  EXTENDEDFORUM_AGGREGATE_COUNT => get_string('aggregatecount', 'extendedforum'),
                  EXTENDEDFORUM_AGGREGATE_MAX   => get_string('aggregatemax', 'extendedforum'),
                  EXTENDEDFORUM_AGGREGATE_MIN   => get_string('aggregatemin', 'extendedforum'),
                  EXTENDEDFORUM_AGGREGATE_SUM   => get_string('aggregatesum', 'extendedforum'));
}

/**
 * Returns array of extendedforum layout modes
 */
function extendedforum_get_layout_modes() {
  /*  return array (EXTENDEDFORUM_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'extendedforum'),
                  EXTENDEDFORUM_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'extendedforum'),
                  EXTENDEDFORUM_MODE_THREADED   => get_string('modethreaded', 'extendedforum'),
                  EXTENDEDFORUM_MODE_NESTED     => get_string('modenested', 'extendedforum'));
    */
    
    return array( EXTENDEDFORUM_MODE_ONLY_DISCUSSION  => get_string('modeonlydiscussion', 'extendedforum')   ,
                  EXTENDEDFORUM_MODE_ALL => get_string('modeall', 'extendedforum')  );
}

/**
 * Returns array of extendedforum types
 */
function extendedforum_get_extendedforum_types() {
    return array ('general'  => get_string('generalextendedforum', 'extendedforum'),
                  'eachuser' => get_string('eachuserextendedforum', 'extendedforum'),
                  'single'   => get_string('singleextendedforum', 'extendedforum'),
                  'qanda'    => get_string('qandaextendedforum', 'extendedforum'));
}

/**
 * Returns array of all extendedforum layout modes
 */
function extendedforum_get_extendedforum_types_all() {
    return array ('news'     => get_string('namenews','extendedforum'),
                  'social'   => get_string('namesocial','extendedforum'),
                  'general'  => get_string('generalextendedforum', 'extendedforum'),
                  'eachuser' => get_string('eachuserextendedforum', 'extendedforum'),
                  'single'   => get_string('singleextendedforum', 'extendedforum'),
                  'qanda'    => get_string('qandaextendedforum', 'extendedforum'));
}

/**
 * Returns array of extendedforum open modes
 */
function extendedforum_get_open_modes() {
    return array ('2' => get_string('openmode2', 'extendedforum'),
                  '1' => get_string('openmode1', 'extendedforum'),
                  '0' => get_string('openmode0', 'extendedforum') );
}

/**
 * Returns all other caps used in module
 */
function extendedforum_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent');
}


function write_log($data){
$myFile = "E:\\tmp\\mylog.log";
$fh = fopen($myFile, 'w') or die("can't open file");
$stringData = "Bobby Bopper\n";
fwrite($fh, $stringData);
$stringData = "Tracy Tanner\n";
fwrite($fh, $stringData);
fclose($fh);
}


?>
