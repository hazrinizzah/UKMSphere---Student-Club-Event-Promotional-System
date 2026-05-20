<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Initialize variables
$events_count = 0;
$upcoming_count = 0;
$completed_count = 0;
$feedback_count = 0;
$time_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$time_data = [0, 0, 0, 0, 0, 0];
$type_labels = ['No Events'];
$type_data = [1];
$average_rating = 0.0;
$club_time_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$club_time_data = [0, 0, 0, 0, 0, 0];

// Check if this is an AJAX request for chart data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'chart') {
    $view_type = isset($_GET['view']) ? $_GET['view'] : 'month';
    $user_role = isset($_GET['role']) ? $_GET['role'] : $user_type;
    
    if ($user_role == 'student') {
        // Get student ID first
        $student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
        $student_stmt = $conn->prepare($student_sql);
        if ($student_stmt) {
            $student_stmt->bind_param("i", $user_id);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            
            if ($student_result->num_rows === 1) {
                $student_data = $student_result->fetch_assoc();
                $student_table_id = $student_data['id'];
                
                // Generate time-based data based on view
                $time_labels = [];
                $time_data = [];
                
                switch($view_type) {
                    case 'week':
                        // Last 7 days - Show events student REGISTERED FOR
                        for ($i = 6; $i >= 0; $i--) {
                            $date = date('Y-m-d', strtotime("-$i days"));
                            $time_labels[] = date('D', strtotime($date));
                            
                            // Count registrations MADE on each day
                            $day_sql = "SELECT COUNT(DISTINCT er.id) as count 
                                      FROM event_registrations_abelities er 
                                      WHERE er.student_id = ? 
                                      AND DATE(er.registered_at) = ?";
                            $day_stmt = $conn->prepare($day_sql);
                            if ($day_stmt) {
                                $day_stmt->bind_param("is", $student_table_id, $date);
                                $day_stmt->execute();
                                $day_result = $day_stmt->get_result();
                                $row = $day_result->fetch_assoc();
                                $time_data[] = (int)$row['count'];
                                $day_stmt->close();
                            } else {
                                $time_data[] = 0;
                            }
                        }
                        break;
                        
                    case 'month':
                        // Last 6 months - Show events student REGISTERED FOR
                        for ($i = 5; $i >= 0; $i--) {
                            $month = date('Y-m', strtotime("first day of -$i months"));
                            $time_labels[] = date('M', strtotime($month . '-01'));
                            
                            // Count registrations MADE in each month
                            $month_sql = "SELECT COUNT(DISTINCT er.id) as count 
                                         FROM event_registrations_abelities er 
                                         WHERE er.student_id = ? 
                                         AND DATE_FORMAT(er.registered_at, '%Y-%m') = ?";
                            $month_stmt = $conn->prepare($month_sql);
                            if ($month_stmt) {
                                $month_stmt->bind_param("is", $student_table_id, $month);
                                $month_stmt->execute();
                                $month_result = $month_stmt->get_result();
                                $row = $month_result->fetch_assoc();
                                $time_data[] = (int)$row['count'];
                                $month_stmt->close();
                            } else {
                                $time_data[] = 0;
                            }
                        }
                        break;
                        
                    case 'year':
                        // Last 5 years - Show events student REGISTERED FOR
                        $current_year = date('Y');
                        for ($i = 4; $i >= 0; $i--) {
                            $year = $current_year - $i;
                            $time_labels[] = (string)$year;
                            
                            // Count registrations MADE in each year
                            $year_sql = "SELECT COUNT(DISTINCT er.id) as count 
                                        FROM event_registrations_abelities er 
                                        WHERE er.student_id = ? 
                                        AND YEAR(er.registered_at) = ?";
                            $year_stmt = $conn->prepare($year_sql);
                            if ($year_stmt) {
                                $year_stmt->bind_param("ii", $student_table_id, $year);
                                $year_stmt->execute();
                                $year_result = $year_stmt->get_result();
                                $row = $year_result->fetch_assoc();
                                $time_data[] = (int)$row['count'];
                                $year_stmt->close();
                            } else {
                                $time_data[] = 0;
                            }
                        }
                        break;
                }
                
                // Get event categories distribution (all registered events)
                $type_sql = "SELECT e.category, COUNT(DISTINCT er.id) as type_count 
                            FROM event_registrations_abelities er
                            JOIN events_abelities e ON er.event_id = e.id
                            WHERE er.student_id = ?
                            GROUP BY e.category";
                $type_stmt = $conn->prepare($type_sql);
                if ($type_stmt) {
                    $type_stmt->bind_param("i", $student_table_id);
                    $type_stmt->execute();
                    $type_result = $type_stmt->get_result();
                    
                    $type_labels = [];
                    $type_data = [];
                    
                    if ($type_result->num_rows > 0) {
                        while ($row = $type_result->fetch_assoc()) {
                            $type_labels[] = ucfirst($row['category']);
                            $type_data[] = (int)$row['type_count'];
                        }
                    } else {
                        // Default empty data
                        $type_labels = ['No Events'];
                        $type_data = [1];
                    }
                    $type_stmt->close();
                }
                
                echo json_encode([
                    'success' => true,
                    'time_labels' => $time_labels,
                    'time_data' => $time_data,
                    'type_labels' => $type_labels,
                    'type_data' => $type_data
                ]);
                exit();
            }
            $student_stmt->close();
        }
        
    } elseif ($user_role == 'club') {
        // Get club ID first
        $club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
        $club_stmt = $conn->prepare($club_sql);
        if ($club_stmt) {
            $club_stmt->bind_param("i", $user_id);
            $club_stmt->execute();
            $club_result = $club_stmt->get_result();
            
            if ($club_result->num_rows === 1) {
                $club_data = $club_result->fetch_assoc();
                $club_table_id = $club_data['id'];
                
                // Generate time-based data based on view
                $time_labels = [];
                $time_data = [];
                
                switch($view_type) {
                    case 'week':
                        // Last 7 days - Events that OCCURRED (past only for clubs)
                        for ($i = 6; $i >= 0; $i--) {
                            $date = date('Y-m-d', strtotime("-$i days"));
                            $time_labels[] = date('D', strtotime($date));
                            
                            $day_sql = "SELECT COUNT(*) as count FROM events_abelities 
                                      WHERE club_id = ? 
                                      AND DATE(event_date) = ?
                                      AND event_date <= CURDATE()"; // Only past events for clubs
                            $day_stmt = $conn->prepare($day_sql);
                            if ($day_stmt) {
                                $day_stmt->bind_param("is", $club_table_id, $date);
                                $day_stmt->execute();
                                $day_result = $day_stmt->get_result();
                                $row = $day_result->fetch_assoc();
                                $time_data[] = (int)$row['count'];
                                $day_stmt->close();
                            } else {
                                $time_data[] = 0;
                            }
                        }
                        break;
                        
                    case 'month':
                        // Last 6 months - Events that OCCURRED
                        for ($i = 5; $i >= 0; $i--) {
                            $month = date('Y-m', strtotime("first day of -$i months"));
                            $time_labels[] = date('M', strtotime($month . '-01'));
                            
                            $month_sql = "SELECT COUNT(*) as count FROM events_abelities 
                                         WHERE club_id = ? 
                                         AND DATE_FORMAT(event_date, '%Y-%m') = ?
                                         AND event_date <= CURDATE()"; // Only past events
                            $month_stmt = $conn->prepare($month_sql);
                            if ($month_stmt) {
                                $month_stmt->bind_param("is", $club_table_id, $month);
                                $month_stmt->execute();
                                $month_result = $month_stmt->get_result();
                                $row = $month_result->fetch_assoc();
                                $time_data[] = (int)$row['count'];
                                $month_stmt->close();
                            } else {
                                $time_data[] = 0;
                            }
                        }
                        break;
                        
                    case 'year':
                        // Last 5 years - Events that OCCURRED
                        $current_year = date('Y');
                        for ($i = 4; $i >= 0; $i--) {
                            $year = $current_year - $i;
                            $time_labels[] = (string)$year;
                            
                            $year_sql = "SELECT COUNT(*) as count FROM events_abelities 
                                        WHERE club_id = ? 
                                        AND YEAR(event_date) = ?
                                        AND event_date <= CURDATE()"; // Only past events
                            $year_stmt = $conn->prepare($year_sql);
                            if ($year_stmt) {
                                $year_stmt->bind_param("ii", $club_table_id, $year);
                                $year_stmt->execute();
                                $year_result = $year_stmt->get_result();
                                $row = $year_result->fetch_assoc();
                                $time_data[] = (int)$row['count'];
                                $year_stmt->close();
                            } else {
                                $time_data[] = 0;
                            }
                        }
                        break;
                }
                
                echo json_encode([
                    'success' => true,
                    'time_labels' => $time_labels,
                    'time_data' => $time_data
                ]);
                exit();
            }
            $club_stmt->close();
        }
    }
    
    echo json_encode(['success' => false]);
    exit();
}

