@foreach($approvals as $card)
    @include('pages.workflow-queues.partials.approval-card', ['card' => $card])
@endforeach
