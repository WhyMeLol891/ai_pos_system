/**
 * Camera Capture & Webcam Manager
 * AI Camera POS System
 * 
 * Supports both live HTML5 getUserMedia webcam stream (desktop/laptop) 
 * and native mobile smartphone camera capture via file input fallback.
 */

class CameraManager {
    constructor(options = {}) {
        this.videoElement = options.videoElement || document.getElementById('cameraVideo');
        this.canvasElement = options.canvasElement || document.getElementById('cameraCanvas');
        this.previewImage = options.previewImage || document.getElementById('cameraPreview');
        this.stream = null;
        this.facingMode = 'environment'; // Prefer rear camera on mobile phones
        this.capturedBase64 = null;
    }

    /**
     * Start live webcam stream
     */
    async startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Your browser does not support live camera access. Please use the "Upload / Mobile Photo" option below.');
        }

        this.stopCamera();

        const constraints = {
            video: {
                facingMode: { ideal: this.facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        };

        try {
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            if (this.videoElement) {
                this.videoElement.srcObject = this.stream;
                this.videoElement.classList.remove('d-none');
                if (this.previewImage) this.previewImage.classList.add('d-none');
                await this.videoElement.play();
            }
            return true;
        } catch (err) {
            console.warn('Live camera stream error:', err);
            // Retry with basic video constraints if facingMode failed
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: true });
                if (this.videoElement) {
                    this.videoElement.srcObject = this.stream;
                    this.videoElement.classList.remove('d-none');
                    if (this.previewImage) this.previewImage.classList.add('d-none');
                    await this.videoElement.play();
                }
                return true;
            } catch (fallbackErr) {
                throw new Error('Unable to access camera: ' + fallbackErr.message);
            }
        }
    }

    /**
     * Stop active stream
     */
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.videoElement) {
            this.videoElement.srcObject = null;
        }
    }

    /**
     * Switch front / rear camera
     */
    async switchCamera() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        return await this.startCamera();
    }

    /**
     * Capture snapshot from active video stream
     */
    captureSnapshot() {
        if (!this.videoElement || this.videoElement.videoWidth === 0) {
            throw new Error('No active video stream to capture.');
        }

        const video = this.videoElement;
        const canvas = this.canvasElement || document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Export as JPEG
        this.capturedBase64 = canvas.toDataURL('image/jpeg', 0.85);

        if (this.previewImage) {
            this.previewImage.src = this.capturedBase64;
            this.previewImage.classList.remove('d-none');
            this.videoElement.classList.add('d-none');
        }

        return this.capturedBase64;
    }

    /**
     * Handle native mobile file input capture
     */
    handleFileInput(file) {
        return new Promise((resolve, reject) => {
            if (!file) {
                reject(new Error('No file selected.'));
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.capturedBase64 = e.target.result;
                if (this.previewImage) {
                    this.previewImage.src = this.capturedBase64;
                    this.previewImage.classList.remove('d-none');
                    if (this.videoElement) this.videoElement.classList.add('d-none');
                }
                this.stopCamera();
                resolve(this.capturedBase64);
            };
            reader.onerror = (err) => reject(err);
            reader.readAsDataURL(file);
        });
    }

    /**
     * Get the latest captured base64 data
     */
    getCapturedData() {
        return this.capturedBase64;
    }

    /**
     * Reset capture
     */
    reset() {
        this.capturedBase64 = null;
        if (this.previewImage) {
            this.previewImage.src = '';
            this.previewImage.classList.add('d-none');
        }
        if (this.videoElement) {
            this.videoElement.classList.remove('d-none');
        }
    }
}
