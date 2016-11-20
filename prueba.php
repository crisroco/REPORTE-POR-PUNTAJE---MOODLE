<?php
include_once 'Classes/PHPExcel.php';
require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');
//include_once 'lib.php';
global $DB;

//$id = required_param('id', PARAM_INT);
//$courseid = required_param('courseid', PARAM_INT);
$courseid = 2;
$id = 1;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="graph.xlsx"');
  header('Cache-Control: max-age=0');

  include_once 'Classes/PHPExcel.php';
  $phpexcel = new PHPExcel();

  $phpexcel->setActiveSheetIndex(0);
  $sheet = $phpexcel->getActiveSheet();
  $sheet->getColumnDimension('A')->setAutoSize(true);
  $sheet->getColumnDimension('B')->setAutoSize(true);
  $sheet->getColumnDimension('C')->setAutoSize(true);
  $sheet->getColumnDimension('D')->setAutoSize(true);
  $sheet->getColumnDimension('E')->setAutoSize(true);
  $sheet->getColumnDimension('F')->setAutoSize(true);
  $sheet->getColumnDimension('G')->setAutoSize(true);
  $sheet->getStyle('A1:G400')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

 $curso = "SELECT suh.id, suh.main_id, suh.child_id FROM {sync_user_history} suh
         WHERE suh.main_id in (?)
         ORDER BY suh.main_id ASC, suh.time_sync DESC";
//$cursos = $DB->get_records_sql($curso,array($courseid));
$cursos = $DB->get_records_sql($curso,array($courseid));

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

$title = array('TIPO','CURSO','Nro Secciones', 'Formato de Curso', 'Coordinado a Cargo ', 'Porcentaje de Sincronización');
$td=0;

foreach ($title as $key => $value) {
    $sheet->setCellValueByColumnAndRow($td,1, $value);
    $td++;
}

   $cont = 1;
   $tr1 = 2;

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
      $percent = '91%';//sync_check_course($id,$value->id);
      $value->porcentaje = $percent;//['percent'];
      if ($value->id == $courseid) {
         $crs = 'Padre';
         $value->tipo = $crs;
      }else{
         $crs = 'Hijo ' . $cont;
         $value->tipo = $crs;
         $cont++;
        }
     }

     foreach ($datos as $key => $dato) {
        $sheet->setCellValueByColumnAndRow(0,$tr1, $dato->tipo);
        $sheet->setCellValueByColumnAndRow(1,$tr1, $dato->shortname);
        $sheet->setCellValueByColumnAndRow(2,$tr1, $dato->sections);
        $sheet->setCellValueByColumnAndRow(3,$tr1, $dato->formato);
        $sheet->setCellValueByColumnAndRow(4,$tr1, $dato->coordinador);
        $sheet->setCellValueByColumnAndRow(5,$tr1, $dato->porcentaje.'%');
        $tr1++;
     }
       /*echo "<pre>";
         print_r($datos);
         echo "</pre>";*/
}  


  $writer = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
  $writer->setIncludeCharts(TRUE);
  $writer->save('php://output');