// --- NORMAL PAGE LOAD DATA FETCHING ---

// Get user-specific data based on type
if ($user_type == 'student') {
    // Get student ID from students_abelities table first
    $student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
    $student_stmt = $conn->prepare($student_sql);
    if ($student_stmt) {
        $student_stmt->bind_param("i", $user_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows === 1) {
            $student_data = $student_result->fetch_assoc();
            $student_table_id = $student_data['id'];
            
            // Get student events count using the correct student ID
            $events_sql = "SELECT COUNT(*) as event_count FROM event_registrations_abelities WHERE student_id = ?";
            $events_stmt = $conn->prepare($events_sql);
            if ($events_stmt) {
                $events_stmt->bind_param("i", $student_table_id);
                $events_stmt->execute();
                $events_result = $events_stmt->get_result();
                $events_count = $events_result->fetch_assoc()['event_count'];
                $events_stmt->close();
            }
            
            // Get upcoming events count
            $upcoming_sql = "SELECT COUNT(*) as count FROM event_registrations_abelities er 
                           JOIN events_abelities e ON er.event_id = e.id 
                           WHERE er.student_id = ? 
                           AND e.event_date >= CURDATE()";
            $upcoming_stmt = $conn->prepare($upcoming_sql);
            if ($upcoming_stmt) {
                $upcoming_stmt->bind_param("i", $student_table_id);
                $upcoming_stmt->execute();
                $upcoming_result = $upcoming_stmt->get_result();
                $upcoming_count = $upcoming_result->fetch_assoc()['count'];
                $upcoming_stmt->close();
            }
            
            // Get completed events count
            $completed_sql = "SELECT COUNT(*) as count FROM event_registrations_abelities er 
                            JOIN events_abelities e ON er.event_id = e.id 
                            WHERE er.student_id = ? 
                            AND e.event_date < CURDATE()";
            $completed_stmt = $conn->prepare($completed_sql);
            if ($completed_stmt) {
                $completed_stmt->bind_param("i", $student_table_id);
                $completed_stmt->execute();
                $completed_result = $completed_stmt->get_result();
                $completed_count = $completed_result->fetch_assoc()['count'];
                $completed_stmt->close();
            }
            
            // Get feedback count
            $feedback_sql = "SELECT COUNT(*) as count FROM feedback_abelities WHERE student_id = ?";
            $feedback_stmt = $conn->prepare($feedback_sql);
            if ($feedback_stmt) {
                $feedback_stmt->bind_param("i", $student_table_id);
                $feedback_stmt->execute();
                $feedback_result = $feedback_stmt->get_result();
                $feedback_count = $feedback_result->fetch_assoc()['count'];
                $feedback_stmt->close();
            }
            
            // Get initial data for charts (default: month view) - Shows REGISTRATIONS MADE
            $time_labels = [];
            $time_data = [];
            
            // Generate last 6 months - Count registrations MADE each month
            for ($i = 5; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("first day of -$i months"));
                $time_labels[] = date('M', strtotime($month . '-01'));
                
                $month_sql = "SELECT COUNT(DISTINCT er.id) as count 
                             FROM event_registrations_abelities er
                             WHERE er.student_id = ?
                             AND DATE_FORMAT(er.registered_at, '%Y-%m') = ?";
                $month_stmt = $conn->prepare($month_sql);
                if ($month_stmt) {
                    $month_stmt->bind_param("is", $student_table_id, $month);
                    $month_stmt->execute();
                    $month_result = $month_stmt->get_result();
                    $row = $month_result->fetch_assoc();
                    $time_data[] = (int)$row['count'];
                    $month_stmt->close();
                } else {
                    $time_data[] = 0;
                }
            }
            
            // Get event categories distribution
            $type_chart_sql = "
                SELECT 
                    e.category,
                    COUNT(DISTINCT er.id) as type_count
                FROM event_registrations_abelities er
                JOIN events_abelities e ON er.event_id = e.id
                WHERE er.student_id = ?
                GROUP BY e.category
            ";
            $type_stmt = $conn->prepare($type_chart_sql);
            if ($type_stmt) {
                $type_stmt->bind_param("i", $student_table_id);
                $type_stmt->execute();
                $type_result = $type_stmt->get_result();
                
                $type_labels = [];
                $type_data = [];
                
                if ($type_result->num_rows > 0) {
                    while ($row = $type_result->fetch_assoc()) {
                        $type_labels[] = ucfirst($row['category']);
                        $type_data[] = (int)$row['type_count'];
                    }
                } else {
                    // Default empty data
                    $type_labels = ['No Events'];
                    $type_data = [1];
                }
                $type_stmt->close();
            }
            
        }
        $student_stmt->close();
    }
    
} elseif ($user_type == 'club') {
    // Get club ID from clubs_abelities table first
    $club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
    $club_stmt = $conn->prepare($club_sql);
    if ($club_stmt) {
        $club_stmt->bind_param("i", $user_id);
        $club_stmt->execute();
        $club_result = $club_stmt->get_result();
        
        if ($club_result->num_rows === 1) {
            $club_data = $club_result->fetch_assoc();
            $club_table_id = $club_data['id'];
            
            // Get club events count
            $events_sql = "SELECT COUNT(*) as event_count FROM events_abelities WHERE club_id = ?";
            $events_stmt = $conn->prepare($events_sql);
            if ($events_stmt) {
                $events_stmt->bind_param("i", $club_table_id);
                $events_stmt->execute();
                $events_result = $events_stmt->get_result();
                $events_count = $events_result->fetch_assoc()['event_count'];
                $events_stmt->close();
            }
            
            // Get upcoming events count for club
            $upcoming_sql = "SELECT COUNT(*) as count FROM events_abelities 
                           WHERE club_id = ? 
                           AND event_date >= CURDATE()";
            $upcoming_stmt = $conn->prepare($upcoming_sql);
            if ($upcoming_stmt) {
                $upcoming_stmt->bind_param("i", $club_table_id);
                $upcoming_stmt->execute();
                $upcoming_result = $upcoming_stmt->get_result();
                $upcoming_count = $upcoming_result->fetch_assoc()['count'];
                $upcoming_stmt->close();
            }
            
            // Get completed events count for club
            $completed_sql = "SELECT COUNT(*) as count FROM events_abelities 
                            WHERE club_id = ? 
                            AND event_date < CURDATE()";
            $completed_stmt = $conn->prepare($completed_sql);
            if ($completed_stmt) {
                $completed_stmt->bind_param("i", $club_table_id);
                $completed_stmt->execute();
                $completed_result = $completed_stmt->get_result();
                $completed_count = $completed_result->fetch_assoc()['count'];
                $completed_stmt->close();
            }
            
            // Get average rating for club events
            $rating_sql = "SELECT AVG(f.rating) as avg_rating 
                          FROM feedback_abelities f
                          JOIN events_abelities e ON f.event_id = e.id
                          WHERE e.club_id = ?";
            $rating_stmt = $conn->prepare($rating_sql);
            if ($rating_stmt) {
                $rating_stmt->bind_param("i", $club_table_id);
                $rating_stmt->execute();
                $rating_result = $rating_stmt->get_result();
                $rating_data = $rating_result->fetch_assoc();
                $average_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0.0;
                $rating_stmt->close();
            }
            
            // Get initial data for charts (default: month view) - Only PAST events for clubs
            $club_time_labels = [];
            $club_time_data = [];
            
            // Generate last 6 months with zeros - Only PAST events
            for ($i = 5; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("first day of -$i months"));
                $club_time_labels[] = date('M', strtotime($month . '-01'));
                
                $month_sql = "SELECT COUNT(*) as count 
                             FROM events_abelities 
                             WHERE club_id = ?
                             AND DATE_FORMAT(event_date, '%Y-%m') = ?
                             AND event_date <= CURDATE()"; // Only past events
                $month_stmt = $conn->prepare($month_sql);
                if ($month_stmt) {
                    $month_stmt->bind_param("is", $club_table_id, $month);
                    $month_stmt->execute();
                    $month_result = $month_stmt->get_result();
                    $row = $month_result->fetch_assoc();
                    $club_time_data[] = (int)$row['count'];
                    $month_stmt->close();
                } else {
                    $club_time_data[] = 0;
                }
            }
            
        }
        $club_stmt->close();
    }
    
} elseif ($user_type == 'hep') {
    // Get HEP Profile ID first
    $hep_id_sql = "SELECT id FROM hep_abelities WHERE user_id = ?";
    $hep_id_stmt = $conn->prepare($hep_id_sql);
    if ($hep_id_stmt) {
        $hep_id_stmt->bind_param("i", $user_id);
        $hep_id_stmt->execute();
        $hep_id_result = $hep_id_stmt->get_result();
        
        if ($hep_id_result->num_rows > 0) {
            $hep_data = $hep_id_result->fetch_assoc();
            $hep_table_id = $hep_data['id']; 

            // Get reports count
            $reports_sql = "SELECT COUNT(*) as report_count FROM reports_abelities WHERE submitted_by_hep = ?";
            $reports_stmt = $conn->prepare($reports_sql);
            if ($reports_stmt) {
                $reports_stmt->bind_param("i", $hep_table_id);
                $reports_stmt->execute();
                $reports_result = $reports_stmt->get_result();
                $reports_count = $reports_result->fetch_assoc()['report_count'];
                $reports_stmt->close();
            }
        }
        $hep_id_stmt->close();
    }
    
    // Get system statistics
    $clubs_sql = "SELECT COUNT(*) as club_count FROM clubs_abelities";
    $clubs_result = $conn->query($clubs_sql);
    if ($clubs_result) {
        $clubs_count = $clubs_result->fetch_assoc()['club_count'];
    } else {
        $clubs_count = 0;
    }
    
    // Count active events
    $active_events_sql = "SELECT COUNT(*) as active_count FROM events_abelities 
                         WHERE event_date >= CURDATE()";
    $active_events_result = $conn->query($active_events_sql);
    if ($active_events_result) {
        $active_events_count = $active_events_result->fetch_assoc()['active_count'];
    } else {
        $active_events_count = 0;
    }
    
    // Get additional statistics for simplified HEP dashboard
    $total_students_sql = "SELECT COUNT(*) as total FROM students_abelities";
    $total_students_result = $conn->query($total_students_sql);
    $total_students = $total_students_result ? $total_students_result->fetch_assoc()['total'] : 0;
    
    // Get event category distribution
    $category_distribution_sql = "SELECT category, COUNT(*) as count FROM events_abelities GROUP BY category ORDER BY count DESC";
    $category_result = $conn->query($category_distribution_sql);
    $category_data = [];
    while ($row = $category_result->fetch_assoc()) {
        $category_data[] = $row;
    }
    
    // Get events needing attention (low registration)
    $low_registration_sql = "SELECT e.title, c.club_name, COUNT(er.id) as registrations 
                             FROM events_abelities e 
                             JOIN clubs_abelities c ON e.club_id = c.id 
                             LEFT JOIN event_registrations_abelities er ON e.id = er.event_id 
                             WHERE e.event_date >= CURDATE() 
                             GROUP BY e.id 
                             HAVING registrations < 10 
                             ORDER BY e.event_date 
                             LIMIT 3";
    $low_registration_result = $conn->query($low_registration_sql);
    $low_registration_events = [];
    while ($row = $low_registration_result->fetch_assoc()) {
        $low_registration_events[] = $row;
    }
    
    // Get inactive clubs (no events in 30 days)
    $inactive_clubs_sql = "SELECT c.club_name, MAX(e.event_date) as last_event 
                           FROM clubs_abelities c 
                           LEFT JOIN events_abelities e ON c.id = e.club_id 
                           GROUP BY c.id 
                           HAVING last_event IS NULL OR last_event < DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                           LIMIT 3";
    $inactive_clubs_result = $conn->query($inactive_clubs_sql);
    $inactive_clubs = [];
    while ($row = $inactive_clubs_result->fetch_assoc()) {
        $inactive_clubs[] = $row;
    }
    
    // Get report status distribution for pie chart
    $report_status_sql = "
        SELECT 
            CASE 
                WHEN r.status IS NULL THEN 'new'
                ELSE r.status
            END as status,
            COUNT(DISTINCT e.id) as count
        FROM events_abelities e
        LEFT JOIN reports_abelities r ON e.id = r.event_id
        WHERE e.event_date <= CURDATE()
        GROUP BY CASE 
            WHEN r.status IS NULL THEN 'new'
            ELSE r.status
        END
        ORDER BY 
            CASE 
                WHEN r.status IS NULL THEN 1
                WHEN r.status = 'draft' THEN 2
                WHEN r.status = 'submitted' THEN 3
                WHEN r.status = 'reviewed' THEN 4
                ELSE 5
            END
    ";
    $report_status_result = $conn->query($report_status_sql);
    $report_status_data = [];
    $report_status_labels = [];
    $report_status_counts = [];
    $report_status_colors = [];
    
    $status_config = [
        'new' => ['label' => 'No Report', 'color' => '#9CA3AF', 'icon' => '📄'],
        'draft' => ['label' => 'Draft', 'color' => '#F59E0B', 'icon' => '✏️'],
        'submitted' => ['label' => 'Submitted', 'color' => '#3B82F6', 'icon' => '📤'],
        'reviewed' => ['label' => 'Reviewed', 'color' => '#10B981', 'icon' => '✅']
    ];
    
    while ($row = $report_status_result->fetch_assoc()) {
        $status = $row['status'];
        $count = $row['count'];
        
        $config = $status_config[$status] ?? ['label' => ucfirst($status), 'color' => '#6B7280', 'icon' => '📋'];
        
        $report_status_labels[] = $config['label'];
        $report_status_counts[] = $count;
        $report_status_colors[] = $config['color'];
        
        $report_status_data[] = [
            'status' => $status,
            'label' => $config['label'],
            'count' => $count,
            'color' => $config['color'],
            'icon' => $config['icon']
        ];
    }
    
    // Calculate completed reports
    $completed_reports = 0;
    foreach($report_status_data as $item) {
        if($item['status'] == 'reviewed') $completed_reports += $item['count'];
    }
    $total_events_with_reports = array_sum($report_status_counts);
    $completion_rate = $total_events_with_reports > 0 ? round(($completed_reports / $total_events_with_reports) * 100) : 0;
    
    // Get pending reviews count
    $pending_sql = "SELECT COUNT(*) as count FROM reports_abelities WHERE status = 'draft'";
    $pending_result = $conn->query($pending_sql);
    $pending_reviews = $pending_result ? $pending_result->fetch_assoc()['count'] : 0;
}

