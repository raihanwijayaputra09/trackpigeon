lucide.createIcons();

const googleLoginBtn = document.querySelector('#googleLoginBtn');
if (googleLoginBtn) {
    let googleReady = false;
    const initGoogleLogin = () => {
        if (googleReady || !window.google?.accounts?.id) {
            return googleReady;
        }
        google.accounts.id.initialize({
            client_id: googleLoginBtn.dataset.clientId,
            callback: (response) => {
                document.querySelector('#googleCredential').value = response.credential;
                document.querySelector('#googleLoginForm').submit();
            },
        });
        googleReady = true;
        return true;
    };
    const waitGoogle = setInterval(() => {
        if (initGoogleLogin()) clearInterval(waitGoogle);
    }, 250);
    googleLoginBtn.addEventListener('click', () => {
        if (!initGoogleLogin()) {
            alert('Google Identity Services belum siap. Coba beberapa detik lagi.');
            return;
        }
        google.accounts.id.prompt();
    });
}

const gpsConfig = {
    required: document.body?.dataset.gpsRequired === '1',
    maxAccuracy: Number(document.body?.dataset.gpsMaxAccuracy || 100),
    maxClockDistance: Number(document.body?.dataset.gpsClockDistance || 100),
    loftLat: Number(document.body?.dataset.loftLat || NaN),
    loftLng: Number(document.body?.dataset.loftLng || NaN),
};
let lastGpsPosition = null;
let gpsBanner = null;

const gpsIsSupported = () => Boolean(navigator.geolocation);
const gpsOptions = { enableHighAccuracy: true, timeout: 16000, maximumAge: 0 };

const gpsDistanceMeter = (lat1, lng1, lat2, lng2) => {
    const rad = Math.PI / 180;
    const earth = 6371000;
    const dLat = (lat2 - lat1) * rad;
    const dLng = (lng2 - lng1) * rad;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) ** 2;
    return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const getGpsPosition = () => new Promise((resolve, reject) => {
    if (!gpsIsSupported()) {
        reject(new Error('Browser tidak mendukung GPS/geolocation.'));
        return;
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, gpsOptions);
});

const getGpsStatusText = (position) => {
    const accuracy = Math.round(position.coords.accuracy || 0);
    const parts = [`GPS aktif`, `akurasi ${accuracy} m`];
    if (Number.isFinite(gpsConfig.loftLat) && Number.isFinite(gpsConfig.loftLng)) {
        const distance = gpsDistanceMeter(position.coords.latitude, position.coords.longitude, gpsConfig.loftLat, gpsConfig.loftLng);
        parts.push(`jarak kandang ${Math.round(distance)} m`);
    }
    return parts.join(' / ');
};

const showGpsBanner = (message, state = 'warning') => {
    if (!gpsConfig.required) return;
    if (!gpsBanner) {
        gpsBanner = document.createElement('div');
        gpsBanner.className = 'gps-guard';
        gpsBanner.innerHTML = `
            <div>
                <strong>GPS akurat wajib aktif</strong>
                <span data-gps-message></span>
            </div>
            <button class="btn btn-sm btn-primary" type="button" data-gps-retry>
                <i data-lucide="crosshair"></i>Aktifkan GPS
            </button>
        `;
        document.body.appendChild(gpsBanner);
        gpsBanner.querySelector('[data-gps-retry]')?.addEventListener('click', () => requestAccurateGps(true));
    }
    gpsBanner.dataset.state = state;
    gpsBanner.querySelector('[data-gps-message]').textContent = message;
    gpsBanner.hidden = false;
    lucide.createIcons();
};

const setClockButtonsState = (ready, label = '') => {
    document.querySelectorAll('.race-clock-btn').forEach((button) => {
        if (button.dataset.clocked === '1') return;
        button.disabled = !ready;
        if (!ready && label) {
            button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
            button.innerHTML = `<i data-lucide="crosshair"></i>${label}`;
        } else if (ready && button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
        }
    });
    lucide.createIcons();
};

