# Browser tests

The Codeception suites (`tests/`, see [DEVELOPER.md](DEVELOPER.md)) check the PHP side: the core API, the events,
the overridden views and the server-side rendering. They cannot check what happens in the browser: layout and CSS,
JavaScript behavior, responsive display, the emails as shown in a real webmail. This file is a prompt for an AI
agent that drives a browser, covering exactly that. Run it on a test instance **after every HumHub core upgrade**,
after an upgrade of a module this one depends on (theme, other modules listed in `module.json`), and before a
release that changes JS, CSS or views.

Duration: about 15 minutes for the full run; about 5 minutes for the **quick run**, which is made of the tests marked `[quick]` and is enough after a core upgrade.

## Running the tests with Claude Code and Claude in Chrome

Claude Code can drive the browser itself through the Claude in Chrome extension, so nobody has to click or forward
screenshots by hand:

1. Open the testing HumHub instance in Chrome, with the Claude in Chrome extension enabled, logged in with an
   administrator account. In the extension, select a **Claude Sonnet** model.
2. Enable the Chrome integration in Claude Code: type `/chrome` in the running session if the extension is enabled
   by default, otherwise exit the session and restart it with `claude --chrome --resume` (this resumes the same
   conversation).
3. Ask Claude Code to run the browser tests of this file. It reads the instructions below, then opens tabs,
   clicks, fills in forms, reads the console errors and takes screenshots. It sees the screenshots itself, so you
   do not need to forward them. It writes its findings to `browser-tests-report.md`.

Prompt to copy and paste into Claude Code:

```text
Run the browser tests of docs/DEVELOPER-BROWSER-TESTS.md on the HumHub instance open in Chrome, by driving
Claude in Chrome (with a Claude Sonnet model).

Before starting, ask me these two questions and wait for my answers:
- Should the module screenshots (`resources/screen_*.png`, listed in `module.json`) be retaken? If yes, all of
  them, or which ones?
- Should the full tests be run (e.g. after a major core upgrade), or only the tests where something might have
  broken since a previous version? In that case, since which version? Then analyze `docs/CHANGELOG.md` and the
  commits since that version (`git log v<version>..HEAD`), tell me which tests you will run and why, and run only
  those (plus the setup and the `[quick]` tests).

Then:

1. Read the file and give Claude in Chrome its "Instructions for the browser agent" section, then let it run the
   tests. Relay its stop-and-warn messages to me.
2. When it reports issues, fix them in the module code (minimal changes, the module's existing code style). Do not
   commit anything.
3. If a fix needs the cache to be flushed or a migration to be run, ask Claude in Chrome to do it from the
   administration (for example Administration > Settings > "Flush cache" button, and
   Administration > Information > Database to run the migrations).
4. Ask Claude in Chrome to retest the tests that failed, plus the ones the fix may have affected, and check that
   each issue is really fixed. Fix what is still broken, or what the fix broke.
5. Repeat steps 2 to 4 until every issue is fixed. After 3 retests with issues still open, stop and ask me
   whether to continue.
6. If I asked for new screenshots, have Claude in Chrome retake them once all issues are fixed (same pages,
   names and sizes as the current ones, 1:1 pixel, showing realistic data without the `QA ` or `[TEST]` test names) and replace the files
   in `resources/`.
7. At the end, give me the list of the issues found and fixed (with the files changed), the issues left open, and
   the steps skipped because they need a human.
```

Some steps still need a human (for example a native browser dialog): the agent asks, or skips them and lists them
as skipped in the report.

---

## Instructions for the browser agent

You are checking, in a real browser, that the "Move content and users" module of HumHub works correctly: display (CSS),
JavaScript behavior, responsive layout and emails. You report problems for the developer (another Claude) who
will fix them: you do **not** fix anything, and you do not change the module or HumHub settings unless a test
asks for it.

### Parameters

- `HUMHUB` = the HumHub test instance currently open in the browser. You should already be logged in there with an administrator account.
- Test users. Each constant is a test user, identified by its email.

  - `ADMIN` = `cuzyapp@proton.me`, member of the "Administrator" group
  - `TEST1` = `test1.oven017@passinbox.com`, standard user — source user
  - `TEST2` = `test2.drove652@passinbox.com`, standard user — target user

