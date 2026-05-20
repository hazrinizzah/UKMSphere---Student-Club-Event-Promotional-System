<?php
include 'config.php';

// 1. Security & Role Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'hep') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION (SAVE/UPDATE REPORT) ---
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_report'])) {
    $p_event_id = intval($_POST['event_id']);
    $p_club_id = intval($_POST['club_id']);
    $p_title = trim($_POST['report_title']);
    $p_content = trim($_POST['report_content']); 
    $p_status = $_POST['report_status']; 

    // STEP 1: Get the valid HEP ID (Fixes Foreign Key Error)
    $hep_lookup_sql = "SELECT id FROM hep_abelities WHERE user_id = ?";
    $hep_lookup_stmt = $conn->prepare($hep_lookup_sql);
    $hep_lookup_stmt->bind_param("i", $user_id);
    $hep_lookup_stmt->execute();
    $hep_result = $hep_lookup_stmt->get_result();
    
    if ($hep_result->num_rows === 0) {
        die("Error: Your account is not registered in the HEP profile table.");
    }

    $valid_hep_id = $hep_result->fetch_assoc()['id'];
    $hep_lookup_stmt->close();
    
    // STEP 2: Check if report exists
    $check_sql = "SELECT id FROM reports_abelities WHERE event_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $p_event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // UPDATE existing report
        $report_id = $check_result->fetch_assoc()['id'];
        $update_sql = "UPDATE reports_abelities SET title=?, content=?, status=?, updated_at=NOW() WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $p_title, $p_content, $p_status, $report_id);
        
        if ($update_stmt->execute()) {
            $success_msg = "Report updated successfully (" . ucfirst($p_status) . ").";
        } else {
            $error_msg = "Error updating report: " . $conn->error;
        }
    } else {
        // INSERT new report
        $insert_sql = "INSERT INTO reports_abelities (title, content, club_id, event_id, submitted_by_hep, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssiiis", $p_title, $p_content, $p_club_id, $p_event_id, $valid_hep_id, $p_status);
        
        if ($insert_stmt->execute()) {
            $success_msg = "Report created successfully (" . ucfirst($p_status) . ").";
        } else {
            $error_msg = "Error creating report: " . $conn->error;
        }
    }
}

// 2. Handle Report Generation (Detail View)
$report_event_id = isset($_GET['generate_id']) ? intval($_GET['generate_id']) : null;
if (!$report_event_id && isset($_POST['event_id'])) {
    $report_event_id = intval($_POST['event_id']);
}

$show_report = false;
$report_data = null;
$saved_report_data = null;
$is_finalized = false; // Lock flag

