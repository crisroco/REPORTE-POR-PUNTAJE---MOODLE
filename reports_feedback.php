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
 
include_once 'Classes/PHPExcel.php'; 
         $phpexcel = new PHPExcel();
         $phpexcel->setActiveSheetIndex(0);  
         $sheet2 = $phpexcel->getActiveSheet()->setTitle('Reporte repuesta marcada');

         ////$objWorkSheet = $phpexcel->createSheet()->setTitle('Reporte por usuario');
         /*foreach(range('A','Z') as $columnID) {
            //$objWorkSheet->getColumnDimension($columnID)
                 ->setAutoSize(true);
            //$objWorkSheet->getStyle($columnID)->getFont()->setSize(13);
         }*/
         foreach(range('A','Z') as $columnID) {
            $sheet2->getColumnDimension($columnID)
                 ->setAutoSize(true);
            $sheet2->getStyle($columnID)->getFont()->setSize(13);

         }
         foreach(range('C','Z') as $columnID) {            
            $sheet2->getStyle($columnID)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
         }



   /**
   RETORNA LOS CURSOS DE LA CATEGORIA ELEGIDA Y CANTIDAD DE ALUMNOS MATRICULADOS
*/
   $categoria = array();

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
   WHERE asg.roleid =  " . $role_config . " 
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




   
     // REPORTE DE CURSO
   
   $now = microtime(true);
   $actividades = array();
   $row_alumnos = 3;
   $row_respuestas = 2;
   $row_titulos = 2;
   $row_title1 = 1;

   $datos_all = array();
   foreach ($cursos as $key => $value) {  
        $nameCourse = $value->course;

      //RETORNA LAS ENCUESTAS VENCIDAS DE UNA SECCION Y CURSO ESPECIFICO 
      $sql_feedback = "SELECT fb.id,  fb.name, fb.course, cs.section as semana , cm.module, c.fullname as crname 
                        from {feedback} as fb  
                        join {course_modules} as cm ON fb.id = cm.instance
                        join {course_sections} as cs ON cm.section = cs.id
                        join {course} as c ON cm.course = c.id
                        where fb.course= $value->course_id AND fb.timeclose < $now AND cs.section = $section_course" ;

      $actividad = $DB->get_records_sql($sql_feedback);

      //IMPRIMIR TITULOS DE COLUMNAS - DATOS USUARIO
      $titulos = array('NOMBRE', 'APELLIDO');
      $td_titulos = 0;
      foreach ($titulos as $tid => $titu) {
         //$objWorkSheet->setCellValueByColumnAndRow($td_titulos,$row_titulos, $titu);
         //$objWorkSheet->getRowDimension($row_titulos)->setRowHeight(30);
         foreach(range('A','Z') as $columnID) {
           
            //$objWorkSheet->getStyle($columnID.$row_titulos)->getFont()->setBold(true);
         }
         $td_titulos++;
      }


      //PROCESAR CADA ENCUESTA
      
      //if ($actividad != array()) {       
      
        foreach ($actividad as $ke => $valu) {
           
           $mdid = $valu->id;
           $title1 = $valu->crname . ' - Semana ' . $section_course;

           //$objWorkSheet->getStyle('A'.$row_title1)->applyFromArray(
            /* array(
                 'fill' => array(
                     'type' => PHPExcel_Style_Fill::FILL_SOLID,
                     'color' => array('rgb' => '3f8cce')
                 )
             )
           ); */
           //$objWorkSheet->getRowDimension($row_title1)->setRowHeight(30);
           //$objWorkSheet->setCellValueByColumnAndRow(0,$row_title1, $title1);


           //RETORNA LOS USUARIOS QUE REALIZARON LA ENCUESTA Y RESPUESTAS QUE MARCARON 
           $sql = "SELECT fv.id, fc.userid, f.timemodified, u.firstname, c.fullname, u.lastname, u.username, fi.presentation, fv.value, fi.typ,f.name as encuesta, fi.name as pregunta, fi.id as pregunta_id
              FROM {user} u
              INNER JOIN {feedback_completed} fc ON fc.userid = u.id
              INNER JOIN {feedback} f ON f.id = fc.feedback
              INNER JOIN {course} c ON f.course = c.id 
              INNER JOIN {feedback_value} fv ON  fc.id = fv.completed
              INNER JOIN {feedback_item} fi ON  fi.id = fv.item
              WHERE f.id IN (?)                      
              ORDER BY fc.userid ASC";

              $preguta_usuario = $DB->get_records_sql($sql, array($mdid));
          



           // PROCESAR CADA PREGUNTADE CADA ENCUESTA
              
              $keytemp = '';
              $colum_respuestas = 2;
              $colum_respuestas_temp = $colum_respuestas; 

              $cont_temp = 1; 

              foreach ($preguta_usuario as $qid => $qstn) {

                 
                 
                  //IMPRIMIR SATOS DEL ALUMNO  
                  $colum_alumno = 0;
                  foreach ($qstn as $k => $v) {
                      if ($k == 'value' || $k == 'presentation' || $k == 'typ' || $k == 'encuesta' || $k == 'pregunta' || $k == 'username' || $k == 'id' || $k == 'userid' || $k == 'timemodified'|| $k == 'fullname'|| $k == 'cursoID'|| $k == 'pregunta_id') {
                          continue;
                      }
                      if ( $qstn->userid  != $keytemp) {  

                          
                          //$objWorkSheet->setCellValueByColumnAndRow($colum_alumno,$row_alumnos, $v);
                          $colum_alumno++;
                          

                      }else{
                          continue;
                      } 
                  }


                     //VALIDAR VALOR DE LA RESPUESTA TIPO MULTICOISE 
                    if ($qstn->typ == 'multichoice') {
                       $resp=explode('|', $qstn->presentation);
                       $valor = $resp[$qstn->value -1];
                       if (strpos($valor,">>>>>")>0){
                             $valor=substr($valor, 1);
                       }  
                             $valor = mberegi_replace("[\n|\r|\n\r|\t||\x0B|>>>>>|<<<<<|####]", "",$valor);
                      
                          
                    } 


                  //IMPRIMIR RESPUESTAS DEL ALUMNO
                   foreach ($qstn as $ky => $vl) {
                     
                      if ($ky == 'firstname' || $ky == 'lastname' || $ky == 'presentation' || $ky == 'typ' || $ky == 'encuesta' || $ky == 'pregunta' || $ky == 'username' || $ky == 'id' || $ky == 'userid' || $ky == 'timemodified'|| $ky == 'fullname' || $k == 'cursoID'|| $k == 'pregunta_id') {
                          continue;
                      }
                      if ( $qstn->userid  == $keytemp) {  
                          $question_n = 'Pregunta ' .$cont_temp; 
                          //$objWorkSheet->setCellValueByColumnAndRow($colum_respuestas,$row_titulos, $question_n);

                          
                          if ($qstn->typ == 'multichoice'){
                             //$objWorkSheet->setCellValueByColumnAndRow($colum_respuestas,$row_respuestas, $valor);
                                $colum_respuestas++;
                          }else{
                             //$objWorkSheet->setCellValueByColumnAndRow($colum_respuestas,$row_respuestas, $vl);
                             $colum_respuestas++;
                          }
                          

                      }else{
                          $question_n = 'Pregunta ' .$cont_temp; 
                          $row_respuestas++;
                          $colum_respuestas = $colum_respuestas_temp;
                          //$objWorkSheet->setCellValueByColumnAndRow($colum_respuestas,$row_titulos, $question_n);

                           if ($qstn->typ == 'multichoice'){
                             //$objWorkSheet->setCellValueByColumnAndRow($colum_respuestas,$row_respuestas, $valor);
                                $colum_respuestas++;
                          }else{
                             //$objWorkSheet->setCellValueByColumnAndRow($colum_respuestas,$row_respuestas, $vl);
                             $colum_respuestas++;
                          }
                          

                      } 
                  }

                  if ($qstn->userid != $keytemp) {
                      $row_alumnos++;
                  }

                  $keytemp = $qstn->userid;
                  $cont_temp++;
              }
        }
      //}

      $row_alumnos += 4;
      $row_respuestas += 4;
      $row_title1 = $row_alumnos - 2;
      $row_titulos = $row_alumnos - 1;

      //HOJA 2 REPORTE
      //cantida de veces marcado
      $respuestas = array();
      foreach ($preguta_usuario as $ky => $val) {
         if ($val->typ != 'multichoice') {
            continue;
         }
         $nresp = count(explode('|',$val->presentation));
         array_push($respuestas, $val->value);
         
      }  

      $encuestas = array();
      $encuestas[] = $preguta_usuario;

      $dato = array();
      $dato2 = array();
      $nresptemp = array();

      //foreach ($preguta_usuario as $encuesta) {                     
             //veces marcada
              foreach ($preguta_usuario as $index => $encuestado) {
                if ($encuestado->typ != 'multichoice') {
                 continue;
                }

                if(!isset($dato[$encuestado->encuesta])){
                    $dato[$encuestado->encuesta] = array();
                    
                }
                
                if(!is_null($dato[$encuestado->encuesta])){
                    if(!isset($dato[$encuestado->encuesta][$encuestado->pregunta])){
                        $dato[$encuestado->encuesta][$encuestado->pregunta] = array();
                        

                        $numAns = explode('|', $encuestado->presentation);
                        foreach ($numAns as $key => $value) {
                            $dato[$encuestado->encuesta][$encuestado->pregunta][$key + 1] = 0;
                            
                        }
                    }
                    
                    if(!is_null($dato[$encuestado->encuesta][$encuestado->pregunta])){
                        $dato[$encuestado->encuesta][$encuestado->pregunta][$encuestado->value]++;
                        
                    }
                }
                $nResp = count(explode('|', $encuestado->presentation));
              }
        //}   
            //porcentaje

            foreach ($dato as $dt => $datos) {               
                foreach ($datos as $enc => $encu) {
                  foreach ($encu as $ans => $answer) {
                     $nresptemp[$dt][$enc]['cantidad'] += $answer;
                     
                  }
                      
                }   
             }

             foreach ($dato as $dt => $datos) {               
                foreach ($datos as $enc => $encu) {
                  foreach ($encu as $ans => $answer) {
                     $dato2[$dt][$enc][$ans] = round(($answer/$nresptemp[$dt][$enc]['cantidad'] )*100,2);
                     
                  }
                      
                }   
             }               

            if ($dato != array() || $dato2 != array()) {
                # code...
               $datos_all[$nameCourse] = array('dato1' => $dato, 'dato2' => $dato2);
             } 

      
      //FIN - cantida de veces marcado
     
       if ($actividad != array()) {
         //foreach ($actividad as $llave => $valor) {
            array_push($actividades, $actividad);
         //}
       }
  }     

