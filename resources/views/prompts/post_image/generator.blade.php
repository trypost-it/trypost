@switch($style)
    @case('cinematic')
Cinematic photograph of {{ $scene }}, soft natural lighting, shallow depth of field, color graded with cinematic tones, professional cinematography, magazine editorial quality, 35mm film aesthetic.
        @break
    @case('illustration')
Modern flat vector illustration of {{ $scene }}, soft pastel color palette of muted tones, clean geometric shapes, no gradients, contemporary editorial illustration style, calm and uncluttered composition.
        @break
    @case('isometric_3d')
3D isometric illustration of {{ $scene }}, isometric viewing angle, vibrant but tasteful colors with soft ambient occlusion shadows, clean octane-style rendering, modern SaaS landing page aesthetic, polished and inviting.
        @break
    @case('cartoon')
Friendly cartoon illustration of {{ $scene }}, hand-drawn line art style, bright cheerful palette of soft tones, slightly chunky line weight, Notion or Linear-style illustration, warm and approachable mood.
        @break
    @case('typographic')
Abstract typographic editorial composition inspired by {{ $scene }}, modern Swiss design aesthetic, large decorative letterform shapes used purely as visual elements, gallery-quality print poster, deep contrasting background. The letterforms must be ABSTRACT and DECORATIVE — do NOT spell out any actual word, name, headline, caption, or readable phrase. Do NOT include any sentence, label, watermark, or numerical text.
        @break
    @case('infographic')
Modern flat infographic design illustrating {{ $scene }}, clean dashboard tile style, soft pastel palette, sleek minimal data visualization aesthetic, no people, focus on shapes and graphics.
        @break
    @case('minimalist')
Minimalist still-life photograph of {{ $scene }}, lots of empty negative space, soft diffused window light, monochromatic muted palette, calm zen aesthetic, fine art editorial photography style.
        @break
    @case('mockup')
Clean product mockup photograph of {{ $scene }}, polished commercial product photography lighting, soft long shadow, minimal styling, editorial Apple-style aesthetic, neutral pastel background.
        @break
@endswitch

@if($style !== 'typographic')
Pure visual composition. Do NOT render any headline, caption, label, watermark, sticker, badge, slogan, or floating written text anywhere on the image. Diegetic text that is part of the scene (UI text on a screen, signage seen in the environment) is acceptable; standalone words overlaid on the image are not.
@endif

@if($style === 'infographic')
Charts and bars are fine but do not include axis labels, numbers, percentages, or any written legend.
@endif

@if($style === 'mockup')
If a screen is shown, it should display generic UI shapes and icons only — no readable copy, no headings, no body text.
@endif

Any diegetic text that appears within the scene (text on screens, packaging, signage, speech bubbles, magazine covers, captions inside a comic frame, decorative letterforms) MUST be written in {{ $language_name }}.

@isset($brand_color_name)
Include a small accent of {{ $brand_color_name }} (the brand colour) on a single physical object or graphic element in the scene — a mug, plant pot, jacket, accessory, neon highlight, or UI bar. Keep this accent to roughly 5% of the image; do not flood the scene with this colour or use it as the dominant palette.
@endisset

@isset($brand_context)
Brand context (use only to inform tasteful detail choices in the scene, not to spell anything out): {{ $brand_context }}
@endisset
