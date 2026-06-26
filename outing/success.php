<?php
// success.php - Outing scan result status screen (exits/entries check-in validation)

$status = $_GET['status'] ?? 'failed';
$error = $_GET['error'] ?? '';

$is_success = (strpos($status, 'success') !== false);
$message = '';
$sub_message = '';

if ($status === 'success_exit') {
    $message = 'OUTING EXIT APPROVED';
    $sub_message = 'Gate exit successfully registered. You may proceed.';
} elseif ($status === 'success_entry') {
    $message = 'OUTING RETURN REGISTERED';
    $sub_message = 'Welcome back! Hostel entry check-in successfully logged.';
} else {
    $message = 'ACCESS DENIED';
    if ($error === 'student_not_found') {
        $sub_message = 'Student Roll Number is not registered in the system.';
    } elseif ($error === 'db_error') {
        $sub_message = 'A system database error occurred. Please contact admin.';
    } else {
        $sub_message = 'Verification process failed. Invalid outpass record.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outpass Verification Result - Student Hostel Outpass System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="../css/all.min.css" rel="stylesheet">
    <!-- Global CSS Stylesheet -->
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .status-container {
            max-width: 460px;
            width: 100%;
        }

        .status-card {
            border-radius: 24px;
            padding: 3.5rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        /* Success glow border */
        .status-card.success-theme {
            border: 2px solid rgba(16, 185, 129, 0.4) !important;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.2);
            background: rgba(11, 28, 23, 0.7);
        }

        /* Failure glow border */
        .status-card.failed-theme {
            border: 2px solid rgba(239, 68, 68, 0.4) !important;
            box-shadow: 0 10px 40px rgba(239, 68, 68, 0.2);
            background: rgba(28, 11, 11, 0.7);
        }

        .indicator-icon-wrapper {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            margin-bottom: 2rem;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .success-theme .indicator-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
            border: 2px solid rgba(16, 185, 129, 0.2);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }

        .failed-theme .indicator-icon-wrapper {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
            border: 2px solid rgba(239, 68, 68, 0.2);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }

        @keyframes popIn {
            0% { transform: scale(0.6); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .redirect-countdown {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="status-container animate-fade-in">
        <div class="glass-card status-card <?php echo $is_success ? 'success-theme' : 'failed-theme'; ?>">
            <div class="indicator-icon-wrapper">
                <?php if ($is_success): ?>
                    <i class="fa-solid fa-circle-check"></i>
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark"></i>
                <?php endif; ?>
            </div>

            <h2 class="fw-bold mb-2 tracking-wide <?php echo $is_success ? 'text-success' : 'text-danger'; ?>" style="font-size: 1.45rem;">
                <?php echo $message; ?>
            </h2>
            <p class="text-secondary mb-0 px-3">
                <?php echo htmlspecialchars($sub_message); ?>
            </p>

            <div class="redirect-countdown">
                <i class="fa-solid fa-spinner fa-spin me-2"></i>Redirecting to home page in <span id="countdown">3</span> seconds...
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Play notification tone
        window.addEventListener('DOMContentLoaded', () => {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            <?php if ($is_success): ?>
                // High pitch beep for success
                osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                osc.start();
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                osc.stop(ctx.currentTime + 0.4);
            <?php else: ?>
                // Low buzz tone for error
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, ctx.currentTime); // Low freq buzz
                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                osc.start();
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                osc.stop(ctx.currentTime + 0.55);
            <?php endif; ?>
        });

        // 3 Seconds Automatic Redirect
        let timeLeft = 3;
        const countdownEl = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            timeLeft--;
            countdownEl.innerText = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(timer);
                const urlParams = new URLSearchParams(window.location.search);
                const redirect = urlParams.get('redirect');
                window.location.href = redirect ? redirect : '../index.html';
            }
        }, 1000);
    </script>
</body>
</html>
