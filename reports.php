<?php include 'includes/header.php'; ?>
<h2>Payout Reports</h2>
<h3>Mentors</h3>
<?php
$m = $pdo->query("SELECT * FROM mentor_payouts")->fetchAll();
foreach($m as $row) echo "<p>Mentor {$row['mentor_id']} - {$row['total_amount']}</p>";
?>
<h3>Teachers</h3>
<?php
$t = $pdo->query("SELECT * FROM teacher_payouts")->fetchAll();
foreach($t as $row) echo "<p>Teacher {$row['teacher_id']} - {$row['total_amount']}</p>";
?>
<?php include 'includes/footer.php'; ?>