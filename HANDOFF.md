# Earner-Pro — Developer Handoff

For the backend / server team picking up Stage 1. It covers what the tool is, what the frontend now does, and exactly what is left to build on the server side.

**Read [PROJECT-CONTEXT.md](PROJECT-CONTEXT.md) first.** It covers the client, the full brief, the live-site audit, the data gap and overall status. This file is the technical companion.

Status as of **29 Oct 2026**. All frontend work is on branch `feat/frontend-changes` (clone this one); `main` holds the client's original V3.1 file.

**Staging:** the tool is deployed at **https://makintrak.projectlabs.in/earner-pro/**. That staging site is a rebuild of the client's site on the Plazart theme + Advanced Product plugin (listings at `/ap-product/<slug>/`). It is not the WooCommerce setup described in the live-site audit. Everything in §9 marked "staging" refers to it. The client status report (29 Oct) is a shared claude.ai doc: ask Abhay.

```
git clone -b feat/frontend-changes https://github.com/AbhayThakurChicmic/EarnerPro.git
```

---

## 1. What this is

**Earner-Pro** is a machine-viability calculator for **Makin Traks** (heavy equipment dealer, Queensland, Australia). A buyer picks a machine, enters price, hours, undercarriage wear, operating costs and finance, and gets a verdict: net profit per week, annual, break-even hours per week. It is a pure cash-flow model (no depreciation).

- It is **one static page plus a JSON file**. No build step, no framework, no server code of its own.
- The design, field names and maths are **final at V3.1** and must not change (client instruction). The footer must read `EARNER-PRO V3.1`.
- Stage 1 goal from the client brief: **live, linked, leads.** Lead capture is the most important task and is never cut.

Client contact: Daz Scale, Makin Traks — mkntraks@gmail.com.

## 2. Files

| File | What it is |
|---|---|
| `index.html` | The whole tool: markup, CSS and JS inline. |
| `machines.json` | The 107-machine database, loaded at runtime. Keys are machine names. |
| `manifest.webmanifest` | PWA manifest (name, icons, standalone display). |
| `sw.js` | Service worker (offline/fast reopen). |
| `icons/` | `icon-192.png`, `icon-512.png`, `apple-touch-icon.png`. **Placeholders** until the client supplies a logo. |
| `.htaccess` | Cache headers, manifest type, gzip, no folder listing; 404s repo-only files. Uploaded with the tool. If the server answers 500 after upload, delete it: the tool works without it. |
| `wordpress/earner-pro-listing-button.php` | The listing-button code installed on staging as a WPCode snippet (§9 B4). **Not uploaded with the tool.** |
| `PROJECT-CONTEXT.md`, `HANDOFF.md` | Project docs. **Don't upload these to the web server.** |

**To deploy the tool:** upload `index.html`, `machines.json`, `manifest.webmanifest`, `sw.js`, `.htaccess` and `icons/` into `<site root>/earner-pro/`. Nothing else.

All paths inside the page are **relative**, so the folder can be served from any sub-path.

## 3. Run it locally

```
python -m http.server 8000      # from the repo root
```
Open `http://localhost:8000/`. It must be served over HTTP. Opening `index.html` from disk won't load `machines.json`. VS Code Live Server works too.

Pre-fill example: `http://localhost:8000/?machine=Caterpillar%20D6T%20XL&price=226000&hours=6200&wear=45&year=2019`

To reset the email gate, clear the site's local storage in DevTools (`ep_unlocked_v1`).

## 4. What the frontend does now

| # | Feature | Notes |
|---|---|---|
| F1 | **URL pre-fill** | See §5. |
| F2 | **Lead gate** | The verdict plate stays visible. The "Weekly numbers" card (including the Share button) is behind an email form: email required, name and mobile optional, privacy line *"Used by Makin Traks to contact you about your report."* On submit it POSTs the lead (§6). The unlock is remembered per device in `localStorage`. |
| F3 | **PWA** | Manifest, iOS home-screen tags and a service worker. The service worker only registers on HTTPS (or localhost). |
| F4 | **Analytics** | GA4 events (§8). Off until `GA_ID` is set. |
| F5 | **External DB** | The machines are fetched from `machines.json` (revalidated on every load), so the data is no longer in the page source. The page shows a loading state, and an error state if the fetch fails. |
| F6 | **Speed** | Google Fonts load without blocking the first paint. Layout checked at 375 px. |

