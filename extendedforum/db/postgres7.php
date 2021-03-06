<?php // $Id: postgres7.php,v 1.52 2006/10/26 22:46:07 stronk7 Exp $

// THIS FILE IS DEPRECATED!  PLEASE DO NOT MAKE CHANGES TO IT!
//
// IT IS USED ONLY FOR UPGRADES FROM BEFORE MOODLE 1.7, ALL 
// LATER CHANGES SHOULD USE upgrade.php IN THIS DIRECTORY.

function extendedforum_upgrade($oldversion) {
// This function does anything necessary to upgrade
// older versions to match current functionality

  global $CFG;

  if ($oldversion < 2003042402) {
      execute_sql("INSERT INTO {$CFG->prefix}log_display (module, action, mtable, field) VALUES ('extendedforum', 'move discussion', 'extendedforum_discussions', 'name')");
  }

  if ($oldversion < 2003082500) {
      table_column("extendedforum", "", "assesstimestart", "integer", "10", "unsigned", "0", "", "assessed");
      table_column("extendedforum", "", "assesstimefinish", "integer", "10", "unsigned", "0", "", "assesstimestart");
  }

  if ($oldversion < 2003082502) {
      execute_sql("UPDATE {$CFG->prefix}extendedforum SET scale = (- scale)");
  }

  if ($oldversion < 2003100600) {
      table_column("extendedforum", "", "maxbytes", "integer", "10", "unsigned", "0", "", "scale");
  }

  if ($oldversion < 2004010100) {
      table_column("extendedforum", "", "assesspublic", "integer", "4", "unsigned", "0", "", "assessed");
  }

  if ($oldversion < 2004011404) {
      table_column("extendedforum_discussions", "", "userid", "integer", "10", "unsigned", "0", "", "firstpost");

      if ($discussions = get_records_sql("SELECT d.id, p.userid
                                            FROM {$CFG->prefix}extendedforum_discussions as d, 
                                                 {$CFG->prefix}extendedforum_posts as p
                                           WHERE d.firstpost = p.id")) {
          foreach ($discussions as $discussion) {
              update_record("extendedforum_discussions", $discussion);
          }
      }
  }
  if ($oldversion < 2004012200) {
      table_column("extendedforum_discussions", "", "groupid", "integer", "10", "unsigned", "0", "", "userid");
  }

  if ($oldversion < 2004020600) {
      table_column("extendedforum_discussions", "", "usermodified", "integer", "10", "unsigned", "0", "", "timemodified");
  }

  if ($oldversion < 2004050300) {
      table_column("extendedforum","","rsstype","integer","2", "unsigned", "0", "", "forcesubscribe");
      table_column("extendedforum","","rssarticles","integer","2", "unsigned", "0", "", "rsstype");
      set_config("extendedforum_enablerssfeeds",0);
  }

  if ($oldversion < 2004060100) {
      modify_database('', "CREATE TABLE prefix_extendedforum_queue (
                           id SERIAL PRIMARY KEY,
                           userid integer default 0 NOT NULL,
                           discussionid integer default 0 NOT NULL,
                           postid integer default 0 NOT NULL
                           );");
  }

  if ($oldversion < 2004070700) {    // This may be redoing it from STABLE but that's OK
      table_column("extendedforum_discussions", "groupid", "groupid", "integer", "10", "", "0", "");
  }


  if ($oldversion < 2004111700) {
      execute_sql(" DROP INDEX {$CFG->prefix}extendedforum_posts_parent_idx;",false);
      execute_sql(" DROP INDEX {$CFG->prefix}extendedforum_posts_discussion_idx;",false);
      execute_sql(" DROP INDEX {$CFG->prefix}extendedforum_posts_userid_idx;",false);
      execute_sql(" DROP INDEX {$CFG->prefix}extendedforum_discussions_extendedforum_idx;",false);
      execute_sql(" DROP INDEX {$CFG->prefix}extendedforum_discussions_userid_idx;",false);

      execute_sql(" CREATE INDEX {$CFG->prefix}extendedforum_posts_parent_idx ON {$CFG->prefix}extendedforum_posts (parent) ");
      execute_sql(" CREATE INDEX {$CFG->prefix}extendedforum_posts_discussion_idx ON {$CFG->prefix}extendedforum_posts (discussion) ");
      execute_sql(" CREATE INDEX {$CFG->prefix}extendedforum_posts_userid_idx ON {$CFG->prefix}extendedforum_posts (userid) ");
      execute_sql(" CREATE INDEX {$CFG->prefix}extendedforum_discussions_extendedforum_idx ON {$CFG->prefix}extendedforum_discussions (extendedforum) ");
      execute_sql(" CREATE INDEX {$CFG->prefix}extendedforum_discussions_userid_idx ON {$CFG->prefix}extendedforum_discussions (userid) ");
  }

  if ($oldversion < 2004111200) {
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_course_idx;",false);
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_queue_userid_idx;",false);
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_queue_discussion_idx;",false); 
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_queue_postid_idx;",false); 
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_ratings_userid_idx;",false); 
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_ratings_post_idx;",false);
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_subscriptions_userid_idx;",false);
      execute_sql("DROP INDEX {$CFG->prefix}extendedforum_subscriptions_extendedforum_idx;",false);

      modify_database('','CREATE INDEX prefix_extendedforum_course_idx ON prefix_extendedforum (course);');
      modify_database('','CREATE INDEX prefix_extendedforum_queue_userid_idx ON prefix_extendedforum_queue (userid);');
      modify_database('','CREATE INDEX prefix_extendedforum_queue_discussion_idx ON prefix_extendedforum_queue (discussionid);');
      modify_database('','CREATE INDEX prefix_extendedforum_queue_postid_idx ON prefix_extendedforum_queue (postid);');
      modify_database('','CREATE INDEX prefix_extendedforum_ratings_userid_idx ON prefix_extendedforum_ratings (userid);');
      modify_database('','CREATE INDEX prefix_extendedforum_ratings_post_idx ON prefix_extendedforum_ratings (post);');
      modify_database('','CREATE INDEX prefix_extendedforum_subscriptions_userid_idx ON prefix_extendedforum_subscriptions (userid);');
      modify_database('','CREATE INDEX prefix_extendedforum_subscriptions_extendedforum_idx ON prefix_extendedforum_subscriptions (extendedforum);');
  }

  if ($oldversion < 2005011500) {
      modify_database('','CREATE TABLE prefix_extendedforum_read (
                          id SERIAL PRIMARY KEY,
                          userid integer default 0 NOT NULL,
                          extendedforumid integer default 0 NOT NULL,
                          discussionid integer default 0 NOT NULL,
                          postid integer default 0 NOT NULL,
                          firstread integer default 0 NOT NULL,
                          lastread integer default 0 NOT NULL
                        );');

      modify_database('','CREATE INDEX prefix_extendedforum_user_extendedforum_idx ON prefix_extendedforum_read (userid, extendedforumid);');
      modify_database('','CREATE INDEX prefix_extendedforum_user_discussion_idx ON prefix_extendedforum_read (userid, discussionid);');
      modify_database('','CREATE INDEX prefix_extendedforum_user_post_idx ON prefix_extendedforum_read (userid, postid);');

      set_config('upgrade', 'extendedforumread');   // The upgrade of this table will be done later by admin/upgradeextendedforumread.php
  }

  if ($oldversion < 2005032900) {
      modify_database('','CREATE INDEX prefix_extendedforum_posts_created_idx ON prefix_extendedforum_posts (created);');
      modify_database('','CREATE INDEX prefix_extendedforum_posts_mailed_idx ON prefix_extendedforum_posts (mailed);');
  }

  if ($oldversion < 2005041100) { // replace wiki-like with markdown
      include_once( "$CFG->dirroot/lib/wiki_to_markdown.php" );
      $wtm = new WikiToMarkdown();
      $sql = "select course from {$CFG->prefix}extendedforum_discussions, {$CFG->prefix}extendedforum_posts ";
      $sql .=  "where {$CFG->prefix}extendedforum_posts.discussion = {$CFG->prefix}extendedforum_discussions.id ";
      $sql .=  "and {$CFG->prefix}extendedforum_posts.id = ";
      $wtm->update( 'extendedforum_posts','message','format',$sql );
  }

  if ($oldversion < 2005042300) { // Add tracking prefs table
      modify_database('','CREATE TABLE prefix_extendedforum_track_prefs (
                          id SERIAL PRIMARY KEY, 
                          userid integer default 0 NOT NULL,
                          extendedforumid integer default 0 NOT NULL
                        );');
  }

  if ($oldversion < 2005042600) {
      table_column('extendedforum','','trackingtype','integer','2', 'unsigned', '1', '', 'forcesubscribe');
      modify_database('','CREATE INDEX prefix_extendedforum_track_user_extendedforum_idx ON prefix_extendedforum_track_prefs (userid, extendedforumid);');
  }

  if ($oldversion < 2005042601) { // Mass cleanup of bad postgres upgrade scripts
      modify_database('','ALTER TABLE prefix_extendedforum ALTER trackingtype SET NOT NULL');
  }

  if ($oldversion < 2005111100) {
      table_column('extendedforum_discussions','','timestart','integer');
      table_column('extendedforum_discussions','','timeend','integer');
  }

  if ($oldversion < 2006011600) {
      notify('extendedforum_type does not exists, you can ignore and this will properly removed');
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum DROP CONSTRAINT {$CFG->prefix}extendedforum_type");
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum ADD CONSTRAINT {$CFG->prefix}extendedforum_type CHECK (type IN ('single','news','general','social','eachuser','teacher','qanda')) ");
  }

  if ($oldversion < 2006011601) {
      table_column('extendedforum','','warnafter');
      table_column('extendedforum','','blockafter');
      table_column('extendedforum','','blockperiod');
  }

  if ($oldversion < 2006011700) {
      table_column('extendedforum_posts','','mailnow','integer');
  }

  if ($oldversion < 2006011701) {
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum DROP CONSTRAINT {$CFG->prefix}extendedforum_type_check");
  }

  if ($oldversion < 2006011702) {
      execute_sql("INSERT INTO {$CFG->prefix}log_display (module, action, mtable, field) VALUES ('extendedforum', 'user report', 'user', 'firstname||\' \'||lastname')");
  }

  if ($oldversion < 2006081800) {
      // Upgrades for new roles and capabilities support.
      require_once($CFG->dirroot.'/mod/extendedforum/lib.php');

      $extendedforummod = get_record('modules', 'name', 'extendedforum');

      if ($extendedforums = get_records('extendedforum')) {

          if (!$teacherroles = get_roles_with_capability('moodle/legacy:teacher', CAP_ALLOW)) {
              notify('Default teacher role was not found. Roles and permissions '.
                     'for all your extendedforums will have to be manually set after '.
                     'this upgrade.');
          }
          if (!$studentroles = get_roles_with_capability('moodle/legacy:student', CAP_ALLOW)) {
              notify('Default student role was not found. Roles and permissions '.
                     'for all your extendedforums will have to be manually set after '.
                     'this upgrade.');
          }
          if (!$guestroles = get_roles_with_capability('moodle/legacy:guest', CAP_ALLOW)) {
              notify('Default guest role was not found. Roles and permissions '.
                     'for teacher extendedforums will have to be manually set after '.
                     'this upgrade.');
          }
          foreach ($extendedforums as $extendedforum) {
              if (!extendedforum_convert_to_roles($extendedforum, $extendedforummod->id, $teacherroles,
                                          $studentroles, $guestroles)) {
                  notify('Forum with id '.$extendedforum->id.' was not upgraded');
              }
          }
          // We need to rebuild all the course caches to refresh the state of
          // the extendedforum modules.
          rebuild_course_cache();
      
      } // End if.
      
      // Drop column extendedforum.open.
      modify_database('', 'ALTER TABLE prefix_extendedforum DROP COLUMN open;');

      // Drop column extendedforum.assesspublic.
      modify_database('', 'ALTER TABLE prefix_extendedforum DROP COLUMN assesspublic;');
  }
    
  if ($oldversion < 2006082700) {
      $sql = "UPDATE {$CFG->prefix}extendedforum_posts SET message = REPLACE(message, '".TRUSTTEXT."', '');";
      $likecond = sql_ilike()." '%".TRUSTTEXT."%'";
      while (true) {
          if (!count_records_select('extendedforum_posts', "message $likecond")) {
              break;
          }
          execute_sql($sql);
      }
  }

  //////  DO NOT ADD NEW THINGS HERE!!  USE upgrade.php and the lib/ddllib.php functions.

  return true;

}


?>
