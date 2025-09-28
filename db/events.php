<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => 'local_assign_ai\observer::submission_graded',
        'includefile' => '/local/assign_ai/classes/observer.php',
        'internal' => false,
        'priority' => 9999,
    ],
];
