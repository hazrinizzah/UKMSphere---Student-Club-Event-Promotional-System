<?php
include 'config.php';

// Handle form submissions for login and registration
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        // Login logic
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $sql = "SELECT id, username, password, user_type FROM users_abelities WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $email;

                // Get additional user data based on type
                $user_type = $user['user_type'];
                if ($user_type == 'student') {
                    $student_sql = "SELECT full_name, matric_number, phone_number FROM students_abelities WHERE user_id = ?";
                    $student_stmt = $conn->prepare($student_sql);
                    $student_stmt->bind_param("i", $user['id']);
                    $student_stmt->execute();
                    $student_result = $student_stmt->get_result();
                    $student_data = $student_result->fetch_assoc();
                    
                    $_SESSION['full_name'] = $student_data['full_name'];
                    $_SESSION['matric_number'] = $student_data['matric_number'];
                    $_SESSION['phone_number'] = $student_data['phone_number'];
                    
                } elseif ($user_type == 'club') {
                    $club_sql = "SELECT club_name, club_id FROM clubs_abelities WHERE user_id = ?";
                    $club_stmt = $conn->prepare($club_sql);
                    $club_stmt->bind_param("i", $user['id']);
                    $club_stmt->execute();
                    $club_result = $club_stmt->get_result();
                    $club_data = $club_result->fetch_assoc();
                    
                    $_SESSION['club_name'] = $club_data['club_name'];
                    $_SESSION['club_id'] = $club_data['club_id'];
                    
                } elseif ($user_type == 'hep') {
                    $hep_sql = "SELECT full_name, work_id FROM hep_abelities WHERE user_id = ?";
                    $hep_stmt = $conn->prepare($hep_sql);
                    $hep_stmt->bind_param("i", $user['id']);
                    $hep_stmt->execute();
                    $hep_result = $hep_stmt->get_result();
                    $hep_data = $hep_result->fetch_assoc();
                    
                    $_SESSION['full_name'] = $hep_data['full_name'];
                    $_SESSION['work_id'] = $hep_data['work_id'];
                }

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $login_error = "Invalid email or password.";
            }
        } else {
            $login_error = "User with this email does not exist.";
        }
        $stmt->close();
    }
    elseif (isset($_POST['register'])) {
        // Registration logic
        $user_type = $_POST['user_type'];
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $username = strtolower(str_replace(' ', '_', $_POST['full_name'] ?? $_POST['club_name'] ?? $_POST['full_name']));

        // Check if email already exists
        $check_sql = "SELECT id FROM users_abelities WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $register_error = "An account with this email already exists.";
        } else {
            // Validate IDs based on user type
            if ($user_type == 'student') {
                $matric_number = trim($_POST['matric_number']);
                if (!preg_match('/^A/i', $matric_number)) {
                    $register_error = "Invalid Matric Number. It must start with 'A'.";
                }
            } elseif ($user_type == 'club') {
                $club_id = trim($_POST['club_id']);
                if (!preg_match('/^C/i', $club_id)) {
                    $register_error = "Invalid Club ID. It must start with 'C'.";
                }
            } elseif ($user_type == 'hep') {
                $work_id = trim($_POST['work_id']);
                if (!preg_match('/^K/i', $work_id)) {
                    $register_error = "Invalid Work ID. It must start with 'K'.";
                }
            }

            if (!isset($register_error)) {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $user_sql = "INSERT INTO users_abelities (username, password, email, user_type) VALUES (?, ?, ?, ?)";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("ssss", $username, $hashed_password, $email, $user_type);
                
                if ($user_stmt->execute()) {
                    $user_id = $user_stmt->insert_id;
                    
                    // Insert type-specific data
                    if ($user_type == 'student') {
                        $student_sql = "INSERT INTO students_abelities (user_id, full_name, matric_number, phone_number) VALUES (?, ?, ?, ?)";
                        $student_stmt = $conn->prepare($student_sql);
                        $student_stmt->bind_param("isss", $user_id, $_POST['full_name'], $matric_number, $_POST['phone_number']);
                        $student_stmt->execute();
                    } elseif ($user_type == 'club') {
                        $club_sql = "INSERT INTO clubs_abelities (user_id, club_name, club_id) VALUES (?, ?, ?)";
                        $club_stmt = $conn->prepare($club_sql);
                        $club_stmt->bind_param("iss", $user_id, $_POST['club_name'], $club_id);
                        $club_stmt->execute();
                    } elseif ($user_type == 'hep') {
                        $hep_sql = "INSERT INTO hep_abelities (user_id, full_name, work_id) VALUES (?, ?, ?)";
                        $hep_stmt = $conn->prepare($hep_sql);
                        $hep_stmt->bind_param("iss", $user_id, $_POST['full_name'], $work_id);
                        $hep_stmt->execute();
                    }
                    
                    $register_success = "Registration successful. You can now log in.";
                } else {
                    $register_error = "An error occurred during registration. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UKMSphere - Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0a192f;
        }

        :root {
            --ukm-blue: #004a98;
            --ukm-red: #d10028;
            --cyber-blue: #00e5ff;
            --cyber-dark: #0a192f;
        }

        #matrixCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.2;
            z-index: 1;
        }

        .cyber-window {
            width: 100%;
            max-width: 450px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            background: rgba(10, 25, 48, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--cyber-blue);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            z-index: 2;
        }

        .cyber-bar {
            background: linear-gradient(90deg, var(--ukm-blue), #002a5e);
            color: var(--cyber-blue);
            padding: 15px 20px;
            font-size: 0.9rem;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--cyber-blue);
            flex-shrink: 0;
        }
        
        .cyber-bar a { color: var(--cyber-blue); text-decoration: none; transition: color 0.2s; font-family: 'Montserrat', sans-serif; font-weight: bold; }
        .cyber-bar a:hover { color: white; }

        .cyber-content {
            padding: 30px;
            color: #e6f1ff;
            overflow-y: auto;
            height: 100%;
        }

        .cyber-content::-webkit-scrollbar { width: 6px; }
        .cyber-content::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .cyber-content::-webkit-scrollbar-thumb { background: var(--cyber-blue); border-radius: 10px; }

        .cyber-logo { text-align: center; margin-bottom: 25px; }
        .cyber-logo h2 { color: #fff; text-shadow: 0 0 10px var(--cyber-blue); margin: 0; font-family: 'Montserrat', sans-serif; }
        .cyber-logo p { color: var(--cyber-blue); opacity: 0.7; font-size: 0.8rem; letter-spacing: 2px; margin-top: 5px; font-family: 'Fira Code', monospace;}

        .cyber-group { margin-bottom: 20px; position: relative; }
        .cyber-label { display: block; margin-bottom: 8px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; color: var(--cyber-blue); }
        .cyber-input {
            width: 100%; box-sizing: border-box; padding: 12px 15px; background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 229, 255, 0.3); border-radius: 8px; color: #fff;
            font-family: 'Montserrat', sans-serif; outline: none; transition: all 0.3s;
        }
        .cyber-input:focus { border-color: var(--cyber-blue); box-shadow: 0 0 15px rgba(0, 229, 255, 0.2); background-color: rgba(0, 0, 0, 0.5); }

        .cyber-btn {
            width: 100%; padding: 15px; background: var(--ukm-blue); border: none; border-radius: 8px; color: #fff;
            font-family: 'Montserrat', sans-serif; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
            cursor: pointer; position: relative; overflow: hidden; transition: all 0.3s;
        }
        .cyber-btn:hover { background: var(--cyber-blue); color: var(--cyber-dark); box-shadow: 0 0 20px rgba(0, 229, 255, 0.6); }

        .cyber-selector { display: flex; margin-bottom: 25px; background: rgba(0,0,0,0.3); border-radius: 8px; padding: 4px; }
        .cyber-type-btn {
            flex: 1; padding: 10px; background: transparent; color: rgba(255,255,255,0.6);
            border: none; border-radius: 6px; cursor: pointer; font-family: 'Montserrat', sans-serif; font-size: 0.9rem; transition: all 0.3s;
        }
        .cyber-type-btn.active { background: var(--ukm-blue); color: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }

        .cyber-link { color: var(--cyber-blue); text-decoration: none; cursor: pointer; transition: all 0.3s; }
        .cyber-link:hover { text-shadow: 0 0 8px var(--cyber-blue); }

        .cyber-alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; font-size: 0.85rem; border-left: 4px solid; }
        .cyber-alert-red { background: rgba(209, 0, 40, 0.15); color: #ff99aa; border-color: var(--ukm-red); }
        .cyber-alert-green { background: rgba(0, 229, 255, 0.15); color: var(--cyber-blue); border-color: var(--cyber-blue); }
        .hidden { display: none; }
    </style>
</head>
<body>
    <canvas id="matrixCanvas"></canvas>
    <div class="cyber-window">
        <div class="cyber-bar">
            <span>UKMSphere Access</span>
            <a href="index.php" title="Back to Homepage">Home</a>
        </div>
        <div class="cyber-content">
            <div class="cyber-logo">
                 <h2>UKMSPHERE</h2>
                 <p>Login or Register</p>
            </div>

            <!-- Alerts -->
            <div id="cyberAlerts">
                <?php if (isset($login_error)): ?>
                    <div class="cyber-alert cyber-alert-red"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <?php if (isset($register_error)): ?>
                    <div class="cyber-alert cyber-alert-red"><?php echo $register_error; ?></div>
                <?php endif; ?>
                <?php if (isset($register_success)): ?>
                    <div class="cyber-alert cyber-alert-green"><?php echo $register_success; ?></div>
                <?php endif; ?>
            </div>

            <!-- LOGIN VIEW -->
            <div id="loginView">
                <form method="POST" action="">
                    <div class="cyber-group">
                        <label class="cyber-label">Email</label>
                        <input name="email" type="email" required class="cyber-input" placeholder="e.g., your.email@ukm.my" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="cyber-group">
                        <label class="cyber-label">Password</label>
                        <input name="password" type="password" required class="cyber-input" placeholder="••••••••">
                    </div>
                    <button type="submit" name="login" class="cyber-btn">Login</button>
                </form>
                <p style="text-align: center; margin-top: 25px; opacity: 0.8; font-size: 0.9rem;">
                    Don't have an account? <span class="cyber-link" onclick="switchMode('register')">Register here</span>
                </p>
            </div>

            <!-- REGISTER VIEW -->
            <div id="registerView" class="hidden">
                <div class="cyber-selector">
                    <button type="button" class="cyber-type-btn active" onclick="setType('student')" id="btn-student">STUDENT</button>
                    <button type="button" class="cyber-type-btn" onclick="setType('club')" id="btn-club">CLUB</button>
                    <button type="button" class="cyber-type-btn" onclick="setType('hep')" id="btn-hep">HEP</button>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="user_type" id="userTypeInput" value="student">
                    <div id="cyberFields">
                        <!-- Fields injected by JS -->
                    </div>
                    <button type="submit" name="register" class="cyber-btn">Register</button>
                </form>
                <p style="text-align: center; margin-top: 25px; opacity: 0.8; font-size: 0.9rem;">
                    Already have an account? <span class="cyber-link" onclick="switchMode('login')">Login here</span>
                </p>
            </div>
        </div>
    </div>

    <script>
        // --- MATRIX RAIN ---
        const canvas = document.getElementById('matrixCanvas');
        const ctx = canvas.getContext('2d');
        let matrixInterval;

        function startMatrix() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const chars = '01UKMSPHERE_CONNECTING...';
            const fontSize = 14;
            const columns = canvas.width / fontSize;
            const rainDrops = Array(Math.floor(columns)).fill(1);

            const draw = () => {
                ctx.fillStyle = 'rgba(10, 25, 48, 0.1)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#00e5ff';
                ctx.font = fontSize + 'px ' + 'Fira Code, monospace';
                for (let i = 0; i < rainDrops.length; i++) {
                    const text = chars.charAt(Math.floor(Math.random() * chars.length));
                    ctx.fillText(text, i * fontSize, rainDrops[i] * fontSize);
                    if (rainDrops[i] * fontSize > canvas.height && Math.random() > 0.98) {
                        rainDrops[i] = 0;
                    }
                    rainDrops[i]++;
                }
            };
            clearInterval(matrixInterval);
            matrixInterval = setInterval(draw, 50);
        }

        function switchMode(mode) {
            if (mode === 'login') {
                document.getElementById('loginView').classList.remove('hidden');
                document.getElementById('registerView').classList.add('hidden');
            } else {
                document.getElementById('loginView').classList.add('hidden');
                document.getElementById('registerView').classList.remove('hidden');
                setType(document.getElementById('userTypeInput').value || 'student');
            }
        }

        function setType(type) {
            document.querySelectorAll('.cyber-type-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-' + type).classList.add('active');
            document.getElementById('userTypeInput').value = type;
            updateFields(type);
        }

        function updateFields(type) {
            const container = document.getElementById('cyberFields');
            let html = '';
            const emailField = `<div class="cyber-group"><label class="cyber-label">Email</label><input name="email" type="email" required class="cyber-input" placeholder="e.g., your.email@ukm.my"></div>`;
            const passField = `<div class="cyber-group"><label class="cyber-label">Password</label><input name="password" type="password" required class="cyber-input" placeholder="••••••••" minlength="6"></div>`;

            if (type === 'student') {
                html += `<div class="cyber-group"><label class="cyber-label">Full Name</label><input name="full_name" type="text" required class="cyber-input" placeholder="Your full name"></div>`;
                html += `<div class="cyber-group"><label class="cyber-label">Matric Number</label><input name="matric_number" type="text" required class="cyber-input" pattern="A.*" title="Must start with A" placeholder="e.g., A123456"></div>`;
                html += emailField;
                html += `<div class="cyber-group"><label class="cyber-label">Phone Number</label><input name="phone_number" type="tel" required class="cyber-input" placeholder="e.g., 0123456789"></div>`;
                html += passField;
            } else if (type === 'club') {
                html += `<div class="cyber-group"><label class="cyber-label">Club Name</label><input name="club_name" type="text" required class="cyber-input" placeholder="Your club's official name"></div>`;
                html += `<div class="cyber-group"><label class="cyber-label">Club ID</label><input name="club_id" type="text" required class="cyber-input" pattern="C.*" title="Must start with C" placeholder="e.g., C123"></div>`;
                html += emailField + passField;
            } else if (type === 'hep') {
                html += `<div class="cyber-group"><label class="cyber-label">Full Name</label><input name="full_name" type="text" required class="cyber-input" placeholder="Your full name"></div>`;
                html += `<div class="cyber-group"><label class="cyber-label">Work ID</label><input name="work_id" type="text" required class="cyber-input" pattern="K.*" title="Must start with K" placeholder="e.g., K123"></div>`;
                html += emailField + passField;
            }
            container.innerHTML = html;
        }

        window.addEventListener('resize', startMatrix);

        // --- INITIALIZE ---
        startMatrix();
        
        <?php if (isset($login_error)): ?>
            switchMode('login');
        <?php elseif (isset($register_error) || isset($register_success)): ?>
            switchMode('register');
        <?php else: ?>
             // If a mode is specified in URL (e.g., ?mode=register), switch to it
            const urlParams = new URLSearchParams(window.location.search);
            const mode = urlParams.get('mode') || 'login';
            switchMode(mode);
        <?php endif; ?>
    </script>
</body>
</html>