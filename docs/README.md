# AdenaLedger — docs

Both files are full project overviews intended as a presentation document
for EGlobal (LU4 owners). Same content, two languages:

- [`adenaledger-overview.es.md`](./adenaledger-overview.es.md) — Spanish
- [`adenaledger-overview.en.md`](./adenaledger-overview.en.md) — English

Both make explicit that **AdenaLedger is non-profit**: hobby project, free
for the community, no monetisation plans. The partnership proposal at the
end of each is about visibility, not money.

## Export to PDF

The YAML front matter at the top of each file is set up for [Pandoc](https://pandoc.org/)
with a LaTeX backend. With Pandoc + a LaTeX distro (`texlive` on Linux,
MacTeX on macOS) installed:

```bash
# Spanish PDF
pandoc docs/adenaledger-overview.es.md \
    -o docs/adenaledger-overview.es.pdf \
    --pdf-engine=xelatex \
    --toc \
    --toc-depth=2

# English PDF
pandoc docs/adenaledger-overview.en.md \
    -o docs/adenaledger-overview.en.pdf \
    --pdf-engine=xelatex \
    --toc \
    --toc-depth=2
```

The result is an A4 PDF with a generated table of contents, 11pt body,
Helvetica main font.

### Alternatives without LaTeX

If installing LaTeX is overkill, any of these work and are zero-setup:

- **VS Code**: install the *Markdown PDF* extension, open the file,
  Cmd/Ctrl-Shift-P → "Markdown PDF: Export (pdf)".
- **macOS**: open the `.md` in [Typora](https://typora.io/) or
  [MacDown](https://macdown.uranusjr.com/) → File → Export → PDF.
- **Online**: paste into [dillinger.io](https://dillinger.io/) and export
  PDF from the menu.
- **Browser**: render in a markdown viewer (e.g. open with `grip`) then
  Cmd/Ctrl-P → "Save as PDF".

The YAML front matter is harmless to those tools — they treat it as
metadata or just ignore it.
