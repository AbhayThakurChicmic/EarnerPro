# Earner-Pro — Project Context

Start here. This file covers **the client, the brief, what we found and where things stand**. The technical detail is in [HANDOFF.md](HANDOFF.md): URL and lead-payload contracts, caching, analytics, and backend task specs.

Last updated **29 Oct 2026**.

## Status at a glance

| Area | State |
|---|---|
| Frontend (F1–F6) | **Done**, tested, on branch `feat/frontend-changes` |
| Frontend leftovers | Need inputs from others: endpoint URL, GA4 ID, logo, client decisions. See "Pending" below |
| Backend / server (B1–B7) | **In progress on staging** (https://makintrak.projectlabs.in): tool hosted, menu item and listing buttons live; HTTPS redirect, homepage banner and lead endpoint still to do. Per-task table in HANDOFF §9 |
| Client inputs | Outstanding. Status report with 11 questions sent 29 Oct. See "Needed from the client" |

**Staging is a rebuild, not a copy of the live site.** It runs the Plazart theme with the Advanced Product plugin (listings at `/ap-product/<slug>/`), whereas the audit below describes the live WooCommerce site. Still to confirm: whether staging replaces the live site (HANDOFF §10, question 13).

**Listing-match finding (29 Oct):** only 29 of the staging site's 139 listings name a model that is in the 107-machine database. The rest open on the default R18 PRO. The client has been asked to approve a "pick the closest model" prompt and a closest-model list. Details in HANDOFF §9 B4.

Branches:
- `main`: the client's original V3.1 file, untouched.
- `feat/frontend-changes`: all Stage 1 frontend work, plus these two docs. **Clone this branch to work on the project.**

## Client

**Makin Traks** (heavy equipment dealer, Sunshine Coast, Queensland). Trading entity Mountain Glen Enterprises Pty Ltd.

Contact: **Daz Scale**, 0402 940 490, mkntraks@gmail.com. The brief says "Call, don't wait."

Live site: **https://www.makintraks.com/**. The brief says `makintraks.com.au`, but that domain **does not exist** (checked via DNS). This needs confirming with Daz before hosting starts.

## What the product is

A standalone "machine viability" calculator. Pick a piece of equipment, enter price, hours, undercarriage wear, operating costs and finance, and it tells you whether the machine is a worthwhile earner: net $/week, annual, break-even hours/week. It is a pure cash-flow model with no depreciation.

The client calls V3.1 the **finished front end**. Its features include:
- black/amber design
- Finance/Cash buyer toggle and owner/paid-operator toggle
- per-machine undercarriage data
- region/currency selector
- compare mode
- a native Share for the report card

**Do not restyle, rename fields or change any maths.** That is an explicit client instruction. The footer must read `EARNER-PRO V3.1 · © MAKIN TRAKS 2026 · MOUNTAIN GLEN ENTERPRISES PTY LTD`.

The client supplied:
- the V3.1 HTML, named `earner-pro-v2.html` in the brief; this repo's `index.html`
- `machine-spec-database-v1.json` (107 machines), which we only have as a PDF conversion; see "Data gap"

## The brief: 7 tasks (Developer Build Brief v2, Oct 2026)

Priority order. If the timeline is at risk, **cut from the bottom, never task 3**.

1. **Host** at `<domain>/earner-pro`. HTTPS is mandatory, because the Share button uses the Web Share API. Static hosting, mobile-first; most traffic is phones on 4G.
2. **PWA basics.** Manifest and icon so "Add to Home Screen" installs with an icon and launches full-screen. No native app or app store.
3. **Lead capture (most important).**
   - **Gate:** email gate before the full weekly breakdown unlocks; the verdict plate stays visible.
   - **Fields:** email required, name and mobile optional.
   - **On unlock:** POST the lead plus the full scenario (every input and result, machine, verdict, net/wk, annual, break-even, timestamp, source URL) to an endpoint that (a) emails mkntraks@gmail.com within 1 minute and (b) logs every lead to a Google Sheet or simple DB.
   - **Remember** the unlock per device.
   - **Privacy line:** *"Used by Makin Traks to contact you about your report."*
   - **Exact email format:** see HANDOFF §6.
4. **Pre-load links from listings.** URL params `machine`, `price`, `hours`, `wear`, `year`, `rate` (all optional, sensible fallbacks), plus a "Check this machine's viability" button on **every** listing. One tap from ad to verdict.
5. **Site placement.**
   - **Homepage:** an "Earner-Pro" menu item and a banner above the fold, linking to the blank tool.
   - **Rippa pages:** a button with R18 PRO pre-loaded (see open question 2).
   - **Every listing:** the task-4 button.
   - Top level only, not nested under "Used Machinery". The tool doubles as cross-selling for Makin Traks' own Rippa stock.
6. **Analytics.** Visits, machine selections, report unlocks (leads), share-button taps, source listing. GA4 or anything simple; weekly totals, not dashboards.
7. **Server-side database** (nice-to-have now, required soon). Serve the 107-machine DB from JSON rather than embedding it in the page, so it can be updated without redeploying.

**Out of scope for Stage 1:** user accounts, subscriptions, multi-language, native apps, crowdsourced data, dealer white-label, auto region detection, any restyling. Quote Stage 1 only.

## Audit of the live site (makintraks.com)

- **Platform:** WordPress + WooCommerce on Apache, no CDN in front. A plain sub-directory upload should work once we have FTP/cPanel access.
- **HTTPS isn't enforced.** `http://www.makintraks.com/` serves the page instead of redirecting. This must be fixed for Share and PWA (B1).
- **Nav today:** Home · Used Machinery · Attachments · Consignment · Hire · Hire to Buy · Finance · About Us · Contact Us. There is no "Rippa" section.
- **Listings:**
  - live at `/used-machinery/`, grouped by category
  - each links to `/product/<slug>/`
  - all use **one shared WooCommerce single-product template**, so the listing button is one template edit, not 100+
- **Rippa products:** 4 live, under Used Machinery → Other: R18 PRO ($26,900), RL06 ($26,900; its slug has a typo, "ripper"), RS03 ($9,900), RS06 PRO ($22,900). All are new 2026 stock, so `hours=0` and `wear=0` are accurate for them.
- **Data per listing:**
  - `price` is a structured WooCommerce field, so it's reliable
  - `year` usually leads the title, so it's reasonably reliable
  - `hours` is free text in an inconsistent format, so it's fragile
  - undercarriage `wear` isn't published anywhere; this is a real data gap
- **Analytics:** none today (no GA4, GTM or Facebook pixel). Starting from zero.
- **Favicon/icon:** none to reuse. `favicon.ico` redirects and `apple-touch-icon.png` returns 404.

## Data gap: machine database

The client's `machine-spec-database-v1.json`, which we only have as a PDF conversion, has these problems:
- It is **missing `price` and resale value (`res`) for all 107 machines.**
- At least one field is truncated (the R18 PRO notes).

V3.1's embedded data has all the fields the calculator needs. So `machines.json` in this repo was **extracted verbatim from V3.1** and is complete. If Daz sends an updated real `.json`, merge any changed figures into `machines.json` and keep its keys and fields.

## What has been done (frontend)

All six are on `feat/frontend-changes`, one commit each. Details in HANDOFF §4–§8.

| # | Item | Notes |
|---|---|---|
| F1 | URL pre-fill | `machine`, `price`, `hours`, `wear`, `year`, `rate` (finance %), plus `src` for the source listing. Values are validated; machine names match loosely. |
| F2 | Email gate and lead POST | The lead carries every input and result. The email subject and body are pre-formatted to the brief's exact format, checked against the brief's R18 PRO example (A$102/wk repayment, A$3,956/wk net, A$189,888 annual, 4.7 hrs break-even). The unlock is remembered per device, and leads are queued until sent. |
| F3 | PWA | Manifest, service worker and iOS tags. Chrome reports it installable. Placeholder "EP" icon. |
| F4 | GA4 events | `tool_open`, `machine_select`, `report_unlock`, `share_tap`, `share_sheet`. |
| F5 | DB served from `machines.json` | No machine data left in the page source. Loading and error states. |
| F6 | Speed | Fonts no longer block the first paint. No horizontal scroll at 375 px. |

Not done: **F7**, a one-line fix for a V3.1 toggle bug. It is waiting for client approval; see HANDOFF §10.

## Pending

### Frontend (small, each blocked on an input)
- **`LEAD_ENDPOINT`:** set it in `index.html` once the backend has deployed the Apps Script, then send a test lead.
- **`GA_ID`:** set it in `index.html` once the client provides a GA4 Measurement ID, then check events arrive.
- **Logo:** swap the 3 placeholder icons in `icons/` for the client's logo, and bump `CACHE` in `sw.js`.
- **F7:** apply the toggle bug fix if the client approves.
- **Client decisions:** adjust if the client's answers to the open questions differ from what's built.
- **Optional, not blocked:** make the Share image wait for `document.fonts.ready`. Since F6, a very fast Share tap could draw the image with fallback fonts.
- **Final QA:** on iPhone and Android once the site is live on HTTPS.

### Backend / server (not started; specs in HANDOFF §9)
- **B1:** force HTTPS site-wide.
- **B2:** host at `/earner-pro/`, with the right headers.
- **B3:** lead endpoint (Google Apps Script, Sheet log, email).
- **B4:** listing button in the WooCommerce template.
- **B5:** nav item and homepage banner.
- **B6:** Rippa page buttons.
- **B7:** process for updating `machines.json`.

## Needed from the client

1. Confirmation of the real domain (`.com` vs `.com.au`).
2. Hosting access (FTP or cPanel) and a WordPress admin login.
3. A Google account for the lead Sheet and Apps Script.
4. A logo for the app icon (square PNG, at least 512 px).
5. A GA4 property and Measurement ID.
6. The real `machine-spec-database-v1.json`, only if the V3.1 figures need updating.
7. Testing on their own iPhone and Android phones, including receiving their own test lead.

## Open questions with the client

Each lists what's built now, or our suggestion where nothing is built yet.

1. **Domain:** `.com` or `.com.au`? Built assuming `makintraks.com`.
2. **Rippa buttons:** always pre-load R18 PRO (as the brief says), or each page's own model? Suggested: each page's own model.
3. **Gate scope:** also gate the donut and Whole-of-life cards? Built: only the Weekly numbers card, with Share inside it.
4. **`rate`:** does it mean the finance interest rate? Built: yes.
5. **Cash buyers:** is the email's Finance line OK as "Finance: cash buyer"? Built: yes.
6. **Compare machine:** include it in the lead? Built: in the payload/Sheet, not in the email.
7. **Repeat leads:** send a new lead on later visits after the first unlock? Built: no, first unlock only.
8. **Listing data:** can hours and wear be added as listing fields? Without them, buttons reliably fill only machine, price and year.
9. **Unmatched listings:** for machines outside the 107, the link's other values currently apply to the default R18 PRO, which can mislead. Suggested: open with price and year, and let the user pick the closest model.
10. **F7:** may we fix the toggle bug? Suggested: yes.

## Shared pages

These are claude.ai artifacts and are private: ask Abhay to share them.
- Scope and estimate (38–64 hrs across 7 tasks + QA): https://claude.ai/artifact/2EYKw3jCFF2CfrqJijNFVV
- Change list by team (F/B items, decisions): https://claude.ai/artifact/8V4cLZN8RTvYHG9P6j3sSA
- Client-facing status: https://claude.ai/artifact/DKhaqfJU1yxxJRQKVEjBDS
