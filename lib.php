<?php
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/blocks/sync/styles.css');
require_once("$CFG->libdir/externallib.php");
class core_course_external1 extends external_api {


        public static function duplicate_course1_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course to duplicate id'),
                'fullname' => new external_value(PARAM_TEXT, 'duplicated course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'duplicated course short name'),
                'categoryid' => new external_value(PARAM_INT, 'duplicated course category parent'),
                'visible' => new external_value(PARAM_INT, 'duplicated course visible, default to yes', VALUE_DEFAULT, 1),
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                                'name' => new external_value(PARAM_ALPHAEXT, 'The backup option name:
                                            "activities" (int) Include course activites (default to 1 that is equal to yes),
                                            "blocks" (int) Include course blocks (default to 1 that is equal to yes),
                                            "filters" (int) Include course filters  (default to 1 that is equal to yes),
                                            "users" (int) Include users (default to 0 that is equal to no),
                                            "role_assignments" (int) Include role assignments  (default to 0 that is equal to no),
                                            "comments" (int) Include user comments  (default to 0 that is equal to no),
                                            "userscompletion" (int) Include user course completion information  (default to 0 that is equal to no),
                                            "logs" (int) Include course logs  (default to 0 that is equal to no),
                                            "grade_histories" (int) Include histories  (default to 0 that is equal to no)'
                                            ),
                                'value' => new external_value(PARAM_RAW, 'the value for the option 1 (yes) or 0 (no)'
                            )
                        )
                    ), VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Duplicate a course
     *
     * @param int $courseid
     * @param string $fullname Duplicated course fullname
     * @param string $shortname Duplicated course shortname
     * @param int $categoryid Duplicated course parent category id
     * @param int $visible Duplicated course availability
     * @param array $options List of backup options
     * @return array New course info
     * @since Moodle 2.3
     */
    public static function duplicate_course1($newcourseid, $courseid, $fullname, $shortname, $categoryid, $visible = 1, $options = array()) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // Parameter validation.
        $params = self::validate_parameters(
                self::duplicate_course1_parameters(),
                array(
                      'courseid' => $courseid,
                      'fullname' => $fullname,
                      'shortname' => $shortname,
                      'categoryid' => $categoryid,
                      'visible' => $visible,
                      'options' => $options
                )
        );

        // Context validation.

        if (! ($course = $DB->get_record('course', array('id'=>$params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'error');
        }
       
        // Category where duplicated course is going to be created.
        $categorycontext = context_coursecat::instance($params['categoryid']);
        self::validate_context($categorycontext);

        // Course to be duplicated.
        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);

        $backupdefaults = array(
            'activities' => 1,
            'blocks' => 1,
            'filters' => 1,
            'users' => 0,
            'role_assignments' => 0,
            'comments' => 0,
            'userscompletion' => 0,
            'logs' => 0,
            'grade_histories' => 0
        );

        $backupsettings = array();
        // Check for backup and restore options.
        if (!empty($params['options'])) {
            foreach ($params['options'] as $option) {

                // Strict check for a correct value (allways 1 or 0, true or false).
                $value = clean_param($option['value'], PARAM_INT);

                if ($value !== 0 and $value !== 1) {
                    throw new moodle_exception('invalidextparam', 'webservice', '', $option['name']);
                }

                if (!isset($backupdefaults[$option['name']])) {
                    throw new moodle_exception('invalidextparam', 'webservice', '', $option['name']);
                }

                $backupsettings[$option['name']] = $value;
            }
        }

        // Capability checking.

        // The backup controller check for this currently, this may be redundant.
//        require_capability('moodle/course:create', $categorycontext);
        require_capability('moodle/restore:restorecourse', $coursecontext);
        require_capability('moodle/backup:backupcourse', $coursecontext);

        if (!empty($backupsettings['users'])) {
            require_capability('moodle/backup:userinfo', $coursecontext);
            require_capability('moodle/restore:userinfo', $categorycontext);
        }

        // Check if the shortname is used.
        if ($foundcourses = $DB->get_records('course', array('shortname'=>$shortname))) {
            foreach ($foundcourses as $foundcourse) {
                $foundcoursenames[] = $foundcourse->fullname;
            }

            $foundcoursenamestring = implode(',', $foundcoursenames);
           // throw new moodle_exception('shortnametaken', '', '', $foundcoursenamestring);
        }

        // Backup the course.
        
        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO, backup::MODE_SAMESITE, 2);
        $backupsettings['activities'] = 0;
        //print_r($backupsettings);
        //exit;
        foreach ($backupsettings as $name => $value) {
            $bc->get_plan()->get_setting($name)->set_value($value);
        }

        $backupid       = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();

        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];

        $bc->destroy();

        // Restore the backup immediately.

        // Check if we need to unzip the file because the backup temp dir does not contains backup files.
        if (!file_exists($backupbasepath . "/moodle_backup.xml")) {
            $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backupbasepath);
        }

        // Create new course.
        //$newcourseid = restore_dbops::create_new_course($params['fullname'], $params['shortname'], $params['categoryid']);
        
        $rc = new restore_controller($backupid, $newcourseid,
                backup::INTERACTIVE_NO, backup::MODE_SAMESITE, 2, backup::TARGET_NEW_COURSE);

        foreach ($backupsettings as $name => $value) {
            $setting = $rc->get_plan()->get_setting($name);
            if ($setting->get_status() == backup_setting::NOT_LOCKED) {
                $setting->set_value($value);
            }
        }

        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }

                $errorinfo = '';

                foreach ($precheckresults['errors'] as $error) {
                    $errorinfo .= $error;
                }

                if (array_key_exists('warnings', $precheckresults)) {
                    foreach ($precheckresults['warnings'] as $warning) {
                        $errorinfo .= $warning;
                    }
                }

                throw new moodle_exception('backupprecheckerrors', 'webservice', '', $errorinfo);
            }
        }

        $rc->execute_plan();
        $rc->destroy();
           //exit;
        $course = $DB->get_record('course', array('id' => $newcourseid), '*', MUST_EXIST);
        $course->fullname = $params['fullname'];
        $course->shortname = $params['shortname'];
        $course->visible = $params['visible'];

        // Set shortname and fullname back.
        $DB->update_record('course', $course);

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($backupbasepath);
        }

        // Delete the course backup file created by this WebService. Originally located in the course backups area.
        $file->delete();

        return array('id' => $course->id, 'shortname' => $course->shortname);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.3
     */
    public static function duplicate_course1_returns() {
        return new external_single_structure(
            array(
                'id'       => new external_value(PARAM_INT, 'course id'),
                'shortname' => new external_value(PARAM_TEXT, 'short name'),
            )
        );
    }
  }
