<?php include 'includes/header.php'; ?>
<h2>Create Schedule</h2>
<form method="POST" action="actions/add_schedule.php">
    Student ID: <input type="number" name="student_id"><br>
    Start Date: <input type="date" name="start_date"><br>
    End Date: <input type="date" name="end_date"><br>
    Hours: <input type="number" name="total_hours"><br>
    <button type="submit">Save</button>
</form>
<?php include 'includes/footer.php'; ?>