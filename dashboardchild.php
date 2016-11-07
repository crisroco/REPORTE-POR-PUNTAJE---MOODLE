<?php

global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');

include('lib.php');

admin_externalpage_setup('dashblocksync');
$context = context_system::instance();
require_login();
require_capability('block/sync:config',$context);

$parent = required_param('parent', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$main = required_param('main', PARAM_INT);
$sync = $DB->get_record('sync_related',array('courseid'=>$courseid));


//CONSULTAS
$moduleP = $DB->get_records('course_modules',array("course"=> $parent));
$modulC = $DB->get_records('course_modules',array("course"=> $courseid));
$modulesP = $DB->get_records('sync_modules',array("main_id"=> $sync->main_id));
$modulesC = $DB->get_records('sync_modules_course',array("course_id"=>$sync->courseid),null,'module_id');
$childs =  $DB->get_records('sync_related',array('main_id'=>$main));


$moduleC = "SELECT sm.id as IDparent, smc.course_id as child, sm.module_id as modParent,  smc.module_id as modChild  FROM
         {sync_modules_course} smc 
         INNER JOIN  {sync_modules} sm ON sm.id = smc.smodule_id
         where smc.course_id IN (?)";

$syncchild = $DB->get_records_sql($moduleC,array($courseid));

$itemss = "SELECT sm.id, sm.module_id, cm.module, sm.main_id FROM {sync_modules} sm
	INNER JOIN {course_modules} cm ON sm.module_id = cm.id
	WHERE sm.main_id IN (?)	
	ORDER BY cm.module ASC, sm.module_id DESC ";
$course_modules = $DB->get_records_sql($itemss, array($main));

//imprimir solo en el padre
$prntonly = array();
$prntdeleted = array();

foreach ($modulesP as $key => $value) {
   //echo $value->id . '<br>';
   if (!in_array($value->id , array_keys($syncchild))) {
      
      $prntonly[$value->module_id] = $value->module_id;
   }
}

foreach ($prntonly as $key => $value) {
   if (!in_array($value , array_keys($moduleP))) {
      $prntdeleted[$value] = $value;
      unset($prntonly[$value]);
   }
}


$table = new html_table();
$table->head = array('Actividades sin sincronizar');

$modinfo = get_fast_modinfo($parent);
if ($prntonly == array()) {
	$activi = 'SIN ACTIVIDADES PENDIENTES DE SINCRIZACIÓN';
	$table->data[] = array($activi);
}else{
	foreach ($prntonly as $key => $value) {
	   
	   $cm = $modinfo->get_cm($value);
	 
	   $infomod = $DB->get_record('course_modules', array("id" => $value));
	  
	   //$mod = array_shift($infomod);
	   $activi = html_writer::tag('p', html_writer::empty_tag('img', array('src' => $cm->get_icon_url(),
	                'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .' ' . $cm->name, array('class' => 'create')) ;
	   $table->data[] = array($activi); 
	}
}
//FIN imprimir solo en el padre

//imprimir solo en el hijo

$chlonly = array_keys($modulC);
foreach ($chlonly as $key => $value) {
  if (in_array($value, array_keys($modulesC))) {
    unset($chlonly[$key]); 
  }
}

$table2 = new html_table();
$table2->head = array('Actividades creadas solo en el curso hijo');

$modinfo = get_fast_modinfo($courseid);

if ($chlonly == array()) {
	$activi = 'SIN ACTIVIDADES CREADAS';
	$table2->data[] = array($activi);
}else{
	foreach ($chlonly as $key => $value) {
	   
	   $cm = $modinfo->get_cm($value);
	 
	   $infomod = $DB->get_record('course_modules', array("id" => $value));
	  
	   //$mod = array_shift($infomod);
	   $activi = html_writer::tag('p', html_writer::empty_tag('img', array('src' => $cm->get_icon_url(),
	                'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .' ' . $cm->name, array('class' => '')) ;
	   $table2->data[] = array($activi); 
	}
}
//FIN imprimir solo en el hijo

//cursos actualizados en padre
$tmp_course = get_course($parent);
$modinfo = get_fast_modinfo($parent);
$table3 = new html_table();
$table3->head = array('Actividades actualizadas en el padre sin sincronizar');
foreach ($course_modules as $key => $value) {

        $exist = $DB->get_record('course_modules',array('id'=>$value->module_id) );
       
  if ($exist){
	$class = '';	
	$updates = 0;	
	
	foreach($childs as $c) { 
		$status = sync_check_status($value,$c->courseid);

		if(is_object($status)){
			switch ((int)$status->type) {
				case 1:
					$class = 'create';
				break;
				case 2:
					//Actualizar
					$updates++;
					$class = 'update';
				break;
				case 3:
					$tmpp = sync_check_deletes($value,$c->courseid);
					$modinfo = get_fast_modinfo($tmpp->course);
					$value->module_id = $tmpp->id;
					$class = 'delete';
				break;
			}
		}
		
	}

	if ($class !== 'update') {

		continue;
	}
			
	//echo $value->module_id .'<br>';
	$cm = $modinfo->get_cm($value->module_id);
	$modinfo = get_fast_modinfo($tmp_course);	

	$cm = $modinfo->get_cm($value->module_id);
	$modinfo = get_fast_modinfo($tmp_course);	


	$tm = new stdClass();
	$tm->id = $cm->id;
	$tm->modname = $cm->modname;
	$tm->name = $cm->name;
	$tm->instance = $cm->instance;
	$tm->module_id = $cm->id;
	$tm->main_id = $main;

	$act[] = $tm;

	$activi = html_writer::tag('p', html_writer::empty_tag('img', array('src' => $cm->get_icon_url(),
                'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .' ' . $cm->name, array('class' => $class)) ;
	$table3->data[] = array($activi);

  }
}
//cursos actualizados en padre

//IMPRIMIR PAGINA

$main_url = new moodle_url('/blocks/sync/dashboardchild.php',array('id'=>$parent));
$tmp_course = get_course($courseid);
$PAGE->set_url($main_url);
$title = 'Dashboard - '.  $tmp_course->fullname;
$PAGE->set_title($title);
$PAGE->set_heading($title);

print $OUTPUT->header();
print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/styles.css','rel'=>'stylesheet'));


   echo html_writer::table($table);
   echo html_writer::table($table2);
   echo html_writer::table($table3);


   
print $OUTPUT->footer();

