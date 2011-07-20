<?php // $Id: post.php,v 1.154.2.18 2009/10/13 20:53:57 skodak Exp $

//  Edit and save a new post to a discussion

require_once('../../config.php');
require_once('lib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$extendedforum   = optional_param('extendedforum', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
// $prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);
$page    = optional_param('page', 0 , PARAM_INT)     ;
$on_top  = optional_param('on_top', 0, PARAM_INT)   ; //1 or -1
$extendedforumid = optional_param('extendedforumid', 0, PARAM_INT)   ;  //extendedforum id for on_top, recommand
$discussionid =    optional_param('discussionid', 0, PARAM_INT)   ;  //discussion id for on_top
$recommand =    optional_param('recommand', 0, PARAM_INT)     ; //1 or -1
 

//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'extendedforum'=>$extendedforum, 'edit'=>$edit);

$sitecontext = get_context_instance(CONTEXT_SYSTEM);

//check if it is a guest
if (has_capability('moodle/legacy:guest', $sitecontext, NULL, false)) {

	$wwwroot = $CFG->wwwroot.'/login/index.php';
	if (!empty($CFG->loginhttps)) {
		$wwwroot = str_replace('http:', 'https:', $wwwroot);
	}

	if (!empty($extendedforum)) {      // User is starting a new discussion in a extendedforum
		if (! $extendedforum = get_record('extendedforum', 'id', $extendedforum)) {
			error('The extendedforum number was incorrect');
		}
	} else if (!empty($reply)) {
		// User is writing a new reply
		if (! $parent = extendedforum_get_post_full($reply)) {
			error('Parent post ID was incorrect');
		}
		if (! $discussion = get_record('extendedforum_discussions', 'id', $parent->discussion)) {
			error('This post is not part of a discussion!');
		}
		if (! $extendedforum = get_record('extendedforum', 'id', $discussion->extendedforum)) {
			error('The extendedforum number was incorrect');
		}
	}
	if (! $course = get_record('course', 'id', $extendedforum->course)) {
		error('The course number was incorrect');
	}

	if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) { // For the logs
		error('Could not get the course module for the extendedforum instance.');
	} else {
		$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
	}

	if (!get_referer()) {   // No referer - probably coming in via email  See MDL-9052
		require_login();
	}


	$navigation = build_navigation('', $cm);
	 
	print_header($course->shortname, $course->fullname, $navigation, '' , '', true, "", navmenu($course, $cm));

	notice_yesno(get_string('noguestpost', 'extendedforum').'<br /><br />'.get_string('liketologin'),
	$wwwroot, get_referer(false));
	print_footer($course);
	exit;
}
//now for all registered users
require_login(0, false);   // Script is useless unless they're logged in

