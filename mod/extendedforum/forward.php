<?php
    require_once('../../config.php');
    require_once('lib.php');
    

    $f           = required_param('f', PARAM_INT);        // Forum ID
    $forward     = required_param('forward', PARAM_INT);   // POST  ID
    $page        = required_param('page', PARAM_INT)  ; //page number
      
      
    
    if ($f) {
         if (! $extendedforum = get_record("extendedforum", "id", $f)) {
            error("Forum ID was incorrect or no longer exists");
        }
        if (! $course = get_record("course", "id", $extendedforum->course)) {
            error("Forum is misconfigured - don't know what course it's from");
        }

        if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
            error("Course Module missing");
        }

        if (!empty($forward)) { 
             // User forwarding post
            if (! $post = extendedforum_get_post_full($forward)) {
                error('Post ID was incorrect');
            }
            $discussion = get_record('extendedforum_discussions','id',$post->discussion) ;
        }
        else {
        error('Must specify  a forward post ID');
       }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
    }
    else {
        error('Must specify  a extendedforum ID');
    }
    
     $navlinks = array();   
     $buttontext = '';        
      //build navigation
      if (! $toppost = get_record("extendedforum_posts", "discussion", $post->discussion, "parent", 0)) {
            error("Could not find top parent of post $post->id");
        }
        
        
        
       if ($post->parent) {
        $navlinks[] = array('name' => format_string($toppost->subject, true), 'link' => "view.php?id=$cm->id", 'type' => 'title');
        $navlinks[] = array('name' => format_string($post->subject, true), 'link' => "view.php?id=$cm->id", 'type' => 'title'); 
       } else {
        $navlinks[] = array('name' => format_string($toppost->subject), 'link' => "view.php?id=$cm->id", 'type' => 'title');
       }
      $navlinks[] = array('name'=>get_string("forwardbymail", "extendedforum"));
      $context = get_context_instance(CONTEXT_MODULE, $cm->id);
      $navigation = build_navigation($navlinks, $cm); 
     
    
    print_header_simple(format_string($extendedforum->name), "",
                 $navigation, "", "", true, $buttontext, navmenu($course, $cm));
     
    
     //Forward mail form
       require_once('forward_form.php'); 
      
      $customdata = (object)array( 'subject' => $post->subject,
                                    'postid' => $post->id,
                                    'extendedforumid' => $f,
                                    'page'    => $page);
          
    $mform = new mod_extendedforum_forward_form ('forward.php', $customdata);
      if ($mform->is_cancelled()){
        $extendedforumurl =   "view.php?f=$extendedforum->id";
          redirect( $extendedforumurl, '', 0); 
           exit;
           
      } 
       else if ($fromform=$mform->get_data()){ //process validated data
       
         $a = (object)array('name' => fullname($USER, true),
                            'course' => $course->shortname,
                            'coursefull'  => $course->fullname,
                            'email' => $USER->email) ;
         
         
          $allhtml = "<head>";
        foreach ($CFG->stylesheets as $stylesheet) {
            $allhtml .= '<link rel="stylesheet" type="text/css" href="' .
                $stylesheet . '" />' . "\n";
        }
        
        $allhtml .= "</head>\n<html><body id='email'>\n";
      
         $preface = get_string('forward_preface', 'extendedforum', $a);
        
        $allhtml .= $preface;
        $alltext = format_text_email($preface, FORMAT_HTML);
        
        // Include intro if specified
        if (!preg_match('~^(<br[^>]*>|<p>|</p>|\s)*$~', $fromform->message)) {
            $alltext .= "\n" . EMAIL_DIVIDER . "\n";
            $allhtml .= '<hr size="1" noshade="noshade" />';

            // Add intro
            $message = trusttext_strip(stripslashes($fromform->message));
            $allhtml .= format_text($message, $fromform->format);
            $alltext .= format_text_email($message, $fromform->format);
        }
        
          //now add the post
          $alltext .= "\n" . EMAIL_DIVIDER . "\n";
          $allhtml .= '<hr size="1" noshade="noshade" />';
          
          $userfrom = get_record('user', 'id', $post->userid);
          
                                              
          $post_message = get_textonly_postmessage($post, $course, $extendedforum, $userfrom, $cm, true) ;
           $allhtml .= $post_message; 
        
        $alltext .= format_text_email($post_message, $fromform->format); 
        
        if ($post->attachment) {
           $post->course = $course->id;
           $post->extendedforum = $extendedforum->id;
           $alltext .= extendedforum_print_attachments($post, "text");
           
           $allhtml .=  extendedforum_print_attachments($post, "html");
         }
         
         //add link to message for html messages only
          $allhtml .= '<div class="link">';
          $allhtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/extendedforum/discuss.php?d='.$post->discussion.'&postid='.$post->id.'">'.
                     get_string('postincontext', 'extendedforum').'</a>';
          $allhtml .= '</div>';
          
         $allhtml .="</body></html>"    ;
         
         
        $emails = preg_split('~[; ]+~', $fromform->email);
        $subject = stripslashes($fromform->subject);
        
        
         foreach ($emails as $email) {
            $fakeuser = (object)array(
                'email' => $email,
                'mailformat' => 1,
                'id' => 0
            );

            $from = $USER;

            if (!email_to_user($fakeuser, $from, $subject, $alltext, $allhtml)) {
                print_error('error_forwardemail', 'extendedforum', $fromform->email);
            }
        }
        //send to me
       
        if(!empty($fromform->ccme)) {
            if (!email_to_user($USER, $from, $subject, $alltext, $allhtml)) {
                print_error('error_forwardemail', 'extendedforum', $USER->email);
            }
        }
            //done sending mail
            print_box(get_string('forward_done', 'extendedforum')); 
            print_continue('view.php?id=' . $cm->id . '&page='. $page);
       }
       else
       {
       
       //print the form
       $mform->display();         
     
     //display  the message  
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
                      null , false, 0 , 0 , 0);   
      
             
   
   } 
   
     //finaly
     print_footer($course);
?>
