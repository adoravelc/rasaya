@extends('layouts.admin')

@section('title', 'Backup & Recovery')

@section('page-header')
    <div>
        <h3 class="mb-1">Backup & Recovery</h3>
        <div class="text-muted">Ekspor data semua fitur ke CSV atau PDF.</div>
    </div>
@endsection

@section('content')
    <div class="alert alert-info">
        - CSV disarankan untuk backup/restore via spreadsheet. PDF memerlukan paket tambahan DomPDF.<br>
        - PDF export maksimal 5000 baris per dataset untuk menjaga performa.
    </div>

    <div class="row g-3">
        @foreach($datasets as $key => $d)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-2">{{ $d['label'] }}</h5>
                        <div class="text-muted small mb-3">Tabel: <code>{{ $d['table'] }}</code></div>
                        <div class="mt-auto d-flex gap-2">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.backup.export', ['dataset' => $key, 'format' => 'csv']) }}">
                                <i class="bi bi-filetype-csv"></i> Download CSV
                            </a>
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.backup.export', ['dataset' => $key, 'format' => 'pdf']) }}">
                                <i class="bi bi-filetype-pdf"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
