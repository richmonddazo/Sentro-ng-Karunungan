# BOOKHOLD React Migration

This project was generated from the uploaded BOOKHOLD single-file HTML application.

## Run

```bash
npm install
npm run dev
```

Then open the local Vite URL shown in the terminal.

## What changed

- Added React + ReactDOM.
- Added Vite project structure.
- Moved the app shell/navigation/screen rendering to React state.
- Added React-based Login/Register, Dashboard, Discover Books, Reservations, Downloads,
  Wishlist, Genres, Profile, and Book Details modal.
- Preserved the original HTML as `ORIGINAL_BOOKHOLD.html` for reference.
- Existing localStorage-style persistence is retained for the React state.

## Important

The original upload is a large Vanilla JavaScript application. This is a working React
migration foundation rather than a claim that every original DOM-specific helper has been
mechanically converted. The original file is included so additional screens/behaviors can
be ported without losing the source.