### Two values to set in `index.html` (search for them)

```js
const LEAD_ENDPOINT="";   // Apps Script web-app URL (…/exec)
const GA_ID="";           // GA4 Measurement ID, "G-XXXXXXX"
```
While `LEAD_ENDPOINT` is empty, unlocking still works, but the lead is only logged to the browser console and is **not sent**.

## 5. Pre-load URL contract (for the listing buttons)

```
/earner-pro/?machine=<name>&price=<n>&hours=<n>&wear=<0-100>&year=<yyyy>&rate=<n>&src=<path>
```

| Param | Meaning | Accepted | If missing or invalid |
|---|---|---|---|
| `machine` | Machine name, matched against `machines.json` keys | Exact match, or the same ignoring case and punctuation (`caterpillar-d6t-xl` works) | Default machine (Rippa R18 PRO) |
| `price` | Purchase price | 1 – 100,000,000. Commas and `$` are stripped | That machine's guide price |
| `hours` | Machine hours | 0 – 200,000 | 0 |
| `wear` | Undercarriage wear % | 0 – 100 | 0 |
| `year` | Model year | 1950 – next year | 2026 |
| `rate` | **Finance interest rate**, % p.a. (not the hire rate; still to be confirmed by the client) | 0 – 30 | 8.5 |
| `src` | Where the visitor came from, e.g. `/product/rippa-r18-pro/`. Used in the lead and analytics | Any string (max 300 chars) | Same-site referrer path, else `direct` |

- **URL-encode every value** (`rawurlencode` in PHP, `encodeURIComponent` in JS).
- **Always link to the folder with the trailing slash** (`/earner-pro/`). Without it, relative paths break unless Apache redirects first.
- **Machine name gotcha:** if `machine` doesn't match any key, the other values are still applied to the default R18 PRO, which can give a misleading verdict. Only send `machine` when the listing maps to a real key (see §9, B4). What should happen for unmatched listings is still open with the client.

## 6. Lead payload contract (for the endpoint)

On unlock the page sends **one POST per lead**:

- `Content-Type: text/plain;charset=utf-8`, `mode: "no-cors"`, body = JSON string.
- We use text/plain + no-cors so the browser sends **no CORS preflight**, which Apps Script can't answer.
- Because of `no-cors`, the **page cannot read the response**. Failures are invisible to the page, so the endpoint should log its own errors.
- If the network call itself fails, the lead stays queued in `localStorage` (`ep_lead_queue_v1`) and is retried on the next page load.

