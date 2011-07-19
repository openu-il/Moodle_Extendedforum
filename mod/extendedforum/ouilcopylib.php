<?php
//This php script contains all the stuff to copy
    //extendedforum mods

/**
 *
 * @global <type> $CFG
 * @param <type> $sourcemodule
 * @param <type> $targetcourse
 * @param <type> $uniquecode
 * @param <type> $sourcecourse
 * @return errorclass
 */
function extendedforum_copy_mod($sourcemodule,  $targetcourse, $uniquecode, $sourcecourse) {
        global $CFG;

          $errorclass = new errorclass();
         $errorclass->setstatus(1);

         if(!isset($sourcemodule->moduleid)){
             $errorclass->setmessage("module id is in correct");
             $errorclass->setstatus(0);

             return $errorclass;
         }
         //get record from working table
          $data = get_record('ouil_copycourse_instanceids','tablename','course_modules',
                  'sourceid' ,$sourcemodule->moduleid, 'copy_uniquecode', $uniquecode);
           if($data){

                $extendedforum = get_record('extendedforum', 'id',$sourcemodule->instance );
                $extendedforum->course =$targetcourse;

                $intro = text_field_update($extendedforum->intro, $targetcourse);
                $extendedforum->intro = $intro;

                 $newid = insert_record("extendedforum", addslashes_recursive($extendedforum));

                  //Do some output
            if (!defined('COPY_BATCH')) {
                echo "<li>".get_string("modulename","extendedforum")." \"".format_string(stripslashes($extendedforum->name),true)."\"</li>";
            }
            copy_flush(300);

            if ($newid) {
                //We have the newid, update copy_ids
                 //we'll be restoring all questions here.
                  $ouil_copycourse_instanceids = new object();
                  $ouil_copycourse_instanceids->tablename = "extendedforum";
                   $ouil_copycourse_instanceids->sourceid = $sourcemodule->instance;
                   $ouil_copycourse_instanceids->targetid =$newid;
                   $ouil_copycourse_instanceids->copy_uniquecode  = $uniquecode;

                 insert_record ('ouil_copycourse_instanceids',$ouil_copycourse_instanceids )  ;


                 // If extendedforum type is single, just recreate the initial discussion/post automatically
             
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
                    $discussionobj = extendedforum_add_discussion($sd, $sd->intro, $extendedforum);
                    //Now, mark the initial post of the discussion as mailed!
                  
                    if ($discussionobj) {
                        set_field ('extendedforum_posts','mailed', '1', 'discussion', $discussionobj->id);
                    }
                }


            } else {
               $errorclass->setstatus(0);
               $errorclass->setmessage("error inserting " . format_string(stripslashes($extendedforum->name),true));
            }
           }
          return $errorclass;

}
?>
