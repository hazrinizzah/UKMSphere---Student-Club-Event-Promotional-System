<?php
include 'config.php';

// 1. Check if user is a logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// FIX: Replaced ?? with isset check for older PHP versions
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

// 3. Fetch Feedback AND Event Details using a JOIN
$sql = "SELECT 
            f.rating, 
            f.comments, 
            f.photo_path, 
            e.title AS event_title, 
            e.event_date,
            c.club_name
        FROM feedback_abelities f
        JOIN events_abelities e ON f.event_id = e.id
        LEFT JOIN clubs_abelities c ON e.club_id = c.id
        WHERE f.event_id = ? AND f.student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $student_table_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // If no feedback found, redirect user to the 'Give Feedback' page
    header("Location: give_feedback.php?event_id=" . $event_id);
    exit();
}

$data = $result->fetch_assoc();
$stmt->close();

// --- INCLUDE HEADER ---
include 'header.php'; 
?>

<style>
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
</style>

<div class="max-w-3xl mx-auto px-4 py-12 min-h-screen">
    <div class="mb-6">
        <a href="event_registered.php" class="inline-flex items-center text-gray-500 hover:text-ukm-blue font-medium transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Registered Events
        </a>
    </div>

    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Submission Details</h1>
        <p class="text-gray-600 mt-2">Thank you for helping us improve campus events.</p>
    </div>

    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        
        <div class="bg-gray-50 p-6 border-b border-gray-200 text-center">
            <h2 class="text-xl font-bold text-ukm-blue"><?php echo htmlspecialchars($data['event_title']); ?></h2>
            <div class="flex items-center justify-center mt-3 text-sm text-gray-600 space-x-4">
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <?php echo htmlspecialchars($data['club_name']); ?>
                </span>
                <span class="text-gray-300">|</span>
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <?php echo date('d M Y', strtotime($data['event_date'])); ?>
                </span>
            </div>
        </div>

        <div class="p-8">
            <div class="mb-8 text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Your Rating</p>
                <div class="flex justify-center space-x-2">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <?php if($i <= $data['rating']): ?>
                            <svg class="w-12 h-12 text-yellow-400 drop-shadow-sm" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        <?php else: ?>
                            <svg class="w-12 h-12 text-gray-200" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <p class="mt-3 text-lg font-medium text-gray-800">
                    <?php 
                    switch($data['rating']) {
                        case 5: echo "Excellent!"; break;
                        case 4: echo "Very Good"; break;
                        case 3: echo "Average"; break;
                        case 2: echo "Poor"; break;
                        case 1: echo "Very Poor"; break;
                    }
                    ?>
                </p>
            </div>

            <hr class="border-gray-100 my-8">

            <div class="mb-8">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Your Comments</p>
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-100 relative">
                    <svg class="absolute top-4 left-4 w-6 h-6 text-gray-200 transform -scale-x-100" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21L14.017 18C14.017 16.8954 14.9124 16 16.017 16H19.017C19.5693 16 20.017 15.5523 20.017 15V9C20.017 8.44772 19.5693 8 19.017 8H15.017C14.4647 8 14.017 7.55228 14.017 7V3H19.017C20.6739 3 22.017 4.34315 22.017 6V15C22.017 16.6569 20.6739 18 19.017 18H16.017V21H14.017ZM5.0166 21L5.0166 18C5.0166 16.8954 5.91203 16 7.0166 16H10.0166C10.5689 16 11.0166 15.5523 11.0166 15V9C11.0166 8.44772 10.5689 8 10.0166 8H6.0166C5.46432 8 5.0166 7.55228 5.0166 7V3H10.0166C11.6735 3 13.0166 4.34315 13.0166 6V15C13.0166 16.6569 11.6735 18 10.0166 18H7.0166V21H5.0166Z"></path></svg>
                    
                    <?php if (!empty($data['comments'])): ?>
                        <p class="text-gray-700 whitespace-pre-line pl-8 leading-relaxed"><?php echo htmlspecialchars($data['comments']); ?></p>
                    <?php else: ?>
                        <p class="text-gray-400 italic pl-8">No written comments provided.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($data['photo_path'])): ?>
                <div class="mb-2">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Attached Photo</p>
                    <div class="rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-gray-50">
                        <img src="<?php echo htmlspecialchars($data['photo_path']); ?>" alt="Feedback attachment" class="w-full h-auto object-cover max-h-96 hover:opacity-95 transition-opacity">
                    </div>
                </div>
            <?php endif; ?>

        </div>
        
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-center">
            <a href="dashboard.php" class="text-ukm-blue hover:text-blue-800 font-semibold text-sm transition-colors">Return to Dashboard</a>
        </div>
    </div>
</div>

<?php 
// --- INCLUDE FOOTER ---
include 'footer.php'; 
?>
<?php $conn->close(); ?>