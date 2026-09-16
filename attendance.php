<?php include 'includes/header.php'; ?>
<h2>Mark Attendance</h2>
<form method="POST" action="actions/mark_attendance.php">
    Session ID: <input type="number" name="session_id"><br>
    Student ID: <input type="number" name="student_id"><br>
    <select name="status">
        <option value="attended">Attended</option>
        <option value="missed">Missed</option>
    </select><br>
    <button type="submit">Save</button>
</form>
<?php include 'includes/footer.php'; ?>