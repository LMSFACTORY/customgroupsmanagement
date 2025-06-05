<?php
namespace local_customgroupsmanagement\manager;
defined('MOODLE_INTERNAL') || die();

use context_course;

class DataManager
{
    protected $db;
    protected $fs;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        $this->fs = get_file_storage();
    }

    /**
     * Aggregate courses and *filtered* groups into paginated rows.
     *
     * @param string $coursename
     * @param string $groupname
     * @param string $teacher
     * @param string $month
     * @param int    $page
     * @param int    $perpage
     * @return array ['rows'=>array, 'total'=>int]
     */
    // public function get_page(string $coursename, string $groupname, string $teacher, string $month, int $page, int $perpage): array
    // {
    //     $cm = new CourseManager();
    //     $gm = new GroupManager();

    //     // parse month into year/month ints
    //     [$year, $mon] = explode('-', $month);
    //     $year = (int) $year;
    //     $mon = (int) $mon;

    //     $all = [];
    //     foreach ($cm->get_all_courses() as $course) {
    //         if ($coursename !== '' && stripos($course->fullname, $coursename) === false) {
    //             continue;
    //         }

    //         // Fetch course image URL as before...
    //         $ctx = \context_course::instance($course->id);
    //         $files = $this->fs->get_area_files(
    //             $ctx->id,
    //             'course',
    //             'overviewfiles',
    //             0,
    //             'sortorder, id',
    //             false
    //         );
    //         $course->imageurl = '';
    //         if ($files) {
    //             $f = reset($files);
    //             $course->imageurl = file_encode_url(
    //                 '/pluginfile.php',
    //                 "/{$ctx->id}/course/overviewfiles/{$f->get_filename()}"
    //             );
    //         }

    //         // Only include groups that passed the session‐date filter
    //         $groups = $gm->get_groups_for_course($course->id);

    //         foreach ($groups as $group) {
    //             if ($groupname !== '' && stripos($group->name, $groupname) === false) {
    //                 continue;
    //             }

    //             // NEW: Filter by teacher
    //             if ($teacher !== '') {
    //                 $found = false;
    //                 foreach ($group->formateurs as $f) {
    //                     if (stripos($f->fullname, $teacher) !== false) {
    //                         $found = true;
    //                         break;
    //                     }
    //                 }
    //                 if (!$found) {
    //                     continue;
    //                 }
    //             }

    //             if (empty($month)) {
    //                 $month = date('Y-m');
    //             }

    //             // NEW: month filter on sessionstart OR sessionend
    //             if (empty($group->sessionstartraw) && empty($group->sessionendrraw)) {
    //                 continue;
    //             }

    //             // Build DateTime for start (if available)
    //             if ($group->sessionstartraw) {
    //                 $start = (new \DateTime())->setTimestamp($group->sessionstartraw);
    //             } else {
    //                 $start = null;
    //             }

    //             // Build DateTime for end (if available)
    //             if ($group->sessionendrraw) {
    //                 $end = (new \DateTime())->setTimestamp($group->sessionendrraw);
    //             } else {
    //                 $end = null;
    //             }

    //             // Only include if start or end falls in the selected month
    //             $match = false;
    //             if (
    //                 $start && ((int) $start->format('Y') === $year
    //                     && (int) $start->format('m') === $mon)
    //             ) {
    //                 $match = true;
    //             }
    //             if (
    //                 !$match && $end && ((int) $end->format('Y') === $year
    //                     && (int) $end->format('m') === $mon)
    //             ) {
    //                 $match = true;
    //             }
    //             if (!$match) {
    //                 continue;
    //             }

    //             $all[] = $this->make_row($course, $group);
    //         }
    //     }

    //     $total = count($all);
    //     $offset = ($page - 1) * $perpage;
    //     $rows = array_slice($all, $offset, $perpage);

    //     return ['rows' => $rows, 'total' => $total];
    // }

    /**
     * Build a single flat row combining course + one group.
     *
     * @param object $course    stdClass with course info
     * @param object $group     stdClass with group + formateurs[]
     * @return array
     */
    // protected function make_row($course, $group): array
    // {
    //     // Ensure sessionstatus is an int for comparisons
    //     $status = (int) $course->sessionstatus;

    //     return [
    //         'courseid' => $course->id,
    //         'coursefullname' => $course->fullname,
    //         'courseimage' => $course->imageurl,
    //         'coursesummary' => $course->summary ?? '',
    //         'visible' => (bool) $course->visible,
    //         'stagiairecount' => $course->stagiairecount,
    //         // Original numeric status (0,1,2)
    //         'sessionstatus' => $status,
    //         // New flags for your template logic
    //         'is_cancelled' => $status === 1,
    //         'is_active' => $status === 2,
    //         'lieu' => $course->lieu,
    //         'groupid' => $group->id,
    //         'groupname' => $group->name,
    //         'groupdescription' => $group->description,
    //         'sessionstart' => $group->sessionstart,
    //         'sessionend' => $group->sessionend,
    //         'sessionstartraw' => $group->sessionstartraw,
    //         'sessionendrraw' => $group->sessionendrraw,
    //         // Pass the full array of user objects (id, fullname, email)
    //         'formateurs' => $group->formateurs,
    //     ];
    // }

    public function get_page(
        string $coursename,
        string $groupname,
        string $teacher,
        string $month,    // format "YYYY-MM"
        int $page,
        int $perpage
    ): array {
        $cm = new CourseManager();
        $gm = new GroupManager();

        // 1) Parse selected month:
        [$year, $mon] = explode('-', $month);
        $year = (int) $year;
        $mon = (int) $mon;

        $monthStart = \DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $mon));
        $monthEnd = (clone $monthStart)
            ->modify('first day of next month')
            ->modify('-1 day');

        $all = [];

        foreach ($cm->get_all_courses() as $course) {
            // Course‐name filter
            if ($coursename !== '' && stripos($course->fullname, $coursename) === false) {
                continue;
            }

            // Fetch course image (unchanged)
            $ctx = context_course::instance($course->id);
            $files = get_file_storage()->get_area_files(
                $ctx->id,
                'course',
                'overviewfiles',
                0,
                'sortorder,id',
                false
            );
            $course->imageurl = $files
                ? file_encode_url('/pluginfile.php', "/{$ctx->id}/course/overviewfiles/" . reset($files)->get_filename())
                : '';

            // 2) Loop groups for this course:
            $groups = $gm->get_groups_for_course($course->id);
            foreach ($groups as $group) {
                // Must have both raw timestamps
                if (empty($group->sessionstartraw) || empty($group->sessionendrraw)) {
                    continue;
                }

                // Group‐name filter
                if ($groupname !== '' && stripos($group->name, $groupname) === false) {
                    continue;
                }

                // Teacher filter
                if ($teacher !== '') {
                    $found = false;
                    foreach ($group->formateurs as $f) {
                        if (stripos($f->fullname, $teacher) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        continue;
                    }
                }

                // Compute start/end DateTime for this group
                $startDt = (new \DateTime())->setTimestamp((int) $group->sessionstartraw);
                $endDt = (new \DateTime())->setTimestamp((int) $group->sessionendrraw);

                // Skip if it doesn’t overlap the selected month at all
                if ($endDt < $monthStart || $startDt > $monthEnd) {
                    continue;
                }

                // Build one “base row” for this group:
                $baseRow = $this->make_row($course, $group);
                // make_row() must include 'sessionstartraw' and 'sessionendrraw'

                // Determine the loop bounds, clamped into the chosen month:
                $loopStart = $startDt < $monthStart ? clone $monthStart : clone $startDt;
                $loopStart->setTime(0, 0, 0);

                $loopEnd = $endDt > $monthEnd ? clone $monthEnd : clone $endDt;
                $loopEnd->setTime(0, 0, 0);

                // Expand one “daily” row for each date in [loopStart..loopEnd]:
                $d = clone $loopStart;
                while ($d <= $loopEnd) {
                    $dayTs = $d->getTimestamp();

                    $dayRow = $baseRow;
                    $dayRow['sessionstartraw'] = $dayTs;

                    $all[] = $dayRow;

                    $d->modify('+1 day');
                }
            }
        }

        // ─── NEW: Sort all rows by the “sessionstartraw” timestamp ───
        // This guarantees that all rows for, say, 2025-06-10 are contiguous.
        usort($all, function ($a, $b) {
            return $a['sessionstartraw'] <=> $b['sessionstartraw'];
        });
        // ─────────────────────────────────────────────────────────────

        // 3) Now paginate that sorted “daily” list:
        $total = count($all);
        $offset = ($page - 1) * $perpage;
        $rows = array_slice($all, $offset, $perpage);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * make_row() must include at least these two raw fields so we can overwrite them above.
     */
    protected function make_row($course, $group): array
    {
        $status = (int) $course->sessionstatus;
        return [
            'courseid' => $course->id,
            'coursefullname' => $course->fullname,
            'courseimage' => $course->imageurl,
            'coursesummary' => $course->summary ?? '',
            'visible' => (bool) $course->visible,
            'stagiairecount' => $course->stagiairecount,
            'lieu' => $course->lieu,
            'sessionstatus' => $status,
            // New flags for your template logic
            'is_cancelled' => $status === 1,
            'is_active' => $status === 2,

            'groupid' => $group->id,
            'groupname' => $group->name,
            'groupdescription' => $group->description,

            // Keep the original raw timestamps here so we can override sessionstartraw for each day:
            'sessionstartraw' => (int) $group->sessionstartraw,
            'sessionendrraw' => (int) $group->sessionendrraw,

            // Localized display strings (e.g. "9 juin 2025, 09h00")
            'sessionstart' => $group->sessionstart,
            'sessionend' => $group->sessionend,

            // Array of formateurs
            'formateurs' => $group->formateurs,
        ];
    }



}