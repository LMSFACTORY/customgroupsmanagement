<?php
namespace local_customgroupsmanagement\output;
defined('MOODLE_INTERNAL') || die();

class Group
{
    public $name;
    public $description;
    public $sessionstart;
    public $sessionend;
    public $formateurs;  // array of strings

    public function __construct(array $data)
    {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }
}