```jsonc
{
  "type": "earner-pro-lead",
  "app_version": "V3.1",
  "submitted_at": "2026-10-18T04:14:00.000Z",     // ISO, UTC
  "submitted_at_local": "18 Oct 2026 2:14pm",     // Australia/Brisbane
  "lead": { "email": "john@example.com", "name": "John Smith", "mobile": "0412 345 678" },  // name/mobile may be ""
  "source": "/product/rippa-r18-pro/",
  "page_url": "https://www.makintraks.com/earner-pro/?machine=…",
  "prefill": { "machine_requested": "Rippa R18 PRO", "matched": true },
  "inputs": {
    "machine": "Rippa R18 PRO", "category": "Mini excavator", "year": 2026, "hours": 0, "price": 26990,
    "uc_wear_pct": 0, "region": "AU", "currency": "A$", "fuel_unit": "L", "fuel_price": 1.9,
    "fuel_burn_per_hr": 4, "charge_rate": 135, "week_hours": 40, "utilisation_pct": 85,
    "operator_mode": "owner", "operator_rate": 0, "servicing_rate_base": 5,
    "finance": true, "deposit_pct": 20, "interest_rate_pct": 8.5, "term_yrs": 5   // null when cash buyer
  },
  "results": {
    "verdict": "STRONG EARNER", "net_week": 3955.99, "annual": 189887.68, "break_even_hrs": 4.7,
    "payback_months": 0.32, "margin_pct": 86.19, "revenue_week": 4590, "billable_hrs_week": 34,
    "fuel_week": 304, "operator_week": 0, "servicing_week": 200, "age_factor": 1, "undercarriage_week": 28,
    "uc_hours_remaining": 1000, "operating_profit_week": 4058, "amount_financed": 21592,
    "repayment_week": 102.01, "cost_per_hr": 15.85, "profit_per_hr": 98.9,
    "lifetime_profit": 395599.34, "years_to_life": 2.08, "life_hrs": 4000
  },
  "compare": null,   // or { machine, price, net_week, break_even_hrs, repayment_week } when "compare" is on
  "email": {
    "to": "mkntraks@gmail.com",
    "subject": "NEW EARNER-PRO LEAD - Rippa R18 PRO",
    "body": "18 Oct 2026 2:14pm | john@example.com | 0412 345 678 | John Smith\nMachine: Rippa R18 PRO | price A$26,990 | 0 hrs | wear 0%\nInputs: A$135/hr | 40hr wk @ 85% | diesel A$1.90/L | owner-operator\nFinance: 20% deposit | 8.5% p.a. | 5 yrs | repayment A$102/wk\nRESULT: STRONG EARNER | Net A$3,956/wk | annual A$189,888 | break-even 4.7 hrs\nSource: /product/rippa-r18-pro/"
  }
}
```

`email.subject` and `email.body` are **already formatted exactly as the client's brief requires**, using the same number formatting the tool displays. The endpoint can send them as-is. Cash buyers get `Finance: cash buyer`. Verdicts are `STRONG EARNER`, `VIABLE — TIGHT MARGIN` or `NOT VIABLE AT THESE SETTINGS`.

The example above is the client's own test case: with the default inputs, the numbers must match the brief exactly (net A$3,956/wk, annual A$189,888, break-even 4.7 hrs).

## 7. Service worker and caching

- **Page and `machines.json`:** network-first, falling back to cache when offline. Updates are seen on the next load.
- **Icons, manifest and Google Fonts:** cache-first.
- **Not intercepted:** POSTs, analytics and the lead endpoint.
- **When a cached file changes** (icons, manifest), bump `const CACHE = "earner-pro-v1"` in `sw.js`.

## 8. Analytics events (GA4)

| Event | When | Params |
|---|---|---|
| `tool_open` | Every page load (a visit) | `source_listing`, `machine`, `prefilled`, `returning_lead` |
| `machine_select` | User changes the machine (or the compare machine) | `machine`, `source_listing`, `compare` |
| `report_unlock` | Email gate submitted (a lead) | `machine`, `verdict`, `source_listing` |
| `share_tap` | "Share / save my report" tapped | `machine`, `source_listing` |
| `share_sheet` | The native Share button in the report overlay tapped | `machine` |

To see the params in reports, register `source_listing`, `machine` and `verdict` as **event-scoped custom dimensions** in GA4 Admin. Mark `report_unlock` as a key event. The client wants weekly totals, not dashboards.

### Frontend: still pending (each blocked on an input)

| Item | Blocked on | Work when it arrives |
|---|---|---|
| Lead sending | B3 endpoint URL | Set `LEAD_ENDPOINT`, then send a test lead |
| Analytics | GA4 Measurement ID (client) | Set `GA_ID`, then check events in GA4 DebugView |
| App icon | Client logo | Replace the 3 PNGs in `icons/` (192, 512, 180 px; keep the important content in the middle 80% for maskable) and bump `CACHE` in `sw.js` |
| F7 toggle bug | Client approval | One-line fix (§10) |
| Client decisions (§10) | Client answers | Small tweaks, only if the answers differ from what's built |
| Final QA | Site live on HTTPS | Share with the image and Add to Home Screen, on iPhone and Android |
| *Optional:* Share image fonts | Nothing | Wait for `document.fonts.ready` before drawing the share canvas |

---

## 9. Backend / server tasks