if (!empty($extendedforum)) {      // User is starting a new discussion in a extendedforum
	if (! $extendedforum = get_record("extendedforum", "id", $extendedforum)) {
		error("The extendedforum number was incorrect ($extendedforum)");
	}
	if (! $course = get_record("course", "id", $extendedforum->course)) {
		error("The course number was incorrect ($extendedforum->course)");
	}
	if (! $cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
		error("Incorrect course module");
	}

	$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

	if (! extendedforum_user_can_post_discussion($extendedforum, $groupid, -1, $cm)) {
		if (has_capability('moodle/legacy:guest', $coursecontext, NULL, false)) {  // User is a guest here!
			$SESSION->wantsurl = $FULLME;
			$SESSION->enrolcancel = $_SERVER['HTTP_REFERER'];
			redirect($CFG->wwwroot.'/course/enrol.php?id='.$course->id, get_string('youneedtoenrol'));
		} else {
			print_error('nopostextendedforum', 'extendedforum');
		}
	}

	if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
		print_error("activityiscurrentlyhidden");
	}

	if (isset($_SERVER["HTTP_REFERER"])) {
		$SESSION->fromurl = $_SERVER["HTTP_REFERER"];
	} else {
		$SESSION->fromurl = '';
	}


	// Load up the $post variable.

	$post = new object();
	$post->course     = $course->id;
	$post->extendedforum      = $extendedforum->id;
	$post->discussion = 0;           // ie discussion # not defined yet
	$post->parent     = 0;
	$post->subject    = '';
	$post->userid     = $USER->id;
	$post->message    = '';

	if (isset($groupid)) {
		$post->groupid = $groupid;
	} else {
		$post->groupid = groups_get_activity_group($cm);
	}
   
	extendedforum_set_return();

} else if (!empty($reply)) { // User is writing a new reply

    
	if (! $parent = extendedforum_get_post_full($reply)) {
		error("Parent post ID was incorrect");
	}
	if (! $discussion = get_record("extendedforum_discussions", "id", $parent->discussion)) {
		error("This post is not part of a discussion!");
	}
	if (! $extendedforum = get_record("extendedforum", "id", $discussion->extendedforum)) {
		error("The extendedforum number was incorrect ($discussion->extendedforum)");
	}
	if($extendedforum->hideauthor)
	{
		$parent->role = extendedforum_get_user_main_role($parent->userid, $discussion->course)      ;
	}
	if (! $course = get_record("course", "id", $discussion->course)) {
		error("The course number was incorrect ($discussion->course)");
	}
	if (! $cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
		error("Incorrect cm");
	}

	// call course_setup to use forced language, MDL-6926
	course_setup($course->id);

	$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
	$modcontext    = get_context_instance(CONTEXT_MODULE, $cm->id);
    
 
	if (! extendedforum_user_can_post($extendedforum, $discussion, $USER, $cm, $course, $modcontext)) {
		if (has_capability('moodle/legacy:guest', $coursecontext, NULL, false)) {  // User is a guest here!
			$SESSION->wantsurl = $FULLME;
			$SESSION->enrolcancel = $_SERVER['HTTP_REFERER'];
			redirect($CFG->wwwroot.'/course/enrol.php?id='.$course->id, get_string('youneedtoenrol'));
		} else {
			print_error('nopostextendedforum', 'extendedforum');
		}
	}

	// Make sure user can post here
	if (groupmode($course, $cm) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
		if ($discussion->groupid == -1) {
			print_error('nopostextendedforum', 'extendedforum');
		} else {
			if (!groups_is_member($discussion->groupid)) {
				print_error('nopostextendedforum', 'extendedforum');
			}
		}
	}

	if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
		print_error("activityiscurrentlyhidden");
	}

	// Load up the $post variable.
  
	$post = new object();
	$post->course      = $course->id;
	$post->extendedforum       = $extendedforum->id;
	$post->discussion  = $parent->discussion;
	$post->parent      = $parent->id;
	$post->subject     = $parent->subject;
	$post->userid      = $USER->id;
	$post->message     = '';

	$post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

	$strre = get_string('re', 'extendedforum');
	if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
		$post->subject = $strre.' '.$post->subject;
	}
   
	unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

	if (! $post = extendedforum_get_post_full($edit)) {
		error("Post ID was incorrect");
	}
	if ($post->parent) {
		if (! $parent = extendedforum_get_post_full($post->parent)) {
			error("Parent post ID was incorrect ($post->parent)");
		}
	}

	if (! $discussion = get_record("extendedforum_discussions", "id", $post->discussion)) {
		error("This post is not part of a discussion! ($edit)");
	}
	if (! $extendedforum = get_record("extendedforum", "id", $discussion->extendedforum)) {
		error("The extendedforum number was incorrect ($discussion->extendedforum)");
	}
	if (! $course = get_record("course", "id", $discussion->course)) {
		error("The course number was incorrect ($discussion->course)");
	}
	if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
		error('Could not get the course module for the extendedforum instance.');
	} else {
		$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
	}
	if (!($extendedforum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
		if (((time() - $post->created) > $CFG->maxeditingtime) and
		!has_capability('mod/extendedforum:editanypost', $modcontext)) {
			error( get_string("maxtimehaspassed", "extendedforum", format_time($CFG->maxeditingtime)) );
		}
	}
	if (($post->userid != $USER->id) or
	!has_capability('mod/extendedforum:editanypost', $modcontext)) {
		error("You can't edit other people's posts!");
	}
	if($extendedforum->hideauthor)
	{
	  
		$parent->role = extendedforum_get_user_main_role($post->userid, $discussion->course)      ;
	}

	// Load up the $post variable.
	$post->edit   = $edit;
	$post->course = $course->id;
	$post->extendedforum  = $extendedforum->id;
	$post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

	trusttext_prepare_edit($post->message, $post->format, can_use_html_editor(), $modcontext);

	unset($SESSION->fromdiscussion);


}else if (!empty($delete)) {  // User is deleting a post

	if (! $post = extendedforum_get_post_full($delete)) {
		error("Post ID was incorrect");
	}
	if (! $discussion = get_record("extendedforum_discussions", "id", $post->discussion)) {
		error("This post is not part of a discussion!");
	}
	if (! $extendedforum = get_record("extendedforum", "id", $discussion->extendedforum)) {
		error("The extendedforum number was incorrect ($discussion->extendedforum)");
	}
	if($extendedforum->hideauthor)
	{
		$post->role =extendedforum_get_user_main_role($post->userid, $discussion->course)      ;
	}
	if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $extendedforum->course)) {
		error('Could not get the course module for the extendedforum instance.');
	}
	if (!$course = get_record('course', 'id', $extendedforum->course)) {
		error('Incorrect course');
	}

	require_login($course, false, $cm);
	$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
	//   $discussion->page = $page;

	if ( !(($post->userid == $USER->id && has_capability('mod/extendedforum:deleteownpost', $modcontext))
	|| has_capability('mod/extendedforum:deleteanypost', $modcontext)) ) {
		error("You can't delete this post!");
	}


	$replycount = extendedforum_count_replies($post);

	if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
    
		if ($post->totalscore) {
	
			notice(get_string("couldnotdeleteratings", "extendedforum"),
			extendedforum_go_back_to("view.php?f=$extendedforum->id"));

		} else if ($replycount && !has_capability('mod/extendedforum:deleteanypost', $modcontext)) {
		
			print_error("couldnotdeletereplies", "extendedforum",
		  	extendedforum_go_back_to("view.php?f=$extendedforum->id"));

		} else {
			if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
				if ($extendedforum->type == 'single') {
					notice("Sorry, but you are not allowed to delete that discussion!",
					extendedforum_go_back_to("view.php?f=$extendedforum->id"));
				}
				extendedforum_delete_discussion($discussion);
         	 
				add_to_log($discussion->course, "extendedforum", "delete discussion",
                               "view.php?id=$cm->id", "$extendedforum->id", $cm->id);
        
				
			    	redirect("view.php?f=$discussion->extendedforum");
			} else if (extendedforum_delete_post($post, has_capability('mod/extendedforum:deleteanypost', $modcontext))) {
				/*
				 if ($extendedforum->type == 'single') {
				 // Single discussion extendedforums are an exception. We show
				 // the extendedforum itself since it only has one discussion
				 // thread.
				 $discussionurl = "view.php?f=$extendedforum->id";
				 } else {
				 $discussionurl = "discuss.php?d=$post->discussion";
				 }
				 */
				 $discussionurl =   "view.php?f=$extendedforum->id&page=$page";
				
				add_to_log($discussion->course, "extendedforum", "delete post", $discussionurl, "$post->id", $cm->id);
        
				redirect(extendedforum_go_back_to($discussionurl));
			} else {
				error("An error occurred while deleting record $post->id");
			}
		}


	} else { // User just asked to delete something
   
		extendedforum_set_return();
    
     $canupdateflag = has_capability('mod/extendedforum:updateflag', $modcontext)  ;
    
		if ($replycount) {
			if (!has_capability('mod/extendedforum:deleteanypost', $modcontext)) {
				print_error("couldnotdeletereplies", "extendedforum",
				extendedforum_go_back_to("view.php?id=$modcontext->instanceid"));
			}
			print_header();
			notice_yesno(get_string("deletesureplural", "extendedforum", $replycount+1),
                             "post.php?delete=$delete&amp;confirm=$delete&amp;sesskey=".sesskey(),
			extendedforum_go_back_to("view.php?id=$modcontext->instanceid"));
        
			  
   
 
			extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false,
                     NULL, "", "",null,  true, null, $canupdateflag, 1, 0 , 0 );

			if (empty($post->edit)) {
				$extendedforumtracked = extendedforum_tp_is_tracked($extendedforum);
				 
				$posts = extendedforum_get_all_discussion_posts($discussion->id, "created ASC", $extendedforumtracked, $extendedforum->hideauthor);
				extendedforum_print_posts_nested($course, $cm, $extendedforum, $discussion, $post, false, false, $extendedforumtracked, $posts, $canupdateflag,0, 0);
			}
		} else {
		
			print_header();
			notice_yesno(get_string("deletesure", "extendedforum", $replycount),
                             "post.php?delete=$delete&amp;confirm=$delete&amp;sesskey=".sesskey(),
			extendedforum_go_back_to("view.php?id=$modcontext->instanceid"));
				extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false,
                     NULL, "", "",null,  true, null, $canupdateflag, 1, 0 , 0 );
		}

	}
	print_footer($course);
	die;


}
else if(!empty($recommand)  )
{
	$extendedforumurl =   "view.php?f=$extendedforumid";
	if (! $extendedforum = get_record("extendedforum", "id", $extendedforumid)) {
		error("The extendedforum number was incorrect ($extendedforumid)");
	}

	if (! $course = get_record('course', 'id', $extendedforum->course)) {
		error('The course number was incorrect');
	}

	if (! $cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
		error("Incorrect cm");
	}


	redirect(extendedforum_go_back_to( $extendedforumurl ));
	die;
}
else if( !empty($on_top)) {


	if (! $extendedforum = get_record("extendedforum", "id", $extendedforumid)) {
		error("The extendedforum number was incorrect ($extendedforumid)");
	}

	if (! $course = get_record('course', 'id', $extendedforum->course)) {
		error('The course number was incorrect');
	}

	if (! $cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id)) {
		error("Incorrect cm");
	}
	$extendedforumurl =   "view.php?id=$cm->id";
	if (! $discussion = get_record('extendedforum_discussions', 'id', $discussionid)) {
		error('Discussion id is not correct!');
	}

	$message = '';
	if($on_top == 1 )
	{
		$discussion->on_top = 1;
		$message  = "lock message"  ;
	}
	else{
		$discussion->on_top = 'NULL';
		$message = "unlock message"   ;
	}

	if (!update_record("extendedforum_discussions", $discussion)) {
		print_error("couldnotupdate", "extendedforum", $extendedforumurl);
	}

	add_to_log($course->id, "extendedforum", $message,
	$extendedforumurl, $on_top, $cm->id);
	redirect ($extendedforumurl);
	die;

}


