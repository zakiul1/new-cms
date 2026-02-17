<div class="space-y-3 text-sm">
    <div><strong>ID:</strong> {{ $s->id }}</div>
    <div><strong>Status:</strong> {{ $s->status }}</div>
    <div><strong>Attempts:</strong> {{ $s->attempts }}</div>
    <div><strong>Next Retry:</strong> {{ $s->next_retry_at }}</div>

    <div><strong>Name:</strong> {{ $s->name }}</div>
    <div><strong>Email:</strong> {{ $s->email }}</div>

    {{-- ✅ new --}}
    <div><strong>Country:</strong> {{ $s->country_name ?? 'Unknown' }}</div>
    <div><strong>WhatsApp:</strong> {{ $s->whatsapp ?? 'Not given' }}</div>

    <div><strong>Subject:</strong> {{ $s->subject }}</div>

    <div><strong>IP:</strong> {{ $s->ip }}</div>
    <div class="break-words"><strong>User Agent:</strong> {{ $s->user_agent }}</div>

    <div class="break-words">
        <strong>Message:</strong>
        <div class="mt-1 p-2 border rounded bg-gray-50 whitespace-pre-wrap">{{ $s->message }}</div>
    </div>

    <div class="break-words">
        <strong>Last Error:</strong>
        <div class="mt-1 p-2 border rounded bg-gray-50 whitespace-pre-wrap">{{ $s->last_error }}</div>
    </div>
</div>