| # | Task | Staging (makintrak.projectlabs.in), 29 Oct |
|---|---|---|
| B1 | HTTPS redirect | **Not done**: http still answers 200 without redirecting |
| B2 | Host at `/earner-pro/` | **Done** |
| B3 | Lead endpoint | **Blocked**: needs the client's Google account |
| B4 | Listing button | **Done** (WPCode snippet); machine fills in for exact matches only |
| B5 | Menu + homepage banner | Menu item **done**; banner **not done** |
| B6 | Rippa pages | Button **done**; only RL06 pre-loads a machine |
| B7 | Machine data updates | Process not agreed yet |

### B1. HTTPS on the whole site
The live site and staging both answer on plain `http://` without redirecting. Web Share and the PWA install need HTTPS. Staging is behind Cloudflare, so **SSL/TLS → Edge Certificates → Always Use HTTPS** is the simplest switch.
- Make sure the certificate covers `www` and the bare domain.
- 301-redirect all http → https.

### B2. Host the tool at `/earner-pro/`
- **Domain:** to be confirmed with the client. The brief says `makintraks.com.au`, which doesn't resolve. The live site is `makintraks.com`.
- **Upload:** the files listed under "To deploy the tool" in §2 into a real `/earner-pro/` directory in the WordPress root (the folder with `wp-config.php`). WordPress's default rewrite skips real directories (`RewriteCond %{REQUEST_FILENAME} !-d`), so a real folder is served directly, without the theme. Use cPanel's File Manager or SFTP, not the "WP File Manager" plugin, which has a history of serious vulnerabilities.
- **Trailing slash:** check that `/earner-pro?…` redirects to `/earner-pro/?…` with the query string kept. `mod_dir` does this by default.
- **Headers:** set by the repo's `.htaccess`:
  - `index.html`, `sw.js` and `machines.json`: `Cache-Control: no-cache`
  - `manifest.webmanifest`: `Content-Type: application/manifest+json`
  - icons: cached for 30 days
  - gzip/deflate for `.html`, `.json`, `.js`
- **Caches:** exclude `/earner-pro/` from any page-cache plugin, and purge Cloudflare after re-uploading a file.
- **Check:** the footer of the live page reads `EARNER-PRO V3.1`.

### B3. Lead endpoint (Google Apps Script + Google Sheet)
**Requirements:** email `mkntraks@gmail.com` within 1 minute, and log every lead to a Sheet.
- It must run under the **client's Google account**, because emails are sent from it and the Sheet lives there.
- Deploy as a **Web app**: execute as *Me*, access *Anyone*. Give us the `/exec` URL so we can put it in `LEAD_ENDPOINT`.

**Reference sketch (untested; adapt as needed):**

```js
const SHEET_ID = "…";                 // the lead Sheet
const TO = "mkntraks@gmail.com";      // hard-code: never take the recipient from the request

function doPost(e) {
  const sheet = SpreadsheetApp.openById(SHEET_ID).getSheetByName("Leads");
  try {
    const d = JSON.parse(e.postData.contents);
    if (d.type !== "earner-pro-lead" || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(d.lead.email)) throw new Error("bad payload");
    const safe = v => { v = String(v ?? "").slice(0, 500); return /^[=+\-@]/.test(v) ? "'" + v : v; }; // block formula injection
    sheet.appendRow([new Date(), d.submitted_at_local, d.lead.email, d.lead.name, d.lead.mobile,
      d.inputs.machine, d.inputs.price, d.inputs.hours, d.inputs.uc_wear_pct, d.results.verdict,
      d.results.net_week, d.results.annual, d.results.break_even_hrs, d.source].map(safe)
      .concat([JSON.stringify(d)]));   // keep the full payload in the last column
    MailApp.sendEmail({ to: TO, subject: String(d.email.subject).slice(0, 200),
      body: String(d.email.body).slice(0, 5000), replyTo: d.lead.email });
  } catch (err) {
    sheet.getParent().getSheetByName("Errors")?.appendRow([new Date(), String(err), e.postData && e.postData.contents]);
  }
  return ContentService.createTextOutput("ok");
}
```