/*else if (!empty($prune)) {  // Pruning

if (!$post = extendedforum_get_post_full($prune)) {
error("Post ID was incorrect");
}
if (!$discussion = get_record("extendedforum_discussions", "id", $post->discussion)) {
error("This post is not part of a discussion!");
}
if (!$extendedforum = get_record("extendedforum", "id", $discussion->extendedforum)) {
error("The extendedforum number was incorrect ($discussion->extendedforum)");
}
if ($extendedforum->type == 'single') {
error('Discussions from this extendedforum cannot be split');
}

if($extendedforum->hideauthor)
{
$post->role = extendedforum_get_user_main_role($post->userid, $discussion->course)      ;
}

if (!$post->parent) {
error('This is already the first post in the discussion');
}
if (!$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $extendedforum->course)) { // For the logs
error('Could not get the course module for the extendedforum instance.');
} else {
$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
}
if (!has_capability('mod/extendedforum:splitdiscussions', $modcontext)) {
error("You can't split discussions!");
}

if (!empty($name) && confirm_sesskey()) {    // User has confirmed the prune

$newdiscussion = new object();
$newdiscussion->course       = $discussion->course;
$newdiscussion->extendedforum        = $discussion->extendedforum;
$newdiscussion->name         = $name;
$newdiscussion->firstpost    = $post->id;
$newdiscussion->userid       = $discussion->userid;
$newdiscussion->groupid      = $discussion->groupid;
$newdiscussion->assessed     = $discussion->assessed;
$newdiscussion->usermodified = $post->userid;
$newdiscussion->timestart    = $discussion->timestart;
$newdiscussion->timeend      = $discussion->timeend;

if (!$newid = insert_record('extendedforum_discussions', $newdiscussion)) {
error('Could not create new discussion');
}

$newpost = new object();
$newpost->id      = $post->id;
$newpost->parent  = 0;
$newpost->subject = $name;

if (!update_record("extendedforum_posts", $newpost)) {
error('Could not update the original post');
}

extendedforum_change_discussionid($post->id, $newid);

// update last post in each discussion
extendedforum_discussion_update_last_post($discussion->id);
extendedforum_discussion_update_last_post($newid);

add_to_log($discussion->course, "extendedforum", "prune post",
"discuss.php?d=$newid", "$post->id", $cm->id);

redirect(extendedforum_go_back_to("discuss.php?d=$newid"));

} else { // User just asked to prune something

$course = get_record('course', 'id', $extendedforum->course);

$navlinks = array();
$navlinks[] = array('name' => format_string($post->subject, true), 'link' => "discuss.php?d=$discussion->id", 'type' => 'title');
$navlinks[] = array('name' => get_string("prune", "extendedforum"), 'link' => '', 'type' => 'title');
$navigation = build_navigation($navlinks, $cm);
print_header_simple(format_string($discussion->name).": ".format_string($post->subject), "", $navigation, '', "", true, "", navmenu($course, $cm));

print_heading(get_string('pruneheading', 'extendedforum'));
echo '<center>';

include('prune.html');

extendedforum_print_post($post, $discussion, $extendedforum, $cm, $course, false, false, false);
echo '</center>';
}
print_footer($course);
die;
} */
else {
	error("No operation specified");

}

