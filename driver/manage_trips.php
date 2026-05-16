<?php
require_once '../config.php';
require_once '../includes/NotificationManager.php';
require_once '../includes/TripNotifications.php';

requireRole('driver');

$user_id = $_SESSION['user_id'];
$message = '';

// Initialize notification system
$notificationManager = new NotificationManager($conn);
$tripNotifications = new TripNotifications($conn, $notificationManager);

// Ensure request support exists in the students table.
if ($conn->query("SHOW COLUMNS FROM students LIKE 'requested_driver_id'")->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN requested_driver_id INT NULL, ADD COLUMN driver_request_status ENUM('pending','accepted','declined') DEFAULT NULL");
}

// Handle driver request approvals and trip creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['accept_request'])) {
        $student_id = (int)$_POST['student_id'];
        $update = $conn->query("UPDATE students SET driver_id = $user_id, driver_request_status = 'accepted' WHERE id = $student_id AND requested_driver_id = $user_id");
        if ($update) {
            $tripNotifications->notifyParentDriverAccepted($student_id, $user_id);
            $message = 'Request accepted. Student is now assigned to you and the parent has been notified.';
        } else {
            $message = 'Unable to accept the request. Please try again.';
        }
    } elseif (isset($_POST['decline_request'])) {
        $student_id = (int)$_POST['student_id'];
        $conn->query("UPDATE students SET driver_request_status = 'declined' WHERE id = $student_id AND requested_driver_id = $user_id");
        $message = 'Request declined.';
    } elseif (isset($_POST['create_trip'])) {
        $student_id = (int)$_POST['student_id'];
        $trip_date = sanitize($_POST['trip_date']);
        $pickup_time = sanitize($_POST['pickup_time']);

        // Verify student belongs to this driver
        $student_check = $conn->query("SELECT id FROM students WHERE id = $student_id AND driver_id = $user_id");
        if ($student_check->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO trips (driver_id, student_id, trip_date, pickup_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $user_id, $student_id, $trip_date, $pickup_time);
            if ($stmt->execute()) {
                $trip_id = $stmt->insert_id;
                // Send notification to driver about new trip
                $tripNotifications->notifyDriverNewTrip($trip_id, $user_id);
                $message = 'Trip created successfully!';
            }
        } else {
            $message = 'Invalid student selection.';
        }
    }
}

// Get pending driver requests
$pending_requests = $conn->query("SELECT s.*, u.full_name as parent_name FROM students s JOIN users u ON s.parent_id = u.id WHERE s.requested_driver_id = $user_id AND s.driver_request_status = 'pending'");

// Get assigned students
$students = $conn->query("SELECT * FROM students WHERE driver_id = $user_id");

// Get upcoming trips
$upcoming_trips = $conn->query("
    SELECT t.*, s.name as student_name, s.address
    FROM trips t
    JOIN students s ON t.student_id = s.id
    WHERE t.driver_id = $user_id AND t.trip_date >= CURDATE() AND t.status != 'completed'
    ORDER BY t.trip_date, t.pickup_time
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trips - Driver</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="nav">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="manage_trips.php">Manage Trips</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="trip_history.php">Trip History</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <h1>Manage Trips</h1>

        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>

        <h2>Pending Driver Requests</h2>
        <?php if ($pending_requests->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Schedule</th>
                        <th>Parent</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($request = $pending_requests->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['name']); ?></td>
                            <td><?php echo htmlspecialchars($request['schedule'] ?: 'Not set'); ?></td>
                            <td><?php echo htmlspecialchars($request['parent_name']); ?></td>
                            <td>Pending</td>
                            <td>
                                <form method="POST" style="display:inline-block; margin-right: 10px;">
                                    <input type="hidden" name="student_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="accept_request" style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">Accept</button>
                                </form>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="student_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="decline_request" style="background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">Decline</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No pending driver requests at the moment.</p>
        <?php endif; ?>

        <h2>Create New Trip</h2>
        <form method="POST" class="trip-form">
            <div class="form-group">
                <label for="student_id">Select Student:</label>
                <select id="student_id" name="student_id" required>
                    <option value="">Choose a student</option>
                    <?php
                    $students->data_seek(0);
                    while ($student = $students->fetch_assoc()): ?>
                        <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> - <?php echo $student['schedule'] ? htmlspecialchars($student['schedule']) : htmlspecialchars($student['address']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="trip_date">Trip Date:</label>
                <input type="date" id="trip_date" name="trip_date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="pickup_time">Pickup Time:</label>
                <input type="time" id="pickup_time" name="pickup_time" required>
            </div>


            <button type="submit" name="create_trip">Create Trip</button>
        </form>

        <h2>Upcoming Trips</h2>
        <?php if ($upcoming_trips->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Pickup Time</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($trip = $upcoming_trips->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($trip['trip_date'])); ?></td>
                            <td><?php echo $trip['student_name']; ?></td>
                            <td><?php echo $trip['pickup_time']; ?></td>
                            <td><?php echo substr($trip['address'], 0, 50) . (strlen($trip['address']) > 50 ? '...' : ''); ?></td>
                            <td><span class="status-<?php echo $trip['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $trip['status'])); ?></span></td>
                            <td>
                                <?php if ($trip['status'] == 'in_transit'): ?>
                                    <a href="dropoff_trip.php?trip_id=<?php echo $trip['id']; ?>" style="background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; text-decoration: none; display: inline-block;">Drop Off</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No upcoming trips scheduled.</p>
        <?php endif; ?>
    </div>

</body>
</html>