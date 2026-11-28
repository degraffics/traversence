```json
[
{"id":"f01","file":"search_listings.php","lines":"407-424","finding":"Map view returns exact lat/lon (and phone) of confidential listings to signed-out callers, with no isConfidential check.","severity":"high","fix":"Add category_id to the pin SELECT; skip or null the point when the listing is confidential."},
{"id":"f02","file":"revert.php","lines":"61-84","finding":"The forced password reset stated in the comment is not implemented.","severity":"high","fix":"Set a must-reset flag or an unusable password hash inside the transaction (needs a human decision)."},
{"id":"f03","file":"login.php","lines":"11-14, 22-31","finding":"No brute-force limiter; the captcha is off until keys are set.","severity":"high","fix":"Add per-IP and per-account failure counters and return 429."},
{"id":"f04","file":"claim.php","lines":"96-117","finding":"Claim actions return or act on any claim_id, exposing text_code and phones, with no ownership check at the endpoint. High only if the library lacks the check.","severity":"high","fix":"Add an ownership guard as at :109 before the switch."},
{"id":"f05","file":"search.php","lines":"68-71","finding":"The ?removed= GET performs a staff block with no CSRF protection.","severity":"med","fix":"Null the user id on GET; move the staff block to a CSRF-protected POST."},
{"id":"f06","file":"upload.php","lines":"38","finding":"The CSRF check passes when the session token is empty, so form posts are accepted. High if token-less sessions exist.","severity":"med","fix":"Reject an empty session token."},
{"id":"f07","file":"trips.php","lines":"157-159","finding":"PDOException text is echoed, including on guest paths.","severity":"med","fix":"Catch PDOException and return a generic 500."},
{"id":"f08","file":"stg-bar.php","lines":"16-18","finding":"An empty RELEASE_BAR_KEY is not refused (conditional).","severity":"med","fix":"Refuse the request if $key === '', as staging-updates.php:16 does."},
{"id":"f09","file":"tag-card.php","lines":"15,17","finding":"A hidden or held thing is still served from cache for up to ~70 minutes; may expose private places.","severity":"med","fix":"Clear the cache key on hide or hold; add a private cache header."},
{"id":"f10","file":"wire-card.php","lines":"11-12","finding":"The code-entry step is not tied to the requester (verify).","severity":"med","fix":"Set a session marker at code verification and check it."},
{"id":"f11","file":"revert.php","lines":"80-81","finding":"No collision check on the restored address; may create a duplicate email if there is no unique index.","severity":"med","fix":"Apply the same id != self check as confirm-change.php and return 409."},
{"id":"f12","file":"login.php","lines":"13-14","finding":"The captcha bypass for TestAccounts is keyed on email only (verify).","severity":"med","fix":"Require a server secret compared with hash_equals."},
{"id":"f13","file":"login.php","lines":"25-26","finding":"The 409/403 responses may reveal that an account exists (verify).","severity":"med","fix":"Return those codes only after the password verifies."},
{"id":"f14","file":"request-reset.php","lines":"14,21-35","finding":"No throttle on request-reset or register while the captcha is off, which allows mail bombing.","severity":"med","fix":"Add per-IP and per-target limits."},
{"id":"f15","file":"resend-confirmation.php","lines":"14-16","finding":"The resend limit is per session only, and there is no captcha.","severity":"med","fix":"Limit server-side per email and per IP."},
{"id":"f16","file":"change-password.php","lines":"24-28","finding":"The current password can be guessed without limit (also request-change.php:29-34).","severity":"med","fix":"Add a per-user failure counter."},
{"id":"f17","file":"claim.php","lines":"125","finding":"The dispute response hands out the other party's claim_id.","severity":"med","fix":"Return only dispute_id."},
{"id":"f18","file":"claim.php","lines":"109-110","finding":"Sibling addresses are returned at any claim status.","severity":"med","fix":"Require a confirmed claim or omit the address."},
{"id":"f19","file":"evidence.php","lines":"30","finding":"The 'use' action skips the dispute freeze and the admin queue for protected fields (queue part needs verifying).","severity":"med","fix":"Add a Claims::frozen check and queue protected fields for admin."},
{"id":"f20","file":"leave.php","lines":"46,48,57","finding":"Bearer-code actions have no visible rate limit; 'check' returns contact details; resend can repeat mail (verify).","severity":"med","fix":"Rate-limit failed codes and throttle resend per code."},
{"id":"f21","file":"upload.php","lines":"52-54","finding":"PDOException message is echoed.","severity":"low","fix":"Catch PDOException and return a generic message."},
{"id":"f22","file":"upload.php","lines":"24","finding":"File and line number appear in the error JSON.","severity":"low","fix":"Return a generic message."},
{"id":"f23","file":"trips.php","lines":"77,134,147,152","finding":"The unvalidated tz value is stored.","severity":"low","fix":"Validate once at :44."},
{"id":"f24","file":"stg-bar.php","lines":"31-32","finding":"CR/LF in an upstream value can reach the Cookie header.","severity":"low","fix":"Restrict the value to [A-Za-z0-9._-]*."},
{"id":"f25","file":"stg-csp.php","lines":"8-16","finding":"Unauthenticated writes to the Held ledger; possible stored XSS of $blocked.","severity":"low","fix":"Check host or content type, cap the rate, and escape on the Held page."}
]
```