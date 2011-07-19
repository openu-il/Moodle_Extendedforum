<?php  //$Id: upgrade.php,v 1.5.2.6 2009/05/04 08:11:15 stronk7 Exp $

// This file keeps track of upgrades to 
// the extendedforum module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_extendedforum_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one 
/// block of code similar to the next one. Please, delete 
/// this comment lines once this file start handling proper
/// upgrade code.

/// if ($result && $oldversion < YYYYMMDD00) { //New version in version.php
///     $result = result of "/lib/ddllib.php" function calls
/// }

    if ($result && $oldversion < 2007101000) {

    /// Define field timemodified to be added to extendedforum_queue
        $table = new XMLDBTable('extendedforum_queue');
        $field = new XMLDBField('timemodified');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'postid');

    /// Launch add field timemodified
        $result = $result && add_field($table, $field);
    }

//===== 1.9.0 upgrade line ======//

    if ($result and $oldversion < 2007101511) {
        notify('Processing extendedforum grades, this may take a while if there are many extendedforums...', 'notifysuccess');
        //MDL-13866 - send extendedforum ratins to gradebook again
        require_once($CFG->dirroot.'/mod/extendedforum/lib.php');
        // too much debug output
        $db->debug = false;
        extendedforum_update_grades();
        $db->debug = true;
    }

    if ($result && $oldversion < 2007101512) {

    /// Cleanup the extendedforum subscriptions
        notify('Removing stale extendedforum subscriptions', 'notifysuccess');

        $roles = get_roles_with_capability('moodle/course:view', CAP_ALLOW);
        $roles = array_keys($roles);
        $roles = implode(',', $roles);

        $sql = "SELECT fs.userid, f.id AS extendedforumid
                  FROM {$CFG->prefix}extendedforum f
                       JOIN {$CFG->prefix}course c                 ON c.id = f.course
                       JOIN {$CFG->prefix}context ctx              ON (ctx.instanceid = c.id AND ctx.contextlevel = ".CONTEXT_COURSE.")
                       JOIN {$CFG->prefix}extendedforum_subscriptions fs   ON fs.extendedforum = f.id
                       LEFT JOIN {$CFG->prefix}role_assignments ra ON (ra.contextid = ctx.id AND ra.userid = fs.userid AND ra.roleid IN ($roles))
                 WHERE ra.id IS NULL";

        if ($rs = get_recordset_sql($sql)) {
            $db->debug = false;
            while ($remove = rs_fetch_next_record($rs)) {
                delete_records('extendedforum_subscriptions', 'userid', $remove->userid, 'extendedforum', $remove->extendedforumid);
                echo '.';
            }
            $db->debug = true;
            rs_close($rs);
        }
    }
      // Multiattach stuff
     if ($result && $oldversion < 2008080501) {
 
     /// Define field multiattach to be added to extendedforum
         $table = new XMLDBTable('extendedforum');
         $field = new XMLDBField('multiattach');
         $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'maxbytes');
 
     /// Launch add field multiattach
         $result = $result && add_field($table, $field);
 
     /// Define field maxattach to be added to extendedforum
         $table = new XMLDBTable('extendedforum');
         $field = new XMLDBField('maxattach');
         $field->setAttributes(XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '5', 'multiattach');
 
     /// Launch add field maxattach
         $result = $result && add_field($table, $field);
     }
     
     // hide author stuff
     if ($result && $oldversion < 2010050207) {

    /// Define field hideauthor to be added to extendedforum
        $table = new XMLDBTable('extendedforum');
        $field = new XMLDBField('hideauthor');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'blockperiod');

    /// Launch add field hideauthor
        $result = $result && add_field($table, $field);
    }

    

    if ($result && $oldversion < 2007101513) {
        delete_records('extendedforum_ratings', 'post', 0); /// Clean existing wrong rates. MDL-18227
    }

    //add flag table to mark posts
    if ($result && $oldversion < 2010070500) {
      /// Define table extendedforum_flags to be created
        $table = new XMLDBTable('extendedforum_flags');

    /// Adding fields to table extendedforum_flags
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('postid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('flagged_date', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table extendedforum_flags
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for extendedforum_flags
        $result = $result && create_table($table);

    
    }

   //add index to extendedforum_flags
   if ($result && $oldversion < 2010070600) {

    /// Define index post_user (unique) to be added to extendedforum_flags
        $table = new XMLDBTable('extendedforum_flags');
        $index = new XMLDBIndex('post_user');
        $index->setAttributes(XMLDB_INDEX_UNIQUE, array('postid', 'userid'));

    /// Launch add index post_user
        $result = $result && add_index($table, $index);
      
   }                            
  if ($result && $oldversion < 2021070700) {

    /// Define field on_top to be added to extendedforum_discussions
        $table = new XMLDBTable('extendedforum_discussions');
        $field = new XMLDBField('on_top');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, null, null, 'timeend');

    /// Launch add field on_top
        $result = $result && add_field($table, $field);
    }
   if ($result && $oldversion < 2021070712) {

    /// Define field mark to be added to extendedforum_posts
        $table = new XMLDBTable('extendedforum_posts');
        $field = new XMLDBField('mark');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, null, null, 'mailnow');

    /// Launch add field mark
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2021070714) {

    /// Changing the default of field on_top on table extendedforum_discussions to 0
        $table = new XMLDBTable('extendedforum_discussions');
        $field = new XMLDBField('on_top');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, null, '0', 'timeend');

    /// Launch change of default for field on_top
        $result = $result && change_field_default($table, $field);
    }

    return $result;
}

?>
