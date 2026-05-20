<?php
include 'config.php';

// 1. FORCE TIMEZONE to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if user is logged in and is a club
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$club_name = $_SESSION['club_name'];

// Get the actual club ID
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

// Get event id from GET
if (!isset($_GET['id'])) {
    die("Error: Event ID missing.");
}
$event_id = (int)$_GET['id'];

// Fetch existing event data
$event_sql = "SELECT * FROM events_abelities WHERE id=? AND club_id=?";
$event_stmt = $conn->prepare($event_sql);
$event_stmt->bind_param("ii", $event_id, $club_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows === 0) {
    die("Error: Event not found or you don't have permission to edit it.");
}

$event_data = $event_result->fetch_assoc();
$current_status = $event_data['event_status']; // Get current status
$event_stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if any valid button was clicked
    if (isset($_POST['update_event']) || isset($_POST['publish_draft'])) {
        
        $title = trim($_POST['eventTitle']);
        $description = trim($_POST['eventDescription']);
        $event_date = $_POST['eventDate'];
        $event_time = $_POST['eventTime'];
        $location = trim($_POST['eventLocation']);
        $category = $_POST['eventCategory'];
        $terms_conditions = trim($_POST['termsConditions']);
        $participant_limit = !empty($_POST['participantLimit']) ? (int)$_POST['participantLimit'] : NULL;

        // --- DETERMINE NEW STATUS ---
        // Default to keeping the old status
        $new_status = $current_status;

        // If it was a draft and user clicked "Publish", change to 'upcoming'
        if ($current_status === 'draft' && isset($_POST['publish_draft'])) {
            $new_status = 'upcoming';
        }
        // If it was a draft and user clicked "Save Draft" (update_event), it stays 'draft'
        // If it was already upcoming, it stays 'upcoming' regardless of button name

        // Handle poster upload
        $poster_path = $event_data['poster_path']; 
        if (isset($_FILES['posterUpload']) && $_FILES['posterUpload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/posters/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_extension = pathinfo($_FILES['posterUpload']['name'], PATHINFO_EXTENSION);
            $file_name = 'poster_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;

            $allowed_types = ['jpg','jpeg','png','gif'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['posterUpload']['tmp_name'], $target_file)) {
                    $poster_path = $target_file;
                } else {
                    $error = "Failed to upload poster.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        }

        if (!isset($error)) {
            // Updated SQL to include event_status
            $update_sql = "UPDATE events_abelities SET title=?, description=?, event_date=?, event_time=?, location=?, category=?, poster_path=?, terms_conditions=?, participant_limit=?, event_status=? WHERE id=? AND club_id=?";
            $stmt = $conn->prepare($update_sql);
            
            // "ssssssssisii" -> string x8, int, string, int, int
            $stmt->bind_param("ssssssssisii", $title, $description, $event_date, $event_time, $location, $category, $poster_path, $terms_conditions, $participant_limit, $new_status, $event_id, $club_id);

            if ($stmt->execute()) {
                $msg = ($new_status === 'upcoming' && $current_status === 'draft') ? "Event published successfully!" : "Event updated successfully!";
                header("Location: club_events.php?success=" . urlencode($msg));
                exit();
            } else {
                $error = "Error updating event: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

include 'header.php';
?>

<style>
    .text-ukm-blue { color: var(--ukm-blue); }
    .form-focus:focus { box-shadow: 0 0 0 3px rgba(0, 74, 152, 0.2); border-color: var(--ukm-blue); outline: none; }
    .upload-area:hover { border-color: var(--ukm-blue); background-color: #f0f7ff; }
    .update-btn { background-color: var(--ukm-blue); transition: all 0.3s ease; }
    .update-btn:hover { background-color: #003878; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 74, 152, 0.2); }
    
    /* Green Publish Button Style */
    .publish-btn { background-color: #10b981; transition: all 0.3s ease; }
    .publish-btn:hover { background-color: #059669; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }

    /* Draft Button Style */
    .draft-btn { background-color: #6b7280; transition: all 0.3s ease; }
    .draft-btn:hover { background-color: #4b5563; transform: translateY(-2px); }

    .form-section { animation: fadeInUp 0.6s ease-out; }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
</style>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 min-h-screen">
    
    <?php if(isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
            <strong class="font-bold">Error:</strong> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 form-section gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Update Event</h1>
            <p class="text-gray-600">
                Status: 
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                    <?php echo ($current_status === 'draft') ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                    <?php echo ucfirst($current_status); ?>
                </span>
            </p>
        </div>
        <a href="club_events.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors shadow-sm">
           <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
           </svg>
           Back to My Events
        </a>
    </div>

    <form id="updateEventForm" method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 form-section">
        
        <div class="mb-8">
            <label class="block text-sm font-bold text-gray-700 mb-3">Event Poster</label>
            <div class="upload-area border-2 border-dashed rounded-xl p-8 text-center cursor-pointer" id="uploadArea">
                <div id="uploadContent" <?php echo ($event_data['poster_path'] ? 'class="hidden"' : ''); ?>>
                    <p class="text-gray-600 mb-2">Click to replace poster</p>
                </div>
                <div id="uploadPreview" class="<?php echo ($event_data['poster_path'] ? '' : 'hidden'); ?>">
                    <img id="previewImage" class="max-w-full h-64 mx-auto rounded-lg object-contain bg-gray-50" alt="Preview" src="<?php echo $event_data['poster_path']; ?>">
                    <p class="text-sm text-ukm-blue font-semibold mt-3">✓ Current Poster</p>
                </div>
            </div>
            <input type="file" id="posterUpload" name="posterUpload" class="hidden" accept="image/*">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Event Title</label>
            <input type="text" name="eventTitle" value="<?php echo htmlspecialchars($event_data['title']); ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Event Date</label>
                <input type="date" id="eventDate" name="eventDate" value="<?php echo $event_data['event_date']; ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Event Time</label>
                <input type="time" name="eventTime" value="<?php echo $event_data['event_time']; ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Location</label>
            <input type="text" name="eventLocation" value="<?php echo htmlspecialchars($event_data['location']); ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Category</label>
            <select name="eventCategory" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus bg-white">
                <?php
                $categories = ['sports'=>'Sports','academic'=>'Academic','cultural'=>'Cultural','social'=>'Social','workshop'=>'Workshop','competition'=>'Competition'];
                foreach($categories as $key=>$val){
                    $selected = ($event_data['category']==$key) ? 'selected' : '';
                    echo "<option value='$key' $selected>$val</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Participant Limit</label>
            <input type="number" name="participantLimit" value="<?php echo htmlspecialchars($event_data['participant_limit']); ?>" min="1" class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
            <textarea name="eventDescription" rows="5" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus resize-none"><?php echo htmlspecialchars($event_data['description']); ?></textarea>
        </div>

        <div class="mb-8">
            <label class="block text-sm font-bold text-gray-700 mb-2">Terms & Conditions</label>
            <textarea name="termsConditions" rows="4" required class="w-full px-4 py-3 border border-gray-300 rounded-lg form-focus resize-none"><?php echo htmlspecialchars($event_data['terms_conditions']); ?></textarea>
        </div>

        <div class="flex flex-col sm:flex-row justify-center gap-4 border-t border-gray-200 pt-8">
            
            <?php if ($current_status === 'draft'): ?>
                
                <button type="submit" name="update_event" class="draft-btn text-white font-bold py-4 px-8 rounded-xl text-lg shadow-md flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Save Draft
                </button>

                <button type="submit" name="publish_draft" class="publish-btn text-white font-bold py-4 px-12 rounded-xl text-lg shadow-lg flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    Publish Event
                </button>

            <?php else: ?>
                
                <button type="submit" name="update_event" class="update-btn text-white font-bold py-4 px-12 rounded-xl text-lg shadow-lg flex items-center justify-center mx-auto">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Save Changes
                </button>

            <?php endif; ?>
            
        </div>
    </form>
</div>

<script>
    // File Upload Logic
    const uploadArea = document.getElementById('uploadArea');
    const posterUpload = document.getElementById('posterUpload');
    const uploadContent = document.getElementById('uploadContent');
    const uploadPreview = document.getElementById('uploadPreview');
    const previewImage = document.getElementById('previewImage');

    uploadArea.addEventListener('click', () => posterUpload.click());
    
    uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault(); 
        uploadArea.classList.remove('dragover'); 
        if(e.dataTransfer.files.length > 0) handleFileUpload(e.dataTransfer.files[0]);
    });
    
    posterUpload.addEventListener('change', (e) => {
        if(e.target.files.length > 0) handleFileUpload(e.target.files[0]);
    });
    
    function handleFileUpload(file){
        if(file && file.type.startsWith('image/')){
            const reader = new FileReader(); 
            reader.onload = (e) => {
                previewImage.src = e.target.result; 
                uploadContent.classList.add('hidden'); 
                uploadPreview.classList.remove('hidden');
            }; 
            reader.readAsDataURL(file);
        }
    }

    // Confirmation for Publishing Draft
    const form = document.getElementById('updateEventForm');
    form.addEventListener('submit', (e) => {
        const submitter = e.submitter;
        
        // If clicking "Publish Event", ask for confirmation
        if (submitter && submitter.name === 'publish_draft') {
            const confirmed = confirm("Are you sure you want to PUBLISH this draft now?\n\nIt will be visible to all students.");
            if (!confirmed) {
                e.preventDefault();
            }
        }
    });
</script>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>