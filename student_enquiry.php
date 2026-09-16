<?php include 'includes/header.php'; ?>
<h2>New Student Enquiry</h2>
<form method="POST" action="actions/add_enquiry.php">
    <input type="text" name="name" placeholder="Student Name" required><br>
    <input type="text" name="class" placeholder="Class" required><br>
    <input type="text" name="syllabus" placeholder="Syllabus" required><br>
    <input type="text" name="subjects" placeholder="Subjects (comma separated)" required><br>
    <input type="text" name="language" placeholder="Language" required><br>
    <input type="text" name="phone" placeholder="Phone" required><br>
    <input type="text" name="parent_phone" placeholder="Parent Phone" required><br>
    <textarea name="remarks" placeholder="Remarks"></textarea><br>
    <textarea name="performance" placeholder="Previous Performance"></textarea><br>
    <button type="submit">Submit</button>
</form>
<?php include 'includes/footer.php'; ?>