// scanner.js - QR Code scanning configurations and event listeners

/**
 * Initializes and starts the camera scanner.
 * @param {string} containerId - The ID of the HTML div container for the scanner.
 * @param {string} redirectUrl - The target base URL (e.g., 'outing.php' or 'check_pass.php').
 */
function initializeScanner(containerId, redirectUrl) {
    if (typeof Html5Qrcode === 'undefined') {
        const scannerStatus = document.getElementById('scanner-status');
        if (scannerStatus) {
            scannerStatus.innerHTML = `<span class="text-danger">Error: QR Scanner library failed to load. Please verify your internet connection (to load fallback CDN) or ensure the local script file is available in the <code>js/</code> directory.</span>`;
        }
        const startBtn = document.getElementById('btn-start-scanner');
        if (startBtn) startBtn.disabled = true;
        return;
    }
    const html5QrCode = new Html5Qrcode(containerId);
    const scannerStatus = document.getElementById('scanner-status');
    const startBtn = document.getElementById('btn-start-scanner');
    const stopBtn = document.getElementById('btn-stop-scanner');
    
    // Play beep sound on scan
    const beepSound = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQQAAAAAAA=='); 
    
    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
        // Play beep sound
        try {
            beepSound.play();
        } catch (e) {
            console.log('Audio feedback could not be played:', e);
        }
        
        // Update status
        if (scannerStatus) {
            scannerStatus.innerHTML = `<span class="text-success">QR Code Scanned! Redirecting...</span>`;
            scannerStatus.classList.remove('text-warning');
        }
        
        // Stop scanning
        html5QrCode.stop().then(() => {
            // Redirect to destination with roll number parameter
            const separator = redirectUrl.includes('?') ? '&' : '?';
            window.location.href = `${redirectUrl}${separator}roll_number=${encodeURIComponent(decodedText)}`;
        }).catch(err => {
            console.warn('Failed to stop scanner after success:', err);
            // Redirect anyway
            const separator = redirectUrl.includes('?') ? '&' : '?';
            window.location.href = `${redirectUrl}${separator}roll_number=${encodeURIComponent(decodedText)}`;
        });
    };

    const config = { 
        fps: 10, 
        qrbox: (width, height) => {
            // Responsive box sizing
            const minDimension = Math.min(width, height);
            const boxSize = Math.floor(minDimension * 0.7);
            return { width: boxSize, height: boxSize };
        }
    };

    // Click handlers for Start/Stop
    if (startBtn) {
        startBtn.addEventListener('click', () => {
            if (scannerStatus) {
                scannerStatus.innerHTML = '<span class="text-warning">Initializing camera...</span>';
            }
            
            // Start camera using environment (back) camera preferred
            html5QrCode.start(
                { facingMode: "environment" }, 
                config, 
                qrCodeSuccessCallback,
                (errorMessage) => {
                    // Ignore noisy frame errors
                }
            ).then(() => {
                if (scannerStatus) {
                    scannerStatus.innerHTML = '<span class="text-success">Scanner Active. Align QR code inside the box.</span>';
                }
                startBtn.classList.add('d-none');
                if (stopBtn) stopBtn.classList.remove('d-none');
            }).catch(err => {
                console.error("Unable to start scanning:", err);
                if (scannerStatus) {
                    scannerStatus.innerHTML = `<span class="text-danger">Camera Access Error: ${err.message || err}. Use manual input.</span>`;
                }
            });
        });
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', () => {
            html5QrCode.stop().then(() => {
                if (scannerStatus) {
                    scannerStatus.innerHTML = '<span class="text-muted">Scanner Stopped.</span>';
                }
                stopBtn.classList.add('d-none');
                if (startBtn) startBtn.classList.remove('d-none');
            }).catch(err => {
                console.error("Failed to stop scanner:", err);
            });
        });
    }
}
