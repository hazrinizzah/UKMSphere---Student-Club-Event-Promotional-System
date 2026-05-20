<?php
include 'config.php';

// Check if user is logged in and is a club
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$club_name = $_SESSION['club_name'];

// Get the actual club ID from clubs_abelities table
$club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
$club_stmt = $conn->prepare($club_sql);
$club_stmt->bind_param("i", $user_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();

if ($club_result->num_rows === 0) {
    die("Error: Club profile not found!");
}

$club_data = $club_result->fetch_assoc();
$club_id = $club_data['id'];
$club_stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check which button was clicked
    if (isset($_POST['create_event']) || isset($_POST['save_draft'])) {
        
        $title = trim($_POST['eventTitle']);
        $description = trim($_POST['eventDescription']);
        $event_date = $_POST['eventDate'];
        $event_time = $_POST['eventTime'];
        $location = trim($_POST['eventLocation']);
        $category = $_POST['eventCategory'];
        $terms_conditions = trim($_POST['termsConditions']);
        $participant_limit = !empty($_POST['participantLimit']) ? (int)$_POST['participantLimit'] : NULL;

        // DETERMINE STATUS
        $event_status = isset($_POST['save_draft']) ? 'draft' : 'upcoming';

        // Handle file upload
        $poster_path = null;
        if (isset($_FILES['posterUpload']) && $_FILES['posterUpload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/posters/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['posterUpload']['name'], PATHINFO_EXTENSION);
            $file_name = 'poster_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['posterUpload']['tmp_name'], $target_file)) {
                    $poster_path = $target_file;
                } else {
                    $error = "Failed to upload poster. Please try again.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        }

        if (!isset($error)) {
            // --- TIME FIX: USE CLIENT TIMESTAMP IF AVAILABLE ---
            // If the JS successfully sent the user's real time, use it.
            // Otherwise, fall back to server time (just in case).
            if (isset($_POST['client_created_at']) && !empty($_POST['client_created_at'])) {
                $created_at_timestamp = $_POST['client_created_at'];
            } else {
                $created_at_timestamp = date('Y-m-d H:i:s');
            }
            
            $sql = "INSERT INTO events_abelities (title, description, event_date, event_time, location, category, poster_path, terms_conditions, participant_limit, club_id, created_at, event_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bind_param("ssssssssiiss", $title, $description, $event_date, $event_time, $location, $category, $poster_path, $terms_conditions, $participant_limit, $club_id, $created_at_timestamp, $event_status);
            
            if ($stmt->execute()) {
                if ($event_status === 'draft') {
                    $success = "Event saved as Draft successfully!";
                } else {
                    $success = "Event published successfully!";
                }
                // Clear form data
                $_POST = array();
            } else {
                $error = "Error creating event: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

include 'header.php';
?>

<style>
    .text-ukm-blue { color: var(--ukm-blue); }
    
    .form-focus:focus {
        box-shadow: 0 0 0 3px rgba(0, 74, 152, 0.2);
        border-color: var(--ukm-blue);
        outline: none;
    }
    
    .upload-area {
        transition: all 0.3s ease;
        border-color: #d1d5db;
    }
    
    .upload-area:hover {
        border-color: var(--ukm-blue);
        background-color: #f0f7ff;
    }
    
    .upload-area.dragover {
        border-color: var(--ukm-blue);
        background-color: #e0efff;
        transform: scale(1.02);
    }
    
    .publish-btn {
        background-color: var(--ukm-blue);
        transition: all 0.3s ease;
    }
    .publish-btn:hover {
        background-color: #003878;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 74, 152, 0.3);
    }

    /* New style for Draft Button */
    .draft-btn {
        background-color: #9ca3af; /* Gray-400 */
        transition: all 0.3s ease;
    }
    .draft-btn:hover {
        background-color: #4b5563; /* Gray-600 */
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .form-section {
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Error: </strong>
            <span class="block sm:inline"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Success: </strong>
            <span class="block sm:inline"><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <div class="text-center mb-12 form-section">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Create New Event</h1>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">Share your upcoming event with the student community</p>
    </div>

    <form id="createEventForm" method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-xl p-8 form-section border border-gray-100">
        
        <input type="hidden" name="client_created_at" id="client_created_at">

        <div class="mb-8">
            <label class="block text-sm font-bold text-gray-700 mb-3">Event Poster</label>
            <div class="upload-area border-2 border-dashed rounded-xl p-8 text-center cursor-pointer" id="uploadArea">
                <div id="uploadContent">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="text-gray-600 mb-2">Click to upload or drag and drop</p>
                    <p class="text-sm text-gray-500">PNG, JPG, GIF up to 10MB</p>
                </div>
                <div id="uploadPreview" class="hidden">
                    <img id="previewImage" class="max-w-full h-64 mx-auto rounded-lg object-contain bg-gray-50" alt="Preview">
                    <p class="text-sm text-ukm-blue font-semibold mt-3">✓ Poster uploaded successfully</p>
                </div>
            </div>
            <input type="file" id="posterUpload" name="posterUpload" class="hidden" accept="image/*">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Event Title</label>
            <input type="text" name="eventTitle" placeholder="Enter your event title" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus transition-colors" required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Event Date</label>
                <input type="date" id="eventDate" name="eventDate" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus transition-colors" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Event Time</label>
                <input type="time" id="eventTime" name="eventTime" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus transition-colors" required>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Location</label>
            <input type="text" name="eventLocation" placeholder="e.g. DECTAR" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus transition-colors" required>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Event Category</label>
            <select name="eventCategory" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus transition-colors" required>
                <option value="">Select a category</option>
                <option value="sports">Sports</option>
                <option value="academic">Academic</option>
                <option value="cultural">Cultural</option>
                <option value="social">Social</option>
                <option value="workshop">Workshop</option>
                <option value="competition">Competition</option>
            </select>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Participant Limit (Optional)</label>
            <input type="number" name="participantLimit" placeholder="Leave empty for unlimited participants" min="1" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus transition-colors">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Event Description</label>
            <textarea name="eventDescription" rows="5" placeholder="Provide a detailed description..." class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus resize-none transition-colors" required></textarea>
        </div>

        <div class="mb-8">
            <label class="block text-sm font-bold text-gray-700 mb-2">Terms & Conditions</label>
            <textarea name="termsConditions" rows="4" placeholder="Enter terms and conditions..." class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus resize-none transition-colors" required></textarea>
        </div>

        <div class="flex flex-col sm:flex-row justify-center gap-4">
            <button 
                type="submit" 
                name="save_draft"
                class="draft-btn text-white font-bold py-4 px-8 rounded-xl text-lg shadow-lg flex items-center justify-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Save as Draft
            </button>

            <button 
                type="submit" 
                name="create_event"
                class="publish-btn text-white font-bold py-4 px-12 rounded-xl text-lg shadow-lg flex items-center justify-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                Publish Event
            </button>
        </div>
    </form>
</div>

<script>
    // File upload functionality
    const uploadArea = document.getElementById('uploadArea');
    const posterUpload = document.getElementById('posterUpload');
    const uploadContent = document.getElementById('uploadContent');
    const uploadPreview = document.getElementById('uploadPreview');
    const previewImage = document.getElementById('previewImage');

    uploadArea.addEventListener('click', () => posterUpload.click());
    
    // Drag & Drop
    uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) handleFileUpload(e.dataTransfer.files[0]);
    });

    posterUpload.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleFileUpload(e.target.files[0]);
    });

    function handleFileUpload(file) {
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                uploadContent.classList.add('hidden');
                uploadPreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    }

    // Set minimum date visual constraint
    const eventDateInput = document.getElementById('eventDate');
    const today = new Date().toISOString().split('T')[0];
    eventDateInput.min = today;

    // Form validation & Confirmation
    const createEventForm = document.getElementById('createEventForm');
    
    createEventForm.addEventListener('submit', (e) => {
        
        // --- 1. SET CLIENT TIMESTAMP ---
        // Capture the user's current exact time for the 'created_at' field
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        const sqlFormatDate = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        document.getElementById('client_created_at').value = sqlFormatDate;


        // --- 2. TIME & DATE VALIDATION ---
        const eventDateVal = document.getElementById('eventDate').value;
        const eventTimeVal = document.getElementById('eventTime').value;

        if (eventDateVal && eventTimeVal) {
            // Combine date and time strings to create a Date object
            // Note: input type="date" gives YYYY-MM-DD, type="time" gives HH:MM
            const eventDateTime = new Date(eventDateVal + 'T' + eventTimeVal);
            
            // Compare with NOW
            if (eventDateTime < now) {
                e.preventDefault();
                alert('Event date and time cannot be in the past.');
                return;
            }
        }

        if (!previewImage.src || previewImage.src === '') {
            e.preventDefault();
            alert('Please upload an event poster.');
            return;
        }

        // --- 3. CHECK WHICH BUTTON WAS CLICKED ---
        const submitButton = e.submitter;
        
        if (submitButton && submitButton.name === 'create_event') {
            // Only ask for confirmation if PUBLISHING
            const isConfirmed = confirm("Are you sure you want to PUBLISH this event to all students?\n\nClick OK to Publish, Cancel to edit.");
            if (!isConfirmed) {
                e.preventDefault();
            }
        }
    });

    // Reset form on success
    <?php if (isset($success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('createEventForm').reset();
            uploadContent.classList.remove('hidden');
            uploadPreview.classList.add('hidden');
            previewImage.src = '';
        });
    <?php endif; ?>
</script>

<?php include 'footer.php'; ?>