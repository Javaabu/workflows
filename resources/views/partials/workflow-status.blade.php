<x-forms::status :label="$workflow['status_label']" :color="$workflow['status'] === 'published' ? 'success' : ($workflow['status'] === 'changed' ? 'warning' : 'secondary')" />
@if($workflow['is_locked'])
    <span class="ms-1 text-xs text-muted-foreground">{{ $strings['locked'] }}</span>
@endif
@if(! $workflow['is_active'])
    <span class="ms-1 text-xs text-muted-foreground">{{ $strings['inactive'] }}</span>
@endif
