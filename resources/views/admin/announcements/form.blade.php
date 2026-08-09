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
                <p class="mt-1 text-xs leading-5 text-slate-500">Pilih foto apa pun, lalu atur posisi dan crop ke frame 16:9 sebelum diterapkan. Hasil akhir otomatis 1280×720 px.</p>
            </div>
            <label class="btn-secondary cursor-pointer">
                <span>{{ $announcement->imageUrl() ? 'Ganti gambar' : 'Pilih gambar' }}</span>
                <input class="sr-only" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-announcement-image-input>
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
                    class="aspect-video w-full object-cover"
                    data-announcement-preview
                >
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                <span>Pratinjau frame 16:9. Sudut gambar akan tampil rounded di halaman pengguna.</span>
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

<div class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/80 p-3 backdrop-blur-sm sm:p-6" data-announcement-crop-modal aria-hidden="true">
    <div class="w-full max-w-4xl overflow-hidden rounded-[1.75rem] border border-white/10 bg-white shadow-2xl dark:bg-[#0b1220]">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-white/10 sm:px-6">
            <div>
                <div class="text-xs font-black uppercase tracking-[.16em] text-violet-600 dark:text-violet-300">Atur gambar</div>
                <h2 class="mt-1 text-xl font-black">Crop gambar pengumuman</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Geser gambar di dalam frame dan gunakan zoom. Area yang terlihat inilah yang akan disimpan.</p>
            </div>
            <button type="button" class="btn-secondary !size-10 !p-0 text-xl" data-announcement-crop-cancel aria-label="Tutup">×</button>
        </div>

        <div class="p-4 sm:p-6">
            <div class="relative mx-auto aspect-video w-full max-w-3xl touch-none overflow-hidden rounded-[1.5rem] bg-slate-950 shadow-inner ring-1 ring-white/10" data-announcement-crop-frame>
                <img src="" alt="Gambar yang akan dicrop" class="pointer-events-none absolute max-w-none select-none" draggable="false" data-announcement-crop-image>
                <div class="pointer-events-none absolute inset-0 rounded-[1.5rem] ring-2 ring-inset ring-white/60"></div>
                <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-white/20"></div>
                <div class="pointer-events-none absolute inset-y-0 left-1/2 border-l border-dashed border-white/20"></div>
            </div>

            <div class="mx-auto mt-5 grid max-w-3xl gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[.03] sm:grid-cols-[1fr_auto] sm:items-end">
                <div>
                    <div class="flex items-center justify-between gap-4">
                        <label for="announcement-crop-zoom" class="text-sm font-black">Zoom</label>
                        <span class="text-xs font-bold text-slate-500" data-announcement-crop-zoom-label>100%</span>
                    </div>
                    <input id="announcement-crop-zoom" type="range" min="100" max="300" step="1" value="100" class="mt-3 w-full accent-violet-600" data-announcement-crop-zoom>
                </div>
                <button type="button" class="btn-secondary" data-announcement-crop-reset>Reset posisi</button>
            </div>

            <div class="mx-auto mt-5 flex max-w-3xl flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="btn-secondary" data-announcement-crop-cancel>Batalkan</button>
                <button type="button" class="btn-primary" data-announcement-crop-apply>Gunakan crop 16:9</button>
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
    const recropButton = editor.querySelector('[data-announcement-recrop]');
    const modal = document.querySelector('[data-announcement-crop-modal]');
    const frame = modal?.querySelector('[data-announcement-crop-frame]');
    const cropImage = modal?.querySelector('[data-announcement-crop-image]');
    const zoomInput = modal?.querySelector('[data-announcement-crop-zoom]');
    const zoomLabel = modal?.querySelector('[data-announcement-crop-zoom-label]');
    const resetButton = modal?.querySelector('[data-announcement-crop-reset]');
    const applyButton = modal?.querySelector('[data-announcement-crop-apply]');

    if (!fileInput || !croppedInput || !previewWrap || !preview || !modal || !frame || !cropImage || !zoomInput || !applyButton) return;

    let sourceUrl = '';
    let naturalWidth = 0;
    let naturalHeight = 0;
    let centerX = 0;
    let centerY = 0;
    let zoom = 100;
    let dragging = false;
    let pointerX = 0;
    let pointerY = 0;
    let lastSelectedFile = null;

    const revokeSource = () => {
        if (sourceUrl && sourceUrl.startsWith('blob:')) URL.revokeObjectURL(sourceUrl);
        sourceUrl = '';
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(renderCrop);
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        dragging = false;
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
        renderCrop();
    };

    const loadSource = (url, file = null) => {
        cropImage.onload = () => {
            naturalWidth = cropImage.naturalWidth;
            naturalHeight = cropImage.naturalHeight;
            centerX = naturalWidth / 2;
            centerY = naturalHeight / 2;
            zoom = 100;
            zoomInput.value = '100';
            lastSelectedFile = file;
            openModal();
        };
        cropImage.onerror = () => {
            alert('Gambar tidak dapat dibaca. Gunakan JPG, PNG, atau WebP yang valid.');
        };
        cropImage.src = url;
    };

    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            alert('Gunakan file JPG, PNG, atau WebP.');
            fileInput.value = '';
            return;
        }

        revokeSource();
        sourceUrl = URL.createObjectURL(file);
        loadSource(sourceUrl, file);
    });

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

        applyButton.disabled = true;
        applyButton.textContent = 'Memproses…';

        const canvas = document.createElement('canvas');
        canvas.width = 1280;
        canvas.height = 720;
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

        canvas.toBlob((blob) => {
            if (!blob) {
                applyButton.disabled = false;
                applyButton.textContent = 'Gunakan crop 16:9';
                alert('Gagal memproses gambar. Silakan coba gambar lain.');
                return;
            }

            const previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            previewWrap.classList.remove('hidden');
            if (recropButton) recropButton.hidden = false;

            try {
                if (typeof DataTransfer === 'function') {
                    const croppedFile = new File([blob], `announcement-${Date.now()}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    fileInput.files = transfer.files;
                    croppedInput.value = '';
                } else {
                    croppedInput.value = canvas.toDataURL('image/jpeg', 0.9);
                    fileInput.value = '';
                }
            } catch (_) {
                croppedInput.value = canvas.toDataURL('image/jpeg', 0.9);
                fileInput.value = '';
            }

            closeModal();
            applyButton.disabled = false;
            applyButton.textContent = 'Gunakan crop 16:9';
        }, 'image/jpeg', 0.9);
    });

    recropButton?.addEventListener('click', () => {
        if (sourceUrl) {
            openModal();
            return;
        }
        if (lastSelectedFile) {
            sourceUrl = URL.createObjectURL(lastSelectedFile);
            loadSource(sourceUrl, lastSelectedFile);
            return;
        }
        if (preview.src) {
            sourceUrl = preview.src;
            loadSource(sourceUrl);
            return;
        }
        fileInput.click();
    });

    window.addEventListener('resize', () => {
        if (!modal.classList.contains('hidden')) renderCrop();
    }, { passive: true });

    window.addEventListener('beforeunload', revokeSource, { once: true });
})();
</script>
@endpush
