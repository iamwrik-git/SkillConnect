<?php
// admin/infoAuth.php
// SkillConnect - Stealth Gateway & Gimmick Terminal
require_once __DIR__ . '/../includes/session.php';

// =========================================================================
// 6-DIGIT AUTHORIZATION HASH (SHA-256)
// Target code: "956420"
// Hash: b531631ca1ec1dffaf1987510072b30bd0bbd90916bddbd28bfae7f003a52440
// =========================================================================
define('DEMO_AUTH_HASH', 'b531631ca1ec1dffaf1987510072b30bd0bbd90916bddbd28bfae7f003a52440');

// Handle AJAX verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $inputCode = trim($data['code'] ?? '');

    if (!empty($inputCode) && hash_equals(DEMO_AUTH_HASH, hash('sha256', $inputCode))) {
        $_SESSION['demo_authenticated'] = true;
        echo json_encode([
            'success' => true,
            'message' => 'Welcome Wrik',
            'redirect' => 'demonstation.php'
        ]);
        exit();
    }

    echo json_encode(['success' => false]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bash - skillconnect@srv-core:~ (tty1)</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #06090e;
            color: #00ff66;
            font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', Menlo, Monaco, Consolas, monospace;
            font-size: 13.5px;
            line-height: 1.55;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            user-select: none;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle scanline overlay */
        body::before {
            content: " ";
            display: block;
            position: fixed;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.22) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.02), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.02));
            z-index: 100;
            background-size: 100% 3px, 6px 100%;
            pointer-events: none;
            opacity: 0.55;
        }

        /* Clean Terminal Header (Mac window buttons removed) */
        .term-header {
            background: #080d16;
            border-bottom: 1px solid rgba(0, 255, 102, 0.15);
            height: 38px;
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            z-index: 10;
        }

        .term-title {
            color: #7b8ea5;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.3px;
        }

        .term-title span.term-chevron {
            color: #00ff66;
            font-weight: 700;
        }

        .term-exit-btn {
            color: #64748b;
            text-decoration: none;
            font-size: 11.5px;
            border: 1px solid rgba(100, 116, 139, 0.35);
            padding: 3px 10px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .term-exit-btn:hover {
            color: #f87171;
            border-color: #f87171;
            background: rgba(248, 113, 113, 0.08);
        }

        /* Terminal Viewport */
        .term-body {
            flex: 1;
            padding: 18px 22px;
            overflow-y: auto;
            position: relative;
            cursor: text;
        }

        .term-output {
            white-space: pre-wrap;
            word-break: break-word;
            margin-bottom: 6px;
        }

        .term-cyan {
            color: #38bdf8;
        }

        .term-white {
            color: #f8fafc;
            font-weight: 600;
        }

        .term-dim {
            color: #52796f;
        }

        .term-red {
            color: #f87171;
        }

        .term-prompt-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .term-prompt {
            color: #38bdf8;
            font-weight: 700;
            margin-right: 8px;
            user-select: none;
        }

        /* Password Dots Masking */
        .term-input-text {
            color: #00ff66;
            font-weight: 700;
            letter-spacing: 2px;
            white-space: pre;
            min-height: 1.5em;
            text-shadow: 0 0 6px rgba(0, 255, 102, 0.6);
        }

        .term-cursor {
            display: inline-block;
            width: 8px;
            height: 1.15em;
            background: #00ff66;
            vertical-align: middle;
            margin-left: 3px;
            animation: cursorBlink 1s step-start infinite;
            box-shadow: 0 0 8px #00ff66;
        }

        @keyframes cursorBlink {
            0%, 50% { opacity: 1; }
            50.01%, 100% { opacity: 0; }
        }

        /* Hidden Input Field */
        #hidden-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            left: -9999px;
            top: -9999px;
        }

        /* Generic Command Not Found Pop-up Toast */
        .cmd-error-toast {
            position: fixed;
            top: 50px;
            right: 20px;
            background: #141c2b;
            border: 1px solid rgba(248, 113, 113, 0.45);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.6);
            color: #f87171;
            font-size: 12.5px;
            padding: 9px 16px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 500;
            opacity: 0;
            transform: translateY(-8px);
            transition: all 0.22s ease;
            pointer-events: none;
        }

        .cmd-error-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Welcome Wrik Pop-up Modal */
        .welcome-popup-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(4, 7, 12, 0.8);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .welcome-popup-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .welcome-popup-card {
            background: #0b111e;
            border: 1px solid rgba(0, 255, 102, 0.5);
            box-shadow: 0 10px 40px rgba(0, 255, 102, 0.25), 0 0 20px rgba(0, 0, 0, 0.9);
            border-radius: 12px;
            padding: 32px 42px;
            text-align: center;
            transform: scale(0.88);
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 440px;
            width: 90%;
        }

        .welcome-popup-backdrop.active .welcome-popup-card {
            transform: scale(1);
        }

        .welcome-icon-box {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: rgba(0, 255, 102, 0.12);
            border: 1px solid #00ff66;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            color: #00ff66;
            box-shadow: 0 0 15px rgba(0, 255, 102, 0.3);
        }

        .welcome-popup-card h2 {
            color: #ffffff;
            font-size: 1.55rem;
            font-weight: 700;
            margin-bottom: 8px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .welcome-popup-card h2 span {
            color: #00ff66;
        }

        .welcome-popup-card p {
            color: #94a3b8;
            font-size: 0.95rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin-bottom: 14px;
        }

        .welcome-loader {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #38bdf8;
            font-size: 0.85rem;
        }

        .welcome-loader-dot {
            width: 6px;
            height: 6px;
            background: #38bdf8;
            border-radius: 50%;
            animation: pulseDot 1.2s infinite ease-in-out;
        }

        .welcome-loader-dot:nth-child(2) { animation-delay: 0.2s; }
        .welcome-loader-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes pulseDot {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
            40% { transform: scale(1.1); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- Header (Mac buttons removed) -->
    <header class="term-header">
        <div class="term-title">
            <span class="term-chevron">&gt;_</span>
            <span>bash &mdash; skillconnect@srv-core:~ (tty1)</span>
        </div>
        <div>
            <a href="../index.php" class="term-exit-btn" title="Exit">&larr; exit</a>
        </div>
    </header>

    <!-- Terminal Viewport (Realistic script traceback & memory error gimmick) -->
    <main class="term-body" id="terminal-body" onclick="focusInput()">
        <div class="term-output" id="terminal-output">[CRON #104] Executing /var/www/skillconnect/scripts/core_daemon.sh (PID: 4182)...
[SYS_WARN] Memory buffer overflow in /lib/x86_64/libcore_runtime.so.2 (addr: 0x7ffd51)
[SYS_ERR] Connection to MySQL daemon socket /var/run/mysqld/mysqld.sock timed out
[DEBUG] Environment dump: PATH=/usr/local/bin:/usr/bin, ENV=production, HOST=srv-core
[!] CRITICAL: Daemon halted unexpectedly with exit code 139 (SIGSEGV)
[!] Core dump generated at /tmp/skillconnect_dump_4182.log
[!] Dropping to low-level emergency shell...
</div>

        <div class="term-prompt-line" id="prompt-line">
            <span class="term-prompt">skillconnect@srv-core:~$</span>
            <span class="term-input-text" id="display-input"></span>
            <span class="term-cursor"></span>
        </div>
    </main>

    <!-- Generic Command Not Found Pop-up Toast -->
    <div class="cmd-error-toast" id="cmd-error-toast">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>bash: command not found</span>
    </div>

    <!-- Welcome Wrik Pop-up Modal -->
    <div class="welcome-popup-backdrop" id="welcome-modal">
        <div class="welcome-popup-card">
            <div class="welcome-icon-box">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h2>Welcome, <span>Wrik</span>!</h2>
            <p>Authorization verified. Entering demonstration...</p>
            <div class="welcome-loader">
                <span class="welcome-loader-dot"></span>
                <span class="welcome-loader-dot"></span>
                <span class="welcome-loader-dot"></span>
            </div>
        </div>
    </div>

    <!-- Hidden Input for keystrokes -->
    <input type="text" id="hidden-input" autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">

    <script>
        let isAuthenticating = false;
        let toastTimeout = null;

        const outputElem = document.getElementById('terminal-output');
        const displayInput = document.getElementById('display-input');
        const hiddenInput = document.getElementById('hidden-input');
        const termBody = document.getElementById('terminal-body');
        const welcomeModal = document.getElementById('welcome-modal');
        const errorToast = document.getElementById('cmd-error-toast');

        function focusInput() {
            hiddenInput.focus();
        }

        window.addEventListener('load', focusInput);
        document.addEventListener('click', focusInput);

        // Show masked dots (•) while typing
        hiddenInput.addEventListener('input', () => {
            const val = hiddenInput.value;
            // Render masked dots for each typed character
            displayInput.textContent = val.split('').map(() => '•').join('');

            // Automatic verification when 6 digits are reached
            if (!isAuthenticating && val.length === 6 && /^\d{6}$/.test(val)) {
                verifyCode(val);
            }
        });

        hiddenInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const val = hiddenInput.value.trim();
                const maskedDots = displayInput.textContent;

                hiddenInput.value = '';
                displayInput.textContent = '';

                if (isAuthenticating) return;

                // Echo the prompt with dots
                appendOutput(`<span class="term-cyan">skillconnect@srv-core:~$</span> <span class="term-white">${maskedDots}</span>`);

                if (!val) return;

                // Support exit/quit to dashboard
                if (val.toLowerCase() === 'exit' || val.toLowerCase() === 'quit') {
                    window.location.href = 'dashboard.php';
                    return;
                }

                // If 6-digit code entered on Enter
                if (/^\d{6}$/.test(val)) {
                    verifyCode(val);
                } else {
                    // Show generic command not found
                    showCommandNotFound();
                }
            }
        });

        function appendOutput(html) {
            outputElem.innerHTML += html + '\n';
            termBody.scrollTop = termBody.scrollHeight;
        }

        function showCommandNotFound() {
            // Append terminal error line
            appendOutput(`<span class="term-dim">bash: command not found</span>`);

            // Pop up the generic error toast
            errorToast.classList.add('show');
            if (toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                errorToast.classList.remove('show');
            }, 1800);
        }

        // Verify against backend SHA-256 hash of "956420"
        function verifyCode(code) {
            isAuthenticating = true;
            fetch('infoAuth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ code: code })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    hiddenInput.value = '';
                    displayInput.textContent = '';

                    // Print confirmation in terminal
                    appendOutput(`<span class="term-white">[✔] Authorization verified. Welcome Wrik!</span>`);
                    appendOutput(`<span class="term-cyan">[&gt;] Loading demonstration.php...</span>`);

                    // Pop up Welcome Wrik modal
                    welcomeModal.classList.add('active');

                    // Enter demonstration.php
                    setTimeout(() => {
                        window.location.href = data.redirect || 'demonstation.php';
                    }, 1200);
                } else {
                    isAuthenticating = false;
                    hiddenInput.value = '';
                    displayInput.textContent = '';
                    showCommandNotFound();
                }
            })
            .catch(() => {
                isAuthenticating = false;
                hiddenInput.value = '';
                displayInput.textContent = '';
                showCommandNotFound();
            });
        }
    </script>
</body>
</html>