function sync_main_modules($courseid = 0, $main_id){
  global $DB;

  if($courseid == 0){
    return FALSE;
  }

  $modules = $DB->get_records('course_modules',array("course"=> $courseid));
  $already = $DB->get_records('sync_modules',array("main_id"=>$main_id),null,'module_id,main_id');
  $k = 0;
  foreach ($modules as $m) {
    if($k != 0 || ($m->module != 9 && $k == 0)){ //Ignore News Forum
      if(!in_array($m->id, array_keys($already))){
        $DB->insert_record('sync_modules', array('main_id'=>$main_id, 'module_id'=>$m->id));
      }
    }
    $k++;
  }
}

function sync_check_status($module,$courseid){
  global $DB;
  $object  = false;
  $exists = $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
  $list = $DB->get_records_menu('modules',array(),null,'id,name');
  $entity = $DB->get_record('course_modules',array('id'=>$module->module_id));

  if($entity){
    $instance = $DB->get_record($list[$entity->module],array('id'=>$entity->instance));
  }

  if(!$exists){
    if($entity){
      $object = new stdClass();
      $object->message = html_writer::tag('p','Crear actividad: '. $instance->name);
      $object->type = 1;
      $object->module = $entity;
      $object->instance = $instance;
    }
  }else{
    $course_entity = $DB->get_record('course_modules',array('id'=>$exists->module_id));
    if($course_entity){
      $course_instance = $DB->get_record($list[$course_entity->module],array('id'=>$course_entity->instance));
    }

    //If the module still there
    if(isset($instance)){

      if(isset($course_instance)){

        if($instance->timemodified > $course_instance->timemodified || $entity->visible != $course_entity->visible){
          $object = new stdClass();
          $object->message = html_writer::tag('p','La actividad: '. $instance->name.' debe ser actualizada');
          $object->type = 2;
          $object->module = $entity;
          $object->instance = $instance;
        }

      }else{
        $object = new stdClass();
        $object->message = html_writer::tag('p','Crear actividad: '. $instance->name);
        $object->type = 1;
        $object->module = $entity;
        $object->instance = $instance;
      }

    }else{
      if(isset($course_instance)){
        $object = new stdClass();
        $object->message = html_writer::tag('p','La actividad: '. $course_instance->name.' debe ser eliminada');
        $object->type = 3;
        $object->module = $entity;
      }
    }

  }

  return $object;

}

