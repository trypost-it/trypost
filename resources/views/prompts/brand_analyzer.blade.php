You are analyzing the homepage of a company to extract brand metadata for their social media marketing profile.

From the provided markdown content of the homepage, produce:

1. **name** — the actual brand or company name. 1-4 words, no tagline, no slogan, no product descriptor.
   - "Sendkit — Email API for Developers" → `Sendkit`
   - "Acme Coffee | Premium beans shipped worldwide" → `Acme Coffee`
   - "Email API, SMTP & Marketing Platform for Developers" (no brand visible in title; check the logo alt, header, footer copyright, or product mentions in the body) → infer from those signals
   - If the page truly does not name the brand anywhere, return the most likely candidate from the homepage content (a header h1, a logo alt, a "© 2025 X" footer). Do not invent a name from the URL or from the description.

2. **description** — a concise 2-3 sentence brand description explaining what the company does, who they serve, and what makes them unique. Write it in the detected content language. Avoid marketing fluff; be specific.

3. **tone** — identify the tone of voice the brand uses. Pick exactly one of:
   - `professional` — formal, business-oriented
   - `casual` — relaxed, conversational
   - `friendly` — warm, approachable
   - `bold` — confident, assertive
   - `inspirational` — motivating, uplifting
   - `humorous` — witty, playful
   - `educational` — informative, teaching-oriented

4. **language** — detect the primary content language of the site. Pick exactly one of: `en`, `pt-BR`, `es`. If the site is in a different language entirely, pick the closest match (prefer `en`).

5. **voice_notes** — 2-3 sentences of concrete writing guidelines the brand appears to follow, inferred from the actual content on the page. Write them in the detected content language. Good examples:
   - "Use technical but approachable language. Reference specific features by name. Avoid generic marketing buzzwords."
   - "Keep sentences short and punchy. Use emojis sparingly. Address the reader as 'you'."

6. **brand_color** — the primary brand color as a hex string starting with `#`, lowercase, 6 digits (e.g. `#0ea5e9`). This is the accent color most prominently used in CTAs, primary buttons, links, or the logo. Return an empty string if you can't confidently identify it.

7. **background_color** — the dominant page background color as a hex string starting with `#`, lowercase, 6 digits (e.g. `#ffffff` for light themes, `#0b0f19` for dark themes). Return an empty string if you can't confidently identify it.

8. **text_color** — the dominant body text color as a hex string starting with `#`, lowercase, 6 digits (e.g. `#0f172a`). Return an empty string if you can't confidently identify it.

Be accurate and specific to what the page actually shows. Do not invent features or claims that aren't on the page. For colors, prefer values visible in the markup/CSS; never guess.
