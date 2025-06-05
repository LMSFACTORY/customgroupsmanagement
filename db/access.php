<?php
defined('MOODLE_INTERNAL') || die();

// Define a custom capability to view your page.
// Administrators get everything by default, so no need to include them here.
$capabilities = [
    'local/customgroupsmanagement:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            // Default roles that should see the page
            'manager' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];