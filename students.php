<?php include 'includes/header.php'; ?>
<h2>Students</h2>
<table border="1">
    <tr>
        <th>Name</th>
        <th>Class</th>
        <th>Mentor</th>
        <th>Assign</th>
    </tr>
    <?php
$stmt = $pdo->query("SELECT s.*, m.user_id as mentor FROM students s LEFT JOIN mentors m ON s.mentor_id=m.id");
while ($row = $stmt->fetch()) {
    echo "<tr>
      <td>{$row['name']}</td><td>{$row['class']}</td><td>{$row['mentor']}</td>
      <td><form method='POST' action='actions/assign_mentor.php'>
        <input type='hidden' name='student_id' value='{$row['id']}'>
        <input type='number' name='mentor_id'>
        <button type='submit'>Assign</button>
      </form></td></tr>";
}
?>
</table>
<?php include 'includes/footer.php'; ?>