Points to handle:
- **The endpoint URL is public**, since it's visible in the page source. Validate the payload, cap field lengths, and consider basic rate limiting (e.g. `CacheService` keyed by email). That stops it being used to spam the inbox.
- **Never send to an address taken from the request.**
- **Mail quota:** consumer Gmail allows about 100 Apps Script emails a day, which is plenty for leads.
- **Test case:** submit a test lead from the R18 PRO defaults and check that the email matches §6 exactly and arrives within a minute.

### B4. "Check this machine's viability" button on every listing
**Built on staging** as the WPCode snippet in [wordpress/earner-pro-listing-button.php](wordpress/earner-pro-listing-button.php) (WPCode → Code Snippets: PHP Snippet, Auto Insert, Frontend Only). The repo file is the source of truth: change it there, then paste it into WPCode. If the snippet ever breaks the admin, `/wp-admin/?wpcode-safe-mode=1` loads with all snippets off.

- **Where it hooks:** staging listings are Advanced Product posts (`ap_product`, `/ap-product/<slug>/`), not WooCommerce products. The theme doesn't render them through `the_content`, so the snippet hooks `wp_footer` and a small script inserts the button:
  - used listings: under the "Call our seller" box (`.ap-templaza-sidebar .call-to-buy`, present twice: mobile and desktop sidebars), styled like the theme's "ENQUIRY NOW!" button
  - Rippa pages (separate `rippa-theme` template): in `.rippa-action-bar`, using its own `rippa-btn rippa-btn-outline` classes
- **Skipped:** the attachment and forklift categories, plus any title containing sweep/screen/rake/pusher/mulcher/ripper (one attachment is filed under the D6N machine category).

| Param | Source | Reliability |
|---|---|---|
| `price` | Post meta `ap_price` | Reliable for used listings; Rippa pages have none, so the tool's guide price is used |
| `hours` | Post meta `hours` | Reliable; Rippa pages have none (new stock, 0 is right) |
| `year` | Leading year of the title (after an optional "New") | Reliable |
| `wear` | Not published anywhere | Omitted |
| `machine` | Leading words of the title, with CAT/JD expanded, matched against `machines.json` keys on whole words; plus a small alias list | **29 listings pre-load a machine** (29 Oct crawl of all 139: 117 on the standard template, attachments included, and 22 Rippa). Unmatched listings send no `machine` |
| `src` | The listing's path, e.g. `/ap-product/rippa-rl06/` | Reliable |

- **Why the title, not the Make/Model fields:** Make is "Caterpiller" on every used listing, including John Deere and Komatsu ones, and Model is wrong on Stock No. 030 and 033 (show "D3K2 XL", titles say D6K2 XL). Reported to the client.
- **Whole-word matching is deliberate:** "D3K2 XL" must not silently become "Caterpillar D3". Choosing a stand-in model is the client's call (open question 8). Variants of the same model do match ("D6T XL VPAT" → D6T XL, "D1 LGP" → D1, "336 DL" → 336).
- **Unmatched listings still open on the default R18 PRO** with the listing's price, hours and year. Proposed to the client: the tool asks the buyer to pick the closest model instead, plus an approved closest-model list added to the snippet's aliases.

### B5. Homepage placement
- **Nav:** a top-level menu item **"Earner-Pro"**. Not nested under Used Machinery.
- **Banner:** above the fold on the homepage.
- **Links:** both go to the **blank** tool. Suggested: add `?src=nav` and `?src=home-banner` so analytics can tell them apart.
- **Staging:** the menu item is in (`/earner-pro/?src=nav`, between Finance and About Us). The menu was also tidied: Home removed, Hire + Hire to Buy merged under one Hire item, Price Guide links removed. Still to do: label it "Earner-Pro"; rename Rippa's "Skid Steers" item (it links to `/ap_category/backhoe-loader/`) to "Backhoe Loader"; remove the non-clickable lines under About Us and Contact Us. The homepage is an Elementor page; the banner goes in a new container directly under the hero.