function sync_check_course($mainid,$courseid){
  global $DB;
  //$output = array();
  $modules = $DB->get_records('sync_modules',  array('main_id' => $mainid));
  $cant_create = 0;
  $cant_update = 0;
  $cant_delete = 0;
  $cant_total = 0;
    $object = new stdClass();
    $object->message = '';
    $cant_total = count($modules);
  foreach ($modules as $module) {
    $instance = null;
    $exists = $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
    $list = $DB->get_records_menu('modules',array(),null,'id,name');
    $entity = $DB->get_record('course_modules',array('id'=>$module->module_id));

    if($entity){
      $instance = $DB->get_record($list[$entity->module],array('id'=>$entity->instance));
    }

    if(!$exists){
      if($entity){
        $cant_create++;
        $object->message .= html_writer::tag('p','Crear actividad: '. $instance->name);
      }
    }else{
      $course_entity = $DB->get_record('course_modules',array('id'=>$exists->module_id));
      if($course_entity){
        $course_instance = $DB->get_record($list[$course_entity->module],array('id'=>$course_entity->instance));
      }

      //If the module still there
      if(isset($instance)){

        if(isset($course_instance)){


          if($instance->timemodified > $course_instance->timemodified || $entity->visible != $course_entity->visible){
            $cant_update++;
            $object->message .= html_writer::tag('p','La actividad: '. $instance->name.' debe ser actualizada');
          }

        }else{
          $cant_create++;
          $object->message .= html_writer::tag('p','Crear actividad: '. $instance->name);
        }

      }else{

        if(isset($course_instance)){
          $cant_delete++;
          
          $object->message .= html_writer::tag('p','La actividad: '. $course_instance->name.' debe ser eliminada');

        }
      }

    }
    
  }
  //$output[] = $object;
  $percent = 100 - round(($cant_create+$cant_update+$cant_delete) / $cant_total * 100, 0);
  //$percent = (($cant_create+$cant_update+$cant_delete)/$cant_total) * 100;

  $output =array(
                  'creates' => $cant_create,
                  'updates' => $cant_update,
                  'deletes' => $cant_delete,
                  'total' => $cant_total,
                  'percent' => $percent,
                  );


  return $output;

}

function sync_check_deletes($module,$courseid){
  global $DB;
  $object  = false;


  $exists = $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
  $list = $DB->get_records_menu('modules',array(),null,'id,name');
  $entity = $DB->get_record('course_modules',array('id'=>$module->module_id));

  if($entity){
    $instance = $DB->get_record($list[$entity->module],array('id'=>$entity->instance));
  }

  if($exists){
    
    $course_entity = $DB->get_record('course_modules',array('id'=>$exists->module_id));

    if($course_entity){
      $course_instance = $DB->get_record($list[$course_entity->module],array('id'=>$course_entity->instance));
    }

    if(isset($course_instance)){
      $object = new stdClass();
      $object->message = html_writer::tag('p','La actividad: '. $course_instance->name.' debe ser eliminada');
      $object->type = 3;
      $object->id = $course_entity->id;
      $object->course = $course_entity->course;
      $object->module = $entity;
    }

  }

  return $object;
}

function generate_progressbar($percent){

    
  $progress = html_writer::tag('div',$percent . '%',array('class' => 'progress-bar progress-bar-striped active',
                                        'role' => 'progressbar',
                                        'aria-valuenow' => $percent, 
                                        'aria-valuemin' => '0',
                                        'aria-valuemax' => '100',
                                        'style' => 'width:' . $percent . '%'));
    $progressbar = html_writer::tag('div',$progress, array('class' => 'progress','id' => 'pb'));

    return $progressbar;
}

function calc_percent($cant,$cant_total){
  return 100 - round($cant / $cant_total * 100, 0);
}



