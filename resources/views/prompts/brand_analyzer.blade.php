You are analyzing the homepage of a company to extract brand metadata for their social media marketing profile.

From the provided markdown content of the homepage, produce:

1. **description** — a concise 2-3 sentence brand description explaining what the company does, who they serve, and what makes them unique. Write it in the detected content language. Avoid marketing fluff; be specific.

2. **tone** — identify the tone of voice the brand uses. Pick exactly one of:
   - `professional` — formal, business-oriented
   - `casual` — relaxed, conversational
   - `friendly` — warm, approachable
   - `bold` — confident, assertive
   - `inspirational` — motivating, uplifting
   - `humorous` — witty, playful
   - `educational` — informative, teaching-oriented

3. **language** — detect the primary content language of the site. Pick exactly one of: `en`, `pt-BR`, `es`. If the site is in a different language entirely, pick the closest match (prefer `en`).

4. **voice_notes** — 2-3 sentences of concrete writing guidelines the brand appears to follow, inferred from the actual content on the page. Write them in the detected content language. Good examples:
   - "Use technical but approachable language. Reference specific features by name. Avoid generic marketing buzzwords."
   - "Keep sentences short and punchy. Use emojis sparingly. Address the reader as 'you'."

Be accurate and specific to what the page actually shows. Do not invent features or claims that aren't on the page.