### B6. Rippa pages
- **Live site (audit):** 4 Rippa products: R18 PRO, RL06, RS03, RS06 PRO. RL06's slug has a typo ("ripper").
- **Staging:** 12 Rippa machines (R10, R13, R15, R18, R22, R32, RS03, RS04, RS06, RL06, RB06, RB18) plus forklifts, on a custom template with no Make, Model or price fields. `machines.json` has 4 Rippa models: R18 PRO, R25, RS06 PRO, RL06. Only RL06 pre-loads today, through the alias list in the snippet. R18 → R18 PRO and RS06 → RS06 PRO are ready in the snippet as commented-out lines, waiting for the client to confirm they're the same machines.
- **Open question:** the brief says the Rippa buttons pre-load **R18 PRO**; the alternative is each page's own model. Check with the client.

### B7. Updating machine data
- `machines.json` can be edited and re-uploaded without touching the page.
- Each entry: `{"cat","fuel" (L/hr),"maint" ($/hr),"ucCost","ucLife" (hrs),"ucType":"steel|rubber","price","res"}`.
- **Don't rename keys.** Listing links and the mapping in B4 depend on them.
- Validate the JSON before upload. A broken file shows "Couldn't load machine data".
- Agree with the client who maintains it.

---

## 10. Open questions with the client

| # | Question | What's built now |
|---|---|---|
| 1 | Domain: `.com` or `.com.au`? | Assumes `makintraks.com/earner-pro/` |
| 2 | Rippa buttons: always R18 PRO, or each page's own model? | Not built (B6) |
| 3 | Gate scope: also gate the donut and Whole-of-life cards? | Only the Weekly numbers card (and Share inside it) is gated |
| 4 | Does `rate` mean the finance interest rate? | Yes |
| 5 | Should the lead include the compare machine? | Yes in the payload/Sheet, not in the email |
| 6 | New lead on later visits after the first unlock? | No, the first unlock only |
| 7 | Add hours and wear fields to listings? | — |
| 8 | What should happen for listings not in the 107 machines? Asked 29 Oct, with a proposed "pick the closest model" prompt and a closest-model list for approval | Other params apply to the default machine (see §5, B4) |
| 9 | May we fix the V3.1 toggle bug (below)? | Not fixed |
| 10 | Are staging's Rippa R18 / RS06 the same as the tool's R18 PRO / RS06 PRO? | Not mapped (B6) |
| 11 | Who corrects the listing data (Make on all used listings, Model on Stock 030/033, the D6N sweep's category)? | The snippet works around it |
| 12 | OK to keep the menu changes (Home removed, Hire merged)? | Changed on staging |
| 13 | Does the staging rebuild replace the live site, or do the changes need porting to the live WooCommerce setup? | The snippet only works on the Advanced Product build |

**Known V3.1 bug (awaiting client approval):**
- **Where:** the operator-mode click handler binds to every `.tbtn`, and the Finance/Cash buttons share that class.
- **Effect:** choosing "Paid operator" and then tapping Finance or Cash resets the operator cost to $0 without showing it. The lead email then says owner-operator.
- **Fix:** one line, no maths change.

## 11. Needed from the client

1. Domain confirmation.
2. Hosting access (cPanel/FTP) and a WordPress admin login.
3. A Google account for the Apps Script and Sheet.
4. A logo for the app icon (square PNG, at least 512 px).
5. A GA4 property and Measurement ID.
6. The real `machine-spec-database-v1.json`, only if the V3.1 figures need updating. The client's copy is missing price and resale value.
7. Testing on their own iPhone and Android phones at the end.

## 12. Acceptance checklist (from the client brief)

- [ ] Footer of the live page reads EARNER-PRO V3.1
- [ ] Fast on a phone over 4G; layout correct at 375 px wide
- [ ] Pre-load link opens with machine, price, hours and wear already set
- [ ] Unlocking the report emails the full lead to mkntraks@gmail.com within 1 minute
- [ ] Every lead also lands in the Sheet
- [ ] Share opens the phone's share sheet with the report image attached (iPhone + Android)
- [ ] Add to Home Screen installs with an icon and launches full-screen
- [ ] Analytics shows visits, unlocks and shares after a test run
- [ ] The client has tested end to end on their own phone and received their own test lead

**Out of scope for Stage 1:** accounts, subscriptions, multiple languages, native apps, crowdsourced data, dealer white-label, automatic region detection, and any restyling.