### Setup: test accounts

Before running the tests:

1. Open `HUMHUB/admin/user/list` and check that you are logged in as an administrator (the page opens). If not,
   stop and ask.
2. For `ADMIN` and each `TESTn` defined in the parameters, search the email in the list
   (`HUMHUB/admin/user/list?UserSearch%5BfreeText%5D=<email>`, URL-encode the `@` as `%40`).
   - Found and enabled: note its ID and username; for `ADMIN`, check it is in the "Administrator" group. Then set
     a new random strong password (at least 16 characters, letters, digits and symbols) in
     `HUMHUB/admin/user/edit?id=<ID>` → "Password" tab, save, and note it. Skip this for the account you are
     currently logged in with.
   - Found but disabled or unapproved: note it and ask before changing it.
   - Not found: create it in `HUMHUB/admin/user/add`:
     - Username: `test-admin` for `ADMIN`, `test-user-<n>` for `TESTn`
     - Email: the account's email
     - Password: a random strong password (at least 16 characters, letters, digits and symbols) that you note
     - First name / Last name: `Test` / `Admin`, or `Test` / `User <n>`
     - Group: "Administrator" for `ADMIN`, the default group otherwise
     - Save, then check the user appears in the list as enabled. Note its ID and that **you created it**.
3. Write down a table of the accounts (role, ID, username, email, created by you yes/no), **without the
   passwords**: keep them only for yourself during the run. It goes in the report.

To switch account, log out (user menu, top right → "Logout"), then log in with the other account and the
password you set. Do the tests grouped by account to limit the switches, and log back in as `ADMIN` at the end.
Do not use the "Impersonate" action: its confirmation is a native browser dialog that needs a human click.

Legal pages: you are expressly authorized to accept all legal pages and consents HumHub or the module shows
(Terms and Conditions, Privacy Policy, cookie banner, "I agree" checkboxes…), for every test account, so they
do not block the tests.

### Rules

- Keep DevTools open (Console + Network, "Preserve log"), and look at the console on **every** page. Any
  JavaScript error or failed request (4xx/5xx) on a HumHub page is an issue, even if the page looks fine. Ignore
  the noise that is not about this module: warnings from browser extensions, third-party scripts and fonts
  blocked by your own browser, and `favicon.ico` 404s.
- Check each screen at 1440 px and 390 px wide (responsive mode), unless the test says otherwise. These exact
  widths are not always possible (the screen is smaller than the window you need, or the browser cannot be resized
  or emulated): when one is not possible, use the closest width you can get (as wide as possible for 1440, as
  narrow as possible for 390) and write the real width in the report.
- Check light and dark mode when the theme supports it, unless the test says otherwise.
- Log out for the logged-out tests (or use a private window).
- Only create test data (contents, spaces, comments…) with the test accounts, and prefix any title or text you
  type with `[TEST]` so it is easy to find and delete afterwards.
- **Settings changed:** every time a test changes a module or HumHub setting, add it to a list (setting, original
  value, new value). The cleanup restores them.
- **Screenshots:** for every page (or screen state) where you find an issue, take a screenshot at the viewport
  where it appears (both 1440 and 390 if it appears on both), as soon as you see it and before changing page. Add
  a close-up of the faulty element when the issue is small. The screenshots are not saved as files: label each one
  `BT-<nn>-<viewport>` (e.g. `BT-03-390`) and describe in the report precisely what it shows. No screenshot is
  needed for the tests that pass.
  Screenshots must be **1:1 pixel**, at the screen's native resolution: do not resize, scale down or compress
  them, and do not use a "fit to size" or low-quality option of your tool. If your tool still reduces the image
  (the text of the page is blurry or smaller than on the screen), capture only the faulty area at its real size
  (the element, or the visible part of the page scrolled to it, or zoom on a region) instead of the whole page.
- **Do not get stuck:** when a step needs something you cannot do (a CAPTCHA, a native browser dialog, an API key
  or a file you do not have, a phone, the command line), do not retry in a loop and do not work around it by
  entering credentials yourself. Skip the step, say why in the report (it counts as skipped) and, if the rest of
  the test depends on it, ask the user.