if (!isset($coursecontext)) {
	// Has not yet been set by post.php.
	$coursecontext = get_context_instance(CONTEXT_COURSE, $extendedforum->course);
}

if (!$cm = get_coursemodule_from_instance('extendedforum', $extendedforum->id, $course->id)) { // For the logs
	error('Could not get the course module for the extendedforum instance.');
}
$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

// setup course variable to force form language
// fix for MDL-6926
course_setup($course->id);
require_once('post_form.php');

$mform_post = new mod_extendedforum_post_form('post.php', array('course'=>$course, 'cm'=>$cm, 'coursecontext'=>$coursecontext, 'modcontext'=>$modcontext, 'extendedforum'=>$extendedforum, 'post'=>$post));

if ($fromform = $mform_post->get_data()) {

	/////////////< Upload HEB files
	if (is_array($mform_post->_upload_manager->files) && count($mform_post->_upload_manager->files))
	{
		for ($i = 0; $i < count($mform_post->_upload_manager->files); $i++)
		{
			$ou_file[$i]->display_name = $mform_post->_upload_manager->files["FILE_{$i}"]["originalname"];
			$ou_file[$i]->real_name = $mform_post->_upload_manager->files["FILE_{$i}"]["name"];
		}
	}
	/////////////>

	require_login($course, false, $cm);

	if (empty($SESSION->fromurl)) {
		$errordestination = "$CFG->wwwroot/mod/extendedforum/view.php?f=$extendedforum->id&page=$page";
	} else {
		$errordestination = $SESSION->fromurl;
	}

	// TODO add attachment processing
	//$fromform->attachment = isset($_FILES['attachment']) ? $_FILES['attachment'] : NULL;

	trusttext_after_edit($fromform->message, $modcontext);

	if ($fromform->edit) {           // Updating a post
		unset($fromform->groupid);
		$fromform->id = $fromform->edit;
		$message = '';

		//fix for bug #4314
		if (!$realpost = get_record('extendedforum_posts', 'id', $fromform->id)) {
			$realpost = new object;
			$realpost->userid = -1;
		}


		// if user has edit any post capability
		// or has either startnewdiscussion or reply capability and is editting own post
		// then he can proceed
		// MDL-7066
		if ( !(($realpost->userid == $USER->id && (has_capability('mod/extendedforum:replypost', $modcontext)
		|| has_capability('mod/extendedforum:startdiscussion', $modcontext))) ||
		has_capability('mod/extendedforum:editanypost', $modcontext)) ) {
			error("You can not update this post");
		}

		/* Dmitry Zviagilsky 03-08-2010
		 * If Remove attachment checkbox checked in Edit glossary entry page - delete old attachments
		 */
		 if(isset($fromform->removeattachment))
		 {
		   if (count($fromform->removeattachment))
		   {
		   	$exceptions = array_keys($fromform->removeattachment, 0);
			
			  extendedforum_delete_old_multiple_attachments($post, $exceptions);
			
	   	  }
	   	}

		$updatepost = $fromform; //realpost
		$updatepost->extendedforum = $extendedforum->id;
		if (!extendedforum_update_post($updatepost, $message)) {
			print_error("couldnotupdate", "extendedforum", $errordestination);
		}
                $dir = extendedforum_file_area_name($fromform);
                    if($mform_post->save_files($dir)){
                        set_field("extendedforum_posts", "attachment", 'attached', "id", $fromform->id);
                    }
                    else
                    {

                        set_field("extendedforum_posts", "attachment", NULL, "id", $fromform->id);
                    }
		// MDL-11818
		if (($extendedforum->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating extendedforum intro
			$extendedforum->intro = stripslashes($updatepost->message);
			$extendedforum->timemodified = time();
			if (!update_record("extendedforum", addslashes_recursive($extendedforum))) {
				print_error("couldnotupdate", "extendedforum", $errordestination);
			}
		}

		$timemessage = 2;
		if (!empty($message)) { // if we're printing stuff about the file upload
			$timemessage = 4;
		}
		$message .= '<br />'.get_string("postupdated", "extendedforum");

		if ($subscribemessage = extendedforum_post_subscription($fromform, $extendedforum)) {
			$timemessage = 4;
		}
		if ($extendedforum->type == 'single') {
			// Single discussion extendedforums are an exception. We show
			// the extendedforum itself since it only has one discussion
			// thread.
			$discussionurl = "view.php?f=$extendedforum->id";
		} else {
			$discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
		}

		//$discussionurl =   "view.php?f=$extendedforum->id&page=$page";
		$discussionurl =   "view.php?f=$extendedforum->id";

		//////////< Upload HEB files
		if (is_array($ou_file) && count($ou_file))
		{
			for ($i = 0; $i < count($ou_file); $i++)
			{
				$ou_file[$i]->fullpath = str_replace("//","/" ,$CFG->dataroot).'/'.$course->id.'/moddata/extendedforum/'.$extendedforum->id.'/'.$fromform->id.'/'.$ou_file[$i]->real_name;
				
				if(! $ou_old_file = get_record('ou_files', 'fullpath' ,$ou_file[$i]->fullpath) )
				{
					insert_record('ou_files', $ou_file[$i]);
				} 
				else 
				{
					$ou_old_file->display_name = htmlspecialchars($ou_file[$i]->display_name);
					update_record('ou_files', $ou_old_file);
				}
			}
		}
		///////////>

		add_to_log($course->id, "extendedforum", "update post",
                    "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

		//  redirect(extendedforum_go_back_to("$discussionurl"), $message.$subscribemessage, $timemessage);
		redirect(extendedforum_go_back_to("$discussionurl", '', 0));
		exit;

		 
	} else if ($fromform->discussion) { // Adding a new post to an existing discussion
		unset($fromform->groupid);
		$message = '';
		$addpost=$fromform;
		$addpost->extendedforum=$extendedforum->id;
		 

		if ($fromform->id = extendedforum_add_new_post($addpost, $message)) {
                    $dir = extendedforum_file_area_name($fromform);
                    if($mform_post->save_files($dir)){
                        set_field("extendedforum_posts", "attachment", 'attached', "id", $fromform->id);
                    }
			$timemessage = 2;
			if (!empty($message)) { // if we're printing stuff about the file upload
				$timemessage = 4;
			}

			if ($subscribemessage = extendedforum_post_subscription($fromform, $extendedforum)) {
				$timemessage = 4;
			}

			if (!empty($fromform->mailnow)) {
				$message .= get_string("postmailnow", "extendedforum");
				$timemessage = 4;
			} else {
				$message .= '<p>'.get_string("postaddedsuccess", "extendedforum") . '</p>';
				$message .= '<p>'.get_string("postaddedtimeleft", "extendedforum", format_time($CFG->maxeditingtime)) . '</p>';
			}

			/* if ($extendedforum->type == 'single') {
			 // Single discussion extendedforums are an exception. We show
			 // the extendedforum itself since it only has one discussion
			 // thread.
			 $discussionurl = "view.php?f=$extendedforum->id";
			 } else {
			 $discussionurl = "discuss.php?d=$discussion->id";
			 }
			 */
			//$discussionurl =   "view.php?f=$extendedforum->id&page=$page";
			$discussionurl =   "view.php?f=$extendedforum->id";
				
			/////////< Upload HEB files
//			if (is_array($ou_file) && count($ou_file))
//			{
//				for ($i = 0; $i < count($ou_file); $i++)
//				{
//					$ou_file[$i]->fullpath = str_replace("//","/" ,$CFG->dataroot).'/'.$course->id.'/moddata/extendedforum/'.$extendedforum->id.'/'.$fromform->id.'/'.$ou_file[$i]->real_name;
//					if(! $ou_old_file = get_record('ou_files', 'fullpath' ,$ou_file[$i]->fullpath) ){
//						insert_record('ou_files', $ou_file[$i]);
//					} else {
//						$ou_old_file->display_name = htmlspecialchars($ou_file[$i]->display_name);
//						update_record('ou_files', $ou_old_file);
//					}
//				}
//			}
			///////////>
				
			add_to_log($course->id, "extendedforum", "add post",
                          "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

			// redirect(extendedforum_go_back_to("$discussionurl#p$fromform->id"), $message.$subscribemessage, $timemessage);
			 
			redirect($discussionurl, '', 0);
		} else {
			print_error("couldnotadd", "extendedforum", $errordestination);
		}
		exit;

	} else {                     // Adding a new discussion
		if (!extendedforum_user_can_post_discussion($extendedforum, $fromform->groupid, -1, $cm, $modcontext)) {
			error('Can not add discussion, sorry.');
		}
		if (empty($fromform->groupid)) {
			$fromform->groupid = -1;
		}

		$fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;
		$discussion = $fromform;

		$discussion->name  = $fromform->subject;
		$discussion->intro = $fromform->message;
		$newstopic = false;

		if ($extendedforum->type == 'news' && !$fromform->parent) {
			$newstopic = true;
		}
		$discussion->timestart = $fromform->timestart;
		$discussion->timeend = $fromform->timeend;

		$message = '';
                $post_of_discussion =  extendedforum_add_discussion($discussion, $message);
                $discussion->id = $post_of_discussion->discussion;
		if ($post_of_discussion->discussion) {

			/////////< Upload HEB files
//			if (is_array($ou_file) && count($ou_file))
//			{
//				for ($i = 0; $i < count($ou_file); $i++)
//				{
//					$ou_file[$i]->fullpath = str_replace("//","/" ,$CFG->dataroot).'/'.$course->id.'/moddata/extendedforum/'.$extendedforum->id.'/'.$fromform->firstpost.'/'.$ou_file[$i]->real_name;
//					if(! $ou_old_file = get_record('ou_files', 'fullpath' ,$ou_file[$i]->fullpath) ){
//						insert_record('ou_files', $ou_file[$i]);
//					} else {
//						$ou_old_file->display_name = htmlspecialchars($ou_file[$i]->display_name);
//						update_record('ou_files', $ou_old_file);
//					}
//				}
//			}
			///////////>
                        
                         $dir = extendedforum_file_area_name($post_of_discussion);
                        
			 if($mform_post->save_files($dir)){
                           set_field("extendedforum_posts", "attachment", 'attached', "id", $post_of_discussion->id);
                        }
			add_to_log($course->id, "extendedforum", "add discussion",
                        "discuss.php?d=$discussion->id", "$discussion->id", $cm->id);

			$timemessage = 2;
			if (!empty($message)) { // if we're printing stuff about the file upload
				$timemessage = 4;
			}

			if ($fromform->mailnow) {
				$message .= get_string("postmailnow", "extendedforum");
				$timemessage = 4;
			} else {
				$message .= '<p>'.get_string("postaddedsuccess", "extendedforum") . '</p>';
				$message .= '<p>'.get_string("postaddedtimeleft", "extendedforum", format_time($CFG->maxeditingtime)) . '</p>';
			}

			if ($subscribemessage = extendedforum_post_subscription($discussion, $extendedforum)) {
				$timemessage = 4;
			}

			// redirect(extendedforum_go_back_to("view.php?f=$fromform->extendedforum&page=$page"), $message.$subscribemessage, $timemessage);
			redirect(extendedforum_go_back_to("view.php?f=$fromform->extendedforum&page=$page"));

		} else {
			print_error("couldnotadd", "extendedforum", $errordestination);
		}

		exit;
	}
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $extendedforum are defined.  $discussion is for edit and reply only.

$cm = get_coursemodule_from_instance("extendedforum", $extendedforum->id, $course->id);

require_login($course->id, false, $cm);

$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

if ($post->discussion) {
	if (! $toppost = get_record("extendedforum_posts", "discussion", $post->discussion, "parent", 0)) {
		error("Could not find top parent of post $post->id");
	}
} else {
	$toppost->subject = ($extendedforum->type == "news") ? get_string("addanewtopic", "extendedforum") :
	get_string("addanewdiscussion", "extendedforum");
}

if (empty($post->edit)) {
	$post->edit = '';
}

if (empty($discussion->name)) {
	if (empty($discussion)) {
		$discussion = new object;
	}
	$discussion->name = $extendedforum->name;
}
if ($extendedforum->type == 'single') {
	// There is only one discussion thread for this extendedforum type. We should
	// not show the discussion name (same as extendedforum name in this case) in
	// the breadcrumbs.
	$strdiscussionname = '';
} else {
	// Show the discussion name in the breadcrumbs.
	$strdiscussionname = format_string($discussion->name).':';
}

$forcefocus = empty($reply) ? NULL : 'message';

$navlinks = array();
if ($post->parent) {
	//  $navlinks[] = array('name' => format_string($toppost->subject, true), 'link' => "discuss.php?d=$discussion->id", 'type' => 'title');
	$navlinks[] = array('name' => get_string('editing', 'extendedforum'), 'link' => '', 'type' => 'title');
} else {
	$navlinks[] = array('name' => format_string($toppost->subject), 'link' => '', 'type' => 'title');
}
$navigation = build_navigation($navlinks, $cm);

print_header("$course->shortname: $strdiscussionname ".
format_string($toppost->subject), $course->fullname,
$navigation, $mform_post->focus($forcefocus), "", true, "", navmenu($course, $cm));

 
// checkup
if (!empty($parent) && !extendedforum_user_can_see_post($extendedforum, $discussion, $post, null, $cm)) {
	error("You cannot reply to this post");
}
if (empty($parent) && empty($edit) && !extendedforum_user_can_post_discussion($extendedforum, $groupid, -1, $cm, $modcontext)) {
	error("You cannot start a new discussion in this extendedforum");
}

if ($extendedforum->type == 'qanda'
&& !has_capability('mod/extendedforum:viewqandawithoutposting', $modcontext)
&& !empty($discussion->id)
&& !extendedforum_user_has_posted($extendedforum->id, $discussion->id, $USER->id)) {
	notify(get_string('qandanotify','extendedforum'));
}

extendedforum_check_throttling($extendedforum, $cm);

if (isset($parent->discussion)) {
	if (! $discussion = get_record('extendedforum_discussions', 'id', $parent->discussion)) {
		error('This post is not part of a discussion!');
	}

	//$discussion->page = $page;
    $canupdateflag = has_capability('mod/extendedforum:updateflag', $modcontext)  ;
	extendedforum_print_post($parent, $discussion, $extendedforum, $cm, $course, false, false, false, null,
  "", "", null, true, null, $canupdateflag, 0, 0 , 0);
	if (empty($post->edit)) {
		if ($extendedforum->type != 'qanda' || extendedforum_user_can_see_discussion($extendedforum, $discussion, $modcontext)) {
			$extendedforumtracked = extendedforum_tp_is_tracked($extendedforum);

			$posts = extendedforum_get_all_discussion_posts($discussion->id, "created ASC", $extendedforumtracked, $extendedforum->hideauthor);

			extendedforum_print_posts_threaded($course, $cm, $extendedforum, $discussion, $parent, 0, false, false, $extendedforumtracked, $posts, false, 0 , 0 , 0);
		}
	}
	$heading = get_string("yourreply", "extendedforum");
} else {
	$extendedforum->intro = trim($extendedforum->intro);
	if (!empty($extendedforum->intro)) {
		print_box(format_text($extendedforum->intro), 'generalbox', 'intro');
	}
	if ($extendedforum->type == 'qanda') {
		$heading = get_string('yournewquestion', 'extendedforum');
	} else {
		$heading = get_string('yournewtopic', 'extendedforum');
	}
}

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
	$data->date = userdate($post->modified);
	if ($post->format == FORMAT_HTML) {
		$data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
		fullname($USER).'</a>';
		$post->message .= '<p>(<span class="edited">'.get_string('editedby', 'extendedforum', $data).'</span>)</p>';
	} else {
		$data->name = fullname($USER);
		$post->message .= "\n\n(".get_string('editedby', 'extendedforum', $data).')';
	}
}

//load data into form

if (extendedforum_is_subscribed($USER->id, $extendedforum->id)) {
	$subscribe = true;

} else if (extendedforum_user_has_posted($extendedforum->id, 0, $USER->id)) {
	$subscribe = false;

} else {
	// user not posted yet - use subscription default specified in profile
	$subscribe = !empty($USER->autosubscribe);
}

// HACK ALERT: this is very wrong, the defaults should be always initialized before calling $mform->get_data() !!!
$mform_post->set_data(array(    'general'=>$heading,
                                        'subject'=>$post->subject,
                                        'message'=>$post->message,
                                        'subscribe'=>$subscribe?1:0,
                                        'mailnow'=>!empty($post->mailnow),
                                        'userid'=>$post->userid,
                                        'parent'=>$post->parent,
                                        'discussion'=>$post->discussion,
                                        'course'=>$course->id)+

$page_params+

(isset($post->format)?array(
                                        'format'=>$post->format):
array())+

(isset($discussion->timestart)?array(
                                        'timestart'=>$discussion->timestart):
array())+

(isset($discussion->timeend)?array(
                                        'timeend'=>$discussion->timeend):
array())+

(isset($post->groupid)?array(
                                        'groupid'=>$post->groupid):
array())+

(isset($discussion->id)?
array('discussion'=>$discussion->id):
array()));


$mform_post->display();


print_footer($course);


?>