function sync_create_module($object,$module,$courseid){
    global $DB;

    $newcmid = sync_restore_module($object->module->id,$courseid);

    $exists =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));

    if($exists){
      $exists->module_id = $newcmid;
      $DB->update_record('sync_modules_course',$exists);
    }else{

      $cmodule = new stdClass();
      $cmodule->module_id = $newcmid;
      $cmodule->smodule_id = $module->id;
      $cmodule->course_id = $courseid;
      $DB->insert_record('sync_modules_course',$cmodule);
    }

    if($object->module->module == 17){
      $context = context_module::instance($object->module->id);
      $files = $DB->get_records('files',array('contextid'=>$context->id));
      $file = array_shift($files);

      $new_context = context_module::instance($newcmid);

      $newfiles = $DB->get_records('files',array('contextid'=>$new_context->id));

      foreach($newfiles as $nfile){
        $nfile->userid = $file->userid;
        $DB->update_record('files',$nfile);
      }
    }

$output =  html_writer::tag('p','Actividad '.$object->instance->name.' creada');
return $output;
}



function sync_update_module($object,$module,$courseid){
  global $DB;

  $list = $DB->get_records_menu('modules',array(),null,'id,name');

  $course_module =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
  $course_entity = $DB->get_record('course_modules',array('id'=>$course_module->module_id));
  $course_instance = $DB->get_record($list[$course_entity->module],array('id'=>$course_entity->instance));
  $update = TRUE;
  switch ($list[$object->module->module]) {
    case 'resource':
      course_delete_module($course_module->module_id);
      $newcmid = sync_restore_module($object->module->id,$courseid);
      $context = context_module::instance($object->module->id);
      $files = $DB->get_records('files',array('contextid'=>$context->id));
      $file = array_shift($files);
      $new_context = context_module::instance($newcmid);

      $newfiles = $DB->get_records('files',array('contextid'=>$new_context->id));

      foreach($newfiles as $nfile){
        $nfile->userid = $file->userid;
        $DB->update_record('files',$nfile);
      }
      $exists =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
      $exists->module_id = $newcmid;
      $DB->update_record('sync_modules_course',$exists);
      break;
    case 'quiz':
      $attempts = $DB->get_records('quiz_attempts',array('quiz'=>$course_instance->id));
      if(!empty($attempts)){
        $update = FALSE;
        $output =  html_writer::tag('p','Actividad '.$course_instance->name.' no se ha actualizado porque ya tiene intentos.');
      }else{
        course_delete_module($course_module->module_id);
        $newcmid = sync_restore_module($object->module->id,$courseid);
        $exists =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
        $exists->module_id = $newcmid;
        $DB->update_record('sync_modules_course',$exists);
      }
      break;
    case 'forum':
      $attempts = $DB->get_records('forum_discussions',array('forum'=>$course_instance->id));
      if(!empty($attempts)){
        $update = FALSE;
        $output = html_writer::tag('p','Actividad '.$course_instance->name.' no se ha actualizado porque ya tiene intentos.');
        return true;
      }else{
        course_delete_module($course_module->module_id);
        $newcmid = sync_restore_module($object->module->id,$courseid);
        $exists =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
        $exists->module_id = $newcmid;
        $DB->update_record('sync_modules_course',$exists);
      }
        case 'assignment':
      $attempts = $DB->get_records('assign_submission',array('assignment'=>$course_instance->id));
      if(!empty($attempts)){
        $update = FALSE;
        $output =  html_writer::tag('p','Actividad '.$course_instance->name.' no se ha actualizado porque ya tiene intentos.');
      }else{
        course_delete_module($course_module->module_id);
        $newcmid = sync_restore_module($object->module->id,$courseid);
        $exists =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
        $exists->module_id = $newcmid;
        $DB->update_record('sync_modules_course',$exists);
      }
      break;
    default:
      $instance = (array)$object->instance;

      foreach($instance as $key => $value){
        if(in_array($key,array('id','course','timecreated','timemodified'))) continue;
        $course_instance->$key = $value;
      }

      $course_instance->timemodified = time();
      $DB->update_record($list[$object->module->module],$course_instance);
      break;
  }

  //reloading from DB
  $course_module =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));
  $course_entity = $DB->get_record('course_modules',array('id'=>$course_module->module_id));
  $course_instance = $DB->get_record($list[$course_entity->module],array('id'=>$course_entity->instance));


  $m = (array)$object->module;

  foreach($m as $field => $value){
    if(in_array($field,array('visible','visibleold','indent','showdescription','completionview','completion'))){
      $course_entity->$field = $value;
    }
  }



  $DB->update_record('course_modules',$course_entity);

  if($update){
    $output = html_writer::tag('p','Actividad '.$object->instance->name.' actualizada');
  }
return $output;

}

