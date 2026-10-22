# Release notes

Every file in this folder is one release note, shown to users under "Wat is er nieuw" in the update notice at the bottom of the sidebar. The folder is read by `mortelos/ui`; change its location with `mortel-ui.updates.notes_path`.

## File names

The file name is the note's id and sets the order. Two forms are accepted:

- a version: `v1.2.3.md` (the leading `v` is optional, at least one dot, any number of parts);
- a date: `2026-10-20.md` (`YYYY-MM-DD`).

Files with any other name (this README, `draft.md`, `notes.txt`) are ignored. Dates sort before versions, so an app that starts numbering after using dates keeps working. Use one form per app.

## What goes in a note

Write one note per release and list only what is new in that release, in plain language a customer understands: what they can do now, not how it was built. No ticket numbers, no internal names. A note is plain text, not markdown: one line per item, written `- `; any other line is a paragraph. Everything is escaped, so links, images, emphasis and HTML show literally.

Example, saved as `v1.2.0.md`:

```text
- Je kunt nu je profielfoto aanpassen onder Instellingen.
- Uitnodigingen voor nieuwe gebruikers komen sneller aan.
```
