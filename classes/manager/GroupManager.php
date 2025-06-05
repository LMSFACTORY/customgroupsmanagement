<?php
namespace local_customgroupsmanagement\manager;
defined('MOODLE_INTERNAL') || die();

class GroupManager
{
    /** @var \moodle_database */
    protected $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Fetch only those groups that have both sessionstart & sessionend,
     * and include for each group all users whose role shortname is 'teacher'
     * or 'teachercertif' (with id, fullname, email).
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    public function get_groups_for_course(int $courseid): array
    {
        global $OUTPUT;
        $groups = $this->db->get_records('groups', ['courseid' => $courseid]);

        // 1) Get exactly the two roles we care about.
        $roles = $this->db->get_records_select(
            'role',
            "shortname = :t OR shortname = :c",
            ['t' => 'teacher', 'c' => 'teachercertif'],
            '',
            'id'
        );
        $roleids = $roles ? array_keys($roles) : [0];
        $roleidslist = implode(',', array_map('intval', $roleids));

        $result = [];
        foreach ($groups as $group) {
            // 2) Load custom group fields
            $sql = "
                SELECT cf.shortname, cd.value
                  FROM {customfield_data} cd
                  JOIN {customfield_field} cf ON cf.id = cd.fieldid
                  JOIN {customfield_category} cat ON cat.id = cf.categoryid
                 WHERE cd.instanceid = :groupid
                   AND cat.component = 'core_group'
                   AND cat.area = 'group'
                   AND cf.shortname IN ('sessionstart','sessionend')
            ";
            $records = $this->db->get_records_sql($sql, ['groupid' => $group->id]);
            $fields = [];
            foreach ($records as $r) {
                $fields[$r->shortname] = $r->value;
            }


            // RAW values from DB (custom date fields store UNIX timestamps)
            $rawstart = $fields['sessionstart'] ?? '';
            $rawend = $fields['sessionend'] ?? '';

            // Cast to int (0 if missing)
            $group->sessionstartraw = is_numeric($rawstart) ? (int) $rawstart : 0;
            $group->sessionendrraw = is_numeric($rawend) ? (int) $rawend : 0;

            // LOCALIZED display strings
            if ($group->sessionstartraw) {
                // e.g. "21 mars 2025" in user’s locale
                $group->sessionstart = userdate(
                    $group->sessionstartraw,
                    '%e %B %Y , %Hh%M'
                );
            } else {
                $group->sessionstart = '';
            }

            if ($group->sessionendrraw) {
                $group->sessionend = userdate(
                    $group->sessionendrraw,
                    '%e %B %Y , %Hh%M'
                );
            } else {
                $group->sessionend = '';
            }

            if ($group->sessionstart === '' || $group->sessionend === '' || $group->sessionstart === '0' || $group->sessionend === '0') {
                continue;
            }

            // 4) Fetch formateur + teacher users in this group
            $sql = "
                SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                  FROM {groups_members} gm
                  JOIN {user} u ON u.id = gm.userid
                  JOIN {role_assignments} ra ON ra.userid = u.id
                 WHERE gm.groupid = :groupid
                   AND ra.roleid IN ($roleidslist)
            ";
            $users = $this->db->get_records_sql($sql, ['groupid' => $group->id]);

            // 5) Build an array of user info
            $group->formateurs = [];
            foreach ($users as $u) {
                $group->formateurs[] = (object) [
                    'id' => $u->id,
                    'fullname' => fullname($u),
                    'email' => $u->email,
                    'pictureurl' => $OUTPUT->user_picture($u, ['size' => 35, 'link' => false]),
                ];
            }

            $result[] = $group;
        }

        return $result;
    }
}