/*echo "<pre>";
  print_r($datos_all);
echo "</pre>";*/

//########################################################################
$datos_all2 = array();
$canti_temp = array();
foreach ($datos_all as $key => $cursos) {  
          
  foreach ($cursos as $dat => $enc) {

    foreach ($enc as $encu => $encuest) {      
      
      if ($dat == 'dato1') {      
                  
        foreach ($encuest as $preg => $pregunt) {
                    
          foreach ($pregunt as $alter => $alternat) {
            $datos_all2['global']['dato1'][$encu][$preg][$alter] += $alternat;
            $canti_temp['global']['dato1'][$encu][$preg] += $datos_all[$key]['dato1'][$encu][$preg][$alter];
            
          }
                      
        }

      }

        
    }
  }  
}

foreach ($datos_all as $key => $cursos) {  
          
  foreach ($cursos as $dat => $enc) {

    foreach ($enc as $encu => $encuest) {      
      
      if ($dat == 'dato2') {      
                  
        foreach ($encuest as $preg => $pregunt) {
                    
          foreach ($pregunt as $alter => $alternat) {
            $datos_all2['global']['dato2'][$encu][$preg][$alter] = round(($datos_all2['global']['dato1'][$encu][$preg][$alter]/$canti_temp['global']['dato1'][$encu][$preg])*100,2);
            
            
          }
                      
        }

      }

        
    }
  }  
}
//########################################################################



