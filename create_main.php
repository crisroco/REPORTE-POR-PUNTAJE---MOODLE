<?php
global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
include('forms.php');
include('lib.php');

admin_externalpage_setup('blocksync');
$context = context_system::instance();
require_login();
require_capability('block/sync:config',$context);

$main_url = new moodle_url('/blocks/sync/create_main.php');

$mform = new sync_create_main($main_url);

//Form processing and displaying is done here
if ($mform->is_cancelled()) {
  $returnurl = new moodle_url('/blocks/sync/admin.php');
  redirect($returnurl);
} else if ($data = $mform->get_data()) {

  

  $main_id = $DB->insert_record('sync_main', array('courseid'=>$data->id));

  $modules = $DB->get_records('course_modules',array("course"=> $data->id));

  $k = 0;
  foreach($modules as $m){
    if($k != 0 || ($m->module != 9 && $k == 0)){ //Ignore News Forum
      $DB->insert_record('sync_modules', array('main_id'=>$main_id, 'module_id'=>$m->id));
    }
    $k++;
  }


  foreach($data->courses as $c){
    if($c != $data->id){
      $DB->insert_record('sync_related', array('courseid'=>$c,'main_id'=>$main_id));  
    }
  }

  $returnurl = new moodle_url('/blocks/sync/admin.php');
  redirect($returnurl);
}


$PAGE->set_url($main_url);


$title = 'Creación Curso Padre - Hijo';
$PAGE->set_title($title);
print $OUTPUT->header();
print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/select2.css','rel'=>'stylesheet'));
$PAGE->requires->js_call_amd('block_sync/module', 'init');
    $mform->display();


    
print $OUTPUT->footer();

