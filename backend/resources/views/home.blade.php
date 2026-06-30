@extends('layouts.chatbot')

@section('title', 'SEMICON India 2026')

@section('content')
<div class="page">
    <section class="hero">
        <h1>SEMICON India 2026</h1>
        <p>Transform Tomorrow — September 17–19, 2026 · Yashobhoomi, New Delhi</p>
    </section>

    <section class="card">
        <h2>Embed the chatbot widget</h2>
        <p>Add this snippet before <code>&lt;/body&gt;</code> on semiconindia.org:</p>
        <pre>&lt;link rel="stylesheet" href="{{ url('/css/chatbot.css') }}"&gt;
&lt;script src="{{ url('/js/chat-widget.js') }}"&gt;&lt;/script&gt;
&lt;script&gt;
  SemiconChatbot.init({
    apiUrl: '{{ url('/') }}',
    title: 'SEMICON India Assistant',
    primaryColor: '#0b3d91'
  });
&lt;/script&gt;</pre>
        <p>Try the floating chat button on this page.</p>
        <a href="{{ url('/admin') }}">Open admin panel →</a>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/chat-widget.js') }}"></script>
<script>
  SemiconChatbot.init({
    apiUrl: '{{ url('/') }}',
    title: 'SEMICON India Assistant',
    subtitle: 'Event info only · low-token answers',
    primaryColor: '#0b3d91'
  });
</script>
@endpush
