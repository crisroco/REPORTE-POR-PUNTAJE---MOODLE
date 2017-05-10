<?php


require_once(dirname(__FILE__).'/../../config.php');
require_once('locallib.php');
require_once('forms.php');

$categoria = optional_param('categoria',0,PARAM_INT);
$section_course = optional_param('section_course',0,PARAM_INT);

$values = array('category'=>$categoria,'section'=>$section_course);

$url = new moodle_url('/report/reportpoints/reporte_feedback.php', $values);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$context = context_system::instance();

require_login();
require_capability('report/reportpoints:view', $context);

$PAGE->set_context($context);

$filters = new reportpoints_form();
$filters->set_data($values);
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
} /*else if ($data = $filters->get_data()) { 
   
   $export = '/report/reportpoints/reports_feedback.php?categoryid='.$data->categoria.'&section_course='.$data->section_course;
  $returnurl = new moodle_url($export);
  redirect($returnurl);
}*/

$exporurl = new moodle_url('/report/reportpoints/reports_feedback.php');
print $OUTPUT->header();
   echo "<script src='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js'></script>";
   echo "<link rel='stylesheet' type='text/css' href='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css'>";
   $urll = $CFG->wwwroot.'/report/reportpoints/index.php';
   print html_writer::start_tag('div', array('class' => 'linkreprote'));
       print html_writer::link($urll, 'ir a Reporte de Participación',array('class' => 'lalal'));
   print html_writer::end_tag('div');
  $filters->display();


  if($data = $filters->get_data()){
    $hmtl = reporte_grafico($categoria,$section_course);

   
    
      print '<form action="'.$exporurl.'" class="formgraph">';
      print '<input type="hidden" value="'.$categoria.'" name="categoryid">';
      print '<input type="hidden" value="'.$section_course.'" name="section_course">';
       
        
        print '<div class="btnexcel">';
        print '<button class="btn btn-primary">Exportar Excel</button>';
        print '</div>';
        print $hmtl;
        print '<div class="btnexcel">';
        print '<button class="btn btn-primary">Exportar Excel</button>';
        print '</div>';

      print '</form>';
    
  }

print $OUTPUT->footer();
