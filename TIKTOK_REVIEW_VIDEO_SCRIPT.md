# TikTok Direct Post API — Review Video Script

Script for re-submitting trypost to TikTok's Direct Post API audit after the initial rejection. Based on https://developers.tiktok.com/doc/content-sharing-guidelines/

Goal: in ~2–3 minutes of screen recording, prove **every** compliance requirement visually. Narration in English (reviewers are in English-speaking teams).

---

## 0. Pre-recording checklist

Before hitting record, set up the state so you can flow through the script without pauses.

### Account / environment
- [ ] A **real TikTok account** connected to trypost (use your personal, not a test stub)
- [ ] Post log in production (`app.trypost.it`) — **not** localhost. Reviewers distrust `.test` domains
- [ ] `npm run build` deployed. Browser cache cleared (hard reload)
- [ ] Dev Tools closed, clean Chrome profile (no extensions bar visible)

### Content to upload
- [ ] One short vertical video (15–30s, 720p+) — original content. **Not** reposted from another platform
- [ ] A caption draft written (avoid preset auto-text — reviewer must see it's editable)
- [ ] Optional: a long video (>max duration of your account) to demonstrate the duration warning

### Workspace state
- [ ] Have a **second TikTok account** also connected — the video should demonstrate multi-account support (each collapse is per-account)
- [ ] Confirm `creator_info` is actually loading (watch Network tab once before recording)

### Recording tool
- Mac: QuickTime > File > New Screen Recording (record cursor + microphone)
- Resolution: 1440p minimum, browser window at 80% scale
- Narration: headset mic, quiet room

---

## 1. Opening shot (0:00–0:10)

**Screen:** trypost dashboard home (`/calendar` or `/posts`).

**Narration:**
> "Hi TikTok team. I'm demonstrating the trypost Direct Post integration. trypost is a social media scheduler for authentic creators. Let me walk you through the posting flow for TikTok."

**Why:** establishes you're not copying from another platform and the app is a scheduler for original content (addresses "Intended Use Requirements" in the doc).

---

## 2. TikTok authorization flow (0:10–0:30)

**Required by the submission form: "User flow of TikTok authorization page".**

**Screen:** navigate to `/accounts` with no TikTok account connected yet.

**Action:**
1. Click "Connect TikTok" button
2. Wait for TikTok's consent screen to load
3. Show the scopes list on TikTok's page
4. Click "Authorize"
5. Return to trypost with the account now connected (avatar + @handle visible)

**Narration:**
> "To use trypost with TikTok, the creator first authorizes our app. Clicking Connect TikTok takes me to TikTok's official consent page, where I can see the exact scopes we request: user profile, basic info, stats, and video publishing permissions. Once I authorize, I'm returned to trypost and my TikTok account is connected."

**What MUST be visible:**
- trypost's Connect TikTok button
- TikTok's native authorization page (not a clone — the real `open-api.tiktok.com/platform/oauth/connect/` screen)
- The scope list on TikTok's page
- Return redirect back to trypost with the account populated

## 3. Open post editor — creator_info fetched (0:30–0:45)

**Screen:** click "Create post" or open an existing draft.

**Action:** just wait 1–2s for the editor to render.

**Narration:**
> "Now I'll open the post editor. When it loads, trypost automatically fetches the latest creator_info for my TikTok account, so all visibility options and interaction settings you'll see reflect my current account state."

**What MUST be visible:**
- The post editor with the TikTok account nickname visible
- Optional: DevTools Network tab briefly showing the `post/publish/creator_info/query/` call

---

## 3. Compose + media upload (0:25–0:40)

**Screen:** post composer.

**Action:**
1. Type a caption manually (e.g. "Testing the TikTok Direct Post integration")
2. Upload the video

**Narration:**
> "I'll write my caption here — notice the caption field is fully editable; trypost does not inject any preset text or hashtags. Now I'll add my video, which I recorded myself."

**What MUST be visible:**
- Caption is user-typed (no auto-fill)
- Media upload visible
- No watermarks or promotional logos added anywhere by trypost

---

## 4. Open the Schedule tab + show creator nickname (0:40–0:55)

**Screen:** right sidebar, click "Schedule" tab.

**Action:** click on the TikTok platform in "Publish to" to select it.

**Narration:**
> "I'll select my TikTok account to publish to. Notice the creator's nickname is displayed here so I know exactly which TikTok account this will post to."

**What MUST be visible:**
- The TikTok platform card with **avatar + nickname visible** (not hidden in tooltip)
- Account visibly selected (primary ring)

---

## 5. Expand TikTok Settings — walk through each compliance field (0:55–1:45)

**Screen:** click to expand "TikTok Settings" collapse.

**Narration (go slowly here — this is the compliance core):**

### 5.1 Creator identity
> "Inside the settings, I see again which account I'm posting to: my nickname and handle."

### 5.2 Privacy level
**Action:** click the "Who can see this video?" dropdown — reveal it's empty with "Select visibility" placeholder.

> "Privacy is required — there is no default value. The user must explicitly choose a visibility. The options listed come directly from TikTok's creator_info API response, so only the visibility levels that my account can actually use are shown."

**Key moment:** hover the dropdown open so the reviewer sees the options list.

### 5.3 Interaction checkboxes
> "Next, the interaction settings — Comments, Duet, and Stitch. None of them are checked by default; I opt in to each."

**Key moment:** if any of them are greyed out on your account (because you disabled them in TikTok app settings), hover over one and show the tooltip *"Disabled by your TikTok account settings"*.

> "If any of these are disabled on my TikTok account itself, trypost respects that — the checkbox is greyed out and I cannot enable it."

### 5.4 Video made with AI
> "There's an independent toggle to declare if the video was made with AI — off by default."

### 5.5 Disclose video content toggle (CRITICAL SECTION)
**Action:** click the "Disclose video content" checkbox.

> "This is the commercial content disclosure toggle. It starts OFF by default, as required. I'll turn it on to demonstrate."

**What happens when ON:** sub-toggles "Your brand" and "Branded content" appear; a reminder text says *"You need to indicate if your content promotes yourself, a third party, or both"*.

**Action:** scroll up to the top of the page — point at the **disabled** Schedule/Post now button.

> "Right now, with the disclosure toggle on but no sub-option selected, the publish button is disabled, matching TikTok's requirement."

**Action:** back to the settings, click "Your brand" sub-toggle.

> "If I select Your brand, TikTok will classify this as Brand Organic. The disclosure reads: 'Your photo or video will be labeled as Promotional content'. Notice the agreement text below: 'By posting, you agree to TikTok's Music Usage Confirmation'."

**Action:** un-select "Your brand", click "Branded content" instead.

> "If I switch to Branded content, the label changes to 'Paid partnership', and the agreement now includes TikTok's Branded Content Policy, as required."

**Action:** open the privacy dropdown again.

> "And because branded content cannot be posted privately, the 'Only me' option is no longer available. If I had already picked 'Only me' before, trypost automatically clears the selection to force me to pick a valid one."

### 5.6 Duration warning (skip if not applicable)
If you set up the long-video scenario: show the red warning *"Video is Xs long but this account can only post videos up to Ys"*.

---

## 6. Preview tab (1:45–2:00)

**Screen:** click the "Preview" tab.

**Narration:**
> "Before posting, I can preview exactly how my post will look. This is the explicit consent step — I only publish after reviewing the content here."

**What MUST be visible:**
- The preview shows the actual video + caption
- Clear that this happens before the upload

---

## 7. Publish + processing notification (2:00–2:20)

**Screen:** back to the composer / schedule view.

**Action:** 
1. Select a valid privacy level (e.g. Public)
2. Make sure disclose is OFF or one sub-toggle is picked
3. Click "Post now"

**Narration:**
> "I'll set privacy to public and publish. After I hit post, trypost shows me a notification saying the content may take a few minutes to process and appear on my TikTok profile — exactly what TikTok's guideline specifies."

**What MUST be visible:**
- Flash banner: *"Post is being published! It may take a few minutes to process and appear on each platform."*
- Button disables / spinner while submitting

---

## 8. Verify on TikTok (2:20–2:40)

**Action:** open tiktok.com in a new tab, navigate to your profile.

**Narration:**
> "And here's the post on my TikTok profile, with the correct visibility, the interaction settings I chose, and — because I had Branded Content disclosed — the 'Paid partnership' label is applied by TikTok automatically."

**What MUST be visible:**
- The exact video just uploaded, live on the TikTok profile
- If branded content was enabled: the "Paid partnership" label

---

## 9. Bonus (optional, strong signal): multi-account support (2:40–3:00)

**Screen:** go back to the post editor (or create a new post).

**Action:** select a **second** TikTok account. Show that a second, independent "TikTok Settings" collapse appears for that account.

**Narration:**
> "trypost supports posting to multiple TikTok accounts simultaneously. Each selected account gets its own settings panel with its own creator_info — so each account's privacy options and interaction rules are respected independently."

**Why this helps:** shows you understand the per-creator nature of the compliance, which matches "creator-specific restrictions" in the doc.

---

## 10. Closing (3:00 end)

**Narration:**
> "That's the trypost Direct Post flow — fully aligned with TikTok's Content Sharing Guidelines. Thanks for reviewing."

**Screen:** end on the TikTok profile showing the freshly-posted video.

---

## Final checklist before uploading the video

Use this as a last gate. **Every** item must be true or the rejection comes back.

### Compliance items demonstrated
- [ ] Creator nickname visible on the upload page
- [ ] Preview of content before publishing
- [ ] Privacy dropdown uses API values (not a static preset)
- [ ] Privacy has no default — user selects
- [ ] Comment / Duet / Stitch all unchecked by default
- [ ] Greyed-out interaction (shown at least once if your account has it)
- [ ] Commercial disclosure toggle default OFF
- [ ] "Your brand" sub-toggle revealed, label "Promotional content"
- [ ] "Branded content" sub-toggle, label "Paid partnership"
- [ ] "Music Usage Confirmation" text + link visible (hover the link)
- [ ] "Branded Content Policy" text + link (when branded content)
- [ ] Publish button disabled while disclose ON + no sub-toggle picked
- [ ] SELF_ONLY (Only me) removed when Branded Content is on
- [ ] Caption/title fully user-editable, no auto-presets
- [ ] Post-publish "may take a few minutes to process" notification
- [ ] Content is original (recorded by you, not reposted)

### What NOT to show
- [ ] No localhost or `.test` domain in the URL bar
- [ ] No dev tools consoles open (unless briefly for the creator_info call)
- [ ] No watermarks/promotional overlays added by trypost
- [ ] No reposted content from other platforms
- [ ] No "Other Apps / tools we compete with" references
- [ ] No preset hashtags or AI-generated caption that the user didn't edit

### Technical
- [ ] Video resolution ≥ 1080p
- [ ] Clear, slow narration in English
- [ ] Cursor visible at each click
- [ ] Total length 2–4 minutes
- [ ] Exported as mp4, < 100MB

---

## Why the last submission was rejected (so you know what changed)

Based on the doc, the previous build likely violated:
1. Privacy level had a default (`SELF_ONLY`)
2. Comments checkbox was pre-checked
3. No "Paid partnership" label when branded content selected
4. No block on SELF_ONLY + branded content
5. Publish button not disabled when disclose ON without sub-toggle
6. No creator_info fetched — interactions & privacy options were static
7. No max-duration check
8. No processing-time notification

All addressed in this build. The video above deliberately demonstrates each of these fixes in order.

---

## Submission notes

When submitting on TikTok's developer portal for the **Direct Post API audit**:
- Paste a direct link to the video (YouTube unlisted, Loom, or upload in the form)
- In the text field, reference this doc: "See attached video demonstrating compliance with Content Sharing Guidelines sections: creator_info fetch, mandatory metadata, commercial content disclosure labels, privacy restrictions for branded content, publish-button gating, processing notification."
- The video intentionally does not include the OAuth/authentication flow, which falls under the Login Kit audit, not Direct Post API.
- If asked about unaudited-client-restrictions: confirm your test accounts are currently private and posts went to SELF_ONLY during development.

---

# 📋 Form Answers (copy / paste)

## Describe your organization's work as it relates to TikTok

trypost is a social media scheduling platform that helps authentic creators, small businesses, and agencies plan, schedule, and publish original content across multiple social networks from a single workspace. Our integration with TikTok enables users to compose and schedule short-form video content for their own TikTok accounts, select per-post settings (privacy level, interaction permissions, commercial disclosure) in full compliance with TikTok's Content Sharing Guidelines, preview content before publishing, and manage multiple TikTok accounts side by side — each account's creator_info (privacy options, interaction rules, max video duration) is respected independently.

We do not repost content from other platforms, inject promotional watermarks, or automate content without explicit user action. Every post is composed, reviewed, and published only upon the user's explicit confirmation. Our role is purely to give creators a better scheduling workflow for content they already own.

## Explain the goal of your application and how Content Posting API integration can be beneficial

trypost helps creators and agencies publish original content consistently across social networks from one workspace, so they can focus on content instead of logging into each platform daily. The Content Posting API is essential because it lets us publish directly to TikTok at scheduled times, fetch creator_info at post time so each account's privacy options, interaction rules and max video duration are respected, and enforce TikTok's Content Sharing Guidelines in the UI — including the commercial disclosure flow, the "Promotional content" / "Paid partnership" labels, and the Music Usage and Branded Content Policy declarations. This benefits TikTok by ensuring uploads go through official endpoints (PULL_FROM_URL, status polling, token refresh), and commercial content is correctly disclosed before it reaches the platform.

## Explain how you determined the daily usage estimate (less than 100)

trypost launched publicly very recently, so our active user base is still small. Our current sign-up volume and TikTok account connection rate put realistic daily publishing on TikTok in the low double digits at most, with clear headroom before approaching 100. We chose this tier because it reflects actual observed usage, not a projection we cannot yet back with data. When real traction pushes us close to the cap, we will reapply with concrete numbers from our analytics to request a higher tier.

## Please list the API response data fields that your API client will save in its database

From /v2/oauth/token/ and /v2/user/info/ (social_accounts table):
- open_id (stored as platform_user_id)
- display_name
- username
- avatar_url (image copied to our storage)
- access_token (encrypted at rest)
- refresh_token (encrypted at rest)
- expires_in (converted to a timestamp)
- granted scopes

From /v2/post/publish/video/init/, /content/init/ and /status/fetch/ (post_platforms table):
- publish_id
- publicaly_available_post_id

Responses from /v2/post/publish/creator_info/query/ and analytics endpoints are fetched on demand and not persisted.
