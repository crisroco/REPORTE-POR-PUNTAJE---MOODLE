<?php

global $DB, $PAGE, $OUTPUT;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');

include('lib.php');

$id = required_param('id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sync = $DB->get_record('sync_main',array('courseid'=>$courseid));
sync_main_modules($sync->courseid,$sync->id);

admin_externalpage_setup('dashblocksync');

$context = context_system::instance();
require_login();
require_capability('block/sync:config',$context);

$main_url = new moodle_url('/blocks/sync/dashboard.php',array('id'=>$id));

$tmp_course = get_course($courseid);
// obtener modulos!!!
$modules = $DB->get_records('course_modules',array("course"=> $courseid));

$k = 0;
$act = array();

$childs =  $DB->get_records('sync_related',array('main_id'=>$id));

$course_modules =  $DB->get_records('sync_modules',array('main_id'=>$id));
//echo '<pre>';
//print_r ($course_modules);

//echo'</pre>';

$modinfo = get_fast_modinfo($tmp_course);


$table_hijos = new html_table();
$table_hijos->head = array('Hijos','Sincronizado','');

$l = array();
    $courses = array();
    foreach($childs as $c){
    	$tmp = get_course($c->courseid);
    	$courses[] = $tmp;

    	$percent = sync_check_course($id,$c->courseid);

    	$table_hijos->data[] = array($tmp->fullname, generate_progressbar($percent['percent']), '');
      $l[] = html_writer::tag('p',$tmp->fullname);
    }

    $line = implode('', $l);

$out_mods = '';

$table = new html_table();
$table->head = array('Actividades','Hijos Sincornizados','Agregar', 'Actualizar' , 'Eliminar');


foreach ($course_modules as $key => $value) {
//$cm = $modinfo->get_cm($value->id);
	//$cm = get_coursemodule_from_id('mod_name', $value->main_id, 0, false, MUST_EXIST);
	$class = '';
	$cont_total = 0;
	$creates = 0;
	$updates = 0;
	$deletes = 0;
	foreach($childs as $c) { 
		$status = sync_check_status($value,$c->courseid);

		if(is_object($status)){
			switch ((int)$status->type) {
				case 1:
					//Crear
					$creates++;
					$class = 'create';
				break;
				case 2:
					//Actualizar
					$updates++;
					$class = 'update';
				break;
				case 3:
					//Borrar
				echo "<pre>";
				print_r($status);
				echo "</pre>";
					$deletes++;
					$tmpp = sync_check_deletes($value,$c->courseid);
					$modinfo = get_fast_modinfo($tmpp->course);
					$value->module_id = $tmpp->id;
					$class = 'delete';
				break;
			}
			//$cont_unit++;
		}

		$cont_total++;
	}

	//echo $value->module_id;
$cm = $modinfo->get_cm($value->module_id);
$modinfo = get_fast_modinfo($tmp_course);	


	$tm = new stdClass();
	$tm->id = $cm->id;
	$tm->modname = $cm->modname;
	$tm->name = $cm->name;
	$tm->instance = $cm->instance;
	$tm->module_id = $cm->id;
	$tm->main_id = $id;

	$act[] = $tm;

	$activi = html_writer::tag('p', html_writer::empty_tag('img', array('src' => $cm->get_icon_url(),
                'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .' ' . $cm->name, array('class' => $class)) ;
	//$table->data[] = array($activi, $cont_total, $creates,$updates,$deletes);
	$table->data[] = array($activi, generate_progressbar(calc_percent(
									$creates + $updates + $deletes, $cont_total)), $creates,$updates,$deletes);

	//$out_mods .= html_writer::tag('p', . $cm->name  . ' ' . $cont_unit . '/' . $cont_total, array('class' => $class));

}

$table_users = new html_table();
$table_users->head = array('Usuarios','Cursos Sincronizados','Fecha');

$user_logs = $DB->get_records('sync_user_history',  array('main_id' => $courseid));
//$resultado = array_unique($user_logs);

foreach ($user_logs as $value) {
	$courses = explode(',', $value->child_id);
	$out_courses = '';
	if(count($courses) >= 2){
		foreach ($courses as $val) {
			if($val != ''){
				$course = get_course($val);
				$out_courses .= html_writer::tag('p', '- ' . $course->fullname);
			}
		}
	}
	
	//gmdate("Y-m-d\TH:i:s\Z", $value->time_sync);


	$user =  $DB->get_record('user',  array('id' => $value->user_id));
	$userpicture = $OUTPUT->user_picture($user,array('size' => 70));
	$userurl = new moodle_url('/user/view.php', array('id' => $user->id));
	//echo html_writer::link($userurl, $userpicture . ' ' . fullname($user) );

	$table_users->data[] = array(html_writer::link($userurl, $userpicture . ' ' . fullname($user)),
								 $out_courses,
								 gmdate("Y-m-d H:i:s", $value->time_sync));
}





/*$cm = $modinfo->get_cm(14);
echo "<pre>";
	print_r($cm);
	echo "</pre>";*/



//obtener miduloss!!!!!

$PAGE->set_url($main_url);
$title = 'Dashboard - ' .  $tmp_course->fullname;
//$title = 'Dashboard - ';
$PAGE->set_title($title);
$PAGE->set_heading($title);
print $OUTPUT->header();
print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/styles.css','rel'=>'stylesheet'));

    //echo $line;

	echo html_writer::table($table_hijos);
	echo html_writer::table($table);
	echo html_writer::table($table_users);






print $OUTPUT->footer();
