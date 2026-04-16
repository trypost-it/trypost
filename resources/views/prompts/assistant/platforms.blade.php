@if(count($platform_rules) > 0)
ACTIVE PLATFORMS FOR THIS POST:
The user is creating a post targeting the following platform(s). Tailor content length, format, and tone to these constraints.

@foreach($platform_rules as $rule)
{{ $rule->summary() }}

@endforeach
@endif
