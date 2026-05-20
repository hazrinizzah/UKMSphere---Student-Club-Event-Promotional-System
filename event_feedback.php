<?php
include 'config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Get student ID from students_abelities table
$student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    die("Error: Student profile not found!");
}

$student_data = $student_result->fetch_assoc();
$student_table_id = $student_data['id'];
$student_stmt->close();

// Close connection
$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registered Events - UKMSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
<style>
        body {
            box-sizing: border-box;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #6b46c1 0%, #8b5cf6 25%, #a855f7 50%, #c084fc 75%, #e879f9 100%);
        }
        
        .event-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }
        
        .category-sports { background-color: #fef3c7; color: #d97706; }
        .category-academic { background-color: #dbeafe; color: #1d4ed8; }
        .category-cultural { background-color: #f3e8ff; color: #7c3aed; }
        .category-social { background-color: #fce7f3; color: #db2777; }
        .category-workshop { background-color: #dcfce7; color: #16a34a; }
        .category-competition { background-color: #ffedd5; color: #ea580c; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="gradient-bg shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <!-- Logo and System Name -->
                <div class="flex items-center space-x-3">
                    <img src="images/ukm-logo.png" alt="UKM Logo" class="h-28 w-40">
                    <div>
                        <h1 class="text-white text-lg font-bold">UKMSphere</h1>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="flex items-center space-x-2">
                    <a href="dashboard.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">Dashboard</a>
                    <a href="view_events.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">Event Feed</a>
                    <a href="event_registered.php" class="text-white bg-purple-700 px-3 py-2 rounded text-sm font-medium">Event Registered</a>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Feedback Form</h1>
            <form action="submit_feedback.php" method="post">
        	<label for="event_id">Event ID:</label><br>
        	<input type="number" name="event_id" id="event_id" required><br><br>

	        <label for="student_id">Student ID:</label><br>
	        <input type="number" name="student_id" id="student_id" required><br><br>

        	<label for="rating">Rating (1–5):</label><br>
        	<input type="number" name="rating" id="rating" min="1" max="5" required><br><br>

        	<label for="comments">Comments (optional):</label><br>
        	<textarea name="comments" id="comments" rows="4" cols="40"></textarea><br><br>

        	<input type="submit" value="Submit Feedback">
    		</form>
        </div>
    </div>

</body>
</html>
