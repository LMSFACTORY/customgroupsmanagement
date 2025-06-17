<?php

namespace local_customgroupsmanagement\output;
defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class Renderer extends \plugin_renderer_base
{

    /**
     * Render filter form, card view, and enhanced pagination
     * with explicit first/last links.
     *
     * @param array  $rows        Rows for the current page.
     * @param string $coursename  Current course filter.
     * @param string $groupname   Current group filter.
     * @param string $teacher     Current teacher filter.
     * @param string $month      Current month filter.
     * @param string $prevmonth  Previous month filter.
     * @param string $nextmonth  Next month filter.
     * @param int    $page        Current page.
     * @param int    $perpage     Items per page.
     * @param int    $total       Total items.
     * @return string             HTML
     */

    public function render_card_view(
        array $rows,
        string $coursename,
        string $groupname,
        string $teacher,
        string $month,
        string $prevmonth,
        string $nextmonth,
        int $page,
        int $perpage,
        int $total
    ): string {
        // Base URL shared by all parts
        $baseurl = new \moodle_url('/local/customgroupsmanagement/index.php');

        // Orchestrate each piece
        $output = '';
        $output .= $this->render_filters($baseurl, $coursename, $groupname, $teacher, $month);
        $output .= $this->render_month_nav($baseurl, $coursename, $groupname, $teacher, $month, $prevmonth, $nextmonth);

        if (empty($rows)) {
            $output .= \html_writer::tag(
                'p',
                get_string('nogroupsfound', 'local_customgroupsmanagement'),
                ['class' => 'text-muted']
            );
            return $output;
        }

        // Only for the current month, build a “Aujourd’hui” bucket.
        if ($month === date('Y-m')) {
            $now = time();
            $todayrows = array_filter($rows, function ($r) use ($now) {
                return !empty($r['sessionstartraw'])
                    && !empty($r['sessionendrraw'])
                    && $r['sessionstartraw'] <= $now
                    && $now <= $r['sessionendrraw'];
            });
            if (!empty($todayrows)) {
                // Heading “Aujourd’hui”
                $output .= \html_writer::tag(
                    'h3',
                    get_string('today', 'local_customgroupsmanagement'),
                    ['class' => 'mt-4 mb-2 label-today']
                );
                // Render those cards
                $output .= $this->render_from_template(
                    'local_customgroupsmanagement/card_view',
                    ['rows' => array_values($todayrows)]
                );
            }
        }

        $output .= $this->render_cards($rows);
        $output .= $this->render_info($total, $page, $perpage);
        $output .= $this->render_pager($baseurl, $coursename, $groupname, $teacher, $month, $page, $perpage, $total);

        return $output;
    }

    /** 1) Top text filters + Apply/Reset */
    protected function render_filters($baseurl, $coursename, $groupname, $teacher, $month): string
    {
        $form = \html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $baseurl->out(false),
            'class' => 'form-inline mb-3 custom-groups-filters'
        ]);
        // Course
        $form .= \html_writer::label(get_string('coursename', 'local_customgroupsmanagement'), 'coursename', ['class' => 'mr-2'])
            . \html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'coursename',
                'value' => s($coursename),
                'class' => 'form-control mr-3',
                'placeholder' => get_string('placeholdercoursename', 'local_customgroupsmanagement')
            ]);
        // Group
        $form .= \html_writer::label(get_string('groupname', 'local_customgroupsmanagement'), 'groupname', ['class' => 'mr-2'])
            . \html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'groupname',
                'value' => s($groupname),
                'class' => 'form-control mr-3',
                'placeholder' => get_string('placeholdergroupname', 'local_customgroupsmanagement')
            ]);
        // Teacher
        $form .= \html_writer::label(get_string('teacher', 'local_customgroupsmanagement'), 'teacher', ['class' => 'mr-2'])
            . \html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'teacher',
                'value' => s($teacher),
                'class' => 'form-control mr-3',
                'placeholder' => get_string('placeholderteacher', 'local_customgroupsmanagement')
            ]);

        // Month
        $form .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'month',
            'value' => $month,
            'class' => 'form-control mr-3'
        ]);
        // Buttons
        $form .= \html_writer::tag(
            'button',
            '<i class="fa-solid fa-filter"></i> ' . get_string('filter', 'local_customgroupsmanagement'),
            ['type' => 'submit', 'class' => 'btn btn-primary mr-2']
        )
            . \html_writer::link($baseurl, get_string('resetfilters', 'local_customgroupsmanagement'), ['class' => 'btn btn-secondary']);
        $form .= \html_writer::end_tag('form');
        return $form;
    }

    /** 2) Month‐selector with ◀ month ▶ */
    protected function render_month_nav($baseurl, $coursename, $groupname, $teacher, $month, $prevmonth, $nextmonth): string
    {
        $timestamp = strtotime("$month-01");
        // Use Moodle’s langconfig string so “mai 2025” shows in French, etc.
        $label = userdate($timestamp, get_string('strftimemonthyear', 'langconfig'));
        $monthNav = \html_writer::start_tag('nav', ['aria-label' => 'Month navigation', 'class' => 'month-nav']);
        $monthNav .= \html_writer::start_tag('ul', ['class' => 'pagination justify-content-center mb-4']);

        // Prev arrow (always active)
        $monthNav .= \html_writer::start_tag('li', ['class' => 'page-item']);
        $monthNav .= \html_writer::link(new \moodle_url($baseurl, [
            'coursename' => $coursename,
            'groupname' => $groupname,
            'teacher' => $teacher,
            'month' => $prevmonth,
            'page' => 1
        ]), '&laquo;', ['class' => 'page-link']);
        $monthNav .= \html_writer::end_tag('li');

        // Month‐picker dropdown
        $monthNav .= \html_writer::start_tag('li', ['class' => 'page-item']);
        // Inline form
        $monthNav .= \html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $baseurl->out(false),
            'class' => 'm-0'
        ]);
        // Preserve text filters
        foreach (['coursename', 'groupname', 'teacher'] as $fld) {
            $mount = $$fld;  // caution: variable variable
            $monthNav .= \html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $fld,
                'value' => s($mount)
            ]);
        }
        // Reset page
        $monthNav .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'page',
            'value' => 1
        ]);

        // Month input
        $monthNav .= \html_writer::empty_tag('input', [
            'type' => 'month',
            'name' => 'month',
            'value' => $month,
            'lang' => current_language(),
            'class' => 'form-control form-control-sm month-picker',
            'onchange' => 'this.form.submit()'
        ]);
        $monthNav .= \html_writer::end_tag('form');
        $monthNav .= \html_writer::end_tag('li');

        // Next arrow
        $monthNav .= \html_writer::start_tag('li', ['class' => 'page-item']);
        $monthNav .= \html_writer::link(new \moodle_url($baseurl, [
            'coursename' => $coursename,
            'groupname' => $groupname,
            'teacher' => $teacher,
            'month' => $nextmonth,
            'page' => 1
        ]), '&raquo;', ['class' => 'page-link']);
        $monthNav .= \html_writer::end_tag('li');

        $monthNav .= \html_writer::end_tag('ul');
        $monthNav .= \html_writer::end_tag('nav');

        return $monthNav;
    }


    /** 3) Group & render cards by sessionstart date */
    protected function render_cards(array $rows): string
    {
        // group by raw timestamp
        $groupsbyday = [];
        foreach ($rows as $r) {
            if (empty($r['sessionstartraw'])) {
                continue;
            }
            $day = date('Y-m-d', (int) $r['sessionstartraw']);
            $groupsbyday[$day][] = $r;
        }
        ksort($groupsbyday);
        // render
        $html = '';
        foreach ($groupsbyday as $day => $dayrows) {
            $ts = strtotime($day);
            $html .= \html_writer::tag('h3', userdate($ts, '%e %B %Y'), ['class' => 'mt-4 mb-2 label-day']);
            $html .= $this->render_from_template('local_customgroupsmanagement/card_view', ['rows' => $dayrows]);
        }
        return $html;
    }

    /** 4) “Showing X–Y of Z results” */
    protected function render_info(int $total, int $page, int $perpage): string
    {
        $start = ($page - 1) * $perpage + 1;
        $end = min($page * $perpage, $total);
        return \html_writer::tag(
            'p',
            get_string(
                'showingresults',
                'local_customgroupsmanagement',
                (object) ['start' => $start, 'end' => $end, 'total' => $total]
            ),
            ['class' => 'text-muted mb-3']
        );
    }

    /** 5) Bottom pagination */
    protected function render_pager(\moodle_url $baseurl, string $coursename, string $groupname, string $teacher, string $month, int $page, int $perpage, int $total): string
    {
        $totalpages = max(1, (int) ceil($total / $perpage));
        $p = \html_writer::start_tag('nav', ['aria-label' => 'Page navigation']);
        $p .= \html_writer::start_tag('ul', ['class' => 'pagination justify-content-center']);
        // Prev
        $prevcl = $page <= 1 ? ' disabled' : '';
        $p .= \html_writer::tag(
            'li',
            \html_writer::link(new \moodle_url($baseurl, [
                'coursename' => $coursename,
                'groupname' => $groupname,
                'teacher' => $teacher,
                'month' => $month,
                'page' => max(1, $page - 1)
            ]), '&laquo;', ['class' => 'page-link']),
            ['class' => 'page-item' . $prevcl]
        );
        // First
        $firstAct = $page === 1 ? ' active' : '';
        $p .= \html_writer::tag(
            'li',
            \html_writer::link(new \moodle_url($baseurl, [
                'coursename' => $coursename,
                'groupname' => $groupname,
                'teacher' => $teacher,
                'month' => $month,
                'page' => 1
            ]), '1', ['class' => 'page-link']),
            ['class' => 'page-item' . $firstAct]
        );
        // Sliding window
        if ($page <= 3) {
            $start = 2;
        } elseif ($page >= $totalpages - 2) {
            $start = max(2, $totalpages - 3);
        } else {
            $start = $page - 1;
        }
        $end = min($start + 2, $totalpages - 1);
        if ($start > 2) {
            $p .= \html_writer::tag('li', \html_writer::tag('span', '…', ['class' => 'page-link']), ['class' => 'page-item disabled']);
        }
        for ($i = $start; $i <= $end; $i++) {
            $act = $i === $page ? ' active' : '';
            $p .= \html_writer::tag(
                'li',
                \html_writer::link(new \moodle_url($baseurl, [
                    'coursename' => $coursename,
                    'groupname' => $groupname,
                    'teacher' => $teacher,
                    'month' => $month,
                    'page' => $i
                ]), (string) $i, ['class' => 'page-link']),
                ['class' => 'page-item' . $act]
            );
        }
        if ($end < $totalpages - 1) {
            $p .= \html_writer::tag('li', \html_writer::tag('span', '…', ['class' => 'page-link']), ['class' => 'page-item disabled']);
        }
        // Last
        if ($totalpages > 1) {
            $lastAct = $page === $totalpages ? ' active' : '';
            $p .= \html_writer::tag(
                'li',
                \html_writer::link(new \moodle_url($baseurl, [
                    'coursename' => $coursename,
                    'groupname' => $groupname,
                    'teacher' => $teacher,
                    'month' => $month,
                    'page' => $totalpages
                ]), (string) $totalpages, ['class' => 'page-link']),
                ['class' => 'page-item' . $lastAct]
            );
        }
        // Next
        $nextcl = $page >= $totalpages ? ' disabled' : '';
        $p .= \html_writer::tag(
            'li',
            \html_writer::link(new \moodle_url($baseurl, [
                'coursename' => $coursename,
                'groupname' => $groupname,
                'teacher' => $teacher,
                'month' => $month,
                'page' => min($totalpages, $page + 1)
            ]), '&raquo;', ['class' => 'page-link']),
            ['class' => 'page-item' . $nextcl]
        );
        $p .= \html_writer::end_tag('ul') . \html_writer::end_tag('nav');
        return $p;
    }
}