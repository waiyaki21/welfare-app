@props([
    'title' => 'Import',
    'importType',
    'previewTabs' => [],
    'uploadRoute',
    'submitRoute' => null,
    'lastUpload' => null,
    'lastUploadRoute' => null,
    'openOnError' => false,
    'dropLabel' => 'Drop your .xlsx file here',
    'accept' => '.xlsx,.xls',
    'importLabel' => 'Import',
])

@php
    $modalId = $importType . '-import-modal';
    $formId = $importType . '-import-form';
    $tabs = collect($previewTabs)->map(fn($tab) => strtolower($tab))->implode(',');
@endphp

<div class="modal-backdrop" id="{{ $modalId }}"
     data-import-modal
     data-import-type="{{ $importType }}"
     data-preview-url="{{ $uploadRoute }}"
     data-final-url="{{ $submitRoute ?? '' }}"
     data-last-upload-url="{{ $lastUploadRoute ?? '' }}"
     data-tabs="{{ $tabs }}"
     data-open-on-error="{{ $openOnError ? '1' : '0' }}">
    <div class="modal" data-role="dialog" style="max-width:580px;transition:max-width .25s ease;">
        <div class="modal-head">
            <div class="modal-title flex items-center gap-2" style="flex-wrap:wrap;">
                <span>{{ $title }}</span>
                <div class="import-header-chips" data-role="header-chips">
                    @if(!empty($lastUpload))
                        <span class="import-chip" data-role="last-upload-chip">
                            Use last upload
                            <button type="button" class="chip-close" data-role="dismiss-last-upload" aria-label="Dismiss last upload">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                            </button>
                        </span>
                    @endif
                    <span class="import-chip import-chip-file" data-role="file-chip" style="display:none;">
                        <span class="chip-text" data-role="file-chip-name"></span>
                        <button type="button" class="chip-close" data-role="file-chip-clear" aria-label="Clear uploaded file">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                        </button>
                    </span>
                </div>
            </div>
            <button class="close-btn" data-role="close-modal" aria-label="Close import modal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ $submitRoute ?? '#' }}" enctype="multipart/form-data" id="{{ $formId }}" data-role="form">
            @csrf
            <div class="modal-body import-preview-shell">
                <div class="import-preview-form" data-role="form-section">
                    @isset($leftExtra)
                        <div class="import-left-extra">
                            {{ $leftExtra }}
                        </div>
                    @endisset

                    @if(!empty($lastUpload))
                        <div class="import-last-upload-card" data-role="last-upload-card">
                            <div class="last-upload-container">
                                <div class="last-upload-info">
                                    <div class="import-last-upload-title">Use last uploaded file?</div>
                                    <div class="import-last-upload-meta">
                                        <div class="meta-row">
                                            <strong>File:</strong> {{ $lastUpload['file_name'] ?? '' }}
                                        </div>
                                        <div class="meta-date">
                                            Uploaded: {{ $lastUpload['uploaded_at'] ?? '' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="last-upload-action">
                                    <label class="custom-switch">
                                        <input type="checkbox" data-role="last-upload-toggle">
                                        <span class="custom-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div data-role="last-upload-status" style="display:none;font-size:.78rem;color:var(--mid);margin-top:8px;padding-top:8px;border-top:1px solid var(--border);"></div>
                        </div>
                    @endif

                    <div class="import-drop-zone" data-role="drop-zone">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--sage)" stroke-width="1.5" style="margin:0 auto 10px;display:block;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <div data-role="drop-label" data-default="{{ $dropLabel }}" style="font-size:.9rem;font-weight:500;color:var(--ink);margin-bottom:4px;">
                            {{ $dropLabel }}
                        </div>
                        <div style="font-size:.8rem;color:var(--mid);">Auto-preview starts after upload</div>
                        <input type="file" data-role="file-input" name="spreadsheet" accept="{{ $accept }}" style="display:none">
                    </div>

                    <div class="import-file-info" data-role="file-info">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--leaf)" stroke-width="2" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="import-file-name" data-role="file-name"></span>
                        <button type="button" class="close-btn" data-role="clear-file" aria-label="Clear import file">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>

                    @isset($inlineNote)
                        {{ $inlineNote }}
                    @endisset
                </div>

                <div class="import-preview-side" data-role="preview-section">
                    <div class="import-preview-placeholder" data-role="preview-placeholder">No sheet uploaded</div>
                    <div data-role="preview-content" style="display:none;">
                        <div class="preview-tabs" data-role="preview-tabs"></div>
                        <div data-role="preview-body"></div>
                    </div>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-role="close-modal">Close</button>
                <button type="submit" class="btn btn-primary import-btn" data-role="import-button" data-label="{{ $importLabel }}" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    {{ $importLabel }}
                </button>
            </div>
        </form>
    </div>
</div>
