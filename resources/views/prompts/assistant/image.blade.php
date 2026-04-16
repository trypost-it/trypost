Generate a social media image. You MUST produce an actual image, not just describe one.

Image requirements:
- Aspect ratio: {{ $aspect_ratio }}
- Eye-catching, professional quality suitable for social media
- Modern design with vibrant colors and clean composition
- If including text on the image, keep it minimal, bold, and highly readable
- ANY text that appears on the image MUST be written in {{ $content_language }}. Do not use any other language for on-image text.
- No watermarks, no borders, no mockup frames
- Do NOT include any offensive, violent, sexual, or inappropriate content
@if($brand_name)

Brand: {{ $brand_name }} — incorporate the brand identity subtly if relevant.
@endif
@if($tone)
Visual style: {{ $tone }}
@endif

What to create: {{ $prompt }}
