Create a social media image.
@if($brandName)
Brand: {{ $brandName }}
@endif
@if($tone)
Style should match a {{ $tone }} tone.
@endif

User request: {{ $prompt }}