if ($report_event_id) {
    $show_report = true;
    
    // Fetch Event Details
    $evt_sql = "SELECT e.*, c.club_name, c.id as club_primary_id 
                FROM events_abelities e
                JOIN clubs_abelities c ON e.club_id = c.id
                WHERE e.id = ?";
    $evt_stmt = $conn->prepare($evt_sql);
    $evt_stmt->bind_param("i", $report_event_id);
    $evt_stmt->execute();
    $report_data = $evt_stmt->get_result()->fetch_assoc();
    $evt_stmt->close();

    // Fetch Saved Report Data
    $rep_sql = "SELECT * FROM reports_abelities WHERE event_id = ?";
    $rep_stmt = $conn->prepare($rep_sql);
    $rep_stmt->bind_param("i", $report_event_id);
    $rep_stmt->execute();
    $saved_report_data = $rep_stmt->get_result()->fetch_assoc();
    $rep_stmt->close();

    // Set defaults
    $form_title = $saved_report_data ? $saved_report_data['title'] : "Post-Mortem: " . $report_data['title'];
    $form_content = $saved_report_data ? $saved_report_data['content'] : "";
    $form_status = $saved_report_data ? $saved_report_data['status'] : "new";

    // --- LOCKING LOGIC ---
    // If status is 'submitted', prevent further edits
    if ($form_status === 'submitted') {
        $is_finalized = true;
    }

    // Fetch Stats
    $reg_sql = "SELECT COUNT(*) as total FROM event_registrations_abelities WHERE event_id = ?";
    $reg_stmt = $conn->prepare($reg_sql);
    $reg_stmt->bind_param("i", $report_event_id);
    $reg_stmt->execute();
    $total_participants = $reg_stmt->get_result()->fetch_assoc()['total'];
    $reg_stmt->close();

    // Fetch Feedback Stats
    $feed_sql = "SELECT COUNT(*) as count, AVG(rating) as avg_rating 
                 FROM feedback_abelities WHERE event_id = ?";
    $feed_stmt = $conn->prepare($feed_sql);
    $feed_stmt->bind_param("i", $report_event_id);
    $feed_stmt->execute();
    $feed_stats = $feed_stmt->get_result()->fetch_assoc();
    $feed_stmt->close();

    // Fetch Comments
    $comments_sql = "SELECT f.rating, f.comments, f.photo_path, f.submitted_at, s.full_name, s.matric_number
                     FROM feedback_abelities f
                     JOIN students_abelities s ON f.student_id = s.id
                     WHERE f.event_id = ? ORDER BY f.rating DESC";
    $comments_stmt = $conn->prepare($comments_sql);
    $comments_stmt->bind_param("i", $report_event_id);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();
    $comments_stmt->close();
}

// 3. Handle Main List View
if (!$show_report) {
    // --- UPDATED QUERY HERE ---
    // Now filters by 'finished' status OR if date is today/past.
    $list_sql = "SELECT e.id, e.title, e.event_date, e.location, c.club_name,
                (SELECT COUNT(*) FROM event_registrations_abelities er WHERE er.event_id = e.id) as participant_count,
                (SELECT AVG(rating) FROM feedback_abelities f WHERE f.event_id = e.id) as avg_rating,
                (SELECT status FROM reports_abelities r WHERE r.event_id = e.id LIMIT 1) as report_status
                FROM events_abelities e
                JOIN clubs_abelities c ON e.club_id = c.id
                WHERE (e.event_status = 'finished' OR e.event_date <= CURDATE())
                ORDER BY e.event_date DESC";
    $list_result = $conn->query($list_sql);
}

include 'header.php'; 
?>

