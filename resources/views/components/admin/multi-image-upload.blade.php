{{--
    Multi-image upload for admin forms (galleries).
    Props: name (field name — posts one path per line via a hidden textarea),
           values (array of current paths), label, folder.
--}}
@props(['name', 'values' => [], 'label' => null, 'folder' => 'media'])

<div x-data="multiImageUpload({ values: {{ json_encode($values ?? []) }}, folder: {{ json_encode($folder) }}, baseUrl: {{ json_encode(asset('')) }} })" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropFile($event)">
    @if ($label)
        <label class="label">{{ $label }}</label>
    @endif

    <textarea name="{{ $name }}" rows="2" class="hidden" x-ref="field">{{ implode("\n", $values ?? []) }}</textarea>

    <div class="flex flex-wrap gap-3">
        <template x-for="(img, index) in images" :key="index">
            <div class="group relative">
                <img :src="img.url" class="h-24 w-32 rounded-xl border border-slate-200 object-cover" alt="gallery image">
                <button type="button" @click="remove(index)"
                        class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white shadow hover:bg-rose-600"
                        title="Remove image">✕</button>
            </div>
        </template>

        <label class="flex h-24 w-32 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed text-center transition"
               :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-slate-300 bg-slate-50/60 hover:border-brand-400'">
            <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span class="px-1 text-[10px] font-semibold text-ink-700">Add images</span>
            <input type="file" accept=".jpg,.jpeg,.png,.webp,.svg,.gif" multiple class="hidden" @change="upload([...$event.target.files]); $event.target.value = ''">
        </label>
    </div>

    <p x-cloak x-show="uploading" class="mt-1 text-xs text-brand-600">Uploading…</p>
    <p x-cloak x-show="error" class="mt-1 text-xs font-semibold text-rose-600" x-text="error"></p>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('multiImageUpload', ({ values, folder, baseUrl }) => ({
            folder,
            baseUrl,
            images: (values || []).map(v => ({ value: v, url: v.startsWith('http') ? v : baseUrl + (v.startsWith('images/') ? v : 'storage/' + v) })),
            dragging: false,
            uploading: false,
            error: '',

            sync() {
                this.$refs.field.value = this.images.map(i => i.value).join('\n');
            },

            async upload(files) {
                if (!files?.length) return;
                this.error = '';
                this.uploading = true;
                try {
                    for (const file of files) {
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
                        this.images.push({ value: json.path, url: json.url });
                    }
                    this.sync();
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.uploading = false;
                }
            },

            dropFile(e) {
                this.dragging = false;
                const files = e.dataTransfer?.files;
                if (files?.length) this.upload([...files]);
            },

            remove(index) {
                this.images.splice(index, 1);
                this.sync();
            },
        }));
    });
</script>
@endpush
@endonce
