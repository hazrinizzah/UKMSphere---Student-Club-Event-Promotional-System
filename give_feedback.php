<?php
include 'config.php';

// 1. Check if user is a logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$student_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($event_id === 0) {
    die("Error: Event ID not specified.");
}

// 2. Get the student's internal ID
$student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    die("Error: Student profile not found!");
}
$student_table_id = $student_result->fetch_assoc()['id'];
$student_stmt->close();

// 3. Fetch event details
$event_sql = "SELECT e.*, c.club_name 
                  FROM events_abelities e 
                  LEFT JOIN clubs_abelities c ON e.club_id = c.id 
                  WHERE e.id = ?";
$event_stmt = $conn->prepare($event_sql);
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows === 0) {
    die("Error: Event not found.");
}
$event = $event_result->fetch_assoc();
$event_stmt->close();

// 4. Check prerequisites

// 4b. Check if student was registered
$reg_sql = "SELECT id FROM event_registrations_abelities WHERE event_id = ? AND student_id = ?";
$reg_stmt = $conn->prepare($reg_sql);
$reg_stmt->bind_param("ii", $event_id, $student_table_id);
$reg_stmt->execute();
if ($reg_stmt->get_result()->num_rows === 0) {
    die("Error: You were not registered for this event.");
}
$reg_stmt->close();

// 4c. Check if feedback already submitted
$fb_sql = "SELECT id FROM feedback_abelities WHERE event_id = ? AND student_id = ?";
$fb_stmt = $conn->prepare($fb_sql);
$fb_stmt->bind_param("ii", $event_id, $student_table_id);
$fb_stmt->execute();
if ($fb_stmt->get_result()->num_rows > 0) {
    $error = "You have already submitted feedback for this event.";
}
$fb_stmt->close();


