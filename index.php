<?php
require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();
require_login();
require_capability('local/customgroupsmanagement:view', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customgroupsmanagement/index.php'));
$PAGE->set_context(context_system::instance());
$title = get_string('pluginname', 'local_customgroupsmanagement');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// 1) Load custom CSS
$PAGE->requires->css(new \moodle_url('/local/customgroupsmanagement/css/styles.css'));

echo $OUTPUT->header();

// Show a full-screen overlay with spinner while loading
echo '<div id="loader-overlay" class="loader-overlay d-none">
        <div class="spinner-border text-primary" role="status"></div>
    </div>';

// 2) Read params
$coursename = optional_param('coursename', '', PARAM_TEXT);
$groupname = optional_param('groupname', '', PARAM_TEXT);
// NEW: formateur filter
$teacher = optional_param('teacher', '', PARAM_TEXT);

// 2. Read & default the month filter (YYYY-MM)
$month = optional_param('month', '', PARAM_ALPHANUMEXT);
// if (empty($month)) {
//     // default to current month in user’s timezone
//     $month = userdate(time(), 'Y-m');
// }

if (empty($month)) {
    $month = date('Y-m');
}
// parse into a DateTime so we can step prev/next
$current = DateTime::createFromFormat('Y-m', $month);
if (!$current) {
    $current = new DateTime();
}
$prev = (clone $current)->modify('-1 month');
$next = (clone $current)->modify('+1 month');
$prevmonth = $prev->format('Y-m');
$nextmonth = $next->format('Y-m');


$page = max(1, optional_param('page', 1, PARAM_INT));
$perpage = 10;

// 3) Fetch paged rows & total
$dm = new \local_customgroupsmanagement\manager\DataManager();
$data = $dm->get_page($coursename, $groupname, $teacher, $month, $page, $perpage);
$rows = $data['rows'];
$total = $data['total'];

// 4) Render cards + pagination
/** @var \local_customgroupsmanagement\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_customgroupsmanagement');
echo $renderer->render_card_view($rows, $coursename, $groupname, $teacher, $month, $prevmonth, $nextmonth, $page, $perpage, $total);



$PAGE->requires->js_call_amd('local_customgroupsmanagement/loader', 'init');
echo $OUTPUT->footer();