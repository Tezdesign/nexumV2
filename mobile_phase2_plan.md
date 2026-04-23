# Mobile Phase 2 Implementation Plan: Edit Profile & Notifications

## Objective
Implement the mobile "Edit Profile" page by mirroring the desktop logic and UI (excluding desktop navs), create a dedicated Notifications page with a testable Bootstrap popup, and refine the mobile settings menu.

## Key Files
- `src/Controller/MobilePort/MobileHomeController.php` (Update)
- `templates/mobile/profile/edit.html.twig` (New)
- `templates/mobile/profile/settings.html.twig` (Update)
- `templates/mobile/notifications/index.html.twig` (New)
- `templates/mobile/layouts/main.html.twig` (Update)

## Implementation Steps

### 1. Settings Page Cleanup
- **File:** `templates/mobile/profile/settings.html.twig`
- **Action:** 
  - Change the "Edit Profile" link `href` to `path('app_mobile_profile_edit')`.
  - Remove the "Notifications" button from the menu since the bottom tab bar handles it.

### 2. Mobile Edit Profile Controller Logic
- **File:** `src/Controller/MobilePort/MobileHomeController.php`
- **Action:** 
  - Add a new route `app_mobile_profile_edit` (`/mobile/profile/edit`).
  - Port the exact PHP logic from the desktop `ProfileController::account` method (handling `GET` and `POST` for user details, password confirmation, and profile image `LONGBLOB` updates).
  - Inject `EntityManagerInterface` and `UtilisateurRepository` to handle the persist/flush logic.
  - Redirect back to `app_mobile_profile_edit` on success or error.

### 3. Mobile Edit Profile UI
- **File:** `templates/mobile/profile/edit.html.twig`
- **Action:**
  - Extend `mobile/layouts/main.html.twig` so it includes the bottom navigation bar.
  - Copy the *literal exact* HTML form body and CSS blocks from `templates/user/account.html.twig`.
  - Update the `<form action="...">` to point to `path('app_mobile_profile_edit')`.
  - Remove desktop-specific top/side nav wrappers (since `mobile/layouts/main.html.twig` handles the layout).

### 4. Notifications Page & Popup Component
- **File:** `templates/mobile/notifications/index.html.twig`
- **Action:**
  - Create the alerts page.
  - Add a Bootstrap Toast component (the popup notification) embedded in the page or layout.
  - Add a "Test Popup" button with JS to manually trigger `.toast('show')` on the Bootstrap Toast component for testing.
- **File:** `templates/mobile/layouts/main.html.twig`
- **Action:**
  - Update the "Alerts" bottom nav item to link to `path('app_mobile_notifications')`.

## Verification
- Click "Edit Profile" in Settings -> Verify it opens the exact desktop form but with mobile bottom nav.
- Modify profile details -> Verify it saves to the database correctly.
- Click "Alerts" in the bottom nav -> Verify it opens the new Notifications page.
- Click "Test Popup" -> Verify the Bootstrap toast popup appears on screen.