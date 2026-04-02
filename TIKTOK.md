# TikTok Direct Post Audit

## Status

Audit application submitted on 2026-04-01. Waiting for TikTok review (2-4 weeks).

## Form Responses

### Step 1 — General Information

**Relationship with TikTok:**
Social media management platform allowing customers to create, schedule, publish, and manage content in one place, including TikTok.

### Step 2 — API Client Information

**App ID:** 7473784331115481143

**Goal of the application:**
TryPost is a social media management platform (trypost.it) that allows users to create, schedule, and publish content across multiple social networks from a single dashboard. The Content Posting API integration enables our users to schedule and directly publish video content to their TikTok accounts alongside other platforms like Instagram, YouTube, Facebook, and LinkedIn — all from one place. This saves creators and businesses significant time by eliminating the need to manually post on each platform individually. Direct posting is essential for our scheduled publishing feature, where posts are automatically published at the optimal time chosen by the user without requiring manual intervention.

**Daily users estimate:** Prefer not to say / Building a new application

**Justification (if asked):**
We are an early-stage platform currently onboarding our first users. We expect gradual growth as we expand our user base.

### Step 3 — Supporting Documents

**Required:**
- PDF with screenshots showing the TikTok integration flow (create post → select TikTok → schedule → publish)
- Screen recording demonstrating the complete user workflow: account connection, post creation, and publishing

**API fields stored:**
- `publish_id` — used to track publish status
- `publicly_available_post_id` — used to build the post URL on TikTok

### Step 4 — Review

Verify all information and submit.

## Without Audit (Current Restrictions)

- All posts are restricted to `SELF_ONLY` (private) visibility
- Maximum 5 users can post in a 24-hour window
- User accounts must be set to private at the time of posting

## After Audit Approval

- Posts can be published with `PUBLIC_TO_EVERYONE` visibility
- No user cap restrictions
- Full Direct Post API access

## References

- [TikTok Content Sharing Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines)
- [TikTok Direct Post API](https://developers.tiktok.com/doc/content-posting-api-reference-direct-post)
- [TikTok Direct Post Audit Guide (Mixpost)](https://docs.mixpost.app/services/social/tik-tok/direct-post-audit/)
