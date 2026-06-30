# C. UI/UX Plan

## Sitemap

- Landing Page
- Login
- Register
- User Dashboard
- Projects
- Create New Project
- Project Detail / Upload Video
- Video Analysis Result
- Clip Selection
- Subtitle Editor
- Render Preview
- Rendering Progress
- Output Library
- Trend Checker
- Admin Dashboard
- Admin Users
- Admin Projects
- Admin Settings

## User Journey

### Creator Journey

1. Login.
2. Create New Project.
3. Upload video.
4. Wait for AI analysis.
5. Review detected niche.
6. Review clip candidates.
7. Edit subtitle/caption/hook.
8. Render selected clip.
9. Download output.

### Admin Journey

1. Login as admin.
2. Monitor platform metrics.
3. Inspect failed render.
4. Open project detail.
5. Adjust user upload limits.
6. Adjust templates/presets.

## Layout Style

Visual direction:

- Dark premium SaaS interface.
- Sidebar navigation.
- Card-based workspace.
- High contrast typography.
- Cyan/violet accent gradient.
- Clear progress/status states.
- Non-technical microcopy.

## Key Components

- `status-badge`: status pending/processing/completed/failed.
- `progress-bar`: render queue progress.
- `empty-state`: guided empty states.
- Project cards.
- Analysis cards.
- Clip candidate cards.
- Render form.
- Trend report panel.
- Admin table.

## Important UX Decisions

- Niche detection appears before rendering to prevent wrong template/caption choices.
- Render is user-confirmed, not automatic, so user can inspect and edit.
- YouTube source is treated as authorized source metadata in dummy build for compliance.
- Output copy is shown as copyable title/caption/hashtags.
- Admin can inspect failed jobs to reduce support friction.
