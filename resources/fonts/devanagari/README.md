# Devanagari fonts for Marathi PDF (mPDF)

**Ship these with the project** (git-tracked):

- `NotoSansDevanagari-Regular.ttf`
- `NotoSansDevanagari-Bold.ttf`

(SIL Open Font License)

## Install / repair

Copied to `storage/fonts` by:

- `/install` and Server maintenance deploy
- **Server maintenance → Install / repair PDF fonts**
- `php artisan krushi:pdf-fonts` (add `--force` to overwrite)

`App\Support\PrintFonts` + `App\Support\PdfMaker` register them with **mPDF**.

If these TTFs are missing from a deploy, Marathi PDF falls back to English — push this folder with the code, then re-run install fonts.