const applyGpsToHomeMap = (position, onlyIfEmpty = true) => {
    const homeTarget = coordinateMaps.homeMap;
    const homeLat = document.querySelector('#homeLat');
    const homeLon = document.querySelector('#homeLon');
    if (!homeTarget || !homeLat || !homeLon) return;
    if (onlyIfEmpty && homeLat.value && homeLon.value) return;
    homeTarget.setPoint(position.coords.latitude, position.coords.longitude);
};

const requestAccurateGps = async (fromUserAction = false) => {
    try {
        const position = await getGpsPosition();
        lastGpsPosition = position;
        applyGpsToHomeMap(position, true);

        const accuracy = Number(position.coords.accuracy || Infinity);
        if (accuracy > gpsConfig.maxAccuracy) {
            showGpsBanner(`Akurasi GPS masih ${Math.round(accuracy)} m. Maksimal ${gpsConfig.maxAccuracy} m. Coba aktifkan mode akurasi tinggi atau pindah ke area terbuka.`, 'danger');
            setClockButtonsState(false, 'GPS belum akurat');
            return position;
        }

        showGpsBanner(getGpsStatusText(position), 'ok');
        setClockButtonsState(true);
        window.setTimeout(() => {
            if (gpsBanner?.dataset.state === 'ok') {
                gpsBanner.hidden = true;
            }
        }, 4500);
        return position;
    } catch (error) {
        const message = fromUserAction
            ? 'GPS belum bisa diambil. Izinkan lokasi browser, aktifkan GPS perangkat, lalu coba lagi.'
            : 'Izinkan akses lokasi agar koordinat kandang dan clocking bisa diverifikasi.';
        showGpsBanner(message, 'danger');
        setClockButtonsState(false, 'GPS wajib');
        return null;
    }
};

if (gpsConfig.required) {
    setClockButtonsState(false, 'Menunggu GPS');
    window.setTimeout(() => requestAccurateGps(false), 700);
}

const coordinateMaps = {};

document.querySelectorAll('.map-box').forEach((mapEl) => {
    if (!window.L) {
        return;
    }

    const latInput = document.querySelector(`#${mapEl.dataset.inputLat}`);
    const lonInput = document.querySelector(`#${mapEl.dataset.inputLon}`);
    if (!latInput || !lonInput) {
        return;
    }

    const parseCoordinate = (value, fallback) => {
        const number = Number(value);
        return Number.isFinite(number) ? number : fallback;
    };
    const initialLat = parseCoordinate(latInput.value || mapEl.dataset.lat, -7.797068);
    const initialLon = parseCoordinate(lonInput.value || mapEl.dataset.lon, 110.370529);
    const map = L.map(mapEl.id, {
        scrollWheelZoom: true,
        tap: true,
        touchZoom: 'center',
        wheelDebounceTime: 24,
        wheelPxPerZoomLevel: 80,
    }).setView([initialLat, initialLon], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const marker = L.marker([initialLat, initialLon], { draggable: true }).addTo(map);

    const setPoint = (lat, lon, moveMap = true) => {
        const cleanLat = Number(lat);
        const cleanLon = Number(lon);
        if (!Number.isFinite(cleanLat) || !Number.isFinite(cleanLon)) {
            return;
        }
        latInput.value = cleanLat.toFixed(8);
        lonInput.value = cleanLon.toFixed(8);
        marker.setLatLng([cleanLat, cleanLon]);
        if (moveMap) {
            map.setView([cleanLat, cleanLon], Math.max(map.getZoom(), 15));
        }
    };

    map.on('click', (event) => setPoint(event.latlng.lat, event.latlng.lng, false));
    marker.on('dragend', () => {
        const point = marker.getLatLng();
        setPoint(point.lat, point.lng, false);
    });

    [latInput, lonInput].forEach((input) => {
        input?.addEventListener('change', () => setPoint(latInput.value, lonInput.value));
    });

    coordinateMaps[mapEl.id] = { map, setPoint };
    const invalidateMapSize = () => {
        window.requestAnimationFrame(() => map.invalidateSize({ pan: false }));
    };
    setTimeout(invalidateMapSize, 150);
    setTimeout(invalidateMapSize, 500);
    window.addEventListener('load', invalidateMapSize, { once: true });
    window.addEventListener('resize', invalidateMapSize);
    window.addEventListener('orientationchange', () => setTimeout(invalidateMapSize, 250));

    if ('ResizeObserver' in window) {
        new ResizeObserver(invalidateMapSize).observe(mapEl);
    }
    if ('IntersectionObserver' in window) {
        new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                invalidateMapSize();
            }
        }).observe(mapEl);
    }
});

