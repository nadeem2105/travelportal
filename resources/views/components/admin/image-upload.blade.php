{{--
    Inline image upload for admin forms.
    Props: name (field name), value (current path or URL), label (optional), folder (storage folder).
    Renders a dropzone that uploads via AJAX, stores the storage path in a hidden
    input named `name`, and shows a live preview with a remove button.
--}}
@props(['name', 'value' => null, 'label' => null, 'folder' => 'media'])

<div class="admin-image-upload" x-data="imageUpload({ value: {{ json_encode($value) }}, folder: {{ json_encode($folder) }}, baseUrl: {{ json_encode(asset('')) }} })" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropFile($event)">
    @if ($label)
        <label class="label">{{ $label }}</label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="value">

    {{-- Preview --}}
    <div x-cloak x-show="value" class="group relative w-fit">
        <img :src="preview" class="h-28 w-44 rounded-xl border border-slate-200 object-cover" alt="preview">
        <button type="button" @click="clear()"
                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white shadow hover:bg-rose-600"
                title="Remove image">✕</button>
    </div>

    {{-- Dropzone --}}
    <label x-cloak x-show="!value" class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-5 text-center transition"
           :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-slate-300 bg-slate-50/60 hover:border-brand-400'">
        <svg class="h-6 w-6 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
        <span class="text-xs font-semibold text-ink-700">Click to upload or drag image here</span>
        <span class="text-[10px] text-ink-500">JPG, PNG, WEBP, SVG or GIF · max 4 MB</span>
        <input type="file" accept=".jpg,.jpeg,.png,.webp,.svg,.gif" class="hidden" @change="upload($event.target.files[0])">
    </label>

    <p x-cloak x-show="uploading" class="mt-1 text-xs text-brand-600">Uploading…</p>
    <p x-cloak x-show="error" class="mt-1 text-xs font-semibold text-rose-600" x-text="error"></p>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('imageUpload', ({ value, folder, baseUrl }) => ({
            folder,
            value: value || '',
            preview: '',
            dragging: false,
            uploading: false,
            error: '',

            init() {
                if (this.value) this.preview = this.resolve(this.value);
            },

            // resolve a stored value (full URL, bundled public path, or storage path) to a browser URL
            resolve(v) {
                if (!v) return '';
                if (v.startsWith('http')) return v;
                // uploads live on the public storage disk; bundled assets sit at the site root
                return baseUrl + (v.startsWith('images/') ? v : 'storage/' + v);
            },

            async upload(file) {
                if (!file) return;
                this.error = '';
                this.uploading = true;
                try {
                    const data = new FormData();
                    data.append('file', file);
                    data.append('folder', this.folder);
                    const res = await fetch('{{ route('admin.media.upload') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: data,
                    });
                    const json = await res.json();
                    if (!res.ok) throw new Error(json.message || json.errors?.file?.[0] || 'Upload failed');
                    this.value = json.path;
                    this.preview = json.url;
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.uploading = false;
                }
            },

            dropFile(e) {
                this.dragging = false;
                const file = e.dataTransfer?.files?.[0];
                if (file) this.upload(file);
            },

            clear() {
                this.value = '';
                this.preview = '';
            },
        }));
    });
</script>
@endpush
@endonce
