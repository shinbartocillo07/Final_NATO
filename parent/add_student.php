<?php
require_once '../config.php';
requireRole('parent');

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $grade = sanitize($_POST['grade']);
    $address = sanitize($_POST['address']);
    $schedule = sanitize($_POST['schedule']);

    $stmt = $conn->prepare("INSERT INTO students (name, grade, address, parent_id, schedule) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $name, $grade, $address, $user_id, $schedule);

    if ($stmt->execute()) {
        $message = 'Student added successfully!';
    } else {
        $message = 'Error adding student.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - Parent</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="nav">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="add_student.php">Add Student</a></li>
                <li><a href="select_driver.php">Select Driver</a></li>
                <li><a href="trip_history.php">Trip History</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <h1>Add Student</h1>

        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Student Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="grade">Grade Level:</label>
                <select id="grade" name="grade" required>
                    <option value="">Select School Level and Grade</option>
                    <optgroup label="Elementary">
                        <option value="Elementary - Grade 1">Grade 1</option>
                        <option value="Elementary - Grade 2">Grade 2</option>
                        <option value="Elementary - Grade 3">Grade 3</option>
                        <option value="Elementary - Grade 4">Grade 4</option>
                        <option value="Elementary - Grade 5">Grade 5</option>
                        <option value="Elementary - Grade 6">Grade 6</option>
                    </optgroup>
                    <optgroup label="High School">
                        <option value="High School - Grade 7">Grade 7</option>
                        <option value="High School - Grade 8">Grade 8</option>
                        <option value="High School - Grade 9">Grade 9</option>
                        <option value="High School - Grade 10">Grade 10</option>
                    </optgroup>
                    <optgroup label="Senior High">
                        <option value="Senior High - Grade 11">Grade 11</option>
                        <option value="Senior High - Grade 12">Grade 12</option>
                    </optgroup>
                    <optgroup label="College">
                        <option value="College - 1st Year">1st Year</option>
                        <option value="College - 2nd Year">2nd Year</option>
                        <option value="College - 3rd Year">3rd Year</option>
                        <option value="College - 4th Year">4th Year</option>
                        <option value="College - 5th Year">5th Year</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address" required></textarea>
            </div>
            <div class="form-group">
                <label for="schedule">Schedule (Pickup/Dropoff times):</label>
                <textarea id="schedule" name="schedule" placeholder="e.g., Pickup: 7:00 AM, Dropoff: 4:00 PM"></textarea>
            </div>
            <button type="submit">Add Student</button>
        </form>
    </div>
</body>
</html>