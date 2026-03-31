@props([
    'headers' => [],
    'rows' => [],
    'search' => true,
    'pagination' => true,
    'perPage' => 10,
    'perPageOptions' => [10, 25, 50, 100],
    'raw' => [],
])

@php
    $tableId = $attributes->get('id') ?? 'table-' . uniqid();
@endphp

<div {{ $attributes->merge(['class' => 'table-shell']) }}
     id="{{ $tableId }}"
     data-table
     data-search-enabled="{{ $search ? '1' : '0' }}"
     data-pagination-enabled="{{ $pagination ? '1' : '0' }}"
     data-per-page="{{ $perPage }}">
    <div class="table-toolbar">
        @if($search)
            <div class="table-search">
                <input type="text" class="table-input" data-table-search placeholder="Search...">
            </div>
        @endif
        @if($pagination)
            <div class="table-page-size">
                <label class="table-label">Rows per page</label>
                <select class="table-select" data-table-size>
                    @foreach($perPageOptions as $size)
                        <option value="{{ $size }}" @if((int) $perPage === (int) $size) selected @endif>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="table-wrap">
        <table class="app-table">
            <thead>
            <tr>
                @foreach($headers as $header)
                    @php
                        $key = is_array($header) ? ($header['key'] ?? $header['label'] ?? '') : $header;
                        $label = is_array($header) ? ($header['label'] ?? $header['key'] ?? '') : $header;
                        $sortable = is_array($header) ? ($header['sortable'] ?? true) : true;
                    @endphp
                    <th class="table-head-cell {{ $sortable ? 'table-sortable' : '' }}" @if($sortable) data-sort-key="{{ $key }}" @endif>
                        <span>{{ $label }}</span>
                        @if($sortable)
                            <span class="table-sort-indicator" data-sort-indicator></span>
                        @endif
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody data-table-body>
            @forelse($rows as $row)
                @php
                    $rowValues = $row['__values'] ?? $row;
                    $rowPayload = [];
                    foreach ($headers as $header) {
                        $key = is_array($header) ? ($header['key'] ?? $header['label'] ?? '') : $header;
                        $rowPayload[$key] = $rowValues[$key] ?? '';
                    }
                @endphp
                <tr data-table-row data-row-values='@json($rowPayload)'>
                    @foreach($headers as $header)
                        @php $key = is_array($header) ? ($header['key'] ?? $header['label'] ?? '') : $header; @endphp
                        <td data-cell="{{ $key }}">
                            @if(in_array($key, $raw, true))
                                {!! $row[$key] ?? '' !!}
                            @else
                                {{ $row[$key] ?? '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <x-table.empty :colspan="count($headers)" />
            @endforelse
            </tbody>
        </table>
    </div>

    @if($pagination)
        <div class="table-pagination" data-table-pagination>
            <button type="button" class="table-page-btn" data-table-prev>Prev</button>
            <span class="table-page-info" data-table-info>Page 1</span>
            <button type="button" class="table-page-btn" data-table-next>Next</button>
        </div>
    @endif
</div>
