/**
   RETORNA LOS CURSOS DE LA CATEGORIA ELEGIDA Y CANTIDAD DE ALUMNOS MATRICULADOS
*/
   $categoria = array();

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
   WHERE asg.roleid = 5 
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
   echo "<pre>";
      print_r($cursos);
   echo "</pre>";



   /**
      REPORTE DE CURSO
   */
   $now = microtime(true);
   $actividades = array();

   foreach ($cursos as $key => $value) {  




        
      //RETORNA LAS ENCUESTAS VENCIDAS DE UNA SECCION Y CURSO ESPECIFICO 
      $sql_feedback = "SELECT fb.id,  fb.name, fb.course, cs.section as semana , cm.module 
                        from {feedback} as fb  
                        join {course_modules} as cm ON fb.id = cm.instance
                        join {course_sections} as cs ON cm.section = cs.id
                        where fb.course= $value->course_id AND fb.timeclose < $now AND cs.section = $section_course" ;

      $actividad = $DB->get_records_sql($sql_feedback);


      //PROCESAR CADA ENCUESTA
      foreach ($actividad as $ke => $valu) {
         
         $mdid = $valu->id;
         //RETORNA LOS USUARIOS QUE REALIZARON LA ENCUESTA Y RESPUESTAS QUE MARCARON 
         $sql = "SELECT fv.id, fc.userid, f.timemodified, u.firstname, u.lastname, u.username, c.fullname, fi.presentation, fv.value, fi.typ,f.name as encuesta, fi.name as pregunta
            FROM {user} u
            INNER JOIN {feedback_completed} fc ON fc.userid = u.id
            INNER JOIN {feedback} f ON f.id = fc.feedback
            INNER JOIN {course} c ON f.course = c.id 
            INNER JOIN {feedback_value} fv ON  fc.id = fv.completed
            INNER JOIN {feedback_item} fi ON  fi.id = fv.item
            WHERE f.id IN (?)
            ORDER BY fc.userid ASC";

             $preguta_usuario = $DB->get_records_sql($sql, array($mdid));
         echo "<pre>";
         print_r($preguta_usuario);
         echo "</pre>";  



         // PROCESAR CADA PREGUNTADE CADA ENCUESTA
            $row = 2;
            $keytemp = '';
            foreach ($preguta_usuario as $qid => $qstn) {
                $colum = 0;

                foreach ($qstn as $k => $v) {
                    if ($k == 'value' || $k == 'presentation' || $k == 'typ' || $k == 'encuesta' || $k == 'pregunta' || $k == 'username' || $k == 'id' || $k == 'userid' || $k == 'timemodified' ) {
                        continue;
                    }
                    if ( $qstn->userid  != $keytemp) {                 
                        
                        $sheet->setCellValueByColumnAndRow($colum,$row, $v);
                        $colum++;

                    }else{
                        continue;
                    }
                    

                }

                if ($qstn->userid != $keytemp) {
                    $row++;
                }

                $row;
                $keytemp = $qstn->userid;
                
            }

        
        //OBTENER LAS PREGUNTAS DE CADA ENCUESTA 
        // $questionid = $DB->get_records('feedback_item',array('feedback'=>$mdid),null,'id,name,typ,feedback,presentation');
         /*echo "<pre>";
         print_r($questionid);
         echo "</pre>";*/

         //nombre del cuestionario
         //$other = $DB->get_record('feedback',array('id'=>$mdid),'id,name');

         //$fbid = end($questionid);//obtiene ultimo objeto del array
         //$courseid = $DB->get_records('feedback',array('id' => $fbid->feedback),null,'id as fbid, course');


         //PROCESAR CADA PREGUNTADE CADA ENCUESTA
         /*foreach ($questionid as $key=>$value) {

            $questions = $DB->get_records('feedback_item', array('id' => $value->id), null, 'id, name');
            print_r($questions);
         } */   

     
      }

      
     
       if ($actividad != array()) {
         //foreach ($actividad as $llave => $valor) {
            array_push($actividades, $actividad);
         //}
       }
      
     
   }