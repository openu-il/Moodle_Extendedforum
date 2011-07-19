<?php

    require_once('../../config.php');
    require_once('lib.php');
    
    $currentextendedforumid            = required_param('f', PARAM_INT);        // Forum ID
    $postid             = required_param('p', PARAM_INT);   // POST  ID
    
    if ($currentextendedforumid) {
          if (! $currentextendedforum = get_record("extendedforum", "id", $currentextendedforumid)) {
            error("Forum ID was incorrect or no longer exists");
        }
        if (! $course = get_record("course", "id", $currentextendedforum->course)) {
            error("Forum is misconfigured - don't know what course it's from");
        }

        if (!$cm = get_coursemodule_from_instance("extendedforum", $currentextendedforum ->id, $course->id)) {
            error("Course Module missing");
        }
        
        if (!empty($postid) ){ 
             // User  post
            if (! $post = extendedforum_get_post_full($postid, 0 , $currentextendedforum->hideauthor)) {
                error('Post ID was incorrect');
                
            }
            else
            {
              $discussion = get_record('extendedforum_discussions', 'id', $post->discussion)  ;
            }
        
        }
        else {
        error('Must specify  a  post ID');
       }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        
    }else {
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
      $navlinks[] = array('name'=>get_string("movemessage_link", "extendedforum"));
      $context = get_context_instance(CONTEXT_MODULE, $cm->id);
      $navigation = build_navigation($navlinks, $cm); 
        /// Add ajax-related libs
    require_js(array('yui_yahoo', 'yui_event', 'yui_dom', 'yui_connection', 'yui_json'));
        //handle the form
     
         
                              
        $alldiscussions  = extendedforum_get_discussions($cm)      ;                  
       require_once('movemessage_form.php');
      $customdata = (object)array( 'course'        => $course,
                                  'currentextendedforum'   => $currentextendedforum,
                                  'cm'             => $cm,
                                  'postid'         => $postid,
                                  'postparent'     => $post->parent );
      $mform = new mod_extendedforum_movemessage_form ('movemessage.php', $customdata);
      
     
     
      if ($mform->is_cancelled()){
      
          require("inner_movemessage.php")       ;
         //show the form again
         $mform->display();
         if (count($alldiscussions) > 1 )
         {
         ?>
          <script type = "text/javascript">  <!-- could not hide it in the form -->
           changestyle('divdiscussionlist', 'none') ;
            
           changestyle('divextendedforumlist', 'none') ;
          </script>
          <?php
          }
      }
       else if ($fromform=$mform->get_data()){ //process validated data
           
           //where to move or copy
           $return =    "view.php?f=$currentextendedforumid";
           $extendedforumordis = 1;
           if(isset($fromform->extendedforumordis)  )
           {
                $extendedforumordis =  $fromform->extendedforumordis ; //extendedforum = 1 , disscussion = 0
           }
           
            $action = $fromform->action;
             $postid = $fromform->p;
             
            
             
             
             if (!$post = extendedforum_get_post_full($postid)) {
                   error("Post ID was incorrect");
             }
            
            if (!$discussion = get_record("extendedforum_discussions", "id", $post->discussion)) {
                   error("This post is not part of a discussion!");
              }
              
              
            if( $extendedforumordis  == 1 )  //move to extendedforum, find out which extendedforum
            {
                 if(isset($fromform->extendedforumlist)  ){
                   $extendedforumtomoveid = $fromform->extendedforumlist;
                  }
                  else
                  {
                      error("Forum was not selected");
                  } 
                   //check that form exist
                     if (! $extendedforumto = get_record("extendedforum", "id", $extendedforumtomoveid)) {
                             error("Forum ID was incorrect or no longer exists");
                   }
                    
                    if (!has_capability('mod/extendedforum:movemessage', $context)) {
                                     error("You can't move messages!");
                    }
                    
                    
                    if($action == 'movebutton')  //move
                     {
                        move_post($post, $currentextendedforum, $discussion,  $extendedforumto,  $return, $cm)  ;
                        
                      
                        $extendedforumurl =   "view.php?f=$extendedforumto->id";
                         redirect( $extendedforumurl, '', 0); 
                       exit; 
                    }//end move
                else if($action == 'copybutton')   //copy for whole discussions only
                {
                    if ($post->parent)
                    {
                          error("we can only copy the whole discussion") ;
                    }
                    
                  extendedforum_copy_posts($post, $currentextendedforum, $discussion, $extendedforumto, $cm)  ;
                  $extendedforumurl =   "view.php?f=$extendedforumto->id";
                     redirect( $extendedforumurl, '', 0); 
                       exit; 
                  
                 }  //end copy
                
              
                
            } //move or copy to extendedforum
            else if($extendedforumordis == 0 )  //move to discussion, find out which discussion
            {
               if(isset($fromform->postradio) )
               {
                 $posttomovetoid = $fromform->postradio;
                
                  extendedforum_move_post_sameextendedforum($post,$posttomovetoid, $currentextendedforum, $cm )   ;
                }
                
            }
            else
            {
               error('not recognized action');
            }
             
             $extendedforumurl =   "view.php?f=$currentextendedforumid";
            
             add_to_log($course->id, "extendedforum", "move post",
                    "$extendedforumurl&amp;parent=$postid", "$postid", $cm->id);
            
           redirect( $extendedforumurl, '', 0); 
            exit; 
         
       }
       else{
           require("inner_movemessage.php")       ;
         $mform->display();
          if (count($alldiscussions) > 1 )
          {  
          ?>
          <script type = "text/javascript">  <!-- could not hide it in the form -->
           changestyle('divdiscussionlist', 'none') ;
            changestyle('divextendedforumlist', 'none') ;
          </script>
          <?php
          } 
         }
         
      //finaly
     print_footer($course);
?>