- **Stop and warn:** stop the tests, without running the next ones, and warn the user in the chat first (what
  blocks, where, with the evidence), then ask what to do, when:
  - you find a bug that would affect the rest of the tests (for example the login or the module's main page gives
    an error page, a JavaScript error breaks every page, nothing can be saved);
  - a configuration does not allow to run the tests correctly (a required module disabled, a required setting off, a prerequisite you cannot create).
  Any other failure does not stop the run: note it and continue with the next test.
- If a step fails (and it is not a reason to stop), note it and continue with the next test.

### Tests

**T1. Moving a user's content** [quick] — as `ADMIN`, Administration > Users > Move content (`HUMHUB/move-content/user/content`)
- Covers: files and records (comments, likes, reactions, orders…) are now moved with one update per table, and tables
  without an author column are skipped.
- Data: as `TEST1`, create a post `[TEST] move` with a file, comment a post of `TEST2`, like it and react to it (if the
  Reactions module is enabled). As `ADMIN`, move `TEST1`'s content to `TEST2`: the job finishes without error.
- Data: as `TEST2`, the post `[TEST] move`, its file, the comment, the like and the reaction now show `TEST2` as author,
  and the moved post keeps its place in the stream (it is not moved to the top).

**T2. Same like or reaction on both sides** — as `ADMIN`
- Covers: a move failed when the target user had already liked or reacted to the same content as the source user.
- Data: as `TEST1` and as `TEST2`, like the same post and react to it with the same emoji. Move `TEST1`'s content to
  `TEST2`: no error; the post shows one like and one reaction of `TEST2` (counts not doubled).

**T3. Move group users and space content** — as `ADMIN`, Administration > Users > Move group users, and a space's
"Move content"
- Display: both pages open, with their forms and hints, and refuse an empty form with a message under the fields.

### Cleanup

Once all tests are finished and the report is written:

1. List the test data you created (contents, comments, spaces…), the settings you changed (from the "Settings
   changed" list) and the test accounts **you created** during the setup (never the ones that already existed).
2. Restore the changed settings to their original values, and say which ones you could not restore.
3. Ask whether the test data and the accounts you created should be deleted. Do not delete anything without a
   "yes".
4. If yes: delete the test data, then delete each account you created in `HUMHUB/admin/user/list` → gear button →
   "Delete" (choose to also delete the contents of the user). Check they no longer appear in the list.

### Report

Produce one file, `browser-tests-report.md`, and nothing else (if you cannot write files, give the report in the
chat). **Never write a password in it**: if a test account needs one later, the developer resets it.

1. Summary: whether the run was stopped early (and why, with the warning), instance, HumHub version (Administration > Information), module version, date, browser, tests run /
   skipped (and why), and the table below.

   | Test | Result (passed / issues / skipped) | Issues |
   |------|------------------------------------|--------|
   | T1   | issues                             | BT-01, BT-03 |

2. The test accounts table from the setup (no passwords).
3. One section per problem, most severe first:

```
### BT-01 — <one-line title>
- Severity: blocker | major | minor | cosmetic | question
- Test: T1
- URL: <exact URL>
- Viewport: 1440 | 390 | both (the real width in px when it is not one of these)
- Theme mode: light | dark | both
- Logged in as: guest | ADMIN | TEST1 | …
- Steps to reproduce: 1. … 2. …
- Expected / Actual: …
- Evidence: screenshot label and what it shows (see "Screenshots" rule); console errors (exact text); failing requests (method, URL, status);
  outerHTML of the faulty element and its relevant computed styles for a visual problem
```

   Severity: **blocker** = the feature cannot be used, data is lost or exposed, or a PHP/JavaScript error page;
   **major** = the feature works but a main step is broken or very hard to use; **minor** = a secondary
   problem with a workaround; **cosmetic** = display only, the feature works; **question** = unclear whether it is
   a problem, the developer decides.

4. The list of the tests that passed, one line each.
5. Skipped steps (`human only` or blocked) and why.
6. Cleanup: the settings restored, what was deleted, what was kept.
