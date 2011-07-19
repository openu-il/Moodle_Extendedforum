<?php // $Id: mysql.php,v 1.57 2006/10/26 22:39:12 stronk7 Exp $

// THIS FILE IS DEPRECATED!  PLEASE DO NOT MAKE CHANGES TO IT!
//
// IT IS USED ONLY FOR UPGRADES FROM BEFORE MOODLE 1.7, ALL 
// LATER CHANGES SHOULD USE upgrade.php IN THIS DIRECTORY.

function extendedforum_upgrade($oldversion) {
// This function does anything necessary to upgrade
// older versions to match current functionality

  global $CFG, $db;

  if ($oldversion < 2002073008) {
    execute_sql("DELETE FROM modules WHERE name = 'discuss' ");
    execute_sql("ALTER TABLE `discuss` RENAME `extendedforum_discussions` ");
    execute_sql("ALTER TABLE `discuss_posts` RENAME `extendedforum_posts` ");
    execute_sql("ALTER TABLE `discuss_ratings` RENAME `extendedforum_ratings` ");
    execute_sql("ALTER TABLE `extendedforum` CHANGE `intro` `intro` TEXT NOT NULL ");
    execute_sql("ALTER TABLE `extendedforum` ADD `forcesubscribe` TINYINT(1) UNSIGNED DEFAULT '0' NOT NULL AFTER `assessed`");
    execute_sql("ALTER TABLE `extendedforum` CHANGE `type` `type` ENUM( 'single', 'news', 'social', 'general', 
                             'eachuser', 'teacher' ) DEFAULT 'general' NOT NULL ");
    execute_sql("ALTER TABLE `extendedforum_posts` CHANGE `discuss` `discussion` INT( 10 ) UNSIGNED DEFAULT '0' NOT NULL ");
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'add', 'extendedforum', 'name') ");
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'add discussion', 'extendedforum_discussions', 'name') ");
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'add post', 'extendedforum_posts', 'subject') ");
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'update post', 'extendedforum_posts', 'subject') ");
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'view discussion', 'extendedforum_discussions', 'name') ");
    execute_sql("DELETE FROM log_display WHERE module = 'discuss' ");
    execute_sql("UPDATE log SET action = 'view discussion' WHERE module = 'discuss' AND action = 'view' ");
    execute_sql("UPDATE log SET action = 'add discussion' WHERE module = 'discuss' AND action = 'add' ");
    execute_sql("UPDATE log SET module = 'extendedforum' WHERE module = 'discuss' ");
    notify("Renamed all the old discuss tables (now part of extendedforum) and created new extendedforum_types");
  }

  if ($oldversion < 2002080100) {
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'view subscribers', 'extendedforum', 'name') ");
    execute_sql("INSERT INTO log_display (module, action, mtable, field) VALUES ('extendedforum', 'update', 'extendedforum', 'name') ");
  }

  if ($oldversion < 2002082900) {
    execute_sql(" ALTER TABLE `extendedforum_posts` ADD `attachment` VARCHAR(100) NOT NULL AFTER `message` ");
  }

  if ($oldversion < 2002091000) {
    if (! execute_sql(" ALTER TABLE `extendedforum_posts` ADD `attachment` VARCHAR(100) NOT NULL AFTER `message` ")) {
      echo "<p>Don't worry about this error - your server already had this upgrade applied";
    }
  }

  if ($oldversion < 2002100300) {
      execute_sql(" ALTER TABLE `extendedforum` CHANGE `open` `open` TINYINT(2) UNSIGNED DEFAULT '2' NOT NULL ");
      execute_sql(" UPDATE `extendedforum` SET `open` = 2 WHERE `open` = 1 ");
      execute_sql(" UPDATE `extendedforum` SET `open` = 1 WHERE `open` = 0 ");
  }
  if ($oldversion < 2002101001) {
      execute_sql(" ALTER TABLE `extendedforum_posts` ADD `format` TINYINT(2) UNSIGNED DEFAULT '0' NOT NULL AFTER `message` ");
  }

  if ($oldversion < 2002122300) {
      execute_sql("ALTER TABLE `extendedforum_posts` CHANGE `user` `userid` INT(10) UNSIGNED DEFAULT '0' NOT NULL ");
      execute_sql("ALTER TABLE `extendedforum_ratings` CHANGE `user` `userid` INT(10) UNSIGNED DEFAULT '0' NOT NULL ");
      execute_sql("ALTER TABLE `extendedforum_subscriptions` CHANGE `user` `userid` INT(10) UNSIGNED DEFAULT '0' NOT NULL ");
  }

  if ($oldversion < 2003042402) {
      execute_sql("INSERT INTO {$CFG->prefix}log_display (module, action, mtable, field) VALUES ('extendedforum', 'move discussion', 'extendedforum_discussions', 'name')");
  }

  if ($oldversion < 2003081403) {
      table_column("extendedforum", "assessed", "assessed", "integer", "10", "unsigned", "0");
  }

  if ($oldversion < 2003082500) {
      table_column("extendedforum", "", "assesstimestart", "integer", "10", "unsigned", "0", "", "assessed");
      table_column("extendedforum", "", "assesstimefinish", "integer", "10", "unsigned", "0", "", "assesstimestart");
  }

  if ($oldversion < 2003082502) {
      table_column("extendedforum", "scale", "scale", "integer", "10", "", "0");
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

  if ($oldversion < 2004013000) {
      table_column("extendedforum_posts", "mailed", "mailed", "tinyint", "2");
  }

  if ($oldversion < 2004020600) {
      table_column("extendedforum_discussions", "", "usermodified", "integer", "10", "unsigned", "0", "", "timemodified");
  }

  if ($oldversion < 2004050300) {
      table_column("extendedforum","","rsstype","tinyint","2", "unsigned", "0", "", "forcesubscribe");
      table_column("extendedforum","","rssarticles","tinyint","2", "unsigned", "0", "", "rsstype");
      set_config("extendedforum_enablerssfeeds",0);
  }

  if ($oldversion < 2004060100) {
      modify_database('', "CREATE TABLE `prefix_extendedforum_queue` (
                                `id` int(11) unsigned NOT NULL auto_increment,
                                `userid` int(11) unsigned default 0 NOT NULL,
                                `discussionid` int(11) unsigned default 0 NOT NULL,
                                `postid` int(11) unsigned default 0 NOT NULL,
                                PRIMARY KEY  (`id`),
                                KEY `user` (userid),
                                KEY `post` (postid)
                              ) TYPE=MyISAM COMMENT='For keeping track of posts that will be mailed in digest form';");
  }

  if ($oldversion < 2004070700) {    // This may be redoing it from STABLE but that's OK
      table_column("extendedforum_discussions", "groupid", "groupid", "integer", "10", "", "0", "");
  }
  
  if ($oldversion < 2004111700) {
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_posts` DROP INDEX {$CFG->prefix}extendedforum_posts_parent_idx;",false);
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_posts` DROP INDEX {$CFG->prefix}extendedforum_posts_discussion_idx;",false);
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_posts` DROP INDEX {$CFG->prefix}extendedforum_posts_userid_idx;",false);
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_discussions` DROP INDEX {$CFG->prefix}extendedforum_discussions_extendedforum_idx;",false); 
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_discussions` DROP INDEX {$CFG->prefix}extendedforum_discussions_userid_idx;",false);

      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_posts` ADD INDEX {$CFG->prefix}extendedforum_posts_parent_idx (parent) ");
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_posts` ADD INDEX {$CFG->prefix}extendedforum_posts_discussion_idx (discussion) ");
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_posts` ADD INDEX {$CFG->prefix}extendedforum_posts_userid_idx (userid) ");
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_discussions` ADD INDEX {$CFG->prefix}extendedforum_discussions_extendedforum_idx (extendedforum) ");
      execute_sql(" ALTER TABLE `{$CFG->prefix}extendedforum_discussions` ADD INDEX {$CFG->prefix}extendedforum_discussions_userid_idx (userid) ");
  }

  if ($oldversion < 2004111700) {
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum DROP INDEX course;",false);
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum_ratings DROP INDEX userid;",false);
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum_ratings DROP INDEX post;",false);
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum_subscriptions DROP INDEX userid;",false);
      execute_sql("ALTER TABLE {$CFG->prefix}extendedforum_subscriptions DROP INDEX extendedforum;",false);

      modify_database('','ALTER TABLE prefix_extendedforum ADD INDEX course (course);');
      modify_database('','ALTER TABLE prefix_extendedforum_ratings ADD INDEX userid (userid);');
      modify_database('','ALTER TABLE prefix_extendedforum_ratings ADD INDEX post (post);');
      modify_database('','ALTER TABLE prefix_extendedforum_subscriptions ADD INDEX userid (userid);');
      modify_database('','ALTER TABLE prefix_extendedforum_subscriptions ADD INDEX extendedforum (extendedforum);');
  }

  if ($oldversion < 2005011500) {
      modify_database('','CREATE TABLE prefix_extendedforum_read (
                  `id` int(10) unsigned NOT NULL auto_increment, 
                  `userid` int(10) NOT NULL default \'0\',
                  `extendedforumid` int(10) NOT NULL default \'0\',
                  `discussionid` int(10) NOT NULL default \'0\',
                  `postid` int(10) NOT NULL default \'0\',
                  `firstread` int(10) NOT NULL default \'0\',
                  `lastread` int(10) NOT NULL default \'0\',
                  PRIMARY KEY  (`id`),
                  KEY `prefix_extendedforum_user_extendedforum_idx` (`userid`,`extendedforumid`),
                  KEY `prefix_extendedforum_user_discussion_idx` (`userid`,`discussionid`),
                  KEY `prefix_extendedforum_user_post_idx` (`userid`,`postid`)
                  ) COMMENT=\'Tracks each users read posts\';');

      set_config('upgrade', 'extendedforumread');   // The upgrade of this table will be done later by admin/upgradeextendedforumread.php
  }

  if ($oldversion < 2005032900) {
      modify_database('','ALTER TABLE prefix_extendedforum_posts ADD INDEX prefix_form_posts_created_idx (created);');
      modify_database('','ALTER TABLE prefix_extendedforum_posts ADD INDEX prefix_form_posts_mailed_idx (mailed);');
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
                  `id` int(10) unsigned NOT NULL auto_increment, 
                  `userid` int(10) NOT NULL default \'0\',
                  `extendedforumid` int(10) NOT NULL default \'0\',
                  PRIMARY KEY  (`id`),
                  KEY `user_extendedforum_idx` (`userid`,`extendedforumid`)
                  ) COMMENT=\'Tracks each users untracked extendedforums.\';');
  }

  if ($oldversion < 2005042500) {
      table_column('extendedforum','','trackingtype','tinyint','2', 'unsigned', '1', '', 'forcesubscribe');
  }

  if ($oldversion < 2005111100) {
      table_column('extendedforum_discussions','','timestart','integer');
      table_column('extendedforum_discussions','','timeend','integer');
  }
  
  if ($oldversion < 2006011600) {
      execute_sql("alter table ".$CFG->prefix."extendedforum change column type type enum('single','news','general','social','eachuser','teacher','qanda') not null default 'general'");
  }

  if ($oldversion < 2006011601) {
      table_column('extendedforum','','warnafter');
      table_column('extendedforum','','blockafter');
      table_column('extendedforum','','blockperiod');
  }

  if ($oldversion < 2006011700) {
      table_column('extendedforum_posts','','mailnow','integer');
  }
  
  if ($oldversion < 2006011702) {
      execute_sql("INSERT INTO {$CFG->prefix}log_display (module, action, mtable, field) VALUES ('extendedforum', 'user report', 'user', 'CONCAT(firstname,\' \',lastname)')");
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
          include_once( "$CFG->dirroot/course/lib.php" );
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