document.querySelectorAll('.locate-btn').forEach((button) => {
    button.addEventListener('click', async () => {
        const target = coordinateMaps[button.dataset.mapTarget];
        if (!target || !gpsIsSupported()) {
            alert('Browser belum mengizinkan akses lokasi.');
            return;
        }

        button.disabled = true;
        button.innerHTML = '<i data-lucide="loader-circle"></i>Mencari GPS...';
        lucide.createIcons();
        const position = await requestAccurateGps(true);
        if (position) {
            target.setPoint(position.coords.latitude, position.coords.longitude);
        } else {
            alert('Lokasi tidak bisa diambil. Aktifkan GPS akurat atau klik titik di peta secara manual.');
        }
        button.disabled = false;
        button.innerHTML = '<i data-lucide="crosshair"></i>Lokasi Saya';
        lucide.createIcons();
    });
});

const birdRingSearch = document.querySelector('#birdRingSearch');
const birdColorSearch = document.querySelector('#birdColorSearch');
const birdGenderSearch = document.querySelector('#birdGenderSearch');
const birdFilterReset = document.querySelector('#birdFilterReset');
const birdTable = document.querySelector('#birdTable');
if (birdTable && (birdRingSearch || birdColorSearch || birdGenderSearch)) {
    const applyBirdFilters = () => {
        const ring = (birdRingSearch?.value || '').trim().toLowerCase();
        const color = (birdColorSearch?.value || '').trim().toLowerCase();
        const gender = (birdGenderSearch?.value || '').trim().toLowerCase();

        birdTable.querySelectorAll('.bird-card').forEach((card) => {
            const matchesRing = !ring || card.dataset.ring.includes(ring);
            const matchesColor = !color || card.dataset.color.includes(color);
            const matchesGender = !gender || card.dataset.gender === gender;
            card.style.display = matchesRing && matchesColor && matchesGender ? '' : 'none';
        });
    };

    [birdRingSearch, birdColorSearch, birdGenderSearch].forEach((input) => {
        input?.addEventListener('input', applyBirdFilters);
        input?.addEventListener('change', applyBirdFilters);
    });

    birdFilterReset?.addEventListener('click', () => {
        if (birdRingSearch) birdRingSearch.value = '';
        if (birdColorSearch) birdColorSearch.value = '';
        if (birdGenderSearch) birdGenderSearch.value = '';
        applyBirdFilters();
    });
}

const liveRingSearch = document.querySelector('#liveRingSearch');
const liveColorSearch = document.querySelector('#liveColorSearch');
const liveGenderSearch = document.querySelector('#liveGenderSearch');
const liveFilterReset = document.querySelector('#liveFilterReset');
const liveRaceTable = document.querySelector('#liveRaceTable');
const liveFilterEmpty = document.querySelector('#liveFilterEmpty');
if (liveRaceTable && (liveRingSearch || liveColorSearch || liveGenderSearch)) {
    const applyLiveFilters = () => {
        const ring = (liveRingSearch?.value || '').trim().toLowerCase();
        const color = (liveColorSearch?.value || '').trim().toLowerCase();
        const gender = (liveGenderSearch?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        liveRaceTable.querySelectorAll('.race-row').forEach((row) => {
            const matchesRing = !ring || row.dataset.ring.includes(ring);
            const matchesColor = !color || row.dataset.color.includes(color);
            const matchesGender = !gender || row.dataset.gender === gender;
            const visible = matchesRing && matchesColor && matchesGender;
            row.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount += 1;
            }
        });

        if (liveFilterEmpty) {
            liveFilterEmpty.hidden = visibleCount > 0;
        }
    };

    [liveRingSearch, liveColorSearch, liveGenderSearch].forEach((input) => {
        input?.addEventListener('input', applyLiveFilters);
        input?.addEventListener('change', applyLiveFilters);
    });

    liveFilterReset?.addEventListener('click', () => {
        if (liveRingSearch) liveRingSearch.value = '';
        if (liveColorSearch) liveColorSearch.value = '';
        if (liveGenderSearch) liveGenderSearch.value = '';
        applyLiveFilters();
    });
}

