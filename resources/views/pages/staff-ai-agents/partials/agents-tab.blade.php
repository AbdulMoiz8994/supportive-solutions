<div class="grid grid-cols-1 lg:grid-cols-2 gap-3.5">
    @foreach($agents as $agent)
        @include('pages.staff-ai-agents.partials.agent-card', ['agent' => $agent])
    @endforeach
</div>
