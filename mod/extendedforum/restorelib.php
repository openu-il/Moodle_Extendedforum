<?php //$Id: restorelib.php,v 1.60.4.6 2011/02/23 20:23:53 moodlerobot Exp $
    //This php script contains all the stuff to backup/restore
    //extendedforum mods

    //This is the "graphical" structure of the extendedforum mod:
    //
    //                               extendedforum                                      
    //                            (CL,pk->id)
    //                                 |
    //         ---------------------------------------------------        
    //         |                                                 |
    //    subscriptions                                  extendedforum_discussions
    //(UL,pk->id, fk->extendedforum)           ---------------(UL,pk->id, fk->extendedforum)
    //                                 |                         |
    //                                 |                         |
    //                                 |                         |
    //                                 |                     extendedforum_posts
    //                                 |-------------(UL,pk->id,fk->discussion,
    //                                 |                  nt->parent,files) 
    //                                 |                         |
    //                                 |                         |
    //                                 |                         |
    //                            extendedforum_read                extendedforum_ratings
    //                       (UL,pk->id,fk->post        (UL,pk->id,fk->post)
    //
    // Meaning: pk->primary key field of the table
    //          fk->foreign key to link with parent
    //          nt->nested field (recursive data)
    //          CL->course level info
    //          UL->user level info
    //          files->table may have files)
    //
    //-----------------------------------------------------------

    function extendedforum_restore_mods($mod,$restore) {
        
        global $CFG,$db;

        $status = true;

        //Get record from backup_ids
        $data = backup_getid($restore->backup_unique_code,$mod->modtype,$mod->id);

        if ($data) {
            //Now get completed xmlized object
            $info = $data->info;
            //if necessary, write to restorelog and adjust date/time fields
            if ($restore->course_startdateoffset) {
                restore_log_date_changes('Forum', $restore, $info['MOD']['#'], array('ASSESSTIMESTART', 'ASSESSTIMEFINISH'));
            }
            //traverse_xmlize($info);                                                                     //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //Now, build the EXTENDEDFORUM record structure
            $extendedforum->course = $restore->course_id;
            $extendedforum->type = backup_todb($info['MOD']['#']['TYPE']['0']['#']);
            $extendedforum->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
            $extendedforum->intro = backup_todb($info['MOD']['#']['INTRO']['0']['#']);
            
            // These get dropped in Moodle 1.7 when the new Roles System gets
            // set up. Therefore they might or not be there depending on whether
            // we are restoring a 1.6+ extendedforum or a 1.7 or later extendedforum backup.
            if (isset($info['MOD']['#']['OPEN']['0']['#'])) {
                $extendedforum->open = backup_todb($info['MOD']['#']['OPEN']['0']['#']);
            }
            if (isset($info['MOD']['#']['ASSESSPUBLIC']['0']['#'])) {
                $extendedforum->assesspublic = backup_todb($info['MOD']['#']['ASSESSPUBLIC']['0']['#']);
            }
            
            $extendedforum->assessed = backup_todb($info['MOD']['#']['ASSESSED']['0']['#']);  
            $extendedforum->assesstimestart = backup_todb($info['MOD']['#']['ASSESSTIMESTART']['0']['#']);
            $extendedforum->assesstimefinish = backup_todb($info['MOD']['#']['ASSESSTIMEFINISH']['0']['#']);
            $extendedforum->maxbytes = backup_todb($info['MOD']['#']['MAXBYTES']['0']['#']);
            $extendedforum->scale = backup_todb($info['MOD']['#']['SCALE']['0']['#']);
            $extendedforum->forcesubscribe = backup_todb($info['MOD']['#']['FORCESUBSCRIBE']['0']['#']);
            $extendedforum->trackingtype = backup_todb($info['MOD']['#']['TRACKINGTYPE']['0']['#']);
            $extendedforum->rsstype = backup_todb($info['MOD']['#']['RSSTYPE']['0']['#']);
            $extendedforum->rssarticles = backup_todb($info['MOD']['#']['RSSARTICLES']['0']['#']);
            $extendedforum->timemodified = backup_todb($info['MOD']['#']['TIMEMODIFIED']['0']['#']);
            $extendedforum->warnafter = isset($info['MOD']['#']['WARNAFTER']['0']['#'])?backup_todb($info['MOD']['#']['WARNAFTER']['0']['#']):'';
            $extendedforum->blockafter = isset($info['MOD']['#']['BLOCKAFTER']['0']['#'])?backup_todb($info['MOD']['#']['BLOCKAFTER']['0']['#']):'';
            $extendedforum->blockperiod = isset($info['MOD']['#']['BLOCKPERIOD']['0']['#'])?backup_todb($info['MOD']['#']['BLOCKPERIOD']['0']['#']):'';


            //We have to recode the scale field if it's <0 (positive is a grade, not a scale)
            if ($extendedforum->scale < 0) {
                $scale = backup_getid($restore->backup_unique_code,"scale",abs($extendedforum->scale));
                if ($scale) {
                    $extendedforum->scale = -($scale->new_id);
                }
            }
            
            $newid = insert_record("extendedforum", $extendedforum);


            //Do some output
            if (!defined('RESTORE_SILENTLY')) {
                echo "<li>".get_string("modulename","extendedforum")." \"".format_string(stripslashes($extendedforum->name),true)."\"</li>";
            }
            backup_flush(300);

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,$mod->modtype,
                             $mod->id, $newid);

                $extendedforum->id = $newid;

                //Now check if want to restore user data and do it.
                if (restore_userdata_selected($restore,'extendedforum',$mod->id)) {
                    //Restore extendedforum_subscriptions
                    $status = extendedforum_subscriptions_restore_mods ($newid,$info,$restore);
                    if ($status) {
                        //Restore extendedforum_discussions
                        $status = extendedforum_discussions_restore_mods ($newid,$info,$restore);
                    }
                    if ($status) {
                        //Restore extendedforum_read
                        $status = extendedforum_read_restore_mods ($newid,$info,$restore);
                    }
                }

                // If extendedforum type is single, just recreate the initial discussion/post automatically
                // if it hasn't been created still (because no user data was selected on backup or
                // restore.
                if ($extendedforum->type == 'single' && !record_exists('extendedforum_discussions', 'extendedforum', $newid)) {
                    //Load extendedforum/lib.php
                    require_once ($CFG->dirroot.'/mod/extendedforum/lib.php');
                    // Calculate the default format
                    if (can_use_html_editor()) {
                        $defaultformat = FORMAT_HTML;
                    } else {
                        $defaultformat = FORMAT_MOODLE;
                    }
                    //Create discussion/post data
                    $sd = new stdClass;
                    $sd->course   = $extendedforum->course;
                    $sd->extendedforum    = $newid;
                    $sd->name     = $extendedforum->name;
                    $sd->intro    = $extendedforum->intro;
                    $sd->assessed = $extendedforum->assessed;
                    $sd->format   = $defaultformat;
                    $sd->mailnow  = false;
                    //Insert dicussion/post data
                    $sdid = extendedforum_add_discussion($sd, $sd->intro, $extendedforum);
                    //Now, mark the initial post of the discussion as mailed!
                    if ($sdid) {
                        set_field ('extendedforum_posts','mailed', '1', 'discussion', $sdid);
                    }
                }
            
            } else {
                $status = false;
            }

            // If the backup contained $extendedforum->open and $extendedforum->assesspublic,
            // we need to convert the extendedforum to use Roles. It means the backup
            // was made pre Moodle 1.7.
            if (isset($extendedforum->open) && isset($extendedforum->assesspublic)) {

                $extendedforummod = get_record('modules', 'name', 'extendedforum');
                
                if (!$teacherroles = get_roles_with_capability('moodle/legacy:teacher', CAP_ALLOW)) {
                      notice('Default teacher role was not found. Roles and permissions '.
                             'for all your extendedforums will have to be manually set.');
                }
                if (!$studentroles = get_roles_with_capability('moodle/legacy:student', CAP_ALLOW)) {
                      notice('Default student role was not found. Roles and permissions '.
                             'for all your extendedforums will have to be manually set.');
                }
                if (!$guestroles = get_roles_with_capability('moodle/legacy:guest', CAP_ALLOW)) {
                      notice('Default guest role was not found. Roles and permissions '.
                             'for teacher extendedforums will have to be manually set.');
                }
                require_once($CFG->dirroot.'/mod/extendedforum/lib.php');
                extendedforum_convert_to_roles($extendedforum, $extendedforummod->id,
                                       $teacherroles, $studentroles, $guestroles,
                                       $restore->mods['extendedforum']->instances[$mod->id]->restored_as_course_module);
            }
            
        } else {
            $status = false;
        }

        return $status;
    }

    //This function restores the extendedforum_subscriptions
    function extendedforum_subscriptions_restore_mods($extendedforum_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the discussions array
        $subscriptions = array();
        if (isset($info['MOD']['#']['SUBSCRIPTIONS']['0']['#']['SUBSCRIPTION'])) {
            $subscriptions = $info['MOD']['#']['SUBSCRIPTIONS']['0']['#']['SUBSCRIPTION'];
        }

        //Iterate over subscriptions
        for($i = 0; $i < sizeof($subscriptions); $i++) {
            $sus_info = $subscriptions[$i];
            //traverse_xmlize($sus_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($sus_info['#']['ID']['0']['#']);
            $olduserid = backup_todb($sus_info['#']['USERID']['0']['#']);

            //Now, build the EXTENDEDFORUM_SUBSCRIPTIONS record structure
            $subscription->extendedforum = $extendedforum_id;
            $subscription->userid = backup_todb($sus_info['#']['USERID']['0']['#']);

            //We have to recode the userid field
            $user = backup_getid($restore->backup_unique_code,"user",$subscription->userid);
            if ($user) {
                $subscription->userid = $user->new_id;
            }

            //The structure is equal to the db, so insert the extendedforum_subscription
            $newid = insert_record ("extendedforum_subscriptions",$subscription);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"extendedforum_subscriptions",$oldid,
                             $newid);
            } else {
                $status = false;
            }
        }

        return $status;
    }

    //This function restores the extendedforum_discussions
    function extendedforum_discussions_restore_mods($extendedforum_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the discussions array
        $discussions = array();
        
        if (!empty($info['MOD']['#']['DISCUSSIONS']['0']['#']['DISCUSSION'])) {
            $discussions = $info['MOD']['#']['DISCUSSIONS']['0']['#']['DISCUSSION'];
        }

        //Iterate over discussions
        for($i = 0; $i < sizeof($discussions); $i++) {
            $dis_info = $discussions[$i];
            //traverse_xmlize($dis_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($dis_info['#']['ID']['0']['#']);
            $olduserid = backup_todb($dis_info['#']['USERID']['0']['#']);

            //Now, build the EXTENDEDFORUM_DISCUSSIONS record structure
            $discussion = new object();
            $discussion->extendedforum = $extendedforum_id;
            $discussion->course = $restore->course_id;
            $discussion->name = backup_todb($dis_info['#']['NAME']['0']['#']);
            $discussion->firstpost = backup_todb($dis_info['#']['FIRSTPOST']['0']['#']);
            $discussion->userid = backup_todb($dis_info['#']['USERID']['0']['#']);
            $discussion->groupid = backup_todb($dis_info['#']['GROUPID']['0']['#']);
            $discussion->assessed = backup_todb($dis_info['#']['ASSESSED']['0']['#']);
            $discussion->timemodified = backup_todb($dis_info['#']['TIMEMODIFIED']['0']['#']);
            $discussion->timemodified += $restore->course_startdateoffset;
            $discussion->usermodified = backup_todb($dis_info['#']['USERMODIFIED']['0']['#']);  
            $discussion->timestart = backup_todb($dis_info['#']['TIMESTART']['0']['#']);
            $discussion->timestart += $restore->course_startdateoffset;
            $discussion->timeend = backup_todb($dis_info['#']['TIMEEND']['0']['#']);
            $discussion->timeend += $restore->course_startdateoffset;
            //We have to recode the userid field
            $user = backup_getid($restore->backup_unique_code,"user",$discussion->userid);
            if ($user) {
                $discussion->userid = $user->new_id;
            }

            //We have to recode the groupid field
            $group = restore_group_getid($restore, $discussion->groupid);
            if ($group) {
                $discussion->groupid = $group->new_id;
            }

            //We have to recode the usermodified field
            $user = backup_getid($restore->backup_unique_code,"user",$discussion->usermodified);
            if ($user) {
                $discussion->usermodified = $user->new_id;
            }

            //The structure is equal to the db, so insert the extendedforum_discussions
            $newid = insert_record ("extendedforum_discussions",$discussion);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"extendedforum_discussions",$oldid,
                             $newid);
                //Restore extendedforum_posts
                $status = extendedforum_posts_restore_mods ($extendedforum_id,$newid,$dis_info,$restore);
                //Now recalculate firstpost field
                $old_firstpost = $discussion->firstpost;
                //Get its new post_id from backup_ids table
                $rec = backup_getid($restore->backup_unique_code,"extendedforum_posts",$old_firstpost);
                if ($rec) {
                    //Put its new firstpost
                    $discussion->firstpost = $rec->new_id;
                    if ($post = get_record("extendedforum_posts", "id", $discussion->firstpost)) {
                        $discussion->userid = $post->userid;
                    }
                } else {
                     $discussion->firstpost = 0;
                     $discussion->userid = 0;
                }
                //Create temp discussion record
                $temp_discussion->id = $newid;
                $temp_discussion->firstpost = $discussion->firstpost;
                $temp_discussion->userid = $discussion->userid;
                //Update discussion (only firstpost and userid will be changed)
                $status = update_record("extendedforum_discussions",$temp_discussion);
                //echo "Updated firstpost ".$old_firstpost." to ".$temp_discussion->firstpost."<br />";                //Debug
            } else {
                $status = false;
            }
        }

        return $status;
    }

    //This function restores the extendedforum_read
    function extendedforum_read_restore_mods($extendedforum_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the read array
        $readposts = array();
        if (isset($info['MOD']['#']['READPOSTS']['0']['#']['READ'])) {
            $readposts = $info['MOD']['#']['READPOSTS']['0']['#']['READ'];
        }

        //Iterate over readposts
        for($i = 0; $i < sizeof($readposts); $i++) {
            $rea_info = $readposts[$i];
            //traverse_xmlize($rea_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($rea_info['#']['ID']['0']['#']);

            //Now, build the EXTENDEDFORUM_READ record structure
            $read->extendedforumid = $extendedforum_id;
            $read->userid = backup_todb($rea_info['#']['USERID']['0']['#']);
            $read->discussionid = backup_todb($rea_info['#']['DISCUSSIONID']['0']['#']);
            $read->postid = backup_todb($rea_info['#']['POSTID']['0']['#']);
            $read->firstread = backup_todb($rea_info['#']['FIRSTREAD']['0']['#']);
            $read->lastread = backup_todb($rea_info['#']['LASTREAD']['0']['#']);

            //We have to recode the userid field
            $user = backup_getid($restore->backup_unique_code,"user",$read->userid);
            if ($user) {
                $read->userid = $user->new_id;
            } else {
                continue; // Skip this extendedforum_read record
            }

            //We have to recode the discussionid field
            $discussion = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$read->discussionid);
            if ($discussion) {
                $read->discussionid = $discussion->new_id;
            } else {
                continue; // Skip this extendedforum_read record
            }

            //We have to recode the postid field
            $post = backup_getid($restore->backup_unique_code,"extendedforum_posts",$read->postid);
            if ($post) {
                $read->postid = $post->new_id;
            } else {
                continue; // Skip this extendedforum_read record
            }

            // Arrived here, the structure is equal to the db, so insert the extendedforum_read
            $newid = 0;
            $newid = insert_record ("extendedforum_read",$read);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"extendedforum_read",$oldid,
                             $newid);
            } else {
                $status = false;
            }
        }

        return $status;
    }

    //This function restores the extendedforum_posts
    function extendedforum_posts_restore_mods($new_extendedforum_id,$discussion_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the posts array
        $posts = $info['#']['POSTS']['0']['#']['POST'];

        //Iterate over posts
        for($i = 0; $i < sizeof($posts); $i++) {
            $pos_info = $posts[$i];
            //traverse_xmlize($pos_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($pos_info['#']['ID']['0']['#']);
            $olduserid = backup_todb($pos_info['#']['USERID']['0']['#']);

            //Now, build the EXTENDEDFORUM_POSTS record structure
            $post->discussion = $discussion_id;
            $post->parent = backup_todb($pos_info['#']['PARENT']['0']['#']);
            $post->userid = backup_todb($pos_info['#']['USERID']['0']['#']);   
            $post->created = backup_todb($pos_info['#']['CREATED']['0']['#']);
            $post->created += $restore->course_startdateoffset;
            $post->modified = backup_todb($pos_info['#']['MODIFIED']['0']['#']);
            $post->modified += $restore->course_startdateoffset;             
            $post->mailed = backup_todb($pos_info['#']['MAILED']['0']['#']);
            $post->subject = backup_todb($pos_info['#']['SUBJECT']['0']['#']);
            $post->message = backup_todb($pos_info['#']['MESSAGE']['0']['#']);
            $post->format = backup_todb($pos_info['#']['FORMAT']['0']['#']);
            $post->attachment = backup_todb($pos_info['#']['ATTACHMENT']['0']['#']);
            $post->totalscore = backup_todb($pos_info['#']['TOTALSCORE']['0']['#']);
            $post->mailnow = backup_todb($pos_info['#']['MAILNOW']['0']['#']);

            //We have to recode the userid field
            $user = backup_getid($restore->backup_unique_code,"user",$post->userid);
            if ($user) {
                $post->userid = $user->new_id;
            }

            //The structure is equal to the db, so insert the extendedforum_posts
            $newid = insert_record ("extendedforum_posts",$post);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"extendedforum_posts",$oldid,
                             $newid);

                //Get old extendedforum id from backup_ids
                $rec = get_record("backup_ids","backup_code",$restore->backup_unique_code,
                                               "table_name","extendedforum",
                                               "new_id",$new_extendedforum_id);
                //Now copy moddata associated files
                $status = extendedforum_restore_files ($rec->old_id, $new_extendedforum_id,
                                                    $oldid, $newid, $restore);

                //Now restore post ratings
                $status = extendedforum_ratings_restore_mods($newid,$pos_info,$restore);

            } else {
                $status = false;
            }
        }

        //Now we get every post in this discussion_id and recalculate its parent post
        $posts = get_records ("extendedforum_posts","discussion",$discussion_id);
        if ($posts) {
            //Iterate over each post
            foreach ($posts as $post) {
                //Get its parent
                $old_parent = $post->parent;
                //Get its new post_id from backup_ids table
                $rec = backup_getid($restore->backup_unique_code,"extendedforum_posts",$old_parent);
                if ($rec) {
                    //Put its new parent
                    $post->parent = $rec->new_id;
                } else {
                     $post->parent = 0;
                }
                //Create temp post record
                $temp_post->id = $post->id;
                $temp_post->parent = $post->parent;
                //echo "Updated parent ".$old_parent." to ".$temp_post->parent."<br />";                //Debug
                //Update post (only parent will be changed)
                $status = update_record("extendedforum_posts",$temp_post);
            }
        }

        return $status;
    }

    //This function copies the extendedforum related info from backup temp dir to course moddata folder,
    //creating it if needed and recoding everything (extendedforum id and post id)
    function extendedforum_restore_files ($oldforid, $newforid, $oldpostid, $newpostid, $restore) {

        global $CFG;

        $status = true;
        $todo = false;
        $moddata_path = "";
        $extendedforum_path = "";
        $temp_path = "";

        //First, we check to "course_id" exists and create is as necessary
        //in CFG->dataroot
        $dest_dir = $CFG->dataroot."/".$restore->course_id;
        $status = check_dir_exists($dest_dir,true);

        //First, locate course's moddata directory
        $moddata_path = $CFG->dataroot."/".$restore->course_id."/".$CFG->moddata;

        //Check it exists and create it
        $status = check_dir_exists($moddata_path,true);

        //Now, locate extendedforum directory
        if ($status) {
            $extendedforum_path = $moddata_path."/extendedforum";
            //Check it exists and create it
            $status = check_dir_exists($extendedforum_path,true);
        }

        //Now locate the temp dir we are restoring from
        if ($status) {
            $temp_path = $CFG->dataroot."/temp/backup/".$restore->backup_unique_code.
                         "/moddata/extendedforum/".$oldforid."/".$oldpostid;
            //Check it exists
            if (is_dir($temp_path)) {
                $todo = true;
            }
        }

        //If todo, we create the neccesary dirs in course moddata/extendedforum
        if ($status and $todo) {
            //First this extendedforum id
            $this_extendedforum_path = $extendedforum_path."/".$newforid;
            $status = check_dir_exists($this_extendedforum_path,true);
            //Now this post id
            $post_extendedforum_path = $this_extendedforum_path."/".$newpostid;
            //And now, copy temp_path to post_extendedforum_path
            $status = backup_copy_file($temp_path, $post_extendedforum_path);
        }

        return $status;
    }

    //This function restores the extendedforum_ratings
    function extendedforum_ratings_restore_mods($new_post_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the ratings array
        $ratings = array();
        if (isset($info['#']['RATINGS']['0']['#']['RATING'])) {
            $ratings = $info['#']['RATINGS']['0']['#']['RATING'];
        }

        //Iterate over ratings
        for($i = 0; $i < sizeof($ratings); $i++) {
            $rat_info = $ratings[$i];
            //traverse_xmlize($rat_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($rat_info['#']['ID']['0']['#']);
            $olduserid = backup_todb($rat_info['#']['USERID']['0']['#']);

            //Now, build the FORM_RATINGS record structure
            $rating->post = $new_post_id;
            $rating->userid = backup_todb($rat_info['#']['USERID']['0']['#']);
            $rating->time = backup_todb($rat_info['#']['TIME']['0']['#']);
            $rating->rating = backup_todb($rat_info['#']['POST_RATING']['0']['#']);

            //We have to recode the userid field
            $user = backup_getid($restore->backup_unique_code,"user",$rating->userid);
            if ($user) {
                $rating->userid = $user->new_id;
            }

            //The structure is equal to the db, so insert the extendedforum_ratings
            $newid = insert_record ("extendedforum_ratings",$rating);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"extendedforum_ratings",$oldid,
                             $newid);
            } else {
                $status = false;
            }
        }

        return $status;
    }

    //This function converts texts in FORMAT_WIKI to FORMAT_MARKDOWN for
    //some texts in the module
    function extendedforum_restore_wiki2markdown ($restore) {
    
        global $CFG;

        $status = true;

        //Convert extendedforum_posts->message
        if ($records = get_records_sql ("SELECT p.id, p.message, p.format
                                         FROM {$CFG->prefix}extendedforum_posts p,
                                              {$CFG->prefix}extendedforum_discussions d,
                                              {$CFG->prefix}extendedforum f,
                                              {$CFG->prefix}backup_ids b
                                         WHERE d.id = p.discussion AND
                                               f.id = d.extendedforum AND
                                               f.course = $restore->course_id AND
                                               p.format = ".FORMAT_WIKI. " AND
                                               b.backup_code = $restore->backup_unique_code AND
                                               b.table_name = 'extendedforum_posts' AND
                                               b.new_id = p.id")) {
            foreach ($records as $record) {
                //Rebuild wiki links
                $record->message = restore_decode_wiki_content($record->message, $restore);
                //Convert to Markdown
                $wtm = new WikiToMarkdown();
                $record->message = $wtm->convert($record->message, $restore->course_id);
                $record->format = FORMAT_MARKDOWN;
                $status = update_record('extendedforum_posts', addslashes_object($record));
                //Do some output
                $i++;
                if (($i+1) % 1 == 0) {
                    if (!defined('RESTORE_SILENTLY')) {
                        echo ".";
                        if (($i+1) % 20 == 0) {
                            echo "<br />";
                        }
                    }
                    backup_flush(300);
                }
            }

        }
        return $status;
    }

    //This function returns a log record with all the necessay transformations
    //done. It's used by restore_log_module() to restore modules log.
    function extendedforum_restore_logs($restore,$log) {

        $status = false;

        //Depending of the action, we recode different things
        switch ($log->action) {
        case "add":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "mark read":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the url and info fields)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?f=".$mod->new_id;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "start tracking":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the url and info fields)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?f=".$mod->new_id;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "stop tracking":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the url and info fields)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?f=".$mod->new_id;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "update":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view extendedforum":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view extendedforums":
            $log->url = "index.php?id=".$log->course;
            $status = true;
            break;
        case "subscribeall":
            $log->url = "index.php?id=".$log->course;
            $status = true;
            break;
        case "unsubscribeall":
            $log->url = "index.php?id=".$log->course;
            $status = true;
            break;
        case "subscribe":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info and url field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?f=".$mod->new_id;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view subscriber":
        case "view subscribers":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info and field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "subscribers.php?id=".$mod->new_id;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "unsubscribe":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info and url field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?f=".$mod->new_id;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "add discussion":
            if ($log->cmid) {
                //Get the new_id of the discussion (to recode the info and url field)
                $dis = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$log->info);
                if ($dis) {
                    $log->url = "discuss.php?d=".$dis->new_id;
                    $log->info = $dis->new_id;
                    $status = true;
                }
            }
            break;
        case "view discussion":
            if ($log->cmid) {
                //Get the new_id of the discussion (to recode the info and url field)
                $dis = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$log->info);
                if ($dis) {
                    $log->url = "discuss.php?d=".$dis->new_id;
                    $log->info = $dis->new_id;
                    $status = true;
                }
            }
            break;
        case "move discussion":
            if ($log->cmid) {
                //Get the new_id of the discussion (to recode the info and url field)
                $dis = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$log->info);
                if ($dis) {
                    $log->url = "discuss.php?d=".$dis->new_id;
                    $log->info = $dis->new_id;
                    $status = true;
                }
            }
            break;
        case "delete discussi":
        case "delete discussion":
            if ($log->cmid) {
                //Get the new_id of the module (to recode the info field)
                $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "add post":
            if ($log->cmid) {
                //Get the new_id of the post (to recode the url and info field)
                $pos = backup_getid($restore->backup_unique_code,"extendedforum_posts",$log->info);
                if ($pos) {
                    //Get the post record from database
                    $dbpos = get_record("extendedforum_posts","id","$pos->new_id");
                    if ($dbpos) {
                        $log->url = "discuss.php?d=".$dbpos->discussion."&parent=".$pos->new_id;
                        $log->info = $pos->new_id;
                        $status = true;
                    }
                }
            }
            break;
        case "prune post":
            if ($log->cmid) {
                //Get the new_id of the post (to recode the url and info field)
                $pos = backup_getid($restore->backup_unique_code,"extendedforum_posts",$log->info);
                if ($pos) {
                    //Get the post record from database
                    $dbpos = get_record("extendedforum_posts","id","$pos->new_id");
                    if ($dbpos) {
                        $log->url = "discuss.php?d=".$dbpos->discussion;
                        $log->info = $pos->new_id;
                        $status = true;
                    }
                }
            }
            break;
        case "update post":
            if ($log->cmid) {
                //Get the new_id of the post (to recode the url and info field)
                $pos = backup_getid($restore->backup_unique_code,"extendedforum_posts",$log->info);
                if ($pos) {
                    //Get the post record from database
                    $dbpos = get_record("extendedforum_posts","id","$pos->new_id");
                    if ($dbpos) {
                        $log->url = "discuss.php?d=".$dbpos->discussion."&parent=".$pos->new_id;
                        $log->info = $pos->new_id;
                        $status = true;
                    }
                }
            }
            break;
        case "delete post":
            if ($log->cmid) {
                //Extract the discussion id from the url field
                $disid = substr(strrchr($log->url,"="),1);
                //Get the new_id of the discussion (to recode the url field)
                $dis = backup_getid($restore->backup_unique_code,"quiz_discussions",$disid);
                if ($dis) {
                    $log->url = "discuss.php?d=".$dis->new_id;
                    $status = true;
                }
            }
            break;
        case "user report":
            //Extract mode from url
            $mode = substr(strrchr($log->url,"="),1);
            //Get new user id
            if ($use = backup_getid($restore->backup_unique_code, 'user', $log->info)) {
                $log->url = 'user.php?course=' . $log->course . '&id=' . $use->new_id . '&mode=' . $mode;
                $log->info = $use->new_id;
                $status = true;
            }
            break;
        case "search":
            $log->url = "search.php?id=".$log->course."&search=".urlencode($log->info);
            $status = true;
            break;
        default:
            if (!defined('RESTORE_SILENTLY')) {
                echo "action (".$log->module."-".$log->action.") unknown. Not restored<br />";                 //Debug
            }
            break;
        }

        if ($status) {
            $status = $log;
        }
        return $status;
    }

    //Return a content decoded to support interactivities linking. Every module
    //should have its own. They are called automatically from
    //extendedforum_decode_content_links_caller() function in each module
    //in the restore process
    function extendedforum_decode_content_links ($content,$restore) {

        global $CFG;

        $result = $content;

        //Link to the list of extendedforums

        $searchstring='/\$@(EXTENDEDFORUMINDEX)\*([0-9]+)@\$/';
        //We look for it
        preg_match_all($searchstring,$content,$foundset);
        //If found, then we are going to look for its new id (in backup tables)
        if ($foundset[0]) {
            //print_object($foundset);                                     //Debug
            //Iterate over foundset[2]. They are the old_ids
            foreach($foundset[2] as $old_id) {
                //We get the needed variables here (course id)
                $rec = backup_getid($restore->backup_unique_code,"course",$old_id);
                //Personalize the searchstring
                $searchstring='/\$@(EXTENDEDFORUMINDEX)\*('.$old_id.')@\$/';
                //If it is a link to this course, update the link to its new location
                if($rec->new_id) {
                    //Now replace it
                    $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/extendedforum/index.php?id='.$rec->new_id,$result);
                } else {
                    //It's a foreign link so leave it as original
                    $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/extendedforum/index.php?id='.$old_id,$result);
                }
            }
        }

        //Link to extendedforum view by moduleid

        $searchstring='/\$@(EXTENDEDFORUMVIEWBYID)\*([0-9]+)@\$/';
        //We look for it
        preg_match_all($searchstring,$result,$foundset);
        //If found, then we are going to look for its new id (in backup tables)
        if ($foundset[0]) {
            //print_object($foundset);                                     //Debug
            //Iterate over foundset[2]. They are the old_ids
            foreach($foundset[2] as $old_id) {
                //We get the needed variables here (course_modules id)
                $rec = backup_getid($restore->backup_unique_code,"course_modules",$old_id);
                //Personalize the searchstring
                $searchstring='/\$@(EXTENDEDFORUMVIEWBYID)\*('.$old_id.')@\$/';
                //If it is a link to this course, update the link to its new location
                if($rec->new_id) {
                    //Now replace it
                    $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/extendedforum/view.php?id='.$rec->new_id,$result);
                } else {
                    //It's a foreign link so leave it as original
                    $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/extendedforum/view.php?id='.$old_id,$result);
                }
            }
        }

        //Link to extendedforum view by extendedforumid

        $searchstring='/\$@(EXTENDEDFORUMVIEWBYF)\*([0-9]+)@\$/';
        //We look for it
        preg_match_all($searchstring,$result,$foundset);
        //If found, then we are going to look for its new id (in backup tables)
        if ($foundset[0]) {
            //print_object($foundset);                                     //Debug
            //Iterate over foundset[2]. They are the old_ids
            foreach($foundset[2] as $old_id) {
                //We get the needed variables here (extendedforum id)
                $rec = backup_getid($restore->backup_unique_code,"extendedforum",$old_id);
                //Personalize the searchstring
                $searchstring='/\$@(EXTENDEDFORUMVIEWBYF)\*('.$old_id.')@\$/';
                //If it is a link to this course, update the link to its new location
                if($rec->new_id) {
                    //Now replace it
                    $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/extendedforum/view.php?f='.$rec->new_id,$result);
                } else {
                    //It's a foreign link so leave it as original
                    $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/extendedforum/view.php?f='.$old_id,$result);
                }
            }
        }

        //Link to extendedforum discussion by discussionid

        $searchstring='/\$@(EXTENDEDFORUMDISCUSSIONVIEW)\*([0-9]+)@\$/';
        //We look for it
        preg_match_all($searchstring,$result,$foundset);
        //If found, then we are going to look for its new id (in backup tables)
        if ($foundset[0]) {
            //print_object($foundset);                                     //Debug
            //Iterate over foundset[2]. They are the old_ids
            foreach($foundset[2] as $old_id) {
                //We get the needed variables here (discussion id)
                $rec = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$old_id);
                //Personalize the searchstring
                $searchstring='/\$@(EXTENDEDFORUMDISCUSSIONVIEW)\*('.$old_id.')@\$/';
                //If it is a link to this course, update the link to its new location
                if($rec->new_id) {
                    //Now replace it
                    $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$rec->new_id,$result);
                } else {
                    //It's a foreign link so leave it as original
                    $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/extendedforum/discuss.php?d='.$old_id,$result);
                }
            }
        }

        //Link to extendedforum discussion with parent syntax

        $searchstring='/\$@(EXTENDEDFORUMDISCUSSIONVIEWPARENT)\*([0-9]+)\*([0-9]+)@\$/';
        //We look for it
        preg_match_all($searchstring,$result,$foundset);
        //If found, then we are going to look for its new id (in backup tables)
        if ($foundset[0]) {
            //print_object($foundset);                                     //Debug
            //Iterate over foundset[2] and foundset[3]. They are the old_ids
            foreach($foundset[2] as $key => $old_id) {
                $old_id2 = $foundset[3][$key];
                //We get the needed variables here (discussion id and post id)
                $rec = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$old_id);
                $rec2 = backup_getid($restore->backup_unique_code,"extendedforum_posts",$old_id2);
                //Personalize the searchstring
                $searchstring='/\$@(EXTENDEDFORUMDISCUSSIONVIEWPARENT)\*('.$old_id.')\*('.$old_id2.')@\$/';
                //If it is a link to this course, update the link to its new location
                if($rec->new_id && $rec2->new_id) {
                    //Now replace it
                    $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$rec->new_id.'&parent='.$rec2->new_id,$result);
                } else {
                    //It's a foreign link so leave it as original
                    $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/extendedforum/discuss.php?d='.$old_id.'&parent='.$old_id2,$result);
                }
            }
        }

        //Link to extendedforum discussion with relative syntax

        $searchstring='/\$@(EXTENDEDFORUMDISCUSSIONVIEWINSIDE)\*([0-9]+)\*([0-9]+)@\$/';
        //We look for it
        preg_match_all($searchstring,$result,$foundset);
        //If found, then we are going to look for its new id (in backup tables)
        if ($foundset[0]) {
            //print_object($foundset);                                     //Debug
            //Iterate over foundset[2] and foundset[3]. They are the old_ids
            foreach($foundset[2] as $key => $old_id) {
                $old_id2 = $foundset[3][$key];
                //We get the needed variables here (discussion id and post id)
                $rec = backup_getid($restore->backup_unique_code,"extendedforum_discussions",$old_id);
                $rec2 = backup_getid($restore->backup_unique_code,"extendedforum_posts",$old_id2);
                //Personalize the searchstring
                $searchstring='/\$@(EXTENDEDFORUMDISCUSSIONVIEWINSIDE)\*('.$old_id.')\*('.$old_id2.')@\$/';
                //If it is a link to this course, update the link to its new location
                if($rec->new_id && $rec2->new_id) {
                    //Now replace it
                    $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$rec->new_id.'#'.$rec2->new_id,$result);
                } else {
                    //It's a foreign link so leave it as original
                    $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/extendedforum/discuss.php?d='.$old_id.'#'.$old_id2,$result);
                }
            }
        }

        return $result;
    }

    //This function makes all the necessary calls to xxxx_decode_content_links()
    //function in each module, passing them the desired contents to be decoded
    //from backup format to destination site/course in order to mantain inter-activities
    //working in the backup/restore process. It's called from restore_decode_content_links()
    //function in restore process
    function extendedforum_decode_content_links_caller($restore) {
        global $CFG;
        $status = true;
        
        //Process every POST (message) in the course
        if ($posts = get_records_sql ("SELECT p.id, p.message
                                   FROM {$CFG->prefix}extendedforum_posts p,
                                        {$CFG->prefix}extendedforum_discussions d
                                   WHERE d.course = $restore->course_id AND
                                         p.discussion = d.id")) {
            //Iterate over each post->message
            $i = 0;   //Counter to send some output to the browser to avoid timeouts
            foreach ($posts as $post) {
                //Increment counter
                $i++;
                $content = $post->message;
                $result = restore_decode_content_links_worker($content,$restore);
                if ($result != $content) {
                    //Update record
                    $post->message = addslashes($result);
                    $status = update_record("extendedforum_posts",$post);
                    if (debugging()) {
                        if (!defined('RESTORE_SILENTLY')) {
                            echo '<br /><hr />'.s($content).'<br />changed to<br />'.s($result).'<hr /><br />';
                        }
                    }
                }
                //Do some output
                if (($i+1) % 5 == 0) {
                    if (!defined('RESTORE_SILENTLY')) {
                        echo ".";
                        if (($i+1) % 100 == 0) {
                            echo "<br />";
                        }
                    }
                    backup_flush(300);
                }
            }
        }

        //Process every EXTENDEDFORUM (intro) in the course
        if ($extendedforums = get_records_sql ("SELECT f.id, f.intro
                                   FROM {$CFG->prefix}extendedforum f
                                   WHERE f.course = $restore->course_id")) {
            //Iterate over each extendedforum->intro
            $i = 0;   //Counter to send some output to the browser to avoid timeouts
            foreach ($extendedforums as $extendedforum) {
                //Increment counter
                $i++;
                $content = $extendedforum->intro;
                $result = restore_decode_content_links_worker($content,$restore);
                if ($result != $content) {
                    //Update record
                    $extendedforum->intro = addslashes($result);
                    $status = update_record("extendedforum",$extendedforum);
                    if (debugging()) {
                        if (!defined('RESTORE_SILENTLY')) {
                            echo '<br /><hr />'.s($content).'<br />changed to<br />'.s($result).'<hr /><br />';
                        }
                    }
                }
                //Do some output
                if (($i+1) % 5 == 0) {
                    if (!defined('RESTORE_SILENTLY')) {
                        echo ".";
                        if (($i+1) % 100 == 0) {
                            echo "<br />";
                        }
                    }
                    backup_flush(300);
                    }
            }
        }

        return $status;
    }
?>
