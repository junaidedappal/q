<?php
// Attendance window for non-admin users (mentors).
// Mentors can mark attendance for the current month. During the first
// ATTENDANCE_GRACE_DAYS day(s) of a new month, the previous month stays open too.
if (!defined('ATTENDANCE_GRACE_DAYS')) {
    define('ATTENDANCE_GRACE_DAYS', 1);
}

if (!function_exists('attendance_open_from_month')) {
    // Returns the earliest month (Y-m) whose sessions can still be marked.
    function attendance_open_from_month(): string
    {
        if ((int) date('j') <= ATTENDANCE_GRACE_DAYS) {
            return date('Y-m', strtotime('first day of last month'));
        }
        return date('Y-m');
    }
}
