import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;

/**
 * uploadProgress — Alpine.js component untuk upload video dengan progress bar.
 * Menggunakan XMLHttpRequest agar mendapatkan upload progress events yang akurat.
 */
window.uploadProgress = function () {
    return {
        uploading: false,
        progress: 0,
        fileName: '',

        onFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.fileName = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
            }
        },

        startUpload(event) {
            event.preventDefault();
            const form = event.target;
            const fileInput = form.querySelector('input[type="file"]');

            if (!fileInput || !fileInput.files[0]) {
                form.submit();
                return;
            }

            this.uploading = true;
            this.progress = 1;

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    // Cap at 90% — server-side processing takes the remaining 10%
                    this.progress = Math.min(90, Math.round((e.loaded / e.total) * 90));
                }
            });

            xhr.addEventListener('load', () => {
                this.progress = 100;
                // Follow redirect from server response
                if (xhr.responseURL) {
                    window.location.href = xhr.responseURL;
                } else {
                    window.location.reload();
                }
            });

            xhr.addEventListener('error', () => {
                this.uploading = false;
                this.progress = 0;
                alert('Upload gagal. Silakan coba lagi.');
            });

            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'text/html,application/xhtml+xml');
            // CSRF header from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken.getAttribute('content'));
            }
            xhr.send(formData);
        },
    };
};

/**
 * renderJobPoller — Alpine.js component untuk auto-refresh status render job.
 * Digunakan di halaman render/show untuk polling setiap 4 detik.
 */
window.renderJobPoller = function (jobId, initialStatus) {
    return {
        status: initialStatus,
        progress: 0,
        interval: null,

        init() {
            if (['pending', 'processing'].includes(this.status)) {
                this.startPolling();
            }
        },

        startPolling() {
            this.interval = setInterval(() => this.poll(), 4000);
        },

        async poll() {
            try {
                const response = await fetch('/api/render-jobs/' + jobId + '/status');
                if (!response.ok) return;
                const data = await response.json();
                this.status = data.status;
                this.progress = data.progress ?? this.progress;

                if (!['pending', 'processing'].includes(this.status)) {
                    clearInterval(this.interval);
                    if (this.status === 'completed') {
                        window.location.reload();
                    }
                }
            } catch (e) {
                // silently ignore network errors during polling
            }
        },

        destroy() {
            if (this.interval) clearInterval(this.interval);
        },
    };
};

Alpine.start();
