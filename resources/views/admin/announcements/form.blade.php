@extends('layouts.app')
@php
    $editing = $announcement->exists;
    $title = $editing ? 'Ubah Pengumuman' : 'Tambah Pengumuman';
    $categories = [
        'info' => 'Informasi',
        'important' => 'Penting',
        'news' => 'Berita',
        'update' => 'Update',
        'deposit' => 'Deposit',
        'service' => 'Layanan',
    ];
    $currentType = old('type', $announcement->type ?: 'info');
    if (! array_key_exists($currentType, $categories)) {
        $currentType = in_array($currentType, ['warning', 'danger'], true) ? 'important' : 'info';
    }
@endphp
@section('content')
<div>
    <a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('admin.announcements.index') }}">← Pengumuman</a>
    <h1 class="mt-2 text-3xl font-black">{{ $editing ? 'Ubah' : 'Tambah' }} pengumuman</h1>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Gunakan kategori agar informasi di dashboard dan halaman pengumuman lebih mudah dipindai.</p>
</div>

<form class="card mt-6 max-w-3xl space-y-5 p-6" method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
    @csrf
    @if($editing) @method('put') @endif

    <div>
        <label class="label">Judul</label>
        <input class="input" name="title" value="{{ old('title', $announcement->title) }}" required>
    </div>

    <div>
        <label class="label">Isi</label>
        <textarea class="input min-h-52" name="body" required>{{ old('body', $announcement->body) }}</textarea>
        <p class="mt-2 text-xs text-slate-500">Baris baru akan dipertahankan saat ditampilkan.</p>
    </div>

    <div data-announcement-image-editor>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <label class="label">Gambar pengumuman</label>
                <p class="mt-1 text-xs leading-5 text-slate-500">Pilih foto apa pun, lalu tentukan format gambar. Tersedia banner, landscape, persegi, portrait, story, dan ukuran custom. Setelah itu geser/zoom gambar sampai pas di frame.</p>
            </div>
            <label class="btn-secondary cursor-pointer">
                <span>{{ $announcement->imageUrl() ? 'Ganti gambar' : 'Pilih gambar' }}</span>
                <input class="sr-only" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/jpg,image/png,image/webp" data-announcement-image-input>
            </label>
        </div>
        <input type="hidden" name="cropped_image" value="" data-announcement-cropped-image>

        @error('image')
            <p class="mt-2 text-sm font-semibold text-rose-600 dark:text-rose-300">{{ $message }}</p>
        @enderror

        <div class="mt-4 {{ $announcement->imageUrl() ? '' : 'hidden' }}" data-announcement-preview-wrap>
            <div class="overflow-hidden rounded-[1.4rem] border border-slate-200 bg-slate-100 shadow-sm dark:border-white/10 dark:bg-white/[.03]">
                <img
                    src="{{ $announcement->imageUrl() ?: '' }}"
                    alt="Pratinjau gambar pengumuman"
                    class="block h-auto max-h-[34rem] w-auto max-w-full object-contain"
                    data-announcement-preview
                >
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                <span data-announcement-preview-meta>Pratinjau gambar. Sudut gambar akan tampil rounded di halaman pengguna.</span>
                <button type="button" class="font-black text-violet-600 dark:text-violet-300" data-announcement-recrop @if(! $announcement->imageUrl()) hidden @endif>Atur crop lagi</button>
            </div>
        </div>

        @if($announcement->imageUrl())
            <label class="mt-3 inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_image" value="1"> Hapus gambar saat ini
            </label>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label class="label">Kategori</label>
            <select class="input" name="type" required>
                @foreach($categories as $type => $label)
                    <option value="{{ $type }}" @selected($currentType === $type)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Mulai tampil</label><input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="label">Berakhir</label><input class="input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}"></div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[.03]">
        <div class="text-xs font-black uppercase tracking-[.16em] text-slate-400">Pratinjau kategori</div>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach(array_keys($categories) as $type)
                <x-announcement-category :value="$type" />
            @endforeach
        </div>
    </div>

    <div class="flex flex-wrap gap-5">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->exists ? $announcement->is_active : true))> Aktif</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned))> Sematkan</label>
    </div>

    <button class="btn-primary">Simpan pengumuman</button>