// 5. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback']) && !isset($error)) {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']); 
    $photo_path = null;

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $error = "Invalid rating. Please select 1 to 5 stars.";
    }

    // Handle file upload
    if (isset($_FILES['feedback_photo']) && $_FILES['feedback_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/feedback/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['feedback_photo']['name'], PATHINFO_EXTENSION);
        $file_name = 'feedback_' . $event_id . '_' . $student_table_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES['feedback_photo']['tmp_name'], $target_file)) {
                $photo_path = $target_file;
            } else {
                $error = "Failed to upload photo. Please try again.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }

    if (!isset($error)) {
        // --- TIME FIX: CAPTURE CLIENT TIME ---
        // If client sent a time, use it. Otherwise fallback to server time.
        if (isset($_POST['client_submitted_at']) && !empty($_POST['client_submitted_at'])) {
            $submitted_at = $_POST['client_submitted_at'];
        } else {
            $submitted_at = date('Y-m-d H:i:s');
        }

        // UPDATED QUERY: Included 'submitted_at' column
        $sql = "INSERT INTO feedback_abelities (event_id, student_id, rating, comments, photo_path, submitted_at) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        // Added 's' to type string for the new datetime parameter
        $stmt->bind_param("iiisss", $event_id, $student_table_id, $rating, $comment, $photo_path, $submitted_at);
        
        if ($stmt->execute()) {
            header("Location: event_registered.php?success=feedback_submitted");
            exit();
        } else {
            $error = "Error submitting feedback: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- INCLUDE HEADER ---
include 'header.php';
?>

<input type="hidden" id="event-datetime-iso" value="<?php echo date('Y-m-d\TH:i:s', strtotime($event['event_date'] . ' ' . $event['event_time'])); ?>">

<style>
    /* Specific styles for the Star Rating */
    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: center;
        gap: 0.5rem;
    }
    .star-rating input[type="radio"] {
        display: none;
    }
    .star-rating label {
        color: #d1d5db; /* gray-300 */
        font-size: 2.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .star-rating input[type="radio"]:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label {
        color: #f59e0b; /* amber-500 */
        transform: scale(1.1);
    }
    
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    .border-ukm-blue { border-color: var(--ukm-blue); }
</style>

<div class="max-w-2xl mx-auto px-4 py-12 min-h-screen">
    
    <div class="mb-8">
        <a href="event_registered.php" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-ukm-blue transition-colors mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Registered Events
        </a>
        <h1 class="text-3xl font-bold text-gray-900">Give Feedback</h1>
        <p class="text-gray-600 mt-2">Share your experience for "<span class="font-semibold text-gray-800"><?php echo htmlspecialchars($event['title']); ?></span>"</p>
    </div>

    <?php if ($event): ?>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-8">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-ukm-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Event Details</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-sm text-gray-600">
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Organizer</span> 
                    <span class="text-ukm-blue font-semibold text-base"><?php echo htmlspecialchars($event['club_name']); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Location</span> 
                    <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($event['location']); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Date</span> 
                    <span class="text-gray-900 font-medium"><?php echo date('F d, Y', strtotime($event['event_date'])); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Time</span> 
                    <span class="text-gray-900 font-medium"><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r shadow-sm mb-6" role="alert">
            <div class="flex">
                <div class="py-1"><svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                <div>
                    <p class="font-bold">Error</p>
                    <p class="text-sm"><?php echo $error; ?></p>
                    <?php if ($error == "You have already submitted feedback for this event."): ?>
                        <a href="event_registered.php" class="text-sm font-bold underline mt-2 inline-block hover:text-red-900">Return to your events</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="time-warning-panel" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Feedback Unavailable:</strong> This event hasn't started or finished yet. Please come back later.
                </p>
            </div>
        </div>
    </div>

    <div id="feedback-form-container">
        <?php if (!isset($error)): ?>
            <form id="feedbackForm" method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-xl shadow-lg border border-gray-100 p-8">
                
                <input type="hidden" name="client_submitted_at" id="client_submitted_at">

                <div class="mb-8 bg-blue-50 p-4 rounded-lg border border-blue-100 flex items-start">
                    <div class="flex-shrink-0 mr-3">
                        <svg class="w-5 h-5 text-ukm-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-700">Submitting as <span class="font-bold text-ukm-blue"><?php echo htmlspecialchars($student_name); ?></span></p>
                        <p class="text-xs text-gray-500 mt-1">Your feedback helps organizers improve future events.</p>
                    </div>
                </div>

                <div class="mb-8 text-center">
                    <label class="block text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">How would you rate this event?</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 stars">★</label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars">★</label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars">★</label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars">★</label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star">★</label>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="comment" class="block text-sm font-bold text-gray-700 mb-2">Comments <span class="text-gray-400 font-normal">(Optional)</span></label>
                    <textarea 
                        id="comment" 
                        name="comment"
                        rows="5"
                        placeholder="What did you like? What could be improved?"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 focus:bg-white transition-all resize-none"
                    ></textarea>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Upload Photo <span class="text-gray-400 font-normal">(Optional)</span></label>
                    
                    <div class="relative w-full h-56">
                        
                        <label for="feedback_photo" id="uploadBox" class="flex flex-col items-center justify-center w-full h-full border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-ukm-blue hover:bg-blue-50 transition-all bg-gray-50 group">
                            
                            <div id="uploadPlaceholder" class="flex flex-col items-center justify-center pt-5 pb-6">
                                <div class="p-3 bg-white rounded-full shadow-sm group-hover:bg-blue-100 transition-colors mb-2">
                                    <svg class="w-6 h-6 text-gray-400 group-hover:text-ukm-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"></path></svg>
                                </div>
                                <p class="mb-1 text-sm text-gray-500 font-medium group-hover:text-ukm-blue">Click to upload image</p>
                                <p class="text-xs text-gray-400">JPG, PNG, GIF up to 5MB</p>
                            </div>

                            <img id="imagePreview" src="#" alt="Preview" class="hidden absolute inset-0 w-full h-full object-contain bg-white rounded-lg p-2" />
                        </label>

                        <button 
                            type="button" 
                            id="removeBtn" 
                            onclick="removeImage()" 
                            class="hidden absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1.5 shadow-md transition-colors z-10"
                            title="Remove image"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        
                        <input type="file" id="feedback_photo" name="feedback_photo" class="hidden" accept="image/*" onchange="handleFileSelect(this)">
                    </div>
                </div>
                <div class="flex justify-end">
                    <a href="event_registered.php" class="mr-4 px-6 py-3 bg-white text-gray-600 font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">Cancel</a>
                    <button 
                        type="submit" 
                        name="submit_feedback"
                        class="bg-ukm-blue hover:bg-blue-800 text-white font-bold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5"
                    >
                        Submit Feedback
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // --- 1. TIME VALIDATION LOGIC ---
    document.addEventListener("DOMContentLoaded", function() {
        const eventDateStr = document.getElementById("event-datetime-iso").value;
        const formContainer = document.getElementById("feedback-form-container");
        const warningPanel = document.getElementById("time-warning-panel");

        if (eventDateStr) {
            const eventTime = new Date(eventDateStr);
            const userTime = new Date();

            // If event is in the FUTURE, hide form
            if (userTime < eventTime) {
                if (formContainer) formContainer.style.display = 'none';
                if (warningPanel) warningPanel.classList.remove('hidden');
            }
        }
    });

    // --- 2. CAPTURE SUBMISSION TIME (FIX FOR SERVER TIME ISSUE) ---
    const feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function() {
            const now = new Date();
            // Format: YYYY-MM-DD HH:MM:SS
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timestamp = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            document.getElementById('client_submitted_at').value = timestamp;
        });
    }

    // --- 3. FILE UPLOAD LOGIC ---
    function handleFileSelect(input) {
        const placeholder = document.getElementById('uploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        const removeBtn = document.getElementById('removeBtn');
        const uploadBox = document.getElementById('uploadBox');

        if (input.files && input.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                removeBtn.classList.remove('hidden');
                placeholder.classList.add('hidden');
                
                uploadBox.classList.remove('border-dashed', 'bg-gray-50');
                uploadBox.classList.add('border-solid', 'border-gray-200');
            }

            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        const input = document.getElementById('feedback_photo');
        const placeholder = document.getElementById('uploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        const removeBtn = document.getElementById('removeBtn');
        const uploadBox = document.getElementById('uploadBox');

        input.value = '';
        preview.src = '#';
        preview.classList.add('hidden');
        removeBtn.classList.add('hidden');
        placeholder.classList.remove('hidden');

        uploadBox.classList.add('border-dashed', 'bg-gray-50');
        uploadBox.classList.remove('border-solid', 'border-gray-200');
    }
</script>

<?php include 'footer.php'; ?>