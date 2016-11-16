<?php 

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('courses', new admin_category('adminblocksync', get_string('titlename','block_sync')));
$ADMIN->add('adminblocksync', new admin_externalpage('blocksync', get_string('configname','block_sync'), "$CFG->wwwroot/blocks/sync/admin.php", 'block/sync:config'));
$ADMIN->add('adminblocksync', new admin_externalpage('dashblocksync', get_string('adminname','block_sync'), "$CFG->wwwroot/blocks/sync/report.php", 'block/sync:config'));