</form>

<div class="announcement-crop-backdrop" data-announcement-crop-modal aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="announcement-crop-title">
    <div class="announcement-crop-dialog" data-announcement-crop-dialog>
        <div class="announcement-crop-header">
            <div class="min-w-0">
                <div class="text-xs font-black uppercase tracking-[.16em] text-violet-600 dark:text-violet-300">Atur gambar</div>
                <h2 id="announcement-crop-title" class="mt-1 text-lg font-black sm:text-xl">Crop gambar pengumuman</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500 dark:text-slate-400">Pilih rasio/resolusi, geser gambar di dalam frame, gunakan zoom bila perlu, lalu terapkan hasilnya.</p>
            </div>
            <button type="button" class="btn-secondary !size-10 !shrink-0 !p-0 text-xl" data-announcement-crop-cancel aria-label="Tutup">×</button>
        </div>

        <div class="announcement-crop-body">
            <div class="announcement-crop-format">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <div class="text-sm font-black">Format & resolusi</div>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Pilih preset atau gunakan ukuran custom. Rasio frame mengikuti ukuran akhir secara otomatis.</p>
                    </div>
                    <span class="announcement-crop-size-badge" data-announcement-output-label>1280×720 · 16:9</span>
                </div>

                <div class="announcement-crop-presets" role="group" aria-label="Pilihan rasio gambar pengumuman">
                    <button type="button" class="announcement-crop-preset is-active" data-announcement-crop-preset data-width="1280" data-height="720"><strong>Banner</strong><span>16:9 · 1280×720</span></button>
                    <button type="button" class="announcement-crop-preset" data-announcement-crop-preset data-width="1500" data-height="500"><strong>Banner lebar</strong><span>3:1 · 1500×500</span></button>
                    <button type="button" class="announcement-crop-preset" data-announcement-crop-preset data-width="1200" data-height="900"><strong>Landscape</strong><span>4:3 · 1200×900</span></button>
                    <button type="button" class="announcement-crop-preset" data-announcement-crop-preset data-width="1080" data-height="1080"><strong>Persegi</strong><span>1:1 · 1080×1080</span></button>
                    <button type="button" class="announcement-crop-preset" data-announcement-crop-preset data-width="900" data-height="1200"><strong>Portrait</strong><span>3:4 · 900×1200</span></button>
                    <button type="button" class="announcement-crop-preset" data-announcement-crop-preset data-width="1080" data-height="1920"><strong>Story</strong><span>9:16 · 1080×1920</span></button>
                    <button type="button" class="announcement-crop-preset" data-announcement-crop-custom-toggle><strong>Custom</strong><span>Atur sendiri</span></button>
                </div>

                <div class="announcement-crop-custom hidden" data-announcement-crop-custom-panel>
                    <div>
                        <label class="label !mb-1" for="announcement-custom-width">Lebar (px)</label>
                        <input id="announcement-custom-width" class="input !py-2.5" type="number" min="240" max="2400" step="1" value="1280" inputmode="numeric" data-announcement-custom-width>
                    </div>
                    <div>
                        <label class="label !mb-1" for="announcement-custom-height">Tinggi (px)</label>
                        <input id="announcement-custom-height" class="input !py-2.5" type="number" min="240" max="2400" step="1" value="720" inputmode="numeric" data-announcement-custom-height>
                    </div>
                    <button type="button" class="btn-secondary self-end" data-announcement-custom-apply>Terapkan ukuran</button>
                    <p class="col-span-full text-[11px] leading-5 text-slate-500 dark:text-slate-400">Ukuran 240–2400 px per sisi. Rasio custom mengikuti lebar : tinggi yang Anda masukkan, maksimum rasio 4:1 atau 1:4.</p>
                </div>
            </div>

            <div class="announcement-crop-frame" data-announcement-crop-frame>
                <img src="" alt="Gambar yang akan dicrop" class="pointer-events-none absolute max-w-none select-none" draggable="false" data-announcement-crop-image>
                <div class="pointer-events-none absolute inset-0 rounded-[inherit] ring-2 ring-inset ring-white/45"></div>
                <div class="pointer-events-none absolute inset-x-0 top-1/3 border-t border-dashed border-white/20"></div>
                <div class="pointer-events-none absolute inset-x-0 top-2/3 border-t border-dashed border-white/20"></div>
                <div class="pointer-events-none absolute inset-y-0 left-1/3 border-l border-dashed border-white/20"></div>
                <div class="pointer-events-none absolute inset-y-0 left-2/3 border-l border-dashed border-white/20"></div>
            </div>

            <p class="announcement-crop-note">Tips: frame akan menyesuaikan pilihan rasio. Tarik gambar dengan mouse/jari untuk mengatur posisi, lalu gunakan zoom bila diperlukan.</p>

            <div class="announcement-crop-controls">
                <div class="flex items-center justify-between gap-4">
                    <label for="announcement-crop-zoom" class="text-sm font-black">Zoom</label>
                    <span class="rounded-full bg-violet-500/10 px-2.5 py-1 text-xs font-black text-violet-700 dark:text-violet-300" data-announcement-crop-zoom-label>100%</span>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <input id="announcement-crop-zoom" type="range" min="100" max="300" step="1" value="100" class="min-w-0 flex-1 accent-violet-600" data-announcement-crop-zoom>
                    <button type="button" class="btn-secondary !whitespace-nowrap" data-announcement-crop-reset>Reset</button>
                </div>
            </div>

            <div class="announcement-crop-actions">
                <button type="button" class="btn-secondary" data-announcement-crop-cancel>Batalkan</button>
                <button type="button" class="btn-primary" data-announcement-crop-apply>Gunakan gambar 16:9</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const editor = document.querySelector('[data-announcement-image-editor]');
    if (!editor) return;

    const fileInput = editor.querySelector('[data-announcement-image-input]');
    const croppedInput = editor.querySelector('[data-announcement-cropped-image]');
    const previewWrap = editor.querySelector('[data-announcement-preview-wrap]');
    const preview = editor.querySelector('[data-announcement-preview]');
    const previewMeta = editor.querySelector('[data-announcement-preview-meta]');
    const recropButton = editor.querySelector('[data-announcement-recrop]');
    const modal = document.querySelector('[data-announcement-crop-modal]');
    const frame = modal?.querySelector('[data-announcement-crop-frame]');
    const cropImage = modal?.querySelector('[data-announcement-crop-image]');
    const zoomInput = modal?.querySelector('[data-announcement-crop-zoom]');
    const zoomLabel = modal?.querySelector('[data-announcement-crop-zoom-label]');
    const resetButton = modal?.querySelector('[data-announcement-crop-reset]');
    const applyButton = modal?.querySelector('[data-announcement-crop-apply]');
    const outputLabel = modal?.querySelector('[data-announcement-output-label]');
    const presetButtons = [...(modal?.querySelectorAll('[data-announcement-crop-preset]') || [])];
    const customToggle = modal?.querySelector('[data-announcement-crop-custom-toggle]');
    const customPanel = modal?.querySelector('[data-announcement-crop-custom-panel]');
    const customWidthInput = modal?.querySelector('[data-announcement-custom-width]');
    const customHeightInput = modal?.querySelector('[data-announcement-custom-height]');
    const customApply = modal?.querySelector('[data-announcement-custom-apply]');

    if (!fileInput || !croppedInput || !previewWrap || !preview || !modal || !frame || !cropImage || !zoomInput || !applyButton) return;

    let sourceUrl = '';
    let naturalWidth = 0;
    let naturalHeight = 0;
    let centerX = 0;
    let centerY = 0;
    let zoom = 100;
    let outputWidth = 1280;
    let outputHeight = 720;
    let dragging = false;
    let pointerX = 0;
    let pointerY = 0;
    let lastSelectedFile = null;

    // FileReader data URL menghindari masalah CSP blob: di Chrome/Android/WebView.
    const revokeSource = () => {
        if (sourceUrl && sourceUrl.startsWith('blob:')) URL.revokeObjectURL(sourceUrl);
        sourceUrl = '';
    };

    // Portal modal ke body supaya position:fixed tidak terpotong layout/sidebar.
    if (modal.parentElement !== document.body) document.body.appendChild(modal);

    let previousBodyOverflow = '';
    let previouslyFocused = null;

    const gcd = (a, b) => {
        a = Math.abs(Math.round(a));
        b = Math.abs(Math.round(b));
        while (b) [a, b] = [b, a % b];
        return a || 1;
    };

    const ratioLabel = (width = outputWidth, height = outputHeight) => {
        const divisor = gcd(width, height);
        return `${Math.round(width / divisor)}:${Math.round(height / divisor)}`;
    };

    const updateOutputUi = () => {
        const ratio = ratioLabel();
        if (outputLabel) outputLabel.textContent = `${outputWidth}×${outputHeight} · ${ratio}`;
        applyButton.textContent = `Gunakan gambar ${ratio}`;
        if (customWidthInput) customWidthInput.value = String(outputWidth);
        if (customHeightInput) customHeightInput.value = String(outputHeight);
    };

    const fitFrameToViewport = () => {
        const body = modal.querySelector('.announcement-crop-body');
        if (!body) return;
        const bodyWidth = Math.max(240, body.clientWidth - 8);
        const maxWidth = Math.min(bodyWidth, 768);
        const maxHeight = Math.min(window.innerHeight * (window.innerWidth <= 640 ? .40 : .50), 520);
        const ratio = outputWidth / outputHeight;

        let width = maxWidth;
        let height = width / ratio;
        if (height > maxHeight) {
            height = maxHeight;
            width = height * ratio;
        }
        if (width > maxWidth) {
            width = maxWidth;
            height = width / ratio;
        }

        frame.style.width = `${Math.max(96, Math.round(width))}px`;
        frame.style.height = `${Math.max(96, Math.round(height))}px`;
        frame.style.aspectRatio = `${outputWidth} / ${outputHeight}`;
    };

    const openModal = () => {
        previouslyFocused = document.activeElement;
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => {
            fitFrameToViewport();
            renderCrop();
            modal.querySelector('[data-announcement-crop-cancel]')?.focus({ preventScroll: true });
        });
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousBodyOverflow;
        dragging = false;
        if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus({ preventScroll: true });
    };

    const cropMetrics = () => {
        const rect = frame.getBoundingClientRect();
        if (!rect.width || !rect.height || !naturalWidth || !naturalHeight) return null;

        const baseScale = Math.max(rect.width / naturalWidth, rect.height / naturalHeight);
        const scale = baseScale * (zoom / 100);
        const sourceWidth = rect.width / scale;
        const sourceHeight = rect.height / scale;

        centerX = Math.min(naturalWidth - sourceWidth / 2, Math.max(sourceWidth / 2, centerX));
        centerY = Math.min(naturalHeight - sourceHeight / 2, Math.max(sourceHeight / 2, centerY));

        return {
            rect,
            scale,
            sourceWidth,
            sourceHeight,
            sourceX: centerX - sourceWidth / 2,
            sourceY: centerY - sourceHeight / 2,
        };
    };

    const renderCrop = () => {
        const metrics = cropMetrics();
        if (!metrics) return;

        cropImage.style.width = `${naturalWidth * metrics.scale}px`;
        cropImage.style.height = `${naturalHeight * metrics.scale}px`;
        cropImage.style.left = `${-metrics.sourceX * metrics.scale}px`;
        cropImage.style.top = `${-metrics.sourceY * metrics.scale}px`;
        zoomLabel.textContent = `${Math.round(zoom)}%`;
    };

    const resetCrop = () => {
        zoom = 100;
        zoomInput.value = '100';
        centerX = naturalWidth / 2;
        centerY = naturalHeight / 2;
        fitFrameToViewport();
        renderCrop();
    };

    const setOutputSize = (width, height, custom = false) => {
        width = Math.round(Number(width));
        height = Math.round(Number(height));
        const ratio = width / height;

        if (!Number.isFinite(width) || !Number.isFinite(height) || width < 240 || height < 240 || width > 2400 || height > 2400) {
            alert('Lebar dan tinggi harus antara 240 sampai 2400 px.');
            return false;
        }
        if (ratio < .25 || ratio > 4) {
            alert('Rasio custom maksimum 4:1 atau 1:4 agar gambar tetap nyaman ditampilkan.');
            return false;
        }
        if (width * height > 6_000_000) {
            alert('Resolusi terlalu besar. Maksimum sekitar 6 megapiksel.');
            return false;
        }

        outputWidth = width;
        outputHeight = height;
        presetButtons.forEach((button) => {
            const active = Number(button.dataset.width) === width && Number(button.dataset.height) === height;
            button.classList.toggle('is-active', active && !custom);
        });
        customToggle?.classList.toggle('is-active', custom);
        customPanel?.classList.toggle('hidden', !custom);
        updateOutputUi();
        resetCrop();
        return true;
    };

    const loadSource = (url, file = null) => {
        cropImage.onload = () => {
            cropImage.onload = null;
            cropImage.onerror = null;
            naturalWidth = cropImage.naturalWidth;
            naturalHeight = cropImage.naturalHeight;

            if (!naturalWidth || !naturalHeight) {
                alert('Ukuran gambar tidak dapat dibaca. Silakan pilih JPG, PNG, atau WebP lain.');
                return;
            }

            centerX = naturalWidth / 2;
            centerY = naturalHeight / 2;
            zoom = 100;
            zoomInput.value = '100';
            lastSelectedFile = file;
            updateOutputUi();
            openModal();
        };
        cropImage.onerror = () => {
            cropImage.onload = null;
            cropImage.onerror = null;
            alert('Browser gagal membuka gambar tersebut. Pastikan file benar-benar JPG, PNG, atau WebP (bukan HEIC/AVIF yang hanya berganti nama).');
        };
        cropImage.src = url;
    };

    const readFileAsDataUrl = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => typeof reader.result === 'string' ? resolve(reader.result) : reject(new Error('empty-result'));
        reader.onerror = () => reject(reader.error || new Error('file-reader-error'));
        reader.readAsDataURL(file);
    });

    const supportedFile = (file) => {
        const mime = String(file.type || '').toLowerCase();
        if (/^image\/(jpeg|jpg|png|webp)$/.test(mime)) return true;
        return /\.(jpe?g|png|webp)$/i.test(String(file.name || ''));
    };

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (!supportedFile(file)) {
            alert('Gunakan file JPG, PNG, atau WebP.');
            fileInput.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran gambar maksimum 5 MB.');
            fileInput.value = '';
            return;
        }

        try {
            revokeSource();
            sourceUrl = await readFileAsDataUrl(file);
            loadSource(sourceUrl, file);
        } catch (_) {
            alert('File gambar tidak dapat dibaca oleh browser. Coba pilih ulang atau simpan ulang sebagai JPG/PNG/WebP.');
            fileInput.value = '';
        }
    });

    presetButtons.forEach((button) => {
        button.addEventListener('click', () => setOutputSize(button.dataset.width, button.dataset.height, false));
    });

    customToggle?.addEventListener('click', () => {
        customPanel?.classList.remove('hidden');
        presetButtons.forEach((button) => button.classList.remove('is-active'));
        customToggle.classList.add('is-active');
        customWidthInput?.focus();
    });

    customApply?.addEventListener('click', () => setOutputSize(customWidthInput?.value, customHeightInput?.value, true));
    [customWidthInput, customHeightInput].forEach((input) => input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            setOutputSize(customWidthInput?.value, customHeightInput?.value, true);
        }
    }));

    zoomInput.addEventListener('input', () => {
        zoom = Number(zoomInput.value || 100);
        renderCrop();
    });

    resetButton?.addEventListener('click', resetCrop);

    frame.addEventListener('pointerdown', (event) => {
        if (!naturalWidth) return;
        dragging = true;
        pointerX = event.clientX;
        pointerY = event.clientY;
        frame.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });

    frame.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        const metrics = cropMetrics();
        if (!metrics) return;
        const dx = event.clientX - pointerX;
        const dy = event.clientY - pointerY;
        pointerX = event.clientX;
        pointerY = event.clientY;
        centerX -= dx / metrics.scale;
        centerY -= dy / metrics.scale;
        renderCrop();
        event.preventDefault();
    });

    const stopDragging = () => { dragging = false; };
    frame.addEventListener('pointerup', stopDragging);
    frame.addEventListener('pointercancel', stopDragging);

    modal.querySelectorAll('[data-announcement-crop-cancel]').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal();
            fileInput.value = '';
            lastSelectedFile = null;
        });
    });

    applyButton.addEventListener('click', () => {
        const metrics = cropMetrics();
        if (!metrics) return;

        const normalLabel = `Gunakan gambar ${ratioLabel()}`;
        applyButton.disabled = true;
        applyButton.textContent = 'Memproses…';

        const canvas = document.createElement('canvas');
        canvas.width = outputWidth;
        canvas.height = outputHeight;
        const context = canvas.getContext('2d', { alpha: false });
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(
            cropImage,
            metrics.sourceX,
            metrics.sourceY,
            metrics.sourceWidth,
            metrics.sourceHeight,
            0,
            0,
            canvas.width,
            canvas.height
        );

        const previewDataUrl = canvas.toDataURL('image/jpeg', 0.9);

        canvas.toBlob((blob) => {
            if (!blob) {
                applyButton.disabled = false;
                applyButton.textContent = normalLabel;
                alert('Gagal memproses gambar. Silakan coba gambar lain.');
                return;
            }

            preview.src = previewDataUrl;
            previewWrap.classList.remove('hidden');
            if (previewMeta) previewMeta.textContent = `Hasil ${outputWidth}×${outputHeight} (${ratioLabel()}). Sudut gambar akan tampil rounded.`;
            if (recropButton) recropButton.hidden = false;

            try {
                if (typeof DataTransfer === 'function') {
                    const croppedFile = new File([blob], `announcement-${outputWidth}x${outputHeight}-${Date.now()}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    fileInput.files = transfer.files;
                    croppedInput.value = '';
                } else {
                    croppedInput.value = previewDataUrl;
                    fileInput.value = '';
                }
            } catch (_) {
                croppedInput.value = previewDataUrl;
                fileInput.value = '';
            }

            closeModal();
            applyButton.disabled = false;
            applyButton.textContent = normalLabel;
        }, 'image/jpeg', 0.9);
    });

    recropButton?.addEventListener('click', async () => {
        if (sourceUrl) {
            loadSource(sourceUrl, lastSelectedFile);
            return;
        }
        if (lastSelectedFile) {
            try {
                sourceUrl = await readFileAsDataUrl(lastSelectedFile);
                loadSource(sourceUrl, lastSelectedFile);
            } catch (_) {
                fileInput.click();
            }
            return;
        }
        if (preview.src) {
            sourceUrl = preview.src;
            loadSource(sourceUrl);
            return;
        }
        fileInput.click();
    });

    if (preview?.src) {
        preview.addEventListener('load', () => {
            if (!previewMeta || !preview.naturalWidth || !preview.naturalHeight) return;
            previewMeta.textContent = `Pratinjau ${preview.naturalWidth}×${preview.naturalHeight} (${ratioLabel(preview.naturalWidth, preview.naturalHeight)}). Sudut gambar tampil rounded.`;
        });
    }

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    window.addEventListener('resize', () => {
        if (modal.classList.contains('is-open')) {
            fitFrameToViewport();
            renderCrop();
        }
    }, { passive: true });

    updateOutputUi();
    window.addEventListener('beforeunload', revokeSource, { once: true });
})();
</script>
@endpush
