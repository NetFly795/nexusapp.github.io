# Candy Crush Soda Saga — GitHub Pages build

Static Ruffle client. Progress is stored in the browser (`localStorage` key `ccss-soda-save-v1`).

## Important

Ruffle **cannot** run from `file://` (double-clicking `index.html`). It must be served over **http://** or **https://**.

GitHub Pages gives you HTTPS. Open the Pages site URL, for example:

`https://YOURUSER.github.io/YOURREPO/`

Do not download the repo ZIP and open `index.html` from disk.

## Deploy

1. Create a GitHub repository.
2. Upload **the contents of this `gh-pages` folder** to the repo **root** (or put them in a `docs/` folder).
3. **Settings → Pages → Deploy from a branch** → branch `main` (or `master`), folder `/` (or `/docs`).
4. Wait a minute, then open the HTTPS Pages URL (with a trailing slash is safest).

### Or with git

```bash
cd gh-pages
git init
git add .
git commit -m "Candy Crush Soda Saga static build"
git branch -M main
git remote add origin https://github.com/YOURUSER/YOURREPO.git
git push -u origin main
```

Then enable Pages on `main` / root.

This build is ~156 MB. Keep individual files under GitHub’s 100 MB limit.

## Local preview

From the project root (not by opening the HTML file):

```bash
node standalone/tools/serve-gh-pages.mjs
```

Open `http://127.0.0.1:8765/`.

## Notes

- Rebuild with `python standalone/tools/build-gh-pages.py` after changing the SWF, packs, or Ruffle inject.
- The stage is 800×600 and scales to fit the viewport while keeping that aspect ratio.
- New players start at level 1 with 5 lives and 500 gold. Clearing site data resets progress.
