<x-forms::status :label="$instance['status_label']" :color="$instance['status'] === 'completed' ? 'success' : ($instance['status'] === 'cancelled' ? 'secondary' : 'info')" />
@if($instance['outcome_label'])
    <span class="ms-1 text-sm" dir="auto">{{ $instance['outcome_label'] }}</span>
@endif