function sync_restore_module($moduleid,$courseid){
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    require_once($CFG->libdir . '/filelib.php');
    $cm = get_coursemodule_from_id(null,$moduleid);

    $a          = new stdClass();
    $a->modtype = get_string('modulename', $cm->modname);
    $a->modname = format_string($cm->name);

    if (!plugin_supports('mod', $cm->modname, FEATURE_BACKUP_MOODLE2)) {
        throw new moodle_exception('duplicatenosupport', 'error', '', $a);
    }

    // Backup the activity.

    $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);

    $backupid       = $bc->get_backupid();
    $backupbasepath = $bc->get_plan()->get_basepath();

    $bc->execute_plan();

    $bc->destroy();

    // Restore the backup immediately.

    $rc = new restore_controller($backupid, $courseid,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, backup::TARGET_CURRENT_ADDING);

    $cmcontext = context_module::instance($cm->id);
    if (!$rc->execute_precheck()) {
        $precheckresults = $rc->get_precheck_results();
        if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }
        }
    }

    $rc->execute_plan();

    $newcmid = null;
    $tasks = $rc->get_plan()->get_tasks();
    foreach ($tasks as $task) {
        if (is_subclass_of($task, 'restore_activity_task')) {
            if ($task->get_old_contextid() == $cmcontext->id) {
                $newcmid = $task->get_moduleid();
                break;
            }
        }
    }

    return $newcmid;
}

function sync_delete_module($object,$module,$courseid){
  global $DB;

  $course_module =  $DB->get_record('sync_modules_course',array('smodule_id'=>$module->id,'course_id'=>$courseid));

  $list = $DB->get_records_menu('modules',array(),null,'id,name');
  $entity = $DB->get_record('course_modules',array('id'=>$course_module->module_id));
  $instance = $DB->get_record($list[$entity->module],array('id'=>$entity->instance));
  $delete = TRUE;
  switch ($list[$entity->instance]) {
    case 'quiz':
      $attempts = $DB->get_records('quiz_attempts',array('quiz'=>$instance->id));
      if(!empty($attempts)){
        $delete = FALSE;
        print html_writer::tag('p','Actividad '.$instance->name.' no se ha eliminado porque ya tiene intentos.');
      }
      break;
    case 'assignment':
      $attempts = $DB->get_records('assignment_submissions',array('assignment'=>$instance->id));
      if(!empty($attempts)){
        $delete = FALSE;
        print html_writer::tag('p','Actividad '.$instance->name.' no se ha eliminado porque ya tiene intentos.');
      }
      break;
    case  'forum':
      $attempts = $DB->get_records('forum_discussions',array('forum'=>$instance->id));
      if(!empty($attempts)){
        $delete = FALSE;
        print html_writer::tag('p','Actividad '.$instance->name.' no se ha eliminado porque ya tiene intentos.');
      }
      break;
    default:
      break;
  }

  if($delete){
    course_delete_module($course_module->module_id);
    //$DB->delete_records('sync_modules', array('')); 188

    print html_writer::tag('p','Actividad '.$instance->name.' eliminada');
  }


}


function sync_get_sections($courseid){
  global $DB;
  $sections = $DB->get_records('course_sections',array('course'=>$courseid));
  $final_sections = array();

  foreach ($sections as $s) {
    $final_sections[$s->section] = $s;
  }

  return $final_sections;

}


function course_template_duplicate_course($newcourseid,$courseid, $fullname, $shortname, $categoryid, $visibility = 1, $enrolmentcopy = 0) {
    global $CFG, $DB, $USER;

    try {
        $transaction = $DB->start_delegated_transaction();

        // Duplicate the course.
        $options = array();
        if ($enrolmentcopy) {
            $value = 1;
        } else {
            $value = 0;
        }
        $options[] = array('name' => 'users', 'value' => $value);
        //$newcourse =core_course_external:: duplicate_course($courseid, $fullname, $shortname, $categoryid, $visibility, $options);
        $newcourse = core_course_external1::duplicate_course1($newcourseid,$courseid, $fullname, $shortname, $categoryid, $visibility, $options);

        // Get the new course object.
        $newcourse = get_course($newcourse['id']);

        // Author.
        $newcourse->author = $USER->id;

        update_course($newcourse);

        $transaction->allow_commit();

        return $newcourse;

    } catch (Exception $e) {
        //extra cleanup steps
        $transaction->rollback($e); // rethrows exception
    }
}


/**
 * Course external functions
 *
 * @package    core_course
 * @category   external
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.2
 */