<style>
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    .border-ukm-blue { border-color: var(--ukm-blue); }
    
    /* Input Styling for Read-Only Mode */
    input[readonly], textarea[readonly] {
        background-color: transparent;
        border: none;
        resize: none;
        cursor: default;
    }
    /* Hide the dashed border on the title when finalized */
    input[readonly].report-title-input {
        border-bottom: none;
    }

    @media print {
        body * { visibility: hidden; }
        .report-container, .report-container * { visibility: visible; }
        .report-container {
            position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; border: none; shadow: none;
        }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .no-print, nav, button, .form-actions { display: none !important; }
        
        textarea { display: none; }
        .print-content-view { display: block !important; }
        input { border: none; background: transparent; font-weight: bold; width: 100%; }
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-8 min-h-screen">

    <?php if ($success_msg): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative no-print">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?php echo $success_msg; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$show_report): ?>
        
        <div class="flex flex-col md:flex-row justify-between items-end mb-8 border-b border-gray-200 pb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Event Reports</h1>
                <p class="text-gray-600 mt-2">Manage and finalize post-mortem reports.</p>
            </div>
        </div>

        <?php if ($list_result && $list_result->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Stats</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($row = $list_result->fetch_assoc()): ?>
                                <tr class="hover:bg-blue-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo date('d M Y', strtotime($row['event_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($row['title']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['club_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            // FIX: Using isset check AND Switch statement for older PHP
                                            $status = isset($row['report_status']) ? $row['report_status'] : 'new'; 
                                            $badge_color = '';
                                            
                                            switch($status) {
                                                case 'submitted':
                                                    $badge_color = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'reviewed':
                                                    $badge_color = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'draft':
                                                    $badge_color = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                default:
                                                    $badge_color = 'bg-gray-100 text-gray-800';
                                            }
                                        ?>
                                        <span class="<?php echo $badge_color; ?> text-xs font-bold px-2.5 py-0.5 rounded uppercase">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                        <div><?php echo $row['participant_count']; ?> pax</div>
                                        <div class="text-xs text-yellow-500 font-bold">★ <?php echo number_format($row['avg_rating'], 1); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="reports.php?generate_id=<?php echo $row['id']; ?>" class="text-ukm-blue hover:text-blue-800 font-bold hover:underline">
                                            <?php 
                                                // Change button text based on status
                                                if ($status == 'submitted') echo 'View Report';
                                                elseif ($status == 'draft') echo 'Edit Draft';
                                                else echo 'Create Report';
                                            ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-xl shadow-sm border border-gray-200">
                <p class="text-gray-500">No finished events found.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        
        <div class="mb-6 no-print flex justify-between items-center">
            <a href="reports.php" class="inline-flex items-center text-gray-500 hover:text-ukm-blue font-medium transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to List
            </a>
            <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-900 text-white px-4 py-2 rounded-lg font-bold shadow flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print PDF
            </button>
        </div>

        <form method="POST" action="reports.php" class="report-container bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
            <input type="hidden" name="event_id" value="<?php echo $report_event_id; ?>">
            <input type="hidden" name="club_id" value="<?php echo $report_data['club_primary_id']; ?>">
            
            <div class="bg-gray-50 border-b border-gray-200 p-8 flex justify-between items-start">
                <div class="flex items-center gap-4 w-full">
                    <img src="images/ukm-logo.png" alt="UKM Logo" class="h-20 w-auto">
                    <div class="w-full">
                        <input type="text" name="report_title" 
                               value="<?php echo htmlspecialchars($form_title); ?>" 
                               class="report-title-input text-2xl font-extrabold text-ukm-blue uppercase tracking-wide bg-transparent border-b border-dashed border-gray-300 focus:border-ukm-blue focus:outline-none w-full mb-1"
                               <?php echo $is_finalized ? 'readonly' : ''; ?>>
                        
                        <p class="text-sm text-gray-600 font-semibold">UKMSphere Activity Management System</p>
                        
                        <div class="mt-2 flex items-center gap-3">
                            <span class="text-xs text-gray-500">Generated: <?php echo date('d F Y'); ?></span>
                            <span class="px-2 py-0.5 rounded text-xs font-bold uppercase 
                                <?php echo ($form_status == 'submitted') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                Status: <?php echo $form_status; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                
                <div class="mb-10">
                    <h3 class="text-lg font-bold text-gray-800 border-b-2 border-gray-200 pb-2 mb-6 uppercase flex items-center">
                        <span class="bg-ukm-blue text-white w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">1</span> 
                        Event Details
                    </h3>
                    <div class="grid grid-cols-2 gap-y-6 gap-x-12">
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Event Title</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($report_data['title']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Organizer</p>
                            <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($report_data['club_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Date & Time</p>
                            <p class="font-medium text-gray-900 text-lg">
                                <?php echo date('d F Y', strtotime($report_data['event_date'])); ?> 
                                <span class="text-gray-300 mx-2">|</span> 
                                <?php echo date('h:i A', strtotime($report_data['event_time'])); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Venue</p>
                            <p class="font-medium text-gray-900 text-lg"><?php echo htmlspecialchars($report_data['location']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mb-10 break-inside-avoid">
                    <h3 class="text-lg font-bold text-gray-800 border-b-2 border-gray-200 pb-2 mb-6 uppercase flex items-center">
                        <span class="bg-ukm-blue text-white w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">2</span> 
                        Executive Summary / HEP Remarks
                    </h3>
                    
                    <textarea name="report_content" rows="6" 
                        class="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-yellow-50 text-gray-800"
                        placeholder="Enter the official executive summary, observations, or final remarks here..."
                        <?php echo $is_finalized ? 'readonly' : ''; ?>><?php echo htmlspecialchars($form_content); ?></textarea>
                    
                    <div class="print-content-view hidden text-justify text-gray-800 leading-relaxed whitespace-pre-wrap">
                        <?php echo $form_content ? nl2br(htmlspecialchars($form_content)) : "No remarks added."; ?>
                    </div>
                </div>

                <div class="mb-10 break-inside-avoid">
                    <h3 class="text-lg font-bold text-gray-800 border-b-2 border-gray-200 pb-2 mb-6 uppercase flex items-center">
                        <span class="bg-ukm-blue text-white w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">3</span> 
                        Participation Data
                    </h3>
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-gray-50 p-5 rounded-xl border border-gray-100 text-center">
                            <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Total Registered</p>
                            <p class="text-4xl font-extrabold text-ukm-blue mt-2"><?php echo $total_participants; ?></p>
                        </div>
                        <div class="bg-gray-50 p-5 rounded-xl border border-gray-100 text-center">
                            <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Fill Rate</p>
                            <p class="text-4xl font-extrabold text-green-600 mt-2">
                                <?php 
                                    if ($report_data['participant_limit'] > 0) {
                                        echo round(($total_participants / $report_data['participant_limit']) * 100) . '%';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </p>
                        </div>
                        <div class="bg-gray-50 p-5 rounded-xl border border-gray-100 text-center">
                            <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Avg Rating</p>
                            <p class="text-4xl font-extrabold text-yellow-500 mt-2"><?php echo number_format($feed_stats['avg_rating'], 1); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-800 border-b-2 border-gray-200 pb-2 mb-6 uppercase flex items-center">
                        <span class="bg-ukm-blue text-white w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">4</span> 
                        Feedback Log
                    </h3>
                    
                    <?php if ($comments_result->num_rows > 0): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-bold text-gray-600">Student</th>
                                        <th class="px-4 py-3 text-left font-bold text-gray-600">Comment</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <?php while($com = $comments_result->fetch_assoc()): ?>
                                        <tr class="break-inside-avoid">
                                            <td class="px-4 py-3 align-top w-1/4">
                                                <div class="font-bold"><?php echo htmlspecialchars($com['full_name']); ?></div>
                                                <div class="text-yellow-500 font-bold"><?php echo $com['rating']; ?> / 5 ★</div>
                                            </td>
                                            <td class="px-4 py-3 align-top text-gray-600">
                                                "<?php echo htmlspecialchars($com['comments'] ? $com['comments'] : '-'); ?>"
                                                <?php if (!empty($com['photo_path'])): ?>
                                                    <div class="mt-2"><img src="<?php echo htmlspecialchars($com['photo_path']); ?>" class="h-20 w-auto border rounded"></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 italic">No comments available.</p>
                    <?php endif; ?>
                </div>

                <?php if (!$is_finalized): ?>
                    <div class="mt-8 pt-6 border-t border-gray-200 bg-gray-50 p-6 rounded-lg form-actions flex justify-end gap-4">
                        <button type="submit" name="save_report" value="1" onclick="this.form.report_status.value='draft'" 
                                class="px-6 py-2 bg-white border border-gray-300 text-gray-700 font-bold rounded shadow-sm hover:bg-gray-100">
                            Save as Draft
                        </button>
                        <button type="submit" name="save_report" value="1" onclick="this.form.report_status.value='submitted'"
                                class="px-6 py-2 bg-ukm-blue text-white font-bold rounded shadow hover:bg-blue-800">
                            Submit Final Report
                        </button>
                        <input type="hidden" name="report_status" id="report_status" value="draft">
                    </div>
                <?php else: ?>
                    <div class="mt-8 pt-6 border-t border-gray-200 bg-green-50 p-6 rounded-lg text-center no-print">
                        <p class="text-green-800 font-bold flex justify-center items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            This report has been finalized and submitted.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>