const trainingBirdGrid = document.querySelector('#trainingBirdGrid');
const squadQuickFilter = document.querySelector('#squadQuickFilter');
const selectAllToggle = document.querySelector('#selectAllToggle');
const invertSelectionBtn = document.querySelector('#invertSelectionBtn');
if (trainingBirdGrid) {
    const cards = [...trainingBirdGrid.querySelectorAll('.bird-check')];
    const visibleCards = () => cards.filter((card) => card.style.display !== 'none');
    const birdCounterEl = document.querySelector('#birdCounter');
    const syncSquadState = () => {
        let checkedCount = 0;
        cards.forEach((card) => {
            const checked = card.querySelector('input[type="checkbox"]').checked;
            card.classList.toggle('is-selected', checked);
            if (checked) checkedCount += 1;
        });
        if (birdCounterEl) {
            birdCounterEl.textContent = `${checkedCount}/${cards.length} dipilih`;
        }
        if (selectAllToggle) {
            const visible = visibleCards();
            const allVisibleChecked = visible.length > 0 && visible.every((card) => card.querySelector('input').checked);
            selectAllToggle.classList.toggle('is-active', allVisibleChecked);
            selectAllToggle.querySelector('span').textContent = allVisibleChecked ? 'Bersihkan' : 'Pilih Semua';
        }
    };
    const applySquadFilter = () => {
        const term = (squadQuickFilter?.value || '').trim().toLowerCase();
        cards.forEach((card) => {
            const visible = !term || card.dataset.ring.includes(term) || card.dataset.color.includes(term);
            card.style.display = visible ? '' : 'none';
        });
        syncSquadState();
    };

    cards.forEach((card) => card.querySelector('input')?.addEventListener('change', syncSquadState));
    squadQuickFilter?.addEventListener('input', applySquadFilter);
    selectAllToggle?.addEventListener('click', () => {
        const visible = visibleCards();
        const shouldSelect = visible.some((card) => !card.querySelector('input').checked);
        visible.forEach((card) => {
            card.querySelector('input').checked = shouldSelect;
        });
        syncSquadState();
    });
    selectAllToggle?.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        visibleCards().forEach((card) => {
            const input = card.querySelector('input');
            input.checked = !input.checked;
        });
        syncSquadState();
    });
    invertSelectionBtn?.addEventListener('click', () => {
        visibleCards().forEach((card) => {
            const input = card.querySelector('input');
            input.checked = !input.checked;
        });
        syncSquadState();
    });
    syncSquadState();
}

document.querySelectorAll('.edit-bird').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('#birdAction').value = 'update_bird';
        document.querySelector('#birdId').value = button.dataset.id;
        document.querySelector('#birdRing').value = button.dataset.ring;
        document.querySelector('#birdRfid').value = button.dataset.rfid || '';
        document.querySelector('#birdName').value = button.dataset.nama || '';
        document.querySelector('#birdColor').value = button.dataset.warna;
        document.querySelector('#birdGender').value = button.dataset.jk || '';
        document.querySelector('#birdBirthDate').value = button.dataset.lahir || '';
        document.querySelector('#birdBloodline').value = button.dataset.bloodline || '';
        document.querySelector('#birdSire').value = button.dataset.sire || '';
        document.querySelector('#birdDam').value = button.dataset.dam || '';
        document.querySelector('#birdWeight').value = button.dataset.weight || '';
        const statusEl = document.querySelector('#birdStatus');
        if (statusEl) statusEl.value = button.dataset.status || 'aktif';
        const catatanEl = document.querySelector('#birdCatatan');
        if (catatanEl) catatanEl.value = button.dataset.catatan || '';
        bootstrap.Modal.getOrCreateInstance(document.querySelector('#birdModal')).show();
    });
});

