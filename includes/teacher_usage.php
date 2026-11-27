<?php
// Counts the records that point at a teacher.
// Deleting a teacher's user account cascades to the teachers row and from there
// (ON DELETE CASCADE) to sessions, demo assignments and payouts, so a teacher
// that has any of these must be set to Inactive instead of being deleted.

if (!function_exists('teacher_usage_counts')) {
    /**
     * @return array<string,int> label => count (only labels with count > 0)
     */
    function teacher_usage_counts(mysqli $conn, int $user_id): array
    {
        $teacher_stmt = $conn->prepare('SELECT id FROM teachers WHERE user_id = ?');
        $teacher_stmt->bind_param('i', $user_id);
        $teacher_stmt->execute();
        $teacher = $teacher_stmt->get_result()->fetch_assoc();
        if (!$teacher) {
            return [];
        }
        $teacher_id = (int) $teacher['id'];

        // table => label (teacher_subjects is only expertise, so it is safe to cascade)
        $checks = [
            'sessions' => 'class sessions',
            'mentor_demos' => 'independent demos',
            'demo_assignments' => 'demo assignments',
            'schedule_teachers' => 'schedule assignments',
            'teacher_payouts' => 'payout records',
            'teacher_payout_lines' => 'payout lines',
        ];

        $usage = [];
        foreach ($checks as $table => $label) {
            // Skip tables that don't exist on this server (they can't hold references).
            $exists = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
            if (!$exists || $exists->num_rows === 0) {
                continue;
            }
            $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table` WHERE teacher_id = ?");
            $stmt->bind_param('i', $teacher_id);
            $stmt->execute();
            $count = (int) $stmt->get_result()->fetch_row()[0];
            if ($count > 0) {
                $usage[$label] = $count;
            }
        }
        return $usage;
    }

    function teacher_usage_message(array $usage): string
    {
        $parts = [];
        foreach ($usage as $label => $count) {
            $parts[] = $count . ' ' . $label;
        }
        return 'This teacher cannot be deleted because they have ' . implode(', ', $parts)
            . '. Deleting would also erase those records. Set the teacher to Inactive instead.';
    }
}
