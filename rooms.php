<?php
session_start();
require_once "dbcon.php";

// Verify admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$room = ['room_id' => '', 'bid_num' => '', 'room_type' => 'Lecture']; // Initialize for form

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add/Edit Room
    if (isset($_POST['save_room'])) {
        $room_id = trim($_POST['room_id']);
        $bid_num = trim($_POST['bid_num']);
        $room_type = trim($_POST['room_type']);

        // Validate inputs
        if (empty($room_id) || empty($bid_num)) {
            $error = "Room ID and Building Number are required";
            // Repopulate $room with submitted data
            $room['room_id'] = $room_id;
            $room['bid_num'] = $bid_num;
            $room['room_type'] = $room_type;

        } else {
            try {
                // Check if adding new or editing
                if (empty($_POST['original_id'])) {
                    // Add new room
                    $stmt = $db->prepare("INSERT INTO rooms (room_id, bid_num, room_type) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $room_id, $bid_num, $room_type);
                } else {
                    // Update existing
                    $stmt = $db->prepare("UPDATE rooms SET room_id=?, bid_num=?, room_type=? WHERE room_id=?");
                    $stmt->bind_param("ssss", $room_id, $bid_num, $room_type, $_POST['original_id']);
                }
                
                if ($stmt->execute()) {
                    $success = "Room saved successfully!";
                } else {
                    $error = "Error saving room: " . $db->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
            // If there was an error during save, repopulate $room for the form
            if (!empty($error)) {
                $room['room_id'] = $room_id;
                $room['bid_num'] = $bid_num;
                $room['room_type'] = $room_type;
            }
        }
    }
    // Delete Room
    elseif (isset($_POST['delete_room'])) {
        $room_id = trim($_POST['room_id']);
        $stmt = $db->prepare("DELETE FROM rooms WHERE room_id=?");

        if ($stmt) {
            $stmt->bind_param("s", $room_id);
            try {
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = "Room deleted successfully!";
                    } else {
                        $error = "Room not found or no changes made.";
                    }
                } else {
                    $error = "Error deleting room: " . $stmt->error;
                }
            } catch (mysqli_sql_exception $e) {
                // In a typical setup, rooms might not have direct foreign key constraints
                // that prevent deletion like teachers (to courses) or courses (to marks).
                // However, if you add such constraints (e.g., room assignments to schedules),
                // you would check for $e->getCode() == 1451 here.
                // For now, a general error is shown.
                $error = "Database error deleting room: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
            } finally {
                $stmt->close();
            }
        } else {
            $error = "Error preparing delete statement: " . $db->error;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['edit'])) {
    // Edit Room (load data for GET request)
    $edit_room_id = trim($_GET['edit']);
    if (!empty($edit_room_id)) {
        $stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $edit_room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $room = $result->fetch_assoc();
            } else {
                $error = "Room not found for editing.";
                unset($_GET['edit']); // Reset GET edit parameter
            }
            $stmt->close();
        } else {
            $error = "Error preparing edit statement: " . $db->error;
        }
    } else {
        unset($_GET['edit']); // Reset GET edit parameter if it was empty
    }
}

// Fetch all rooms
$rooms = [];
$result = $db->query("SELECT * FROM rooms ORDER BY room_id");
if ($result) {
    $rooms = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management</title>
    <link rel="stylesheet" href="css/students.css">
</head>
<body>
    <div class="container">
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Room Management</h1>
            
            <!-- Status Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Room Form -->
            <div class="card">
                <h2><?php echo empty($_GET['edit']) ? 'Add New Room' : 'Edit Room'; ?></h2>
                <form method="POST">
                    <input type="hidden" name="original_id" value="<?php echo isset($_GET['edit']) ? htmlspecialchars($room['room_id']) : ''; ?>">
                    
                    <div class="form-group">
                        <label for="room_id">Room ID</label>
                        <input type="text" id="room_id" name="room_id" 
                               value="<?php echo htmlspecialchars($room['room_id']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bid_num">Building Number</label>
                        <input type="text" id="bid_num" name="bid_num" 
                               value="<?php echo htmlspecialchars($room['bid_num']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="room_type">Room Type</label>
                        <select id="room_type" name="room_type">
                            <option value="Lecture" <?php echo ($room['room_type'] == 'Lecture') ? 'selected' : ''; ?>>Lecture Room</option>
                            <option value="Lab" <?php echo ($room['room_type'] == 'Lab') ? 'selected' : ''; ?>>Laboratory</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="save_room">Save Room</button>
                    <?php if (!empty($_GET['edit'])): ?>
                        <a href="rooms.php" class="btn">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Room List -->
            <div class="card">
                <h2>Room Records</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search rooms...">
                </div>
                
                <table id="roomTable">
                    <thead>
                        <tr>
                            <th>Room ID</th>
                            <th>Building No.</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['room_id']); ?></td>
                                <td><?php echo htmlspecialchars($r['bid_num']); ?></td>
                                <td><?php echo htmlspecialchars($r['room_type']); ?></td>
                                <td>
                                    <a href="rooms.php?edit=<?php echo urlencode($r['room_id']); ?>" 
                                       class="btn btn-edit">Edit</a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($r['room_id']); ?>">
                                        <button type="submit" name="delete_room" class="btn btn-danger" 
                                                onclick="return confirm('Delete this room?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Simple client-side search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('#roomTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php $db->close(); ?>