const birdModal = document.querySelector('#birdModal');
if (birdModal) {
    birdModal.addEventListener('hidden.bs.modal', () => {
        document.querySelector('#birdAction').value = 'create_bird';
        document.querySelector('#birdId').value = '';
        document.querySelector('#birdRing').value = '';
        document.querySelector('#birdRfid').value = '';
        document.querySelector('#birdName').value = '';
        document.querySelector('#birdColor').value = '';
        document.querySelector('#birdGender').value = '';
        document.querySelector('#birdBirthDate').value = '';
        document.querySelector('#birdBloodline').value = '';
        document.querySelector('#birdSire').value = '';
        document.querySelector('#birdDam').value = '';
        document.querySelector('#birdWeight').value = '';
        document.querySelector('#birdPhoto').value = '';
        document.querySelector('#photoPreview').classList.remove('is-visible');
        document.querySelector('#photoPreview').removeAttribute('src');
        document.querySelector('#uploadStatus').hidden = true;
        document.querySelector('#uploadSavingInfo').textContent = '';
    });
}

const birdPhoto = document.querySelector('#birdPhoto');
if (birdPhoto) {
    let cancelCompression = false;
    const status = document.querySelector('#uploadStatus');
    const statusText = document.querySelector('#uploadStatusText');
    const progressBar = document.querySelector('#uploadProgressBar');
    const savingInfo = document.querySelector('#uploadSavingInfo');
    const cancelBtn = document.querySelector('#cancelCompression');
    const formatBytes = (bytes) => {
        if (!bytes) return '0KB';
        const units = ['B', 'KB', 'MB'];
        let value = bytes;
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }
        return `${value.toFixed(unit === 0 ? 0 : 1)}${units[unit]}`;
    };
    const setProgress = (percent, text) => {
        status.hidden = false;
        statusText.textContent = text;
        progressBar.style.width = `${percent}%`;
    };
    cancelBtn?.addEventListener('click', () => {
        cancelCompression = true;
        birdPhoto.value = '';
        status.hidden = true;
        savingInfo.textContent = '';
        document.querySelector('#photoPreview').classList.remove('is-visible');
    });
    birdPhoto.closest('form')?.addEventListener('submit', () => {
        if (!status.hidden && birdPhoto.files?.length) {
            setProgress(100, 'Mengunggah...');
        }
    });

    birdPhoto.addEventListener('change', () => {
        const preview = document.querySelector('#photoPreview');
        const file = birdPhoto.files?.[0];
        if (!file) {
            preview.classList.remove('is-visible');
            preview.removeAttribute('src');
            return;
        }
        cancelCompression = false;
        savingInfo.textContent = '';
        setProgress(20, file.size < 200 * 1024 ? 'Menyiapkan pratinjau...' : 'Mengompresi... 20%');

        const finish = (outputFile) => {
            if (cancelCompression) return;
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(outputFile);
            birdPhoto.files = dataTransfer.files;
            preview.src = URL.createObjectURL(outputFile);
            preview.classList.add('is-visible');
            setProgress(100, 'Siap diunggah');
            const saved = Math.max(0, file.size - outputFile.size);
            const percent = file.size ? Math.round((saved / file.size) * 100) : 0;
            savingInfo.textContent = `Size sebelum: ${formatBytes(file.size)}, Sesudah: ${formatBytes(outputFile.size)} (Hemat ${percent}%)`;
        };

        if (!window.Compressor || file.size < 160 * 1024) {
            finish(file);
            return;
        }

        setProgress(50, 'Mengompresi... 50%');
        new Compressor(file, {
            maxWidth: 720,
            maxHeight: 720,
            quality: 0.66,
            mimeType: 'image/webp',
            success(result) {
                const compressed = new File([result], file.name.replace(/\.[^.]+$/, '.webp'), {
                    type: 'image/webp',
                    lastModified: Date.now(),
                });
                finish(compressed);
            },
            error() {
                finish(file);
            },
        });
    });
}

