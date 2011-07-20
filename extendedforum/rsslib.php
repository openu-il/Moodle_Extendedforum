<?php  // $Id: rsslib.php,v 1.25.2.1 2008/07/10 09:48:46 scyrma Exp $
    //This file adds support to rss feeds generation

    //This function is the main entry point to extendedforum
    //rss feeds generation. Foreach site extendedforum with rss enabled
    //build one XML rss structure.
    function extendedforum_rss_feeds() {

        global $CFG;

        $status = true;

        //Check CFG->enablerssfeeds
        if (empty($CFG->enablerssfeeds)) {
            debugging('DISABLED (admin variables)');
        //Check CFG->extendedforum_enablerssfeeds
        } else if (empty($CFG->extendedforum_enablerssfeeds)) {
            debugging('DISABLED (module configuration)');
        //It's working so we start...
        } else {
            //Iterate over all extendedforums
            if ($extendedforums = get_records("extendedforum")) {
                foreach ($extendedforums as $extendedforum) {
                    if (!empty($extendedforum->rsstype) && !empty($extendedforum->rssarticles) && $status) {

                        $filename = rss_file_name('extendedforum', $extendedforum);  // RSS file
                      
                        //First let's make sure there is work to do by checking existing files
                        if (file_exists($filename)) {
                            if ($lastmodified = filemtime($filename)) {
                                if (!extendedforum_rss_newstuff($extendedforum, $lastmodified)) {
                                    continue;
                                }
                            }
                        }

                        //Ignore hidden extendedforums
                        if (!instance_is_visible('extendedforum',$extendedforum)) {
                            if (file_exists($filename)) {
                                @unlink($filename);
                            }
                            continue;
                        }

                        mtrace("Updating RSS feed for ".format_string($extendedforum->name,true).", ID: $extendedforum->id");

                        //Get the XML contents
                        $result = extendedforum_rss_feed($extendedforum);
                        //Save the XML contents to file
                        if (!empty($result)) {
                            $status = rss_save_file("extendedforum",$extendedforum,$result);
                        }
                        if (debugging()) {
                            if (empty($result)) {
                                echo "ID: $extendedforum->id-> (empty) ";
                            } else {
                                if (!empty($status)) {
                                    echo "ID: $extendedforum->id-> OK ";
                                } else {
                                    echo "ID: $extendedforum->id-> FAIL ";
                                }
                            }
                        }
                    }
                }
            }
        }
        return $status;
    }


    // Given a extendedforum object, deletes the RSS file
    function extendedforum_rss_delete_file($extendedforum) {
        global $CFG;
        $rssfile = rss_file_name('extendedforum', $extendedforum);
        if (file_exists($rssfile)) {
            return unlink($rssfile);
        } else {
            return true;
        }
    }


    function extendedforum_rss_newstuff($extendedforum, $time) {
    // If there is new stuff in the extendedforum since $time then this returns
    // true.  Otherwise it returns false.
        if ($extendedforum->rsstype == 1) {
            $items = extendedforum_rss_feed_discussions($extendedforum, $time);
        } else {
            $items = extendedforum_rss_feed_posts($extendedforum, $time);
        }
        return (!empty($items));
    }

    //This function return the XML rss contents about the extendedforum record passed as parameter
    //It returns false if something is wrong
    function extendedforum_rss_feed($extendedforum) {

        global $CFG;

        $status = true;

        //Check CFG->enablerssfeeds
        if (empty($CFG->enablerssfeeds)) {
            debugging("DISABLED (admin variables)");
        //Check CFG->extendedforum_enablerssfeeds
        } else if (empty($CFG->extendedforum_enablerssfeeds)) {
            debugging("DISABLED (module configuration)");
        //It's working so we start...
        } else {
            //Check the extendedforum has rss activated
            if (!empty($extendedforum->rsstype) && !empty($extendedforum->rssarticles)) {
                //Depending of the extendedforum->rsstype, we are going to execute, different sqls
                if ($extendedforum->rsstype == 1) {    //Discussion RSS
                    $items = extendedforum_rss_feed_discussions($extendedforum);
                } else {                //Post RSS
                    $items = extendedforum_rss_feed_posts($extendedforum);

                }
                //Now, if items, we begin building the structure
                if (!empty($items)) {
                    //First all rss feeds common headers
                    $header = rss_standard_header(strip_tags(format_string($extendedforum->name,true)),
                                                  $CFG->wwwroot."/mod/extendedforum/view.php?f=".$extendedforum->id,
                                                  format_string($extendedforum->intro,true));
                    //Now all the rss items
                    if (!empty($header)) {
                        $articles = rss_add_items($items);
                    }
                    //Now all rss feeds common footers
                    if (!empty($header) && !empty($articles)) {
                        $footer = rss_standard_footer();
                    }
                    //Now, if everything is ok, concatenate it
                    if (!empty($header) && !empty($articles) && !empty($footer)) {
                        $status = $header.$articles.$footer;
                    } else {
                        $status = false;
                    }
                } else {
                    $status = false;
                }
            }
        }
        return $status;
    }

    //This function returns "items" record array to be used to build the rss feed
    //for a Type=discussions extendedforum
    function extendedforum_rss_feed_discussions($extendedforum, $newsince=0) {

        global $CFG;

        $items = array();

        if ($newsince) {
            $newsince = " AND p.modified > '$newsince'";
        } else {
            $newsince = "";
        }

        if ($recs = get_records_sql ("SELECT d.id AS discussionid, 
                                             d.name AS discussionname, 
                                             u.id AS userid, 
                                             u.firstname AS userfirstname,
                                             u.lastname AS userlastname,
                                             p.message AS postmessage,
                                             p.created AS postcreated,
                                             p.format AS postformat
                                      FROM {$CFG->prefix}extendedforum_discussions d,
                                           {$CFG->prefix}extendedforum_posts p,
                                           {$CFG->prefix}user u
                                      WHERE d.extendedforum = '$extendedforum->id' AND
                                            p.discussion = d.id AND
                                            p.parent = 0 AND
                                            u.id = p.userid $newsince
                                      ORDER BY p.created desc", 0, $extendedforum->rssarticles)) {

            $item = NULL;
            $user = NULL;

            $formatoptions = new object;
            $formatoptions->trusttext = true;

            foreach ($recs as $rec) {
                unset($item);
                unset($user);
                $item->title = format_string($rec->discussionname);
                $user->firstname = $rec->userfirstname;
                $user->lastname = $rec->userlastname;
                $item->author = fullname($user);
                $item->pubdate = $rec->postcreated;
                $item->link = $CFG->wwwroot."/mod/extendedforum/discuss.php?d=".$rec->discussionid;
                $item->description = format_text($rec->postmessage,$rec->postformat,$formatoptions,$extendedforum->course);
                $items[] = $item;
            }
        }
        return $items;
    }

    //This function returns "items" record array to be used to build the rss feed
    //for a Type=posts extendedforum
    function extendedforum_rss_feed_posts($extendedforum, $newsince=0) {

        global $CFG;

        $items = array();

        if ($newsince) {
            $newsince = " AND p.modified > '$newsince'";
        } else {
            $newsince = "";
        }

        $sql = "";
         if(!$extendedforum->hideauthor)
         {
           $sql = "SELECT p.id AS postid,
                                             d.id AS discussionid,
                                             d.name AS discussionname,
                                             u.id AS userid,
                                             u.firstname AS userfirstname,
                                             u.lastname AS userlastname,
                                             p.subject AS postsubject,
                                             p.message AS postmessage,
                                             p.created AS postcreated,
                                             p.format AS postformat
                                      FROM {$CFG->prefix}extendedforum_discussions d,
                                           {$CFG->prefix}extendedforum_posts p,
                                           {$CFG->prefix}user u
                                      WHERE d.extendedforum = '$extendedforum->id' AND
                                            p.discussion = d.id AND
                                            u.id = p.userid $newsince
                                      ORDER BY p.created desc" ;
         }
         else
         {
            $sql = "SELECT p.id as postid, 
                           d.id as discussionid , 
                           d.name as discussionname,
                           r.name AS role ,
                          p.subject AS postsubject,
                          p.message AS postmessage,
                          p.created AS postcreated,
                           p.format AS postformat
        FROM {$CFG->prefix}extendedforum_posts p
        INNER JOIN {$CFG->prefix}extendedforum_discussions d ON d.id = p.discussion
        LEFT JOIN {$CFG->prefix}context c ON ( c.instanceid = d.course
        OR c.instanceid =0 ) 
        LEFT JOIN {$CFG->prefix}role_assignments ra ON ra.userid = p.userid
         INNER JOIN {$CFG->prefix}role r ON ra.roleid = r.id                                                                                    
        WHERE d.extendedforum =   '$extendedforum->id'
         $newsince
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
         ORDER BY p.created desc"  ;
         }
        if ($recs = get_records_sql ( $sql, 0, $extendedforum->rssarticles)) {

            $item = NULL;
            $user = NULL;

            $formatoptions = new object;
            $formatoptions->trusttext = true;

            require_once($CFG->libdir.'/filelib.php');

            foreach ($recs as $rec) {
                unset($item);
                unset($user);
                $item->category = $rec->discussionname;
                $item->title = $rec->postsubject;
                if(!$extendedforum->hideauthor)
                {
                $user->firstname = $rec->userfirstname;
                $user->lastname = $rec->userlastname;
                $item->author = fullname($user);
                }
                else
                {
                  $user->role = $rec->role;
                  $item->author = $rec->role;
                }
                $item->pubdate = $rec->postcreated;
                $item->link = $CFG->wwwroot."/mod/extendedforum/discuss.php?d=".$rec->discussionid."&parent=".$rec->postid;
                $item->description = format_text($rec->postmessage,$rec->postformat,$formatoptions,$extendedforum->course);


                $post_file_area_name = str_replace('//', '/', "$extendedforum->course/$CFG->moddata/extendedforum/$extendedforum->id/$rec->postid");
                $post_files = get_directory_list("$CFG->dataroot/$post_file_area_name");
                
                if (!empty($post_files)) {            
                    $item->attachments = array();
                    foreach ($post_files as $file) {                    
                        $attachment = new stdClass;
                        $attachment->url = get_file_url("$post_file_area_name/$file");
                        $attachment->length = filesize("$CFG->dataroot/$post_file_area_name/$file");
                        $item->attachments[] = $attachment;
                    }
                }

                $items[] = $item;
            }
        }
        return $items;
    }
?>
