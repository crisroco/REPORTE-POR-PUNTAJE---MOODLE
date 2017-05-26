<?php
include_once 'Classes/PHPExcel.php';
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');

global $DB, $CFG, $PAGE, $OUTPUT, $USER;



require_login();

$categoryid = required_param('categoryid', PARAM_INT);
$section_course = required_param('section_course', PARAM_INT);

 //header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 //header('Content-Disposition: attachment;filename="Reporte de participacion.xlsx"');
 //header('Cache-Control: max-age=0');


/**
   Retorna los cursos de la categoria elegida y cantidad de alumnos matriculados
*/

   $role_config = $DB->get_record('config',  array('name' => 'reportpointsroleid'))->value;
   $sql_cursos = "SELECT course.id as Course_id,course.fullname AS Course
   ,context.id AS Context
   , COUNT(course.id) AS Students
   ,category.name
   ,category.path
   FROM {role_assignments} AS asg
   JOIN {context} AS context ON asg.contextid = context.id AND context.contextlevel = 50
   JOIN {user} AS USER ON USER.id = asg.userid
   JOIN {course} AS course ON context.instanceid = course.id
   JOIN {course_categories} AS category ON course.category = category.id
   WHERE asg.roleid = " . $role_config . " 
   AND category.id =".$categoryid."  
   GROUP BY course.id
   ORDER BY COUNT(course.id) DESC";

   $cursos = $DB->get_records_sql($sql_cursos);

   if ($cursos == array()) {
      //echo 'Verifique la categoria seleccionda';
      die();
   }else{

      foreach ($cursos as $key => $value) {
         if (strlen($value->path)>2) {
            unset($cursos[$key]);
         }
      }

      if ($cursos == array()) {
         die();
      }
   }   


   /**
      devuelve datos de las actividades(encuesta y tareas)
   */

      //cm.instance -> es el id del modulo
   $now = microtime(true);
   $actividades = array();

      foreach ($cursos as $key => $value) {
         $type_activity = "SELECT cm.instance, cm.module
                           from {course_modules} as cm
                           INNER JOIN {course_sections} as cs ON cm.section = cs.id
                           where cs.course = $value->course_id AND cs.section = $section_course AND (cm.module = 7 or cm.module = 1)";
         //solo debe haber una encuesta o una tarea por semana
         $tipo_actividad = $DB->get_record_sql($type_activity);
        
         
         
         if (is_object($tipo_actividad)) {
            $activ = $tipo_actividad->module;
         }
         //FLUJO DE FEEDBACK
         if ($activ == 7) {
            
             $sql_feedback = "SELECT fb.id,  fb.name, fb.course, c.fullname as curname, cs.section as semana , cm.module 
                              from {feedback} as fb  
                              join {course_modules} as cm ON fb.id = cm.instance
                              join {course_sections} as cs ON cm.section = cs.id
                              join {course} as c ON cm.course = c.id
                              where fb.course= $value->course_id AND fb.timeclose < $now AND cs.section = $section_course" ;

             $actividad = $DB->get_records_sql($sql_feedback);
             if ($actividad != array()) {
               foreach ($actividad as $llave => $valor) {
                  array_push($actividades, $valor);
               }
             }
         //FIN - FLUJO DE FEEDBACK
         //FLUJO DE TAREA 
         }elseif ($activ == 1) {
            $sql_assign = "SELECT ass.id,  ass.name, ass.course, c.fullname as curname, cs.section as semana , cm.module 
                              from {assign} as ass  
                              join {course_modules} as cm ON ass.id = cm.instance
                              join {course_sections} as cs ON cm.section = cs.id
                              join {course} as c ON cm.course = c.id
                              where ass.course= $value->course_id  AND cs.section = $section_course" ;

             $actividad = $DB->get_records_sql($sql_assign);
             if ($actividad != array()) {
               foreach ($actividad as $llave => $valor) {
                  array_push($actividades, $valor);
               }
             }
         //FIN - FLUJO DE TAREA 
         }
         
      } 



   /**
      calcular puntaje 
   */
      $all_data = array();
      foreach ($actividades as $key => $value) {       

          $cur_name = $value->curname;

         if ($value->module == 7) {
            $puntaje = 0;
            $sql_feedback_cantidad = "SELECT count(fc.id) as cantidad_participante from {feedback_completed} as fc where fc.feedback = $value->id";
            $encuestas_cantidad = $DB->get_records_sql($sql_feedback_cantidad);
            foreach ($encuestas_cantidad as $llave => $valor) {        
              
               $puntaje = ($valor->cantidad_participante/$cursos[$value->course]->students)*100;
            }
            $fb_name = $value->name;

            $datos_feedback = new stdClass();
            $datos_feedback->activity_name = $fb_name;
            $datos_feedback->tipo = 'Encuesta';
            $datos_feedback->puntaje = round($puntaje,2).'%';
            $datos_feedback->curname = $cur_name;
            array_push($all_data, $datos_feedback);

         }elseif ($value->module == 1) {
            $puntaje = 100;
            
            $sql_tarea_verification = "SELECT subass.id,subass.status,subass.timecreated, ass.id, ass.name, ass.duedate  
                                       from {assign} as ass
                                       join {assign_submission} as subass ON ass.id = subass.assignment 
                                       where ass.id = $value->id AND status = 'submitted'";
            $tarea_verification = $DB->get_records_sql($sql_tarea_verification);

            $tarea_verification = array_values($tarea_verification);

            $valorMaximo = 0;
            foreach ($tarea_verification as $key => $value) {
              if($value->timecreated < $valorMaximo){
                $tmp = $tarea_verification[0];
                $tarea_verification[0] = $tarea_verification[$key];
                $tarea_verification[$key] = $tmp;

              }
              $valorMaximo = $value->timecreated;
            }
            

            while ( count($tarea_verification) > 1) {
               array_pop($tarea_verification);
            }

            foreach ($tarea_verification as $key => $value) {

               $now = $value->timecreated;

               $time_upload = $value->duedate;
               $one_day = $time_upload+86400;
               $two_day = $one_day+86400;
               $three_day = $two_day+86400;
               $four_day = $three_day+86400;

               $dias = dias_transcurridos($value->duedate,$value->timecreated);
               switch (ceil($dias)) {
                 case 1:
                   $puntaje = 80;
                 break;
                 case 2:
                   $puntaje = 60;
                 break;
                 case 3:
                   $puntaje = 40;
                 break;
                 case 4:
                   $puntaje = 20;
                 break;
                 default:
                   $puntaje = 0;
                 break;
               }
               if($días <= 0.0){
                  $puntaje = 100;
               }
               echo "-------" . $puntaje . "----<pre>";
               print_r($dias);
               echo "</pre>";die();
               /*if ($time_upload < $now) {
                  $puntaje = 100;
               }elseif ($now == $one_day) {
                  $puntaje = 80;
               }elseif ($now == $two_day) {
                  $puntaje = 60;                  
               }elseif ($now == $three_day) {
                  $puntaje = 40;                  
               }elseif ($now == $four_day) {
                  $puntaje = 20;                  
               }else{
                  $puntaje = 0;                  
               } */  
            

               $ass_name = $value->name;

               $datos_tarea = new stdClass();
               $datos_tarea->activity_name = $ass_name;
               $datos_tarea->tipo = 'Tarea';
               $datos_tarea->puntaje = $puntaje;
               $datos_tarea->curname = $cur_name;
               array_push($all_data, $datos_tarea);
            }
            
            //array_push($all_data, $tarea_verification);
            
         }
      }


     

     //########### EXCEL ########################

        include_once 'Classes/PHPExcel.php'; 
         $phpexcel = new PHPExcel();
         $phpexcel->setActiveSheetIndex(0);  
         $objWorkSheet = $phpexcel->getActiveSheet()->setTitle('Reporte de participacion');

         //$objWorkSheet->getColumnDimension('A')->setAutoSize(true);
         $objWorkSheet->getColumnDimension('A')->setAutoSize(true);
         $objWorkSheet->getColumnDimension('B')->setAutoSize(true);
         $objWorkSheet->getColumnDimension('C')->setAutoSize(true);
         $objWorkSheet->getColumnDimension('D')->setAutoSize(true);
         $objWorkSheet->getColumnDimension('E')->setAutoSize(true);

         $objWorkSheet->mergeCells('A1:B1');

         $objWorkSheet->getStyle('A1:L400')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

         $objWorkSheet->getStyle("A1:D3")->getFont()->setBold(true);
        
         $objWorkSheet->getStyle('A1:B1')->applyFromArray(
           array(
               'fill' => array(
                   'type' => PHPExcel_Style_Fill::FILL_SOLID,
                   'color' => array('rgb' => '3f8cce')
               )
           )
         ); 

         $objWorkSheet->setCellValueByColumnAndRow(0,1, 'Reporte de participacion Semana'.$section_course);
         $objWorkSheet->setCellValueByColumnAndRow(0,3, 'Curso');
         $objWorkSheet->setCellValueByColumnAndRow(1,3, 'Actividad');
         $objWorkSheet->setCellValueByColumnAndRow(2,3, 'Tipo');
         $objWorkSheet->setCellValueByColumnAndRow(3,3, 'Puntaje');

          $styleArray = array(    
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            ));


         $tr = 4;

         foreach ($all_data as $key => $value) {
            $objWorkSheet->getStyle('A3:D'.$tr)->applyFromArray($styleArray); 
            $objWorkSheet->setCellValueByColumnAndRow(0,$tr, $value->curname);
            $objWorkSheet->setCellValueByColumnAndRow(1,$tr, $value->activity_name);
            $objWorkSheet->setCellValueByColumnAndRow(2,$tr, $value->tipo);
            $objWorkSheet->setCellValueByColumnAndRow(3,$tr, $value->puntaje);

            $tr++;
         }



         $writer = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
        $writer->setIncludeCharts(TRUE);
        $writer->save('php://output');

 

 function dias_transcurridos($fecha_i,$fecha_f)
{
  

  //$fecha_i = gmdate("Y-m-d", $fecha_i);
  //$fecha_f = gmdate("Y-m-d", $fecha_f);

  $dias = ($fecha_i-$fecha_f)/86400;
  //$dias   = abs($dias); 
  //$dias = floor($dias);   

  return $dias * -1;
}