document.querySelectorAll('.optimized-image-input').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            alert('File harus berupa gambar JPG, PNG, atau WEBP.');
            input.value = '';
            return;
        }
        if (file.size > 8 * 1024 * 1024) {
            alert('Ukuran file maksimal 8MB sebelum kompresi.');
            input.value = '';
            return;
        }
        if (!window.Compressor || file.size < 160 * 1024) {
            return;
        }

        new Compressor(file, {
            maxWidth: 640,
            maxHeight: 640,
            quality: 0.66,
            mimeType: 'image/webp',
            success(result) {
                const compressed = new File([result], file.name.replace(/\.[^.]+$/, '.webp'), {
                    type: 'image/webp',
                    lastModified: Date.now(),
                });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(compressed);
                input.files = dataTransfer.files;
            },
            error() {
                // Server-side WebP conversion still keeps uploads light.
            },
        });
    });
});

const photoViewer = document.querySelector('#photoViewer');
if (photoViewer) {
    const image = document.querySelector('#photoViewerImage');
    const stage = photoViewer.querySelector('.photo-viewer-stage');
    const title = document.querySelector('#photoViewerTitle');
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let dragStart = null;
    let pinchStart = null;
    const activePointers = new Map();

    const applyZoom = () => {
        image.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
    };

    const resetView = () => {
        zoom = 1;
        panX = 0;
        panY = 0;
        applyZoom();
    };

    document.querySelectorAll('.photo-zoom-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            image.src = button.dataset.photo;
            image.alt = `Foto ${button.dataset.title || 'Merpati'}`;
            title.textContent = button.dataset.title || 'Foto Merpati';
            resetView();
            photoViewer.classList.add('is-open');
            photoViewer.setAttribute('aria-hidden', 'false');
        });
    });

    photoViewer.querySelectorAll('[data-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.zoom;
            if (action === 'in') {
                zoom = Math.min(3, zoom + .25);
            } else if (action === 'out') {
                zoom = Math.max(.5, zoom - .25);
            } else {
                resetView();
                return;
            }
            applyZoom();
        });
    });

    stage.addEventListener('wheel', (event) => {
        event.preventDefault();
        const direction = event.deltaY > 0 ? -.12 : .12;
        zoom = Math.min(4, Math.max(.5, zoom + direction));
        if (zoom <= 1) {
            panX = 0;
            panY = 0;
        }
        applyZoom();
    }, { passive: false });

    stage.addEventListener('dblclick', () => {
        if (zoom > 1) {
            resetView();
            return;
        }
        zoom = 2;
        applyZoom();
    });

    const pointerDistance = () => {
        const points = [...activePointers.values()];
        if (points.length < 2) {
            return 0;
        }
        return Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
    };

    stage.addEventListener('pointerdown', (event) => {
        activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        stage.setPointerCapture(event.pointerId);
        if (activePointers.size === 1) {
            dragStart = { x: event.clientX, y: event.clientY, panX, panY };
        }
        if (activePointers.size === 2) {
            pinchStart = { distance: pointerDistance(), zoom };
        }
    });

    stage.addEventListener('pointermove', (event) => {
        if (!activePointers.has(event.pointerId)) {
            return;
        }
        activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (activePointers.size === 2 && pinchStart) {
            const distance = pointerDistance();
            if (distance > 0) {
                zoom = Math.min(4, Math.max(.5, pinchStart.zoom * (distance / pinchStart.distance)));
                if (zoom <= 1) {
                    panX = 0;
                    panY = 0;
                }
                applyZoom();
            }
            return;
        }

        if (activePointers.size === 1 && dragStart && zoom > 1) {
            panX = dragStart.panX + event.clientX - dragStart.x;
            panY = dragStart.panY + event.clientY - dragStart.y;
            applyZoom();
        }
    });

    const endPointer = (event) => {
        activePointers.delete(event.pointerId);
        dragStart = null;
        pinchStart = null;
        if (activePointers.size === 1) {
            const point = [...activePointers.values()][0];
            dragStart = { x: point.x, y: point.y, panX, panY };
        }
    };

    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);
    stage.addEventListener('pointerleave', endPointer);

    const closeViewer = () => {
        photoViewer.classList.remove('is-open');
        photoViewer.setAttribute('aria-hidden', 'true');
        image.removeAttribute('src');
        activePointers.clear();
        resetView();
    };

    photoViewer.querySelector('[data-close-viewer]')?.addEventListener('click', closeViewer);
    photoViewer.addEventListener('click', (event) => {
        if (event.target === photoViewer) {
            closeViewer();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && photoViewer.classList.contains('is-open')) {
            closeViewer();
        }
    });
}

