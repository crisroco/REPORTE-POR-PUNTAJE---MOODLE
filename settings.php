<?php


defined('MOODLE_INTERNAL') || die;



$ADMIN->add('reports', new admin_category('reportes', get_string('pluginname','report_reportpoints')));


$ADMIN->add('reportes',
      new admin_externalpage('reportpoints',get_string('reporte_encuesta','report_reportpoints'),
      "$CFG->wwwroot/report/reportpoints/reprote_feedback.php",'report/reportpoints:view'));

$ADMIN->add('reportes',
      new admin_externalpage('reportpoints',get_string('reporte_participacion','report_reportpoints'),
      "$CFG->wwwroot/report/reportpoints/index.php",'report/reportpoints:view'));

// no report settings
$settings = null;
