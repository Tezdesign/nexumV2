class CompreFaceManager {
    constructor() {
        this.stream = null;
        this.videoElement = null;
        this.canvas = null;
        this.isCapturing = false;
    }

    async startCamera(videoElement) {
        try {
            this.videoElement = videoElement;
            
            // Request camera access
            this.stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                } 
            });
            
            videoElement.srcObject = this.stream;
            this.isCapturing = true;
            
            return true;
        } catch (error) {
            console.error('Camera access error:', error);
            this.showError('Unable to access camera. Please ensure camera permissions are granted.');
            return false;
        }
    }

    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            this.isCapturing = false;
        }
        
        if (this.videoElement) {
            this.videoElement.srcObject = null;
        }
    }

    captureImage() {
        if (!this.videoElement || !this.isCapturing) {
            this.showError('Camera is not active');
            return null;
        }

        // Create canvas to capture image
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        canvas.width = this.videoElement.videoWidth;
        canvas.height = this.videoElement.videoHeight;
        
        // Draw video frame to canvas
        context.drawImage(this.videoElement, 0, 0, canvas.width, canvas.height);
        
        // Convert to base64 JPEG
        const imageData = canvas.toDataURL('image/jpeg', 0.9);
        
        return imageData;
    }

    async registerFace(imageData, subject) {
        try {
            const response = await fetch('/api/compreface/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    image: imageData,
                    subject: subject
                })
            });

            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Face registered successfully!');
                return true;
            } else {
                this.showError(result.message || 'Face registration failed');
                return false;
            }
        } catch (error) {
            console.error('Registration error:', error);
            this.showError('Network error during face registration');
            return false;
        }
    }

    async recognizeFace(imageData) {
        try {
            const response = await fetch('/api/compreface/recognize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    image: imageData
                })
            });

            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Face recognized! Redirecting...');
                // Redirect to dashboard or handle login
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
                return true;
            } else {
                this.showError(result.message || 'Face not recognized');
                return false;
            }
        } catch (error) {
            console.error('Recognition error:', error);
            this.showError('Network error during face recognition');
            return false;
        }
    }

    showSuccess(message) {
        this.showAlert(message, 'success');
    }

    showError(message) {
        this.showAlert(message, 'danger');
    }

    showAlert(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.compreface-alert');
        existingAlerts.forEach(alert => alert.remove());

        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} compreface-alert`;
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
        `;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px;">×</button>
        `;

        // Add close functionality
        alertDiv.querySelector('button').addEventListener('click', () => {
            alertDiv.remove();
        });

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);

        document.body.appendChild(alertDiv);
    }

    createCameraModal(onCapture, subject = null) {
        // Create modal HTML
        const modalHtml = `
            <div id="compreface-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9998;
            ">
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 16px;
                    max-width: 600px;
                    width: 90%;
                    text-align: center;
                ">
                    <h3 style="margin-bottom: 20px; color: #333;">
                        ${subject ? 'Register Your Face' : 'Face Recognition Login'}
                    </h3>
                    
                    <video id="compreface-video" style="
                        width: 100%;
                        max-width: 400px;
                        height: 300px;
                        object-fit: cover;
                        border-radius: 12px;
                        background: #000;
                        margin-bottom: 20px;
                    " autoplay></video>
                    
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="capture-btn" style="
                            background: #7c3aed;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 14px;
                            font-weight: 600;
                        ">${subject ? 'Register Face' : 'Recognize Face'}</button>
                        
                        <button id="cancel-btn" style="
                            background: #6b7280;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 14px;
                            font-weight: 600;
                        ">Cancel</button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = document.getElementById('compreface-modal');
        const video = document.getElementById('compreface-video');
        const captureBtn = document.getElementById('capture-btn');
        const cancelBtn = document.getElementById('cancel-btn');

        // Start camera
        this.startCamera(video);

        // Capture button handler
        captureBtn.addEventListener('click', async () => {
            const imageData = this.captureImage();
            if (imageData) {
                captureBtn.disabled = true;
                captureBtn.textContent = subject ? 'Registering...' : 'Recognizing...';
                
                const success = subject 
                    ? await this.registerFace(imageData, subject)
                    : await this.recognizeFace(imageData);
                
                if (success) {
                    this.closeCameraModal();
                }
                
                captureBtn.disabled = false;
                captureBtn.textContent = subject ? 'Register Face' : 'Recognize Face';
            }
        });

        // Cancel button handler
        cancelBtn.addEventListener('click', () => {
            this.closeCameraModal();
        });

        // Close on background click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeCameraModal();
            }
        });
    }

    closeCameraModal() {
        this.stopCamera();
        const modal = document.getElementById('compreface-modal');
        if (modal) {
            modal.remove();
        }
    }
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

// Global instance
window.compreFaceManager = new CompreFaceManager();
