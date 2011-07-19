<?php //$Id: backuplib.php,v 1.21 2006/08/25 02:41:16 vyshane Exp $
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

    function extendedforum_backup_mods($bf,$preferences) {
       
        global $CFG;

        $status = true;

        //Iterate over extendedforum table
        $extendedforums = get_records ("extendedforum","course",$preferences->backup_course,"id");
        if ($extendedforums) {
            foreach ($extendedforums as $extendedforum) {
                if (backup_mod_selected($preferences,'extendedforum',$extendedforum->id)) {
                    $status = extendedforum_backup_one_mod($bf,$preferences,$extendedforum);
                    // backup files happens in backup_one_mod now too.
                }
            }
        }
        return $status;
    }


    function extendedforum_backup_one_mod($bf,$preferences,$extendedforum) {
    
        global $CFG;
        
        if (is_numeric($extendedforum)) {
            $extendedforum = get_record('extendedforum','id',$extendedforum);
        }
        $instanceid = $extendedforum->id;
        
        $status = true;
        
        //Start mod
        fwrite ($bf,start_tag("MOD",3,true));
        
        //Print extendedforum data
        fwrite ($bf,full_tag("ID",4,false,$extendedforum->id));
        fwrite ($bf,full_tag("MODTYPE",4,false,"extendedforum"));
        fwrite ($bf,full_tag("TYPE",4,false,$extendedforum->type));
        fwrite ($bf,full_tag("NAME",4,false,$extendedforum->name));
        fwrite ($bf,full_tag("INTRO",4,false,$extendedforum->intro));
        fwrite ($bf,full_tag("ASSESSED",4,false,$extendedforum->assessed));
        fwrite ($bf,full_tag("ASSESSTIMESTART",4,false,$extendedforum->assesstimestart));
        fwrite ($bf,full_tag("ASSESSTIMEFINISH",4,false,$extendedforum->assesstimefinish));
        fwrite ($bf,full_tag("MAXBYTES",4,false,$extendedforum->maxbytes));
        fwrite ($bf,full_tag("SCALE",4,false,$extendedforum->scale));
        fwrite ($bf,full_tag("FORCESUBSCRIBE",4,false,$extendedforum->forcesubscribe));
        fwrite ($bf,full_tag("TRACKINGTYPE",4,false,$extendedforum->trackingtype));
        fwrite ($bf,full_tag("RSSTYPE",4,false,$extendedforum->rsstype));
        fwrite ($bf,full_tag("RSSARTICLES",4,false,$extendedforum->rssarticles));
        fwrite ($bf,full_tag("TIMEMODIFIED",4,false,$extendedforum->timemodified));
        fwrite ($bf,full_tag("WARNAFTER",4,false,$extendedforum->warnafter));
        fwrite ($bf,full_tag("BLOCKAFTER",4,false,$extendedforum->blockafter));
        fwrite ($bf,full_tag("BLOCKPERIOD",4,false,$extendedforum->blockperiod));
        
        //if we've selected to backup users info, then execute backup_extendedforum_suscriptions and
        //backup_extendedforum_discussions
        if (backup_userdata_selected($preferences,'extendedforum',$extendedforum->id)) {
            $status = backup_extendedforum_subscriptions($bf,$preferences,$extendedforum->id);
            if ($status) {
                $status = backup_extendedforum_discussions($bf,$preferences,$extendedforum->id);
            }
            if ($status) {
                $status = backup_extendedforum_read($bf,$preferences,$extendedforum->id);
            }
            if ($status) {
                $status = backup_extendedforum_files_instance($bf,$preferences,$extendedforum->id);
            }
        }
        //End mod
        $status =fwrite ($bf,end_tag("MOD",3,true));
        return $status;
    }


    function extendedforum_check_backup_mods_instances($instance,$backup_unique_code) {
        $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
        $info[$instance->id.'0'][1] = '';
        if (!empty($instance->userdata)) {
            $info[$instance->id.'1'][0] = get_string("subscriptions","extendedforum");
            if ($ids = extendedforum_subscription_ids_by_instance ($instance->id)) {
                $info[$instance->id.'1'][1] = count($ids);
            } else {
                $info[$instance->id.'1'][1] = 0;
            }
            //Discussions
            $info[$instance->id.'2'][0] = get_string("discussions","extendedforum");
            if ($ids = extendedforum_discussion_ids_by_instance ($instance->id)) {
                $info[$instance->id.'2'][1] = count($ids);
            } else {
                $info[$instance->id.'2'][1] = 0;
            }
            //Posts
            $info[$instance->id.'3'][0] = get_string("posts","extendedforum");
            if ($ids = extendedforum_post_ids_by_instance ($instance->id)) {
                $info[$instance->id.'3'][1] = count($ids);
            } else {
                $info[$instance->id.'3'][1] = 0;
            }
            //Ratings
            $info[$instance->id.'4'][0] = get_string("ratings","extendedforum");
            if ($ids = extendedforum_rating_ids_by_instance ($instance->id)) {
                $info[$instance->id.'4'][1] = count($ids);
            } else {
                $info[$instance->id.'4'][1] = 0;
            }
        }
        return $info;
    }

    //Backup extendedforum_subscriptions contents (executed from extendedforum_backup_mods)     
    function backup_extendedforum_subscriptions ($bf,$preferences,$extendedforum) {     

        global $CFG;

        $status = true;

        $extendedforum_subscriptions = get_records("extendedforum_subscriptions","extendedforum",$extendedforum,"id");
        //If there is subscriptions
        if ($extendedforum_subscriptions) {
            //Write start tag
            $status =fwrite ($bf,start_tag("SUBSCRIPTIONS",4,true));
            //Iterate over each answer
            foreach ($extendedforum_subscriptions as $for_sus) {
                //Start suscription
                $status =fwrite ($bf,start_tag("SUBSCRIPTION",5,true));
                //Print extendedforum_subscriptions contents
                fwrite ($bf,full_tag("ID",6,false,$for_sus->id));
                fwrite ($bf,full_tag("USERID",6,false,$for_sus->userid));
                //End subscription
                $status =fwrite ($bf,end_tag("SUBSCRIPTION",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("SUBSCRIPTIONS",4,true));
        }
        return $status;
    }

    //Backup extendedforum_discussions contents (executed from extendedforum_backup_mods)
    function backup_extendedforum_discussions ($bf,$preferences,$extendedforum) {

        global $CFG;

        $status = true;

        $extendedforum_discussions = get_records("extendedforum_discussions","extendedforum",$extendedforum,"id");
        //If there are discussions
        if ($extendedforum_discussions) {
            //Write start tag
            $status =fwrite ($bf,start_tag("DISCUSSIONS",4,true));
            //Iterate over each discussion
            foreach ($extendedforum_discussions as $for_dis) {
                //Start discussion
                $status =fwrite ($bf,start_tag("DISCUSSION",5,true));
                //Print extendedforum_discussions contents
                fwrite ($bf,full_tag("ID",6,false,$for_dis->id));
                fwrite ($bf,full_tag("NAME",6,false,$for_dis->name));
                fwrite ($bf,full_tag("FIRSTPOST",6,false,$for_dis->firstpost));
                fwrite ($bf,full_tag("USERID",6,false,$for_dis->userid));
                fwrite ($bf,full_tag("GROUPID",6,false,$for_dis->groupid));
                fwrite ($bf,full_tag("ASSESSED",6,false,$for_dis->assessed));
                fwrite ($bf,full_tag("TIMEMODIFIED",6,false,$for_dis->timemodified));
                fwrite ($bf,full_tag("USERMODIFIED",6,false,$for_dis->usermodified));
                fwrite ($bf,full_tag("TIMESTART",6,false,$for_dis->timestart));
                fwrite ($bf,full_tag("TIMEEND",6,false,$for_dis->timeend));
                //Now print posts to xml
                $status = backup_extendedforum_posts($bf,$preferences,$for_dis->id);
                //End discussion
                $status =fwrite ($bf,end_tag("DISCUSSION",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("DISCUSSIONS",4,true));
        }
        return $status;
    }

    //Backup extendedforum_read contents (executed from extendedforum_backup_mods)     
    function backup_extendedforum_read ($bf,$preferences,$extendedforum) {     

        global $CFG;

        $status = true;

        $extendedforum_read = get_records("extendedforum_read","extendedforumid",$extendedforum,"id");
        //If there are read
        if ($extendedforum_read) {
            //Write start tag
            $status =fwrite ($bf,start_tag("READPOSTS",4,true));
            //Iterate over each read
            foreach ($extendedforum_read as $for_rea) {
                //Start read
                $status =fwrite ($bf,start_tag("READ",5,true));
                //Print extendedforum_read contents
                fwrite ($bf,full_tag("ID",6,false,$for_rea->id));
                fwrite ($bf,full_tag("USERID",6,false,$for_rea->userid));
                fwrite ($bf,full_tag("DISCUSSIONID",6,false,$for_rea->discussionid));
                fwrite ($bf,full_tag("POSTID",6,false,$for_rea->postid));
                fwrite ($bf,full_tag("FIRSTREAD",6,false,$for_rea->firstread));
                fwrite ($bf,full_tag("LASTREAD",6,false,$for_rea->lastread));
                //End read
                $status =fwrite ($bf,end_tag("READ",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("READPOSTS",4,true));
        }
        return $status;
    }

    //Backup extendedforum_posts contents (executed from backup_extendedforum_discussions)
    function backup_extendedforum_posts ($bf,$preferences,$discussion) {

        global $CFG;

        $status = true;

        $extendedforum_posts = get_records("extendedforum_posts","discussion",$discussion,"id");
        //If there are posts
        if ($extendedforum_posts) {
            //Write start tag
            $status =fwrite ($bf,start_tag("POSTS",6,true));
            //Iterate over each post
            foreach ($extendedforum_posts as $for_pos) {
                //Start post
                $status =fwrite ($bf,start_tag("POST",7,true));
                //Print extendedforum_posts contents
                fwrite ($bf,full_tag("ID",8,false,$for_pos->id));
                fwrite ($bf,full_tag("PARENT",8,false,$for_pos->parent));
                fwrite ($bf,full_tag("USERID",8,false,$for_pos->userid));
                fwrite ($bf,full_tag("CREATED",8,false,$for_pos->created));
                fwrite ($bf,full_tag("MODIFIED",8,false,$for_pos->modified));
                fwrite ($bf,full_tag("MAILED",8,false,$for_pos->mailed));
                fwrite ($bf,full_tag("SUBJECT",8,false,$for_pos->subject));
                fwrite ($bf,full_tag("MESSAGE",8,false,$for_pos->message));
                fwrite ($bf,full_tag("FORMAT",8,false,$for_pos->format));
                fwrite ($bf,full_tag("ATTACHMENT",8,false,$for_pos->attachment));
                fwrite ($bf,full_tag("TOTALSCORE",8,false,$for_pos->totalscore));
                fwrite ($bf,full_tag("MAILNOW",8,false,$for_pos->mailnow));
                //Now print ratings to xml
                $status = backup_extendedforum_ratings($bf,$preferences,$for_pos->id);

                //End discussion
                $status =fwrite ($bf,end_tag("POST",7,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("POSTS",6,true));
        }
        return $status;
    }


    //Backup extendedforum_ratings contents (executed from backup_extendedforum_posts)
    function backup_extendedforum_ratings ($bf,$preferences,$post) {

        global $CFG;

        $status = true;

        $extendedforum_ratings = get_records("extendedforum_ratings","post",$post,"id");
        //If there are ratings
        if ($extendedforum_ratings) {
            //Write start tag
            $status =fwrite ($bf,start_tag("RATINGS",8,true));
            //Iterate over each rating
            foreach ($extendedforum_ratings as $for_rat) {
                //Start rating
                $status =fwrite ($bf,start_tag("RATING",9,true));
                //Print extendedforum_rating contents
                fwrite ($bf,full_tag("ID",10,false,$for_rat->id));
                fwrite ($bf,full_tag("USERID",10,false,$for_rat->userid));
                fwrite ($bf,full_tag("TIME",10,false,$for_rat->time));
                fwrite ($bf,full_tag("POST_RATING",10,false,$for_rat->rating));
                //End rating
                $status =fwrite ($bf,end_tag("RATING",9,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("RATINGS",8,true));
        }
        return $status;
    }

    //Backup extendedforum files because we've selected to backup user info
    //and files are user info's level
    function backup_extendedforum_files($bf,$preferences) {
        global $CFG;

        $status = true;

        //First we check to moddata exists and create it as necessary
        //in temp/backup/$backup_code  dir
        $status = check_and_create_moddata_dir($preferences->backup_unique_code);
        //Now copy the extendedforum dir
        if ($status) {
            //Only if it exists !! Thanks to Daniel Miksik.
            if (is_dir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/extendedforum")) {
                $handle = opendir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/extendedforum");
                while (false!==($item = readdir($handle))) {
                    if ($item != '.' && $item != '..' && is_dir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/extendedforum/".$item)
                        && array_key_exists($item,$preferences->mods['extendedforum']->instances)
                        && !empty($preferences->mods['extendedforum']->instances[$item]->backup)) {
                        $status = backup_copy_file($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/extendedforum/".$item,
                                                   $CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/extendedforum/",$item);
                    }
                }
            }
        }

        return $status;

    }


    //Backup extendedforum files because we've selected to backup user info
    //and files are user info's level
    function backup_extendedforum_files_instance($bf,$preferences,$instanceid) {
        global $CFG;

        $status = true;

        //First we check to moddata exists and create it as necessary
        //in temp/backup/$backup_code  dir
        $status = check_and_create_moddata_dir($preferences->backup_unique_code);
        $status = check_dir_exists($CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/extendedforum/",true);
        //Now copy the extendedforum dir
        if ($status) {
            //Only if it exists !! Thanks to Daniel Miksik.
            if (is_dir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/extendedforum/".$instanceid)) {
                $status = backup_copy_file($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/extendedforum/".$instanceid,
                                           $CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/extendedforum/".$instanceid);
            }
        }

        return $status;

    }

   ////Return an array of info (name,value)
   function extendedforum_check_backup_mods($course,$user_data=false,$backup_unique_code,$instances=null) {
       
       if (!empty($instances) && is_array($instances) && count($instances)) {
           $info = array();
           foreach ($instances as $id => $instance) {
               $info += extendedforum_check_backup_mods_instances($instance,$backup_unique_code);
           }
           return $info;
       }
        //First the course data
        $info[0][0] = get_string("modulenameplural","extendedforum");
        if ($ids = extendedforum_ids ($course)) {
            $info[0][1] = count($ids);
        } else {
            $info[0][1] = 0;
        }

        //Now, if requested, the user_data
        if ($user_data) {
            //Subscriptions
            $info[1][0] = get_string("subscriptions","extendedforum");
            if ($ids = extendedforum_subscription_ids_by_course ($course)) {
                $info[1][1] = count($ids);
            } else {
                $info[1][1] = 0;
            }
            //Discussions
            $info[2][0] = get_string("discussions","extendedforum");
            if ($ids = extendedforum_discussion_ids_by_course ($course)) {
                $info[2][1] = count($ids);
            } else {
                $info[2][1] = 0;
            }
            //Posts
            $info[3][0] = get_string("posts","extendedforum");
            if ($ids = extendedforum_post_ids_by_course ($course)) {
                $info[3][1] = count($ids);
            } else {
                $info[3][1] = 0;
            }
            //Ratings
            $info[4][0] = get_string("ratings","extendedforum");
            if ($ids = extendedforum_rating_ids_by_course ($course)) {
                $info[4][1] = count($ids);
            } else {
                $info[4][1] = 0;
            }
        }
        return $info;
    }

    //Return a content encoded to support interactivities linking. Every module
    //should have its own. They are called automatically from the backup procedure.
    function extendedforum_encode_content_links ($content,$preferences) {

        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        //Link to the list of extendedforums
        $buscar="/(".$base."\/mod\/extendedforum\/index.php\?id\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@EXTENDEDFORUMINDEX*$2@$',$content);

        //Link to extendedforum view by moduleid
        $buscar="/(".$base."\/mod\/extendedforum\/view.php\?id\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@EXTENDEDFORUMVIEWBYID*$2@$',$result);

        //Link to extendedforum view by extendedforumid
        $buscar="/(".$base."\/mod\/extendedforum\/view.php\?f\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@EXTENDEDFORUMVIEWBYF*$2@$',$result);

        //Link to extendedforum discussion with parent syntax
        $buscar="/(".$base."\/mod\/extendedforum\/discuss.php\?d\=)([0-9]+)\&parent\=([0-9]+)/";
        $result= preg_replace($buscar,'$@EXTENDEDFORUMDISCUSSIONVIEWPARENT*$2*$3@$',$result);

        //Link to extendedforum discussion with relative syntax
        $buscar="/(".$base."\/mod\/extendedforum\/discuss.php\?d\=)([0-9]+)\#([0-9]+)/";
        $result= preg_replace($buscar,'$@EXTENDEDFORUMDISCUSSIONVIEWINSIDE*$2*$3@$',$result);

        //Link to extendedforum discussion by discussionid
        $buscar="/(".$base."\/mod\/extendedforum\/discuss.php\?d\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@EXTENDEDFORUMDISCUSSIONVIEW*$2@$',$result);

        return $result;
    }

    // INTERNAL FUNCTIONS. BASED IN THE MOD STRUCTURE

    //Returns an array of extendedforums id
    function extendedforum_ids ($course) {

        global $CFG;

        return get_records_sql ("SELECT a.id, a.course
                                 FROM {$CFG->prefix}extendedforum a
                                 WHERE a.course = '$course'");
    }

    //Returns an array of extendedforum subscriptions id
    function extendedforum_subscription_ids_by_course ($course) {

        global $CFG;

        return get_records_sql ("SELECT s.id , s.extendedforum
                                 FROM {$CFG->prefix}extendedforum_subscriptions s,
                                      {$CFG->prefix}extendedforum a
                                 WHERE a.course = '$course' AND
                                       s.extendedforum = a.id");
    }

    //Returns an array of extendedforum subscriptions id 
    function extendedforum_subscription_ids_by_instance($instanceid) {
 
        global $CFG;
        
        return get_records_sql ("SELECT s.id , s.extendedforum
                                 FROM {$CFG->prefix}extendedforum_subscriptions s
                                 WHERE s.extendedforum = $instanceid");
    }

    //Returns an array of extendedforum discussions id
    function extendedforum_discussion_ids_by_course ($course) {

        global $CFG;

        return get_records_sql ("SELECT s.id , s.extendedforum      
                                 FROM {$CFG->prefix}extendedforum_discussions s,    
                                      {$CFG->prefix}extendedforum a 
                                 WHERE a.course = '$course' AND
                                       s.extendedforum = a.id"); 
    }

    //Returns an array of extendedforum discussions id
    function extendedforum_discussion_ids_by_instance ($instanceid) {

        global $CFG;

        return get_records_sql ("SELECT s.id , s.extendedforum      
                                 FROM {$CFG->prefix}extendedforum_discussions s   
                                 WHERE s.extendedforum = $instanceid"); 
    }

    //Returns an array of extendedforum posts id
    function extendedforum_post_ids_by_course ($course) {

        global $CFG;

        return get_records_sql ("SELECT p.id , p.discussion, s.extendedforum
                                 FROM {$CFG->prefix}extendedforum_posts p,
                                      {$CFG->prefix}extendedforum_discussions s,
                                      {$CFG->prefix}extendedforum a
                                 WHERE a.course = '$course' AND
                                       s.extendedforum = a.id AND
                                       p.discussion = s.id");
    }

    //Returns an array of extendedforum posts id
    function extendedforum_post_ids_by_instance ($instanceid) {

        global $CFG;

        return get_records_sql ("SELECT p.id , p.discussion, s.extendedforum
                                 FROM {$CFG->prefix}extendedforum_posts p,
                                      {$CFG->prefix}extendedforum_discussions s
                                 WHERE s.extendedforum = $instanceid AND
                                       p.discussion = s.id");
    }

    //Returns an array of ratings posts id      
    function extendedforum_rating_ids_by_course ($course) {      

        global $CFG;

        return get_records_sql ("SELECT r.id, r.post, p.discussion, s.extendedforum
                                 FROM {$CFG->prefix}extendedforum_ratings r,
                                      {$CFG->prefix}extendedforum_posts p,
                                      {$CFG->prefix}extendedforum_discussions s,
                                      {$CFG->prefix}extendedforum a    
                                 WHERE a.course = '$course' AND
                                       s.extendedforum = a.id AND   
                                       p.discussion = s.id AND
                                       r.post = p.id");
    }

    //Returns an array of ratings posts id      
    function extendedforum_rating_ids_by_instance ($instanceid) {      

        global $CFG;

        return get_records_sql ("SELECT r.id, r.post, p.discussion, s.extendedforum
                                 FROM {$CFG->prefix}extendedforum_ratings r,
                                      {$CFG->prefix}extendedforum_posts p,
                                      {$CFG->prefix}extendedforum_discussions s
                                 WHERE s.extendedforum = $instanceid AND   
                                       p.discussion = s.id AND
                                       r.post = p.id");
    }
?>