document.querySelectorAll('.finish-btn').forEach((button) => {
    button.addEventListener('click', async () => {
        const row = button.closest('.race-row');
        const data = new FormData();
        data.append('action', 'checkin');
        data.append('detail_id', row.dataset.detail);
        button.disabled = true;
        button.innerHTML = '<span>Mencatat</span><strong>...</strong>';

        const response = await fetch('index.php', { method: 'POST', body: data });
        const result = await response.json();
        if (!result.ok) {
            alert(result.message || 'Check-in gagal.');
            button.disabled = false;
            button.innerHTML = '<span>Tandai</span><strong>Tiba</strong>';
            return;
        }

        row.classList.add('arrived');
        row.querySelector('.race-info span').textContent = `${result.arrival} / ${result.duration}`;
        row.querySelector('.race-speed').innerHTML = `${Number(result.speed).toLocaleString('id-ID')}<small>MPM / K ${Number(result.koefisien || 0).toLocaleString('id-ID')}</small>`;
        button.innerHTML = '<span>Tandai</span><strong>Tiba</strong>';
        setTimeout(() => window.location.reload(), 900);
    });
});

document.querySelectorAll('.race-clock-btn').forEach((button) => {
    button.addEventListener('click', async () => {
        const ring = button.dataset.ring || 'burung ini';
        const position = await requestAccurateGps(true);
        if (!position) {
            alert('Clocking ditolak. GPS akurat wajib aktif.');
            return;
        }
        const accuracy = Number(position.coords.accuracy || Infinity);
        if (accuracy > gpsConfig.maxAccuracy) {
            alert(`Clocking ditolak. Akurasi GPS ${Math.round(accuracy)} m, maksimal ${gpsConfig.maxAccuracy} m.`);
            return;
        }
        const buttonLoftLat = button.dataset.loftLat !== '' ? Number(button.dataset.loftLat) : NaN;
        const buttonLoftLng = button.dataset.loftLng !== '' ? Number(button.dataset.loftLng) : NaN;
        const loftLat = Number.isFinite(buttonLoftLat) ? buttonLoftLat : gpsConfig.loftLat;
        const loftLng = Number.isFinite(buttonLoftLng) ? buttonLoftLng : gpsConfig.loftLng;
        if (Number.isFinite(loftLat) && Number.isFinite(loftLng)) {
            const distance = gpsDistanceMeter(position.coords.latitude, position.coords.longitude, loftLat, loftLng);
            if (distance > gpsConfig.maxClockDistance) {
                alert(`Clocking ditolak. Posisi kamu ${Math.round(distance)} m dari koordinat kandang, maksimal ${gpsConfig.maxClockDistance} m.`);
                return;
            }
        }
        if (!confirm(`Clock ${ring} sekarang? Sistem akan memakai waktu server dan data tidak bisa diedit manual.`)) {
            return;
        }
        if (!confirm('Konfirmasi kedua: clocking hanya boleh dilakukan saat burung benar-benar masuk loft dan GPS berada maksimal 100 m dari kandang. Lanjutkan?')) {
            return;
        }

        const data = new FormData();
        data.append('action', 'clock_race_manual');
        data.append('registration_id', button.dataset.registration);
        data.append('clocking_lat', position.coords.latitude.toFixed(8));
        data.append('clocking_lng', position.coords.longitude.toFixed(8));
        data.append('gps_accuracy', Math.round(accuracy).toString());
        button.disabled = true;
        button.innerHTML = '<i data-lucide="loader-circle"></i>';
        lucide.createIcons();

        const response = await fetch('index.php', { method: 'POST', body: data });
        const result = await response.json();
        if (!result.ok) {
            alert(result.message || 'Clocking gagal.');
            button.disabled = false;
            button.innerHTML = '<i data-lucide="timer"></i>';
            lucide.createIcons();
            return;
        }
        const gpsText = result.gps_distance_meter !== undefined
            ? ` / GPS ${Math.round(Number(result.gps_distance_meter))} m dari kandang`
            : '';
        alert(`Clocking tercatat ${result.arrival} / ${Number(result.speed).toLocaleString('id-ID')} MPM${gpsText}`);
        window.location.reload();
    });
});

