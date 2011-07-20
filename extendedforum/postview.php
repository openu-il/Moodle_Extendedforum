<?php
   require_once('../../config.php');
   require_once('lib.php');
   
    $extendedforumid   = optional_param('f', 0, PARAM_INT);     //Forum ID
    $postid   = required_param('view', PARAM_INT);   // POST  ID
    $discussionid = required_param('discussion', PARAM_INT)  ; //Discussionid
    $print        = optional_param('print', PARAM_INT)      ;
  
   
      $class = 'posts';
   
    if ($extendedforumid) {
         if (! $extendedforum = get_record("extendedforum", "id", $extendedforumid)) {
            error("Forum ID was incorrect or no longer exists");
            return;
        }
        if (! $course = get_record("course", "id", $extendedforum->course)) {
            error("Forum is misconfigured - don't know what course it's from");
             return;
        }

        if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
            error("Course Module missing");
             return;
        }
    }else {
        error('Must specify a extendedforum');
         return;
    }
   
   
    require_course_login($course, true, $cm);
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);  
    
    
    if (! $post = extendedforum_get_post_full($postid, 0 , $extendedforum->hideauthor)) {
                error('Post ID was incorrect');
                 return;
    }
    
    //make sure user can see post
      if (!$discussion = get_record('extendedforum_discussions','id',$discussionid))
      {
        error("Discussion ID was incorrect or no longer exists");
            return;
      
      }
    if( !extendedforum_user_can_see_post($extendedforum, $discussion, $post, null, $cm  ))
    {
        //user cannot view post
        debugging('user cannot view post', DEBUG_DEVELOPER);
         return;
    }
   
   $userfrom = get_record('user', 'id', $post->userid);
    
      
    
    
    if($print == 1)
   {
        $title =   get_string('print', 'extendedforum');
        
    }
    else
    {
        $title = $post->subject     ;
    }
      print_header($title, '', '', '','', $cache=true, '&nbsp;', '',false, '', false)   ; 
   
      echo '<h1>' . $course->fullname . ' ( ' . $course->shortname . ') </h1>';
      echo '<h2>'  .$extendedforum->name . '</h2>' ;
     
      
   if($print== 1) { 
   
    $print_text = get_string('print', 'extendedforum');
      
      $printgif = '/extendedforum/print.gif';
      $printimage = '<img src="' . $CFG->wwwroot . '/mod/extendedforum/pix/print.gif'" class="printimage"  border="0" alt="'.  $print_text . '" title = "' . $print_text. '" width="16"  height="16">' ;
    
     $post_message = get_textonly_postmessage($post, $course, $extendedforum, $userfrom, $cm, true, true) ; 
      echo '<div class="printimage"><a href="javascript:print();">'. $printimage .'&nbsp;' .  $print_text . '</a></div>';
      echo '<p>' . $post_message;
       echo '</div>';
       
      if ($post->attachment) {
        $post->course = $course->id;
        echo '<div class="attachments">';
        echo extendedforum_print_attachments($post, 'html');
        echo "</div>";
    }
    
   }
   else
   {
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
       extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false, $ratings, "", "", null, true, 
                      null , false, 0 , 0);
                      
       echo '</div></div>';
 
   }
   
   
  
  
 
  
?>