// --- INCLUDE HEADER (Styles, Fonts, Navigation) ---
include 'header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    .view-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
        outline: none;
    }
    
    .view-btn.active {
        background-color: #2563eb;
        color: white;
    }
    
    .view-btn:not(.active) {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .view-btn:hover:not(.active) {
        background-color: #bfdbfe;
    }
    
    .stats-card {
        background-color: white;
        padding: 1.5rem;
        border-radius: 0.5rem;
        border: 2px solid #e5e7eb; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    }

    .stats-card:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border-color: #cbd5e1; 
        z-index: 10;
    }
    
    .gradient-card-blue {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }
    
    .gradient-card-purple {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }
    
    .loading-spinner {
        display: none;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
        margin-left: 8px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div id="app" class="w-full h-full overflow-auto bg-blue-50">

    <?php if ($user_type == 'student'): ?>
        <!-- Student Dashboard Header -->
        <header class="bg-blue-600 text-white px-8 py-6 shadow-lg">
            <h1 id="dashboard-title" class="text-3xl font-bold">My Dashboard</h1>
            <p class="text-blue-100 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Here's your activity overview</p>
        </header>
        
        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-8 py-8">
            <!-- Profile Card -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-2xl font-semibold text-blue-900 mb-4">Student Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            👤
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Name</p>
                            <p id="student-name" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📧
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Email</p>
                            <p id="student-email" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📱
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Phone</p>
                            <p id="student-phone" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['phone_number']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Student Info -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            🔢
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Matric Number</p>
                            <p class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['matric_number']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📅
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Total Events</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $events_count; ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            💬
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Feedback Given</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $feedback_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <h2 class="text-2xl font-semibold text-blue-900">Event Statistics</h2>
                    <div class="flex gap-2">
                        <button id="btn-week" class="view-btn" onclick="changeStudentView('week')">
                            Week
                        </button>
                        <button id="btn-month" class="view-btn active" onclick="changeStudentView('month')">
                            Month
                        </button>
                        <button id="btn-year" class="view-btn" onclick="changeStudentView('year')">
                            Year
                        </button>
                    </div>
                </div>
                
                <!-- Charts Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Participation Over Time Chart -->
                    <div>
                        <h3 class="text-lg font-semibold text-blue-800 mb-4">Registration Activity Over Time</h3>
                        <div class="chart-container">
                            <canvas id="timeChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Event Categories Chart -->
                    <div>
                        <h3 class="text-lg font-semibold text-blue-800 mb-4">Event Categories</h3>
                        <div class="chart-container">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8 pt-8 border-t border-gray-200">
                    <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <span class="text-blue-600">⏳</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Upcoming Events</p>
                                <p class="text-lg font-semibold text-blue-900"><?php echo $upcoming_count; ?></p>
                            </div>
                        </div>
                        <div class="text-blue-600 font-medium">Registered</div>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <span class="text-green-600">✅</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Completed Events</p>
                                <p class="text-lg font-semibold text-blue-900"><?php echo $completed_count; ?></p>
                            </div>
                        </div>
                        <div class="text-green-600 font-medium">Attended</div>
                    </div>
                </div>
            </div>

        </main>

    <?php elseif ($user_type == 'club'): ?>
        <!-- Club Dashboard Header -->
        <header class="bg-blue-600 text-white px-8 py-6 shadow-lg">
            <div class="max-w-7xl mx-auto">
                <h1 id="dashboard-title" class="text-3xl font-bold">Club Dashboard</h1>
                <p class="text-blue-100 mt-1">Manage your events and track engagement</p>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-8 py-8">
            <!-- Club Information Card -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-2xl font-semibold text-blue-900 mb-4">Club Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            🏛️
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Club Name</p>
                            <p id="club-name" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['club_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            🆔
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Club ID</p>
                            <p id="club-id" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['club_id']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📧
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Contact Email</p>
                            <p id="contact-email" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Club Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📅
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Active Events</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $upcoming_count; ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            ✅
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Completed Events</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $completed_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <div class="gradient-card-blue rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium opacity-90">Total Events</p>
                        <span class="text-2xl">📅</span>
                    </div>
                    <p id="stat-total-events" class="text-4xl font-bold"><?php echo $events_count; ?></p>
                    <p class="text-xs opacity-75 mt-1">All time</p>
                </div>
                <div class="gradient-card-purple rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium opacity-90">Average Rating</p>
                        <span class="text-2xl">⭐</span>
                    </div>
                    <p id="stat-avg-rating" class="text-4xl font-bold"><?php echo $average_rating; ?></p>
                    <p class="text-xs opacity-75 mt-1">Out of 5.0</p>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <h2 class="text-2xl font-semibold text-blue-900">Event Analytics</h2>
                    <div class="flex gap-2">
                        <button id="club-btn-week" class="view-btn" onclick="changeClubView('week')">
                            Week
                        </button>
                        <button id="club-btn-month" class="view-btn active" onclick="changeClubView('month')">
                            Month
                        </button>
                        <button id="club-btn-year" class="view-btn" onclick="changeClubView('year')">
                            Year
                        </button>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-blue-800 mb-4">Events Posted Over Time</h3>
                    <div class="chart-container">
                        <canvas id="clubEventsChart"></canvas>
                    </div>
                </div>
                
                <!-- Event Categories Summary -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-blue-800 mb-4">Event Categories</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <?php
                        // Get event categories distribution
                        $categories_sql = "
                            SELECT 
                                category,
                                COUNT(*) as category_count
                            FROM events_abelities 
                            WHERE club_id = ?
                            GROUP BY category
                        ";
                        $categories_stmt = $conn->prepare($categories_sql);
                        $category_data = [];
                        if ($categories_stmt && isset($club_table_id)) {
                            $categories_stmt->bind_param("i", $club_table_id);
                            $categories_stmt->execute();
                            $categories_result = $categories_stmt->get_result();
                            
                            $category_icons = [
                                'sports' => '🏅',
                                'academic' => '📚',
                                'cultural' => '🎭',
                                'social' => '🎉',
                                'workshop' => '🔧',
                                'competition' => '🏆'
                            ];
                            
                            $category_names = [
                                'sports' => 'Sports',
                                'academic' => 'Academic',
                                'cultural' => 'Cultural',
                                'social' => 'Social',
                                'workshop' => 'Workshop',
                                'competition' => 'Competition'
                            ];
                            
                            while ($row = $categories_result->fetch_assoc()) {
                                $category = $row['category'];
                                $count = $row['category_count'];
                                $icon = $category_icons[$category] ?? '📋';
                                $name = $category_names[$category] ?? ucfirst($category);
                        ?>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl mb-2"><?php echo $icon; ?></div>
                            <p class="text-sm font-medium text-gray-600"><?php echo $name; ?></p>
                            <p class="text-lg font-bold text-blue-900"><?php echo $count; ?></p>
                        </div>
                        <?php
                            }
                            $categories_stmt->close();
                        } else {
                            // Show empty categories if no data
                            $empty_categories = [
                                ['icon' => '🏅', 'name' => 'Sports', 'count' => 0],
                                ['icon' => '📚', 'name' => 'Academic', 'count' => 0],
                                ['icon' => '🎭', 'name' => 'Cultural', 'count' => 0],
                                ['icon' => '🎉', 'name' => 'Social', 'count' => 0],
                                ['icon' => '🔧', 'name' => 'Workshop', 'count' => 0],
                                ['icon' => '🏆', 'name' => 'Competition', 'count' => 0]
                            ];
                            foreach ($empty_categories as $cat) {
                        ?>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl mb-2"><?php echo $cat['icon']; ?></div>
                            <p class="text-sm font-medium text-gray-600"><?php echo $cat['name']; ?></p>
                            <p class="text-lg font-bold text-blue-900">0</p>
                        </div>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

        </main>

    <?php elseif ($user_type == 'hep'): ?>
        <!-- HEP Dashboard Header -->
        <header class="bg-blue-600 text-white px-8 py-6 shadow-lg">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-3xl font-bold">HEP Dashboard</h1>
                <p class="text-blue-100 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Here's your activity overview</p>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-8 py-8">
            <!-- Profile Card -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-2xl font-semibold text-blue-900 mb-4">HEP Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            👤
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Name</p>
                            <p id="hep-name" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📧
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Email</p>
                            <p id="hep-email" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            🆔
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Work ID</p>
                            <p id="work-id" class="text-blue-900 font-semibold"><?php echo htmlspecialchars($_SESSION['work_id']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Additional HEP Info -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📊
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Total Reports</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $reports_count; ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            📅
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Past Events</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $total_events_with_reports; ?></p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="text-blue-600 text-2xl">
                            ✅
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Reviewed Reports</p>
                            <p class="text-blue-900 font-semibold text-xl"><?php echo $completed_reports; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <h2 class="text-2xl font-semibold text-blue-900">Report Statistics</h2>
                </div>
                
                <!-- Charts Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Report Status Distribution -->
                    <div>
                        <h3 class="text-lg font-semibold text-blue-800 mb-4">Report Status Distribution</h3>
                        <div class="chart-container">
                            <canvas id="reportStatusChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- System Overview -->
                    <div>
                        <h3 class="text-lg font-semibold text-blue-800 mb-4">System Overview</h3>
                        <div class="space-y-4">
                            <!-- Stats Cards -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="text-blue-600">👥</span>
                                        <p class="text-sm font-medium text-gray-600">Total Students</p>
                                    </div>
                                    <p class="text-2xl font-bold text-blue-900"><?php echo $total_students; ?></p>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="text-green-600">🏛️</span>
                                        <p class="text-sm font-medium text-gray-600">Active Clubs</p>
                                    </div>
                                    <p class="text-2xl font-bold text-green-900"><?php echo $clubs_count; ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="text-purple-600">📅</span>
                                        <p class="text-sm font-medium text-gray-600">Active Events</p>
                                    </div>
                                    <p class="text-2xl font-bold text-purple-900"><?php echo $active_events_count; ?></p>
                                </div>
                                
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="text-yellow-600">⏳</span>
                                        <p class="text-sm font-medium text-gray-600">Pending Reviews</p>
                                    </div>
                                    <p class="text-2xl font-bold text-yellow-900"><?php echo $pending_reviews; ?></p>
                                </div>
                            </div>
                            
                            <!-- Completion Rate -->
                            <div class="mt-4">
                                <?php if($total_events_with_reports > 0): ?>
                                    <?php 
                                    $progress_color = $completion_rate >= 80 ? 'bg-green-500' : ($completion_rate >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                                    ?>
                                    <div class="mb-1">
                                        <div class="flex justify-between text-sm">
                                            <span class="font-medium text-gray-700">Report Completion Rate</span>
                                            <span class="font-bold <?php echo $completion_rate >= 80 ? 'text-green-600' : ($completion_rate >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                <?php echo $completion_rate; ?>%
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                                            <div class="h-2.5 rounded-full <?php echo $progress_color; ?>" style="width: <?php echo $completion_rate; ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php echo $completed_reports; ?> of <?php echo $total_events_with_reports; ?> past events have completed reports
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8 pt-8 border-t border-gray-200">
                    <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <span class="text-blue-600">📄</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">No Report</p>
                                <p class="text-lg font-semibold text-blue-900">
                                    <?php 
                                    $no_report_count = 0;
                                    foreach($report_status_data as $item) {
                                        if($item['status'] == 'new') $no_report_count = $item['count'];
                                    }
                                    echo $no_report_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-blue-600 font-medium">Events</div>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <span class="text-green-600">✅</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Reviewed</p>
                                <p class="text-lg font-semibold text-blue-900"><?php echo $completed_reports; ?></p>
                            </div>
                        </div>
                        <div class="text-green-600 font-medium">Events</div>
                    </div>
                </div>
            </div>

        </main>
    <?php endif; ?>

</div>

<script>
    // Global chart variables
    let studentTimeChart = null;
    let studentTypeChart = null;
    let clubEventsChart = null;
    let reportStatusChart = null;
    
    <?php if ($user_type == 'student'): ?>
    // Student Charts initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Time Chart
        const timeCtx = document.getElementById('timeChart').getContext('2d');
        studentTimeChart = new Chart(timeCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($time_labels); ?>,
                datasets: [{
                    label: 'Registrations Made',
                    data: <?php echo json_encode($time_data); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: false 
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return `Registrations: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { 
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Initialize Type Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        studentTypeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($type_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($type_data); ?>,
                    backgroundColor: [
                        '#1e40af',
                        '#2563eb',
                        '#3b82f6',
                        '#60a5fa',
                        '#93c5fd',
                        '#bfdbfe'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                if (label === 'No Events') return 'No events registered yet';
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} event(s) (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        // Set initial active button
        updateStudentViewButtons('month');
    });
    
    // Student view change function
    async function changeStudentView(view) {
        // Update button states
        updateStudentViewButtons(view);
        
        // Show loading state
        const buttons = ['week', 'month', 'year'];
        buttons.forEach(btn => {
            const button = document.getElementById('btn-' + btn);
            if (btn === view) {
                const originalText = button.textContent;
                button.innerHTML = 'Loading...';
                button.disabled = true;
                
                // Store original text in data attribute
                button.dataset.originalText = originalText;
            }
        });
        
        try {
            // Fetch new chart data
            const response = await fetch(`?ajax=chart&view=${view}&role=student&t=${Date.now()}`);
            const data = await response.json();
            
            if (data.success) {
                // Update time chart
                studentTimeChart.data.labels = data.time_labels;
                studentTimeChart.data.datasets[0].data = data.time_data;
                studentTimeChart.update();
                
                // Update type chart (if data exists)
                if (data.type_labels && data.type_data) {
                    studentTypeChart.data.labels = data.type_labels;
                    studentTypeChart.data.datasets[0].data = data.type_data;
                    studentTypeChart.update();
                }
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
            alert('Failed to load chart data. Please try again.');
        } finally {
            // Reset button states
            buttons.forEach(btn => {
                const button = document.getElementById('btn-' + btn);
                if (btn === view) {
                    button.innerHTML = button.dataset.originalText || 
                                     btn.charAt(0).toUpperCase() + btn.slice(1);
                    button.disabled = false;
                }
            });
        }
    }
    
    function updateStudentViewButtons(activeView) {
        const buttons = ['week', 'month', 'year'];
        buttons.forEach(btn => {
            const button = document.getElementById('btn-' + btn);
            if (btn === activeView) {
                button.classList.add('active');
                button.classList.remove('bg-blue-100', 'text-blue-700');
            } else {
                button.classList.remove('active');
                button.classList.add('bg-blue-100', 'text-blue-700');
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($user_type == 'club'): ?>
    // Club Charts initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Club Events Chart
        const clubEventsCtx = document.getElementById('clubEventsChart').getContext('2d');
        clubEventsChart = new Chart(clubEventsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($club_time_labels); ?>,
                datasets: [{
                    label: 'Events Held',
                    data: <?php echo json_encode($club_time_data); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: false 
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return `Events: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { 
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Set initial active button
        updateClubViewButtons('month');
    });
    
    // Club view change function
    async function changeClubView(view) {
        // Update button states
        updateClubViewButtons(view);
        
        // Show loading state
        const buttons = ['week', 'month', 'year'];
        buttons.forEach(btn => {
            const button = document.getElementById('club-btn-' + btn);
            if (btn === view) {
                const originalText = button.textContent;
                button.innerHTML = 'Loading...';
                button.disabled = true;
                
                // Store original text in data attribute
                button.dataset.originalText = originalText;
            }
        });
        
        try {
            // Fetch new chart data
            const response = await fetch(`?ajax=chart&view=${view}&role=club&t=${Date.now()}`);
            const data = await response.json();
            
            if (data.success) {
                // Update club events chart
                clubEventsChart.data.labels = data.time_labels;
                clubEventsChart.data.datasets[0].data = data.time_data;
                clubEventsChart.update();
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
            alert('Failed to load chart data. Please try again.');
        } finally {
            // Reset button states
            buttons.forEach(btn => {
                const button = document.getElementById('club-btn-' + btn);
                if (btn === view) {
                    button.innerHTML = button.dataset.originalText || 
                                     btn.charAt(0).toUpperCase() + btn.slice(1);
                    button.disabled = false;
                }
            });
        }
    }
    
    function updateClubViewButtons(activeView) {
        const buttons = ['week', 'month', 'year'];
        buttons.forEach(btn => {
            const button = document.getElementById('club-btn-' + btn);
            if (btn === activeView) {
                button.classList.add('active');
                button.classList.remove('bg-blue-100', 'text-blue-700');
            } else {
                button.classList.remove('active');
                button.classList.add('bg-blue-100', 'text-blue-700');
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($user_type == 'hep'): ?>
    // HEP Report Status Chart initialization
    document.addEventListener('DOMContentLoaded', function() {
        const reportStatusCtx = document.getElementById('reportStatusChart')?.getContext('2d');
        
        if (reportStatusCtx) {
            reportStatusChart = new Chart(reportStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($report_status_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($report_status_counts); ?>,
                        backgroundColor: <?php echo json_encode($report_status_colors); ?>,
                        borderWidth: 3,
                        borderColor: '#FFFFFF',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} events (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }
    });
    <?php endif; ?>
</script>

<?php 
$conn->close();
include 'footer.php'; 
?>