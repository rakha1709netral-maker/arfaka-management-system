const openPrintDialog = () => {
    window.focus();
    window.setTimeout(() => window.print(), 100);
};

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', openPrintDialog);
});

if (document.querySelector('[data-auto-print]')) {
    window.setTimeout(openPrintDialog, 350);
}

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const getCurrentPosition = () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
        reject(new Error('Browser tidak mendukung lokasi. Gunakan browser terbaru melalui HTTPS.'));

        return;
    }

    navigator.geolocation.getCurrentPosition(resolve, () => {
        reject(new Error('Lokasi tidak dapat dibaca. Aktifkan GPS dan izinkan akses lokasi, lalu coba lagi.'));
    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
});

const canvasBlob = (canvas, quality) => new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
        if (blob) {
            resolve(blob);
        } else {
            reject(new Error('Kompresi foto gagal. Silakan foto ulang.'));
        }
    }, 'image/jpeg', quality);
});

const blobDataUrl = (blob) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(new Error('Foto gagal disiapkan untuk dikirim. Silakan foto ulang.'));
    reader.readAsDataURL(blob);
});

const stopCamera = (stream) => {
    stream?.getTracks().forEach((track) => track.stop());
};

const captureCameraPhoto = async (video, capturedAt, position) => {
    if (!video.videoWidth || !video.videoHeight) {
        throw new Error('Kamera belum siap. Tunggu sebentar lalu ambil foto lagi.');
    }

    const scale = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Browser tidak dapat memproses foto. Gunakan browser terbaru.');
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    const fontSize = Math.max(18, Math.round(canvas.width / 42));
    const padding = Math.round(fontSize * 0.7);
    const lineHeight = Math.round(fontSize * 1.35);
    const latitude = position.coords.latitude.toFixed(7);
    const longitude = position.coords.longitude.toFixed(7);
    const timestamp = capturedAt.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' });
    const watermarkHeight = lineHeight * 2 + padding * 2;

    context.fillStyle = 'rgba(2, 6, 23, 0.78)';
    context.fillRect(0, canvas.height - watermarkHeight, canvas.width, watermarkHeight);
    context.fillStyle = '#ffffff';
    context.font = `600 ${fontSize}px system-ui, sans-serif`;
    context.fillText(`ARFAKA TOUR · ${timestamp}`, padding, canvas.height - padding - lineHeight);
    context.font = `${fontSize}px system-ui, sans-serif`;
    context.fillText(`Lokasi ${latitude}, ${longitude} · akurasi ±${Math.round(position.coords.accuracy)} m`, padding, canvas.height - padding);

    const original = await canvasBlob(canvas, 0.92);
    let quality = 0.78;
    let compressed = await canvasBlob(canvas, quality);

    while (compressed.size > 1_900_000 && quality > 0.48) {
        quality -= 0.1;
        compressed = await canvasBlob(canvas, quality);
    }

    if (compressed.size > 2_097_152) {
        throw new Error('Foto masih terlalu besar setelah dikompres. Dekatkan kamera dan foto ulang.');
    }

    return { blob: compressed, originalSize: original.size };
};

document.querySelectorAll('form[data-camera-expense]').forEach((form) => {
    const video = form.querySelector('[data-camera-video]');
    const preview = form.querySelector('[data-camera-preview]');
    const status = form.querySelector('[data-camera-status]');
    const startButton = form.querySelector('[data-camera-start]');
    const captureButton = form.querySelector('[data-camera-capture]');
    const retakeButton = form.querySelector('[data-camera-retake]');
    const receiptData = form.querySelector('[data-receipt-data]');
    let cameraStream = null;
    let previewUrl = null;

    const showStatus = (message, isError = false) => {
        status.classList.remove('hidden', 'border-red-400/30', 'text-red-200', 'border-emerald-400/30', 'text-emerald-200');
        status.classList.add(isError ? 'border-red-400/30' : 'border-emerald-400/30');
        status.classList.add(isError ? 'text-red-200' : 'text-emerald-200');
        status.textContent = message;
    };

    const resetCapture = () => {
        receiptData.value = '';
        form.querySelectorAll('[data-captured-at], [data-latitude], [data-longitude], [data-location-accuracy], [data-original-size]')
            .forEach((field) => { field.value = ''; });
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        preview.removeAttribute('src');
        preview.classList.add('hidden');
        retakeButton.classList.add('hidden');
    };

    const startCamera = async () => {
        resetCapture();
        showStatus('Meminta izin kamera…');

        if (!navigator.mediaDevices?.getUserMedia) {
            showStatus('Kamera browser tidak tersedia. Buka aplikasi melalui HTTPS atau localhost dan gunakan browser terbaru.', true);

            return;
        }

        try {
            stopCamera(cameraStream);
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
                audio: false,
            });
            video.srcObject = cameraStream;
            await video.play();
            video.classList.remove('hidden');
            startButton.classList.add('hidden');
            captureButton.classList.remove('hidden');
            showStatus('Kamera siap. Pastikan bukti terlihat jelas lalu tekan Ambil foto.');
        } catch {
            stopCamera(cameraStream);
            cameraStream = null;
            showStatus('Kamera tidak dapat dibuka. Izinkan akses kamera di browser, lalu coba lagi.', true);
        }
    };

    startButton.addEventListener('click', startCamera);
    retakeButton.addEventListener('click', startCamera);

    captureButton.addEventListener('click', async () => {
        captureButton.disabled = true;
        showStatus('Membaca GPS, memberi timestamp, dan mengompres foto…');

        try {
            const capturedAt = new Date();
            const position = await getCurrentPosition();
            const result = await captureCameraPhoto(video, capturedAt, position);
            receiptData.value = await blobDataUrl(result.blob);
            form.querySelector('[data-captured-at]').value = capturedAt.toISOString();
            form.querySelector('[data-latitude]').value = position.coords.latitude;
            form.querySelector('[data-longitude]').value = position.coords.longitude;
            form.querySelector('[data-location-accuracy]').value = position.coords.accuracy;
            form.querySelector('[data-original-size]').value = result.originalSize;

            stopCamera(cameraStream);
            cameraStream = null;
            video.srcObject = null;
            video.classList.add('hidden');
            captureButton.classList.add('hidden');
            previewUrl = URL.createObjectURL(result.blob);
            preview.src = previewUrl;
            preview.classList.remove('hidden');
            retakeButton.classList.remove('hidden');
            showStatus(`Foto siap dikirim (${Math.round(result.blob.size / 1024)} KB).`);
        } catch (error) {
            showStatus(error instanceof Error ? error.message : 'Foto tidak dapat diproses. Silakan coba lagi.', true);
        } finally {
            captureButton.disabled = false;
        }
    });

    form.addEventListener('submit', (event) => {
        if (!receiptData.value) {
            event.preventDefault();
            showStatus('Ambil foto bukti dan tunggu hingga statusnya siap dikirim.', true);
        }
    });

    window.addEventListener('pagehide', () => stopCamera(cameraStream));
});
