<?php


require_once(dirname(__FILE__).'/../../config.php');
//require_once('locallib.php');
require_once('forms.php');


// Get course
//$categoryid = optional_param('categoryid',0,PARAM_INT);
//$section_course = optional_param('section_course',0,PARAM_INT);

//$values = array('categoryid'=>$categoryid,'section_course'=>1);

$url = new moodle_url('/report/reportpoints/reporte_feedback.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$context = context_system::instance();

require_login();
require_capability('report/reportpoints:view', $context);

$PAGE->set_context($context);

$filters = new reportpoints_form($url);
//$filters->set_data($values);


$title = get_string('pluginname','report_reportpoints');
$PAGE->requires->css('/report/reportpoints/assets/select2.css');
$PAGE->requires->css('/report/reportpoints/assets/style.css');

$PAGE->requires->js_call_amd('report_reportpoints/module', 'init');

$PAGE->set_title($title);
$PAGE->set_heading($title);


//Form processing and displaying is done here
if ($filters->is_cancelled()) {
  $returnurl = new moodle_url('/report/reportpoints/reporte_feedback.php');
  redirect($returnurl);
} else if ($data = $filters->get_data()) { 
   
   $export = '/report/reportpoints/reports_feedback.php?categoryid='.$data->categoria.'&section_course='.$data->section_course;
  $returnurl = new moodle_url($export);
  redirect($returnurl);
}


print $OUTPUT->header();
  $filters->display();
print $OUTPUT->footer();