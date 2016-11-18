<?php

global $DB, $PAGE, $OUTPUT, $CFG;

require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');

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
//$modules = $DB->get_records('course_modules',array("course"=> $courseid));;

$childs =  $DB->get_records('sync_related',array('main_id'=>$id));
$modinfo = get_fast_modinfo($tmp_course);

//===================Secciones curso padre=======================
$section ="SELECT cs.id, c.id as course, cs.section FROM {course_sections} cs
         INNER JOIN {course} c ON c.id = cs.course
         where c.id IN (?)";   
$sections = $DB->get_records_sql($section, array($_GET['courseid']));
$secciones = array();
$secont = 0;
$prntonly = array(); 

foreach ($sections as $llave => $valor) {  
   
   //$course_modules =  $DB->get_records('sync_modules',array('main_id'=>$id));
   $itemss = "SELECT sm.id, sm.module_id, cm.module, sm.main_id, cs.section FROM {sync_modules} sm
      INNER JOIN {course_modules} cm ON sm.module_id = cm.id
       INNER JOIN {course_sections} cs ON cs.id = cm.section
      WHERE sm.main_id IN (?) and cs.section IN (?)
      ORDER BY cs.section ASC, cm.module ASC ";
   $course_modules = $DB->get_records_sql($itemss, array($id,$valor->section));

   if ($course_modules == array()) {
      continue;
   }

   $table = new html_table();
   $table->head = array('Actividades','Hijos Sincornizados');
   

   foreach ($course_modules as $key => $value) {

      $exist = $DB->get_record('course_modules',array('id'=>$value->module_id));
          
      if ($exist){
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
                  //echo "<pre>";print_r($status);echo "</pre>";
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
         if ($class != '') {
            //$modinfo = get_fast_modinfo($tmp_course);
            $tm = new stdClass();
            $tm->id = $cm->id;
            $tm->modname = $cm->modname;
            $tm->name = $cm->name;
            $tm->instance = $cm->instance;
            $tm->section = $cm->sectionnum;
            $tm->module_id = $cm->id;
            $tm->status = $class;
            $tm->main_id = $id;
            array_push($prntonly, $tm);           
          
         }
         




         $activi = html_writer::tag('p', html_writer::empty_tag('img', array('src' => $cm->get_icon_url(),
                      'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .' ' . $cm->name, array('class' => $class)) ;
         $table->data[] = array($activi, generate_progressbar(calc_percent(
                                 $creates + $updates + $deletes, $cont_total)));
      }
    
   }
   $outs = html_writer::start_tag('div', array('class' => 'panel-group sctn'));
   $outs .= html_writer::start_tag('div', array('class' => 'panel panel-default'));
      $outs .= html_writer::start_tag('div', array('class' => 'panel-heading'));
         $outs .= html_writer::start_tag('h4', array('class' => 'panel-title'));
            $outs .= html_writer::start_tag('div', array('class' => 'collapsable', 'target' => '#section'.$secont));
               $outs .= html_writer::start_tag('a', array('class' => 'username'));
                  $outs .=  get_string('section', 'block_sync').' '.$valor->section;
               $outs .= html_writer::end_tag('a'); 
               
            $outs .= html_writer::end_tag('div');
         $outs .= html_writer::end_tag('h4');
      $outs .= html_writer::end_tag('div');
      $outs .= '<div id="section'.$secont.'" class="panel-collapse">

                  <div class="panel-body">';


               $outs .= html_writer::table($table);
            $outs .= html_writer::end_tag('div');
         $outs .= html_writer::end_tag('div');
      $outs .= html_writer::end_tag('div');
   $outs .= html_writer::end_tag('div');
   array_push($secciones, $outs);
   $secont++;
}
//==================FIN secciones curso padre=======================

//===============Tabla de Datos================


$curso = "SELECT suh.id, suh.main_id, suh.child_id FROM {sync_user_history} suh
         WHERE suh.main_id in (?)
         ORDER BY suh.main_id ASC, suh.time_sync DESC";
$cursos = $DB->get_records_sql($curso,array($_GET['courseid']));

$synctimes = count($cursos);

$temp = array_shift($cursos);
$cursos = array();
array_push($cursos, $temp);
$ids = array();

foreach ($cursos as $key => $value) {
   $ids[$value->main_id] =  $value->main_id;
   $child = explode(',', $value->child_id);
   array_pop($child);
   foreach ($child as $value) {
      $ids[$value] =  $value;
   }
}
$cont = 0;
$crss = array();

foreach ($ids as $key => $value) {
   $coord = '';
   $mdsec = '';
   $coordinador = "SELECT CONCAT(u.firstname,' ', u.lastname) as coordinador FROM {course} c
               INNER JOIN {context} ctx ON ctx.instanceid = c.id
               INNER JOIN {role_assignments} ra ON ctx.id = ra.contextid
               INNER JOIN {role} r ON r.id = ra.roleid
               INNER JOIN {user} u ON u.id = ra.userid
               WHERE r.id = 3 and c.id IN (?)";
   $coordinadores = $DB->get_records_sql($coordinador, array($value));           
   foreach ($coordinadores as $ke => $valu) {
      $coord = $valu->coordinador;     
   }
   $dato = "SELECT c.id, c.shortname,  COUNT(cs.section) as sections, c.format as formato
        FROM {course} c 
        INNER JOIN {course_sections} cs ON c.id = cs.course
        where c.id IN (?) 
        GROUP BY c.shortname";

   $datos = $DB->get_records_sql($dato, array($value));
     
   foreach ($datos as $key => $value) {
      $value->coordinador = $coord;
      $percent = sync_check_course($id,$value->id);

      if ($value->id == $_GET['courseid']) {
         $crs = 'Padre';
         $prgrs = '';
      }else{
         $crs = 'Hijo ' . $cont;
         $prgrs = generate_progressbar($percent['percent']);
         
         //solo en hijo         
         $moduC = "SELECT cm.id, m.name as modname, cs.section, cm.course FROM {course_modules} cm
                     INNER JOIN {modules} m ON m.id = cm.module
                     INNER JOIN {course_sections} cs ON cs.id = cm.section
                     WHERE cm.course IN (?)
                     ORDER BY cs.section ASC, cm.module ASC";
         $modulC = $DB->get_records_sql($moduC, array($value->id));
         
         $modulesC = $DB->get_records('sync_modules_course',array("course_id"=>$value->id),null,'module_id');

           
         $chlonly = array_keys($modulC);
         foreach ($chlonly as $key => $chld) {
           if (in_array($chld, array_keys($modulesC))) {
             unset($modulC[$chld]);            
           }
         }


            
         //FIN solo en hijo
         
         $sec = "SELECT cs.section FROM {course_sections} cs where cs.course IN (?)";
         $secs = $DB->get_records_sql($sec, array($value->id));

         foreach ($secs as $key => $sec) {

            $mdsec .= '<div class="collapsable" target="#parnt'.$value->id.$sec->section.'">
                        <a class="username">seccion '.$sec->section.'</a>
                       </div> ';
            $contm = 0;
            $tmpn = '';
            $tmpp = array();
            foreach ($prntonly as $key => $prnt) {
               
               if ($sec->section == $prnt->section) {
                  $contm++;
                  if ($prnt->modname != $tmpn) {
                     $contm = 1;
                  }                  
                  $tmpp[$prnt->modname]= $contm;
                  $tmpn = $prnt->modname;
              }
            }
            $contm = 0;
            $tmpn = '';
            $tmpc = array();
            foreach ($modulC as $key => $chld) {
               
               if ($sec->section == $chld->section) {
                  $contm++;
                  if ($chld->modname != $tmpn) {
                     $contm = 1;
                  }                  
                  $tmpc[$chld->modname]= $contm;
                  $tmpn = $chld->modname;
              }
            }


            $mdsec .= '<div class="unsync" id="parnt'.$value->id.$sec->section.'">';
            if ($tmpp == array() && $tmpc == array()) {
              $mdsec .= '<strong><h5>Modulos correctos</h5></strong>';
            }

            if ($tmpp != array()) {

              $mdsec .= '<h5>Modulos sin sincronizar</h5>
                        <ul class="list-group">
                           <li class="list-group-item" ><strong>Módulo</strong> <span class="badge">Cantidad</span></li>';      
               foreach ($tmpp as $name => $md) {
                  $mdsec .= '<li class="list-group-item">'.get_string($name, 'block_sync').'<span class="badge list">'. $md.'</span></li>';
               }
            }

            $mdsec .= ' </ul>';

            if ($tmpc != array()) {

               $mdsec .= '<h5>Modulos creados en curso hijo</h5>
                           <ul class="list-group">
                           <li class="list-group-item"><strong>Módulo</strong> <span class="badge">Cantidad</span></li>';
               foreach ($tmpc as $name => $md) {
                  $mdsec .= '<li class="list-group-item">'.get_string($name, 'block_sync').'<span class="badge list">'. $md.'</span></li>';
               }
            }

            $mdsec .= '</div>';
         }

      }

      $table_datos = new html_table();
      $table_datos->head = array('Nombre','N de secciones','Formato', 'Coordinador','Sincronizado');
      $table_datos->data[] = array( $value->shortname, $value->sections,get_string($value->formato, 'block_sync'), $value->coordinador,$prgrs);

      $dts = html_writer::start_tag('div', array('class' => 'panel-group'));
      $dts .= html_writer::start_tag('div', array('class' => 'panel panel-default'));
         $dts .= html_writer::start_tag('div', array('class' => 'panel-heading'));
            $dts .= html_writer::start_tag('h4', array('class' => 'panel-title'));
               $dts .= html_writer::start_tag('div', array('class' => 'collapsable', 'target' => '#course'.$value->id));
                  $dts .= html_writer::start_tag('a', array('class' => 'username'));
                     $dts .=  'Curso '.$crs. ' - '.$value->shortname;
                  $dts .= html_writer::end_tag('a');                   
               $dts .= html_writer::end_tag('div');
            $dts .= html_writer::end_tag('h4');
         $dts .= html_writer::end_tag('div');
         $dts .= '<div id="course'.$value->id.'" class="panel-collapse">
                     <div class="panel-body datos">';
                  $dts .= html_writer::table($table_datos);
                  $dts .= $mdsec;
               $dts .= html_writer::end_tag('div');
            $dts .= html_writer::end_tag('div');
         $dts .= html_writer::end_tag('div');
      $dts .= html_writer::end_tag('div'); 
      array_push($crss, $dts);     
   
      $cont++;
   }
}
//==============FIN Tabla de Datos============

//==============usuarios que ralizaron sincronización==============
$syncuser = "SELECT suh.user_id FROM {sync_user_history} suh WHERE suh.main_id IN (?) group by suh.user_id";
$syncusers = $DB->get_records_sql($syncuser,array($courseid));
$userdata = array();

foreach ($syncusers as $key => $value) {
$cont = 0;
$table_users = new html_table();
$table_users->head = array('Cursos Sincronizados','Fecha', '# Sincronización');

   $user_logs = $DB->get_records('sync_user_history',  array('main_id' => $courseid, 'user_id' => $value->user_id)); 
   $usuario = $DB->get_record('user',  array('id' => $value->user_id));
   $userpicture = $OUTPUT->user_picture($usuario,array('size' => 50));
   $userurl = new moodle_url('/user/view.php', array('id' => $usuario->id));

   foreach ($user_logs as $values) {
      $cont++;
      $courses = explode(',', $values->child_id);
      $out_courses = '';
      if(count($courses) >= 2){
         foreach ($courses as $val) {
            if($val != ''){
               $course = get_course($val);
               $out_courses .= html_writer::tag('p', '- ' . $course->fullname);
            }
         }
      }
      $table_users->data[] = array($out_courses,
                         gmdate("Y-m-d H:i:s", $values->time_sync), $cont);      
   }


   $out = html_writer::start_tag('div', array('class' => 'panel-group'));
      $out .= html_writer::start_tag('div', array('class' => 'panel panel-default'));
         $out .= html_writer::start_tag('div', array('class' => 'panel-heading'));
            $out .= html_writer::start_tag('h4', array('class' => 'panel-title'));
               $out .= html_writer::start_tag('div', array('class' => 'collapsable', 'target' => '#collapse'.$value->user_id));     
                  $out .= html_writer::link($userurl, $userpicture);
                  $out .= html_writer::start_tag('a', array('class' => 'username'));
                     $out .=  fullname($usuario);
                  $out .= html_writer::end_tag('a');  
                  
               $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('h4');
         $out .= html_writer::end_tag('div');
         $out .= '<div id="collapse'.$value->user_id.'" class="panel-collapse">

                     <div class="panel-body">';


                  $out .= html_writer::table($table_users);
               $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('div');
         $out .= html_writer::end_tag('div');
      $out .= html_writer::end_tag('div');

      array_push($userdata, $out);
}      
// ==============FIN usuarios que ralizaron sincronización==============

//==============leyenda==============
$lgd = html_writer::start_tag('div', array('class' => 'legend'));
	$lgd .= html_writer::start_tag('ul');
		$lgd .= html_writer::start_tag('li', array('class' => 'finished'));
			 $lgd .= html_writer::tag('span', '',array('class'=> 'color' ));
			 $lgd .= html_writer::tag('span', 'Sincronizado');
		$lgd .= html_writer::end_tag('li');
		$lgd .= html_writer::start_tag('li', array('class' => 'in-progress'));
			 $lgd .= html_writer::tag('span', '',array('class'=> 'color' ));
			 $lgd .= html_writer::tag('span', 'Sin sincronizar');
		$lgd .= html_writer::end_tag('li');
		$lgd .= html_writer::start_tag('li', array('class' => 'update'));
			 $lgd .= html_writer::tag('span', '',array('class'=> 'color' ));
			 $lgd .= html_writer::tag('span', 'Actualizado');
		/*$lgd .= html_writer::end_tag('li');
		$lgd .= html_writer::start_tag('li', array('class' => 'deleted'));
			 $lgd .= html_writer::tag('span', '',array('class'=> 'color' ));
			 $lgd .= html_writer::tag('span', 'Eliminado');
		$lgd .= html_writer::end_tag('li');*/
	$lgd .= html_writer::end_tag('ul');
$lgd .= html_writer::end_tag('div'); 
//==============FIN leyenda==============

//==============IMPRIMIR PAGINA==============
$PAGE->set_url($main_url);
$title = 'Dashboard - ' .  $tmp_course->fullname;
//$title = 'Dashboard - ';
$PAGE->set_title($title);
$PAGE->set_heading($title);
print $OUTPUT->header();
print html_writer::tag('link','',array('href'=>$CFG->wwwroot.'/blocks/sync/assets/css/styles.css','rel'=>'stylesheet'));

   echo '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script> ';
	echo '<script type="text/javascript" src="format.js"></script>';

   echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>';
   
   echo  html_writer::start_tag('div', array('class' => 'cursos'));
      echo html_writer::tag('h3','Cursos'); 
       foreach ($crss as $key => $value) {
         echo $value;
       }
   echo html_writer::end_tag('div'); 
   echo  html_writer::start_tag('div', array('class' => 'sectn'));
      echo html_writer::tag('h3','Secciones Curso padre');
        echo $lgd;
        foreach ($secciones as $key => $value) {
           echo $value;
        }
   echo html_writer::end_tag('div');   
   echo  html_writer::start_tag('div', array('class' => 'user'));
      echo html_writer::tag('h3','Usuarios ');
      foreach ($userdata as $key => $value) {
         echo $value;
      }
   echo html_writer::end_tag('div');    
 


print $OUTPUT->footer();



/*foreach ($user_logs as $value) {
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

   $cont++;
   $user =  $DB->get_record('user',  array('id' => $value->user_id));
   $userpicture = $OUTPUT->user_picture($user,array('size' => 70));
   $userurl = new moodle_url('/user/view.php', array('id' => $user->id));
   //echo html_writer::link($userurl, $userpicture . ' ' . fullname($user) );

   $table_users->data[] = array(html_writer::link($userurl, $userpicture . ' ' . fullname($user)),
                         $out_courses,
                         gmdate("Y-m-d H:i:s", $value->time_sync), $cont);
}*/

/*echo '<div class="panel-group">
        <div class="panel panel-default">
          <div class="panel-heading">
            <h4 class="panel-title">
              <div data-toggle="collapse" href="#collapse1">
               <a class="username">Collapsible panel</a>
              </div>
            </h4>
          </div>
          <div id="collapse1" class="panel-collapse collapse">
            <div class="panel-body">Panel Body</div>
            <div class="panel-footer">Panel Footer</div>
          </div>
        </div>
    </div>';*/

    /*
SELECT sm.id, sm.module_id, cm.module, sm.main_id, cs.section FROM `mdl_sync_modules` sm
   INNER JOIN `mdl_course_modules` cm ON sm.module_id = cm.id
    INNER JOIN `mdl_course_sections` cs ON cs.is = cm.section
   WHERE sm.main_id = 1
   ORDER BY cm.module ASC, sm.module_id DESC



   SELECT sm.id, sm.module_id, cm.module, sm.main_id, cs.section FROM {sync_modules} sm
   INNER JOIN {course_modules} cm ON sm.module_id = cm.id
    INNER JOIN {course_sections} cs ON cs.id = cm.section
   WHERE sm.main_id = 1
   ORDER BY cs.section ASC, cm.module ASC

   SELECT c.id, cs.section FROM {course_sections} cs
INNER JOIN {mdl_course} c ON c.id = cs.course
where c.id = 15


SELECT sm.id, sm.module_id, cm.module, sm.main_id, cs.section FROM `mdl_sync_modules` sm INNER JOIN `mdl_course_modules` cm ON sm.module_id = cm.id INNER JOIN `mdl_course_sections` cs ON cs.id = cm.section WHERE sm.main_id = 1 ORDER BY cs.section ASC, cm.module ASC
    */