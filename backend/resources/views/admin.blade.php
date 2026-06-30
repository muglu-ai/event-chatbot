@extends('layouts.chatbot')

@section('title', 'Admin — ' . config('app.name'))

@section('content')
<div class="admin">
    <header>
        <h1>SEMICON Chatbot Admin</h1>
        <a href="{{ url('/') }}">← Demo</a>
    </header>

    <section class="auth card">
        <label for="admin-key">Admin key</label>
        <input id="admin-key" type="password" placeholder="Enter admin key" />
        <button id="load-dashboard" type="button">Load dashboard</button>
        <p id="admin-error" class="error hidden"></p>
    </section>

    <section id="stats-section" class="card hidden">
        <div id="stats-grid" class="stats"></div>
    </section>

    <section id="logs-section" class="card hidden">
        <div class="toolbar">
            <h2>All chat requests</h2>
            <button id="refresh-dashboard" class="secondary" type="button">Refresh</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time</th>
                    <th>Original</th>
                    <th>Improved</th>
                    <th>Answer</th>
                    <th>Tokens</th>
                    <th>Source</th>
                    <th>Provider</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="logs-body"></tbody>
        </table>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}"></script>
@endpush
