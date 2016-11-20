<?php
include_once 'Classes/PHPExcel.php';
require_once("../../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/modinfolib.php');
require_once($CFG->libdir.'/formslib.php');

include('lib.php');
global $DB;

//$id = required_param('id', PARAM_INT);
//$courseid = required_param('courseid', PARAM_INT);
$courseid = 2;
$id = 1;


header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

$hoy =date("j_F_Y");


header("Content-Disposition: attachment;filename=Reporte_$hoy.xlsx");
header('Cache-Control: max-age=0');



$phpexcel = new PHPExcel();

$phpexcel->setActiveSheetIndex(0);
$sheet = $phpexcel->getActiveSheet();
//MODIFICIONES======================
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
      $percent = sync_check_course($id,$value->id);
      $value->porcentaje = $percent['percent'];
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
         $sheet->setCellValueByColumnAndRow(1,$tr1, $dato->tipo);
        $sheet->setCellValueByColumnAndRow(2,$tr1, $dato->shortname);
        $sheet->setCellValueByColumnAndRow(3,$tr1, $dato->sections);
        $sheet->setCellValueByColumnAndRow(4,$tr1, $dato->formato);
        $sheet->setCellValueByColumnAndRow(5,$tr1, $dato->coordinador);
        $sheet->setCellValueByColumnAndRow(6,$tr1, $dato->porcentaje.'%');
        /*echo "<pre>";
         print_r($dato->tipo);
         echo "</pre>";*/
         $tr1++;
     }

       /*echo "<pre>";
         print_r($datos);
         echo "</pre>";*/
}
     
         

//MODIFICIONES======================
/*

//$encuesta=$DB->get_record('course_modules',array('id'=>$_GET['id']),'id,instance');

//##############################==REPORTE POR PREGUNTA==###########################################
$questionid = $DB->get_records('feedback_item',array('feedback'=>$encuesta->instance),null,'id,name');

$espacio = 0;
foreach ($questionid as $key=>$value) {

    //etiquetas preguntas
    //$questions = $DB->get_records('feedback_item', array('id' => $value->id), null, 'id, name');
    $data = array();
    foreach ($questionid as $key => $value) {
        array_push($data, $value->name);
    }

    $row=$espacio+1;

    $styleArray = array(
        'font'  => array(
            'bold'  => true,
            'color' => array('rgb' => '808080'),
            'size'  => 13,
            'name'  => 'Verdana'
        ));
    $sheet->getStyle("B$row:C$row")->applyFromArray($styleArray);
    foreach($data as $point) {
        $sheet->setCellValueByColumnAndRow(1, $row++, $point);
    }

    //etiquetas de opciones
    $options = $DB->get_records('feedback_item',array('id'=>$value->id),null,'id,presentation');
    foreach ($options as $key => $value){

        $data2=explode('|', $value->presentation);

    }
    //elimina saltos de linea tabulaciones y caracteres especiales -> mberegi_replace("[\n|\r|\n\r|\t||\x0B]", "",$string);
    $row = $espacio+2;
    foreach($data2 as $point) {

        if (strpos($point,">>>>>")>0){
            $point=substr($point, 1);
        }

        $sheet->setCellValueByColumnAndRow(1, $row++, mberegi_replace("[\n|\r|\n\r|\t||\x0B|>>>>>]", "",$point));
    }

    //cantidad de veces marcados
    $datalength=sizeof($data2);
    $nespacios=$datalength;
    $values = $DB->get_records('feedback_value',array('item'=>$value->id),null,'id, value');
    $datas=array();
    foreach ($values as $key => $value){
        array_push($datas,$value->value);
    }
    $valores=array();
    do{
        $suma=0;
        foreach ($datas as $key=>$value) {
            $contador=0;
            if ($value==$datalength) {
                $contador++;
            }
            $suma+=$contador;
        }
        array_push($valores,$suma);
        $datalength--;
    } while ( $datalength > 0);

    $valores=array_reverse($valores);
    $row = $espacio+2;
    foreach($valores as $point) {
        $sheet->setCellValueByColumnAndRow(2, $row++, $point);
      }
*/
/*
$n1=$espacio+1;
$n2=$espacio+15;
$dato1='E'. $n1;
$dato2='L' . $n2;


$sheet->setCellValueByColumnAndRow(2, $n1, 'Cantidad de veces marcada');
$dato3='Worksheet!B'. ($espacio+2) .':B'. ($espacio+1+$nespacios);
$dato4='Worksheet!C'. ($espacio+2) .':C'. ($espacio+1+$nespacios);

$categories = new PHPExcel_Chart_DataSeriesValues('String', $dato3);
$values = new PHPExcel_Chart_DataSeriesValues('String', $dato4);


$series = new PHPExcel_Chart_DataSeries(
PHPExcel_Chart_DataSeries::TYPE_BARCHART,       // plotType
+PHPExcel_Chart_DataSeries::GROUPING_CLUSTERED,  // plotGrouping
array(0),                                       // plotOrder
array(),                                        // plotLabel
array($categories),                             // plotCategory
array($values)                                  // plotValues
);
$series->setPlotDirection(PHPExcel_Chart_DataSeries::DIRECTION_HORIZONTAL);

$layout = new PHPExcel_Chart_Layout();
$plotarea = new PHPExcel_Chart_PlotArea($layout, array($series));
$xTitle = new PHPExcel_Chart_Title('Respuestas');
$yTitle = new PHPExcel_Chart_Title('');*/
/*
$chart = new PHPExcel_Chart('sample', null, null, $plotarea, true,0,$xTitle,$yTitle);

$chart->setTopLeftPosition($dato1);
$chart->setBottomRightPosition($dato2);

$sheet->addChart($chart);

    $espacio+=$nespacios+14;*/

//########################################################################################

$writer = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
$writer->setIncludeCharts(TRUE);
$writer->save('php://output');