$tr_curso = 1;
$tr_encuesta = 2;
$tr_pregunta = 4;
$tr_percent = 6;
foreach ($datos_all2 as $key => $cursos) {
   $crutr = $tr_encuesta-1;
   $sheet2->getStyle('A'.$crutr.':B'.$crutr)->applyFromArray(
     array(
         'fill' => array(
             'type' => PHPExcel_Style_Fill::FILL_SOLID,
             'color' => array('rgb' => '3f8cce')
         )
     )
   ); 

   $sheet2->setCellValueByColumnAndRow(1, $tr_encuesta-1, 'REPORTE GLOBAL DE RESPUESTAS POR ENCUESTA'); 
   foreach ($cursos as $dat => $enc) {
      foreach ($enc as $encu => $encuest) {      
      $sum_tr = sizeof($encuest);

         if ($dat == 'dato1') {
           $sheet2->getStyle('A'.$tr_encuesta.':B'.$tr_encuesta)->applyFromArray(
              array(
                  'fill' => array(
                      'type' => PHPExcel_Style_Fill::FILL_SOLID,
                      'color' => array('rgb' => '9ac2e5')
                  )
              )
            );    
           $sheet2->setCellValueByColumnAndRow(1,$tr_encuesta, $encu);            
           $pregcont = 1;
           foreach ($encuest as $preg => $pregunt) {

            $sheet2->setCellValueByColumnAndRow(1,$tr_pregunta-1, 'PREGUNTA'.$pregcont);
            $sheet2->setCellValueByColumnAndRow(2,$tr_pregunta-1, 'RESPUESTAS');
            $sheet2->setCellValueByColumnAndRow(1,$tr_pregunta, $preg);
            $tr_trmp1 = $tr_pregunta+1;
            $tr_trmp2 = $tr_pregunta+2;
            $sheet2->getStyle('A'.$tr_trmp1.':B'.$tr_trmp2)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

            $sheet2->setCellValueByColumnAndRow(1,$tr_pregunta+1, 'Veces marcada');
            $sheet2->setCellValueByColumnAndRow(1,$tr_pregunta+2, 'Porcentaje');
            $td_alter = 2;
            $cont = 1;
            foreach ($pregunt as $alter => $alternat) {
               $tr_temp3 = $tr_pregunta+1;
               $tr_temp4 = $tr_pregunta+2;
               $sheet2->setCellValueByColumnAndRow($td_alter, $tr_pregunta, 'Respuesta '.$cont);
               $borderr = array(
                 'borders' => array(
                   'allborders' => array(
                     'style' => PHPExcel_Style_Border::BORDER_THIN
                   )
                 )
               );
               $sheet2->getStyle('C'.$tr_temp3.':G'.$tr_temp4)->applyFromArray($borderr);
               $sheet2->getStyle('C'.$tr_temp3.':G'.$tr_temp4)->applyFromArray(
                 array(
                     'fill' => array(
                         'type' => PHPExcel_Style_Fill::FILL_SOLID,
                         'color' => array('rgb' => 'd6e8f5')
                     )
                 )
               );
               $sheet2->setCellValueByColumnAndRow($td_alter,$tr_pregunta+1, $alternat);
               $td_alter++;
               $cont++;
            }
            $tr_pregunta += 6;
            $pregcont++;
           }
            $tr_encuesta = $tr_pregunta + 1;         
         }
         if ($dat == 'dato2') {
            foreach ($encuest as $preg => $pregunt) {
               $td_percent = 2;
               foreach ($pregunt as $alter => $alternat) {
                  $sheet2->setCellValueByColumnAndRow($td_percent,$tr_percent, round($alternat, 2).'%');
                  $td_percent++;
               }
               $tr_percent += 6;
            }
         }
      }
     
    
   }
      
   $tr_curso += $sum_tr + 9;//sumar numero de preguntas mas filas adicionales
   $tr_pregunta += 3;
   $tr_percent += 3;
   //$tr_encuesta += 2;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte de encuestas.xlsx"');
header('Cache-Control: max-age=0');
$writer = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
        $writer->setIncludeCharts(TRUE);
        $writer->save('php://output');


/*

  foreach ($dato as $encu => $encues) {
     $sheet2->setCellValueByColumnAndRow(1,2, $encu);
     foreach ($encues as $preg => $pregunt) {
         $sheet2->setCellValueByColumnAndRow(2,4, $preg);
         $tr_preg = 4;
         $td_alter = 3;
         $tr_alter = 5;
         $tr_porcen = 6;
         
         foreach ($pregunt as $alter => $alternat) {
            $sheet2->setCellValueByColumnAndRow($td_alter, $tr_preg, 'Respuesta');
            $sheet2->setCellValueByColumnAndRow($td_alter,$tr_alter, $alternat);
            $sheet2->setCellValueByColumnAndRow($td_alter,$tr_porcen, $dato2[$encu][$preg][$alter].'%');
            $td_alter++;
         }
         $tr_preg += 6;
         $tr_alter += 6;
         $tr_porcen += 6;
     }
   } 
   $tr_titl = 3; 
      $sheet2->setCellValueByColumnAndRow(1,$tr_titl, 'Etiqueta');
      $sheet2->setCellValueByColumnAndRow(2,$tr_titl, 'Pregunta');
      $sheet2->setCellValueByColumnAndRow(3,$tr_titl, 'Respuestas');

     
      $tr_titl += $nresp*2 + 1;

*/