document.querySelectorAll('.server-clock[data-server-time]').forEach((clock) => {
    let current = new Date(clock.dataset.serverTime || Date.now());
    const tick = () => {
        current = new Date(current.getTime() + 1000);
        clock.textContent = `${current.toLocaleTimeString('id-ID', { hour12: false })} WIB`;
    };
    setInterval(tick, 1000);
});

const speedChart = document.querySelector('#speedChart');
if (speedChart && window.Chart) {
    const points = JSON.parse(speedChart.dataset.points || '[]');
    new Chart(speedChart, {
        type: 'line',
        data: {
            labels: points.map((point) => point.label),
            datasets: [{
                label: 'MPM',
                data: points.map((point) => point.value),
                borderColor: '#1A3C5E',
                backgroundColor: 'rgba(26, 60, 94, .14)',
                fill: true,
                tension: .35,
                pointBackgroundColor: '#F4A91C',
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#1A3C5E' } } },
            scales: {
                x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(26, 60, 94, .12)' } },
                y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(26, 60, 94, .12)' } },
            },
        },
    });
}

const publicLeaderboard = document.querySelector('#publicLeaderboard');
if (publicLeaderboard) {
    const refreshPublicLeaderboard = async () => {
        const url = new URL(window.location.href);
        url.searchParams.set('action', 'public_leaderboard');
        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }
        const result = await response.json();
        if (result.html) {
            publicLeaderboard.innerHTML = result.html;
        }
    };
    setInterval(refreshPublicLeaderboard, 15000);
}

const globalLiveRace = document.querySelector('#globalLiveRace');
if (globalLiveRace) {
    const syncGlobalLiveTimers = () => {
        const now = Date.now();
        globalLiveRace.querySelectorAll('.global-live-card').forEach((card) => {
            const start = Date.parse(card.dataset.start);
            const distance = Number(card.dataset.distance || 0);
            if (!Number.isFinite(start) || !Number.isFinite(distance)) return;
            const elapsedSeconds = Math.max(0, Math.floor((now - start) / 1000));
            const slowSeconds = Math.max(1, Math.ceil((distance / 400) * 60));
            const hours = String(Math.floor(elapsedSeconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((elapsedSeconds % 3600) / 60)).padStart(2, '0');
            const seconds = String(elapsedSeconds % 60).padStart(2, '0');
            const timeEl = card.querySelector('[data-flight-time]');
            const progressEl = card.querySelector('[data-flight-progress]');
            if (timeEl) timeEl.textContent = `${hours}:${minutes}:${seconds}`;
            if (progressEl) progressEl.style.width = `${Math.min(100, Math.round((elapsedSeconds / slowSeconds) * 100))}%`;
        });
    };
    const refreshGlobalLive = async () => {
        const url = new URL(window.location.href);
        url.searchParams.set('action', 'global_live');
        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const result = await response.json();
        if (result.html) {
            globalLiveRace.innerHTML = result.html;
            lucide.createIcons();
            syncGlobalLiveTimers();
        }
    };
    syncGlobalLiveTimers();
    setInterval(syncGlobalLiveTimers, 1000);
    setInterval(refreshGlobalLive, 5000);
}
