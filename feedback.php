<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'] ?? 'User';

// Get event_id from URL parameter
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Fetch event details
$event_details = null;
if ($event_id > 0) {
    $event_sql = "SELECT e.*, c.club_name 
                  FROM events_abelities e 
                  LEFT JOIN clubs_abelities c ON e.club_id = c.id 
                  WHERE e.id = ?";
    $event_stmt = $conn->prepare($event_sql);
    $event_stmt->bind_param("i", $event_id);
    $event_stmt->execute();
    $event_result = $event_stmt->get_result();
    
    if ($event_result->num_rows > 0) {
        $event_details = $event_result->fetch_assoc();
    }
    $event_stmt->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $event_id = (int)$_POST['event_id'];
    $rating = (int)$_POST['rating'];
    $comments = trim($_POST['comments']);
    $submitted_at = date('Y-m-d H:i:s');

    // Validate inputs
    if ($event_id > 0 && $rating >= 1 && $rating <= 5 && !empty($comments)) {
        // Check if user already submitted feedback for this event
        $check_sql = "SELECT id FROM feedback_abelities WHERE student_id = ? AND event_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $event_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "You have already submitted feedback for this event.";
        } else {
            // Insert feedback into database
            $sql = "INSERT INTO feedback_abelities (student_id, event_id, rating, comments) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiss", $student_id, $event_id, $rating, $comments);
            
            if ($stmt->execute()) {
                $success = "Thank you! Your feedback has been submitted successfully.";
                // Clear form data
                $_POST = array();
            } else {
                $error = "Error submitting feedback: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    } else {
        $error = "Please fill in all required fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Event Feedback - UKMSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            box-sizing: border-box;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #6b46c1 0%, #8b5cf6 25%, #a855f7 50%, #c084fc 75%, #e879f9 100%);
        }
        
        .form-focus:focus {
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, #7c3aed, #6b46c1);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }
        
        .form-section {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .star-rating {
            display: flex;
            gap: 0.5rem;
            font-size: 2rem;
        }
        
        .star {
            cursor: pointer;
            color: #d1d5db;
            transition: all 0.2s ease;
        }
        
        .star:hover,
        .star.active {
            color: #fbbf24;
            transform: scale(1.1);
        }
        
        .star.active {
            filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.5));
        }
    </style>
</head>
<body class="bg-gray-50 min-h-full">
    
    <!-- Navigation Bar -->
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
                    <a href="my_registrations.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">My Events</a>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Error/Success Messages -->
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Error: </strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Success: </strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="text-center mb-12 form-section">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Event Feedback</h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">Share your experience and help us improve future events</p>
        </div>

        <?php if ($event_details): ?>
            <!-- Event Information Card -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8 form-section">
                <h2 class="text-2xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($event_details['title']); ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                    <div>
                        <span class="font-semibold">Organizer:</span> 
                        <span class="text-purple-600"><?php echo htmlspecialchars($event_details['club_name']); ?></span>
                    </div>
                    <div>
                        <span class="font-semibold">Date:</span> 
                        <?php echo date('F d, Y', strtotime($event_details['event_date'])); ?>
                    </div>
                    <div>
                        <span class="font-semibold">Time:</span> 
                        <?php echo date('g:i A', strtotime($event_details['event_time'])); ?>
                    </div>
                    <div>
                        <span class="font-semibold">Location:</span> 
                        <?php echo htmlspecialchars($event_details['location']); ?>
                    </div>
                </div>
            </div>

            <!-- Feedback Form -->
            <form id="feedbackForm" method="POST" action="" class="bg-white rounded-2xl shadow-xl p-8 form-section">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                
                <!-- User Information (Auto-filled) -->
                <div class="mb-8 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Your Information</h3>
                    <p class="text-sm text-gray-600">Submitting as: <span class="font-medium text-purple-600"><?php echo htmlspecialchars($student_name); ?></span></p>
                    <p class="text-xs text-gray-500 mt-1">Your feedback is anonymous to other users but visible to event organizers</p>
                </div>

                <!-- Rating Section -->
                <div class="mb-8">
                    <label class="block text-sm font-semibold text-gray-700 mb-4">How would you rate this event?</label>
                    <div class="star-rating justify-center" id="starRating">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                    <p class="text-center text-sm text-gray-600 mt-2" id="ratingText">Please select a rating</p>
                </div>

                <!-- Comments Section -->
                <div class="mb-8">
                    <label for="comments" class="block text-sm font-semibold text-gray-700 mb-2">Your Feedback</label>
                    <textarea 
                        id="comments" 
                        name="comments"
                        rows="6"
                        placeholder="Share your thoughts about the event... What did you enjoy? What could be improved?"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 form-focus resize-none"
                        required
                    ><?php echo isset($_POST['comments']) ? htmlspecialchars($_POST['comments']) : ''; ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Minimum 10 characters required</p>
                </div>

                <!-- Submission Info -->
                <div class="mb-8 bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <p class="text-sm text-gray-700">
                        <span class="font-semibold">Note:</span> Your feedback will be submitted at the current date and time. 
                        Once submitted, you cannot modify your feedback.
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button 
                        type="submit" 
                        name="submit_feedback"
                        id="submitBtn"
                        class="submit-btn text-white font-bold py-4 px-12 rounded-xl text-lg shadow-lg"
                        disabled
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Submit Feedback
                    </button>
                </div>
            </form>
        <?php else: ?>
            <!-- No Event Found -->
            <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Event Not Found</h2>
                <p class="text-gray-600 mb-6">The event you're trying to provide feedback for could not be found.</p>
                <a href="view_events.php" class="text-purple-600 hover:text-purple-700 font-medium">
                    ← Back to Events
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Star rating functionality
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('ratingInput');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        const commentsInput = document.getElementById('comments');
        
        const ratingDescriptions = {
            1: 'Poor',
            2: 'Fair',
            3: 'Good',
            4: 'Very Good',
            5: 'Excellent'
        };

        let selectedRating = 0;

        stars.forEach(star => {
            star.addEventListener('click', () => {
                selectedRating = parseInt(star.getAttribute('data-rating'));
                ratingInput.value = selectedRating;
                updateStars();
                updateRatingText();
                checkFormValidity();
            });

            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.getAttribute('data-rating'));
                highlightStars(rating);
            });
        });

        document.querySelector('.star-rating').addEventListener('mouseleave', () => {
            updateStars();
        });

        function highlightStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }

        function updateStars() {
            highlightStars(selectedRating);
        }

        function updateRatingText() {
            if (selectedRating > 0) {
                ratingText.textContent = `You rated this event: ${ratingDescriptions[selectedRating]} (${selectedRating}/5)`;
                ratingText.classList.remove('text-gray-600');
                ratingText.classList.add('text-purple-600', 'font-semibold');
            } else {
                ratingText.textContent = 'Please select a rating';
                ratingText.classList.add('text-gray-600');
                ratingText.classList.remove('text-purple-600', 'font-semibold');
            }
        }

        function checkFormValidity() {
            const commentsValid = commentsInput.value.trim().length >= 10;
            const ratingValid = selectedRating > 0;
            
            if (commentsValid && ratingValid) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        // Check form validity on comments input
        commentsInput.addEventListener('input', checkFormValidity);

        // Form validation
        const feedbackForm = document.getElementById('feedbackForm');
        
        feedbackForm.addEventListener('submit', (e) => {
            if (selectedRating === 0) {
                e.preventDefault();
                alert('Please select a rating before submitting.');
                return;
            }

            if (commentsInput.value.trim().length < 10) {
                e.preventDefault();
                alert('Please provide feedback with at least 10 characters.');
                return;
            }
        });

        // Initial check
        checkFormValidity();
    </script>
</body>
</html>