<?php
namespace local_customgroupsmanagement\output;
defined('MOODLE_INTERNAL') || die();

class Course
{
    public $id;
    public $fullname;
    public $visible;
    public $stagiairecount;
    public $sessionstatus;
    public $lieu;
    public $groups;  // array of Group objects

    public function __construct(array $data)
    {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }
}