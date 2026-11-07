# Nexus 7 Legacy

Reconstructed snapshots of early Nexus shells, compared against sibling folders on disk:

| Folder on disk | Era | Reference |
|----------------|-----|-----------|
| `n7legacy/` (here) | **Nexus 7** | `../../submenus/home.html`, `../../submenus/settings.html` (parent `Nexus 7 Social/`) |
| `n7social/` (here) | **Nexus 7 Social** | `../Nexus 7 Social/app.html`, `../../dash/` |
| `../Nexus 9/` (standalone) | **Nexus 8** | Split view, `theme-sync.js`, liquid glass |
| `app.html` (repo root) | **Nexus 9** | Luna, Connected Center, glass dash |

## Entry points

| URL | What you get |
|-----|----------------|
| **`n7legacy/index.html`** | Tile home, **Social** tab, 5-step setup |
| **`n7social/index.html`** | Glass **dashboard** home, **Connected** tab, 3-step setup |
| **`app.html`** | Current Nexus 9 |

Serve the repo root over HTTP and open either legacy URL.

## Nexus 7 (`n7legacy/`)

### What this replicates

| Feature | Nexus 7 (this folder) | Later versions |
|--------|------------------------|----------------|
| Home | Animated **300×300 tile grid** with quotes | N7 Social `dash/`, then N9 glass dash |
| Navigation | Floating **pill dock** (mouse-lit gradient) | Dock cluster + Luna + sidebar |
| Social | **Social** tab → `social.html` (local Nexus Pay + remote tabs) | N7 Social **Connected**, then Connected Center |
| Settings | Original bento **`Nexus 7 Social/submenus/settings.html`** | Nexus 9 `submenus/settings.html` adds theme-sync / dark mode |
| Applets | **Not in dock** (unfinished; `applets/` is a later artifact) | N7 Social ✨ popup, then Luna |
| Themes | 5 light tints via `data-theme` | `theme-sync.js` + dark premium |
| Setup | 5 steps (name, photo, bio, theme, welcome) | N7 Social: 3 steps |

### File map

```
n7legacy/
  index.html      Shell (iframe host + dock)
  home.html       Tile home (from submenus/home.html)
  social.html     Social hub (Nexus Pay tabs)
  settings.html   Redirect → ../../submenus/settings.html (parent Nexus 7 Social tree)
  applets.html    Unfinished applet store (not linked from dock)
  css/            Shell, home, social (+ unused applets/settings stubs)
  js/             theme.js, shell.js, home.js, social.js
```

Shared assets load from the parent repo (`../icons/`, `../submenus/images/`, `../moderngame/`, `../applets/`).

## Nexus 7 Social (`n7social/`)

Thin shell that reuses `n7legacy/js/shell.js` with `N7_CONFIG`:

- **Home** → `../../dash/index.html` (Nexus 7 Social dashboard in parent folder)
- **Connected** → `../submenus/social.html`
- **Settings** → `../../submenus/settings.html` (parent folder — original N7 bento settings)
- **Setup** → 3 steps (name → theme → welcome), matching `Nexus 7 Social/app.html`

Requires the parent `Nexus 7 Social/` tree (`dash/`, `bank/`) to exist beside `Nexus 9/`.

## localStorage

Uses the same keys as the modern app (`nexus_theme`, `nexus_username`, `nexus_setup_complete`, `nexus_button_order`, etc.) so profile and theme carry over when switching shells.

## Source remnants used

- `submenus/home.html` — tile layout
- `Nexus 7 Social/submenus/settings.html` — **original Nexus 7 settings** (bento grid, light themes only)
- `Nexus 7 Social/submenus/social.html` — Connected iframe tabs
- `Nexus 7 Social/bank/` — local Nexus Pay for legacy social
- `Nexus 7 Social/app.html` — dash preload, Connected label, shorter setup

## Not replicated (intentionally)

- Nexus 8 split-view multitasking (`Nexus 9/app.html`)
- WebGL dock, aurora video, Luna, Connected Center — **Nexus 9**
- Liquid glass canvas dock — **Nexus 8** (`apppadded.html`)
