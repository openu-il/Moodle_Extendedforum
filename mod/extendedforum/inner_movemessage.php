<?php
  print_header_simple(format_string($currentextendedforum->name), "",
                 $navigation, "", "", true, $buttontext, navmenu($course, $cm)); 
                 
            $replycount = extendedforum_count_replies($post);           
     
      $global_move_message = get_string( 'error_postmove', 'extendedforum')    ;
     echo '<script type="text/javascript">movemessage_init("'. $global_move_message .'" )  </script>  ';
            echo '<h1>'     . get_string('movemessage_link', 'extendedforum')   . '</h1>'   ;
           echo '<div>'    . get_string('movemessage_title', 'extendedforum')  . '</div>' ;
            extendedforum_print_post($post, $discussion,  $currentextendedforum, $cm, $course, false, false, false, null, '', '', null,
                            true, null, false, 0 ,0);
      
          if ($replycount) {
              $extendedforumtracked = extendedforum_tp_is_tracked($currentextendedforum);
              $posts = extendedforum_get_all_discussion_posts($discussion->id, "created ASC", $extendedforumtracked, $currentextendedforum->hideauthor);
               extendedforum_print_posts_threaded($course, $cm, $currentextendedforum, $discussion, $post, 0, false, false, $extendedforumtracked, $posts, false, 0 , 0);
          }
       
?>
