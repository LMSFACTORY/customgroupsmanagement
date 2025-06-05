<?php
namespace local_customgroupsmanagement\manager;
defined('MOODLE_INTERNAL') || die();

class CourseManager
{
    /** @var \moodle_database */
    protected $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Fetch all courses with enrolment and custom field info.
     * @return \stdClass[]
     */
    public function get_all_courses(): array
    {
        // Basic course records
        $courses = $this->db->get_records('course', [], 'id', 'id, fullname, visible');

        // Fetch role id for "stagiaire"
        $stagiaireRole = $this->db->get_field('role', 'id', ['shortname' => 'student']);

        foreach ($courses as $course) {
            // 1) Count stagiaire enrollments
            if ($stagiaireRole) {
                $sql = "SELECT COUNT(1)
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                         WHERE ra.roleid = :roleid
                           AND ctx.contextlevel = :ctxlvl
                           AND ctx.instanceid = :courseid";
                $course->stagiairecount = (int) $this->db->count_records_sql($sql, [
                    'roleid' => $stagiaireRole,
                    'ctxlvl' => CONTEXT_COURSE,
                    'courseid' => $course->id
                ]);
            } else {
                $course->stagiairecount = 0;
            }

            // 2) Fetch custom course fields: sessionstatus & lieu_distance
            $sql = "SELECT cf.shortname, cd.value
                      FROM {customfield_data} cd
                      JOIN {customfield_field} cf ON cf.id = cd.fieldid
                      JOIN {customfield_category} cat ON cat.id = cf.categoryid
                     WHERE cd.instanceid = :instanceid
                       AND cat.component = 'core_course'
                       AND cat.area = 'course'
                       AND cf.shortname IN ('annulee','lieu')";
            $records = $this->db->get_records_sql($sql, ['instanceid' => $course->id]);

            // Map results by shortname
            $fields = [];
            foreach ($records as $r) {
                $fields[$r->shortname] = $r->value;
            }

            // 3) Assign values to the annulee as it returns an integer it should be converted to the corresponding value. i.e if 1 it should be Annulée and if 2 it should be Maintenue.

            // if (isset($fields['annulee'])) {
            //     $fields['annulee'] = ($fields['annulee'] == 1) ? 'Annulée' : 'Maintenue';
            // }

            $course->sessionstatus = $fields['annulee'] ?? '';
            $course->lieu = $fields['lieu'] ?? '';
        }

        return array_values($courses);
    }
}