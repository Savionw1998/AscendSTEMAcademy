# Restyle and plugin removal: 2026-09-23 (round 3)

## Pages rebuilt in the Why Choose design
About (2780), Enhancements (2782), Graduation (2783), FAQ (2784), Contact (2785), Resources (5025),
Enrollment Agreement (4448) and Shop (3477). Enrollment (2781), Time Card (6097) and Home (2732) were left alone.

- Each page's content is now plain block HTML (source: `site/restyle/<page>.html`) and Elementor is switched off
  for that page (`_elementor_edit_mode` = `off`). The Elementor design is still stored on each page; clicking
  "Edit with Elementor" opens the OLD design, and saving it would replace the new one.
- All styles live in Appearance > Customize > Additional CSS (source: `site/restyle/additional-css.css`),
  scoped to `.asa-why`. Undo token for this CSS change: 2736573e1fcbabff7b907717a1496fa7.

## Replacing the Ascend Site Updates plugin (no extra plugin)
`site/snippets/ascend-site-snippet.php` goes into Code Snippets ("Run snippet everywhere"). It does nothing while
the plugin is still active, so there is never a double discount. It provides:
- the `[ascend_latest_guides]` shortcode (Blog page),
- the sibling discount,
- the /blog and /register redirects,
- the phone number in the time card's no-JavaScript message,
- the Shop page fix: /shop/ shows the page's own content instead of WooCommerce's default product list,
- the Enrollment form: logged-out visitors go to /login/ and come back after logging in.

Order: restore the Home page from Tools > Ascend Site Updates (Restore) first, then add and activate the
snippet, then deactivate and delete the plugin.

## Time Card access
The Time Card page is limited by Ultimate Member to logged-in users, which is why the app shortcut opens a
nearly empty page. To make it public: edit the Time Card page, and in the "Ultimate Member: Content Restriction"
box set access to "Everyone". Linking a guest's submission to an account by name and email needs a change inside
the Ascend Enrollment & Time Cards plugin (its source is not in this repo).

## Google sign-in prompt
It comes from Site Kit's "Sign in with Google" module (One Tap on). It only logs someone in when their Google
email matches an existing account email; it does not create accounts unless "Anyone can register" is on.
